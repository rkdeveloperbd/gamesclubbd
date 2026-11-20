<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        header('Location: index.php'); exit;
    }
    $error = 'Username or password is incorrect.';
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login - Games Club BD</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{min-height:100vh;background:#f3f4f6;display:grid;place-items:center;font-family:Inter,Arial,sans-serif}.card{width:min(420px,92vw);border:0;border-radius:16px;box-shadow:0 12px 35px #17255418}.btn-primary{background:#1976d2;border:0}</style></head>
<body><main class="card p-4 p-md-5"><h1 class="h3 fw-bold mb-1">Games Club BD</h1><p class="text-secondary mb-4">Administrator sign in</p><?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label class="form-label" for="username">Username</label><input class="form-control mb-3" id="username" name="username" required autocomplete="username"><label class="form-label" for="password">Password</label><input class="form-control mb-4" id="password" type="password" name="password" required autocomplete="current-password"><button class="btn btn-primary w-100 py-2">Login</button></form></main></body></html>
