<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php'; // PHPMailer
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php'; // Подключение БД

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOrderStatusEmail($email, $orderId, $newStatus) {
    $mail = new PHPMailer(true);

    try {
        // Настройки SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@example.com';
        $mail->Password = 'your-password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // От кого
        $mail->setFrom('no-reply@example.com', 'Ваш сервис');

        // Кому
        $mail->addAddress($email);

        // Тема и текст письма
        $mail->Subject = "Статус вашего заказа #$orderId обновлен";
        $mail->Body = "Здравствуйте!\n\nВаш заказ #$orderId теперь в статусе: $newStatus.\n\nСпасибо за заказ!";

        // Отправляем письмо
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Ошибка при отправке email: " . $mail->ErrorInfo, 3, $_SERVER['DOCUMENT_ROOT'] . '/logs/error.log');
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? null;
    $newStatus = $_POST['status'] ?? null;

    if (!$orderId || !$newStatus) {
        die("Ошибка: отсутствуют данные.");
    }

    // Получаем email клиента
    $query = $pdo->prepare("SELECT customer_email FROM orders WHERE id = ?");
    $query->execute([$orderId]);
    $customerEmail = $query->fetchColumn();

    if ($customerEmail) {
        // Обновляем статус в БД
        $updateQuery = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $updateQuery->execute([$newStatus, $orderId]);

        // Отправляем email
        sendOrderStatusEmail($customerEmail, $orderId, $newStatus);

        // Добавляем уведомление в БД
        $notificationQuery = $pdo->prepare("INSERT INTO notifications (customer_email, message) VALUES (?, ?)");
        $notificationQuery->execute([$customerEmail, "Статус заказа #$orderId изменён на '$newStatus'"]);
    }
}
?>
