<?php
// Включаем отображение ошибок для отладки (удалите в продакшене)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'session.php'; // Здесь обязательно session_start() в session.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Проверяем пользователя в базе
    $stmt = $pdo->prepare("SELECT id, email, password_hash, first_name, last_name FROM customers WHERE email = ?");
    $stmt->execute([$email]);

    // Если есть ошибка SQL-запроса, выводим ее
    if ($stmt->errorCode() != '00000') {
        echo "SQL Error: " . $stmt->errorInfo()[2];
        exit();
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // Устанавливаем данные пользователя в сессию
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

        // Редирект на страницу кабинета после успешного входа
        header("Location: /users/dashboard.php");
        exit();
    } else {
        $error = "Неверный email или пароль!";
    }
}
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход | Future Auth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --neon-blue: #00f3ff;
            --neon-purple: #bc13fe;
            --deep-space: #0a0e17;
            --star-dust: #1f2633;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            min-height: 100vh;
            background: var(--deep-space);
            display: grid;
            place-items: center;
            position: relative;
            overflow: hidden;
        }

        .cyber-grid {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(188, 19, 254, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(188, 19, 254, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: 0;
        }

        .auth-container {
            background: rgba(15, 20, 30, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 40px rgba(0, 243, 255, 0.1);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }

        .auth-container:hover {
            transform: translateY(-5px);
        }

        .holographic-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .holographic-header h2 {
            color: #fff;
            font-size: 2.5rem;
            background: linear-gradient(45deg, var(--neon-blue), var(--neon-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(0, 243, 255, 0.3);
        }

        .cyber-input-group {
            margin-bottom: 28px;
            position: relative;
        }

        .cyber-input {
            width: 100%;
            padding: 16px 24px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            transition: var(--transition);
        }

        .cyber-input:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: 0 0 20px rgba(0, 243, 255, 0.2);
        }

        .cyber-input::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .cyber-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            border: none;
            border-radius: 12px;
            color: #000;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .cyber-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 243, 255, 0.3);
        }

        .social-auth {
            margin-top: 32px;
            display: grid;
            gap: 16px;
        }

        .auth-link {
            color: rgba(255,255,255,0.8);
            text-align: center;
            text-decoration: none;
            transition: var(--transition);
        }

        .auth-link:hover {
            color: var(--neon-blue);
        }

        .error-pulse {
            animation: errorPulse 1.5s infinite;
        }

        @keyframes errorPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 24px;
                margin: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="cyber-grid"></div>

    <div class="auth-container">
        <div class="holographic-header">
            <h2>ВХОД В СИСТЕМУ</h2>
        </div>

        <?php if (isset($error)): ?>
            <div class="cyber-input-group error-pulse">
                <p style="color: #ff4d4d; text-align: center;">⚠️ <?= $error ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="cyber-input-group">
                <input type="email" name="email" class="cyber-input" placeholder="Email" required>
            </div>

            <div class="cyber-input-group">
                <input type="password" name="password" class="cyber-input" placeholder="Пароль" required>
            </div>

            <button type="submit" class="cyber-button">АКТИВИРОВАТЬ ДОСТУП</button>
        </form>

        <div class="social-auth">
            <a href="/users/register.php" class="auth-link">Создать новый аккаунт</a>
            <div style="display: flex; gap: 20px; justify-content: center;">
                <a href="/users/google.php" class="auth-link">
                    <i class="fab fa-google"></i> Google
                </a>
                <a href="/users/apple.php" class="auth-link">
                    <i class="fab fa-apple"></i> Apple
                </a>
            </div>
        </div>
    </div>
</body>
</html>