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
        $stmt = $this->pdo->query('SELECT id, title, product_id, layout_json, latest_export_path, updated_at FROM flyers ORDER BY updated_at DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, title, product_id, layout_json, latest_export_path, updated_at FROM flyers WHERE id = :id LIMIT 1');
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

    public function createExport(int $flyerId, string $filePath, string $mimeType, int $fileSize, ?string $exportedBy): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO flyer_exports (flyer_id, file_path, mime_type, file_size, exported_by) VALUES (:flyer_id, :file_path, :mime_type, :file_size, :exported_by)');
        $stmt->bindValue(':flyer_id', $flyerId, PDO::PARAM_INT);
        $stmt->bindValue(':file_path', $filePath);
        $stmt->bindValue(':mime_type', $mimeType);
        $stmt->bindValue(':file_size', $fileSize, PDO::PARAM_INT);
        $stmt->bindValue(':exported_by', $exportedBy, $exportedBy === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        $this->pdo->prepare('UPDATE flyers SET latest_export_path = :path WHERE id = :id')
            ->execute([':path' => $filePath, ':id' => $flyerId]);

        return $this->findExport((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function exportsByFlyer(int $flyerId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, flyer_id, file_path, mime_type, file_size, exported_by, created_at FROM flyer_exports WHERE flyer_id = :flyer_id ORDER BY created_at DESC, id DESC');
        $stmt->execute([':flyer_id' => $flyerId]);
        return $stmt->fetchAll();
    }

    private function findExport(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, flyer_id, file_path, mime_type, file_size, exported_by, created_at FROM flyer_exports WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
