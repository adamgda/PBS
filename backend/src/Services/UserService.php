<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;

/**
 * Serwis zarządzania użytkownikami — sekcja Ustawienia → Użytkownicy.
 *
 * Operacje: lista (paginacja + filtry), tworzenie (email → link do set-password),
 * edycja, aktualizacja uprawnień, blokowanie/odblokowanie, usuwanie.
 *
 * Bezpieczeństwo:
 * - password_hash nigdy nie wychodzi z API (DTO mapping w toDto()).
 * - Nie można usunąć/zablokować własnego konta ani konta super_admin (jeśli nie jesteś super_admin).
 * - IDOR: każda operacja weryfikuje istnienie zasobu.
 * - Audit log dla każdej akcji.
 *
 * Tworzenie użytkownika reuse'uje mechanizm tokenów resetujących (password_reset_tokens)
 * — nowy użytkownik dostaje e-mail z linkiem do /set-password, a konto ma flagę
 * must_change_password=true do pierwszego ustawienia hasła.
 */
final class UserService
{
    /** Dopuszczalne sekcje uprawnień (zgodne z menu PBS). */
    private const array ALLOWED_SECTIONS = [
        'dashboard', 'pracownicy', 'sprzet', 'terminale',
        'harmonogram', 'analityka', 'raportowanie', 'ustawienia', 'awaria',
    ];

    /** Dopuszczalne role. */
    private const array ALLOWED_ROLES = ['super_admin', 'admin', 'user'];

