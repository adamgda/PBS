<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Repository\UserRepository;

/**
 * Middleware Permission — sprawdza uprawnienia użytkownika per sekcja.
 * Wymaga wcześniejszego AuthMiddleware (user_id i role w atrybutach).
 *
 * Uprawnienia wczytywane są z bazy danych (UserRepository) na podstawie user_id,
 * dzięki czemu backend zawsze egzekwuje AKTUALNE uprawnienia — niezależnie od tego,
 * kiedy wystawiono token JWT. Eliminuje to problem „stale token" (rozjazd między
 * uprawnieniami we froncie a backendem po zmianie uprawnień w adminie).
 * super_admin ma bypass.
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int, string> $requiredSections wymagane sekcje uprawnień (np. ['ustawienia'])
     * @param array<int, string> $superAdminBypass role pomijające sprawdzenie uprawnień
     */
    public function __construct(
        private readonly array $requiredSections = [],
        private readonly array $superAdminBypass = ['super_admin'],
        private readonly ?UserRepository $userRepository = null,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $role = $request->attribute('role');

        if (is_string($role) && in_array($role, $this->superAdminBypass, true)) {
            return $next($request);
        }

        $userId = $request->attribute('user_id');
        $permissions = $this->loadPermissions(is_int($userId) ? $userId : 0, $request);

        foreach ($this->requiredSections as $section) {
            $hasPermission = $permissions[$section] ?? false;
            if (!$hasPermission) {
                return Response::error(403, "Access denied to section: {$section}");
            }
        }

        return $next($request);
    }

    /**
     * Wczytuje uprawnienia użytkownika — z bazy (gdy podano UserRepository) lub
     * z claima JWT (fallback dla testów / braku repozytorium).
     *
     * @return array<string, bool>
     */
    private function loadPermissions(int $userId, Request $request): array
    {
        if ($this->userRepository === null) {
            /** @var array<string, bool>|null $jwtPermissions */
            $jwtPermissions = $request->attribute('permissions');

            return is_array($jwtPermissions) ? $jwtPermissions : [];
        }

        $user = $this->userRepository->findById($userId);

        return $user !== null ? $this->decodePermissions($user['permissions'] ?? null) : [];
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
            return [];
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            if (is_bool($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}