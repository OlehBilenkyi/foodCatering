<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_email'])) {
    header("Location: /auth/login");
    exit();
}

$user_email = $_SESSION['user_email'];

try {
    // Получаем заказы пользователя по email
    $stmt = $pdo->prepare("
        SELECT order_id, total_price, status, created_at 
        FROM orders 
        WHERE customer_email = :email 
        ORDER BY created_at DESC
    ");
    $stmt->execute(['email' => $user_email]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка загрузки заказов: " . $e->getMessage());
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заказы</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Мои заказы</h1>
    
    <?php if (empty($orders)): ?>
        <p>У вас пока нет заказов.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID заказа</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Детали</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td><?php echo number_format($order['total_price'], 2, ',', ' '); ?> ZL</td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
                        <td><a href="/users/orders.php<?php echo $order['order_id']; ?>">🔍 Подробнее</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p><a href="/dashboard">⬅ Назад в кабинет</a></p>
</body>
</html>
