<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Исправленный путь к базе данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/functions.php'; // Подключаем функции проверки

// Проверка авторизации администратора
check_admin_auth();

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

// Проверка наличия корректного CSRF токена
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: /admin/admin_menu.php?status=csrf_error');
    exit();
}

// Проверка, есть ли ID, который нужно удалить, и фильтрация данных
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if ($id) {
    try {
        // Подготовка запроса на удаление записи
        $stmt = $pdo->prepare("DELETE FROM weekly_menu WHERE id = ?");
        $stmt->execute([$id]);

        // Проверяем, был ли успешно удалён элемент
        if ($stmt->rowCount() > 0) {
            // Логирование успешного удаления
            error_log('[INFO] Успешно удалена запись с ID: ' . $id);
            // Перенаправляем с сообщением об успешном удалении
            header('Location: /admin/admin_menu.php?status=deleted');
            exit();
        } else {
            // Если запись не найдена или не удалена, перенаправляем с сообщением об ошибке
            error_log('[WARNING] Не удалось найти или удалить запись с ID: ' . $id);
            header('Location: /admin/admin_menu.php?status=not_found');
            exit();
        }
    } catch (Exception $e) {
        // Логируем ошибку и перенаправляем с сообщением об ошибке
        error_log('[ERROR] Ошибка при удалении записи: ' . $e->getMessage());
        header('Location: /admin/admin_menu.php?status=delete_error');
        exit();
    }
} else {
    // Логируем ошибку о неверном ID
    error_log('[WARNING] Неверный ID записи. ID: ' . $id);
    // Перенаправляем с сообщением о неверном ID
    header('Location: /admin/admin_menu.php?status=invalid_id');
    exit();
}
?>
