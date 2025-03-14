<?php
session_start();
header('Content-Type: application/json');

// Генерируем CSRF-токен, если его нет
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Возвращаем CSRF-токен в JSON-формате
echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
?>