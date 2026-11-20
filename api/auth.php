<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'POST required.'], 405);
require_csrf();

$input = json_decode(file_get_contents('php://input') ?: '', true);
$action = is_array($input) ? ($input['action'] ?? '') : ($_POST['action'] ?? '');
try {
    if ($action === 'register') {
        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            json_response(['ok' => false, 'error' => 'Enter your name, a valid email and a password of at least 8 characters.'], 422);
        }
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        try { $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]); }
        catch (PDOException $e) { if ((string)$e->getCode() === '23000') json_response(['ok' => false, 'error' => 'An account with this email already exists.'], 409); throw $e; }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)db()->lastInsertId();
        json_response(['ok' => true]);
    }
    if ($action === 'login') {
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) json_response(['ok' => false, 'error' => 'Email or password is incorrect.'], 401);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        json_response(['ok' => true]);
    }
    json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log((string)$e);
    json_response(['ok' => false, 'error' => 'Unable to process the request. Check the PHP error log.'], 500);
}
