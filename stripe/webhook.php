<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Stripe\Webhook;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error_log.log');

// Подгружаем переменные окружения из .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Секретный ключ вебхука
$endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;
if (!$endpoint_secret) {
    error_log("❌ Ошибка: Секретный ключ вебхука отсутствует.");
    http_response_code(500);
    exit();
}

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
    error_log("✅ Успешное подключение к базе данных.");
} catch (PDOException $e) {
    error_log("❌ Ошибка подключения к базе данных: " . $e->getMessage());
    http_response_code(500);
    exit();
}

// Читаем запрос
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;
$event = null;

try {
    if (!$sig_header) {
        throw new Exception("❌ Ошибка: Отсутствует заголовок Stripe Signature.");
    }
    $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    error_log("✅ Событие от Stripe успешно верифицировано.");
} catch (Exception $e) {
    http_response_code(400);
    error_log($e->getMessage());
    exit();
}

// Проверка события checkout.session.completed
if ($event->type === 'checkout.session.completed') {
    error_log("✅ Получено событие checkout.session.completed");
    $session = $event->data->object;
    $orderId = $session->metadata->order_id ?? null;

    if (!$orderId) {
        error_log("❌ Ошибка: order_id отсутствует в метаданных Stripe.");
        http_response_code(400);
        exit();
    }

    error_log("ℹ️ Обработка заказа с order_id: $orderId");

    $order = getOrderFromDB($pdo, 'orders', $orderId);
    $menuOrder = getOrderFromDB($pdo, 'customer_menu_orders', $orderId);

    if ($order) {
        updateOrderStatus($pdo, 'orders', $orderId, 'оплачен');
        $orderItems = getOrderItems($pdo, 'orders', $orderId);
        $emailTemplate = __DIR__ . '/../emails/order_confirmation_template.php';
        $customerEmail = $order['customer_email'];
    } elseif ($menuOrder) {
    updateOrderStatus($pdo, 'customer_menu_orders', $orderId, 'paid');

    // Получаем позиции меню
    $rawOrderItems = getOrderItems($pdo, 'customer_menu_orders', $orderId);

    // Группируем по датам доставки
    $orderItems = [];
    foreach ($rawOrderItems as $item) {
        $date = $item['delivery_date'];
        if (!isset($orderItems[$date])) {
            $orderItems[$date] = [
                'delivery_date' => $date,
                'day_total_price' => $item['day_total_price'],
                'items' => []
            ];
        }
        if (!empty($item['dish_name'])) {
            $orderItems[$date]['items'][] = [
                'category' => $item['category'],
                'dish_name' => $item['dish_name'],
                'weight' => $item['weight'],
                'price' => $item['price']
            ];
        }
    }

    $emailTemplate = __DIR__ . '/../emails/menu_order_confirmation_template.php';
    $customerEmail = $menuOrder['delivery_email'];
} else {
        error_log("❌ Ошибка: заказ #$orderId не найден.");
        http_response_code(404);
        exit();
    }

    if (empty($customerEmail)) {
        error_log("⚠️ Ошибка: email не найден для заказа #$orderId.");
        http_response_code(400);
        exit();
    }
function getPackageDetails($pdo, $orderId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM order_packages WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $orderId]);
        $packages = $stmt->fetchAll();

        $packageDetailsHtml = '';
        foreach ($packages as $package) {
            $stmtDates = $pdo->prepare("SELECT delivery_date FROM delivery_dates WHERE order_package_id = :order_package_id");
            $stmtDates->execute([':order_package_id' => $package['id']]);
            $dates = $stmtDates->fetchAll();
            $deliveryDates = array_map(function($date) {
                return htmlspecialchars($date['delivery_date'], ENT_QUOTES, 'UTF-8');
            }, $dates);
            $deliveryDatesString = implode(', ', $deliveryDates);
            $packageDetailsHtml .= 'Kalorie: ' . htmlspecialchars($package['calories'], ENT_QUOTES, 'UTF-8') . '<br>' .
                                   'Ilość: ' . intval($package['quantity']) . '<br>' .
                                   'Daty dostawy: ' . $deliveryDatesString . '<br><br>';
        }
        return $packageDetailsHtml;
    } catch (PDOException $e) {
        error_log("Ошибка при получении данных о пакетах и датах доставки: " . $e->getMessage());
        return 'Błąd przy uzyskiwaniu szczegółów pakietów.';
    }
}

