<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class OrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(?string $customerName, string $whatsappPayload, int $total, array $items): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO orders (customer_name, whatsapp_payload, total, status, archived) VALUES (:customer_name, :whatsapp_payload, :total, :status, :archived)');

        $this->pdo->beginTransaction();

        try {
            $stmt->execute([
                ':customer_name' => $customerName,
                ':whatsapp_payload' => $whatsappPayload,
                ':total' => $total,
                ':status' => 'nuevo',
                ':archived' => 0,
            ]);

            $orderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare('INSERT INTO order_items (order_id, product_id, name_snapshot, price_snapshot, qty) VALUES (:order_id, :product_id, :name_snapshot, :price_snapshot, :qty)');

            foreach ($items as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':name_snapshot' => $item['name_snapshot'],
                    ':price_snapshot' => $item['price_snapshot'],
                    ':qty' => $item['qty'],
                ]);
            }

            $this->pdo->commit();

            return $this->findWithItems($orderId) ?? [];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function allWithItems(?bool $archived = false): array
    {
        $archivedValue = $archived ? 1 : 0;
        $stmt = $this->pdo->prepare('SELECT id, customer_name, whatsapp_payload, total, status, archived, created_at FROM orders WHERE archived = :archived ORDER BY created_at DESC');
        $stmt->execute([':archived' => $archivedValue]);
        $orders = $stmt->fetchAll();

        if ($orders === []) {
            return [];
        }

        $orderIds = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemsStmt = $this->pdo->prepare("SELECT order_id, product_id, name_snapshot, price_snapshot, qty FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC");
        $itemsStmt->execute($orderIds);
        $items = $itemsStmt->fetchAll();

        $itemsByOrderId = [];
        foreach ($items as $item) {
            $orderId = (int) $item['order_id'];
            if (!isset($itemsByOrderId[$orderId])) {
                $itemsByOrderId[$orderId] = [];
            }

            $itemsByOrderId[$orderId][] = [
                'product_id' => $item['product_id'] !== null ? (int) $item['product_id'] : null,
                'name_snapshot' => $item['name_snapshot'],
                'price_snapshot' => (int) $item['price_snapshot'],
                'qty' => (int) $item['qty'],
            ];
        }

        return array_map(static function (array $order) use ($itemsByOrderId): array {
            $orderId = (int) $order['id'];

            return [
                'id' => $orderId,
                'customer_name' => $order['customer_name'],
                'whatsapp_payload' => $order['whatsapp_payload'],
                'total' => (int) $order['total'],
                'status' => $order['status'],
                'archived' => (int) $order['archived'],
                'created_at' => $order['created_at'],
                'items' => $itemsByOrderId[$orderId] ?? [],
            ];
        }, $orders);
    }


    public function allActiveWithItems(): array
    {
        return $this->allWithItems(false);
    }

    public function allArchivedWithItems(): array
    {
        return $this->allWithItems(true);
    }

    public function updateStatus(int $id, string $status, string $changedBy = 'system'): ?array
    {
        $current = $this->findWithItems($id);
        if ($current === null) {
            return null;
        }

        $stmt = $this->pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);

        if ((string) $current['status'] !== $status) {
            $this->insertStatusHistory($id, (string) $current['status'], $status, $changedBy);
        }

        return $this->findWithItems($id);
    }

    public function archive(int $id): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET archived = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->findWithItems($id);
    }

    private function insertStatusHistory(int $orderId, string $fromStatus, string $toStatus, string $changedBy): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_status_history (order_id, changed_by, previous_status, new_status) VALUES (:order_id, :changed_by, :previous_status, :new_status)'
        );

        $stmt->execute([
            ':order_id' => $orderId,
            ':changed_by' => $changedBy,
            ':previous_status' => $fromStatus,
            ':new_status' => $toStatus,
        ]);
    }

    private function findWithItems(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, customer_name, whatsapp_payload, total, status, archived, created_at FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();

        if ($order === false) {
            return null;
        }

        $itemsStmt = $this->pdo->prepare('SELECT product_id, name_snapshot, price_snapshot, qty FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $itemsStmt->execute([':order_id' => $id]);

        return [
            'id' => (int) $order['id'],
            'customer_name' => $order['customer_name'],
            'whatsapp_payload' => $order['whatsapp_payload'],
            'total' => (int) $order['total'],
            'status' => $order['status'],
            'archived' => (int) $order['archived'],
            'created_at' => $order['created_at'],
            'items' => array_map(static fn (array $item): array => [
                'product_id' => $item['product_id'] !== null ? (int) $item['product_id'] : null,
                'name_snapshot' => $item['name_snapshot'],
                'price_snapshot' => (int) $item['price_snapshot'],
                'qty' => (int) $item['qty'],
            ], $itemsStmt->fetchAll()),
        ];
    }
}
