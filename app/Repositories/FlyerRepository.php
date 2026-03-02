<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class FlyerRepository
{
    private ?bool $hasTemplateColumns = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT ' . $this->flyerSelectColumns() . ' FROM flyers ORDER BY updated_at DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . $this->flyerSelectColumns() . ' FROM flyers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $title, string $layoutJson, ?int $productId, ?string $templateId, ?string $bgColor): array
    {
        if ($this->supportsTemplateColumns()) {
            $stmt = $this->pdo->prepare('INSERT INTO flyers (title, product_id, template_id, bg_color, layout_json) VALUES (:title, :product_id, :template_id, :bg_color, :layout_json)');
            $stmt->bindValue(':template_id', $templateId ?: 'custom');
            $stmt->bindValue(':bg_color', $bgColor ?: '#fffaf0');
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO flyers (title, product_id, layout_json) VALUES (:title, :product_id, :layout_json)');
        }

        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':layout_json', $layoutJson);
        $stmt->bindValue(':product_id', $productId, $productId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return $this->find((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, string $title, string $layoutJson, ?int $productId, ?string $templateId, ?string $bgColor): ?array
    {
        if ($this->supportsTemplateColumns()) {
            $stmt = $this->pdo->prepare('UPDATE flyers SET title = :title, product_id = :product_id, template_id = :template_id, bg_color = :bg_color, layout_json = :layout_json WHERE id = :id');
            $stmt->bindValue(':template_id', $templateId ?: 'custom');
            $stmt->bindValue(':bg_color', $bgColor ?: '#fffaf0');
        } else {
            $stmt = $this->pdo->prepare('UPDATE flyers SET title = :title, product_id = :product_id, layout_json = :layout_json WHERE id = :id');
        }

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

    private function flyerSelectColumns(): string
    {
        if ($this->supportsTemplateColumns()) {
            return 'id, title, product_id, template_id, bg_color, layout_json, latest_export_path, updated_at';
        }

        return "id, title, product_id, 'custom' AS template_id, '#fffaf0' AS bg_color, layout_json, latest_export_path, updated_at";
    }

    private function supportsTemplateColumns(): bool
    {
        if ($this->hasTemplateColumns !== null) {
            return $this->hasTemplateColumns;
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM flyers LIKE 'template_id'");
        $templateExists = $stmt->fetchColumn() !== false;
        $stmt = $this->pdo->query("SHOW COLUMNS FROM flyers LIKE 'bg_color'");
        $bgColorExists = $stmt->fetchColumn() !== false;

        $this->hasTemplateColumns = $templateExists && $bgColorExists;
        return $this->hasTemplateColumns;
    }
}
