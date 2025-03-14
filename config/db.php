<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\Dotenv\Dotenv as SymfonyDotenv;

// Включаем логирование ошибок ДО запуска сессии
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);

// Увеличиваем время жизни сессии до 4 часов
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 14400);
    session_start();
}

// Проверяем, есть ли файл .env
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env')) {
    error_log("❌ Файл .env не найден.");
    die('Произошла ошибка сервера. Обратитесь в поддержку.');
}

// Загружаем переменные окружения
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Проверяем режим отладки
$debugMode = filter_var($_ENV['DEBUG_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Функция безопасного получения переменной окружения
function getEnvVar(string $key): string {
    if (empty($_ENV[$key])) {
        error_log("⚠️ Отсутствует переменная окружения: $key");
        throw new Exception("Ошибка конфигурации.");
    }
    return $_ENV[$key];
}

// Получаем данные для подключения
$host    = getEnvVar('DB_HOST');
$db      = getEnvVar('DB_NAME');
$user    = getEnvVar('DB_USER');
$pass    = getEnvVar('DB_PASSWORD');
$charset = getEnvVar('DB_CHARSET');

// Формируем DSN
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES 'utf8mb4'");

    if ($debugMode) {
        error_log("✅ Успешное подключение к БД ($db) пользователем $user.");
    }
} catch (PDOException $e) {
    error_log("❌ Ошибка БД: " . $e->getMessage());
    http_response_code(500);

    $errorPagePath = $_SERVER['DOCUMENT_ROOT'] . '/includes/error_page.php';
    if (file_exists($errorPagePath)) {
        include($errorPagePath);
    } else {
        echo '<h1>Произошла ошибка</h1>';
        echo '<p>Сейчас невозможно подключиться к базе. Попробуйте позже.</p>';
    }
    exit();
}
?>
