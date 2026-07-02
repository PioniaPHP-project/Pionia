<?php

namespace Pionia\Utils;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * ULID generation, validation, and timestamp extraction.
 *
 * Replaces Symfony UID for Pionia's minimal dependency surface. Generation follows
 * the standard 128-bit layout: 48-bit millisecond timestamp + 80-bit randomness,
 * encoded as 26 Crockford base32 characters.
 *
 * @see \Pionia\Security\Security::ulid() Application entry point via `secure_ulid()`
 */
final class Ulid implements \Stringable
{
    /** Crockford base32 alphabet (no I, L, O, U to avoid ambiguity). */
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private function __construct(
        private readonly string $value,
        private readonly DateTimeImmutable $dateTime,
    ) {
    }

    /**
     * Whether `$value` is a syntactically valid ULID (26 chars, valid charset, time nibble).
     */
    public static function isValid(string $value): bool
    {
        if (strlen($value) !== 26) {
            return false;
        }

        $value = strtoupper($value);

        return preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $value) === 1;
    }

    /**
     * Generate a new ULID.
     *
     * @param int|null $timestampMs Unix epoch milliseconds; defaults to `microtime(true)`
     *
     * @throws InvalidArgumentException When timestamp exceeds 48-bit ULID range
     */
    public static function generate(?int $timestampMs = null): string
    {
        $time = $timestampMs ?? (int) (microtime(true) * 1000);

        if ($time < 0 || $time > 0x0000FFFFFFFFFFFF) {
            throw new InvalidArgumentException('Timestamp is out of ULID range.');
        }

        $timeChars = '';

        for ($index = 0; $index < 10; $index++) {
            $timeChars = self::ENCODING[$time % 32] . $timeChars;
            $time = intdiv($time, 32);
        }

        $random = random_bytes(10);
        $randChars = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($random) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $randChars .= self::ENCODING[($buffer >> $bits) & 0x1F];
            }
        }

        return $timeChars . substr($randChars, 0, 16);
    }

    /**
     * Parse a ULID string into an immutable value object with embedded timestamp.
     *
     * @throws InvalidArgumentException When `$value` fails {@see isValid()}
     */
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

    /** Extract the timestamp encoded in the first 10 characters of this ULID. */
    public function getDateTime(): DateTimeInterface
    {
        return $this->dateTime;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** Decode the 48-bit millisecond timestamp from the ULID time component. */
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
