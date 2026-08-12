<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EquipmentRepository;
use App\Repository\IncidentRepository;

/**
 * Serwis zarządzania awariami — sekcja Awaria (Etap 10).
 *
 * Operacje: lista (paginacja + filtry: typ, status, sprzęt), szczegóły
 * (komentarze + historia statusów + czas zakończenia), tworzenie zgłoszenia,
 * zmiana statusu (z historią), dodawanie komentarzy.
 *
 * Bezpieczeństwo:
 * - Walidacja danych wejściowych (enum typu i statusu, wymagany opis).
 * - Walidacja istnienia sprzętu gdy typ=sprzet (FK).
 * - `zgloszona_przez` i autor komentarza pobierani z JWT (ID zalogowanego usera).
 * - Audit log dla każdej akcji mutującej.
 */
final class IncidentService
{
    private const int MAX_OPIS_LENGTH = 5000;
    private const int MAX_COMMENT_LENGTH = 5000;
    private const array VALID_TYPES = ['sprzet', 'inne'];
    private const array VALID_STATUSES = ['zgloszona', 'w_trakcie_naprawy', 'naprawiona', 'zamknieta'];
    private const array TERMINAL_STATUSES = ['naprawiona', 'zamknieta'];

    public function __construct(
        private readonly IncidentRepository $incidentRepository,
        private readonly EquipmentRepository $equipmentRepository,
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Lista awarii z paginacją i filtrami.
     *
     * @param array{typ?: string, status?: string, equipment_id?: string, zrodlo?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->incidentRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->incidentRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Szczegóły awarii (z komentarzami i historią statusów).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $incident = $this->incidentRepository->findById($id);
        if ($incident === null) {
            return ['error' => 'Incident not found', 'code' => 404];
        }

        $dto = $this->toDto($incident);
        $dto['comments'] = array_map(fn (array $c): array => $this->commentToDto($c), $this->incidentRepository->findComments($id));
        $dto['status_history'] = array_map(fn (array $h): array => $this->historyToDto($h), $this->incidentRepository->findStatusHistory($id));

        return $dto;
    }

    /**
     * Tworzenie zgłoszenia awarii.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function create(array $data, Request $request): array
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ['error' => 'Authenticated user required', 'code' => 401];
        }

        $validation = $this->validateCreate($data);
        if ($validation !== null) {
            return $validation;
        }

        $typ = is_string($data['typ'] ?? null) ? $data['typ'] : 'inne';
        $equipmentId = $this->nullableInt($data['equipment_id'] ?? null);
        if ($typ === 'sprzet') {
            if ($equipmentId === null || $this->equipmentRepository->findById($equipmentId) === null) {
                return ['error' => 'Equipment not found', 'code' => 422];
            }
        } else {
            $equipmentId = null;
        }

        $opis = is_string($data['opis'] ?? null) ? $data['opis'] : '';

        $incident = $this->incidentRepository->createIncident([
            'typ' => $typ,
            'equipment_id' => $equipmentId,
            'opis' => $opis,
            'status' => 'zgloszona',
            'data_zakonczenia' => null,
            'zgloszona_przez' => $userId,
        ]);

        $id = $this->toInt($incident['id'] ?? 0);
        $this->auditLog($request, 'incident.create', $id);

        return $this->toDto($incident);
    }

    /**
     * Zmiana statusu awarii (z wpisem do historii).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function changeStatus(int $id, string $newStatus, Request $request): array
    {
        $incident = $this->incidentRepository->findById($id);
        if ($incident === null) {
            return ['error' => 'Incident not found', 'code' => 404];
        }

        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            return ['error' => 'Invalid status', 'code' => 422];
        }

        $userId = $this->userId($request);
        if ($userId === null) {
            return ['error' => 'Authenticated user required', 'code' => 401];
        }

        $oldStatus = is_string($incident['status'] ?? null) ? $incident['status'] : 'zgloszona';
        if ($oldStatus === $newStatus) {
            return $this->toDto($incident);
        }

        $updateData = ['status' => $newStatus];
        if (in_array($newStatus, self::TERMINAL_STATUSES, true)) {
            $updateData['data_zakonczenia'] = date('Y-m-d H:i:s');
        } else {
            $updateData['data_zakonczenia'] = null;
        }

        $updated = $this->incidentRepository->updateIncident($id, $updateData);
        $this->incidentRepository->addStatusHistory($id, $oldStatus, $newStatus, $userId);
        $this->auditLog($request, 'incident.change_status', $id);

        return $this->toDto($updated ?? $incident);
    }

    /**
     * Dodanie komentarza do awarii.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function addComment(int $id, string $tresc, Request $request): array
    {
        $incident = $this->incidentRepository->findById($id);
        if ($incident === null) {
            return ['error' => 'Incident not found', 'code' => 404];
        }

        $trimmed = trim($tresc);
        if ($trimmed === '') {
            return ['error' => 'Comment content is required', 'code' => 422];
        }
        if (mb_strlen($trimmed) > self::MAX_COMMENT_LENGTH) {
            return ['error' => 'Comment is too long', 'code' => 422];
        }

        $userId = $this->userId($request);
        if ($userId === null) {
            return ['error' => 'Authenticated user required', 'code' => 401];
        }

        $comment = $this->incidentRepository->addComment($id, $userId, $trimmed);
        $this->auditLog($request, 'incident.add_comment', $id);

        return $this->commentToDto($comment);
    }

    // --- DTO ---

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'typ' => is_string($row['typ'] ?? null) ? $row['typ'] : 'inne',
            'equipment_id' => $this->nullableInt($row['equipment_id'] ?? null),
            'equipment_nazwa' => is_string($row['equipment_nazwa'] ?? null) ? $row['equipment_nazwa'] : null,
            'opis' => is_string($row['opis'] ?? null) ? $row['opis'] : '',
            'status' => is_string($row['status'] ?? null) ? $row['status'] : 'zgloszona',
            'data_zgloszenia' => is_string($row['data_zgloszenia'] ?? null) ? $row['data_zgloszenia'] : null,
            'data_zakonczenia' => is_string($row['data_zakonczenia'] ?? null) ? $row['data_zakonczenia'] : null,
            'zgloszona_przez' => $this->nullableInt($row['zgloszona_przez'] ?? null),
            'zgloszona_przez_email' => is_string($row['zgloszona_przez_email'] ?? null) ? $row['zgloszona_przez_email'] : null,
            'zrodlo' => is_string($row['zrodlo'] ?? null) ? $row['zrodlo'] : 'panel',
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function commentToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'incident_id' => $this->toInt($row['incident_id'] ?? 0),
            'tresc' => is_string($row['tresc'] ?? null) ? $row['tresc'] : '',
            'user_id' => $this->toInt($row['user_id'] ?? 0),
            'user_email' => is_string($row['user_email'] ?? null) ? $row['user_email'] : null,
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function historyToDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'incident_id' => $this->toInt($row['incident_id'] ?? 0),
            'status_od' => is_string($row['status_od'] ?? null) ? $row['status_od'] : '',
            'status_do' => is_string($row['status_do'] ?? null) ? $row['status_do'] : '',
            'zmieniony_przez' => $this->toInt($row['zmieniony_przez'] ?? 0),
            'zmieniony_przez_email' => is_string($row['zmieniony_przez_email'] ?? null) ? $row['zmieniony_przez_email'] : null,
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
        ];
    }

    // --- Walidacja ---

    /**
     * @param array<string, mixed> $data
     * @return array{error: string, code: int}|null
     */
    private function validateCreate(array $data): ?array
    {
        $typ = is_string($data['typ'] ?? null) ? $data['typ'] : '';
        if ($typ === '' || !in_array($typ, self::VALID_TYPES, true)) {
            return ['error' => 'Invalid typ (sprzet|inne)', 'code' => 422];
        }

        $opis = is_string($data['opis'] ?? null) ? trim($data['opis']) : '';
        if ($opis === '') {
            return ['error' => 'Opis is required', 'code' => 422];
        }
        if (mb_strlen($opis) > self::MAX_OPIS_LENGTH) {
            return ['error' => 'Opis is too long', 'code' => 422];
        }

        return null;
    }

    // --- Pomocnicze ---

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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function userId(Request $request): ?int
    {
        $userId = $request->attribute('user_id');

        return is_int($userId) ? $userId : null;
    }

    private function auditLog(Request $request, string $action, int $resourceId): void
    {
        $userId = $request->attribute('user_id');
        $this->auditLogRepository->logFromRequest(
            is_int($userId) ? $userId : null,
            $action,
            $request,
            'incident',
            $resourceId,
        );
    }
}