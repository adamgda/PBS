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
     * @param array<string, mixed> $dbConfig klucze: host, name, login, password, charset, timeout, persistent
     */
    public static function make(array $dbConfig): PDO
    {
        $host = is_string($dbConfig['host'] ?? null) ? $dbConfig['host'] : 'localhost';
        $name = is_string($dbConfig['name'] ?? null) ? $dbConfig['name'] : 'pbs';
        $user = is_string($dbConfig['login'] ?? null) ? $dbConfig['login'] : 'root';
        $pass = is_string($dbConfig['password'] ?? null) ? $dbConfig['password'] : '';
        $charset = is_string($dbConfig['charset'] ?? null) ? $dbConfig['charset'] : 'utf8mb4';
        $timeout = is_numeric($dbConfig['timeout'] ?? null) ? (int) $dbConfig['timeout'] : 5;
        $persistent = (bool) ($dbConfig['persistent'] ?? false);

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Timeout połączenia z MySQL (dokumentacja 14.5): 5 s, brak retry.
            PDO::ATTR_TIMEOUT => $timeout,
        ];

        // Connection pooling (dokumentacja 14.4) — opcjonalnie, przez .env.
        if ($persistent) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
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
            'timeout' => $config->get('DB_TIMEOUT', '5') ?? '5',
            'persistent' => $config->getBool('DB_PERSISTENT', false),
        ]);
    }
}