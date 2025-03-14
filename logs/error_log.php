<?php
// Убедитесь, что метод запроса - POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем входные данные
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    // Проверка на наличие данных
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        // Логируем ошибку, если не удалось декодировать JSON
        $logEntry = date('Y-m-d H:i:s') . ' - Ошибка декодирования JSON: ' . json_last_error_msg() . PHP_EOL;
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ошибка декодирования JSON.']);
        exit();
    }

    // Проверяем, содержит ли данные сообщение об ошибке
    if (isset($data['message']) && is_string($data['message'])) {
        $errorMessage = $data['message'];

        // Если есть информация об ошибке, добавляем её к сообщению
        if (isset($data['error']) && is_string($data['error'])) {
            $errorMessage .= ' | Details: ' . $data['error'];
        }

        // Добавляем дополнительные данные, такие как user-agent, IP-адрес и URI для лучшего анализа
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Неизвестный User-Agent';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Неизвестный IP';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'Неизвестный URI';
        $errorMessage .= ' | User-Agent: ' . $userAgent . ' | IP: ' . $ipAddress . ' | URI: ' . $requestUri;

        // Форматируем строку для лога с текущей датой и временем
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $errorMessage . PHP_EOL;

        // Записываем сообщение в лог-файл
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Возвращаем ответ клиенту
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Сообщение об ошибке записано.']);
    } else {
        // Логируем ошибку, если сообщение отсутствует или некорректное
        $logEntry = date('Y-m-d H:i:s') . ' - Некорректный запрос: отсутствует сообщение об ошибке или некорректный формат.' . PHP_EOL;
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Возвращаем ответ клиенту
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Некорректный запрос: отсутствует сообщение об ошибке или некорректный формат.']);
    }
} else {
    // Если запрос не POST, логируем это как ошибку
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Неизвестный User-Agent';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Неизвестный IP';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'Неизвестный URI';
    $logEntry = date('Y-m-d H:i:s') . ' - Некорректный метод запроса: ожидается POST. | User-Agent: ' . $userAgent . ' | IP: ' . $ipAddress . ' | URI: ' . $requestUri . PHP_EOL;
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log';
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // Возвращаем ответ клиенту
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Некорректный метод запроса: ожидается POST.']);
}
?>
