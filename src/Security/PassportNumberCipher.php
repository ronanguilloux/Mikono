<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts passport numbers before they reach the database, so a leaked
 * .db file or backup copy doesn't carry them. The key is a runtime secret
 * that never travels with those copies. See ADR 0033.
 *
 * A stored value is "v1:" + base64(nonce ‖ secretbox). The prefix names the
 * key generation, leaving room for a rotation.
 */
final readonly class PassportNumberCipher
{
    private const string PREFIX = 'v1:';

    private string $key;

    public function __construct(#[Autowire(env: 'PASSPORT_ENCRYPTION_KEY')] string $base64Key)
    {
        $key = base64_decode($base64Key, true);
        if (false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($key)) {
            throw new \InvalidArgumentException(sprintf('PASSPORT_ENCRYPTION_KEY must be %d bytes, base64-encoded (openssl rand -base64 32).', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    /** @throws \RuntimeException when the value was tampered with or sealed under another key */
    public function decrypt(string $stored): string
    {
        $raw = str_starts_with($stored, self::PREFIX) ? base64_decode(substr($stored, strlen(self::PREFIX)), true) : false;
        if (false === $raw || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Not a stored passport number.');
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );
        if (false === $plaintext) {
            throw new \RuntimeException('This passport number cannot be decrypted with the current key.');
        }

        return $plaintext;
    }
}
