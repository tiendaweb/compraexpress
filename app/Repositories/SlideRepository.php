<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class SlideRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, image, text FROM slides ORDER BY sort_order ASC, id ASC');

        return $stmt->fetchAll();
    }
}
