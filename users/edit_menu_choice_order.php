<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверка, что пользователь авторизован
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

$userEmail = $_SESSION['user_email'];  // Получаем email из сессии
$orderId = isset($_GET['order_id']) ? $_GET['order_id'] : null;  // Защищаем от отсутствия параметра order_id

// Проверка, что order_id передан
if (!$orderId) {
    echo "Ошибка: Не передан идентификатор заказа.";
    exit();
}

// Получаем информацию о заказе
$orderQuery = $pdo->prepare("SELECT * FROM customer_menu_orders WHERE order_id = :order_id AND customer_email = :email");
$orderQuery->execute(['order_id' => $orderId, 'email' => $userEmail]);
$order = $orderQuery->fetch(PDO::FETCH_ASSOC);

// Проверка, если заказ существует
if (!$order) {
    echo "Заказ не найден.";
    exit();
}

// Получаем данные о днях и позициях
$menuDaysQuery = $pdo->prepare("SELECT * FROM customer_menu_order_days WHERE order_id = :order_id");
$menuDaysQuery->execute(['order_id' => $orderId]);
$menuDays = $menuDaysQuery->fetchAll(PDO::FETCH_ASSOC);

foreach ($menuDays as $menuDay) {
    $menuItemsQuery = $pdo->prepare("SELECT * FROM customer_menu_order_items WHERE order_day_id = :order_day_id");
    $menuItemsQuery->execute(['order_day_id' => $menuDay['order_day_id']]);
    $menuItems = $menuItemsQuery->fetchAll(PDO::FETCH_ASSOC);
}

// Если форма отправлена, обновляем заказ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Обновляем основной заказ
    $updateOrderQuery = $pdo->prepare("
        UPDATE customer_menu_orders SET
            customer_fullname = :fullname,
            customer_phone = :phone,
            customer_street = :street,
            customer_house_number = :house_number,
            customer_apartment = :apartment,
            customer_floor = :floor,
            customer_klatka = :klatka,
            customer_gate_code = :gate_code,
            customer_notes = :notes,
            total_price = :total_price
        WHERE order_id = :order_id
    ");

    $updateOrderQuery->execute([
        'fullname' => $_POST['fullname'],
        'phone' => $_POST['phone'],
        'street' => $_POST['street'],
        'house_number' => $_POST['house_number'],
        'apartment' => $_POST['apartment'],
        'floor' => $_POST['floor'],
        'klatka' => $_POST['klatka'],
        'gate_code' => $_POST['gate_code'],
        'notes' => $_POST['notes'],
        'total_price' => $_POST['total_price'],
        'order_id' => $orderId
    ]);

    // Обновляем дни доставки
    foreach ($_POST['delivery_dates'] as $dayId => $deliveryDate) {
        $updateDayQuery = $pdo->prepare("UPDATE customer_menu_order_days SET delivery_date = :delivery_date WHERE order_day_id = :order_day_id");
        $updateDayQuery->execute([
            'delivery_date' => $deliveryDate,
            'order_day_id' => $dayId
        ]);
    }

    // Обновляем блюда
    foreach ($_POST['menu_items'] as $itemId => $itemData) {
        $updateItemQuery = $pdo->prepare("
            UPDATE customer_menu_order_items SET
                category = :category,
                dish_name = :dish_name,
                weight = :weight,
                price = :price
            WHERE order_day_id = :order_day_id AND menu_options_id = :menu_options_id
        ");
        $updateItemQuery->execute([
            'category' => $itemData['category'],
            'dish_name' => $itemData['dish_name'],
            'weight' => $itemData['weight'],
            'price' => $itemData['price'],
            'order_day_id' => $itemData['order_day_id'],
            'menu_options_id' => $itemData['menu_options_id']
        ]);
    }

    // Сообщаем пользователю об успешном обновлении
    echo "Заказ успешно обновлен!";
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать меню по выбору</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <h1>Редактировать меню по выбору</h1>
    </header>

    <!-- Форма для редактирования заказа -->
    <form method="POST">
        <input type="text" name="fullname" value="<?= htmlspecialchars($order['customer_fullname']) ?>">
        <input type="text" name="phone" value="<?= htmlspecialchars($order['customer_phone']) ?>">
        <input type="text" name="street" value="<?= htmlspecialchars($order['customer_street']) ?>">
        <input type="text" name="house_number" value="<?= htmlspecialchars($order['customer_house_number']) ?>">
        <input type="text" name="apartment" value="<?= htmlspecialchars($order['customer_apartment']) ?>">
        <input type="text" name="floor" value="<?= htmlspecialchars($order['customer_floor']) ?>">
        <input type="text" name="klatka" value="<?= htmlspecialchars($order['customer_klatka']) ?>">
        <input type="text" name="gate_code" value="<?= htmlspecialchars($order['customer_gate_code']) ?>">
        <input type="text" name="notes" value="<?= htmlspecialchars($order['customer_notes']) ?>">
        <input type="text" name="total_price" value="<?= htmlspecialchars($order['total_price']) ?>">

        <!-- Пример для дней -->
        <?php foreach ($menuDays as $day): ?>
            <input type="date" name="delivery_dates[<?= $day['order_day_id'] ?>]" value="<?= $day['delivery_date'] ?>">
        <?php endforeach; ?>

        <!-- Пример для блюд -->
        <?php foreach ($menuItems as $item): ?>
            <input type="text" name="menu_items[<?= $item['menu_options_id'] ?>][category]" value="<?= $item['category'] ?>">
            <input type="text" name="menu_items[<?= $item['menu_options_id'] ?>][dish_name]" value="<?= $item['dish_name'] ?>">
            <input type="number" name="menu_items[<?= $item['menu_options_id'] ?>][weight]" value="<?= $item['weight'] ?>">
            <input type="number" name="menu_items[<?= $item['menu_options_id'] ?>][price]" value="<?= $item['price'] ?>">
        <?php endforeach; ?>

        <input type="submit" value="Обновить заказ">
    </form>
</body>
</html>
