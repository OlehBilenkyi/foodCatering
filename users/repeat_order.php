<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

if (!isset($_SESSION['user_email'])) {
    die('Ошибка: Требуется авторизация.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['order_id'])) {
    die('Ошибка: Некорректный запрос.');
}

$order_id = $_POST['order_id'];
$user_email = $_SESSION['user_email'];

try {
    // Получаем оригинальный заказ
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id AND customer_email = :email");
    $stmt->execute(['order_id' => $order_id, 'email' => $user_email]);
    $order = $stmt->fetch();

    if (!$order) {
        die('Ошибка: Заказ не найден.');
    }

    // Клонируем заказ
    $stmt = $pdo->prepare("
        INSERT INTO orders (customer_fullname, customer_email, customer_phone, customer_street, customer_house_number, 
            customer_apartment, customer_floor, customer_klatka, customer_gate_code, customer_notes, 
            total_price, status, created_at)
        VALUES (:fullname, :email, :phone, :street, :house, :apartment, :floor, :klatka, :gate_code, :notes, :price, 'pending', NOW())
    ");

    $stmt->execute([
        'fullname' => $order['customer_fullname'],
        'email' => $order['customer_email'],
        'phone' => $order['customer_phone'],
        'street' => $order['customer_street'],
        'house' => $order['customer_house_number'],
        'apartment' => $order['customer_apartment'],
        'floor' => $order['customer_floor'],
        'klatka' => $order['customer_klatka'],
        'gate_code' => $order['customer_gate_code'],
        'notes' => $order['customer_notes'],
        'price' => $order['total_price']
    ]);

    $new_order_id = $pdo->lastInsertId();

    // Копируем пакеты заказа
    $stmt = $pdo->prepare("SELECT * FROM order_packages WHERE order_id = :order_id");
    $stmt->execute(['order_id' => $order_id]);
    $packages = $stmt->fetchAll();

    foreach ($packages as $package) {
        $stmt = $pdo->prepare("
            INSERT INTO order_packages (order_id, calories, quantity)
            VALUES (:order_id, :calories, :quantity)
        ");
        $stmt->execute([
            'order_id' => $new_order_id,
            'calories' => $package['calories'],
            'quantity' => $package['quantity']
        ]);
    }

    header("Location: /orders/$new_order_id");
    exit();
} catch (PDOException $e) {
    error_log("Ошибка повтора заказа: " . $e->getMessage());
    die('Ошибка: Не удалось повторить заказ.');
}
