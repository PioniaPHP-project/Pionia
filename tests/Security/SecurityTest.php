<?php

namespace Security;

use Pionia\Security\Security;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\Utils\Ulid;

class SecurityTest extends PioniaTestCase
{
    private Security $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->security = security();
    }

    public function testRandomStringUsesRequestedLengthAndAlphabet(): void
    {
        $value = $this->security->randomString(24, Security::ALPHABET_NUMERIC);

        $this->assertSame(24, strlen($value));
        $this->assertMatchesRegularExpression('/^\d{24}$/', $value);
    }

    public function testUuidIsValidV4(): void
    {
        $uuid = $this->security->uuid();

        $this->assertTrue(Security::isUuid($uuid));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }

    public function testUlidGenerationIsValid(): void
    {
        $ulid = $this->security->ulid();

        $this->assertTrue(Ulid::isValid($ulid));
        $this->assertTrue(Security::isUlid($ulid));
    }

    public function testOtpRespectsLength(): void
    {
        $otp = $this->security->otp(8);

        $this->assertTrue(Security::isOtp($otp, 8));
        $this->assertMatchesRegularExpression('/^\d{8}$/', $otp);
    }

    public function testPasswordGenerationMeetsComplexityRules(): void
    {
        $password = $this->security->password(12);

        $this->assertSame(12, strlen($password));
        $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).{8,}$/', $password);
    }

    public function testPasswordHashingRoundTrip(): void
    {
        $hash = $this->security->hashPassword('Secret1!');

        $this->assertTrue($this->security->verifyPassword('Secret1!', $hash));
        $this->assertFalse($this->security->verifyPassword('wrong', $hash));
    }

    public function testHmacVerification(): void
    {
        $signature = $this->security->hmac('payload', 'secret-key');

        $this->assertTrue($this->security->verifyHmac('payload', 'secret-key', $signature));
        $this->assertFalse($this->security->verifyHmac('payload', 'other-key', $signature));
    }

    public function testEqualsIsTimingSafe(): void
    {
        $this->assertTrue($this->security->equals('same-value', 'same-value'));
        $this->assertFalse($this->security->equals('same-value', 'other-value'));
    }

    public function testTokenValidation(): void
    {
        $token = $this->security->token(24);

        $this->assertTrue(Security::isToken($token, 16));
        $this->assertTrue(Security::isToken($this->security->randomHex(16), 16));
    }

    public function testHelperFunctionsDelegateToSecurity(): void
    {
        $this->assertTrue(Security::isToken(secure_token(24), 16));
        $this->assertTrue(Security::isOtp(secure_otp(6), 6));
        $this->assertTrue(Security::isUuid(secure_uuid()));
        $this->assertTrue(Security::isUlid(secure_ulid()));
        $this->assertTrue(secure_equals('abc', 'abc'));
        $this->assertTrue(verify_password('Secret1!', hash_password('Secret1!')));
        $this->assertSame(32, strlen(secure_random_bytes(32)));
        $this->assertSame(16, strlen(secure_random_hex(8)));
        $this->assertFalse(security()->needsRehash(hash_password('Secret1!')));
        $this->assertNotSame('', secure_password(12));
        $this->assertNotSame('', csrf_token());
        $this->assertTrue(verify_hmac('data', 'key', secure_hmac('data', 'key')));
        $this->assertSame(hash('sha256', 'abc'), secure_hash('abc'));
    }

    public function testEncryptAndDecryptWithExplicitKey(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('sodium extension is not available.');
        }

        $key = $this->security->randomBytes(32);
        $encrypted = $this->security->encrypt('hello world', $key);

        $this->assertSame('hello world', $this->security->decrypt($encrypted, $key));
    }

    public function testPublicPrivateKeyEncryptionRoundTrip(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('sodium extension is not available.');
        }

        $keys = $this->security->keyPair();
        $encrypted = $this->security->encryptWithPublicKey('secret payload', $keys['public_key']);

        $this->assertSame(
            'secret payload',
            $this->security->decryptWithPrivateKey($encrypted, $keys['public_key'], $keys['private_key']),
        );

        $this->assertSame(
            'secret payload',
            decrypt_with_private_key($encrypted, $keys['public_key'], $keys['private_key']),
        );
    }

    public function testAuthenticatedBoxEncryptionBetweenParties(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('sodium extension is not available.');
        }

        $alice = $this->security->keyPair();
        $bob = $this->security->keyPair();

        $encrypted = $this->security->encryptForRecipient(
            'hello bob',
            $bob['public_key'],
            $alice['private_key'],
        );

        $this->assertSame(
            'hello bob',
            $this->security->decryptFromSender($encrypted, $alice['public_key'], $bob['private_key']),
        );
    }

    public function testPublicKeyCanBeDerivedFromPrivateKey(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('sodium extension is not available.');
        }

        $keys = $this->security->keyPair();

        $this->assertSame(
            $keys['public_key'],
            $this->security->publicKeyFromPrivateKey($keys['private_key']),
        );
    }

    public function testRsaEncryptionRoundTripForSmallAndLargePayloads(): void
    {
        if (!extension_loaded('openssl') || !extension_loaded('sodium')) {
            $this->markTestSkipped('openssl and sodium extensions are required.');
        }

        $keys = $this->security->rsaKeyPair();
        $small = $this->security->rsaEncrypt('short message', $keys['public_key']);
        $large = $this->security->rsaEncrypt(str_repeat('x', 512), $keys['public_key']);

        $this->assertSame('short message', $this->security->rsaDecrypt($small, $keys['private_key']));
        $this->assertSame(str_repeat('x', 512), $this->security->rsaDecrypt($large, $keys['private_key']));
    }
}
