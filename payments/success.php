<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
use Stripe\Stripe;

// Настройки параметров куки для сессии
session_set_cookie_params([
    'lifetime' => 14400, // Устанавливаем время жизни куки на 4 часа
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']), // Куки только для HTTPS
    'httponly' => true, // Защита от XSS
    'samesite' => 'None', // Для совместимости с мобильными устройствами
]);

session_start();
session_regenerate_id(true); // Защита от фиксации сессий

// Включаем логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

$dotenv = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Проверяем наличие session_id в запросе
if (!isset($_GET['session_id'])) {
    error_log("Ошибка: отсутствует идентификатор сессии Stripe.");
    http_response_code(400);
    exit("Отсутствует идентификатор сессии Stripe.");
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

// Проверка Stripe-сессии для отображения успеха пользователю
try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status !== 'paid') {
        error_log("Ошибка: сессия не оплачена.");
        http_response_code(400);
        exit("Сессия не оплачена.");
    }

    // Логирование успешной проверки Stripe-сессии
    error_log("Сессия с ID $session_id успешно оплачена. Переход на страницу подтверждения оплаты.");

    // Очистка данных о заказе из сессии
    session_unset(); // Полностью очищаем данные сессии
    session_write_close();

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Ошибка при проверке Stripe-сессии: " . $e->getMessage());
    http_response_code(500);
    exit("Ошибка при проверке платежа.");
} catch (Exception $e) {
    error_log("Общая ошибка: " . $e->getMessage());
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
          <a href="/index2" class="btn">Złóż zamówienie</a>
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
