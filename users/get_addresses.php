<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Неавторизованный доступ']);
    exit();
}

$user_email = $_SESSION['user_email'];

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

    // Получаем адреса
    $stmt = $pdo->prepare("SELECT id, address_line, city, postal_code, country, is_default FROM customer_addresses WHERE customer_id = :customer_id");
    $stmt->execute(['customer_id' => $user['id']]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($addresses);
} catch (PDOException $e) {
    error_log("Ошибка получения адресов: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
