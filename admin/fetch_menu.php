<?php
// fetch_menu.php

// Подключаем файл с настройками подключения к базе данных
include $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Массив допустимых категорий
$validCategories = ['Sniadanie', 'Obiad', 'Przekaska', 'Kolacja'];


try {
    // Проверяем, что запрос был методом GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method.', 405);
    }

    // Проверяем, что параметр "category" передан
    if (!isset($_GET['category']) || empty(trim($_GET['category']))) {
        throw new Exception('Missing or empty category parameter.', 400);
    }

    // Получаем категорию из параметров запроса
$category = urldecode(trim($_GET['category'])); // Раскодируем параметр категории
if (!in_array($category, $validCategories, true)) {
    throw new Exception('Invalid category parameter.', 400);
}


    // Подготовка SQL-запроса с использованием подготовленных выражений
    $stmt = $pdo->prepare("SELECT * FROM menu_options WHERE category = :category");
    $stmt->bindParam(':category', $category, PDO::PARAM_STR);
    $stmt->execute();

    // Извлекаем результат
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Если данные не найдены, возвращаем пустой массив
    if (empty($dishes)) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    // Отправка данных в формате JSON
    header('Content-Type: application/json');
    echo json_encode($dishes);
} catch (Exception $e) {
    // Логирование ошибки
    error_log('Error in fetch_menu.php: ' . $e->getMessage());

    // Отправка ошибки на клиент
    $httpCode = $e->getCode() ?: 500;
    http_response_code($httpCode);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
