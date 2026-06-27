<?php

namespace Pionia\Utils;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Minimal ULID parser for timestamp extraction (Symfony UID replacement).
 */
final class Ulid implements \Stringable
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private function __construct(
        private readonly string $value,
        private readonly DateTimeImmutable $dateTime,
    ) {
    }

    public static function isValid(string $value): bool
    {
        if (strlen($value) !== 26) {
            return false;
        }

        $value = strtoupper($value);

        return preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $value) === 1;
    }

    public static function fromString(string $value): self
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid ULID "%s".', $value));
        }

        $value = strtoupper($value);
        $timestampMs = self::decodeTimestamp($value);

        return new self(
            $value,
            DateTimeImmutable::createFromFormat('U.u', sprintf('%.3F', $timestampMs / 1000))
                ?: new DateTimeImmutable('@0'),
        );
    }

    public function getDateTime(): DateTimeInterface
    {
        return $this->dateTime;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function decodeTimestamp(string $ulid): int
    {
        $time = 0;
        $length = strlen(self::ENCODING);

        for ($i = 0; $i < 10; $i++) {
            $char = $ulid[$i];
            $index = strpos(self::ENCODING, $char);
            if ($index === false) {
                throw new InvalidArgumentException(sprintf('Invalid ULID character "%s".', $char));
            }

            $time = ($time * $length) + $index;
        }

        return $time;
    }
}
