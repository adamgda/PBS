<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;

/**
 * Interfejs migracji bazy danych.
 */
interface MigrationInterface
{
    public function up(PDO $pdo): void;

    public function down(PDO $pdo): void;

    public function name(): string;
}