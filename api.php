<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

try {
    $pdo = db_connect($config);
    initialize_schema($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a la base de datos', 'details' => $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function require_admin(): void {
    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
}

if ($action === 'bootstrap') {
    $products = $pdo->query("SELECT * FROM products WHERE status <> 'archivado' ORDER BY id DESC")->fetchAll();
    $orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();
    $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    foreach ($orders as &$order) {
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll();
    }
    echo json_encode([
        'appName' => $config['app_name'],
        'whatsapp' => $config['whatsapp_number'],
        'currency' => $config['currency'],
        'isAdmin' => !empty($_SESSION['admin_id']),
        'products' => $products,
        'orders' => $orders,
    ]);
    exit;
}

if ($action === 'login') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales inválidas']);
        exit;
    }
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    echo json_encode(['ok' => true, 'name' => $admin['name']]);
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'save_product') {
    require_admin();
    $id = (int)($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $price = (float)($input['price'] ?? 0);
    $image = trim($input['image_url'] ?? '');
    $description = trim($input['description'] ?? '');
    if (!$name || $price <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE products SET name=?, price=?, image_url=?, description=? WHERE id=?');
        $stmt->execute([$name, $price, $image, $description, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO products(name,price,image_url,description,status) VALUES(?,?,?,?, 'publicado')");
        $stmt->execute([$name, $price, $image, $description]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'product_status') {
    require_admin();
    $id = (int)($input['id'] ?? 0);
    $status = $input['status'] ?? 'borrador';
    $allowed = ['publicado','borrador','archivado'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Estado inválido']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE products SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'create_order') {
    $customer = trim($input['customer_name'] ?? '');
    $phone = trim($input['customer_phone'] ?? '');
    $address = trim($input['customer_address'] ?? '');
    $items = $input['items'] ?? [];
    if (!$customer || !$phone || empty($items)) {
        http_response_code(422);
        echo json_encode(['error' => 'Completa cliente, teléfono y carrito']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $total = 0;
        $detail = [];
        $stmtProduct = $pdo->prepare('SELECT id,name,price FROM products WHERE id = ?');
        foreach ($items as $item) {
            $pid = (int)($item['id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $stmtProduct->execute([$pid]);
            $p = $stmtProduct->fetch();
            if (!$p) continue;
            $line = $p['price'] * $qty;
            $total += $line;
            $detail[] = ['product_id' => $p['id'], 'name' => $p['name'], 'qty' => $qty, 'price' => $p['price']];
        }
        if (empty($detail)) {
            throw new RuntimeException('Sin productos válidos');
        }

        $lines = ["Hola, soy {$customer}.", 'Quiero hacer este pedido:'];
        foreach ($detail as $d) {
            $lines[] = "- {$d['name']} x{$d['qty']}";
        }
        $lines[] = "Dirección: {$address}";
        $lines[] = 'Total: ' . $config['currency'] . number_format($total, 2);
        $message = implode("\n", $lines);

        $stmtOrder = $pdo->prepare('INSERT INTO orders(customer_name,customer_phone,customer_address,total,status,whatsapp_message) VALUES(?,?,?,?,"nuevo",?)');
        $stmtOrder->execute([$customer, $phone, $address, $total, $message]);
        $orderId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,qty,unit_price) VALUES(?,?,?,?,?)');
        foreach ($detail as $d) {
            $stmtItem->execute([$orderId, $d['product_id'], $d['name'], $d['qty'], $d['price']]);
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'orderId' => $orderId, 'whatsappUrl' => 'https://wa.me/' . $config['whatsapp_number'] . '?text=' . urlencode($message)]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'order_status') {
    require_admin();
    $id = (int)($input['id'] ?? 0);
    $status = $input['status'] ?? 'nuevo';
    $allowed = ['nuevo','preparacion','viaje','entregado'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Estado inválido']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Acción no encontrada']);
