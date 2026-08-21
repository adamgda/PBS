<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\ExportRepository;

/**
 * Serwis eksportu CSV — sekcja „Eksport danych".
 *
 * Odpowiada za: walidację typu eksportu (biała lista), pobranie wierszy
 * z `ExportRepository` oraz serializację do CSV zgodnie z RFC 4180
 * (CRLF, ucieczka podwójnymi cudzysłowami, BOM UTF-8 dla poprawności w Excelu).
 *
 * Każdy typ ma zdefiniowaną listę nagłówków (kolumn) i nazwę pliku wynikowego.
 */
final class ExportService
{
    /** Kolumny (nagłówki) per typ eksportu. */
    private const array HEADERS = [
        'orders' => [
            'Id zlecenia', 'Numer zlecenia', 'Klient', 'Terminal', 'Data rozpoczęcia',
            'Data zakończenia', 'Zakres prac', 'Wartość (PLN)', 'Status',
            'Pracownik', 'Rola', 'Godziny', 'Stawka/h (PLN)',
        ],
        'employees' => [
            'Id pracownika', 'Imię', 'Nazwisko', 'Telefon', 'E-mail', 'Aktywny', 'Terminal', 'Sprzęt',
        ],
        'equipment' => [
            'Id sprzętu', 'Nazwa', 'Kategoria', 'Nr seryjny', 'Aktywny', 'Terminal',
            'Przypisany pracownik', 'Ostatni przebieg', 'Ostatni serwis olejowy',
            'Ostatnia awaria', 'Data OC', 'Wynik OC', 'Następny przegląd',
        ],
        'incidents' => [
            'Id awarii', 'Typ', 'Sprzęt', 'Opis', 'Status', 'Data zgłoszenia',
            'Data zakończenia', 'Zgłosił', 'Czas trwania (h)',
        ],
        'daily_reports' => [
            'Typ raportu', 'Data', 'Obiekt', 'Opis', 'Uwagi', 'Utworzył',
        ],
    ];

    /** Nazwa pliku (bez rozszerzenia) per typ eksportu. */
    private const array FILENAMES = [
        'orders' => 'zlecenia-rozliczenia',
        'employees' => 'pracownicy',
        'equipment' => 'sprzet',
        'incidents' => 'awarie',
        'daily_reports' => 'raporty-dzienne',
    ];

    public function __construct(
        private readonly ExportRepository $repository,
    ) {}

    /**
     * Generuje zawartość CSV dla wskazanego typu.
     *
     * @return array{content: string, filename: string}|array{error: string, code: int}
     */
    public function export(string $type, string $from, string $to): array
    {
        $headers = self::HEADERS[$type] ?? null;
        if ($headers === null) {
            return ['error' => 'Unsupported export type', 'code' => 422];
        }

        $rows = $this->rows($type, $from, $to);

        $content = $this->buildCsv($headers, $rows);

        return [
            'content' => $content,
            'filename' => self::FILENAMES[$type] ?? 'eksport',
        ];
    }

    /**
     * Zwraca wiersze dla wskazanego typu z repozytorium.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $type, string $from, string $to): array
    {
        return match ($type) {
            'orders' => $this->repository->orders($from, $to),
            'employees' => $this->repository->employees(),
            'equipment' => $this->repository->equipment(),
            'incidents' => $this->repository->incidents($from, $to),
            'daily_reports' => $this->repository->dailyReports($from, $to),
            default => [],
        };
    }

    /**
     * Buduje treść CSV: BOM + nagłówek + wiersze (RFC 4180).
     *
     * @param array<int, string> $headers
     * @param array<int, array<string, mixed>> $rows
     */
    private function buildCsv(array $headers, array $rows): string
    {
        $lines = [$this->escapeRow($headers)];

        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                $key = $this->columnKey($header);
                $values[] = $row[$key] ?? '';
            }
            $lines[] = $this->escapeRow($values);
        }

        // BOM UTF-8 — poprawne kodowanie polskich znaków w Excelu.
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Mapuje nagłówek (etykietę) na klucz kolumny w wierszu z bazy.
     */
    private function columnKey(string $header): string
    {
        $map = [
            'Id zlecenia' => 'order_id',
            'Numer zlecenia' => 'numer_zlecenia',
            'Klient' => 'klient_nazwa',
            'Terminal' => 'terminal_nazwa',
            'Data rozpoczęcia' => 'data_rozpoczecia',
            'Data zakończenia' => 'data_zakonczenia',
            'Zakres prac' => 'zakres_prac',
            'Wartość (PLN)' => 'wartosc_pln',
            'Status' => 'status',
            'Pracownik' => 'pracownik',
            'Rola' => 'rola',
            'Godziny' => 'godziny',
            'Stawka/h (PLN)' => 'stawka_godzinowa',
            'Id pracownika' => 'employee_id',
            'Imię' => 'imie',
            'Nazwisko' => 'nazwisko',
            'Telefon' => 'telefon',
            'E-mail' => 'email',
            'Aktywny' => 'is_active',
            'Sprzęt' => 'sprzet_nazwa',
            'Id sprzętu' => 'equipment_id',
            'Nazwa' => 'nazwa',
            'Kategoria' => 'kategoria',
            'Nr seryjny' => 'numer_seryjny',
            'Przypisany pracownik' => 'przypisany_pracownik',
            'Ostatni przebieg' => 'ostatni_przebieg',
            'Ostatni serwis olejowy' => 'ostatni_serwis_olejowy',
            'Ostatnia awaria' => 'ostatnia_awaria',
            'Data OC' => 'data_ostatniej_oc',
            'Wynik OC' => 'wynik_ostatniej_oc',
            'Następny przegląd' => 'data_nastepnego_przegladu',
            'Id awarii' => 'incident_id',
            'Typ' => 'typ',
            'Opis' => 'opis',
            'Data zgłoszenia' => 'data_zgloszenia',
            'Zgłosił' => 'zgloszona_przez_email',
            'Czas trwania (h)' => 'czas_trwania_godziny',
            'Typ raportu' => 'typ_raportu',
            'Data' => 'data_raportu',
            'Obiekt' => 'obiekt',
            'Uwagi' => 'uwagi',
            'Utworzył' => 'utworzono_przez',
        ];

        return $map[$header] ?? $header;
    }

    /**
     * @param array<int, mixed> $values
     */
    private function escapeRow(array $values): string
    {
        $escaped = array_map(
            static fn (mixed $value): string => self::escape($value),
            $values,
        );

        return implode(',', $escaped);
    }

    private static function escape(mixed $value): string
    {
        if ($value === null || !is_scalar($value)) {
            return '';
        }

        $str = (string) $value;

        // Użycie podwójnego cudzysłowu podwaja się zgodnie z RFC 4180.
        if (str_contains($str, ',') || str_contains($str, '"') || str_contains($str, "\n") || str_contains($str, "\r")) {
            return '"' . str_replace('"', '""', $str) . '"';
        }

        return $str;
    }
}
