<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';
require_once 'functions.php';

// Логирование ошибок
function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, '../logs/error_log.log');
}

// Генерация CSRF токена и сохранение в сессии
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Генерируем случайный токен
}

// Проверка блокировки аккаунта
if (isset($_SESSION['lock_until']) && time() < $_SESSION['lock_until']) {
    $remainingTime = ($_SESSION['lock_until'] - time()) / 60;
    echo "<p class='error-message'>Ваш аккаунт временно заблокирован. Пожалуйста, попробуйте снова через " . ceil($remainingTime) . " минут.</p>";
    exit();
}

// Проверяем, был ли запрос методом POST и включаем проверку CSRF токена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        log_error("Некорректный CSRF токен. Возможная CSRF атака.");
        echo "<p class='error-message'>Ошибка безопасности. Пожалуйста, обновите страницу и попробуйте снова.</p>";
        exit();
    }

    // Валидация входных данных
    $login = trim($_POST['login']);
    $password = $_POST['password'];

    if (empty($login) || empty($password)) {
        $_SESSION['errorMessage'] = "Пожалуйста, заполните все поля.";
        header("Location: admin.php");
        exit();
    }

    // Логика проверки данных пользователя
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$login]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Успешная авторизация
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_logged_in'] = true;
            header("Location: admin_panel.php");
            exit();
        } else {
            $_SESSION['errorMessage'] = "Неверный логин или пароль.";
            header("Location: admin.php");
            exit();
        }
    } catch (Exception $e) {
        log_error("Ошибка при проверке пользователя: " . $e->getMessage());
        $_SESSION['errorMessage'] = "Произошла ошибка. Пожалуйста, попробуйте позже.";
        header("Location: admin.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <?php
    $pageTitle = "Catering Całodzienny w Krakowie - Śniadania, Obiady i Kolacje | FOODCASE";
    $metaDescription = "Catering całodzienny w Krakowie - zamów śniadania, obiady i kolacje z dostawą na cały dzień od FOODCASE.";
    $metaKeywords = "catering całodzienny, Kraków, dostawa jedzenia, śniadania, obiady, kolacje, FOODCASE";
    header("Content-Security-Policy: script-src 'self' https://www.google.com https://www.gstatic.com 'unsafe-inline';");
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
    ?>
    <link rel="stylesheet" type="text/css" href="../assets/stylescss/admin_styles.css">
    <title>Панель Администратора - Вход</title>
    <!-- Подключение reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=6Led-2cqAAAAAA8-Ob6rNFBVLqqQXsbCQKGypfkH"></script>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; ?>
    <div class="admin-login-container">
        <h2>Вход в панель администратора</h2>

        <?php
        if (!empty($_SESSION['errorMessage'])) {
            echo "<p class='error-message'>" . htmlspecialchars($_SESSION['errorMessage']) . "</p>";
            unset($_SESSION['errorMessage']); // Очищаем ошибку после вывода
        }
        ?>

        <form method="POST" action="admin.php" class="admin-login-form" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
            <div class="form-group">
                <label for="login">Логин:</label>
                <input type="text" id="login" name="login" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" id="loginButton">Войти</button>
        </form>

        <div id="loadingIndicator" style="display: none;">Проверка reCAPTCHA, пожалуйста, подождите...</div>
        
        <p>Забыли логин? Больше не забывай;) <a href="/admin/forgot_username.php">Восстановить логин</a></p>
        <p>Забыли пароль? Запиши <a href="/admin/reset_password.php">Сбросить пароль</a></p>
    </div>

    <script>
        // Подготавливаем reCAPTCHA
        document.addEventListener('DOMContentLoaded', function () {
            grecaptcha.ready(function () {
                grecaptcha.execute('6Led-2cqAAAAAA8-Ob6rNFBVLqqQXsbCQKGypfkH', { action: 'login' })
                .then(function (token) {
                    document.getElementById('g-recaptcha-response').value = token;
                }).catch(function (error) {
                    console.error("Ошибка при получении токена reCAPTCHA:", error);
                    logError('Ошибка при получении токена reCAPTCHA: ' + error);
                    alert('Ошибка при получении токена reCAPTCHA. Пожалуйста, обновите страницу и попробуйте снова.');
                });
            });
        });

        function logError(message) {
            fetch('/logs/error_log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ error: message })
            });
        }

        document.getElementById('loginButton').addEventListener('click', function (e) {
            if (document.getElementById('g-recaptcha-response').value === "") {
                e.preventDefault();
                alert('Ошибка: токен reCAPTCHA не был установлен. Пожалуйста, подождите, пока reCAPTCHA будет готова.');
            }
        });
    </script>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
</body>
</html>
