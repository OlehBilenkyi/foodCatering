<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/utils/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Ошибка: Неверный метод запроса.");
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$newStatus = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

if (!$orderId || !$newStatus) {
    die("Ошибка: Неверные данные.");
}

// Получаем email клиента
$query = $pdo->prepare("SELECT customer_email FROM orders WHERE order_id = ?");
$query->execute([$orderId]);
$customerEmail = $query->fetchColumn();

if ($customerEmail) {
    // Обновляем статус в БД
    $updateQuery = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $updateQuery->execute([$newStatus, $orderId]);

    // Отправляем уведомление
    sendOrderStatusEmail($customerEmail, $orderId, $newStatus);

    header("Location: /orders/order_details.php?order_id=$orderId");
    exit();
} else {
    die("Ошибка: Заказ не найден.");
}
?>
