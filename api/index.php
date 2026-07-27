<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['DEEPSEEK_API_KEY'] ?? getenv('DEEPSEEK_API_KEY');

if (!$apiKey) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API key not configured']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$message = $data['request']['command'] ?? '';

if (empty($message)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Empty request']);
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.aitunnel.ru/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'deepseek-chat',
    'messages' => [
        ['role' => 'user', 'content' => $message]
    ]
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DeepSeek API error', 'code' => $httpCode]);
    exit;
}

$result = json_decode($response, true);
$answer = $result['choices'][0]['message']['content'] ?? 'Извините, не удалось получить ответ.';

$output = [
    'response' => [
        'text' => $answer,
        'end_session' => false
    ],
    'version' => '1.0'
];

header('Content-Type: application/json');
echo json_encode($output, JSON_UNESCAPED_UNICODE);
