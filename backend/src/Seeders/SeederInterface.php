<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

/**
 * Interfejs seedera danych.
 */
interface SeederInterface
{
    public function run(PDO $pdo): void;

    public function name(): string;
}