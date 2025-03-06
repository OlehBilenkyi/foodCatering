<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');

header('Content-Type: application/json');

$date = $_GET['date'];
$calories = (int)$_GET['calories']; // Добавим калорийность пакета для пересчета

try {
    $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=" . $_ENV['DB_CHARSET'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Выбираем блюда для указанной даты
    $stmt = $pdo->prepare("
        SELECT * FROM weekly_menu 
        WHERE week_number = WEEK(:date, 1) AND day_number = DAYOFWEEK(:date) - 1
    ");
    $stmt->execute(['date' => $date]);
    $dishes = $stmt->fetchAll();
    
    $result = [];
    foreach ($dishes as $dish) {
        // Пересчитываем все значения на основе калорийности пакета
        for ($i = 1; $i <= 4; $i++) {
            $result[] = [
                'title' => $dish["dish_{$i}_title"],
                'ingredients' => $dish["dish_{$i}_ingredients"],
                'energy' => round(($dish["dish_{$i}_energy"] / 1000) * $calories, 2),
                'fat' => round(($dish["dish_{$i}_fat"] / 1000) * $calories, 2),
                'carbohydrates' => round(($dish["dish_{$i}_carbohydrates"] / 1000) * $calories, 2),
                'protein' => round(($dish["dish_{$i}_protein"] / 1000) * $calories, 2),
                'salt' => round(($dish["dish_{$i}_salt"] / 1000) * $calories, 2),
                'production_date' => $dish["dish_{$i}_production_date"],
            ];
        }
    }

    echo json_encode($result);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка базы данных', 'details' => $e->getMessage()]);
}
?>
