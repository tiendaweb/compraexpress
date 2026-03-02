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
        $sql = 'SELECT p.id, p.name, p.price, p.img, p.is_active, p.category_id, c.name AS category_name, c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id';

        if ($status === 'active') {
            $sql .= ' WHERE p.is_active = 1';
        } elseif ($status === 'archived') {
            $sql .= ' WHERE p.is_active = 0';
        }

        $sql .= ' ORDER BY p.id DESC';
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll();
    }

    public function create(string $name, int $price, string $img, ?int $categoryId = null): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO products (name, price, img, category_id) VALUES (:name, :price, :img, :category_id)');
        $stmt->execute([
            ':name' => $name,
            ':price' => $price,
            ':img' => $img,
            ':category_id' => $categoryId,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, string $name, int $price, string $img, ?int $categoryId, bool $isActive): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE products SET name = :name, price = :price, img = :img, category_id = :category_id, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':price' => $price,
            ':img' => $img,
            ':category_id' => $categoryId,
            ':is_active' => $isActive ? 1 : 0,
        ]);

        return $this->findById($id);
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

        return $this->findById($id);
    }

    private function findById(int $id): ?array
    {
        $findStmt = $this->pdo->prepare('SELECT p.id, p.name, p.price, p.img, p.is_active, p.category_id, c.name AS category_name, c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = :id
            LIMIT 1');
        $findStmt->execute([':id' => $id]);
        $product = $findStmt->fetch();

        return $product === false ? null : $product;
    }
}
