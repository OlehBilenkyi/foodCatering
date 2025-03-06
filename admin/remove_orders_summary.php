<?php
// Включаем отображение всех ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Логирование ошибок в файл (делаем это ДО запуска сессии)
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Подключаем файл с конфигурацией базы данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверяем, что соединение с базой данных установлено
if (!$pdo) {
    die("Ошибка подключения к базе данных.");
}

// Проверка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    if ($orderId > 0) {
        try {
            // Сначала удаляем все записи в delivery_dates, связанные с order_packages
            $stmt = $pdo->prepare("
                DELETE FROM delivery_dates 
                WHERE order_package_id IN (SELECT id FROM order_packages WHERE order_id = :order_id)
            ");
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            // Далее удаляем все пакеты, связанные с заказом
            $stmt = $pdo->prepare("
                DELETE FROM order_packages 
                WHERE order_id = :order_id
            ");
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            // Теперь можно удалить сам заказ
            $stmt = $pdo->prepare("
                DELETE FROM orders 
                WHERE order_id = :order_id
            ");
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            echo 'success';
        } catch (PDOException $e) {
            echo 'Ошибка при удалении заказа: ' . $e->getMessage();
        }
    } else {
        echo 'Неверный ID заказа.';
    }
} else {
    echo 'Неверный запрос.';
}
?>
