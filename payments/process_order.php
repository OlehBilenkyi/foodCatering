<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');

// Проверяем, запущена ли сессия
if (session_status() === PHP_SESSION_NONE) {
    // Настройки параметров куки для сессии перед запуском сессии
    session_set_cookie_params([
        'lifetime' => 14400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'None'
    ]);

    session_start();
    session_regenerate_id(true);
}

// Включаем логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Проверка на наличие данных POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Ошибка: Неподдерживаемый метод запроса.');
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Получаем данные из POST-запроса (JSON)
$rawInput = file_get_contents('php://input');
error_log("Полученные сырые данные JSON: " . $rawInput);

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Ошибка разбора JSON: ' . json_last_error_msg());
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit();
}
error_log("Сырые входящие данные: " . print_r($input, true));

if (empty($input)) {
    error_log('Ошибка: Входящие данные отсутствуют');
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Empty form input']);
    exit();
}

// Проверка наличия CSRF-токена
$csrfToken = $input['csrf_token'] ?? null;
if (!$csrfToken || !isset($_SESSION['csrf_token'])) {
    error_log('Ошибка CSRF: отсутствует токен в сессии или в форме.');
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'CSRF token missing']);
    exit();
}

// Проверяем соответствие CSRF-токенов
if ($csrfToken !== $_SESSION['csrf_token']) {
    error_log('Ошибка CSRF: Несоответствие токенов.');
    error_log('Ожидалось: ' . $_SESSION['csrf_token'] . ', Получено: ' . $csrfToken);
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'CSRF token mismatch']);
    exit();
}
error_log('CSRF-токен успешно проверен');

// Проверка корректности числовых данных
if (!isset($input['total_price']) || !is_numeric($input['total_price']) || floatval($input['total_price']) <= 0) {
    error_log('Ошибка: Неверная или отсутствующая общая сумма.');
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid total price']);
    exit();
}
$totalPrice = number_format(floatval($input['total_price']), 2, '.', '');

// Подключение к базе данных
try {
    $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=" . $_ENV['DB_CHARSET'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    error_log("Успешное подключение к базе данных для пользователя " . $_ENV['DB_USER']);
} catch (PDOException $e) {
    error_log("Ошибка подключения к базе данных: " . $e->getMessage());
    http_response_code(500);
    exit("Произошла ошибка при подключении к базе данных.");
}

// Запись данных в базу данных (без total_without_discount и discount)
try {
    $stmt = $pdo->prepare("
        INSERT INTO orders (customer_email, total_price, status, created_at, customer_phone, customer_street, customer_house_number, customer_apartment, customer_floor, customer_gate_code, customer_notes, customer_fullname, customer_klatka)
        VALUES (:customer_email, :total_price, 'pending', NOW(), :customer_phone, :customer_street, :customer_house_number, :customer_apartment, :customer_floor, :customer_gate_code, :customer_notes, :customer_fullname, :customer_klatka)
    ");

    $stmt->execute([
        ':customer_email' => htmlspecialchars($input['email'] ?? ''),
        ':total_price' => $totalPrice,
        ':customer_phone' => htmlspecialchars($input['phone'] ?? ''),
        ':customer_street' => htmlspecialchars($input['street'] ?? ''),
        ':customer_house_number' => htmlspecialchars($input['house_number'] ?? ''),
        ':customer_apartment' => htmlspecialchars($input['apartment'] ?? ''),
        ':customer_floor' => htmlspecialchars($input['floor'] ?? ''),
        ':customer_gate_code' => htmlspecialchars($input['gate_code'] ?? ''),
        ':customer_notes' => htmlspecialchars($input['notes'] ?? ''),
        ':customer_fullname' => htmlspecialchars($input['fullname'] ?? ''),
        ':customer_klatka' => htmlspecialchars($input['klatka'] ?? '')
    ]);
    error_log('Запись заказа завершена успешно.');

    // Получаем ID последнего вставленного заказа
    $orderId = $pdo->lastInsertId();
    error_log('Получен ID последнего вставленного заказа: ' . $orderId);

} catch (PDOException $e) {
    error_log("Ошибка записи в базу данных: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database insert error']);
    exit();
}

// Запись данных о пакетах в таблицу order_packages
$packages = $input['packages'] ?? [];
if ($packages && is_array($packages)) {
    foreach ($packages as $package) {
        if (
            !isset($package['calories']) || !is_string($package['calories']) ||
            !isset($package['quantity']) || !is_numeric($package['quantity']) ||
            !isset($package['dates']) || !is_array($package['dates'])
        ) {
            error_log('Ошибка: Пакеты отсутствуют или данные неверного формата.');
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid package data']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO order_packages (order_id, calories, quantity)
                VALUES (:order_id, :calories, :quantity)
            ");
            $stmt->execute([
                ':order_id' => $orderId,
                ':calories' => htmlspecialchars($package['calories']),
                ':quantity' => intval($package['quantity'])
            ]);

            // Получаем последний вставленный ID для order_package
            $orderPackageId = $pdo->lastInsertId();
            error_log("Создан новый пакет с ID: " . $orderPackageId);

            // Разделение дат, если они пришли в одной строке
            $deliveryDates = [];
            foreach ($package['dates'] as $deliveryDate) {
                // Если даты объединены в строку, разбиваем её
                if (strpos($deliveryDate, ',') !== false) {
                    $deliveryDates = array_merge($deliveryDates, array_map('trim', explode(',', $deliveryDate)));
                } else {
                    $deliveryDates[] = trim($deliveryDate);
                }
            }

            // Сохранение всех дат доставки в таблице delivery_dates
            foreach ($deliveryDates as $deliveryDate) {
                error_log("Сохранение даты доставки: " . $deliveryDate);
                $stmt = $pdo->prepare("
                    INSERT INTO delivery_dates (order_package_id, delivery_date)
                    VALUES (:order_package_id, :delivery_date)
                ");
                $stmt->execute([
                    ':order_package_id' => $orderPackageId,
                    ':delivery_date' => $deliveryDate
                ]);
                error_log("Дата доставки " . $deliveryDate . " успешно сохранена для order_package_id: " . $orderPackageId);
            }
        } catch (PDOException $e) {
            error_log("Ошибка записи в базу данных пакетов: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database insert error for packages']);
            exit();
        }
    }
}

error_log("Данные заказа и пакетов успешно сохранены в базу данных.");

// Генерация нового CSRF-токена
$newCsrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $newCsrfToken;
$_SESSION['order_id'] = $orderId;
error_log("Сгенерирован новый CSRF-токен: " . $newCsrfToken);

// Формируем ответ клиенту с подтверждением успеха и новым CSRF-токеном
$response = [
    'status' => 'success',
    'message' => 'Данные заказа успешно обработаны и сохранены.',
    'new_csrf_token' => $newCsrfToken,
    'order_id' => $orderId
];
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
