<?php
// Подключение к базе данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Инициализация сессии, если она ещё не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Включение логгирования ошибок
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);

// Проверка, авторизован ли пользователь как администратор
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/admin.php');
    exit();
}

// Проверка CSRF-токена
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Ошибка CSRF токена.');
}

// Проверка корректности переданного ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Неверный ID записи.');
}

$id = intval($_GET['id']);

try {
    // Подготовка и выполнение запроса на удаление записи
    $sql = "DELETE FROM menu_options WHERE menu_options_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);

    // После успешного удаления перенаправляем пользователя на страницу с перечнем записей (можно добавить параметр сообщения)
    header('Location: /admin/edit_menu_do_wyboru.php?message=deleted');

    exit();
} catch (Exception $e) {
    // Запись ошибки в лог и вывод сообщения об ошибке
    error_log($e->getMessage());
    die('Ошибка при удалении записи.');
}
