<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Подключение к базе данных

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

if (isset($_GET['token'])) {
    $token = htmlspecialchars(trim($_GET['token']));
    
    try {
        // Проверяем, что токен действителен и не истек
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE reset_token = ? AND reset_token_expiry > NOW()');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Если отправлена форма с новым паролем
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password'])) {
                $new_password = htmlspecialchars(trim($_POST['new_password']));
                
                // Минимальная длина пароля для лучшей безопасности
                if (strlen($new_password) < 8) {
                    $errorMessage = "Пароль должен содержать минимум 8 символов.";
                } else {
                    try {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        // Обновляем пароль в базе данных и сбрасываем токен
                        $stmt = $pdo->prepare('UPDATE admins SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?');
                        $stmt->execute([$hashed_password, $user['id']]);

                        // Выводим сообщение об успешной смене пароля и перенаправляем на страницу входа
                        $successMessage = "Пароль успешно изменен. Переадресация на страницу входа...";
                        header("Refresh: 5; url=/admin/admin.php");
                    } catch (PDOException $e) {
                        error_log("Ошибка при обновлении пароля: " . $e->getMessage());
                        $errorMessage = "Произошла ошибка при обновлении пароля. Пожалуйста, попробуйте позже.";
                    }
                }
            }
        } else {
            $errorMessage = "Токен истек или недействителен.";
        }
    } catch (PDOException $e) {
        error_log("Ошибка при проверке токена: " . $e->getMessage());
        $errorMessage = "Произошла ошибка при проверке токена. Пожалуйста, попробуйте позже.";
    }
} else {
    $errorMessage = "Неверный или отсутствующий токен.";
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка нового пароля</title>
    <link rel="stylesheet" href="/assets/stylescss/styles.css">
</head>
<body>
    <div class="reset-password-container">
        <h2>Установка нового пароля</h2>

        <?php
        if (!empty($errorMessage)) {
            echo "<p class='error-message'>" . htmlspecialchars($errorMessage) . "</p>";
        }
        if (!empty($successMessage)) {
            echo "<p class='success-message'>" . htmlspecialchars($successMessage) . "</p>";
        } elseif (isset($user)) {
        ?>
            <form method="POST" action="" class="reset-password-form">
                <div class="form-group">
                    <label for="new_password">Новый пароль:</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary">Установить новый пароль</button>
            </form>
        <?php
        }
        ?>
    </div>
</body>
</html>
