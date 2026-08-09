<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Skaner antywirusowy ClamAV — łączy się z daemonem clamd przez socket INSTREAM.
 *
 * Etap 7 — upload dokumentów pracownika (sekcja 9.3 dokumentacji: skanowanie ClamAV).
 *
 * Jeśli daemon clamd nie jest dostępny, `isAvailable()` zwraca false
 * a FileUploadService gracefully degraduje się (pomija skanowanie z logowaniem).
 */
final class ClamAvScanner implements VirusScannerInterface
{
    private const int CHUNK_SIZE = 8192;

    public function __construct(
        private readonly string $socket = 'unix:///var/run/clamav/clamd.ctl',
        private readonly float $timeoutSeconds = 5.0,
    ) {}

    public function isAvailable(): bool
    {
        if (!function_exists('stream_socket_client')) {
            return false;
        }
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($this->socket, $errno, $errstr, $this->timeoutSeconds);
        if (!is_resource($socket)) {
            return false;
        }
        fclose($socket);

        return true;
    }

    public function scan(string $filePath): bool
    {
        if (!is_readable($filePath)) {
            return false;
        }
        if (!function_exists('stream_socket_client')) {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($this->socket, $errno, $errstr, $this->timeoutSeconds);
        if (!is_resource($socket)) {
            return false;
        }

        stream_set_timeout($socket, (int) $this->timeoutSeconds);

        // Protokół clamd INSTREAM: <4 bajty długość><chunk><...><0 długość>
        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            fclose($socket);
            return false;
        }

        while (!feof($fh)) {
            $chunk = fread($fh, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $size = pack('N', strlen($chunk));
            fwrite($socket, $size . $chunk);
        }
        fwrite($socket, pack('N', 0));
        fclose($fh);

        $response = fread($socket, 4096);
        fclose($socket);

        if (!is_string($response)) {
            return false;
        }

        // clamd zwraca "stream: OK\n" dla czystego pliku
        return str_contains($response, 'OK');
    }
}