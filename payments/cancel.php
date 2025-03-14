<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'; // Подключение PHPMailer для отправки email уведомлений

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Защита параметров сессии и безопасности куки
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']), // Только по HTTPS
    'httponly' => true, // Запрещает доступ к куки через JavaScript
    'samesite' => 'Strict',
]);

// Старт сессии для защиты от CSRF
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Проверка наличия email клиента в сессии для отправки уведомления об отмене платежа
if (isset($_SESSION['customer_email'])) {
    sendCancellationEmail($_SESSION['customer_email']);
}

// Функция для отправки email об отмене платежа
function sendCancellationEmail($email) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer();
    try {
        // Настройка и отправка email уведомления
        $mail->setFrom('noreply@foodcasecatering.net', 'FoodCase');
        $mail->addAddress($email);
        $mail->Subject = 'Отмена платежа FoodCase';
        $mail->Body = "Мы заметили, что вы отменили оплату. Если у вас возникли вопросы или трудности, пожалуйста, свяжитесь с нашей службой поддержки.";

        if (!$mail->send()) {
            throw new Exception('Ошибка при отправке email об отмене: ' . $mail->ErrorInfo);
        }
        error_log("Уведомление об отмене платежа успешно отправлено на email: {$email}");
    } catch (Exception $e) {
        error_log("Ошибка отправки email об отмене: " . $e->getMessage());
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Подключаем базу данных
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата отменена - FoodCase</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="../assets/css/global.css">
    
    <style>
        .info-block__actions {
        display: flex;
        align-items: center;
        gap: 15px;
        justify-content: center;
        flex-direction: column;
    }
    .info-block__cancel{
        display: flex;
        justify-content: space-evenly;
        padding-bottom: 30px;
        gap: 10px;
    }
    
    
    </style>
    
    
</head>
<body>
  <?php  include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; // Подключаем шапку ?>

<main class="page-main">
    <div class="container">
      
      <div class="info-block">

        <svg width="120" height="139" viewBox="0 0 120 139" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="60" cy="69" r="57.5" stroke="#FF0000" stroke-width="5"/>
          <path d="M64.5625 27.3636L63.7457 87.4773H54.2713L53.4545 27.3636H64.5625ZM59.0085 111.653C56.9938 111.653 55.265 110.932 53.8221 109.489C52.3791 108.046 51.6577 106.317 51.6577 104.303C51.6577 102.288 52.3791 100.559 53.8221 99.1161C55.265 97.6732 56.9938 96.9517 59.0085 96.9517C61.0232 96.9517 62.752 97.6732 64.195 99.1161C65.6379 100.559 66.3594 102.288 66.3594 104.303C66.3594 105.637 66.0191 106.862 65.3384 107.978C64.685 109.094 63.8002 109.993 62.6839 110.673C61.5949 111.327 60.3698 111.653 59.0085 111.653Z" fill="#FF1313"/>
        </svg>

        <h1>Twoja płatność nie została zrealizowana</h1>
        <p>Twoja płatność za zamówienie nie została zakończona pomyślnie. Zamówienie nie zostało przetworzone, ponieważ wystąpiły problemy z realizacją płatności. Proszę spróbować ponownie lub skontaktować się z nami w celu uzyskania wsparcia.</p>


        <div class="info-block__actions">
            <a href="/" class="btn">Strona główna</a>
            <div class="info-block__cancel">
                <a href="/index2/" class="btn">Standardowe menu</a>
    <a href="/menu_do_wyboru/" class="btn">Menu do wyboru</a>
            </div>
        </div>
          
      </div>

    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
  <script async>
    // Перенаправляем пользователя через 5 секунд после успешной оплаты
    setTimeout(function() {
        window.location.href = "/"; // Перенаправление на главную страницу
    }, 4000);
  </script>
</body>
</html>
