<?php

namespace Pionia\Cache\Adapters;

use Pionia\Cache\Concerns\ValidatesCacheKeys;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\Contracts\PrunableCacheAdapterInterface;
use Pionia\Cache\InvalidCacheArgumentException;
use Pionia\Cache\Support\CacheTtl;

/**
 * PSR-16 filesystem cache store (Symfony FilesystemAdapter replacement).
 */
final class FilesystemCacheAdapter implements CacheAdapterInterface, PrunableCacheAdapterInterface
{
    use ValidatesCacheKeys;

    public function __construct(
        private readonly string $directory,
        private readonly int $defaultTtl = 0,
    ) {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0777, true) && !is_dir($this->directory)) {
            throw new InvalidCacheArgumentException(sprintf('Cache directory "%s" is not writable.', $this->directory));
        }
    }

    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);
        $path = $this->pathFor((string) $key);

        if (!is_file($path)) {
            return $default;
        }

        $payload = $this->readPayload($path);
        if ($payload === null) {
            unlink($path);

            return $default;
        }

        return $payload['value'];
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);
        $expiresAt = CacheTtl::expiresAt($ttl, $this->defaultTtl);
        $path = $this->pathFor((string) $key);
        $encoded = json_encode([
            'expires' => $expiresAt,
            'value' => base64_encode(serialize($value)),
        ], JSON_THROW_ON_ERROR);

        return file_put_contents($path, $encoded, LOCK_EX) !== false;
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);
        $path = $this->pathFor((string) $key);

        if (!is_file($path)) {
            return true;
        }

        return unlink($path);
    }

    public function clear(): bool
    {
        foreach (scandir($this->directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->directory . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                unlink($path);
            }
        }

        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $this->set($key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple($keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->delete($key) && $ok;
        }

        return $ok;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);
        $path = $this->pathFor((string) $key);

        if (!is_file($path)) {
            return false;
        }

        return $this->readPayload($path) !== null;
    }

    public function prune(): bool
    {
        $pruned = false;

        foreach (scandir($this->directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->directory . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }

            if ($this->readPayload($path) === null) {
                unlink($path);
                $pruned = true;
            }
        }

        return $pruned;
    }

    private function pathFor(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    /**
     * @return array{value: mixed}|null
     */
    private function readPayload(string $path): ?array
    {
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            /** @var array{expires: int|null, value: string} $payload */
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!array_key_exists('value', $payload)) {
            return null;
        }

        $expires = $payload['expires'] ?? null;
        if (CacheTtl::isExpired($expires !== null ? (int) $expires : null)) {
            return null;
        }

        try {
            $value = unserialize(base64_decode((string) $payload['value'], true), ['allowed_classes' => true]);
        } catch (\Throwable) {
            return null;
        }

        return ['value' => $value];
    }
}
