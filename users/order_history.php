<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: /auth/login");
    exit();
}

$user_email = $_SESSION['user_email'];

// Получаем заказы пользователя
$filter = $_GET['status'] ?? 'all';

$query = "SELECT order_id, total_price, status, created_at FROM orders WHERE customer_email = :email";
$params = ['email' => $user_email];

if ($filter !== 'all') {
    $query .= " AND status = :status";
    $params['status'] = $filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История заказов</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>📜 История заказов</h1>
    
    <p>Фильтр по статусу:</p>
    <form method="get">
        <select name="status" onchange="this.form.submit()">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Все</option>
            <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Ожидает</option>
            <option value="оплачен" <?= $filter === 'оплачен' ? 'selected' : '' ?>>Оплаченные</option>
            <option value="доставлен" <?= $filter === 'доставлен' ? 'selected' : '' ?>>Доставленные</option>
            <option value="отменён" <?= $filter === 'отменён' ? 'selected' : '' ?>>Отменённые</option>
        </select>
    </form>

    <ul>
        <?php if (count($orders) > 0): ?>
            <?php foreach ($orders as $order): ?>
                <li>
                    <a href="/orders/<?= $order['order_id'] ?>">
                        Заказ #<?= $order['order_id'] ?> - <?= $order['total_price'] ?> ₽ - <?= $order['status'] ?> - <?= $order['created_at'] ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Заказов пока нет.</p>
        <?php endif; ?>
    </ul>
    
    <p><a href="/dashboard">⬅ Вернуться в кабинет</a></p>
</body>
</html>
