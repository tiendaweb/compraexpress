<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, slug, created_at, updated_at FROM categories ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, created_at, updated_at FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $category = $stmt->fetch();

        return $category === false ? null : $category;
    }

    public function createIfNotExists(string $name): array
    {
        $baseSlug = $this->slugify($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->findBySlug($slug) !== null) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $stmt = $this->pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $created = $this->findById($id);

        return $created ?? [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
        ];
    }

    public function findOrCreateByName(string $name): array
    {
        $trimmed = trim($name);
        $stmt = $this->pdo->prepare('SELECT id, name, slug, created_at, updated_at FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $stmt->execute([':name' => $trimmed]);
        $existing = $stmt->fetch();
        if ($existing !== false) {
            return $existing;
        }

        return $this->createIfNotExists($trimmed);
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, created_at, updated_at FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $category = $stmt->fetch();

        return $category === false ? null : $category;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug) ?? '';
        $slug = preg_replace('/[\s-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'categoria';
    }
}