$emailData = [
    'orderId' => $orderId,
    'order_date' => $order['created_at'] ?? $menuOrder['order_date'] ?? 'Nie podano',
    'total_amount' => ($order['total_price'] ?? $menuOrder['total_price'] ?? '0.00') . ' zł',
    'delivery_email' => $order['customer_email'] ?? $menuOrder['delivery_email'] ?? 'Nie podano',
    'customer_email' => $order['customer_email'] ?? $menuOrder['delivery_email'] ?? 'Nie podano', // Дублируем для разных названий
    'phone' => $order['customer_phone'] ?? $menuOrder['phone'] ?? 'Nie podano',
    'customer_phone' => $order['customer_phone'] ?? $menuOrder['phone'] ?? 'Nie podano',
    'full_name' => $order['customer_fullname'] ?? $menuOrder['full_name'] ?? 'Nie podano',
    'customer_name' => $order['customer_fullname'] ?? $menuOrder['full_name'] ?? 'Nie podano',
    'street' => $order['customer_street'] ?? $menuOrder['street'] ?? 'Nie podano',
    'customer_street' => $order['customer_street'] ?? $menuOrder['street'] ?? 'Nie podano',
    'house_number' => $order['customer_house_number'] ?? $menuOrder['house_number'] ?? 'Nie podano',
    'customer_house_number' => $order['customer_house_number'] ?? $menuOrder['house_number'] ?? 'Nie podano',
    'building' => $order['customer_building'] ?? $menuOrder['building'] ?? 'Nie podano',
    'customer_building' => $order['customer_building'] ?? $menuOrder['building'] ?? 'Nie podano',
    'floor' => $order['customer_floor'] ?? $menuOrder['floor'] ?? 'Nie podano',
    'customer_floor' => $order['customer_floor'] ?? $menuOrder['floor'] ?? 'Nie podano',
    'apartment' => $order['customer_apartment'] ?? $menuOrder['apartment'] ?? 'Nie podano',
    'customer_apartment' => $order['customer_apartment'] ?? $menuOrder['apartment'] ?? 'Nie podano',
    'entry_code' => $order['customer_gate_code'] ?? $menuOrder['entry_code'] ?? 'Nie podano',
    'customer_gate_code' => $order['customer_gate_code'] ?? $menuOrder['entry_code'] ?? 'Nie podano',
    'klatka' => $order['customer_klatka'] ?? $menuOrder['building'] ?? 'Nie podano',
    'customer_klatka' => $order['customer_klatka'] ?? $menuOrder['building'] ?? 'Nie podano',
    'notes' => $order['customer_notes'] ?? $menuOrder['notes'] ?? 'Nie podano',
    'customer_notes' => $order['customer_notes'] ?? $menuOrder['notes'] ?? 'Nie podano',
       // Позиции заказа
    'order_items' => array_values($orderItems),

    // Детали пакетов (если есть)
    'package_details' => getPackageDetails($pdo, $orderId) ?? 'Brak danych'
];

    error_log("ℹ️ Email Data: " . print_r($emailData, true));

    ob_start();
    extract($emailData);
    include $emailTemplate;
    $emailBody = ob_get_clean();

    sendEmail($customerEmail, "Potwierdzenie zamówienia - FoodCase", $emailBody);
    sendEmail($_ENV['SMTP_USERNAME'], "Nowe zamówienie - FoodCase", $emailBody);
    error_log("✅ Email отправлен покупателю: $customerEmail");
}

http_response_code(200);
exit();

// Функция для генерации деталей пакета для email, включая даты доставки


// Функции для работы с базой данных
function getOrderFromDB($pdo, $table, $orderId) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE order_id = :order_id");
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetch();
}

function updateOrderStatus($pdo, $table, $orderId, $status) {
    $stmt = $pdo->prepare("UPDATE $table SET status = :status WHERE order_id = :order_id");
    $stmt->execute([':status' => $status, ':order_id' => $orderId]);
}

function getOrderItems($pdo, $table, $orderId) {
    if ($table === 'orders') {
        $query = "
            SELECT op.calories, op.quantity, dd.delivery_date
            FROM order_packages op
            LEFT JOIN delivery_dates dd ON op.id = dd.order_package_id
            WHERE op.order_id = :order_id
        ";
    } else {
        $query = "
            SELECT 
                cmd.delivery_date, 
                cmd.day_total_price, 
                cmi.category, 
                cmi.dish_name, 
                cmi.weight, 
                cmi.price
            FROM customer_menu_order_days cmd
            LEFT JOIN customer_menu_order_items cmi ON cmd.order_day_id = cmi.order_day_id
            WHERE cmd.order_id = :order_id
        ";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    

}




// Функция отправки email
function sendEmail($email, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_USERNAME'], 'FoodCase');
        $mail->addAddress($email);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Ошибка при отправке email: " . $e->getMessage());
    }
}
