<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'POST required.'], 405);
require_csrf();
$user = require_user();
$input = json_decode(file_get_contents('php://input') ?: '', true);
$amount = is_array($input) ? filter_var($input['amount'] ?? null, FILTER_VALIDATE_FLOAT) : false;
if ($amount === false || $amount <= 0 || $amount > 1000000) json_response(['ok' => false, 'error' => 'Enter a valid amount.'], 422);
$amount = round((float)$amount, 2);
$formattedAmount = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
$reference = bin2hex(random_bytes(20));
$pdo = db();

try {
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, gateway, created_at) VALUES (?, 'deposit', ?, 'PENDING', ?, 'goriber_pay', NOW())");
    $stmt->execute([(int)$user['id'], $amount, $reference]);

    $payload = [
        'cus_name' => (string)$user['name'],
        'cus_email' => (string)$user['email'],
        'amount' => $formattedAmount,
        'success_url' => app_url('add-money.php'),
        'cancel_url' => app_url('add-money.php?payment=cancelled'),
        'meta_data' => ['reference' => $reference, 'user_id' => (int)$user['id']],
    ];
    $ch = curl_init('https://pay.goriberpay.cyou/api/payment/create');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'BRAND-KEY: ' . saved_brand_key()],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);
    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $response = is_string($responseBody) ? json_decode($responseBody, true) : null;
    if ($curlError || $httpCode < 200 || $httpCode >= 300 || !is_array($response)) {
        throw new RuntimeException('Goriber Pay payment creation failed: ' . ($curlError ?: 'HTTP ' . $httpCode));
    }
    $data = $response['data'] ?? $response;
    $paymentUrl = $data['payment_url'] ?? $response['payment_url'] ?? null;
    if (($response['status'] ?? false) !== true || !is_string($paymentUrl) || !filter_var($paymentUrl, FILTER_VALIDATE_URL) || parse_url($paymentUrl, PHP_URL_SCHEME) !== 'https') {
        throw new RuntimeException('Payment provider did not return a valid payment URL.');
    }
    $gatewayId = $data['transactionId'] ?? $data['transaction_id'] ?? $data['payment_id'] ?? $response['transactionId'] ?? $response['transaction_id'] ?? null;
    if (is_scalar($gatewayId) && (string)$gatewayId !== '') {
        $update = $pdo->prepare('UPDATE transactions SET gateway_transaction_id = ? WHERE reference = ?');
        $update->execute([(string)$gatewayId, $reference]);
    }
    json_response(['ok' => true, 'payment_url' => $paymentUrl]);
} catch (Throwable $e) {
    error_log((string)$e);
    $pdo->prepare("UPDATE transactions SET status = 'FAILED' WHERE reference = ? AND status = 'PENDING'")->execute([$reference]);
    json_response(['ok' => false, 'error' => 'Could not start payment. Please try again or contact support.'], 502);
}
