<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
use Stripe\Stripe;

// Настройки параметров куки для сессии
session_set_cookie_params([
    'lifetime' => 14400, // 4 часа
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']), 
    'httponly' => true, 
    'samesite' => 'None', 
]);

session_start();
session_regenerate_id(true); 

// Логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

$dotenv = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Проверяем наличие session_id
if (!isset($_GET['session_id'])) {
    error_log("Ошибка: отсутствует session_id.");
    http_response_code(400);
    exit("Ошибка: отсутствует session_id.");
}

$session_id = htmlspecialchars($_GET['session_id']);

// Убедимся, что ключ Stripe существует
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
if (!$stripeSecretKey) {
    error_log("Ошибка: отсутствует ключ Stripe.");
    http_response_code(500);
    exit("Ошибка: отсутствует ключ Stripe.");
}

Stripe::setApiKey($stripeSecretKey);

try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status !== 'paid') {
        error_log("Ошибка: сессия $session_id не оплачена.");
        http_response_code(400);
        exit("Сессия не оплачена.");
    }

    // Получаем order_id из метаданных Stripe
    $orderId = $session->metadata->order_id ?? null;

    if (!$orderId) {
        error_log("Ошибка: order_id отсутствует в метаданных.");
        http_response_code(400);
        exit("Ошибка: отсутствует order_id.");
    }

    error_log("Проверяем заказ #$orderId в БД.");

    // Подключение к БД
    try {
        $pdo = new PDO(
            "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=" . $_ENV['DB_CHARSET'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        error_log("Ошибка БД: " . $e->getMessage());
        http_response_code(500);
        exit();
    }

    // Проверяем заказ в `orders`
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if ($order) {
        if ($order['status'] !== 'оплачен') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'оплачен' WHERE order_id = ?");
            $stmt->execute([$orderId]);
            error_log("✅ Статус заказа #$orderId обновлен в `orders`.");
        }
        $orderType = 'orders';
    } else {
        // Проверяем заказ в `customer_menu_orders`
        $stmt = $pdo->prepare("SELECT status FROM customer_menu_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $menuOrder = $stmt->fetch();

        if ($menuOrder) {
            if ($menuOrder['status'] !== 'paid') {
                $stmt = $pdo->prepare("UPDATE customer_menu_orders SET status = 'paid' WHERE order_id = ?");
                $stmt->execute([$orderId]);
                error_log("✅ Статус заказа #$orderId обновлен в `customer_menu_orders`.");
            }
            $orderType = 'customer_menu_orders';
        } else {
            error_log("Ошибка: заказ #$orderId не найден в БД.");
            http_response_code(404);
            exit("Ошибка: заказ не найден.");
        }
    }

    // Очистка данных о заказе из сессии
    session_unset();
    session_write_close();

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Ошибка Stripe: " . $e->getMessage());
    http_response_code(500);
    exit("Ошибка платежа.");
} catch (Exception $e) {
    error_log("Общая ошибка: " . $e->getMessage());
    http_response_code(500);
    exit("Ошибка обработки заказа.");
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
