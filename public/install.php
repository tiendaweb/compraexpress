<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
$migrationPath = $root . '/database/migrations/001_initial_schema.sql';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (isValidInstallation($configPath)) {
    header('Location: ' . appUrl($basePath, '/'));
    exit;
}

$old = [];
$installError = null;
$installAction = appUrl($basePath, '/install.php');

if ($method === 'POST') {
    handleInstall($configPath, $migrationPath, $basePath);
}

require $root . '/views/installer.php';

function handleInstall(string $configPath, string $migrationPath, string $basePath): void
{
    global $root, $old, $installError;

    $old = [
        'db_host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
        'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
        'db_name' => trim((string) ($_POST['db_name'] ?? 'compraexpress')),
        'db_user' => trim((string) ($_POST['db_user'] ?? 'root')),
        'db_pass' => (string) ($_POST['db_pass'] ?? ''),
        'app_name' => trim((string) ($_POST['app_name'] ?? 'CompraExpress')),
        'admin_name' => trim((string) ($_POST['admin_name'] ?? '')),
        'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
    ];

    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($old['admin_name'] === '' || !filter_var($old['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 8) {
        $installError = 'Completa los datos del administrador (email válido y contraseña de al menos 8 caracteres).';
        return;
    }

    $dbConfig = [
        'host' => $old['db_host'],
        'port' => (int) $old['db_port'],
        'name' => $old['db_name'],
        'user' => $old['db_user'],
        'pass' => $old['db_pass'],
        'charset' => 'utf8mb4',
    ];

    try {
        $pdo = createPdo($dbConfig);
        $pdo->beginTransaction();

        $sql = file_get_contents($migrationPath);
        if ($sql === false) {
            throw new RuntimeException('No fue posible leer el archivo de migraciones.');
        }

        $pdo->exec($sql);

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = VALUES(role)');
        $stmt->execute([
            ':name' => $old['admin_name'],
            ':email' => $old['admin_email'],
            ':password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
            ':role' => 'admin',
        ]);

        $settingStmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $settingStmt->execute([
            ':key' => 'app_name',
            ':value' => $old['app_name'],
        ]);

        $pdo->commit();

        writeConfigFile($configPath, $dbConfig, $old['app_name']);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $installError = 'No se pudo completar la instalación: ' . $e->getMessage();
        return;
    }

    header('Location: ' . appUrl($basePath, '/'));
    exit;
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
    } catch (PDOException) {
        return false;
    }

    return true;
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

function writeConfigFile(string $configPath, array $db, string $appName): void
{
    $content = "<?php\n\n";
    $content .= "declare(strict_types=1);\n\n";
    $content .= "return " . var_export([
        'app_name' => $appName,
        'db' => $db,
    ], true) . ";\n";

    $result = file_put_contents($configPath, $content, LOCK_EX);
    if ($result === false) {
        throw new RuntimeException('No se pudo escribir config/config.php.');
    }
}

function appUrl(string $basePath, string $path): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    return ($basePath === '' ? '' : $basePath) . $normalizedPath;
}
