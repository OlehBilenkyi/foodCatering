<?php
// File: payment_summary_do_wyboru.php
declare(strict_types=1);

use Dotenv\Dotenv;
use \Stripe\Stripe;
use \Stripe\Checkout\Session;
use \Stripe\Exception\ApiErrorException;

// Настройки и автозагрузка
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 14400,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'],
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
    error_log("Сессия запущена в payment_summary_do_wyboru.php. ID: " . session_id());
}

// Подключаем Composer autoload и Dotenv
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Подключаемся к БД (если нужно)
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');
if (!$pdo) {
    error_log("[ERROR] Ошибка подключения к базе данных.");
    sendJsonAndExit(['status' => 'error', 'message' => 'Не удалось подключиться к базе данных.'], 500);
}

/**
 * Вспомогательная функция для JSON-ответа
 */
function sendJsonAndExit(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Получаем и декодируем JSON
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    error_log("Получены сырые данные JSON: " . $rawInput);
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Ошибка разбора JSON: " . json_last_error_msg());
        sendJsonAndExit(['status' => 'error', 'message' => 'Invalid JSON input'], 400);
    }
} else {
    // Если нужно, поддерживаем обычный POST
    $input = $_POST;
    error_log("Получены данные (POST): " . print_r($input, true));
}

if (empty($input)) {
    error_log("Ошибка: Входящие данные отсутствуют");
    sendJsonAndExit(['status' => 'error', 'message' => 'Empty form input'], 400);
}

// Проверяем CSRF-токен
$sessionToken = $_SESSION['csrf_token'] ?? null;
if (!$sessionToken) {
    error_log("[ERROR] CSRF-токен отсутствует в сессии!");
    sendJsonAndExit(['status' => 'error', 'message' => 'Ошибка безопасности: CSRF-токен отсутствует.'], 403);
}
$csrfTokenInput = $input['csrf_token'] ?? null;
if (empty($csrfTokenInput) || !hash_equals($sessionToken, (string)$csrfTokenInput)) {
    error_log("Ошибка CSRF: Ожидалось: $sessionToken, получено: $csrfTokenInput");
    sendJsonAndExit(['status' => 'error', 'message' => 'Ошибка безопасности: Неверный CSRF-токен.'], 403);
}
error_log("CSRF-токен успешно проверен.");

// ======================== Валидация полей для создания Stripe-сессии ========================
// Предположим, нужно как минимум: order_id, total_price, email
$requiredFields = ['order_id', 'total_price', 'email'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        error_log("Ошибка: Отсутствует обязательное поле: $field");
        sendJsonAndExit(['status' => 'error', 'message' => "Отсутствует поле: $field"], 400);
    }
}

$orderId    = $input['order_id'];
$totalPrice = floatval($input['total_price']);
$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
if (!$email) {
    error_log("Ошибка: Неверный формат email: " . $input['email']);
    sendJsonAndExit(['status' => 'error', 'message' => 'Неверный формат email'], 400);
}


if (!$email) {
    error_log("Ошибка: Неверный формат email: " . $input['email']);
    sendJsonAndExit(['status' => 'error', 'message' => 'Неверный формат email'], 400);
}
if ($totalPrice <= 0) {
    error_log("Ошибка: Неверная сумма заказа: $totalPrice");
    sendJsonAndExit(['status' => 'error', 'message' => 'Неверная сумма заказа'], 400);
}

// Подключаем Stripe
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
if (!$stripeSecretKey) {
    error_log("Ошибка: Stripe Secret Key не установлен.");
    sendJsonAndExit(['status' => 'error', 'message' => 'Stripe Secret Key is not set'], 500);
}
Stripe::setApiKey($stripeSecretKey);
$fullname = $input['fullname'] ?? 'N/A'; 
$orderDays = $input['order_days'] ?? [];

// Формируем line_items (по умолчанию один элемент)
$lineItems = [[
    'price_data' => [
        'currency'     => 'pln',
        'product_data' => [
            'name'        => "Заказ №$orderId",
            'description' => "Całkowita suma zamówienia: $totalPrice PLN",
        ],
        'unit_amount'  => intval($totalPrice * 100),
    ],
    'quantity' => 1
]];

// Объявляем fullname и orderDays с безопасными значениями по умолчанию:
$fullname  = $input['fullname'] ?? 'N/A';
$orderDays = $input['order_days'] ?? [];

// 2) Формируем краткую сводку по дням (например: "Data: 2025-02-25 (120.00 zł), Data: 2025-02-26 (95.00 zł)")
$daysSummary = [];
foreach ($orderDays as $day) {
    $date = $day['delivery_date']   ?? '?';
    $sum  = $day['day_total_price'] ?? '?';
    $daysSummary[] = "Data: $date ($sum zł)";
}
$daysString = implode(", ", $daysSummary);

// 3) Склеиваем общую строку для описания, чтобы Stripe показал её на экране
$description = "Całkowita suma zamówienia: $totalPrice PLN\n$daysString";

// Stripe иногда обрезает слишком длинное описание, но на всякий случай 
// можно ограничить, скажем, до 200-300 символов, если боитесь обрезки:
if (strlen($description) > 300) {
    $description = substr($description, 0, 297) . '...';
}

// 4) Формируем line_items (по умолчанию один элемент):
$lineItems = [[
    'price_data' => [
        'currency'     => 'pln',
        'product_data' => [
            'name'        => "Заказ №$orderId",
            'description' => $description, // <-- Здесь указываем расширенное описание
            'images'      => ['https://foodcasecatering.net/assets/img/logo.png'],
        ],
        'unit_amount'  => intval($totalPrice * 100),
    ],
    'quantity' => 1
]];


// 5) Создаем Stripe-сессию:
try {
    $session = Session::create([
    'payment_method_types' => ['card', 'blik'],
    'line_items'           => $lineItems,
    'mode'                 => 'payment',
    'success_url'          => 'https://foodcasecatering.net/payments/success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'           => 'https://foodcasecatering.net/payments/cancel.php',
    'client_reference_id'  => session_id(),
    'customer_email'       => $email, // <=== именно этот параметр!
    'metadata'             => [
         'order_id'   => (string)$orderId,
         'full_name'  => $fullname,
         'days_info'  => $daysString, 
    ],
]);


    error_log('Stripe Checkout Session успешно создан. ID: ' . $session->id);
    sendJsonAndExit(['status' => 'success', 'id' => $session->id]);
} catch (ApiErrorException $e) {
    error_log('Ошибка при создании Stripe Session: ' . $e->getMessage());
    sendJsonAndExit(['status' => 'error', 'message' => 'Stripe API error: ' . $e->getMessage()], 500);
} catch (\Exception $e) {
    error_log('Общая ошибка: ' . $e->getMessage());
    sendJsonAndExit(['status' => 'error', 'message' => $e->getMessage()], 500);
}