<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Serwis polityki haseł — walidacja zgodnie z dokumentacją (sekcja 9.1.1).
 *
 * Zasady:
 * - Min. 12 znaków, max 128
 * - Min. 3 z 4 klas znaków (małe, wielkie, cyfry, specjalne)
 * - Blokada popularnych haseł
 * - Hasło nie może zawierać adresu e-mail użytkownika
 */
final class PasswordPolicyService
{
    /** @var array<int, string> */
    private readonly array $commonPasswords;

    public function __construct()
    {
        // Statyczna lista najpopularniejszych haseł — docelowo haveibeenpwned API
        $this->commonPasswords = [
            'password', 'Password1!', 'password123', '123456789012',
            'qwerty123456', 'admin123456', 'letmein12345', 'welcome12345',
            'ZmienMnie123!', 'haslo123456', '123456789Abc', 'ChangeMe123!',
        ];
    }

    /**
     * @return array<int, string> Lista błędów walidacji (pusta = hasło poprawne)
     */
    public function validate(string $password, ?string $email = null): array
    {
        $errors = [];

        $length = strlen($password);
        if ($length < 12) {
            $errors[] = 'Password must be at least 12 characters long';
        }
        if ($length > 128) {
            $errors[] = 'Password must not exceed 128 characters';
        }

        $classes = $this->countCharacterClasses($password);
        if ($classes < 3) {
            $errors[] = 'Password must contain at least 3 of 4 character classes: lowercase, uppercase, digits, special';
        }

        if (in_array($password, $this->commonPasswords, true)) {
            $errors[] = 'Password is too common';
        }

        if ($email !== null && $email !== '' && str_contains(strtolower($password), strtolower($email))) {
            $errors[] = 'Password must not contain your email address';
        }

        return $errors;
    }

    public function isValid(string $password, ?string $email = null): bool
    {
        return $this->validate($password, $email) === [];
    }

    public function hash(string $password): string
    {
        // Argon2id preferowany, fallback bcrypt cost ≥ 12
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }

        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    private function countCharacterClasses(string $password): int
    {
        $classes = 0;
        if (preg_match('/[a-z]/', $password) === 1) {
            $classes++;
        }
        if (preg_match('/[A-Z]/', $password) === 1) {
            $classes++;
        }
        if (preg_match('/[0-9]/', $password) === 1) {
            $classes++;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $password) === 1) {
            $classes++;
        }

        return $classes;
    }
}