    private const int RESET_TOKEN_TTL_MINUTES = 60;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordResetRepository $passwordResetRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly MailService $mailService,
        private readonly string $frontendBaseUrl = 'http://localhost:4200',
        private readonly bool $debug = false,
    ) {}

    /**
     * Lista użytkowników z paginacją i filtrami.
     *
     * @param array{email?: string, role?: string, is_active?: string, sort?: string, direction?: string} $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
        $direction = is_string($filters['direction'] ?? null) ? $filters['direction'] : 'asc';

        $rows = $this->userRepository->search($filters, $perPage, $offset, $sort, $direction);
        $total = $this->userRepository->countSearch($filters);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Szczegóły użytkownika.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function get(int $id): array
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return ['error' => 'User not found', 'code' => 404];
        }

        return $this->toDto($user);
    }

    /**
     * Tworzenie użytkownika — email → link do ustawienia hasła.
     *
     * @param array<string, mixed> $permissions
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function create(string $email, string $role, array $permissions, Request $request): array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email', 'code' => 422];
        }
        if (strlen($email) > 255) {
            return ['error' => 'Email is too long', 'code' => 422];
        }
        // Role są predefiniowane: z zarządzania użytkownikami (Ustawienia) można
        // tworzyć wyłącznie konta Administratora. Super Administratorzy oraz konta
        // „użytkownik" (pracownicy) nie są tworzone z tego poziomu.
        if ($role !== 'admin') {
            return ['error' => 'Only admin accounts can be created here', 'code' => 422];
        }

        $existing = $this->userRepository->findByEmail($email);
        if ($existing !== null) {
            return ['error' => 'Email already registered', 'code' => 409];
        }

        $permissions = $this->normalizePermissions($permissions);

        // Placeholder hasła — losowy ciąg, który nie przejdzie password_verify().
        // Konto pozostaje w statusie „zaproszony" do ustawienia hasła przez link.
        $placeholderHash = bin2hex(random_bytes(32));

        $user = $this->userRepository->createUser([
            'email' => $email,
            'password_hash' => $placeholderHash,
            'role' => 'admin',
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $userId = $this->toInt($user['id'] ?? 0);

        $token = $this->issueSetPasswordInvite($userId, $email);
        $this->auditLogRepository->logFromRequest(
            $this->toInt($request->attribute('user_id')),
            'user_created',
            $request,
            'user',
            $userId,
            ['email' => $email, 'role' => 'admin'],
        );

        $dto = $this->toDto($user);
        if ($this->debug) {
            $dto['set_password_url'] = rtrim($this->frontendBaseUrl, '/') . '/set-password?token=' . $token;
        }

        return $dto;
    }

    /**
     * Tworzy konto użytkownika dla pracownika (rola 'user') z predefiniowanym,
     * ograniczonym dostępem — wyłącznie sekcje „awaria" oraz „raportowanie"
     * (raportowanie obsługi codziennej OC, Etap 20). Konto otrzymuje e-mail
     * z linkiem do ustawienia hasła (status „zaproszony" do pierwszego logowania).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function createEmployeeAccount(string $email, Request $request): array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email', 'code' => 422];
        }
        if (strlen($email) > 255) {
            return ['error' => 'Email is too long', 'code' => 422];
        }

        $existing = $this->userRepository->findByEmail($email);
        if ($existing !== null) {
            return ['error' => 'Email already registered', 'code' => 409];
        }

        // Predefiniowane uprawnienia pracownika: zgłaszanie awarii + raportowanie obsługi.
        $permissions = $this->normalizePermissions([]);
        $permissions['awaria'] = true;
        $permissions['raportowanie'] = true;

        $placeholderHash = bin2hex(random_bytes(32));

        $user = $this->userRepository->createUser([
            'email' => $email,
            'password_hash' => $placeholderHash,
            'role' => 'user',
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $userId = $this->toInt($user['id'] ?? 0);

        $token = $this->issueSetPasswordInvite($userId, $email);
        $this->auditLogRepository->logFromRequest(
            $this->toInt($request->attribute('user_id')),
            'employee_account_created',
            $request,
            'user',
            $userId,
            ['email' => $email, 'role' => 'user'],
        );

        $dto = $this->toDto($user);
        if ($this->debug) {
            $dto['set_password_url'] = rtrim($this->frontendBaseUrl, '/') . '/set-password?token=' . $token;
        }

        return $dto;
    }

    /**
     * Generuje jednorazowy token set-password (reuse password_reset_tokens),
     * zapisuje go w bazie i wysyła e-mail z linkiem.
     *
     * @return string surowy token (do wstawienia w linku)
     */
    private function issueSetPasswordInvite(int $userId, string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::RESET_TOKEN_TTL_MINUTES * 60);
        $this->passwordResetRepository->createToken($userId, $tokenHash, $expiresAt);

        $this->mailService->sendPasswordResetEmail($email, $token, $this->frontendBaseUrl);

        return $token;
    }

    /**
     * Edycja użytkownika (email, role, opcjonalnie is_active — blokada/odblok).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, string $email, string $role, Request $request, ?bool $isActive = null): array
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return ['error' => 'User not found', 'code' => 404];
        }

        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email', 'code' => 422];
        }
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            return ['error' => 'Invalid role', 'code' => 422];
        }

        // Unikalność e-maila (poza własnym rekordem)
        $existing = $this->userRepository->findByEmail($email);
        if ($existing !== null && $this->toInt($existing['id']) !== $id) {
            return ['error' => 'Email already registered', 'code' => 409];
        }

        $data = [
            'email' => $email,
            'role' => $role,
        ];

        if ($isActive !== null) {
            // Ochrona przed samoblokadą i modyfikacją konta super_admin (gdy nie jesteś super_admin)
            $currentUserId = $this->toInt($request->attribute('user_id'));
            if ($id === $currentUserId && $isActive === false) {
                return ['error' => 'Cannot block your own account', 'code' => 422];
            }
            $userRole = is_string($user['role'] ?? null) ? $user['role'] : '';
            $currentRole = $request->attribute('role');
            if ($userRole === 'super_admin' && $currentRole !== 'super_admin') {
                return ['error' => 'Cannot modify super_admin account', 'code' => 403];
            }
            $data['is_active'] = $isActive;
        }

        $updated = $this->userRepository->update($id, $data);

        $this->auditLogRepository->logFromRequest(
            $this->toInt($request->attribute('user_id')),
            'user_updated',
            $request,
            'user',
            $id,
            ['email' => $email, 'role' => $role, 'is_active' => $isActive],
        );

        return $updated !== null ? $this->toDto($updated) : ['error' => 'User not found', 'code' => 404];
    }

    /**
     * Aktualizacja uprawnień per sekcja.
     *
     * @param array<string, mixed> $permissions
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function updatePermissions(int $id, array $permissions, Request $request): array
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return ['error' => 'User not found', 'code' => 404];
        }

        $normalized = $this->normalizePermissions($permissions);
        $this->userRepository->updatePermissions($id, $normalized);

        $this->auditLogRepository->logFromRequest(
            $this->toInt($request->attribute('user_id')),
            'user_permissions_updated',
            $request,
            'user',
            $id,
            ['permissions' => $normalized],
        );

        $refreshed = $this->userRepository->findById($id);

        return $refreshed !== null ? $this->toDto($refreshed) : ['error' => 'User not found', 'code' => 404];
    }

    /**
     * Blokowanie / odblokowanie konta (is_active).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function toggleActive(int $id, bool $active, Request $request): array
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return ['error' => 'User not found', 'code' => 404];
        }

        $currentUserId = $this->toInt($request->attribute('user_id'));
        if ($id === $currentUserId) {
            return ['error' => 'Cannot block your own account', 'code' => 422];
        }

        $role = is_string($user['role'] ?? null) ? $user['role'] : '';
        $currentRole = $request->attribute('role');
        if ($role === 'super_admin' && $currentRole !== 'super_admin') {
            return ['error' => 'Cannot modify super_admin account', 'code' => 403];
        }

        $this->userRepository->setActive($id, $active);
        $this->auditLogRepository->logFromRequest(
            $currentUserId,
            $active ? 'user_unblocked' : 'user_blocked',
            $request,
            'user',
            $id,
        );

        $refreshed = $this->userRepository->findById($id);

        return $refreshed !== null ? $this->toDto($refreshed) : ['error' => 'User not found', 'code' => 404];
    }

    /**
     * Usunięcie użytkownika (anonimizacja e-maila + deaktywacja wg ducha RODO).
     *
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function delete(int $id, Request $request): array
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            return ['error' => 'User not found', 'code' => 404];
        }

        $currentUserId = $this->toInt($request->attribute('user_id'));
        if ($id === $currentUserId) {
            return ['error' => 'Cannot delete your own account', 'code' => 422];
        }

        $role = is_string($user['role'] ?? null) ? $user['role'] : '';
        $currentRole = $request->attribute('role');
        if ($role === 'super_admin' && $currentRole !== 'super_admin') {
            return ['error' => 'Cannot delete super_admin account', 'code' => 403];
        }

        // Anonimizacja e-maila + deaktywacja (zamiast fizycznego usuwania — polityka RODO).
        $anonymizedEmail = 'deleted_' . $id . '@pbs.local';
        $this->userRepository->update($id, [
            'email' => $anonymizedEmail,
            'password_hash' => bin2hex(random_bytes(32)),
            'is_active' => false,
            'permissions' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        $this->auditLogRepository->logFromRequest(
            $currentUserId,
            'user_deleted',
            $request,
            'user',
            $id,
        );

        return ['success' => true];
    }

    /**
     * Mapuje wiersz z DB na bezpieczny DTO (bez password_hash, failed_login_attempts itp.).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0),
            'email' => is_string($row['email'] ?? null) ? $row['email'] : '',
            'role' => is_string($row['role'] ?? null) ? $row['role'] : 'user',
            'permissions' => $this->decodePermissions($row['permissions'] ?? null),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'must_change_password' => (bool) ($row['must_change_password'] ?? false),
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    /**
     * Filtruje uprawnienia do dopuszczalnych sekcji (mass assignment protection).
     *
     * @param array<string, mixed> $permissions
     * @return array<string, bool>
     */
    private function normalizePermissions(array $permissions): array
    {
        $normalized = [];
        foreach (self::ALLOWED_SECTIONS as $section) {
            $normalized[$section] = (bool) ($permissions[$section] ?? false);
        }

        return $normalized;
    }

    /**
     * @param mixed $permissions
     * @return array<string, bool>
     */
    private function decodePermissions(mixed $permissions): array
    {
        $decoded = $permissions;
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
        }

        if (!is_array($decoded)) {
            $normalized = [];
            foreach (self::ALLOWED_SECTIONS as $section) {
                $normalized[$section] = false;
            }

            return $normalized;
        }

        $normalized = [];
        foreach (self::ALLOWED_SECTIONS as $section) {
            $normalized[$section] = (bool) ($decoded[$section] ?? false);
        }

        return $normalized;
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