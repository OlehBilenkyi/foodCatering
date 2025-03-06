<?php
session_start();

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки

// Подключаем файл базы данных и конфигурацию
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

// Максимальное количество попыток входа
define('MAX_FAILED_ATTEMPTS', 3);
// Время блокировки в минутах после превышения количества попыток
define('LOCKOUT_TIME', 30);

// Проверка наличия CSRF токена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['errorMessage'] = "Неверный CSRF токен.";
        error_log('[WARNING] CSRF токен отсутствует или не совпадает.');
        header('Location: /admin/admin.php');
        exit();
    }

    // Удаляем CSRF токен из сессии, чтобы избежать повторного использования
    unset($_SESSION['csrf_token']);

    // Проверяем наличие reCAPTCHA ответа
    if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
        $recaptchaToken = $_POST['g-recaptcha-response'];

        // Логируем полученный токен для проверки
        error_log('[INFO] Полученный токен reCAPTCHA: ' . $recaptchaToken);

        // Создаем запрос на проверку токена с использованием cURL
        $recaptchaSecret = RECAPTCHA_SECRET_KEY;
        $recaptchaURL = "https://www.google.com/recaptcha/api/siteverify";

        // Формируем запрос
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $recaptchaURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'secret' => $recaptchaSecret,
            'response' => $recaptchaToken,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Тайм-аут на соединение
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Общий тайм-аут

        // Выполняем запрос
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('[ERROR] Ошибка cURL: ' . curl_error($ch));
            $_SESSION['errorMessage'] = "Ошибка сервера при проверке reCAPTCHA. Пожалуйста, попробуйте позже.";
            curl_close($ch);
            header('Location: /admin/admin.php');
            exit();
        }
        curl_close($ch);

        // Декодируем ответ
        $result = json_decode($response, true);

        // Логируем полный ответ от Google для диагностики
        error_log('[INFO] Ответ Google reCAPTCHA: ' . $response);

        // Проверяем результат валидации
        if ($result && isset($result['success']) && $result['success'] == true) {
            // Проверка успешная, проверяем логин и пароль пользователя
            if (isset($_POST['login']) && isset($_POST['password'])) {
                $login = trim($_POST['login']);
                $password = $_POST['password'];

                // Поиск пользователя в базе данных
                try {
                    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = :username');
                    $stmt->execute(['username' => $login]);
                    $admin = $stmt->fetch();

                    if ($admin) {
                        // Проверяем, не заблокирован ли аккаунт
                        if ($admin['lock_until'] && strtotime($admin['lock_until']) > time()) {
                            $_SESSION['errorMessage'] = "Аккаунт временно заблокирован. Пожалуйста, попробуйте позже.";
                            error_log("[WARNING] Аккаунт заблокирован для пользователя: $login до " . $admin['lock_until']);
                            header('Location: /admin/admin.php');
                            exit();
                        }

                        // Проверяем пароль
                        if (password_verify($password, $admin['password_hash'])) {
                            // Авторизация успешна, сбрасываем неудачные попытки
                            $stmt = $pdo->prepare('UPDATE admins SET failed_attempts = 0, lock_until = NULL WHERE id = :id');
                            $stmt->execute(['id' => $admin['id']]);
                            
                            $_SESSION['logged_in'] = true;
                            $_SESSION['username'] = $admin['username'];
                            $_SESSION['login_attempts'] = 0;
                            error_log("[INFO] Авторизация успешна для пользователя: $login");
                            header('Location: /admin/admin_panel.php');
                            exit();
                        } else {
                            // Неверный пароль, увеличиваем счетчик неудачных попыток
                            $failed_attempts = $admin['failed_attempts'] + 1;

                            if ($failed_attempts >= MAX_FAILED_ATTEMPTS) {
                                // Блокируем аккаунт на LOCKOUT_TIME минут
                                $lock_until = date('Y-m-d H:i:s', strtotime("+".LOCKOUT_TIME." minutes"));
                                $stmt = $pdo->prepare('UPDATE admins SET failed_attempts = :failed_attempts, lock_until = :lock_until WHERE id = :id');
                                $stmt->execute(['failed_attempts' => $failed_attempts, 'lock_until' => $lock_until, 'id' => $admin['id']]);
                                $_SESSION['errorMessage'] = "Аккаунт заблокирован из-за превышения количества неудачных попыток входа. Попробуйте снова через 30 минут.";
                                error_log("[WARNING] Аккаунт заблокирован для пользователя: $login до $lock_until");
                            } else {
                                // Обновляем количество неудачных попыток
                                $stmt = $pdo->prepare('UPDATE admins SET failed_attempts = :failed_attempts WHERE id = :id');
                                $stmt->execute(['failed_attempts' => $failed_attempts, 'id' => $admin['id']]);
                                $_SESSION['errorMessage'] = "Неверный логин или пароль.";
                                error_log("[WARNING] Неверный пароль для пользователя: $login. Попыток: $failed_attempts");
                            }

                            header('Location: /admin/admin.php');
                            exit();
                        }
                    } else {
                        // Пользователь не найден
                        $_SESSION['errorMessage'] = "Неверный логин или пароль.";
                        error_log("[WARNING] Пользователь не найден: $login");
                        header('Location: /admin/admin.php');
                        exit();
                    }
                } catch (PDOException $e) {
                    error_log('[ERROR] Ошибка базы данных: ' . $e->getMessage());
                    $_SESSION['errorMessage'] = "Ошибка сервера. Пожалуйста, попробуйте позже.";
                    header('Location: /admin/admin.php');
                    exit();
                }
            } else {
                $_SESSION['errorMessage'] = "Не указан логин или пароль.";
                error_log('[WARNING] Логин или пароль не были предоставлены.');
                header('Location: /admin/admin.php');
                exit();
            }
        } else {
            $_SESSION['errorMessage'] = "Ошибка при проверке reCAPTCHA. Пожалуйста, попробуйте снова.";
            if (isset($result['error-codes'])) {
                error_log('[ERROR] Ошибка reCAPTCHA: ' . implode(', ', $result['error-codes']));
            }
            header('Location: /admin/admin.php');
            exit();
        }
    } else {
        $_SESSION['errorMessage'] = "Не получен ответ reCAPTCHA.";
        error_log('[WARNING] Ответ reCAPTCHA не был получен.');
        header('Location: /admin/admin.php');
        exit();
    }
} else {
    $_SESSION['errorMessage'] = "Неверный метод запроса.";
    error_log('[WARNING] Получен неверный метод запроса. Ожидается POST.');
    header('Location: /admin/admin.php');
    exit();
}
?>
