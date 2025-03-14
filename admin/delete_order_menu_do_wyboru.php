<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

if (!$pdo) {
    die("Ошибка подключения к базе данных");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if ($order_id <= 0) {
        die("Неверный ID заказа");
    }

    try {
        $pdo->beginTransaction();

        // Получаем все order_day_id для данного заказа
        $stmtDays = $pdo->prepare("SELECT order_day_id FROM customer_menu_order_days WHERE order_id = :order_id");
        $stmtDays->execute([':order_id' => $order_id]);
        $days = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

        foreach ($days as $day) {
            $stmtDeleteItems = $pdo->prepare("DELETE FROM customer_menu_order_items WHERE order_day_id = :order_day_id");
            $stmtDeleteItems->execute([':order_day_id' => $day['order_day_id']]);
        }

        // Удаляем записи о днях заказа
        $stmtDeleteDays = $pdo->prepare("DELETE FROM customer_menu_order_days WHERE order_id = :order_id");
        $stmtDeleteDays->execute([':order_id' => $order_id]);

        // Удаляем основной заказ
        $stmtDeleteOrder = $pdo->prepare("DELETE FROM customer_menu_orders WHERE order_id = :order_id");
        $stmtDeleteOrder->execute([':order_id' => $order_id]);

        $pdo->commit();
        echo "Заказ успешно удалён";  // Отправляем текстовый ответ
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Ошибка при удалении заказа: " . $e->getMessage();
    }
} else {
    echo "Неверный метод запроса";
}
?>
