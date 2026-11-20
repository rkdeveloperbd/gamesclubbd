<?php
// Create a first administrator from the command line: php create_admin.php username
// Then enter a password when prompted. This script refuses to run over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/api/config.php';
$username = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) { fwrite(STDERR, "Usage: php create_admin.php <username>\n"); exit(2); }
$existing = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($existing > 0) { fwrite(STDERR, "An administrator already exists. Use the existing account.\n"); exit(1); }
fwrite(STDOUT, "Password (minimum 12 characters): ");
if (PHP_OS_FAMILY !== 'Windows') shell_exec('stty -echo');
$password = trim((string)fgets(STDIN));
if (PHP_OS_FAMILY !== 'Windows') shell_exec('stty echo');
fwrite(STDOUT, "\n");
if (strlen($password) < 12) { fwrite(STDERR, "Password must be at least 12 characters.\n"); exit(2); }
$stmt = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrator created. Sign in at /admin/login.php\n");
