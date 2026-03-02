<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Repositories\FlyerRepository;
use App\Repositories\MediaRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SlideRepository;
use App\Repositories\UserRepository;
use PDOException;

class ApiController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly SlideRepository $slides,
        private readonly SettingRepository $settings,
        private readonly FlyerRepository $flyers,
        private readonly OrderRepository $orders,
        private readonly MediaRepository $media,
        private readonly UserRepository $users,
    ) {
    }

    public function bootstrap(): void
    {
        try {
            $this->json([
                'products' => $this->products->all('active'),
                'slides' => $this->slides->all(),
                'config' => $this->settings->all(),
                'categories' => $this->categories->all(),
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

    public function me(): void
    {
        $user = $this->requireAuth();
        $this->json(['user' => $this->sanitizeUser($user)]);
    }

    public function login(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        $email = trim(strtolower((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->json(['error' => 'Email y contraseña son obligatorios.'], 422);
            return;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $this->json(['error' => 'Credenciales inválidas.'], 401);
            return;
        }

        $role = strtolower((string) $user['role']);
        if (!in_array($role, ['admin', 'gestion'], true)) {
            $this->json(['error' => 'El usuario no tiene un rol válido.'], 403);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => $role,
        ];

        $this->json(['user' => $this->sanitizeUser($_SESSION['user'])]);
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        $this->json(['ok' => true]);
    }

    public function getProducts(): void
    {
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        $this->json($this->products->all($status));
    }

    public function createProduct(): void
    {
        $this->enforceRole(['admin', 'gestion']);

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

        $categoryId = isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null;

        $product = $this->products->create($name, $price, $img, $categoryId);
        $this->json($product, 201);
    }

    public function uploadMedia(): void
    {
        $this->enforceRole(['admin', 'gestion']);

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
        $uploadedBy = $this->currentUser()['email'] ?? null;
        $media = $this->media->create($originalName, $publicPath, $mimeType, $size, $uploadedBy);

        $this->json([
            'media' => $media,
            'path' => $publicPath,
        ], 201);
    }

    public function deleteProduct(int $id): void
    {
        $this->enforceRole(['admin', 'gestion']);

        $deleted = $this->products->delete($id);
        if (!$deleted) {
            $this->json(['error' => 'Producto no encontrado.'], 404);
            return;
        }

        $this->json(['ok' => true]);
    }


    public function updateProduct(int $id): void
    {
        $this->enforceRole(['admin', 'gestion']);

        $data = json_decode((string) file_get_contents('php://input'), true);
        $name = trim((string) ($data['name'] ?? ''));
        $price = (int) ($data['price'] ?? 0);
        $img = trim((string) ($data['img'] ?? ''));
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $categoryId = isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null;

        if ($name === '' || $price <= 0) {
            $this->json(['error' => 'Nombre y precio son obligatorios.'], 422);
            return;
        }

        if ($img === '') {
            $img = 'https://via.placeholder.com/300x300/f0f0f0/999?text=Sin+Imagen';
        }

        if ($isActive !== 0 && $isActive !== 1) {
            $this->json(['error' => 'Estado de producto inválido.'], 422);
            return;
        }

        $product = $this->products->update($id, $name, $price, $img, $categoryId, $isActive === 1);
        if ($product === null) {
            $this->json(['error' => 'Producto no encontrado.'], 404);
            return;
        }

        $this->json($product);
    }

    public function getCategories(): void
    {
        $this->json($this->categories->all());
    }

    public function createCategory(): void
    {
        $this->enforceRole(['admin', 'gestion']);

        $data = json_decode((string) file_get_contents('php://input'), true);
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            $this->json(['error' => 'El nombre de la categoría es obligatorio.'], 422);
            return;
        }

        $category = $this->categories->findOrCreateByName($name);
        $this->json($category, 201);
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
        $this->enforceRole(['admin']);
        $this->json($this->flyers->all());
    }

    public function getFlyer(int $id): void
    {
        $this->enforceRole(['admin']);

        $flyer = $this->flyers->find($id);
        if ($flyer === null) {
            $this->json(['error' => 'Flyer no encontrado.'], 404);
            return;
        }

        $this->json($flyer);
    }

    public function saveFlyer(): void
    {
        $this->enforceRole(['admin']);

        $data = json_decode((string) file_get_contents('php://input'), true);
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $title = trim((string) ($data['title'] ?? ''));
        $productId = isset($data['product_id']) && $data['product_id'] !== '' ? (int) $data['product_id'] : null;
        $templateId = trim((string) ($data['template_id'] ?? 'custom'));
        $bgColor = trim((string) ($data['bg_color'] ?? '#fffaf0'));
        $layout = $data['layout'] ?? [];

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
            $flyer = $this->flyers->update($id, $title, $layoutJson, $productId, $templateId, $bgColor);
            if ($flyer === null) {
                $this->json(['error' => 'Flyer no encontrado para actualizar.'], 404);
                return;
            }

            $this->json($flyer);
            return;
        }

        $this->json($this->flyers->create($title, $layoutJson, $productId, $templateId, $bgColor), 201);
    }

    public function exportFlyer(int $id): void
    {
        $this->enforceRole(['admin']);

        $flyer = $this->flyers->find($id);
        if ($flyer === null) {
            $this->json(['error' => 'Flyer no encontrado.'], 404);
            return;
        }

        $data = json_decode((string) file_get_contents('php://input'), true);
        $imagePayload = trim((string) ($data['image'] ?? ''));
        $exportedBy = $this->currentUser()['email'] ?? null;

        if (!preg_match('#^data:image/png;base64,#', $imagePayload)) {
            $this->json(['error' => 'La imagen debe enviarse en formato PNG base64 (data URL).'], 422);
            return;
        }

        $base64 = substr($imagePayload, strpos($imagePayload, ',') + 1);
        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            $this->json(['error' => 'No se pudo decodificar la imagen.'], 422);
            return;
        }

        $relativeDir = '/flyers/' . date('Y') . '/' . date('m');
        $storageDir = dirname(__DIR__, 2) . '/storage' . $relativeDir;
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            $this->json(['error' => 'No fue posible crear la carpeta para exportaciones de flyers.'], 500);
            return;
        }

        $fileName = 'flyer_' . $id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.png';
        $target = $storageDir . '/' . $fileName;
        if (file_put_contents($target, $binary) === false) {
            $this->json(['error' => 'No fue posible guardar la exportación del flyer.'], 500);
            return;
        }

        $publicPath = $relativeDir . '/' . $fileName;
        $export = $this->flyers->createExport($id, $publicPath, 'image/png', strlen($binary), $exportedBy);

        $this->json([
            'ok' => true,
            'export' => $export,
            'download_url' => $publicPath,
        ], 201);
    }

    public function listFlyerExports(int $id): void
    {
        $this->enforceRole(['admin']);

        $flyer = $this->flyers->find($id);
        if ($flyer === null) {
            $this->json(['error' => 'Flyer no encontrado.'], 404);
            return;
        }

        $this->json($this->flyers->exportsByFlyer($id));
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
        $this->enforceRole(['admin']);

        $archived = isset($_GET['archived']) && (int) $_GET['archived'] === 1;
        $this->json($archived ? $this->orders->allArchivedWithItems() : $this->orders->allActiveWithItems());
    }

    public function updateProductStatus(int $id): void
    {
        $this->enforceRole(['admin', 'gestion']);

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
        $this->enforceRole(['admin']);

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

        $changedBy = $this->currentUser()['email'] ?? 'system';
        $order = $this->orders->updateStatus($id, $status, $changedBy);
        if ($order === null) {
            $this->json(['error' => 'Pedido no encontrado.'], 404);
            return;
        }

        $this->json($order);
    }

    private function enforceRole(array $allowedRoles): void
    {
        $role = $this->resolveRole();
        if (!in_array($role, $allowedRoles, true)) {
            $this->json(['error' => 'No tienes permisos para realizar esta acción.'], 403);
            exit;
        }
    }

    private function resolveRole(): string
    {
        $user = $this->requireAuth();
        return strtolower((string) ($user['role'] ?? ''));
    }

    private function requireAuth(): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->json(['error' => 'Debes iniciar sesión.'], 401);
            exit;
        }

        return $user;
    }

    private function currentUser(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || !isset($user['id'])) {
            return null;
        }

        $dbUser = $this->users->findById((int) $user['id']);
        if ($dbUser === null) {
            return null;
        }

        return [
            'id' => (int) $dbUser['id'],
            'name' => (string) $dbUser['name'],
            'email' => (string) $dbUser['email'],
            'role' => strtolower((string) $dbUser['role']),
        ];
    }

    private function sanitizeUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => strtolower((string) $user['role']),
        ];
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
