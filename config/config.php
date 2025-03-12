<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php'; // Подключение autoload от Composer

use Dotenv\Dotenv;

// Загружаем переменные окружения из файла .env
$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Конфигурация базы данных
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);
define('DB_CHARSET', $_ENV['DB_CHARSET']);

// Конфигурация Google reCAPTCHA
define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY']);
define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY']);

// Конфигурация SMTP
define('SMTP_HOST', $_ENV['SMTP_HOST']);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME']);
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD']);
define('SMTP_PORT', $_ENV['SMTP_PORT']);
define('SMTP_SECURE', $_ENV['SMTP_SECURE']);

// Подключение к базе данных через PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (PDOException $e) {
    error_log('Ошибка подключения к базе данных: ' . $e->getMessage());
    die('Произошла ошибка при подключении к базе данных.');
}
?>