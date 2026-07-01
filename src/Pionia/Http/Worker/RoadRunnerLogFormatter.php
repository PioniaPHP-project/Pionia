<?php

namespace Pionia\Http\Worker;

/**
 * Turns RoadRunner zap/console log lines into compact terminal output.
 */
final class RoadRunnerLogFormatter
{
    public static function format(string $line): string
    {
        $line = rtrim($line, "\r");
        if ($line === '') {
            return '';
        }

        $json = self::extractTrailingJson($line);
        if ($json === null) {
            return self::compactPlainLine($line);
        }

        if (isset($json['status'], $json['method'])) {
            return self::formatHttpAccess($line, $json);
        }

        return self::formatStructured($line, $json);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function extractTrailingJson(string $line): ?array
    {
        if (!preg_match('/\s(\{.+})$/', $line, $matches)) {
            return null;
        }

        $decoded = json_decode($matches[1], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatHttpAccess(string $line, array $data): string
    {
        $time = self::extractTime($line, $data);
        $method = str_pad((string) $data['method'], 6);
        $uri = (string) ($data['URI'] ?? $data['URL'] ?? $data['request'] ?? '/');
        $status = (int) $data['status'];
        $size = self::humanBytes(self::responseBytes($data));
        $duration = self::formatDuration($data);

        $statusTag = self::statusTag($status);

        return sprintf(
            '%s  %s  %s  %s  %s  %s',
            $time,
            $method,
            $uri,
            $statusTag,
            str_pad($size, 8),
            $duration,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatStructured(string $line, array $data): string
    {
        $time = self::extractTime($line, $data);
        $level = self::extractLevel($line);
        $channel = self::extractChannel($line);
        $message = self::extractMessage($line);

        if (isset($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        }

        $parts = array_filter([$time, $level, $channel, $message], static fn (string $part): bool => $part !== '');

        return implode('  ', $parts);
    }

    private static function compactPlainLine(string $line): string
    {
        if (preg_match(
            '/^(?<ts>\S+)\s+(?<level>\S+)\s+(?<channel>\S+)\s+(?<message>.+)$/u',
            $line,
            $matches,
        )) {
            $time = self::shortTime($matches['ts']);
            $message = trim($matches['message']);

            return sprintf(
                '%s  %-5s  %-8s  %s',
                $time,
                $matches['level'],
                $matches['channel'],
                $message,
            );
        }

        return $line;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractTime(string $line, array $data): string
    {
        if (isset($data['start']) && is_string($data['start'])) {
            return self::shortTime($data['start']);
        }

        return self::extractTimeFromLine($line);
    }

    private static function extractTimeFromLine(string $line): string
    {
        if (preg_match('/^(\S+)/', $line, $matches)) {
            return self::shortTime($matches[1]);
        }

        return '--:--:--';
    }

    private static function shortTime(string $timestamp): string
    {
        if (preg_match('/(\d{2}:\d{2}:\d{2})/', $timestamp, $matches)) {
            return $matches[1];
        }

        return $timestamp;
    }

    private static function extractLevel(string $line): string
    {
        if (preg_match('/^\S+\s+(\S+)/', $line, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function extractChannel(string $line): string
    {
        if (preg_match('/^\S+\s+\S+\s+(\S+)/', $line, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function extractMessage(string $line): string
    {
        if (preg_match('/^\S+\s+\S+\s+\S+\s+(.+?)(?:\s+\{.+)?$/', $line, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function responseBytes(array $data): int
    {
        foreach (['write_bytes', 'bytes_sent', 'response_bytes'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (int) $data[$key];
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatDuration(array $data): string
    {
        if (isset($data['elapsed']) && is_numeric($data['elapsed'])) {
            $elapsed = (float) $data['elapsed'];

            return $elapsed >= 1000 ? sprintf('%.2fs', $elapsed / 1000) : sprintf('%dms', (int) $elapsed);
        }

        if (isset($data['request_time']) && is_numeric($data['request_time'])) {
            $seconds = (float) $data['request_time'];

            return $seconds >= 1 ? sprintf('%.2fs', $seconds) : sprintf('%dms', (int) round($seconds * 1000));
        }

        return '-';
    }

    private static function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    private static function statusTag(int $status): string
    {
        $label = (string) $status;

        return match (true) {
            $status >= 500 => '<error>' . $label . '</error>',
            $status >= 400 => '<comment>' . $label . '</comment>',
            $status >= 300 => '<comment>' . $label . '</comment>',
            default => '<info>' . $label . '</info>',
        };
    }
}
