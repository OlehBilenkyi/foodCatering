<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Добавление водителя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['driver_name'])) {
    $stmt = $pdo->prepare("INSERT INTO drivers (name, phone) VALUES (:name, :phone)");
    $stmt->execute([
        ':name' => $_POST['driver_name'],
        ':phone' => $_POST['driver_phone'] ?? null,
    ]);
    header('Location: /admin/drivers.php');
    exit();
}

// Удаление водителя
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM drivers WHERE id = :id");
    $stmt->execute([':id' => $_GET['delete_id']]);
    header('Location: /admin/drivers.php');
    exit();
}

// Получение всех водителей
$stmt = $pdo->query("SELECT * FROM drivers");
$drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Управление водителями</title>
</head>
<body>
    <h1>Список водителей</h1>
    <form method="POST">
        <input type="text" name="driver_name" placeholder="Имя водителя" required>
        <input type="text" name="driver_phone" placeholder="Телефон">
        <button type="submit">Добавить</button>
    </form>
    <ul>
        <?php foreach ($drivers as $driver): ?>
            <li>
                <?= htmlspecialchars($driver['name']) ?> (<?= htmlspecialchars($driver['phone'] ?? 'N/A') ?>)
                <a href="?delete_id=<?= $driver['id'] ?>">Удалить</a>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
