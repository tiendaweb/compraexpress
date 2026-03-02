<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FlyerRepository;
use App\Repositories\MediaRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SlideRepository;
use PDOException;

class ApiController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SlideRepository $slides,
        private readonly SettingRepository $settings,
        private readonly FlyerRepository $flyers,
        private readonly OrderRepository $orders,
        private readonly MediaRepository $media,
    ) {
    }

    public function bootstrap(): void
    {
        try {
            $this->json([
                'products' => $this->products->all('active'),
                'slides' => $this->slides->all(),
                'config' => $this->settings->all(),
            ]);
        } catch (PDOException $exception) {
            if ($this->isInstallationSchemaError($exception)) {
                $this->json([
                    'error' => 'Instalación incompleta',
                    'code' => 'INSTALLATION_INCOMPLETE',
                ], 503);
                return;
            }

            throw $exception;
        }
    }

    public function getProducts(): void
    {
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        $this->json($this->products->all($status));
    }

    public function createProduct(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        $name = trim((string) ($data['name'] ?? ''));
        $price = (int) ($data['price'] ?? 0);
        $img = trim((string) ($data['img'] ?? ''));

        if ($name === '' || $price <= 0) {
            $this->json(['error' => 'Nombre y precio son obligatorios.'], 422);
            return;
        }

        if ($img === '') {
            $img = 'https://via.placeholder.com/300x300/f0f0f0/999?text=Sin+Imagen';
        }

        $product = $this->products->create($name, $price, $img);
        $this->json($product, 201);
    }

    public function uploadMedia(): void
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            $this->json(['error' => 'Debes adjuntar un archivo en el campo "file".'], 422);
            return;
        }

        $file = $_FILES['file'];
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Error al subir archivo. Código: ' . $errorCode], 422);
            return;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'archivo');
        $size = (int) ($file['size'] ?? 0);

        $maxSize = 5 * 1024 * 1024;
        if ($size <= 0 || $size > $maxSize) {
            $this->json(['error' => 'El archivo debe pesar entre 1 byte y 5 MB.'], 422);
            return;
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($extension, $allowedExtensions, true)) {
            $this->json(['error' => 'Extensión no permitida. Usa jpg, jpeg, png, webp o gif.'], 422);
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpPath);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($mimeType, $allowedMimes, true)) {
            $this->json(['error' => 'Tipo MIME no permitido.'], 422);
            return;
        }

        $year = date('Y');
        $month = date('m');
        $relativeDir = '/uploads/' . $year . '/' . $month;
        $storageDir = dirname(__DIR__, 2) . '/storage' . $relativeDir;

        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            $this->json(['error' => 'No fue posible crear la carpeta de almacenamiento.'], 500);
            return;
        }

        $uniqueName = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $storageDir . '/' . $uniqueName;

        if (!move_uploaded_file($tmpPath, $target)) {
            $this->json(['error' => 'No fue posible guardar el archivo subido.'], 500);
            return;
        }

        $publicPath = $relativeDir . '/' . $uniqueName;
        $uploadedBy = isset($_POST['uploaded_by']) ? trim((string) $_POST['uploaded_by']) : null;
        $uploadedBy = $uploadedBy === '' ? null : $uploadedBy;
        $media = $this->media->create($originalName, $publicPath, $mimeType, $size, $uploadedBy);

        $this->json([
            'media' => $media,
            'path' => $publicPath,
        ], 201);
    }

    public function deleteProduct(int $id): void
    {
        $deleted = $this->products->delete($id);

        if (!$deleted) {
            $this->json(['error' => 'Producto no encontrado.'], 404);
            return;
        }

        $this->json(['ok' => true]);
    }

    public function getSlides(): void
    {
        $this->json($this->slides->all());
    }

    public function getSettings(): void
    {
        $this->json($this->settings->all());
    }

    public function getFlyers(): void
    {
        $this->json($this->flyers->all());
    }

    public function getFlyer(int $id): void
    {
        $flyer = $this->flyers->find($id);
        if ($flyer === null) {
            $this->json(['error' => 'Flyer no encontrado.'], 404);
            return;
        }

        $this->json($flyer);
    }

    public function saveFlyer(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $title = trim((string) ($data['title'] ?? ''));
        $layout = $data['layout'] ?? null;
        $productId = isset($data['product_id']) && $data['product_id'] !== '' ? (int) $data['product_id'] : null;

        if ($title === '' || !is_array($layout)) {
            $this->json(['error' => 'Título y layout son obligatorios.'], 422);
            return;
        }

        $layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE);
        if ($layoutJson === false) {
            $this->json(['error' => 'Layout inválido.'], 422);
            return;
        }

        if ($id !== null && $id > 0) {
            $flyer = $this->flyers->update($id, $title, $layoutJson, $productId);
            if ($flyer === null) {
                $this->json(['error' => 'Flyer no encontrado para actualizar.'], 404);
                return;
            }

            $this->json($flyer);
            return;
        }

        $this->json($this->flyers->create($title, $layoutJson, $productId), 201);
    }


    public function createOrder(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $customerName = $customerName === '' ? null : $customerName;
        $whatsappPayload = (string) ($data['whatsapp_payload'] ?? '');
        $total = (int) ($data['total'] ?? 0);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        if ($whatsappPayload === '' || $total <= 0 || $items === []) {
            $this->json(['error' => 'Payload de WhatsApp, total e ítems son obligatorios.'], 422);
            return;
        }

        $normalizedItems = [];
        foreach ($items as $item) {
            $name = trim((string) ($item['name_snapshot'] ?? ''));
            $price = (int) ($item['price_snapshot'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            $productId = isset($item['product_id']) && $item['product_id'] !== null ? (int) $item['product_id'] : null;

            if ($name === '' || $price <= 0 || $qty <= 0) {
                $this->json(['error' => 'Cada ítem requiere nombre, precio y cantidad válidos.'], 422);
                return;
            }

            $normalizedItems[] = [
                'product_id' => $productId,
                'name_snapshot' => $name,
                'price_snapshot' => $price,
                'qty' => $qty,
            ];
        }

        $order = $this->orders->create($customerName, $whatsappPayload, $total, $normalizedItems);
        $this->json($order, 201);
    }

    public function getOrders(): void
    {
        $archived = isset($_GET['archived']) && (int) $_GET['archived'] === 1;
        $this->json($archived ? $this->orders->allArchivedWithItems() : $this->orders->allActiveWithItems());
    }



    public function updateProductStatus(int $id): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : null;

        if ($isActive !== 0 && $isActive !== 1) {
            $this->json(['error' => 'Estado de producto inválido.'], 422);
            return;
        }

        $product = $this->products->updateStatus($id, $isActive === 1);
        if ($product === null) {
            $this->json(['error' => 'Producto no encontrado.'], 404);
            return;
        }

        $this->json($product);
    }

    public function updateOrderStatus(int $id): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        $status = trim((string) ($data['status'] ?? ''));
        $archiveFlag = isset($data['archived']) ? (int) $data['archived'] : 0;
        $allowedStatuses = ['nuevo', 'en_preparacion', 'en_viaje', 'entregado'];

        if ($archiveFlag === 1) {
            $order = $this->orders->archive($id);
            if ($order === null) {
                $this->json(['error' => 'Pedido no encontrado.'], 404);
                return;
            }

            $this->json($order);
            return;
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $this->json(['error' => 'Estado inválido.'], 422);
            return;
        }

        $changedBy = trim((string) ($data['changed_by'] ?? 'admin'));
        $order = $this->orders->updateStatus($id, $status, $changedBy);
        if ($order === null) {
            $this->json(['error' => 'Pedido no encontrado.'], 404);
            return;
        }

        $this->json($order);
    }

    private function isInstallationSchemaError(PDOException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $sqlState === '42S02'
            || str_contains($message, 'base table or view not found')
            || str_contains($message, 'table') && str_contains($message, "doesn't exist");
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
