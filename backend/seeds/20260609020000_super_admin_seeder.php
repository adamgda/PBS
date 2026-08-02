<?php

declare(strict_types=1);

use App\Seeders\SeederInterface;

final class SuperAdminSeeder implements SeederInterface
{
    public function run(PDO $pdo): void
    {
        $email = 'admin@pbs.local';
        $passwordHash = password_hash('ZmienMnie123!', PASSWORD_BCRYPT);
        $permissions = json_encode([
            'dashboard' => true,
            'pracownicy' => true,
            'sprzet' => true,
            'terminale' => true,
            'harmonogram' => true,
            'analityka' => true,
            'raportowanie' => true,
            'ustawienia' => true,
            'awaria' => true,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO `users` (`email`, `password_hash`, `role`, `permissions`, `is_active`)
             VALUES (?, ?, ?, ?, TRUE)
             ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `role` = VALUES(`role`), `permissions` = VALUES(`permissions`)',
        );
        $stmt->execute([$email, $passwordHash, 'super_admin', $permissions]);
    }

    public function name(): string
    {
        return 'super_admin_seeder';
    }
}

return new SuperAdminSeeder();