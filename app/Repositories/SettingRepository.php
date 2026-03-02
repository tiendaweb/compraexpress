<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class SettingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT `key`, `value` FROM settings');
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }
}
