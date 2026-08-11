<?php

declare(strict_types=1);

use App\Migrations\AbstractMigration;

/**
 * Migracja tabeli szybkich notatek to-do (Etap 19).
 *
 * Notatki są prywatne i przypisane do konta (user_id). Usunięcie użytkownika
 * kaskadowo usuwa jego notatki (ON DELETE CASCADE).
 */
final class CreateUserNotesTable extends AbstractMigration
{
    public function up(PDO $pdo): void
    {
        $this->execute($pdo, "
            CREATE TABLE `user_notes` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `tresc` VARCHAR(500) NOT NULL,
                `is_done` TINYINT(1) NOT NULL DEFAULT 0,
                `kolejnosc` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_user_notes_user_id` (`user_id`),
                INDEX `idx_user_notes_user_done` (`user_id`, `is_done`),
                INDEX `idx_user_notes_user_order` (`user_id`, `kolejnosc`),
                CONSTRAINT `fk_user_notes_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'user_notes')) {
            $this->execute($pdo, 'DROP TABLE `user_notes`');
        }
    }

    public function name(): string
    {
        return '20260814110000_create_user_notes_table';
    }
}

return new CreateUserNotesTable();
