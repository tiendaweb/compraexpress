<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, price, img FROM products ORDER BY id DESC');

        return $stmt->fetchAll();
    }

    public function create(string $name, int $price, string $img): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO products (name, price, img) VALUES (:name, :price, :img)');
        $stmt->execute([
            ':name' => $name,
            ':price' => $price,
            ':img' => $img,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return [
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'img' => $img,
        ];
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
