<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); unset($_SESSION['admin_id']); session_regenerate_id(true); header('Location: login.php'); exit; }
?><!doctype html><html lang="en"><body><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"></form><script>document.forms[0].submit()</script></body></html>
