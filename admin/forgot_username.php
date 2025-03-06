<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Подключение к базе данных
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'; // Загрузка PHPMailer и dotenv

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки

// Загрузка переменных окружения из .env
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Проверяем наличие email в базе данных
            $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Отправка письма с логином
                $username = $user['username'];
                
                $mail = new PHPMailer(true);
                try {
                    // Настройки SMTP
                    $mail->isSMTP();
                    $mail->Host = $_ENV['SMTP_HOST'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['SMTP_USERNAME'];
                    $mail->Password = $_ENV['SMTP_PASSWORD'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                    $mail->Port = $_ENV['SMTP_PORT'];
                    $mail->CharSet = 'UTF-8'; // Устанавливаем кодировку UTF-8

                    // Получатель
                    $mail->setFrom('no-reply@foodcasecatering.net', 'FoodCase Catering');
                    $mail->addAddress($email);

                    // Контент письма
                    $mail->isHTML(true);
                    $mail->Subject = "=?UTF-8?B?" . base64_encode('Ваш логин для FoodCase Catering') . "?="; // Кодируем тему в UTF-8 Base64
                    $mail->Body = 'Привет! Ваш логин для входа: <strong>' . htmlspecialchars($username) . '</strong>';

                    $mail->send();
                    $successMessage = "Если email существует в нашей системе, письмо с логином было отправлено.";
                } catch (Exception $e) {
                    error_log("Ошибка при отправке письма: " . $mail->ErrorInfo);
                    $errorMessage = "Ошибка при отправке письма. Пожалуйста, попробуйте позже.";
                }
            } else {
                $successMessage = "Если email существует в нашей системе, письмо с логином было отправлено.";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errorMessage = "Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.";
        }
    } else {
        $errorMessage = "Пожалуйста, введите корректный email.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление Логина</title>
    <link rel="stylesheet" href="/assets/stylescss/styles.css">
</head>
<body>
    <div class="reset-username-container">
        <h2>Восстановление Логина Администратора</h2>

        <?php
        if (!empty($errorMessage)) {
            echo "<p class='error-message'>" . htmlspecialchars($errorMessage) . "</p>";
        }
        if (!empty($successMessage)) {
            echo "<p class='success-message'>" . htmlspecialchars($successMessage) . "</p>";
        }
        ?>

        <form method="POST" action="forgot_username.php" class="reset-username-form">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Получить Логин</button>
        </form>
    </div>
</body>
</html>
