<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Security\PassportNumberCipher;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The passport number is the most sensitive value the app holds, so the
 * cipher is asserted on its own: a copy of the database without the key must
 * be useless (ADR 0033). No database here, so no #[ResetDatabase].
 */
final class PassportNumberCipherTest extends KernelTestCase
{
    #[Test]
    public function theContainerWiresTheCipherFromTheEnvironmentKey(): void
    {
        self::bootKernel();
        $cipher = self::getContainer()->get(PassportNumberCipher::class);
        self::assertInstanceOf(PassportNumberCipher::class, $cipher);

        self::assertSame('C01X00T47', $cipher->decrypt($cipher->encrypt('C01X00T47')));
    }

    #[Test]
    public function aStoredValueNeverContainsTheNumberAndDiffersEveryTime(): void
    {
        $cipher = self::cipher();

        $first = $cipher->encrypt('C01X00T47');
        $second = $cipher->encrypt('C01X00T47');

        self::assertStringStartsWith('v1:', $first);
        self::assertStringNotContainsString('C01X00T47', $first);
        self::assertNotSame($first, $second);
        self::assertSame('C01X00T47', $cipher->decrypt($second));
    }

    #[Test]
    public function anotherKeyCannotReadIt(): void
    {
        $stored = self::cipher()->encrypt('C01X00T47');

        $this->expectException(\RuntimeException::class);
        self::cipher()->decrypt($stored);
    }

    #[Test]
    public function aTamperedValueIsRefused(): void
    {
        $cipher = self::cipher();
        $raw = (string) base64_decode(substr($cipher->encrypt('C01X00T47'), 3), true);
        $raw[-1] = chr(ord($raw[-1]) ^ 1);

        $this->expectException(\RuntimeException::class);
        $cipher->decrypt('v1:' . base64_encode($raw));
    }

    #[Test]
    public function aValueWithoutTheVersionPrefixIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        self::cipher()->decrypt('C01X00T47');
    }

    #[Test]
    public function aKeyOfTheWrongLengthIsRejectedUpFront(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PassportNumberCipher(base64_encode(random_bytes(16)));
    }

    #[Test]
    public function aMissingKeyIsRejectedUpFront(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PassportNumberCipher('');
    }

    private static function cipher(): PassportNumberCipher
    {
        return new PassportNumberCipher(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }
}
