<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: /auth/login");
    exit();
}

// Фильтруем входные данные
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    die('Ошибка: Неверный идентификатор заказа.');
}

$user_email = $_SESSION['user_email'];

try {
    // Получаем детали заказа
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE order_id = :order_id AND customer_email = :email
    ");
    $stmt->execute(['order_id' => $order_id, 'email' => $user_email]);
    $order = $stmt->fetch();

    if (!$order) {
        die('Ошибка: Заказ не найден или у вас нет доступа.');
    }

    // Получаем пакеты заказа
    $stmt = $pdo->prepare("
        SELECT * FROM order_packages WHERE order_id = :order_id
    ");
    $stmt->execute(['order_id' => $order_id]);
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка загрузки заказа #{$order_id}: " . $e->getMessage());
    die('Ошибка при загрузке данных. Попробуйте позже.');
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <h1>Заказ #<?php echo $order_id; ?></h1>

    <p><strong>Статус:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
    <p><strong>Сумма:</strong> <?php echo number_format($order['total_price'], 2, ',', ' '); ?> ₽</p>
    <p><strong>Дата заказа:</strong> <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>

    <h2>Пакеты заказа</h2>
    <?php if (empty($packages)): ?>
        <p>Нет информации о пакетах.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($packages as $package): ?>
                <li>Калории: <?php echo $package['calories']; ?>, Количество: <?php echo $package['quantity']; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    
    <h2>Изменить статус заказа</h2>
    <form action="/orders/update_order_status.php" method="post">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <select name="status">
            <option value="обработан">Обработан</option>
            <option value="в пути">В пути</option>
            <option value="доставлен">Доставлен</option>
            <option value="отменён">Отменён</option>
        </select>
        <button type="submit">Обновить</button>
    </form>
    
    <p>
    <form action="/orders/repeat" method="post">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <button type="submit">🔄 Повторить заказ</button>
    </form>
</p>

<p>
    <?php if ($order['status'] !== 'отменён' && $order['status'] !== 'доставлен'): ?>
        <form action="/orders/cancel" method="post" onsubmit="return confirm('Вы уверены? Отменённый заказ нельзя восстановить!');">
            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
            <button type="submit" style="color: red;">❌ Отменить заказ</button>
        </form>
    <?php else: ?>
        <p>Этот заказ уже нельзя отменить.</p>
    <?php endif; ?>
</p>


    <p><a href="/orders">⬅ Назад к заказам</a></p>
</body>
</html>


