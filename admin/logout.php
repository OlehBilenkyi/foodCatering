<?php
session_start();

// Удаление всех данных сессии
session_unset();
session_destroy();

// Перенаправление на страницу входа
header('Location: /admin/admin.php');
exit();
?>
