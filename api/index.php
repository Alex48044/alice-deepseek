<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['DEEPSEEK_API_KEY'] ?? getenv('DEEPSEEK_API_KEY');

// GET-запрос (для проверки в браузере)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/plain');
    echo "Навык работает! Отправляйте POST-запросы от Алисы.";
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Если данные не пришли — корректный ответ
if (!$data) {
    $output = [
        'response' => [
            'text' => 'Извините, я не понял запрос. Попробуйте ещё раз.',
            'end_session' => false
        ],
        'version' => '1.0'
    ];
    header('Content-Type: application/json');
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    exit;
}

$message = $data['request']['command'] ?? '';

// Приветствие при первом запуске или пустом сообщении
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

// Запрос к AITUNNEL с таймаутом 3 секунды
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
curl_setopt($ch, CURLOPT_TIMEOUT, 3); // <-- Таймаут 3 секунды

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// curl_close() удалена — не нужна, PHP сам закроет

// Проверяем, был ли таймаут или ошибка
if ($response === false || $httpCode !== 200) {
    $output = [
        'response' => [
            'text' => 'Извините, я немного задумался. Повторите вопрос позже.',
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