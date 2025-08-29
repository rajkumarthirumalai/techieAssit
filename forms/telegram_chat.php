<?php
// Simple Telegram forwarder for chat widget submissions
// Configure your bot token and destination chat ID below

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// TODO: Set these via environment variables in production
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$chatId = getenv('TELEGRAM_CHAT_ID') ?: '';

if (!$botToken || !$chatId) {
  // Fallback: allow defining directly in file if env not set
  // $botToken = '123456789:AA...';
  // $chatId = '123456789';
}

function json_response($statusCode, $data) {
  http_response_code($statusCode);
  echo json_encode($data);
  exit;
}

// Read input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
  $input = $_POST; // Support form-encoded fallback
}

$name = isset($input['name']) ? trim($input['name']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$message = isset($input['message']) ? trim($input['message']) : '';
$page = isset($input['page']) ? trim($input['page']) : '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($message === '') {
  json_response(400, [ 'ok' => false, 'error' => 'Message is required' ]);
}

// Build Telegram message
$lines = [];
$lines[] = "\u2709\uFE0F New Website Chat Message";
if ($name) $lines[] = "\n\uD83D\uDC64 Name: " . $name;
if ($phone) $lines[] = "\n\u260E\uFE0F Phone: " . $phone;
if ($email) $lines[] = "\n\uD83D\uDCE7 Email: " . $email;
if ($page) $lines[] = "\n\uD83D\uDCC4 Page: " . $page;
$lines[] = "\n\uD83D\uDCDD Message:\n" . $message;
$lines[] = "\n\n\uD83D\uDCE6 IP: $ip";

$text = implode('', $lines);

if (!$botToken || !$chatId) {
  // If not configured, respond with info so developer can set env
  json_response(500, [
    'ok' => false,
    'error' => 'Telegram not configured',
    'hint' => 'Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID env variables on the server.',
  ]);
}

$url = "https://api.telegram.org/bot{$botToken}/sendMessage";

$payload = [
  'chat_id' => $chatId,
  'text' => $text,
  'parse_mode' => 'HTML'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) {
  json_response(502, [ 'ok' => false, 'error' => 'Failed to reach Telegram' ]);
}

$decoded = json_decode($resp, true);
if ($status >= 200 && $status < 300 && isset($decoded['ok']) && $decoded['ok'] === true) {
  json_response(200, [ 'ok' => true ]);
}

json_response(500, [ 'ok' => false, 'error' => 'Telegram API error', 'details' => $decoded ]);
?>


