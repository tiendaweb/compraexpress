<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        return $user === false ? null : $user;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user === false ? null : $user;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, email, role, created_at, updated_at FROM users ORDER BY id DESC');
        return $stmt->fetchAll();
    }

    public function create(string $name, string $email, string $passwordHash, string $role): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)');
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':role' => $role,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, string $name, string $email, string $role, ?string $passwordHash = null): ?array
    {
        if ($passwordHash !== null) {
            $stmt = $this->pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role, password_hash = :password_hash WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':password_hash' => $passwordHash,
            ]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
            ]);
        }

        if ($stmt->rowCount() === 0 && $this->findById($id) === null) {
            return null;
        }

        return $this->findById($id);
    }
}
