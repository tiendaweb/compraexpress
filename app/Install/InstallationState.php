<?php

declare(strict_types=1);

function isValidInstallation(string $configPath): bool
{
    if (!file_exists($configPath)) {
        return false;
    }

    $config = require $configPath;
    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
        return false;
    }

    try {
        $pdo = installationCreatePdo($config['db']);

        $requiredTables = ['settings', 'products', 'slides', 'users'];
        foreach ($requiredTables as $table) {
            $tableStmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = :db_name AND table_name = :table_name LIMIT 1');
            $tableStmt->execute([
                ':db_name' => (string) $config['db']['name'],
                ':table_name' => $table,
            ]);

            if ($tableStmt->fetchColumn() === false) {
                return false;
            }
        }

        $sentinelStmt = $pdo->prepare("SELECT 1 FROM settings WHERE `key` = :key LIMIT 1");
        $sentinelStmt->execute([':key' => 'currency']);

        return $sentinelStmt->fetchColumn() !== false;
    } catch (\Throwable) {
        return false;
    }
}

function installationCreatePdo(array $db): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    return new PDO($dsn, (string) $db['user'], (string) $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
