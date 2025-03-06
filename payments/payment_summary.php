<?php
// payment_summary.php

require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;
use Stripe\Stripe;

session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

error_log('====== Начало работы payment_summary.php ======');

// Логируем идентификатор сессии и данные сессии на старте
error_log('Сгенерирован новый идентификатор сессии: ' . session_id());
error_log('Данные сессии на старте: ' . print_r($_SESSION, true));

// Инициализируем загрузку переменных окружения
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
error_log('Загружены переменные окружения из .env');

// Устанавливаем заголовок для JSON-ответа
header('Content-Type: application/json');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Неподдерживаемый метод запроса');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
    exit();
}

// Логирование заголовков запроса для проверки
$headers = getallheaders();
error_log('Заголовки запроса: ' . print_r($headers, true));

// Получаем входящие данные от клиента и проверяем корректность JSON
$rawInput = file_get_contents('php://input');
error_log('Полученные сырые данные JSON: ' . $rawInput);

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Ошибка разбора JSON: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit();
}
error_log('Входящие данные от клиента: ' . print_r($input, true));

// Проверяем, установлен ли секретный ключ Stripe
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
if (!$stripeSecretKey) {
    error_log("Ошибка: Stripe Secret Key не установлен.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Stripe Secret Key is not set']);
    exit();
}
error_log('Секретный ключ Stripe успешно загружен');

// Обработка исключений
try {
    Stripe::setApiKey($stripeSecretKey);
    error_log('API ключ Stripe успешно установлен');

    // Проверяем данные о сумме
    if (!isset($input['total_price']) || !is_numeric($input['total_price']) || $input['total_price'] <= 0) {
        throw new Exception('Неверная или отсутствующая общая сумма.');
    }
    $totalPrice = floatval($input['total_price']) * 100; // Переводим в грош (pln -> grosze)
    error_log('Общая сумма заказа: ' . $totalPrice . ' grosze');

    // Проверяем наличие пакетов
    if (empty($input['packages']) || !is_array($input['packages'])) {
        throw new Exception('Отсутствуют данные о пакетах или они не определены.');
    }
    error_log('Количество пакетов: ' . count($input['packages']));

    // Проверка наличия order_id
    $orderId = $input['order_id'] ?? null;
    if (!$orderId) {
        throw new Exception('order_id отсутствует в входящих данных');
    }
    error_log('Используемый order_id для метаданных Stripe: ' . $orderId);

    // Извлекаем информацию о скидке и общей сумме без учета скидки (только для информации)
    $discount = floatval($input['discount'] ?? 0);
    $totalWithoutDiscount = floatval($input['total_without_discount'] ?? 0);

    // Вычисляем коэффициент скидки (только для отображения)
    $discountFactor = 1;
    if ($totalWithoutDiscount > 0 && $discount > 0) {
        $discountFactor = ($totalWithoutDiscount - $discount) / $totalWithoutDiscount;
    }
    error_log('Коэффициент скидки: ' . $discountFactor);

    $lineItems = [];
    foreach ($input['packages'] as $package) {
        // Внесенные проверки для каждого пакета
        if (!isset($package['calories']) || !is_string($package['calories'])) {
            throw new Exception('Неверные данные: calories отсутствует или не является строкой.');
        }
        if (!isset($package['quantity']) || !is_numeric($package['quantity']) || $package['quantity'] <= 0) {
            throw new Exception('Неверные данные: quantity отсутствует, не является числом или меньше либо равно 0.');
        }
        if (!isset($package['price_per_package']) || !is_numeric($package['price_per_package']) || $package['price_per_package'] <= 0) {
            throw new Exception('Неверные данные: price_per_package отсутствует, не является числом или меньше либо равно 0.');
        }

        // Применяем скидку к цене за пакет (для отображения)
        $discountedPrice = floatval($package['price_per_package']) * $discountFactor;

        // Сбор данных о датах доставки
        $deliveryDates = '';
        if (isset($package['dates']) && is_array($package['dates'])) {
            $deliveryDates = implode(', ', array_map('htmlspecialchars', $package['dates']));
        }

        // Фильтруем данные каждого пакета
        $filteredPackage = [
            'calories' => htmlspecialchars($package['calories'], ENT_QUOTES, 'UTF-8'),
            'price' => number_format($discountedPrice, 2, '.', ''),
            'quantity' => intval($package['quantity']),
            'dates' => $deliveryDates
        ];

        $lineItems[] = [
            'price_data' => [
                'currency' => 'pln',
                'product_data' => [
                    'name' => 'Paczka: ' . $filteredPackage['calories'],
                    'description' => 'Kalorie: ' . $filteredPackage['calories'] . '. Ilość: ' . $filteredPackage['quantity'] . '. Terminy dostaw: ' . $filteredPackage['dates'],
                    'images' => ['https://foodcasecatering.net/assets/img/logo.png'],
                ],
                'unit_amount' => intval($filteredPackage['price'] * 100),
            ],
            'quantity' => $filteredPackage['quantity'],
        ];
    }

    error_log('Собранные line items для Stripe: ' . print_r($lineItems, true));

    // Создаем сессию Stripe Checkout
    error_log('Пытаемся создать Stripe Checkout Session...');
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card', 'blik'],
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => 'https://foodcasecatering.net/payments/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://foodcasecatering.net/payments/cancel.php',
        'client_reference_id' => session_id(),
        'customer_email' => $input['email'],
        'metadata' => [
            'order_id' => strval($orderId)
        ],
    ]);

    error_log('Stripe Checkout Session успешно создан. ID: ' . $session->id);

    echo json_encode(['id' => $session->id]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Ошибка при создании Stripe Checkout Session: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Stripe API error: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    error_log('Общая ошибка: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}

error_log('====== Конец работы payment_summary.php ======');
?>
