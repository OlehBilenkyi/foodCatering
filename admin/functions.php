<?php
// functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Запуск сессии только если она еще не активна
}

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

// Проверка авторизации для всех администраторских страниц
function check_admin_auth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /admin/admin.php');
        exit();
    }
}

// Генерация и валидация CSRF-токенов для защиты от CSRF атак
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        error_log("Ошибка CSRF токена: некорректный или отсутствующий токен.");
        header('Location: /admin/admin_menu.php?status=csrf_error');
        exit();
    }
}
?>
