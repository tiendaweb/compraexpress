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
        $stmt = $this->pdo->query('SELECT id, image, text, sort_order FROM slides ORDER BY sort_order ASC, id ASC');

        return $stmt->fetchAll();
    }

    public function create(string $image, string $text, int $sortOrder): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO slides (image, text, sort_order) VALUES (:image, :text, :sort_order)');
        $stmt->execute([
            ':image' => $image,
            ':text' => $text,
            ':sort_order' => $sortOrder,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? [];
    }

    public function update(int $id, string $image, string $text, int $sortOrder): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE slides SET image = :image, text = :text, sort_order = :sort_order WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':image' => $image,
            ':text' => $text,
            ':sort_order' => $sortOrder,
        ]);

        if ($stmt->rowCount() === 0 && $this->findById($id) === null) {
            return null;
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM slides WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, image, text, sort_order FROM slides WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $slide = $stmt->fetch();

        return $slide === false ? null : $slide;
    }
}
