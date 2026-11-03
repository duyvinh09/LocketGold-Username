<?php
/**
 * Proxy nội bộ — chỉ dùng server-to-server, không lộ API ra client.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$clientKey = $_POST['key'] ?? '';
$secret = defined('API_SECRET') ? API_SECRET : '';
if ($clientKey === '' || !hash_equals($secret, $clientKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$data = $_POST;
unset($data['key']);
$data['server_key'] = defined('API_SECRET') ? API_SECRET : '';

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$gatewayUrl = rtrim($base, '/') . dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/gateway.php';
$gatewayUrl = preg_replace('#/+#', '/', str_replace('\\', '/', $gatewayUrl));

$ch = curl_init($gatewayUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'message' => curl_error($ch)]);
    exit;
}
curl_close($ch);

echo $response;
