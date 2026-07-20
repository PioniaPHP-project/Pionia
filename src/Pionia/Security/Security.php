<?php

namespace Pionia\Security;

use InvalidArgumentException;
use Pionia\Utils\Ulid;
use RuntimeException;

/**
 * Central cryptographic utilities for Pionia applications.
 *
 * Resolve via `security()`, `realm()->security()`, or the container binding on
 * {@see \Pionia\Realm\AppRealm}. Each public method has a snake_case global
 * helper in `src/Pionia/Utils/helpers.php` (for example `secure_token()`).
 *
 * **Random & identifiers** — CSPRNG bytes/strings, UUID v4, ULID, OTP, API tokens.
 * **Passwords** — generation aligned with the `password` validation rule; wrappers
 * around PHP's `password_hash` / `password_verify`.
 * **Digests** — `hash()`, `hmac()`, timing-safe `equals()` / `verifyHmac()`.
 * **Symmetric encryption** — libsodium secretbox using `APP_KEY` or an explicit key.
 * **Asymmetric encryption** — libsodium box seal/box (X25519) and RSA-OAEP (hybrid).
 * **JWT** — `jwtEncode` / `jwtDecode` / `jwtVerify` (HS256/384/512, RS256) and opaque `jwtRefreshToken()`.
 * **Validators** — static `isUuid()`, `isUlid()`, `isOtp()`, `isToken()` for validation.
 *
 * Extension requirements:
 * - `encrypt()`, box/seal APIs: `ext-sodium`
 * - `rsaEncrypt()` hybrid payloads: `ext-openssl` + `ext-sodium`
 *
 * Maintainer notes:
 * - PHP 8.5+ `sodium_crypto_box()` / `_open()` take a combined key pair — build with
 *   `sodium_crypto_box_keypair_from_secretkey_and_publickey()`.
 * - RSA payloads prefix a version byte: `0x00` = direct OAEP, `0x01` = wrapped secretbox.
 * - Box keys may be base64-encoded strings or raw binary of the expected byte length.
 *
 * @see \Pionia\Utils\Ulid::generate() Used by {@see ulid()}
 */
final class Security
{
    /** Lowercase ASCII letters for {@see randomString()} and {@see password()}. */
    public const ALPHABET_LOWER = 'abcdefghijklmnopqrstuvwxyz';

    /** Uppercase ASCII letters for {@see randomString()} and {@see password()}. */
    public const ALPHABET_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** Digits 0–9 for OTPs and numeric random strings. */
    public const ALPHABET_NUMERIC = '0123456789';

    /** Default alphabet for {@see randomString()}. */
    public const ALPHABET_ALPHANUMERIC = self::ALPHABET_LOWER . self::ALPHABET_UPPER . self::ALPHABET_NUMERIC;

    /** Lowercase hex digits for {@see randomHex()}. */
    public const ALPHABET_HEX = '0123456789abcdef';

    /** Symbol set used when {@see password()} is generated with `$symbols = true`. */
    public const ALPHABET_SYMBOLS = '!@#$%^&*()-_=+[]{}<>?';

    /** Minimum OTP length accepted by {@see otp()} and {@see isOtp()}. */
    private const MIN_OTP_LENGTH = 4;

    /** Maximum OTP length accepted by {@see otp()} and {@see isOtp()}. */
    private const MAX_OTP_LENGTH = 20;

    /** Minimum length for {@see password()} — matches validation rule expectations. */
    private const MIN_PASSWORD_LENGTH = 8;

    // -------------------------------------------------------------------------
    // Random data
    // -------------------------------------------------------------------------

