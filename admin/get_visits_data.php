<?php
session_start();

// Подключаемся к базе данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; 

// Проверяем, что пользователь авторизован как администратор
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("Ошибка авторизации: Попытка неавторизованного доступа к get_visits_data.php");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Пользователь не авторизован']);
    exit();
}

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); 

try {
    // Используем безопасные SQL-запросы с COALESCE для замены NULL значений
    $stmt = $pdo->query("SELECT ip_address, DATE(visit_date) AS visit_date, COUNT(*) AS visit_count,
                         COALESCE(country, 'Неизвестно') AS country,
                         COALESCE(city, 'Неизвестно') AS city
                         FROM page_visits
                         GROUP BY ip_address, DATE(visit_date)
                         ORDER BY visit_date ASC");
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем общее количество посещений с каждого уникального IP-адреса
    $stmt_total_visits = $pdo->query("SELECT ip_address, COUNT(*) AS total_visits,
                                      COALESCE(country, 'Неизвестно') AS country,
                                      COALESCE(city, 'Неизвестно') AS city
                                      FROM page_visits
                                      GROUP BY ip_address
                                      ORDER BY total_visits DESC");
    $total_visits = $stmt_total_visits->fetchAll(PDO::FETCH_ASSOC);

    // Если данных нет, отправляем соответствующий ответ
    if (empty($visits) && empty($total_visits)) {
        http_response_code(204);
        echo json_encode(['message' => 'Нет данных для отображения']);
        exit();
    }

    $result = [
        'visits' => $visits,
        'total_visits' => $total_visits
    ];

    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    error_log("Ошибка получения данных посещений: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка при получении данных посещений']);
}
?>
