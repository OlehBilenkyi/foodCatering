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

$address_line = trim($data['address_line'] ?? '');
$city = trim($data['city'] ?? '');
$postal_code = trim($data['postal_code'] ?? '');
$country = trim($data['country'] ?? '');

if (!$address_line || !$city || !$postal_code || !$country) {
    http_response_code(400);
    echo json_encode(['error' => 'Все поля обязательны']);
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

    // Проверяем, есть ли у пользователя адреса
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_addresses WHERE customer_id = :customer_id");
    $stmt->execute(['customer_id' => $customer_id]);
    $has_addresses = $stmt->fetchColumn() > 0;

    // Добавляем адрес
    $stmt = $pdo->prepare("INSERT INTO customer_addresses (customer_id, address_line, city, postal_code, country, is_default) 
                           VALUES (:customer_id, :address_line, :city, :postal_code, :country, :is_default)");
    $stmt->execute([
        'customer_id' => $customer_id,
        'address_line' => $address_line,
        'city' => $city,
        'postal_code' => $postal_code,
        'country' => $country,
        'is_default' => $has_addresses ? 0 : 1 // Если это первый адрес — делаем его основным
    ]);

    echo json_encode(['message' => 'Адрес добавлен']);
} catch (PDOException $e) {
    error_log("Ошибка добавления адреса: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>
