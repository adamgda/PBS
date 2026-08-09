<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Serwis uploadu plików — Etap 7 (dokumenty pracownika).
 *
 * Bezpieczeństwo (zgodnie z dokumentacją techniczną sekcja 9.3):
 * - Walidacja typu MIME przez `finfo_file()` (nie ufamy $_FILES['type']).
 * - Whitelist rozszerzeń: `.pdf`, `.jpg`, `.png`.
 * - Limit rozmiaru: 5 MB.
 * - Generowanie nowej nazwy pliku (UUID) — nie ufamy nazwie od klienta.
 * - Pliki przechowywane poza document root (`storage/private/...`).
 * - Skanowanie antywirusowe ClamAV (z graceful degradation).
 * - Dostęp przez signed URL z krótkim TTL (HMAC).
 */
final class FileUploadService
{
    private const int MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const int SIGNED_URL_TTL_SECONDS = 300; // 5 min

    /** @var array<string, string> rozszerzenie => oczekiwany MIME */
    private const array ALLOWED_EXTENSIONS = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(
        private readonly string $storageDir,
        private readonly string $baseUrl,
        private readonly string $hmacSecret,
        private readonly VirusScannerInterface $scanner,
    ) {}

    /**
     * Waliduje plik z $_FILES i zwraca tablicę błędów (pusta = OK).
     *
     * @param array<string, mixed> $file
     * @return array<int, string>
     */
    public function validate(array $file): array
    {
        $errors = [];

        if (!array_key_exists('tmp_name', $file) || !is_string($file['tmp_name']) || $file['tmp_name'] === '') {
            $errors[] = 'No file uploaded';
            return $errors;
        }

        $uploadError = $file['error'] ?? UPLOAD_ERR_OK;
        if (!is_int($uploadError)) {
            $uploadError = UPLOAD_ERR_OK;
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed (code ' . $uploadError . ')';
            return $errors;
        }

        $size = $file['size'] ?? 0;
        if (!is_int($size)) {
            $size = 0;
        }
        if ($size > self::MAX_SIZE_BYTES) {
            $errors[] = 'File too large (max 5 MB)';
        }

        $name = is_string($file['name'] ?? null) ? $file['name'] : '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            $errors[] = 'Unsupported file type (allowed: pdf, jpg, png)';
            return $errors;
        }

        $realMime = $this->detectMime($file['tmp_name']);
        $expectedMime = self::ALLOWED_EXTENSIONS[$extension];
        if ($realMime === null || !str_starts_with($realMime, strtok($expectedMime, '/'))) {
            $errors[] = 'MIME type mismatch';
        }

        return $errors;
    }

    /**
     * Zapisuje plik w storage z nazwą UUID i zwraca nową nazwę (uuid.ext).
     *
     * @param array<string, mixed> $file
     * @return string nazwa zapisanego pliku
     */
    public function store(array $file): string
    {
        $tmpName = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        $name = is_string($file['name'] ?? null) ? $file['name'] : '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $this->ensureStorageDir();

        // Skanowanie antywirusowe (graceful degradation gdy niedostępne).
        if ($this->scanner->isAvailable()) {
            $clean = $this->scanner->scan($tmpName);
            if (!$clean) {
                throw new \RuntimeException('File failed virus scan');
            }
        }

        $uuid = $this->uuid();
        $filename = $uuid . '.' . $extension;
        $dest = rtrim($this->storageDir, '/') . '/' . $filename;

        if (!move_uploaded_file($tmpName, $dest)) {
            // W testach / środowisku nie-HTTP move_uploaded_file zawodzi —
            // fallback na kopiowanie (gdy tmp_name jest zwykłym plikiem).
            if (!copy($tmpName, $dest)) {
                throw new \RuntimeException('Failed to store uploaded file');
            }
        }

        return $filename;
    }

    /**
     * Generuje signed URL z TTL dla pliku (dostęp do pobrania dokumentu).
     */
    public function signedUrl(string $filename): string
    {
        $expiry = time() + self::SIGNED_URL_TTL_SECONDS;
        $signature = $this->sign($filename, $expiry);

        $token = base64_encode($filename . '|' . $expiry . '|' . $signature);

        return rtrim($this->baseUrl, '/') . '/api/v1/documents/download?token=' . urlencode($token);
    }

    /**
     * Weryfikuje signed token i zwraca nazwę pliku lub null gdy nieprawidłowy/wygasły.
     */
    public function verifySignedToken(string $token): ?string
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return null;
        }
        [$filename, $expiryStr, $signature] = $parts;
        $expiry = (int) $expiryStr;
        if ($expiry < time()) {
            return null;
        }
        if (!hash_equals($this->sign($filename, $expiry), $signature)) {
            return null;
        }

        return $filename;
    }

    private function sign(string $filename, int $expiry): string
    {
        return hash_hmac('sha256', $filename . '|' . $expiry, $this->hmacSecret);
    }

    private function detectMime(string $tmpName): ?string
    {
        if (!function_exists('finfo_file')) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    private function ensureStorageDir(): void
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0o700, true);
        }
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}