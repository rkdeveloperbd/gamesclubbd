<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $config = array_merge($config, require $localConfig);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
    session_start();
}

function db(): PDO
{
    static $pdo;
    global $config;
    if (!$pdo) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        json_response(['ok' => false, 'error' => 'Invalid security token. Refresh and try again.'], 419);
    }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, name, email, deposit_balance, winning_balance FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) json_response(['ok' => false, 'error' => 'Please log in first.'], 401);
    return $user;
}

function require_admin(): array
{
    if (empty($_SESSION['admin_id'])) json_response(['ok' => false, 'error' => 'Administrator login required.'], 401);
    $stmt = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin) json_response(['ok' => false, 'error' => 'Administrator login required.'], 401);
    return $admin;
}

function app_url(string $path = ''): string
{
    global $config;
    $base = $config['app_url'];
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        // API scripts run from /api, while app_url should always point to the project root.
        if (str_ends_with($scriptDir, '/api')) $scriptDir = dirname($scriptDir);
        if ($scriptDir !== '/' && $scriptDir !== '.') $base .= rtrim($scriptDir, '/');
    }
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function saved_brand_key(): string
{
    $fallback = 'OzMPezoTynnfFsgjAwPiq6iCYX4vFwNVBp024f62QvZPvmuBFz';
    try {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'goriber_brand_key' LIMIT 1");
        $stmt->execute();
        $key = $stmt->fetchColumn();
        return is_string($key) && trim($key) !== '' ? trim($key) : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}
