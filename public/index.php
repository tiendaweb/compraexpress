<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Repositories\FlyerRepository;
use App\Repositories\MediaRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SlideRepository;

$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$path = normalizePath($requestPath, $basePath);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!isValidInstallation($configPath)) {
    header('Location: ' . appUrl($basePath, '/install.php'));
    exit;
}

require_once $root . '/app/bootstrap.php';
ensurePublicUploadsSymlink($root);

if (str_starts_with($path, '/api/')) {
    $controller = new ApiController(
        new ProductRepository(db()),
        new SlideRepository(db()),
        new SettingRepository(db()),
        new FlyerRepository(db()),
        new OrderRepository(db()),
        new MediaRepository(db())
    );

    if ($method === 'GET' && $path === '/api/bootstrap') {
        $controller->bootstrap();
        return;
    }

    if ($method === 'GET' && $path === '/api/products') {
        $controller->getProducts();
        return;
    }

    if ($method === 'POST' && $path === '/api/products') {
        $controller->createProduct();
        return;
    }

    if ($method === 'DELETE' && preg_match('#^/api/products/(\d+)$#', $path, $matches)) {
        $controller->deleteProduct((int) $matches[1]);
        return;
    }

    if ($method === 'PATCH' && preg_match('#^/api/products/(\d+)/status$#', $path, $matches)) {
        $controller->updateProductStatus((int) $matches[1]);
        return;
    }

    if ($method === 'GET' && $path === '/api/orders') {
        $controller->getOrders();
        return;
    }

    if ($method === 'POST' && $path === '/api/orders') {
        $controller->createOrder();
        return;
    }

    if ($method === 'PATCH' && preg_match('#^/api/orders/(\d+)/status$#', $path, $matches)) {
        $controller->updateOrderStatus((int) $matches[1]);
        return;
    }

    if ($method === 'GET' && $path === '/api/slides') {
        $controller->getSlides();
        return;
    }

    if ($method === 'GET' && $path === '/api/settings') {
        $controller->getSettings();
        return;
    }

    if ($method === 'POST' && $path === '/api/media/upload') {
        $controller->uploadMedia();
        return;
    }

    if ($method === 'GET' && $path === '/api/flyers') {
        $controller->getFlyers();
        return;
    }

    if ($method === 'GET' && preg_match('#^/api/flyers/(\d+)$#', $path, $matches)) {
        $controller->getFlyer((int) $matches[1]);
        return;
    }

    if ($method === 'POST' && $path === '/api/flyers') {
        $controller->saveFlyer();
        return;
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Endpoint no encontrado'], JSON_UNESCAPED_UNICODE);
    return;
}

function ensurePublicUploadsSymlink(string $root): void
{
    $publicUploads = $root . '/public/uploads';
    $storageUploads = $root . '/storage/uploads';

    if (!is_dir($storageUploads) && !mkdir($storageUploads, 0775, true) && !is_dir($storageUploads)) {
        return;
    }

    if (is_link($publicUploads) || file_exists($publicUploads)) {
        return;
    }

    @symlink($storageUploads, $publicUploads);
}

function isValidInstallation(string $configPath): bool
{
    if (!file_exists($configPath)) {
        return false;
    }

    $config = require $configPath;
    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
        return false;
    }

    try {
        createPdo($config['db']);
    } catch (\PDOException) {
        return false;
    }

    return true;
}

function normalizePath(string $requestPath, string $basePath): string
{
    if ($basePath === '') {
        return $requestPath === '' ? '/' : $requestPath;
    }

    if ($requestPath === $basePath) {
        return '/';
    }

    if (str_starts_with($requestPath, $basePath . '/')) {
        return substr($requestPath, strlen($basePath));
    }

    return $requestPath === '' ? '/' : $requestPath;
}

function appUrl(string $basePath, string $path): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    return ($basePath === '' ? '' : $basePath) . $normalizedPath;
}

