<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'POST required.'], 405);
require_csrf();
require_admin();
$input = json_decode(file_get_contents('php://input') ?: '', true);
$key = is_array($input) ? trim((string)($input['brand_key'] ?? '')) : '';
if ($key === '' || strlen($key) > 500) json_response(['ok' => false, 'error' => 'Enter a valid Brand Key.'], 422);
$stmt = db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('goriber_brand_key', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
$stmt->execute([$key]);
json_response(['ok' => true, 'message' => 'Goriber Pay Brand Key saved.']);
