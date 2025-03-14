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
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, phone FROM customers WHERE email = :email");
    $stmt->execute(['email' => $user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Пользователь не найден']);
        exit();
    }

    echo json_encode($user);
} catch (PDOException $e) {
    error_log("Ошибка получения профиля: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
