<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\AlertConfigRepository;
use App\Repository\AlertNotificationRepository;
use App\Repository\AlertSourceRepository;

/**
 * Mechanizm sprawdzania warunków alertów i wysyłki e-mail (Etap 14).
 *
 * Uruchamiany przez cron/queue (`bin/alerts.php`). Dla każdej aktywnej
 * konfiguracji alertu sprawdza odpowiedni warunek, wysyła powiadomienie SMTP
 * i loguje wysyłkę do `alert_notifications`, aby nie wysyłać duplikatów w ciągu dnia.
 *
 * Warunki (zgodnie z dokumentacją techniczną sekcja 13):
 *  - certyfikat_wygasa   → `employee_documents.data_waznosci` ≤ 30 dni
 *  - przeglad_wymagany   → `vehicle_service_plans.data_nastepnego_planowanego` ≤ 30 dni
 *  - brak_raportu_oc     → brak `daily_vehicle_reports` na dziś (po godzinie `czas_wysylki`)
 *  - awaria_zgloszona    → nowe `incidents` (od północy)
 */
final class AlertService
{
    private const int INSPECTION_DAYS = 30;
    private const int CERT_EXPIRY_DAYS = 30;

    public function __construct(
        private readonly AlertConfigRepository $alertConfigRepository,
        private readonly AlertNotificationRepository $alertNotificationRepository,
        private readonly AlertSourceRepository $alertSourceRepository,
        private readonly MailService $mailService,
    ) {}

    /**
     * Wykonuje pełny cykl sprawdzania i wysyłki alertów.
     *
     * @param \DateTimeImmutable|null $now Punkt odniesienia czasu (domyślnie teraz) — do testów
     * @return array{checked: int, sent: int, failed: int, by_type: array<string, int>}
     */
    public function run(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now');
        $sent = 0;
        $failed = 0;
        $byType = array_fill_keys(AlertSettingsService::ALLOWED_TYPES, 0);
        $checked = 0;

        $today = $now->format('Y-m-d');
        $configs = $this->alertConfigRepository->findAllActive();

        foreach ($configs as $config) {
            $configId = $this->toInt($config['id'] ?? 0);
            $type = is_string($config['typ_alertu'] ?? null) ? $config['typ_alertu'] : '';
            $email = is_string($config['email_odbiorcy'] ?? null) ? $config['email_odbiorcy'] : '';

            if ($email === '' || !in_array($type, AlertSettingsService::ALLOWED_TYPES, true)) {
                continue;
            }

            // Dla „braku raportu OC” uwzględnij tylko jeśli minęła godzina wysyłki.
            if ($type === 'brak_raportu_oc' && !$this->isSendTimeReached($config['czas_wysylki'] ?? null, $now)) {
                continue;
            }

            $items = $this->resolveItems($type, $today, $config);
            foreach ($items as $item) {
                $refType = $this->refTypeFor($type);
                $refId = $this->toInt($item['id'] ?? 0);
                if ($refId === 0 || $this->alertNotificationRepository->alreadySent($configId, $refType, $refId, $today)) {
                    continue;
                }

                $checked++;
                $subject = $this->subjectFor($type);
                $body = $this->bodyFor($type, $item, $today);

                if ($this->mailService->sendAlert($email, $subject, $body)) {
                    $this->alertNotificationRepository->markSent($configId, $type, $refType, $refId, $today);
                    $sent++;
                    $byType[$type]++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'checked' => $checked,
            'sent' => $sent,
            'failed' => $failed,
            'by_type' => $byType,
        ];
    }

    /**
     * Rozwiązuje listę encji do sprawdzenia dla danego typu alertu.
     *
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(string $type, string $today, array $config): array
    {
        return match ($type) {
            'certyfikat_wygasa' => $this->alertSourceRepository->expiringCertificates(self::CERT_EXPIRY_DAYS),
            'przeglad_wymagany' => $this->alertSourceRepository->upcomingInspections(self::INSPECTION_DAYS),
            'brak_raportu_oc' => $this->alertSourceRepository->vehiclesMissingOcReport($today),
            'awaria_zgloszona' => $this->alertSourceRepository->newIncidents($today . ' 00:00:00'),
            default => [],
        };
    }

    private function refTypeFor(string $type): string
    {
        return match ($type) {
            'certyfikat_wygasa' => 'cert',
            'przeglad_wymagany' => 'inspection',
            'brak_raportu_oc' => 'oc',
            'awaria_zgloszona' => 'incident',
            default => 'other',
        };
    }

    private function subjectFor(string $type): string
    {
        return match ($type) {
            'certyfikat_wygasa' => '[PBS] Uprawnienie/certyfikat wygasa',
            'przeglad_wymagany' => '[PBS] Zbliża się przegląd pojazdu',
            'brak_raportu_oc' => '[PBS] Brak raportu obsługi codziennej (OC)',
            'awaria_zgloszona' => '[PBS] Zgłoszono nową awarię',
            default => '[PBS] Powiadomienie',
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function bodyFor(string $type, array $item, string $today): string
    {
        return match ($type) {
            'certyfikat_wygasa' => 'Uprawnienie/certyfikat wygasa w ciągu 30 dni:'
                . "\n  - Dokument: " . $this->str($item['nazwa'])
                . "\n  - Pracownik: " . $this->str($item['imie']) . ' ' . $this->str($item['nazwisko'])
                . "\n  - Numer: " . $this->str($item['numer_dokumentu'])
                . "\n  - Data ważności: " . $this->str($item['data_waznosci'])
                . "\n\nPozdrawiamy,\nZespół PBS",
            'przeglad_wymagany' => 'Zbliża się przegląd pojazdu:'
                . "\n  - Pojazd: " . $this->str($item['nazwa']) . ' (' . $this->str($item['numer_seryjny']) . ')'
                . "\n  - Typ przeglądu: " . $this->str($item['typ_przegladu'])
                . "\n  - Planowana data: " . $this->str($item['data_nastepnego_planowanego'])
                . "\n\nPozdrawiamy,\nZespół PBS",
            'brak_raportu_oc' => 'Dla pojazdu nie wpłynął raport obsługi codziennej (OC):'
                . "\n  - Pojazd: " . $this->str($item['nazwa']) . ' (' . $this->str($item['numer_seryjny']) . ')'
                . "\n  - Data raportu: " . $today
                . "\n\nProsimy o uzupełnienie raportu OC w panelu PBS."
                . "\n\nPozdrawiamy,\nZespół PBS",
            'awaria_zgloszona' => 'Zgłoszono nową awarię w systemie PBS:'
                . "\n  - Nr zgłoszenia: " . $this->str($item['id'])
                . "\n  - Sprzęt: " . $this->str($item['equipment_nazwa'])
                . "\n  - Opis: " . $this->str($item['opis'])
                . "\n  - Status: " . $this->str($item['status'])
                . "\n  - Data zgłoszenia: " . $this->str($item['data_zgloszenia'])
                . "\n\nPozdrawiamy,\nZespół PBS",
            default => 'Powiadomienie z systemu PBS.',
        };
    }

    /**
     * Sprawdza, czy punkt odniesienia minął godzinę wysyłki (np. 10:00).
     */
    private function isSendTimeReached(mixed $czasWysylki, \DateTimeImmutable $now): bool
    {
        if (!is_string($czasWysylki) || $czasWysylki === '') {
            return false;
        }

        $parts = explode(':', $czasWysylki);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        $nowMinutes = (int) $now->format('G') * 60 + (int) $now->format('i');

        return $nowMinutes >= ($hour * 60 + $minute);
    }

    private function str(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '-';
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
