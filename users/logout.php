<?php
session_start();
session_destroy();

// Если это AJAX-запрос, возвращаем JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Вы вышли из системы']);
    exit();
}

// Если обычный запрос, редиректим на страницу входа
header("Location: /auth/login");
exit();
?>
