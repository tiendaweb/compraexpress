<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class SettingRepository
{
    private const DEFAULTS = [
        'currency' => '$',
        'whatsappNumber' => '573001234567',
        'appName' => 'Pañalería y Algo Más',
        'appLogo' => '',
        'socialLinks' => '[]',
        'address' => '',
        'googleMapsEmbed' => '',
        'appIcon' => 'fa-solid fa-baby-carriage',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT `key`, `value` FROM settings');
        $rows = $stmt->fetchAll();

        $settings = self::DEFAULTS;
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

    public function upsertMany(array $settings): array
    {
        if ($settings === []) {
            return $this->all();
        }

        $stmt = $this->pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');

        foreach ($settings as $key => $value) {
            $stmt->execute([
                ':key' => $key,
                ':value' => (string) $value,
            ]);
        }

        return $this->all();
    }
}
