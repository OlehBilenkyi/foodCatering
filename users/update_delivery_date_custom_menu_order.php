<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

header("Content-Type: application/json");

// Проверка CSRF
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(["success" => false, "error" => "Błąd CSRF"]);
    exit;
}

// Проверка аутентификации пользователя
if (!isset($_SESSION['user_email'])) {
    echo json_encode(["success" => false, "error" => "Brak autoryzacji"]);
    exit;
}

// Проверка входных данных
$order_day_id = $data['order_day_id'] ?? null;
$new_delivery_date = $data['new_delivery_date'] ?? null;

if (!$order_day_id || !$new_delivery_date) {
    echo json_encode(["success" => false, "error" => "Brak wymaganych danych"]);
    exit;
}

// Проверка формата даты (дд-мм-гггг)
$dateObj = DateTime::createFromFormat('d-m-Y', $new_delivery_date);
if (!$dateObj) {
    echo json_encode(["success" => false, "error" => "Nieprawidłowy format daty"]);
    exit;
}

// Обновление даты в БД
try {
    $stmt = $pdo->prepare("UPDATE customer_menu_order_days SET delivery_date = ? WHERE order_day_id = ?");
    $stmt->execute([$dateObj->format('Y-m-d'), $order_day_id]);

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    error_log("Błąd aktualizacji daty dostawy: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Błąd bazy danych"]);
}
?>
