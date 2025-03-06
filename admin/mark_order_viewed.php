<?php
// Включение отображения всех ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Логирование ошибок в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Подключение файла конфигурации базы данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверка подключения к базе данных
if (!isset($pdo) || !$pdo) {
    die("Ошибка подключения к базе данных.");
}

// Проверка метода запроса и наличие order_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];

    // Проверяем, что order_id валиден
    if ($orderId > 0) {
        try {
            // Проверяем, существует ли заказ с указанным ID
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_id = :order_id");
            $checkStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $checkStmt->execute();
            $orderExists = $checkStmt->fetchColumn();

            if ($orderExists) {
                // Обновляем поле `nonce` для указания, что заказ просмотрен
                $updateStmt = $pdo->prepare("UPDATE orders SET nonce = 'viewed' WHERE order_id = :order_id");
                $updateStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
                $updateStmt->execute();

                // Проверяем, было ли обновление успешным
                if ($updateStmt->rowCount() > 0) {
                    echo 'success';
                } else {
                    echo 'Ошибка: Не удалось обновить статус заказа.';
                }
            } else {
                echo 'Ошибка: Заказ с указанным ID не найден.';
            }
        } catch (PDOException $e) {
            // Логируем ошибку и возвращаем сообщение
            error_log("Ошибка при обновлении статуса заказа: " . $e->getMessage());
            echo 'Ошибка при обновлении статуса заказа.';
        }
    } else {
        echo 'Ошибка: Неверный идентификатор заказа.';
    }
} else {
    echo 'Ошибка: Неверный запрос.';
}
?>
