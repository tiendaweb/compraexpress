<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class MediaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $fileName, string $filePath, string $mimeType, int $fileSize, ?string $uploadedBy): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media (file_name, file_path, mime_type, file_size, uploaded_by) VALUES (:file_name, :file_path, :mime_type, :file_size, :uploaded_by)'
        );
        $stmt->execute([
            ':file_name' => $fileName,
            ':file_path' => $filePath,
            ':mime_type' => $mimeType,
            ':file_size' => $fileSize,
            ':uploaded_by' => $uploadedBy,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'uploaded_by' => $uploadedBy,
        ];
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, file_name, file_path, mime_type, file_size, uploaded_by, created_at FROM media ORDER BY created_at DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, file_name, file_path, mime_type, file_size, uploaded_by, created_at FROM media WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM media WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
