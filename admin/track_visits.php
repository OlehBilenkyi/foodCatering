<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; 

// Функция для получения IP-адреса клиента
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]); // Берем первый IP-адрес из списка, так как их может быть несколько
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    } else {
        return null; // Возвращаем null, если IP не удалось получить
    }
}

// Обновленная функция для получения информации о геопозиции с использованием IP-API
function getGeoInfo($ip) {
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
        error_log("Ошибка: Неверный или отсутствующий IP-адрес: $ip");
        return ['country' => 'Неизвестно', 'city' => 'Неизвестно'];
    }

    // Используем cURL вместо file_get_contents для большей гибкости и безопасности
    $url = "http://ip-api.com/json/{$ip}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Устанавливаем таймаут запроса в 2 секунды
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("Ошибка: Не удалось получить данные о геопозиции для IP: $ip. Ошибка cURL: " . curl_error($ch));
        curl_close($ch);
        return ['country' => 'Неизвестно', 'city' => 'Неизвестно'];
    }

    curl_close($ch);

    $geoData = json_decode($response, true);
    if ($geoData['status'] !== 'success') {
        error_log("Ошибка API: Невозможно получить данные о геопозиции для IP: $ip");
        return ['country' => 'Неизвестно', 'city' => 'Неизвестно'];
    }

    return [
        'country' => $geoData['country'] ?? 'Неизвестно',
        'city' => $geoData['city'] ?? 'Неизвестно',
    ];
}

try {
    // Получаем IP пользователя
    $ip = getUserIP();

    // Проверяем, что IP корректный
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new Exception("Некорректный IP-адрес: $ip");
    }

    // Получаем информацию о геопозиции на основе IP
    $geoInfo = getGeoInfo($ip);

    // Если удалось получить данные о местоположении
    $country = $geoInfo['country'];
    $city = $geoInfo['city'];

    // Подготовленное выражение для записи посещения в базу данных
    $stmt = $pdo->prepare("INSERT INTO page_visits (visit_date, ip_address, country, city) VALUES (NOW(), ?, ?, ?)");
    $stmt->execute([$ip, $country, $city]);

} catch (Exception $e) {
    error_log("Ошибка записи данных о посещении: " . $e->getMessage());
}
?>
