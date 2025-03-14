<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: /users/auth/login.php");
    exit();
}

$userEmail = htmlspecialchars($_SESSION['user_email']);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Пользователь');
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>FoodCase | Личный кабинет</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B6B;
            --secondary: #4ECDC4;
            --background: #1A1A1A;
            --surface: #2D2D2D;
            --text: #FFFFFF;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="light"] {
            --background: #FFFFFF;
            --surface: #F8F9FA;
            --text: #1A1A1A;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            transition: background-color 0.3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: linear-gradient(135deg, rgba(255,107,107,0.1) 0%, rgba(78,205,196,0.1) 100%);
            backdrop-filter: blur(20px);
            border-radius: 2rem;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .user-info {
            text-align: center;
        }

        .avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 1.5rem;
            padding: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.1);
        }

        .theme-toggle {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: rgba(255,255,255,0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            z-index: 1000;
        }

        .ai-assistant {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .ai-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(255,107,107,0.3);
            transition: var(--transition);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header {
                border-radius: 1rem;
                padding: 1.5rem;
            }

            .theme-toggle {
                top: 1rem;
                right: 1rem;
            }

            .ai-assistant {
                bottom: 1rem;
                right: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="theme-toggle" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </div>

    <div class="ai-assistant">
        <div class="ai-button" onclick="toggleAssistant()">
            <i class="fas fa-robot"></i>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <div class="user-info">
                <div class="avatar">
                    <?= strtoupper(mb_substr($userName, 0, 1)) ?>
                </div>
                <h1><?= $userName ?></h1>
                <p class="email"><?= $userEmail ?></p>
            </div>
        </div>

        <div class="grid">
            <a href="/users/standard_orders.php" class="card">
                <h2><i class="fas fa-utensils"></i> Стандартное меню</h2>
                <p>Готовые решения для вашего события</p>
            </a>

            <a href="/users/custom_menu_orders.php" class="card">
                <h2><i class="fas fa-pencil-alt"></i> Кастомизация меню</h2>
                <p>Создайте уникальное меню под ваш вкус</p>
            </a>

            <div class="card" onclick="showComingSoon()">
                <h2><i class="fas fa-history"></i> История заказов</h2>
                <p>Все ваши предыдущие заказы</p>
            </div>

            <div class="card" onclick="showComingSoon()">
                <h2><i class="fas fa-cog"></i> Настройки профиля</h2>
                <p>Управление аккаунтом и настройками</p>
            </div>
        </div>
    </div>

    <div class="ai-chat" style="display: none;">
        <!-- Чат ассистента -->
    </div>

    <script>
        // Переключение темы с сохранением в localStorage
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            document.querySelector('.theme-toggle i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            localStorage.setItem('theme', newTheme);
        }

        // Восстановление темы при загрузке
        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.querySelector('.theme-toggle i').className = savedTheme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        }

        // AI Assistant
        function toggleAssistant() {
            const chatWindow = document.querySelector('.ai-chat');
            chatWindow.style.display = chatWindow.style.display === 'none' ? 'block' : 'none';
            // Здесь можно добавить загрузку чата
        }

        // Уведомления
        function showComingSoon() {
            const notification = document.createElement('div');
            notification.textContent = '🚀 Функция в разработке!';
            notification.style = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--primary);
                color: white;
                padding: 15px 30px;
                border-radius: 50px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                animation: slideIn 0.3s ease-out;
            `;
            
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Инициализация
        window.addEventListener('DOMContentLoaded', () => {
            loadTheme();
            
            // Приветствие ассистента
            if ('speechSynthesis' in window) {
                const synth = window.speechSynthesis;
                const utterance = new SpeechSynthesisUtterance('Добро пожаловать в ваш личный кабинет!');
                synth.speak(utterance);
            }
        });
    </script>
</body>
</html>