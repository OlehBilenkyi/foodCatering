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

$old_password = $data['old_password'] ?? '';
$new_password = $data['new_password'] ?? '';

if (!$old_password || !$new_password || strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные данные']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM customers WHERE email = :email");
    $stmt->execute(['email' => $user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($old_password, $user['password_hash'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Старый пароль неверный']);
        exit();
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE customers SET password_hash = :new_hash WHERE email = :email");
    $stmt->execute(['new_hash' => $new_hash, 'email' => $user_email]);

    echo json_encode(['message' => 'Пароль изменён']);
} catch (PDOException $e) {
    error_log("Ошибка смены пароля: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
