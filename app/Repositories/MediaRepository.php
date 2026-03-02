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
}