    /**
     * Return cryptographically secure raw bytes.
     *
     * @throws InvalidArgumentException When `$length` is less than 1
     */
    public function randomBytes(int $length): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Length must be at least 1.');
        }

        return random_bytes($length);
    }

    /**
     * Build a random string by rejection sampling to avoid modulo bias.
     *
     * @param string $alphabet Character pool; use the `ALPHABET_*` constants
     *
     * @throws InvalidArgumentException When `$length` is less than 1 or alphabet is empty
     */
    public function randomString(int $length, string $alphabet = self::ALPHABET_ALPHANUMERIC): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Length must be at least 1.');
        }

        if ($alphabet === '') {
            throw new InvalidArgumentException('Alphabet must not be empty.');
        }

        $alphabetLength = strlen($alphabet);
        $result = '';
        $max = 256 - (256 % $alphabetLength);

        while (strlen($result) < $length) {
            $byte = ord($this->randomBytes(1));

            if ($byte >= $max) {
                continue;
            }

            $result .= $alphabet[$byte % $alphabetLength];
        }

        return $result;
    }

    /**
     * Hex-encode `$bytes` random octets (output length = `$bytes * 2`).
     */
    public function randomHex(int $bytes = 16): string
    {
        return bin2hex($this->randomBytes($bytes));
    }

    /**
     * Base64-encode random bytes; URL-safe form strips padding and swaps `+/`.
     */
    public function randomBase64(int $bytes = 32, bool $urlSafe = true): string
    {
        $encoded = base64_encode($this->randomBytes($bytes));

        if (!$urlSafe) {
            return $encoded;
        }

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    /**
     * Generate a random UUID version 4 (RFC 4122).
     */
    public function uuid(): string
    {
        $bytes = $this->randomBytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($bytes), 4),
        );
    }

    /**
     * Generate a sortable ULID (26 Crockford base32 characters).
     *
     * @see Ulid::generate()
     */
    public function ulid(): string
    {
        return Ulid::generate();
    }

    /**
     * Generate a one-time code for MFA, email/SMS verification, etc.
     *
     * @param bool $numericOnly When false, uses {@see ALPHABET_ALPHANUMERIC}
     *
     * @throws InvalidArgumentException When `$length` is outside 4–20
     */
    public function otp(int $length = 6, bool $numericOnly = true): string
    {
        $this->assertOtpLength($length);

        return $this->randomString(
            $length,
            $numericOnly ? self::ALPHABET_NUMERIC : self::ALPHABET_ALPHANUMERIC,
        );
    }

    /**
     * URL-safe random token suitable for session IDs, API keys, and reset links.
     */
    public function token(int $bytes = 32): string
    {
        return $this->randomBase64($bytes, true);
    }

    /**
     * Alias of {@see token()} — semantic name for long-lived secrets.
     */
    public function secret(int $bytes = 32): string
    {
        return $this->token($bytes);
    }

    /**
     * Generate a random password satisfying the framework `password` validation rule.
     *
     * Guarantees at least one lower, upper, digit, and (optionally) symbol before
     * filling the remainder and shuffling with Fisher–Yates (`random_int`).
     *
     * @throws InvalidArgumentException When `$length` is less than 8
     */
    public function password(int $length = 16, bool $symbols = true): string
    {
        if ($length < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Password length must be at least %d.', self::MIN_PASSWORD_LENGTH),
            );
        }

        $required = [
            $this->randomString(1, self::ALPHABET_LOWER),
            $this->randomString(1, self::ALPHABET_UPPER),
            $this->randomString(1, self::ALPHABET_NUMERIC),
        ];

        $pool = self::ALPHABET_LOWER . self::ALPHABET_UPPER . self::ALPHABET_NUMERIC;

        if ($symbols) {
            $required[] = $this->randomString(1, self::ALPHABET_SYMBOLS);
            $pool .= self::ALPHABET_SYMBOLS;
        }

        $remaining = $length - count($required);

        return $this->shuffleString(
            implode('', $required) . $this->randomString($remaining, $pool),
        );
    }

    // -------------------------------------------------------------------------
    // Password hashing (storage)
    // -------------------------------------------------------------------------

    /**
     * Hash a plaintext password for database storage.
     *
     * Uses PHP `PASSWORD_DEFAULT` (currently bcrypt or argon2id depending on build).
     *
     * @param array<string, mixed> $options Passed to `password_hash()`
     */
    public function hashPassword(string $password, array $options = []): string
    {
        return password_hash($password, PASSWORD_DEFAULT, $options);
    }

    /**
     * Verify a plaintext password against a stored hash.
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Whether a stored hash should be re-computed after an algorithm upgrade.
     *
     * Prefer PHP's native `password_needs_rehash()` in application code when possible.
     *
     * @param array<string, mixed> $options Passed to `password_needs_rehash()`
     */
    public function needsRehash(string $hash, array $options = []): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT, $options);
    }

    // -------------------------------------------------------------------------
    // Digests & comparison
    // -------------------------------------------------------------------------

    /**
     * One-way digest via PHP `hash()`.
     */
    public function hash(string $data, string $algo = 'sha256', bool $binary = false): string
    {
        return hash($algo, $data, $binary);
    }

    /**
     * HMAC for webhook signatures and message authentication.
     */
    public function hmac(string $data, string $key, string $algo = 'sha256', bool $binary = false): string
    {
        return hash_hmac($algo, $data, $key, $binary);
    }

    /**
     * Timing-safe HMAC verification.
     *
     * Accepts hex-encoded or raw binary expected values.
     */
    public function verifyHmac(string $data, string $key, string $expected, string $algo = 'sha256'): bool
    {
        $computed = $this->hmac($data, $key, $algo, true);

        if (strlen($expected) === strlen($computed) * 2 && ctype_xdigit($expected)) {
            $expected = hex2bin($expected);
        }

        if (!is_string($expected)) {
            return false;
        }

        return hash_equals($computed, $expected);
    }

    /**
     * Timing-safe string comparison — use for tokens, OTPs, and API secrets.
     */
    public function equals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Generate a CSRF token (alias of {@see token()}).
     */
    public function csrfToken(int $bytes = 32): string
    {
        return $this->token($bytes);
    }

    // -------------------------------------------------------------------------
    // Symmetric encryption (shared secret)
    // -------------------------------------------------------------------------

    /**
     * Encrypt with libsodium secretbox.
     *
     * Payload: base64(`nonce` || `ciphertext`). Key from `$key`, else `APP_KEY`
     * (supports `base64:` prefix), hashed to 32 bytes via `sodium_crypto_generichash`.
     *
     * @throws RuntimeException When ext-sodium is missing or `APP_KEY` is unset
     */
    public function encrypt(string $plaintext, ?string $key = null): string
    {
        $this->assertSodiumAvailable();

        $keyBytes = $this->resolveEncryptionKey($key);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $keyBytes);

        return base64_encode($nonce . $cipher);
    }

    /**
     * Decrypt a payload from {@see encrypt()}.
     *
     * @throws RuntimeException When decryption fails (wrong key or tampered data)
     * @throws InvalidArgumentException When payload is malformed
     */
    public function decrypt(string $payload, ?string $key = null): string
    {
        $this->assertSodiumAvailable();

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Encrypted payload is not valid base64.');
        }

        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if (strlen($decoded) < $nonceLength + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new InvalidArgumentException('Encrypted payload is too short.');
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $cipher = substr($decoded, $nonceLength);
        $keyBytes = $this->resolveEncryptionKey($key);
        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $keyBytes);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt payload. Check the key and ciphertext.');
        }

        return $plaintext;
    }

    // -------------------------------------------------------------------------
    // Asymmetric encryption — libsodium box (X25519)
    // -------------------------------------------------------------------------

    /**
     * Generate an X25519 key pair for libsodium box operations.
     *
     * @return array{public_key: string, private_key: string} Base64-encoded raw keys
     *
     * @throws RuntimeException When ext-sodium is missing
     */
    public function keyPair(): array
    {
        $this->assertSodiumAvailable();

        $pair = sodium_crypto_box_keypair();

        return [
            'public_key' => base64_encode(sodium_crypto_box_publickey($pair)),
            'private_key' => base64_encode(sodium_crypto_box_secretkey($pair)),
        ];
    }

    /**
     * Anonymous sender encryption — only the recipient's public key is required.
     *
     * Uses `sodium_crypto_box_seal`. Output is base64-encoded ciphertext.
     *
     * @throws RuntimeException When ext-sodium is missing
     */
    public function encryptWithPublicKey(string $plaintext, string $publicKey): string
    {
        $this->assertSodiumAvailable();

        $publicKeyBytes = $this->decodeBinaryKey(
            $publicKey,
            SODIUM_CRYPTO_BOX_PUBLICKEYBYTES,
            'public key',
        );

        return base64_encode(sodium_crypto_box_seal($plaintext, $publicKeyBytes));
    }

    /**
     * Decrypt a payload from {@see encryptWithPublicKey()}.
     *
     * Requires the recipient's public and private keys (rebuilds the key pair for seal_open).
     *
     * @throws RuntimeException When decryption fails
     */
    public function decryptWithPrivateKey(string $payload, string $publicKey, string $privateKey): string
    {
        $this->assertSodiumAvailable();

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Encrypted payload is not valid base64.');
        }

        $publicKeyBytes = $this->decodeBinaryKey(
            $publicKey,
            SODIUM_CRYPTO_BOX_PUBLICKEYBYTES,
            'public key',
        );
        $privateKeyBytes = $this->decodeBinaryKey(
            $privateKey,
            SODIUM_CRYPTO_BOX_SECRETKEYBYTES,
            'private key',
        );

        $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKeyBytes, $publicKeyBytes);
        $plaintext = sodium_crypto_box_seal_open($decoded, $keyPair);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt payload. Check the keys and ciphertext.');
        }

        return $plaintext;
    }

    /**
     * Authenticated encryption from a known sender to a known recipient.
     *
     * Payload: base64(`nonce` || `ciphertext`). On PHP 8.5+, builds a combined
     * key pair from sender secret + recipient public before calling `sodium_crypto_box`.
     *
     * @throws RuntimeException When ext-sodium is missing
     */
    public function encryptForRecipient(
        string $plaintext,
        string $recipientPublicKey,
        string $senderPrivateKey,
    ): string {
        $this->assertSodiumAvailable();

        $recipientPublicKeyBytes = $this->decodeBinaryKey(
            $recipientPublicKey,
            SODIUM_CRYPTO_BOX_PUBLICKEYBYTES,
            'recipient public key',
        );
        $senderPrivateKeyBytes = $this->decodeBinaryKey(
            $senderPrivateKey,
            SODIUM_CRYPTO_BOX_SECRETKEYBYTES,
            'sender private key',
        );

        $nonce = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
        $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            $senderPrivateKeyBytes,
            $recipientPublicKeyBytes,
        );
        $cipher = sodium_crypto_box($plaintext, $nonce, $keyPair);

        return base64_encode($nonce . $cipher);
    }

    /**
     * Decrypt a payload from {@see encryptForRecipient()}.
     *
     * Rebuilds key pair from recipient secret + sender public for `sodium_crypto_box_open`.
     *
     * @throws RuntimeException When decryption fails
     */
    public function decryptFromSender(
        string $payload,
        string $senderPublicKey,
        string $recipientPrivateKey,
    ): string {
        $this->assertSodiumAvailable();

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Encrypted payload is not valid base64.');
        }

        $nonceLength = SODIUM_CRYPTO_BOX_NONCEBYTES;

        if (strlen($decoded) < $nonceLength + SODIUM_CRYPTO_BOX_MACBYTES) {
            throw new InvalidArgumentException('Encrypted payload is too short.');
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $cipher = substr($decoded, $nonceLength);

        $senderPublicKeyBytes = $this->decodeBinaryKey(
            $senderPublicKey,
            SODIUM_CRYPTO_BOX_PUBLICKEYBYTES,
            'sender public key',
        );
        $recipientPrivateKeyBytes = $this->decodeBinaryKey(
            $recipientPrivateKey,
            SODIUM_CRYPTO_BOX_SECRETKEYBYTES,
            'recipient private key',
        );

        $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            $recipientPrivateKeyBytes,
            $senderPublicKeyBytes,
        );
        $plaintext = sodium_crypto_box_open($cipher, $nonce, $keyPair);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt payload. Check the keys and ciphertext.');
        }

        return $plaintext;
    }

    /**
     * Derive the libsodium box public key from a base64 or raw private key.
     *
     * @throws RuntimeException When ext-sodium is missing
     */
    public function publicKeyFromPrivateKey(string $privateKey): string
    {
        $this->assertSodiumAvailable();

        $privateKeyBytes = $this->decodeBinaryKey(
            $privateKey,
            SODIUM_CRYPTO_BOX_SECRETKEYBYTES,
            'private key',
        );

        return base64_encode(sodium_crypto_box_publickey_from_secretkey($privateKeyBytes));
    }

    // -------------------------------------------------------------------------
    // Asymmetric encryption — RSA (PEM)
    // -------------------------------------------------------------------------

    /**
     * Generate an RSA key pair in PEM format.
     *
     * @return array{public_key: string, private_key: string} PEM strings
     *
     * @throws InvalidArgumentException When `$bits` is less than 2048
     * @throws RuntimeException When key generation or export fails
     */
    public function rsaKeyPair(int $bits = 2048): array
    {
        $this->assertOpenSslAvailable();

        if ($bits < 2048) {
            throw new InvalidArgumentException('RSA keys must be at least 2048 bits.');
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Unable to generate RSA key pair.');
        }

        $privateKey = '';
        $exported = openssl_pkey_export($resource, $privateKey);

        if (!$exported) {
            throw new RuntimeException('Unable to export RSA private key.');
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false || !isset($details['key'])) {
            throw new RuntimeException('Unable to read RSA public key.');
        }

        return [
            'public_key' => $details['key'],
            'private_key' => $privateKey,
        ];
    }

    /**
     * RSA-OAEP encrypt with a PEM public key.
     *
     * Small messages use version byte `0x00` (direct OAEP). Larger messages use
     * `0x01` hybrid: RSA-wrapped secretbox key + nonce + ciphertext.
     *
     * @throws RuntimeException When encryption fails
     */
    public function rsaEncrypt(string $plaintext, string $publicKey): string
    {
        $this->assertOpenSslAvailable();
        $this->assertSodiumAvailable();

        $key = openssl_pkey_get_public($publicKey);

        if ($key === false) {
            throw new InvalidArgumentException('Invalid RSA public key.');
        }

        $maxLength = $this->rsaMaxPlaintextLength($key);

        if (strlen($plaintext) <= $maxLength) {
            return base64_encode("\x00" . $this->rsaEncryptBinary($plaintext, $key));
        }

        $symmetricKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $symmetricKey);
        $wrappedKey = $this->rsaEncryptBinary($symmetricKey, $key);

        return base64_encode("\x01" . $this->packLengthPrefixed($wrappedKey) . $nonce . $cipher);
    }

    /**
     * Decrypt a payload from {@see rsaEncrypt()}.
     *
     * @throws RuntimeException When decryption fails
     */
    public function rsaDecrypt(string $payload, string $privateKey): string
    {
        $this->assertOpenSslAvailable();
        $this->assertSodiumAvailable();

        $decoded = base64_decode($payload, true);

        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('Encrypted payload is not valid base64.');
        }

        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new InvalidArgumentException('Invalid RSA private key.');
        }

        $version = ord($decoded[0]);
        $body = substr($decoded, 1);

        if ($version === 0) {
            $plaintext = $this->rsaDecryptBinary($body, $key);

            if ($plaintext === null) {
                throw new RuntimeException('Unable to decrypt RSA payload.');
            }

            return $plaintext;
        }

        if ($version !== 1) {
            throw new InvalidArgumentException('Unsupported RSA payload version.');
        }

        [$wrappedKey, $remainder] = $this->unpackLengthPrefixed($body);
        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if (strlen($remainder) < $nonceLength + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new InvalidArgumentException('Encrypted payload is too short.');
        }

        $nonce = substr($remainder, 0, $nonceLength);
        $cipher = substr($remainder, $nonceLength);
        $symmetricKey = $this->rsaDecryptBinary($wrappedKey, $key);

        if ($symmetricKey === null) {
            throw new RuntimeException('Unable to decrypt RSA-wrapped key.');
        }

        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $symmetricKey);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt RSA payload.');
        }

        return $plaintext;
    }

    // -------------------------------------------------------------------------
    // Static validators (validation rules & helpers)
    // -------------------------------------------------------------------------

    /**
     * Whether `$value` is a well-formed UUID (versions 1–8, RFC variant bits).
     */
    public static function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        ) === 1;
    }

    /**
     * Whether `$value` is a valid 26-character Crockford ULID.
     */
    public static function isUlid(string $value): bool
    {
        return Ulid::isValid($value);
    }

    /**
     * Whether `$value` matches OTP length and charset constraints.
     */
    public static function isOtp(string $value, int $length = 6, bool $numericOnly = true): bool
    {
        if ($length < self::MIN_OTP_LENGTH || $length > self::MAX_OTP_LENGTH) {
            return false;
        }

        if ($numericOnly) {
            return preg_match('/^\d{' . $length . '}$/', $value) === 1;
        }

        return preg_match('/^[0-9A-Za-z]{' . $length . '}$/', $value) === 1;
    }

    /**
     * Whether `$value` has sufficient entropy as hex or URL-safe base64.
     *
     * @param int $minBytes Minimum raw byte entropy (hex length = 2×, base64 ≈ 4/3×)
     */
    public static function isToken(string $value, int $minBytes = 16): bool
    {
        if ($minBytes < 1) {
            return false;
        }

        if (preg_match('/^[a-f0-9]+$/i', $value) === 1) {
            return strlen($value) >= ($minBytes * 2);
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1) {
            return strlen($value) >= (int) ceil($minBytes * 4 / 3);
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /** Fisher–Yates shuffle using `random_int` (not `str_shuffle`). */
    private function shuffleString(string $value): string
    {
        $chars = str_split($value);

        for ($index = count($chars) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$chars[$index], $chars[$swap]] = [$chars[$swap], $chars[$index]];
        }

        return implode('', $chars);
    }

    /** @throws InvalidArgumentException */
    private function assertOtpLength(int $length): void
    {
        if ($length < self::MIN_OTP_LENGTH || $length > self::MAX_OTP_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('OTP length must be between %d and %d.', self::MIN_OTP_LENGTH, self::MAX_OTP_LENGTH),
            );
        }
    }

    /** @throws RuntimeException */
    private function assertSodiumAvailable(): void
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('The sodium extension is required for encryption.');
        }
    }

    /** @throws RuntimeException */
    private function assertOpenSslAvailable(): void
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('The openssl extension is required for RSA encryption.');
        }
    }

    /**
     * Decode a base64 key string, falling back to raw bytes when not valid base64.
     *
     * @throws InvalidArgumentException When decoded length does not match `$expectedLength`
     */
    private function decodeBinaryKey(string $key, int $expectedLength, string $label): string
    {
        $decoded = base64_decode($key, true);

        if ($decoded === false) {
            $decoded = $key;
        }

        if (strlen($decoded) !== $expectedLength) {
            throw new InvalidArgumentException(sprintf('Invalid %s length.', $label));
        }

        return $decoded;
    }

    /**
     * Max OAEP plaintext size for a given RSA key ( PKCS#1 v2.1 overhead ≈ 42 bytes).
     *
     * @param \OpenSSLAsymmetricKey $key
     */
    private function rsaMaxPlaintextLength($key): int
    {
        $details = openssl_pkey_get_details($key);

        if ($details === false || !isset($details['bits'])) {
            throw new RuntimeException('Unable to determine RSA key size.');
        }

        return intdiv($details['bits'], 8) - 42;
    }

    /** @param \OpenSSLAsymmetricKey $key */
    private function rsaEncryptBinary(string $plaintext, $key): string
    {
        $encrypted = '';
        $ok = openssl_public_encrypt($plaintext, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING);

        if (!$ok) {
            throw new RuntimeException('RSA encryption failed.');
        }

        return $encrypted;
    }

    /** @param \OpenSSLAsymmetricKey $key */
    private function rsaDecryptBinary(string $payload, $key): ?string
    {
        $plaintext = '';
        $ok = openssl_private_decrypt($payload, $plaintext, $key, OPENSSL_PKCS1_OAEP_PADDING);

        return $ok ? $plaintext : null;
    }

    /** Pack a length prefix (uint16 BE) for RSA hybrid payload framing. */
    private function packLengthPrefixed(string $value): string
    {
        return pack('n', strlen($value)) . $value;
    }

    /**
     * @return array{0: string, 1: string} Tuple of [value, remainder]
     *
     * @throws InvalidArgumentException When framing is invalid
     */
    private function unpackLengthPrefixed(string $payload): array
    {
        if (strlen($payload) < 2) {
            throw new InvalidArgumentException('Encrypted payload is too short.');
        }

        $length = unpack('n', substr($payload, 0, 2))[1];
        $value = substr($payload, 2, $length);
        $remainder = substr($payload, 2 + $length);

        if (strlen($value) !== $length) {
            throw new InvalidArgumentException('Encrypted payload is malformed.');
        }

        return [$value, $remainder];
    }

    /**
     * Normalize an encryption key to 32 secretbox bytes.
     *
     * Reads `APP_KEY` when `$key` is null. Supports `base64:` prefix (Laravel-style).
     *
     * @throws RuntimeException When no key is available
     * @throws InvalidArgumentException When `base64:` value is invalid
     */
    private function resolveEncryptionKey(?string $key): string
    {
        $key ??= function_exists('env') ? (string) env('APP_KEY', '') : '';

        if ($key === '') {
            throw new RuntimeException('Encryption key is missing. Set APP_KEY or pass an explicit key.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false) {
                throw new InvalidArgumentException('APP_KEY base64 value is invalid.');
            }

            $key = $decoded;
        }

        return sodium_crypto_generichash($key, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    // -------------------------------------------------------------------------
    // JWT
    // -------------------------------------------------------------------------

    /**
     * Create a signed JWT (HS256/HS384/HS512 or RS256).
     *
     * Default secret: `[jwt] SECRET` / `JWT_SECRET` / `APP_KEY`.
     * Adds `iat` when missing; adds `exp` from `[jwt] TTL` (seconds, default 3600) when missing.
     *
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $headers
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function jwtEncode(
        array $claims,
        ?string $secret = null,
        array $headers = [],
        string $alg = 'HS256',
    ): string {
        $alg = strtoupper($alg);
        $now = time();

        if (!isset($claims['iat'])) {
            $claims['iat'] = $now;
        }

        if (!isset($claims['exp'])) {
            $ttl = (int) $this->jwtConfig('TTL', $this->jwtConfig('ttl', env('JWT_TTL', 3600)));
            $claims['exp'] = $now + max(1, (int) $ttl);
        }

        $issuer = $this->jwtConfig('ISSUER', $this->jwtConfig('issuer', env('JWT_ISSUER')));
        if ($issuer && !isset($claims['iss'])) {
            $claims['iss'] = $issuer;
        }

        $audience = $this->jwtConfig('AUDIENCE', $this->jwtConfig('audience', env('JWT_AUDIENCE')));
        if ($audience && !isset($claims['aud'])) {
            $claims['aud'] = $audience;
        }

        $configuredAlg = $this->jwtConfig('ALG', $this->jwtConfig('alg', null));
        if ($configuredAlg && $alg === 'HS256' && empty($headers['alg'])) {
            $alg = strtoupper((string) $configuredAlg);
        }

        $header = array_merge(['typ' => 'JWT', 'alg' => $alg], $headers);
        $header['alg'] = $alg;

        $segments = [
            $this->jwtBase64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->jwtBase64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $segments[] = $this->jwtBase64UrlEncode($this->jwtSign($signingInput, $alg, $secret));

        return implode('.', $segments);
    }

    /**
     * Decode a JWT. When `$verify` is true, signature and exp/nbf/iss/aud are checked.
     *
     * @return array{header: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function jwtDecode(string $token, bool $verify = true, ?string $secret = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('JWT must have three segments.');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $headerJson = $this->jwtBase64UrlDecode($headerB64);
        $payloadJson = $this->jwtBase64UrlDecode($payloadB64);
        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            throw new InvalidArgumentException('JWT header or payload is not valid JSON.');
        }

        if ($verify) {
            $alg = strtoupper((string) ($header['alg'] ?? 'HS256'));
            if (!$this->jwtVerifySignature($headerB64 . '.' . $payloadB64, $this->jwtBase64UrlDecode($sigB64), $alg, $secret)) {
                throw new InvalidArgumentException('JWT signature is invalid.');
            }

            $this->jwtValidateClaims($payload);
        }

        return ['header' => $header, 'payload' => $payload];
    }

    /**
     * Verify signature and standard claims; returns false instead of throwing.
     */
    public function jwtVerify(string $token, ?string $secret = null): bool
    {
        try {
            $this->jwtDecode($token, true, $secret);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Opaque refresh token (CSPRNG). Store/hash server-side; not a JWT.
     */
    public function jwtRefreshToken(int $bytes = 32): string
    {
        return $this->token($bytes);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jwtValidateClaims(array $payload): void
    {
        $now = time();

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now + 60) {
            throw new InvalidArgumentException('JWT is not valid yet (nbf).');
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < $now) {
            throw new InvalidArgumentException('JWT has expired.');
        }

        $issuer = $this->jwtConfig('ISSUER', $this->jwtConfig('issuer', env('JWT_ISSUER')));
        if ($issuer !== null && $issuer !== '' && isset($payload['iss']) && (string) $payload['iss'] !== (string) $issuer) {
            throw new InvalidArgumentException('JWT issuer mismatch.');
        }

        $audience = $this->jwtConfig('AUDIENCE', $this->jwtConfig('audience', env('JWT_AUDIENCE')));
        if ($audience !== null && $audience !== '' && isset($payload['aud'])) {
            $aud = $payload['aud'];
            $ok = is_array($aud) ? in_array($audience, $aud, true) : (string) $aud === (string) $audience;
            if (!$ok) {
                throw new InvalidArgumentException('JWT audience mismatch.');
            }
        }
    }

    private function jwtSign(string $input, string $alg, ?string $secret): string
    {
        return match ($alg) {
            'HS256' => hash_hmac('sha256', $input, $this->resolveJwtSecret($secret), true),
            'HS384' => hash_hmac('sha384', $input, $this->resolveJwtSecret($secret), true),
            'HS512' => hash_hmac('sha512', $input, $this->resolveJwtSecret($secret), true),
            'RS256' => $this->jwtRsaSign($input, $secret),
            default => throw new InvalidArgumentException("Unsupported JWT algorithm: {$alg}"),
        };
    }

    private function jwtVerifySignature(string $input, string $signature, string $alg, ?string $secret): bool
    {
        if (str_starts_with($alg, 'HS')) {
            return $this->equals($this->jwtSign($input, $alg, $secret), $signature);
        }

        if ($alg === 'RS256') {
            $publicKey = $secret
                ?? (function_exists('env') ? (string) env('JWT_PUBLIC_KEY', env('JWT_PRIVATE_KEY', '')) : '');
            if ($publicKey === '') {
                return false;
            }
            $resource = openssl_pkey_get_public($publicKey) ?: openssl_pkey_get_private($publicKey);
            if ($resource === false) {
                return false;
            }

            return openssl_verify($input, $signature, $resource, OPENSSL_ALGO_SHA256) === 1;
        }

        return false;
    }

    private function jwtRsaSign(string $input, ?string $privateKeyPem): string
    {
        $key = $privateKeyPem ?? (function_exists('env') ? (string) env('JWT_PRIVATE_KEY', '') : '');
        if ($key === '') {
            throw new RuntimeException('RS256 requires a private key (JWT_PRIVATE_KEY).');
        }

        $resource = openssl_pkey_get_private($key);
        if ($resource === false) {
            throw new InvalidArgumentException('Invalid RSA private key for JWT.');
        }

        $signature = '';
        $ok = openssl_sign($input, $signature, $resource, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('RS256 signing failed.');
        }

        return $signature;
    }

    private function resolveJwtSecret(?string $secret): string
    {
        if ($secret !== null && $secret !== '') {
            return $secret;
        }

        $fromConfig = $this->jwtConfig('SECRET', $this->jwtConfig('secret', null));
        $resolved = (string) ($fromConfig
            ?? (function_exists('env') ? env('JWT_SECRET', env('APP_KEY', '')) : '')
            ?? '');

        if ($resolved === '') {
            throw new RuntimeException('JWT secret is missing. Set JWT_SECRET, [jwt] SECRET, or APP_KEY.');
        }

        return $resolved;
    }

    /**
     * Read a value from the `[jwt]` settings section, then fall back to `$default`.
     */
    private function jwtConfig(string $key, mixed $default = null): mixed
    {
        if (!function_exists('env')) {
            return $default;
        }

        $section = env('jwt');
        if (is_array($section) && array_key_exists($key, $section) && $section[$key] !== '' && $section[$key] !== null) {
            return $section[$key];
        }

        return $default;
    }

    private function jwtBase64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function jwtBase64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid JWT base64url encoding.');
        }

        return $decoded;
    }
}
