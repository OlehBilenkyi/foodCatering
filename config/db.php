<?php
// Включаем автозагрузку библиотек через Composer
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Включаем логирование ошибок ДО запуска сессии
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

// Устанавливаем увеличенное время жизни сессии до 4 часов (ДО запуска сессии)
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 14400); // Только если сессия не активна
    session_start(); // Запуск сессии, если она еще не была запущена
}

use Dotenv\Dotenv;

// Проверяем наличие файла .env
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env')) {
    error_log("Файл .env не найден.");
    die('Произошла внутренняя ошибка сервера. Пожалуйста, обратитесь в службу поддержки.');
}

// Загружаем переменные окружения из файла .env
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Проверяем режим отладки
$debugMode = $_ENV['DEBUG_MODE'] ?? 'false';

// Параметры подключения к базе данных через переменные окружения
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db = $_ENV['DB_NAME'] ?? 'u100222829_foodcasecateri';
$user = $_ENV['DB_USER'] ?? 'u100222829_fc';
$pass = $_ENV['DB_PASSWORD'] ?? 'S_hevcenka1993';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// DSN (Data Source Name) для подключения к базе данных
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Включение режима обработки ошибок с выбросом исключений
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Установка режима выборки по умолчанию для ассоциативных массивов
    PDO::ATTR_EMULATE_PREPARES => false, // Отключение эмуляции подготовленных выражений для повышения безопасности
    PDO::ATTR_PERSISTENT => false // Отключение постоянного подключения для предотвращения превышения лимита соединений
];

try {
    // Подключение к базе данных
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Логируем успешное подключение только в режиме отладки
    if ($debugMode === 'true') {
        error_log("Успешное подключение к базе данных для пользователя $user.");
    }
} catch (\PDOException $e) {
    // Логирование ошибки и вывод понятного сообщения для пользователя
    error_log("Ошибка подключения к базе данных: " . $e->getMessage());
    http_response_code(500);

    // Проверяем существование файла error_page.php перед подключением
    $errorPagePath = $_SERVER['DOCUMENT_ROOT'] . '/includes/error_page.php';
    if (file_exists($errorPagePath)) {
        include($errorPagePath); // Страница ошибки
    } else {
        echo '<h1>Произошла ошибка</h1>';
        echo '<p>Мы не можем подключиться к базе данных в данный момент. Пожалуйста, попробуйте позже.</p>';
    }
    
    exit();
}
?>
