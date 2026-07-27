<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['DEEPSEEK_API_KEY'] ?? getenv('DEEPSEEK_API_KEY');

// Если это GET-запрос (просто открыли в браузере)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/plain');
    echo "Навык DeepSeek работает! Отправляйте POST-запросы с данными от Алисы.";
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Возвращаем корректный ответ для Алисы
    $output = [
        'response' => [
            'text' => 'Извините, я не понял запрос. Пожалуйста, попробуйте ещё раз.',
            'end_session' => false
        ],
        'version' => '1.0'
    ];
    header('Content-Type: application/json');
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
}

$message = $data['request']['command'] ?? '';

// Если команда пустая (например, первый запуск)
if (empty($message)) {
    $output = [
        'response' => [
            'text' => 'Здравствуйте! Я ваш юридический помощник с чувством юмора. Чем могу помочь?',
            'end_session' => false
        ],
        'version' => '1.0'
    ];
    header('Content-Type: application/json');
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
}

// Отправка запроса к DeepSeek через AITUNNEL
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
    $output = [
        'response' => [
            'text' => 'Извините, сейчас я не могу ответить. Попробуйте позже.',
            'end_session' => false
        ],
        'version' => '1.0'
    ];
    header('Content-Type: application/json');
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
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