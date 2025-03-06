<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Подключаем библиотеку Dotenv для получения данных из .env файла
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'errors' => ['Ошибка CSRF: неверный токен.']]);
        exit();
    }

    // Валидация данных
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
    $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    $errors = [];

    if (empty($message) || strlen($message) < 15) {
        $errors[] = 'Сообщение должно содержать не менее 15 символов.';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit();
    }

    $name = !empty($name) ? htmlspecialchars($name) : 'Anonymous';
    $email = !empty($email) ? htmlspecialchars($email) : 'not_provided@example.com';

    $to = $_ENV['SMTP_USERNAME'];
    $subject = "Feedback from " . $name;
    $messageBody = "
        <html>
            <head>
                <title>Feedback from Website</title>
            </head>
            <body>
                <p><strong>Имя:</strong> " . $name . "</p>
                <p><strong>Email:</strong> " . ($email !== 'not_provided@example.com' ? $email : 'Не указан') . "</p>
                <p><strong>Сообщение:</strong> " . nl2br(htmlspecialchars($message)) . "</p>
            </body>
        </html>
    ";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SMTP_USERNAME'], 'Feedback');
        $mail->addAddress($to);

        if ($email !== 'not_provided@example.com') {
            $mail->addReplyTo($email, $name);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $messageBody;

        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Сообщение успешно отправлено. Спасибо за ваш отзыв!']);
    } catch (Exception $e) {
        error_log("Ошибка при отправке email через SMTP: " . $mail->ErrorInfo);
        echo json_encode(['success' => false, 'errors' => ['Ошибка при отправке сообщения. Попробуйте позже.']]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Некорректный метод запроса. Ожидается POST.']]);
}
?>
