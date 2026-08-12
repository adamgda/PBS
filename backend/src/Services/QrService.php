<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\DailyVehicleReportRepository;
use App\Repository\EquipmentRepository;
use App\Repository\IncidentRepository;

/**
 * Serwis kodów QR dla maszyn (Etap 20).
 *
 * Odpowiada za:
 *  - (re)generację publicznego tokena QR maszyny i unieważnienie starego,
 *  - przygotowanie danych do wydruku naklejki (QR SVG + publiczny URL),
 *  - publiczny podgląd maszyny po tokenie (bez danych osobowych),
 *  - anonimowe zgłoszenia awarii (`incidents`, `zrodlo='qr'`),
 *  - anonimowe raporty obsługi codziennej (`daily_vehicle_reports`, `zrodlo='qr'`).
 *
 * Bezpieczeństwo:
 *  - token generowany losowo (`random_bytes(32)`, hex) — nigdy `id` maszyny,
 *  - publiczne endpointy nie ujawniają danych osobowych,
 *  - walidacja danych wejściowych (długość opisu, przebieg, typ).
 */
final class QrService
{
    private const int MAX_OPIS_LENGTH = 5000;
    private const int MAX_OC_LENGTH = 5000;
    private const int MAX_UWAGI_LENGTH = 5000;
    private const int MAX_KONTAKT_LENGTH = 255;
    private const array VALID_TYPES = ['sprzet', 'inne'];

    public function __construct(
        private readonly EquipmentRepository $equipmentRepository,
        private readonly IncidentRepository $incidentRepository,
        private readonly DailyVehicleReportRepository $vehicleReportRepository,
        private readonly QrCodeService $qrCodeService,
        private readonly string $frontendBaseUrl,
    ) {}

    /**
     * (Re)generacja tokena QR maszyny — unieważnia poprzedni.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function generateToken(int $equipmentId, Request $request): array
    {
        $equipment = $this->equipmentRepository->findById($equipmentId);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        if (!$this->isQrEligible($equipment)) {
            return ['error' => 'QR code is available only for vehicles (or "inne")', 'code' => 422];
        }

        $token = bin2hex(random_bytes(32));
        $updated = $this->equipmentRepository->setQrToken($equipmentId, $token);

        $dto = $this->toDto($updated ?? $equipment);
        $dto['qr_token'] = $token;
        $dto['public_url'] = $this->publicUrl($token);

        return $dto;
    }

    /**
     * Dane QR + kod do wydruku naklejki (autoryzowane).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function qrInfo(int $equipmentId): array
    {
        $equipment = $this->equipmentRepository->findById($equipmentId);
        if ($equipment === null) {
            return ['error' => 'Equipment not found', 'code' => 404];
        }

        if (!$this->isQrEligible($equipment)) {
            return ['error' => 'QR code is available only for vehicles (or "inne")', 'code' => 422];
        }

        $token = is_string($equipment['qr_token'] ?? null) ? $equipment['qr_token'] : null;
        if ($token === null || $token === '') {
            return ['error' => 'QR token not generated yet', 'code' => 409];
        }

        $nazwa = is_string($equipment['nazwa'] ?? null) ? $equipment['nazwa'] : '';
        $numer = is_string($equipment['numer_seryjny'] ?? null) ? $equipment['numer_seryjny'] : '';
        $kategoria = is_string($equipment['kategoria'] ?? null) ? $equipment['kategoria'] : 'inne';

        return [
            'qr_token' => $token,
            'public_url' => $this->publicUrl($token),
            'qr_svg' => $this->qrCodeService->svg($this->publicUrl($token)),
            'machine' => [
                'id' => $equipmentId,
                'nazwa' => $nazwa,
                'numer_seryjny' => $numer,
                'kategoria' => $kategoria,
            ],
        ];
    }

    /**
     * Publiczna informacja o maszynie po tokenie (bez danych osobowych).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function machine(string $token): array
    {
        $equipment = $this->equipmentRepository->findPublicByQrToken($token);
        if ($equipment === null) {
            return ['error' => 'Machine not found', 'code' => 404];
        }

        return [
            'id' => $this->toInt($equipment['id'] ?? 0),
            'kategoria' => is_string($equipment['kategoria'] ?? null) ? $equipment['kategoria'] : 'inne',
            'nazwa' => is_string($equipment['nazwa'] ?? null) ? $equipment['nazwa'] : '',
            'numer_seryjny' => is_string($equipment['numer_seryjny'] ?? null) ? $equipment['numer_seryjny'] : null,
            'is_active' => (bool) ($equipment['is_active'] ?? true),
        ];
    }

    /**
     * Anonimowe zgłoszenie awarii maszyny z QR.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createIncident(string $token, array $data): array
    {
        $equipment = $this->equipmentRepository->findPublicByQrToken($token);
        if ($equipment === null) {
            return ['error' => 'Machine not found', 'code' => 404];
        }

        $validation = $this->validateIncident($data);
        if ($validation !== null) {
            return $validation;
        }

        $equipmentId = $this->toInt($equipment['id'] ?? 0);
        $typ = is_string($data['typ'] ?? null) && in_array($data['typ'], self::VALID_TYPES, true) ? $data['typ'] : 'sprzet';
        $opis = $this->buildOpis($data);

        $incident = $this->incidentRepository->createIncident([
            'typ' => $typ,
            'equipment_id' => $equipmentId,
            'opis' => $opis,
            'status' => 'zgloszona',
            'zrodlo' => 'qr',
            'zgloszona_przez' => null,
        ]);

        return [
            'id' => $this->toInt($incident['id'] ?? 0),
            'numer_zgloszenia' => 'AWR-' . str_pad((string) $this->toInt($incident['id'] ?? 0), 6, '0', STR_PAD_LEFT),
            'status' => 'zgloszona',
        ];
    }

    /**
     * Anonimowy raport obsługi codziennej (OC) maszyny z QR.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createDailyReport(string $token, array $data): array
    {
        $equipment = $this->equipmentRepository->findPublicByQrToken($token);
        if ($equipment === null) {
            return ['error' => 'Machine not found', 'code' => 404];
        }

        $validation = $this->validateDailyReport($data);
        if ($validation !== null) {
            return $validation;
        }

        $equipmentId = $this->toInt($equipment['id'] ?? 0);
        $dataRaportu = is_string($data['data_raportu'] ?? null) ? trim($data['data_raportu']) : date('Y-m-d');

        // Unikalność: jeden raport dla pojazdu i daty (UNIQUE(equipment_id, data_raportu)).
        if ($this->vehicleReportRepository->existsForEquipmentAndDate($equipmentId, $dataRaportu)) {
            return ['error' => 'Daily report for this equipment and date already exists', 'code' => 409];
        }

        $report = $this->vehicleReportRepository->create([
            'equipment_id' => $equipmentId,
            'data_raportu' => $dataRaportu,
            'aktualny_przebieg' => $this->toInt($data['aktualny_przebieg'] ?? 0),
            'przebieg_oc' => is_string($data['przebieg_oc'] ?? null) ? trim($data['przebieg_oc']) : '',
            'uwagi' => $this->nullableString($data['uwagi'] ?? null),
            'utworzony_przez' => null,
            'zrodlo' => 'qr',
        ]);

        return [
            'id' => $this->toInt($report['id'] ?? 0),
            'equipment_id' => $equipmentId,
            'data_raportu' => $dataRaportu,
            'status' => 'ok',
        ];
    }


    // --- Pomocnicze ---

    /**
     * @param array<string, mixed> $equipment
     */
    private function isQrEligible(array $equipment): bool
    {
        $kategoria = is_string($equipment['kategoria'] ?? null) ? $equipment['kategoria'] : '';

        return $kategoria === 'pojazd' || $kategoria === 'inne';
    }

