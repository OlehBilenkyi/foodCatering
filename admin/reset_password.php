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
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

// Загрузка переменных окружения из .env
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("Начало обработки POST запроса"); // Логируем начало работы
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Проверяем наличие email в базе данных
            error_log("Проверка наличия email: " . $email); // Логируем email
            $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                error_log("Email найден в базе данных, генерируем токен");
                // Генерация токена для сброса пароля
                $reset_token = bin2hex(random_bytes(32));
                $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour')); // Срок действия токена — 1 час

                // Обновляем таблицу admins, добавляем токен и срок действия
                error_log("Обновляем запись в базе данных с токеном");
                $stmt = $pdo->prepare('UPDATE admins SET reset_token = ?, reset_token_expiry = ? WHERE email = ?');
                $stmt->execute([$reset_token, $expiry_time, $email]);

                // Настройка PHPMailer
                $mail = new PHPMailer(true);
                try {
                    error_log("Настройка SMTP для отправки письма");
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
                    $reset_link = "https://foodcasecatering.net/admin/new_password.php?token=" . $reset_token;
                    $mail->isHTML(true);
                    $mail->Subject = "=?UTF-8?B?" . base64_encode('Сброс пароля для FoodCase Catering') . "?=";
                    $mail->Body = 'Привет! Чтобы сбросить пароль, перейдите по следующей ссылке: <a href="' . $reset_link . '">Сбросить пароль</a>';

                    // Отправка письма
                    error_log("Отправка письма");
                    $mail->send();
                    error_log("Письмо успешно отправлено");
                    $successMessage = "Если email существует в нашей системе, письмо для сброса пароля было отправлено.";
                } catch (Exception $e) {
                    error_log("Ошибка при отправке письма: " . $mail->ErrorInfo);
                    $errorMessage = "Ошибка при отправке письма. Пожалуйста, попробуйте позже.";
                }
            } else {
                error_log("Email не найден в базе данных");
                $successMessage = "Если email существует в нашей системе, письмо для сброса пароля было отправлено.";
            }
        } catch (PDOException $e) {
            error_log("Ошибка в запросе к базе данных: " . $e->getMessage());
            $errorMessage = "Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.";
        }
    } else {
        error_log("Введен некорректный email: " . $email);
        $errorMessage = "Пожалуйста, введите корректный email.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля</title>
    <link rel="stylesheet" href="/assets/stylescss/styles.css">
</head>
<body>
    <div class="reset-password-container">
        <h2>Сброс пароля администратора</h2>

        <?php
        if (!empty($errorMessage)) {
            echo "<p class='error-message'>" . htmlspecialchars($errorMessage) . "</p>";
        }
        if (!empty($successMessage)) {
            echo "<p class='success-message'>" . htmlspecialchars($successMessage) . "</p>";
        }
        ?>

        <form method="POST" action="reset_password.php" class="reset-password-form">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Сбросить пароль</button>
        </form>
    </div>
</body>
</html>
