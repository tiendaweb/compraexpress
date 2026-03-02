<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class FlyerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, title, product_id, layout_json, updated_at FROM flyers ORDER BY updated_at DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, title, product_id, layout_json, updated_at FROM flyers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $title, string $layoutJson, ?int $productId): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO flyers (title, product_id, layout_json) VALUES (:title, :product_id, :layout_json)');
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':layout_json', $layoutJson);
        $stmt->bindValue(':product_id', $productId, $productId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return $this->find((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, string $title, string $layoutJson, ?int $productId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE flyers SET title = :title, product_id = :product_id, layout_json = :layout_json WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':layout_json', $layoutJson);
        $stmt->bindValue(':product_id', $productId, $productId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0 ? $this->find($id) : null;
    }
}
