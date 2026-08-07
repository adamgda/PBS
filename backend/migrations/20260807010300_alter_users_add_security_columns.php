<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Migracja: rozszerzenie tabeli users o kolumny bezpieczeństwa:
 * - failed_login_attempts: licznik nieudanych logowań
 * - locked_until: blokada konta do czasu (15 min po 5 próbach)
 * - password_changed_at: data ostatniej zmiany hasła (wymuszona zmiana + historia)
 * - must_change_password: flaga wymuszenia zmiany hasła (pierwsze logowanie)
 */
final class AlterUsersAddSecurityColumns extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'users', 'failed_login_attempts')) {
            $this->execute($pdo, "ALTER TABLE `users` ADD COLUMN `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0");
        }
        if (! $this->columnExists($pdo, 'users', 'locked_until')) {
            $this->execute($pdo, "ALTER TABLE `users` ADD COLUMN `locked_until` DATETIME NULL");
        }
        if (! $this->columnExists($pdo, 'users', 'password_changed_at')) {
            $this->execute($pdo, "ALTER TABLE `users` ADD COLUMN `password_changed_at` DATETIME NULL");
        }
        if (! $this->columnExists($pdo, 'users', 'must_change_password')) {
            $this->execute($pdo, "ALTER TABLE `users` ADD COLUMN `must_change_password` BOOLEAN NOT NULL DEFAULT FALSE");
        }
        // Indeks dla szybkiego wyszukiwania aktywnych kont
        $this->execute($pdo, "ALTER TABLE `users` ADD INDEX `idx_is_active` (`is_active`)");
    }

    public function down(PDO $pdo): void
    {
        foreach (['failed_login_attempts', 'locked_until', 'password_changed_at', 'must_change_password'] as $column) {
            if ($this->columnExists($pdo, 'users', $column)) {
                $this->execute($pdo, "ALTER TABLE `users` DROP COLUMN `{$column}`");
            }
        }
    }

    public function name(): string
    {
        return '20260807010300_alter_users_add_security_columns';
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $stmt->execute([$table, $column]);

        /** @var int|string|false $result */
        $result = $stmt->fetchColumn();

        return $result !== false && (int) $result > 0;
    }
}

return new AlterUsersAddSecurityColumns();