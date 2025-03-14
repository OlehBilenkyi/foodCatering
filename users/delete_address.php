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
$address_id = $data['address_id'] ?? 0;

if (!$address_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Не указан адрес']);
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

    // Проверяем, принадлежит ли адрес пользователю
    $stmt = $pdo->prepare("SELECT is_default FROM customer_addresses WHERE id = :id AND customer_id = :customer_id");
    $stmt->execute(['id' => $address_id, 'customer_id' => $customer_id]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {
        http_response_code(404);
        echo json_encode(['error' => 'Адрес не найден']);
        exit();
    }

    // Удаляем адрес
    $stmt = $pdo->prepare("DELETE FROM customer_addresses WHERE id = :id");
    $stmt->execute(['id' => $address_id]);

    // Если это был основной адрес — выбираем другой
    if ($address['is_default']) {
        $stmt = $pdo->prepare("UPDATE customer_addresses SET is_default = 1 WHERE customer_id = :customer_id LIMIT 1");
        $stmt->execute(['customer_id' => $customer_id]);
    }

    echo json_encode(['message' => 'Адрес удалён']);
} catch (PDOException $e) {
    error_log("Ошибка удаления адреса: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
