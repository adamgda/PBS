<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Serwis szyfrowania danych wrażliwych at-rest — AES-256-GCM (dokumentacja sekcja 9.1).
 *
 * Klucz pochodzi z `APP_KEY` w .env (32 bajty zakodowane base64). Format payloadu:
 * base64( nonce(12) + tag(16) + ciphertext ).
 */
final class CryptoService
{
    private const string CIPHER = 'aes-256-gcm';
    private const int NONCE_LENGTH = 12;
    private const int TAG_LENGTH = 16;

    /** @var string 32-bajtowy klucz surowy */
    private readonly string $key;

    public function __construct(string $appKey)
    {
        $this->key = $this->normalizeKey($appKey);
    }

    /**
     * Szyfruje dane (AES-256-GCM). Zwraca base64 payloadu.
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Odszyfrowuje dane. Zwraca plaintext lub null przy niepoprawnym payloadzie/kluczu.
     */
    public function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }

        $minLength = self::NONCE_LENGTH + self::TAG_LENGTH;
        if (strlen($raw) < $minLength) {
            return null;
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, $minLength);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false) {
            return null;
        }

        return $plaintext;
    }

    /**
     * Normalizuje APP_KEY do 32-bajtowego klucza surowego.
     * Obsługuje: base64(32B), base64(24B), base64(16B) oraz surowy ciąg.
     */
    private function normalizeKey(string $appKey): string
    {
        $decoded = base64_decode($appKey, true);
        if ($decoded !== false && in_array(strlen($decoded), [16, 24, 32], true)) {
            return str_pad($decoded, 32, "\0");
        }

        $raw = $appKey;
        if (strlen($raw) < 16) {
            $raw = str_pad($raw, 16, "\0");
        }

        return str_pad($raw, 32, "\0");
    }
}
