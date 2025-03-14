<?php
session_start();
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userEmail = filter_var($_SESSION['user_email'], FILTER_SANITIZE_EMAIL);

$query = $pdo->prepare("
    SELECT message, created_at 
    FROM notifications 
    WHERE customer_email = :email 
    ORDER BY created_at DESC 
    LIMIT 10
");
$query->execute(['email' => $userEmail]);
$notifications = $query->fetchAll(PDO::FETCH_ASSOC);

// Преобразуем даты в читаемый формат
foreach ($notifications as &$notification) {
    $notification['created_at'] = date('d.m.Y H:i', strtotime($notification['created_at']));
}

echo json_encode($notifications);
?>
