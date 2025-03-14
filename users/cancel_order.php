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
$order_id = $data['order_id'] ?? 0;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Не указан ID заказа']);
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

    // Проверяем, принадлежит ли заказ пользователю и его статус
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :order_id AND customer_id = :customer_id");
    $stmt->execute(['order_id' => $order_id, 'customer_id' => $customer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Заказ не найден']);
        exit();
    }

    if ($order['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['error' => 'Заказ нельзя отменить']);
        exit();
    }

    // Обновляем статус
    $stmt = $pdo->prepare("UPDATE orders SET status = 'canceled' WHERE id = :order_id");
    $stmt->execute(['order_id' => $order_id]);

    echo json_encode(['message' => 'Заказ отменён']);
} catch (PDOException $e) {
    error_log("Ошибка отмены заказа: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
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
    // Проверяем, есть ли такой заказ
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = :order_id AND customer_email = :email");
    $stmt->execute(['order_id' => $order_id, 'email' => $user_email]);
    $order = $stmt->fetch();

    if (!$order) {
        die('Ошибка: Заказ не найден.');
    }

    if ($order['status'] === 'отменён' || $order['status'] === 'доставлен') {
        die('Ошибка: Этот заказ уже нельзя отменить.');
    }

    // Обновляем статус
    $stmt = $pdo->prepare("UPDATE orders SET status = 'отменён' WHERE order_id = :order_id AND customer_email = :email");
    $stmt->execute(['order_id' => $order_id, 'email' => $user_email]);

    header("Location: /orders/$order_id");
    exit();
} catch (PDOException $e) {
    error_log("Ошибка отмены заказа: " . $e->getMessage());
    die('Ошибка: Не удалось отменить заказ.');
}
