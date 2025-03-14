<?php
require_once '../../config/db.php';
require_once '../utils/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        respondWithJson(['error' => 'Email обязателен'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
        $stmt->execute([$token, $email]);

        // Отправка письма (реализуем позже)
        respondWithJson(['message' => 'Ссылка для сброса отправлена']);
    } else {
        respondWithJson(['error' => 'Email не найден'], 404);
    }
}
?>
