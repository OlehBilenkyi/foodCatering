<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Неавторизованный доступ']);
    exit();
}

$user_email = $_SESSION['user_email'];
$data = json_decode(file_get_contents("php://input"), true);

$items = $data['items'] ?? [];
$total_price = $data['total_price'] ?? 0;
$delivery_date = $data['delivery_date'] ?? '';

if (empty($items) || $total_price <= 0 || empty($delivery_date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные данные']);
    exit();
}

try {
    // Получаем ID пользователя
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = :email");
    $stmt->execute(['email' => $user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Пользователь не найден']);
        exit();
    }

    $customer_id = $user['id'];

    // Создаём заказ
    $stmt = $pdo->prepare("INSERT INTO orders (customer_id, total_price, status, created_at, delivery_date) 
                           VALUES (:customer_id, :total_price, 'pending', NOW(), :delivery_date)");
    $stmt->execute([
        'customer_id' => $customer_id,
        'total_price' => $total_price,
        'delivery_date' => $delivery_date
    ]);

    $order_id = $pdo->lastInsertId();

    // Добавляем товары в заказ
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (:order_id, :menu_item_id, :quantity, :price)");
    foreach ($items as $item) {
        $stmt->execute([
            'order_id' => $order_id,
            'menu_item_id' => $item['menu_item_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price']
        ]);
    }

    echo json_encode(['message' => 'Заказ создан', 'order_id' => $order_id]);
} catch (PDOException $e) {
    error_log("Ошибка создания заказа: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
