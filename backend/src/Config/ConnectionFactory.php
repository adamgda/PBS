<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * Fabryka połączeń PDO z MySQL.
 */
final class ConnectionFactory
{
    /**
     * Tworzy połączenie PDO na podstawie konfiguracji.
     *
     * @param array<string, string> $dbConfig klucze: host, name, login, password, charset
     */
    public static function make(array $dbConfig): PDO
    {
        $host = $dbConfig['host'] ?? 'localhost';
        $name = $dbConfig['name'] ?? 'pbs';
        $user = $dbConfig['login'] ?? 'root';
        $pass = $dbConfig['password'] ?? '';
        $charset = $dbConfig['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return $pdo;
    }

    public static function fromConfig(Config $config): PDO
    {
        return self::make([
            'host' => $config->get('DATABASE_HOST', 'localhost') ?? 'localhost',
            'name' => $config->get('DATABASE_NAME', 'pbs') ?? 'pbs',
            'login' => $config->get('DATABASE_LOGIN', 'root') ?? 'root',
            'password' => $config->get('DATABASE_PASSWORD', '') ?? '',
            'charset' => $config->get('DATABASE_CHARSET', 'utf8mb4') ?? 'utf8mb4',
        ]);
    }
}