<?php
session_start(); // Запускаем сессию

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

$client_id = "YOUR_GOOGLE_CLIENT_ID"; // Убедитесь, что это правильный client_id
$redirect_uri = "https://foodcasecatering.net/users/oauth_callback.php"; // Убедитесь, что это правильный URI перенаправления

// Генерируем `state` для защиты от CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Google OAuth URL
$url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    "client_id" => $client_id,
    "redirect_uri" => $redirect_uri,
    "response_type" => "code",
    "scope" => "openid email profile",
    "state" => $state,
    "access_type" => "offline", // Получение refresh_token
    "prompt" => "consent" // Принудительное подтверждение аккаунта
]);

header("Location: $url");
exit();
?>
