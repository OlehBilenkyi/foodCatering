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
$orderQuery = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id AND customer_email = :email");
$orderQuery->execute(['order_id' => $orderId, 'email' => $userEmail]);
$order = $orderQuery->fetch(PDO::FETCH_ASSOC);

// Проверка, если заказ существует
if (!$order) {
    echo "Заказ не найден.";
    exit();
}

// Если форма отправлена, обновляем заказ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Обновляем основной заказ
    $updateOrderQuery = $pdo->prepare("
        UPDATE orders SET
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
    <title>Редактировать стандартное меню</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <h1>Редактировать стандартное меню</h1>
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
        <input type="submit" value="Обновить заказ">
    </form>
</body>
</html>
