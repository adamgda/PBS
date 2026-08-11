<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AlertConfigRepository;
use App\Repository\AuditLogRepository;

/**
 * Serwis konfiguracji alertów — sekcja Ustawienia → Alerty (Etap 14).
 *
 * Operacje CRUD na tabeli `alert_settings` (odbiorcy e-mail, typy alertów,
 * aktywność, czas wysyłki). Zawiera walidację wejścia, mapowanie na bezpieczny
 * DTO oraz audit log dla każdej operacji mutującej.
 */
final class AlertSettingsService
{
    /** Dopuszczalne typy alertów (zgodne z ENUM w migracji alert_settings). */
    public const array ALLOWED_TYPES = [
        'certyfikat_wygasa',
        'przeglad_wymagany',
        'brak_raportu_oc',
        'awaria_zgloszona',
    ];

    public function __construct(
        private readonly AlertConfigRepository $alertConfigRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Lista konfiguracji alertów.
     *
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function list(): array
    {
        $rows = $this->alertConfigRepository->findAll();

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
        ];
    }

    /**
     * Szczegóły pojedynczej konfiguracji.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $row = $this->alertConfigRepository->findById($id);
        if ($row === null) {
            return ['error' => 'Alert config not found', 'code' => 404];
        }

        return $this->toDto($row);
    }

    /**
     * Utworzenie konfiguracji alertu.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function create(array $data, Request $request): array
    {
        $email = is_string($data['email_odbiorcy'] ?? null) ? trim($data['email_odbiorcy']) : '';
        $typ = is_string($data['typ_alertu'] ?? null) ? $data['typ_alertu'] : '';
        $isActive = (bool) ($data['czy_aktywny'] ?? true);
        $czasWysylki = $this->normalizeSendTime($data['czas_wysylki'] ?? null);

        $validation = $this->validate($email, $typ, $czasWysylki);
        if ($validation !== null) {
            return $validation;
        }

        $config = $this->alertConfigRepository->createConfig([
            'email_odbiorcy' => $email,
            'typ_alertu' => $typ,
            'czy_aktywny' => $isActive,
            'czas_wysylki' => $czasWysylki,
        ]);

        $userId = $this->currentUserId($request);
        $this->auditLogRepository->logFromRequest(
            $userId,
            'alert_config_created',
            $request,
            'alert_config',
            $this->toInt($config['id'] ?? 0),
        );

        return $this->toDto($config);
    }

    /**
     * Edycja konfiguracji alertu.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, array $data, Request $request): array
    {
        $existing = $this->alertConfigRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Alert config not found', 'code' => 404];
        }

        $email = is_string($data['email_odbiorcy'] ?? null) ? trim($data['email_odbiorcy']) : '';
        $typ = is_string($data['typ_alertu'] ?? null) ? $data['typ_alertu'] : '';
        $isActive = array_key_exists('czy_aktywny', $data) ? (bool) $data['czy_aktywny'] : (bool) ($existing['czy_aktywny'] ?? true);
        $czasWysylki = $this->normalizeSendTime($data['czas_wysylki'] ?? ($existing['czas_wysylki'] ?? null));

        $validation = $this->validate($email, $typ, $czasWysylki);
        if ($validation !== null) {
            return $validation;
        }

        $updated = $this->alertConfigRepository->updateConfig($id, [
            'email_odbiorcy' => $email,
            'typ_alertu' => $typ,
            'czy_aktywny' => $isActive,
            'czas_wysylki' => $czasWysylki,
        ]);

        $userId = $this->currentUserId($request);
        $this->auditLogRepository->logFromRequest(
            $userId,
            'alert_config_updated',
            $request,
            'alert_config',
            $id,
        );

        return $this->toDto($updated ?? ['id' => $id]);
    }

    /**
     * Usunięcie konfiguracji alertu.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function delete(int $id, Request $request): array
    {
        $existing = $this->alertConfigRepository->findById($id);
        if ($existing === null) {
            return ['error' => 'Alert config not found', 'code' => 404];
        }

        $this->alertConfigRepository->delete($id);

        $userId = $this->currentUserId($request);
        $this->auditLogRepository->logFromRequest(
            $userId,
            'alert_config_deleted',
            $request,
            'alert_config',
            $id,
        );

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'email_odbiorcy' => is_string($row['email_odbiorcy'] ?? null) ? $row['email_odbiorcy'] : '',
            'typ_alertu' => is_string($row['typ_alertu'] ?? null) ? $row['typ_alertu'] : '',
            'czy_aktywny' => (bool) ($row['czy_aktywny'] ?? false),
            'czas_wysylki' => is_string($row['czas_wysylki'] ?? null) ? $row['czas_wysylki'] : null,
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * Walidacja reguł. Zwraca strukturę błędu albo null (gdy dane poprawne).
     *
     * @return array{error: string, code: int}|null
     */
    private function validate(string $email, string $typ, ?string $czasWysylki): ?array
    {
        if ($email === '') {
            return ['error' => 'Email is required', 'code' => 422];
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['error' => 'Invalid email address', 'code' => 422];
        }
        if (!in_array($typ, self::ALLOWED_TYPES, true)) {
            return ['error' => 'Invalid alert type', 'code' => 422];
        }
        // Dla alertu „brak raportu OC” godzina wysyłki jest wymagana.
        if ($typ === 'brak_raportu_oc' && $czasWysylki === null) {
            return ['error' => 'Send time is required for missing OC report alert', 'code' => 422];
        }

        return null;
    }

    /**
     * Normalizuje czas wysyłki (HH:MM[:SS]) do formatu "HH:MM:SS" lub zwraca null.
     */
    private function normalizeSendTime(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = explode(':', trim($value));
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    private function currentUserId(Request $request): ?int
    {
        $value = $request->attribute('user_id');

        return is_numeric($value) ? (int) $value : null;
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
