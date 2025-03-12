<?php
require_once '../../config/db.php';
require_once '../utils/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['password'] ?? '';

    if (!$token || !$newPassword) {
        respondWithJson(['error' => 'Токен и пароль обязательны'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);

        respondWithJson(['message' => 'Пароль успешно обновлён']);
    } else {
        respondWithJson(['error' => 'Недействительный или истёкший токен'], 400);
    }
}
?>
