<?php
require_once __DIR__ . '/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Валидация email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Некорректный email!";
    } elseif (strlen($password) < 6) {
        $error = "Пароль должен содержать минимум 6 символов!";
    } else {
        // Проверяем, существует ли email
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "Этот email уже зарегистрирован!";
        } else {
            // Хешируем пароль
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Добавляем пользователя
            $stmt = $pdo->prepare("INSERT INTO customers (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$email, $password_hash, $first_name, $last_name])) {
                $_SESSION['user'] = [
                    'id' => $pdo->lastInsertId(),
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ];
                header("Location: /dashboard");
                exit();
            } else {
                $error = "Ошибка регистрации!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
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
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            min-height: 100vh;
            background: var(--deep-space);
            display: grid;
            place-items: center;
            overflow: hidden;
            font-size: 1rem;
        }

        .auth-container {
            background: rgba(15, 20, 30, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            position: relative;
            box-shadow: 0 0 40px rgba(0, 243, 255, 0.1);
            z-index: 1;
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
        }

        .cyber-input {
            width: 100%;
            padding: 16px 24px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
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
            color: rgba(255,255,255,0.4);
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
        }

        .cyber-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 243, 255, 0.3);
        }

        .error {
            color: #ff4d4d;
            text-align: center;
            margin-bottom: 16px;
        }

        .links {
            margin-top: 32px;
            text-align: center;
        }

        .links a {
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            transition: var(--transition);
        }

        .links a:hover {
            color: var(--neon-blue);
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="holographic-header">
            <h2>Регистрация</h2>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="cyber-input-group">
                <input type="text" name="first_name" required placeholder="Имя">
            </div>
            <div class="cyber-input-group">
                <input type="text" name="last_name" required placeholder="Фамилия">
            </div>
            <div class="cyber-input-group">
                <input type="email" name="email" required placeholder="Email">
            </div>
            <div class="cyber-input-group">
                <input type="password" name="password" required placeholder="Пароль">
            </div>
            <button type="submit" class="cyber-button">Зарегистрироваться</button>
        </form>

        <div class="links">
            <p>Уже есть аккаунт? <a href="/users/login.php">Войти</a></p>
            <a href="/users/google.php">Войти через Google</a>
            <a href="/users/apple.php">Войти через Apple</a>
        </div>
    </div>
</body>
</html>
