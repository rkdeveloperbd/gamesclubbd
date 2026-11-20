<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'POST required.'], 405);
require_csrf();
$user = require_user();
$input = json_decode(file_get_contents('php://input') ?: '', true);
$transactionId = is_array($input) ? trim((string)($input['transactionId'] ?? '')) : '';
if ($transactionId === '' || strlen($transactionId) > 200) json_response(['ok' => false, 'error' => 'Missing transactionId.'], 422);

$pdo = db();
$stmt = $pdo->prepare("SELECT id, user_id, amount, status, reference, gateway_transaction_id FROM transactions WHERE user_id = ? AND type = 'deposit' AND gateway = 'goriber_pay' AND (gateway_transaction_id = ? OR reference = ?) LIMIT 1");
$stmt->execute([(int)$user['id'], $transactionId, $transactionId]);
$transaction = $stmt->fetch();
if (!$transaction) json_response(['ok' => false, 'error' => 'Payment record not found for this account.'], 404);
if ($transaction['status'] === 'COMPLETED') json_response(['ok' => true, 'credited' => false, 'message' => 'Payment was already credited.']);
if ($transaction['status'] !== 'PENDING') json_response(['ok' => false, 'error' => 'This payment is not pending.'], 409);

$ch = curl_init('https://pay.goriberpay.cyou/api/payment/verify');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'BRAND-KEY: ' . saved_brand_key()],
    CURLOPT_POSTFIELDS => json_encode(['transactionId' => $transactionId], JSON_UNESCAPED_SLASHES),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$error = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$verification = is_string($body) ? json_decode($body, true) : null;
if ($error || $httpCode < 200 || $httpCode >= 300 || !is_array($verification)) {
    error_log('Goriber Pay verify failed: ' . ($error ?: 'HTTP ' . $httpCode));
    json_response(['ok' => false, 'error' => 'Could not verify payment right now. Please try again.'], 502);
}

$data = $verification['data'] ?? $verification;
$status = $data['status'] ?? $verification['status'] ?? null;
if ($status === true && isset($data['payment_status'])) $status = $data['payment_status'];
if (is_string($status)) $status = strtoupper($status);
$verifiedId = (string)($data['transactionId'] ?? $data['transaction_id'] ?? $data['payment_id'] ?? $transactionId);
$verifiedAmount = $data['amount'] ?? $data['paid_amount'] ?? null;
$meta = $data['meta_data'] ?? $verification['meta_data'] ?? null;
if (is_array($meta) && isset($meta['reference']) && !hash_equals((string)$transaction['reference'], (string)$meta['reference'])) {
    json_response(['ok' => false, 'error' => 'Payment reference mismatch.'], 400);
}
if ($status !== 'COMPLETED') json_response(['ok' => false, 'error' => 'Payment has not completed.'], 409);
if ($verifiedAmount !== null && abs((float)$verifiedAmount - (float)$transaction['amount']) > 0.001) {
    json_response(['ok' => false, 'error' => 'Verified payment amount does not match the pending deposit.'], 400);
}

try {
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id, user_id, amount, status FROM transactions WHERE id = ? FOR UPDATE');
    $lock->execute([(int)$transaction['id']]);
    $locked = $lock->fetch();
    if (!$locked || (int)$locked['user_id'] !== (int)$user['id']) throw new RuntimeException('Payment ownership check failed.');
    if ($locked['status'] === 'COMPLETED') {
        $pdo->commit();
        json_response(['ok' => true, 'credited' => false, 'message' => 'Payment was already credited.']);
    }
    if ($locked['status'] !== 'PENDING') throw new RuntimeException('Payment is no longer pending.');
    $credit = $pdo->prepare('UPDATE users SET deposit_balance = deposit_balance + ? WHERE id = ?');
    $credit->execute([(float)$locked['amount'], (int)$locked['user_id']]);
    $finish = $pdo->prepare("UPDATE transactions SET status = 'COMPLETED', gateway_transaction_id = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'PENDING'");
    $finish->execute([$verifiedId, (int)$locked['id']]);
    if ($finish->rowCount() !== 1) throw new RuntimeException('Could not mark payment complete.');
    $pdo->commit();
    json_response(['ok' => true, 'credited' => true, 'message' => 'Payment verified and wallet credited.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log((string)$e);
    json_response(['ok' => false, 'error' => 'Unable to credit the payment. Please contact support.'], 500);
}
