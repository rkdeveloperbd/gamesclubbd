<?php
declare(strict_types=1);
require_once __DIR__ . '/api/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
require_csrf();
unset($_SESSION['user_id']);
session_regenerate_id(true);
header('Location: index.php');
exit;
