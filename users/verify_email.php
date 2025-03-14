<?php
require_once '../../config/db.php';
require_once '../utils/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';

    if (!$token) {
        respondWithJson(['error' => 'Отсутствует токен'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, email_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        respondWithJson(['message' => 'Email подтверждён']);
    } else {
        respondWithJson(['error' => 'Недействительный токен'], 400);
    }
}
?>