    private function publicUrl(string $token): string
    {
        $base = rtrim($this->frontendBaseUrl, '/');

        return $base . '/qr/' . $token;
    }

    /**
     * @param array<string, mixed> $equipment
     * @return array<string, mixed>
     */
    private function toDto(array $equipment): array
    {
        return [
            'id' => $this->toInt($equipment['id'] ?? 0),
            'kategoria' => is_string($equipment['kategoria'] ?? null) ? $equipment['kategoria'] : 'inne',
            'nazwa' => is_string($equipment['nazwa'] ?? null) ? $equipment['nazwa'] : '',
            'numer_seryjny' => is_string($equipment['numer_seryjny'] ?? null) ? $equipment['numer_seryjny'] : null,
            'is_active' => (bool) ($equipment['is_active'] ?? true),
        ];
    }

    /**
     * Buduje opis zgłoszenia (z opcjonalnym polem kontaktu).
     *
     * @param array<string, mixed> $data
     */
    private function buildOpis(array $data): string
    {
        $opis = is_string($data['opis'] ?? null) ? trim($data['opis']) : '';
        $kontakt = is_string($data['kontakt'] ?? null) ? trim($data['kontakt']) : '';

        if ($kontakt !== '') {
            $opis = 'Kontakt: ' . $kontakt . "\n\n" . $opis;
        }

        return $opis;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validateIncident(array $data): ?array
    {
        $typ = is_string($data['typ'] ?? null) ? $data['typ'] : 'sprzet';
        if (!in_array($typ, self::VALID_TYPES, true)) {
            return ['error' => 'Invalid typ (sprzet|inne)', 'code' => 422];
        }

        $opis = is_string($data['opis'] ?? null) ? trim($data['opis']) : '';
        if ($opis === '') {
            return ['error' => 'Opis is required', 'code' => 422];
        }
        if (mb_strlen($opis) > self::MAX_OPIS_LENGTH) {
            return ['error' => 'Opis is too long', 'code' => 422];
        }

        $kontakt = $data['kontakt'] ?? null;
        if ($kontakt !== null && (!is_string($kontakt) || mb_strlen(trim($kontakt)) > self::MAX_KONTAKT_LENGTH)) {
            return ['error' => 'Kontakt is too long', 'code' => 422];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validateDailyReport(array $data): ?array
    {
        $przebieg = $data['aktualny_przebieg'] ?? null;
        if (!is_numeric($przebieg) || (int) $przebieg < 0) {
            return ['error' => 'Invalid aktualny_przebieg', 'code' => 422];
        }

        $oc = is_string($data['przebieg_oc'] ?? null) ? trim($data['przebieg_oc']) : '';
        if ($oc === '') {
            return ['error' => 'Przebieg_oc is required', 'code' => 422];
        }
        if (mb_strlen($oc) > self::MAX_OC_LENGTH) {
            return ['error' => 'Przebieg_oc is too long', 'code' => 422];
        }

        $uwagi = $data['uwagi'] ?? null;
        if ($uwagi !== null && (!is_string($uwagi) || mb_strlen(trim($uwagi)) > self::MAX_UWAGI_LENGTH)) {
            return ['error' => 'Uwagi is too long', 'code' => 422];
        }

        $dataRaportu = $data['data_raportu'] ?? null;
        if ($dataRaportu !== null && (is_string($dataRaportu) && $dataRaportu !== '' && strtotime($dataRaportu) === false)) {
            return ['error' => 'Invalid data_raportu', 'code' => 422];
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return null;
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

