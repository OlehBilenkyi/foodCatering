<?php
// Включаем строго типизированные объявления для улучшения безопасности и надежности
declare(strict_types=1);

// Подключаем файл с настройками базы данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Устанавливаем тип контента для возврата данных в формате JSON
header('Content-Type: application/json; charset=UTF-8');

// Ограничиваем допустимые методы запроса только методом GET для обеспечения безопасности
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    // Возвращаем ошибку с кодом 405 - Method Not Allowed
    echo json_encode(['error' => 'Некорректный метод запроса. Ожидается GET.']);
    http_response_code(405);
    exit();
}

try {
    // Подготовка запроса для получения количества заказов по датам
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as order_date, COUNT(*) as order_count
        FROM orders
        WHERE created_at IS NOT NULL
        GROUP BY order_date
        ORDER BY order_date ASC
    ");

    // Выполнение запроса
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Проверка наличия данных
    if ($orders && count($orders) > 0) {
        echo json_encode(['orders' => $orders], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        http_response_code(200); // OK
    } else {
        echo json_encode(['error' => 'Нет данных по заказам'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        http_response_code(404); // Not Found
    }
} catch (PDOException $e) {
    // Запись ошибки в лог файл для обеспечения безопасности и возможности отладки
    error_log('Ошибка при получении данных о заказах: ' . $e->getMessage(), 3, $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
    // Возвращаем пользователю общее сообщение об ошибке
    echo json_encode(['error' => 'Ошибка при получении данных о заказах. Пожалуйста, попробуйте позже.'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    http_response_code(500); // Internal Server Error
} catch (JsonException $je) {
    // Обработка ошибок при кодировании JSON
    error_log('Ошибка при кодировании JSON: ' . $je->getMessage(), 3, $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
    echo json_encode(['error' => 'Ошибка при обработке данных. Пожалуйста, попробуйте позже.'], JSON_UNESCAPED_UNICODE);
    http_response_code(500); // Internal Server Error
}
?>
