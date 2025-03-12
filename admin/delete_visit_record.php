<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверяем авторизацию
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit(json_encode(['error' => 'У вас нет прав для выполнения этого действия.']));
}

// Получаем данные из POST запроса
$data = json_decode(file_get_contents("php://input"), true);
$ipAddress = $data['ip_address'] ?? null;
$visitDate = $data['visit_date'] ?? null;

// Проверяем наличие IP-адреса и даты
if (!$ipAddress || !$visitDate) {
    http_response_code(400);
    exit(json_encode(['error' => 'Некорректные данные для удаления записи.']));
}

// Выполняем удаление записей из базы данных
try {
    $stmt = $pdo->prepare("DELETE FROM page_visits WHERE ip_address = ? AND DATE(visit_date) = ?");
    $stmt->execute([$ipAddress, $visitDate]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => 'Записи успешно удалены']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Записи не найдены']);
    }
} catch (Exception $e) {
    error_log("Ошибка удаления записи: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка при удалении записи']);
}
?>