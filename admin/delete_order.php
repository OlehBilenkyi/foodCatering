<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['order_id'])) {
    parse_str(file_get_contents("php://input"), $data);
    $order_id = intval($_GET['order_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Заказ не найден']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка при удалении заказа']);
    }
}
?>
