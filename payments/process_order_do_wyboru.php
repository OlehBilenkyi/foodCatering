<?php
// Отключаем вывод ошибок в браузер, но включаем логирование
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);

// Заголовки безопасности и отключение кэширования
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// ======================== Запуск сессии ========================

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
    error_log("Сессия запущена в process_order_do_wyboru.php. ID: " . session_id());
}

// ======================== Подключение автозагрузчика и переменных окружения ========================

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// ======================== Подключение к базе данных ========================

include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');
if (!$pdo) {
    error_log("[ERROR] Ошибка подключения к базе данных.");
    exit(json_encode(['status' => 'error', 'message' => 'Не удалось подключиться к базе данных.']));
}

// ======================== Работа с CSRF-токеном ========================

$sessionToken = $_SESSION['csrf_token'] ?? null;
if (!$sessionToken) {
    error_log("[ERROR] CSRF-токен отсутствует в сессии!");
    echo json_encode(['status' => 'error', 'message' => 'Ошибка безопасности: CSRF-токен отсутствует.']);
    exit();
}


// Логируем текущее состояние сессии
error_log("Текущее содержимое сессии: " . print_r($_SESSION, true));

// ======================== Обработка входящих данных ========================

$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    error_log("Payment Summary - Полученные сырые данные JSON: " . $rawInput);
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Payment Summary - Ошибка разбора JSON: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
        exit();
    }
} else {
    $input = $_POST;
    error_log("Payment Summary - Полученные данные (POST): " . print_r($input, true));
}

if (empty($input)) {
    error_log("Payment Summary - Ошибка: Входящие данные отсутствуют");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty form input']);
    exit();
}

// Логирование CSRF-токенов
error_log("Payment Summary - SESSION CSRF token: " . $sessionToken);
$csrfTokenInput = $input['csrf_token'] ?? null;
error_log("Payment Summary - Полученный CSRF token из запроса: " . ($csrfTokenInput ?? 'не задан'));

// Проверка CSRF-токена
if (empty($csrfTokenInput) || !hash_equals($sessionToken, (string)$csrfTokenInput)) {
    error_log("Payment Summary - Ошибка CSRF: Ожидалось: " . $sessionToken . ", получено: " . $csrfTokenInput);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Ошибка безопасности: Неверный CSRF-токен.']);
    exit();
}
error_log("Payment Summary - CSRF-токен успешно проверен.");

// ======================== Валидация входящих данных ========================

// Список полей, которые должны быть строками
$requiredStringFields = ['email', 'phone', 'fullname', 'street', 'house_number', 'floor', 'apartment', 'total_price'];
foreach ($requiredStringFields as $field) {
    $value = $input[$field] ?? '';
    // Если значение является массивом, берем первый элемент
    if (is_array($value)) {
        $value = trim(reset($value));
    } else {
        $value = trim($value);
    }
    if (empty($value)) {
        error_log("Payment Summary - Ошибка: Не заполнено обязательное поле: " . $field);
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Проверьте, что все обязательные поля заполнены корректно.'
        ]);
        exit();
    }
}

// Отдельно проверяем поле order_days (оно должно быть массивом)
if (!isset($input['order_days']) || !is_array($input['order_days']) || empty($input['order_days'])) {
    error_log("Payment Summary - Ошибка: Отсутствуют данные о днях заказа.");
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Отсутствуют данные о днях заказа.'
    ]);
    exit();
}

$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
if (!$email) {
    error_log("Payment Summary - Ошибка: Неверный формат email: " . $input['email']);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Неверный формат email.']);
    exit();
}

$phone       = trim($input['phone']);
$fullName    = trim($input['fullname']);
$street      = trim($input['street']);
$houseNumber = trim($input['house_number']);
$building    = trim($input['building'] ?? '');
$floor       = trim($input['floor']);
$apartment   = trim($input['apartment']);
$entryCode   = trim($input['gate_code'] ?? '');
$notes       = trim($input['notes'] ?? '');
$totalPrice  = floatval($input['total_price']);
if ($totalPrice <= 0) {
    error_log("Payment Summary - Ошибка: Неверная сумма оплаты: " . $totalPrice);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Неверная или отсутствующая сумма для оплаты.']);
    exit();
}

