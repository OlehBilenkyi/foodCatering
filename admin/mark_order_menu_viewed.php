<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

if (isset($_POST['order_id'])) {
    $orderId = intval($_POST['order_id']);
    $sql = "UPDATE customer_menu_orders SET nonce_menu = 'viewed' WHERE order_id = :order_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'error';
    }
} else {
    echo 'error';
}
?>