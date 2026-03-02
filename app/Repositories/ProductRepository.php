<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(?string $status = null): array
    {
        $sql = 'SELECT id, name, price, img, is_active FROM products';

        if ($status === 'active') {
            $sql .= ' WHERE is_active = 1';
        } elseif ($status === 'archived') {
            $sql .= ' WHERE is_active = 0';
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $this->pdo->query($sql);

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

    public function updateStatus(int $id, bool $isActive): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE products SET is_active = :is_active WHERE id = :id');
        $stmt->execute([
            ':is_active' => $isActive ? 1 : 0,
            ':id' => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        $findStmt = $this->pdo->prepare('SELECT id, name, price, img, is_active FROM products WHERE id = :id LIMIT 1');
        $findStmt->execute([':id' => $id]);
        $product = $findStmt->fetch();

        return $product === false ? null : $product;
    }
}
