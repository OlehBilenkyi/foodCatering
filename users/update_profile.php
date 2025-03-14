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

$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$phone = trim($data['phone'] ?? '');

if (!$first_name || !$last_name || !$phone) {
    http_response_code(400);
    echo json_encode(['error' => 'Все поля обязательны']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE customers SET first_name = :first_name, last_name = :last_name, phone = :phone WHERE email = :email");
    $stmt->execute([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'email' => $user_email
    ]);

    echo json_encode(['message' => 'Профиль обновлён']);
} catch (PDOException $e) {
    error_log("Ошибка обновления профиля: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
