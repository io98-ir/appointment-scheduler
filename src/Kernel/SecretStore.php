<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

use Vaqtyar\Kernel\Log\Logger;

/**
 * API keys of payment gateways and SMS providers (ADR-014, architecture §12).
 *
 * A secret is read from a wp-config.php constant first, {SLUG}_{NAME} (e.g.
 * the constant for "zarinpal_merchant"), so a host can keep it out of the
 * database. Otherwise it is an option, encrypted with XChaCha20-Poly1305
 * under a key derived from the site's auth salt (AUTH_KEY, AUTH_SALT), and
 * bound to its own name so a ciphertext copied to another name fails to open.
 *
 * sodium_* are always there: WordPress ships sodium_compat.
 */
final class SecretStore
{
    /** Marks the format, so a later change of cipher can read old values. */
    private const VERSION = 'v1:';

    /**
     * @param string $key 32 bytes, from keyFromSalt().
     */
    public function __construct(
        private readonly string $key,
        private readonly Logger $logger,
    ) {
        if (\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== \strlen($key)) {
            throw KernelException::secretKeyLength(\strlen($key));
        }
    }

    /**
     * Changing the salts in wp-config.php makes every stored secret unreadable;
     * get() then reports it and the admin enters them again.
     */
    public static function keyFromSalt(string $salt): string
    {
        return \sodium_crypto_generichash(
            'secret-store:' . $salt,
            '',
            \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
        );
    }

    /**
     * @return string|null Null when it is not set, or cannot be decrypted
     *     (logged as an error).
     */
    public function get(string $name): ?string
    {
        $option = Options::key('secret_' . $name);
        $constant = self::constantName($name);
        if (\defined($constant)) {
            $value = \constant($constant);
            if (\is_int($value)) {
                // A numeric terminal id, written without quotes.
                return (string) $value;
            }
            if (!\is_string($value) || '' === $value) {
                $this->logger->error('secrets', 'A secret in wp-config.php is empty or not a string.', [
                    'secret' => $name,
                ]);

                return null;
            }

            return $value;
        }

        $stored = \get_option($option, null);
        if (!\is_string($stored) || '' === $stored) {
            return null;
        }

        $plain = $this->decrypt($stored, $option);
        if (null === $plain) {
            // Not the value: it is a secret. The name tells the admin what to re-enter.
            $this->logger->error('secrets', 'A stored secret cannot be decrypted; the salts may have changed.', [
                'secret' => $name,
            ]);
        }

        return $plain;
    }

    /**
     * An empty value deletes the secret.
     *
     * @throws KernelException When wp-config.php defines it: that value would win anyway.
     */
    public function set(string $name, string $value): void
    {
        $option = Options::key('secret_' . $name);
        if ($this->isDefinedInConfig($name)) {
            throw KernelException::secretInConfig($name);
        }
        if ('' === $value) {
            \delete_option($option);

            return;
        }

        $nonce = \random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($value, $option, $nonce, $this->key);
        \update_option(
            $option,
            self::VERSION . \sodium_bin2base64($nonce . $cipher, \SODIUM_BASE64_VARIANT_ORIGINAL),
            false
        );
    }

    /**
     * For the settings screen: such a secret is shown read-only.
     */
    public function isDefinedInConfig(string $name): bool
    {
        Options::key('secret_' . $name);

        return \defined(self::constantName($name));
    }


    private function decrypt(string $stored, string $option): ?string
    {
        if (!\str_starts_with($stored, self::VERSION)) {
            return null;
        }
        try {
            $raw = \sodium_base642bin(\substr($stored, \strlen(self::VERSION)), \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\SodiumException) {
            return null;
        }
        $nonceLength = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (\strlen($raw) <= $nonceLength) {
            return null;
        }
        $plain = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            \substr($raw, $nonceLength),
            $option,
            \substr($raw, 0, $nonceLength),
            $this->key
        );

        return \is_string($plain) ? $plain : null;
    }

    /**
     * {SLUG}_{NAME}; the name is validated by Options::key() before this runs.
     */
    private static function constantName(string $name): string
    {
        return \strtoupper(Identity::SLUG) . '_' . \strtoupper($name);
    }
}
