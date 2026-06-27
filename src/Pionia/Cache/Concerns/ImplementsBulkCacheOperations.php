<?php

namespace Pionia\Cache\Concerns;

trait ImplementsBulkCacheOperations
{
    public function getMultiple($keys, $default = null): iterable
    {
        $this->assertValidKeys($keys);
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
        $this->assertValidKeys($keys);
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->delete($key) && $ok;
        }

        return $ok;
    }
}
