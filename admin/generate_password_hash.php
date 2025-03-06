<?php
// generate_password_hash.php
// Простой PHP-скрипт для генерации хэша пароля

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

// Получаем пароль из консоли или переменной
$password = 'S_hevcenka1993'; // Замените на желаемый пароль или используйте консольный ввод для повышения безопасности

if ($password) {
    try {
        // Генерация хэша пароля с использованием алгоритма PASSWORD_DEFAULT
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        echo "Хэш пароля: " . $hashedPassword . PHP_EOL;
    } catch (Exception $e) {
        // Логирование ошибок
        error_log("Ошибка при генерации хэша пароля: " . $e->getMessage());
        echo "Произошла ошибка при генерации хэша. Пожалуйста, попробуйте снова." . PHP_EOL;
    }
} else {
    echo "Пожалуйста, введите корректный пароль." . PHP_EOL;
}
?>
