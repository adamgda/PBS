<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Interfejs skanera antywirusowego — Etap 7 (upload dokumentów pracownika).
 *
 * Dokumentacja techniczna (sekcja 9.3) wymaga skanowania antywirusowego
 * przesłanych plików (ClamAV). W środowiskach bez dostępnego clamd
 * implementacja powinna gracefully degradować się (logowanie + pominięcie).
 */
interface VirusScannerInterface
{
    /**
     * Skanuje plik pod kątem wirusów.
     *
     * @param string $filePath ścieżka do fizycznego pliku
     * @return bool true jeśli plik jest czysty, false jeśli wykryto wirusa
     */
    public function scan(string $filePath): bool;

    /**
     * Czy skaner jest dostępny w bieżącym środowisku?
     */
    public function isAvailable(): bool;
}