<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
use Stripe\Stripe;

// Настройки параметров куки для сессии
session_set_cookie_params([
    'lifetime' => 14400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'None',
]);

session_start();
session_regenerate_id(true);

// Включаем логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

$dotenv = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Подключаем базу данных
include $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверка наличия session_id в запросе
if (!isset($_GET['session_id'])) {
    error_log("❌ Ошибка: отсутствует идентификатор сессии Stripe.");
    http_response_code(400);
    exit("Отсутствует идентификатор сессии Stripe.");
}

$session_id = htmlspecialchars($_GET['session_id']);

// Убедимся, что ключ Stripe существует
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
if (!$stripeSecretKey) {
    error_log("❌ Ошибка: отсутствует ключ Stripe.");
    http_response_code(500);
    exit("Ошибка: отсутствует ключ Stripe.");
}

Stripe::setApiKey($stripeSecretKey);

try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status !== 'paid') {
        error_log("❌ Ошибка: сессия не оплачена.");
        http_response_code(400);
        exit("Сессия не оплачена.");
    }

    // Получаем order_id из метаданных Stripe
    $orderId = $session->metadata->order_id ?? null;
    if (!$orderId) {
        error_log("❌ Ошибка: order_id отсутствует в метаданных.");
        http_response_code(400);
        exit("Ошибка при обработке платежа.");
    }

    // Логирование перед обновлением статуса
    error_log("🔄 Обновление статуса заказа $orderId на 'paid'");

    // Обновляем статус заказа в customer_menu_orders
    $stmt = $pdo->prepare("UPDATE customer_menu_orders SET status = 'paid' WHERE order_id = ?");
    $stmt->execute([$orderId]);

    if ($stmt->rowCount() > 0) {
        error_log("✅ Статус заказа #$orderId успешно обновлён на 'paid'");
    } else {
        error_log("⚠️ Ошибка: статус заказа #$orderId не обновился. Возможно, order_id неверный?");
    }

    // Получаем order_day_id из customer_menu_order_days
    $stmt = $pdo->prepare("SELECT order_day_id FROM customer_menu_order_days WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $orderDay = $stmt->fetch();

    if (!$orderDay) {
        error_log("⚠️ Ошибка: order_day_id не найден для order_id = $orderId");
    } else {
        $orderDayId = $orderDay['order_day_id'];
        error_log("✅ Найден order_day_id: $orderDayId для order_id: $orderId");

        // Получаем детали заказанных позиций
        $stmt = $pdo->prepare("SELECT dish_name, weight, price FROM customer_menu_order_items WHERE order_day_id = ?");
        $stmt->execute([$orderDayId]);
        $items = $stmt->fetchAll();

        if ($items) {
            error_log("✅ Найдено " . count($items) . " позиций в заказе для order_day_id: $orderDayId");
            $itemDetails = '';
            foreach ($items as $item) {
                $itemDetails .= "<b>{$item['dish_name']}</b> - {$item['weight']}g - {$item['price']} PLN<br>";
            }
            error_log("📌 Детали заказа:\n$itemDetails");
        } else {
            error_log("⚠️ Внимание: Не найдено позиций в заказе для order_day_id: $orderDayId");
        }
    }

    error_log("✅ Заказ #$orderId успешно обновлен на статус 'paid'.");

    // Очистка данных о заказе из сессии
    session_unset();
    session_write_close();

    // Перенаправление на страницу успешного платежа
    header("Location: /payments/success.php?order_id=$orderId");
    exit();

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("❌ Ошибка при проверке Stripe-сессии: " . $e->getMessage());
    http_response_code(500);
    exit("Ошибка при проверке платежа.");
} catch (Exception $e) {
    error_log("❌ Общая ошибка: " . $e->getMessage());
    http_response_code(500);
    exit("Произошла ошибка обработки вашего заказа.");
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Pay - FoodCase Catering</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="../assets/css/global.css">
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  
  <style>
      .info-block__actions {
        display: flex;
        align-items: center;
        gap: 15px;
        justify-content: center;
        flex-direction: column;
    }
    .info-block__success{
        display: flex;
        gap: 10px;
    }
      
  </style>
  
</head>
<body>
  <?php  include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';?>

  <main class="page-main">
    <div class="container">
      <div class="info-block">
        <svg width="120" height="139" viewBox="0 0 120 139" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="60" cy="69" r="57.5" stroke="#057916" stroke-width="5"/>
          <line x1="32.6609" y1="69.1315" x2="59.6609" y2="93.1315" stroke="#006A23" stroke-width="5"/>
          <line x1="57.0866" y1="92.391" x2="94.0866" y2="48.391" stroke="#006A23" stroke-width="5"/>
        </svg>

        <h1>Twoja płatność została przetworzona!</h1>
        <p>Twoja płatność za zamówienie została pomyślnie zakończona. Rozpoczęliśmy już realizację Twojego zamówienia. Oczekuj e-maila z potwierdzeniem i wszystkimi szczegółami.</p>

        <div class="info-block__actions">
          <a href="/" class="btn">Strona główna</a>
          <div class="info-block__success">
                <a href="/index2/" class="btn">Standardowe menu</a>
                <a href="/menu_do_wyboru/" class="btn">Menu do wyboru</a>
            </div>
        </div>
        
      </div>
    </div>
  </main>

  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>

  <script src="../assets/js/global.js"></script>

  <script async>
    // Перенаправляем пользователя через 5 секунд после успешной оплаты
    setTimeout(function() {
        window.location.href = "/"; // Перенаправление на главную страницу
    }, 4000);
  </script>

  <noscript>
    <p>JavaScript отключен в вашем браузере. Некоторые функции могут быть недоступны.</p>
  </noscript>
</body>
</html>