function createPdo(array $db): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    return new PDO($dsn, (string) $db['user'], (string) $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pañalería y Algo Más | Tienda Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'baby-cream': '#fffaf0',
                        'baby-blue-light': '#e0f7fa',
                        'baby-blue': '#b3e5fc',
                        'baby-pink': '#f8bbd0',
                        'baby-green': '#c8e6c9',
                        'baby-text': '#546e7a'
                    }
                }
            }
        }
    </script>
    <style>
        .product-card:hover { transform: translateY(-5px) rotate(1deg); transition: all 0.3s ease; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .active-tab { border-bottom: 4px solid #f8bbd0; color: #546e7a; font-weight: bold; }
        body { font-family: 'Comic Sans MS', 'Courier New', sans-serif; }
    </style>
</head>
<body class="bg-baby-cream text-baby-text font-sans">
<header class="sticky top-0 z-50 bg-white/90 backdrop-blur-sm shadow-sm border-b-2 border-baby-blue-light">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-baby-text flex items-center gap-2">
            <i class="fa-solid fa-baby-carriage text-baby-pink"></i>
            <span>Pañalería <span class="text-baby-blue font-light">& Más</span></span>
        </h1>
        <button onclick="toggleCart()" class="relative p-3 bg-baby-pink rounded-full text-white hover:scale-105 transition">
            <i class="fa-solid fa-cart-shopping text-xl"></i>
            <span id="cart-count" class="absolute -top-1 -right-1 bg-white text-baby-pink text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center border-2 border-baby-pink">0</span>
        </button>
    </div>

    <nav class="bg-white border-t border-baby-blue-light">
        <div class="container mx-auto flex">
            <button onclick="showTab('store')" id="tab-store" class="flex-1 py-3 text-center text-gray-500 hover:text-baby-text active-tab transition items-center justify-center gap-2 flex">
                <i class="fa-solid fa-store"></i> Tienda
            </button>
            <button onclick="showTab('admin')" id="tab-admin" class="flex-1 py-3 text-center text-gray-500 hover:text-baby-text transition items-center justify-center gap-2 flex">
                <i class="fa-solid fa-tools"></i> Admin Productos
            </button>
            <button onclick="showTab('flyers')" id="tab-flyers" class="flex-1 py-3 text-center text-gray-500 hover:text-baby-text transition items-center justify-center gap-2 flex">
                <i class="fa-solid fa-image"></i> Flyers
            </button>
        </div>
    </nav>
</header>

<?php require __DIR__ . '/../views/store.php'; ?>
<?php require __DIR__ . '/../views/admin.php'; ?>
<?php require __DIR__ . '/../views/flyers.php'; ?>

<div id="cart-overlay" onclick="toggleCart()" class="fixed inset-0 bg-black/50 z-50 hidden"></div>
<div id="cart-drawer" class="fixed inset-y-0 right-0 w-full max-w-md bg-baby-cream shadow-2xl z-[60] transform translate-x-full transition-transform duration-300 flex flex-col border-l-4 border-baby-blue">
    <div class="p-5 border-b-2 border-baby-blue-light flex justify-between items-center bg-white">
        <h3 class="font-bold text-xl flex items-center gap-2"><i class="fa-solid fa-basket-shopping text-baby-pink"></i> Tu Canasta</h3>
        <button onclick="toggleCart()" class="text-baby-text text-2xl hover:rotate-90 transition">&times;</button>
    </div>

    <div id="cart-items" class="flex-1 overflow-y-auto p-4 space-y-4 hide-scrollbar"></div>

    <div class="p-5 border-t-2 border-baby-blue-light bg-white space-y-4">
        <div class="flex justify-between text-xl font-bold">
            <span>Total:</span>
            <span id="cart-total" class="text-baby-pink">$0</span>
        </div>

        <div class="space-y-3">
            <input id="cust-name" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none" placeholder="Tu nombre completo">
            <input id="cust-address" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none" placeholder="Dirección de entrega">
            <button onclick="sendOrder()" class="w-full bg-baby-green text-baby-text py-4 rounded-full font-bold text-lg hover:bg-green-300 active:scale-95 transition flex items-center justify-center gap-3">
                <i class="fa-brands fa-whatsapp text-2xl"></i> Enviar Pedido
            </button>
        </div>
    </div>
</div>

<script>
    window.APP_BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars(appUrl($basePath, '/assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