$orderDays = $input['order_days'];

// ======================== Подключение к базе данных (PDO) ========================

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
    error_log("Payment Summary - Успешное подключение к базе данных для пользователя " . $_ENV['DB_USER']);
} catch (PDOException $e) {
    error_log("Payment Summary - Ошибка подключения к базе данных: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Ошибка сервера. Попробуйте позже.'
    ]);
    exit();
}

// ======================== Обработка и сохранение заказа ========================

try {
    // Начинаем транзакцию
    $pdo->beginTransaction();

    // Вставляем основной заказ
    $stmtOrder = $pdo->prepare("
        INSERT INTO customer_menu_orders
            (delivery_email, phone, full_name, street, house_number, building, floor, apartment, entry_code, notes, total_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtOrder->execute([
        $email,
        $phone,
        $fullName,
        $street,
        $houseNumber,
        $building,
        $floor,
        $apartment,
        $entryCode,
        $notes,
        $totalPrice
    ]);
    
    $orderId = $pdo->lastInsertId();
    error_log("Payment Summary - Основной заказ успешно сохранён. order_id: " . $orderId);

    // Вставляем данные по дням заказа
    $stmtDay = $pdo->prepare("
        INSERT INTO customer_menu_order_days (order_id, delivery_date, day_total_price)
        VALUES (?, ?, ?)
    ");

    // Подготавливаем запрос для блюд заказа (с menu_options_id)
    $stmtItem = $pdo->prepare("
        INSERT INTO customer_menu_order_items (order_day_id, menu_options_id, category, dish_name, weight, price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    // Обрабатываем каждый день заказа
    foreach ($orderDays as $day) {
        if (!isset($day['delivery_date'], $day['day_total_price'], $day['items']) || !is_array($day['items'])) {
            throw new Exception("Payment Summary - Неверный формат данных дня заказа.");
        }
        $stmtDay->execute([$orderId, $day['delivery_date'], $day['day_total_price']]);
        $orderDayId = $pdo->lastInsertId();

        foreach ($day['items'] as $item) {
            // Извлекаем menu_options_id из массива
            $menuOptionsId = isset($item['menu_options_id']) 
    ? (is_array($item['menu_options_id']) ? intval($item['menu_options_id'][0]) : intval($item['menu_options_id']))
    : null;


            // Логируем данные для отладки:
            error_log("Вставляем блюдо: category=" . ($item['category'] ?? 'N/A') .
                ", dishName=" . ($item['dish_name'] ?? ($item['value'] ?? 'N/A')) .
                ", weight=" . ($item['weight'] ?? 'N/A') .
                ", price=" . ($item['price'] ?? 'N/A') .
                ", menu_options_id=" . $menuOptionsId);

            $stmtItem->execute([
                $orderDayId,
                $menuOptionsId,
                $item['category'] ?? null,
                $item['dish_name'] ?? ($item['value'] ?? null),
                $item['weight'] ?? 0,
                $item['price'] ?? 0
            ]);

            $errorInfo = $stmtItem->errorInfo();
            if ($errorInfo[0] !== '00000') {
                error_log("Ошибка вставки блюда: " . print_r($errorInfo, true));
            }
        }
    }

    // Фиксируем транзакцию
    $pdo->commit();

    // Генерируем новый CSRF-токен для будущих запросов
    $newCsrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $newCsrfToken;
    $_SESSION['order_id'] = $orderId;
    error_log("Payment Summary - Новый CSRF-токен сгенерирован для будущих запросов: " . $newCsrfToken);

    echo json_encode([
        'status'         => 'success',
        'message'        => 'Заказ успешно сохранён.',
        'order_id'       => $orderId,
        'new_csrf_token' => $newCsrfToken
    ]);
    exit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment Summary - Ошибка при сохранении заказа: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Ошибка при оформлении заказа. Попробуйте снова.'
    ]);
    exit();
}

error_log("Payment Summary - ====== Конец работы process_order_do_wyboru.php ======");
?>