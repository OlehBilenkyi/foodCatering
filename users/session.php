<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';


// Если пользователь уже залогинен, загружаем его данные
function getUser()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Если пользователя нет — редирект на логин
function requireAuth()
{
    if (!isset($_SESSION['user'])) {
        header('Location: /auth/login');
        exit();
    }
}
?>
