<?php
declare(strict_types=1); // Включаем строгую типизацию
session_start();

// Конфигурация
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/oauth_config.php'; // Выносим секреты в отдельный файл

// Проверка состояния OAuth
if (!isset($_GET['code'], $_GET['state'], $_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
    error_log('OAuth state mismatch or missing parameters');
    die("Ошибка авторизации: недействительный запрос");
}

// Загружаем конфигурацию OAuth
$client_id = GOOGLE_CLIENT_ID; // Из конфига
$client_secret = GOOGLE_CLIENT_SECRET; // Из конфига
$redirect_uri = GOOGLE_REDIRECT_URI;

// Получаем токен доступа
$token_url = "https://oauth2.googleapis.com/token";
$data = [
    "code" => $_GET['code'],
    "client_id" => $client_id,
    "client_secret" => $client_secret,
    "redirect_uri" => $redirect_uri,
    "grant_type" => "authorization_code"
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $token_url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error || $status !== 200) {
    error_log("Google token error: $error | Response: $response");
    die("Ошибка соединения с сервером авторизации");
}

$token = json_decode($response, true);
if (!isset($token['access_token'])) {
    error_log("Invalid token response: " . print_r($token, true));
    die("Ошибка авторизации: неверный ответ сервера");
}

// Получаем данные пользователя через cURL
$user_info_url = "https://www.googleapis.com/oauth2/v3/userinfo";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $user_info_url,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token['access_token']}"],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$user_response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200) {
    error_log("User info request failed: $user_response");
    die("Ошибка получения данных пользователя");
}

$user_info = json_decode($user_response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    die("Ошибка обработки данных");
}

// Валидация основных данных
if (empty($user_info['email']) || !filter_var($user_info['email'], FILTER_VALIDATE_EMAIL)) {
    die("Не удалось получить валидный email адрес");
}

// Подготовка данных
$email = $user_info['email'];
$first_name = $user_info['given_name'] ?? '';
$last_name = $user_info['family_name'] ?? '';

// Работа с базой данных
try {
    $pdo->beginTransaction();

    // Поиск пользователя
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM customers WHERE email = ? FOR UPDATE");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Создание нового пользователя
        $stmt = $pdo->prepare("INSERT INTO customers (email, first_name, last_name, registration_type) VALUES (?, ?, ?, 'google')");
        $stmt->execute([$email, $first_name, $last_name]);
        $user_id = $pdo->lastInsertId();

        $user = [
            'id' => $user_id,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name
        ];
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error: " . $e->getMessage());
    die("Ошибка базы данных");
}

// Обновляем сессию
$_SESSION = [
    'user_id' => $user['id'],
    'user_email' => $user['email'],
    'user_name' => trim("{$user['first_name']} {$user['last_name']}"),
    'oauth_provider' => 'google',
    'last_login' => time()
];

// Регенерация ID сессии для предотвращения фиксации
session_regenerate_id(true);

// Перенаправление
header("Location: /users/dashboard.php", true, 303);
exit();
?>
