<?php
// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключение к базе данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => true, 'details' => 'Неверный метод запроса.']);
    exit();
}

// Получаем дату доставки и номер недели из GET-параметров
$delivery_date = isset($_GET['delivery_date']) ? trim($_GET['delivery_date']) : '';
$week_number = isset($_GET['week_number']) ? (int)$_GET['week_number'] : 1; // Получаем номер недели, по умолчанию 1

if (empty($delivery_date)) {
    echo json_encode(['error' => true, 'details' => 'Дата доставки не указана.']);
    exit();
}

// Определяем день недели на основе даты доставки
$day_of_week = date('N', strtotime($delivery_date));

// Логируем параметры запроса
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/debug_log.log', "Параметры запроса: delivery_date = {$delivery_date}, week_number = {$week_number}, day_of_week = {$day_of_week}\n", FILE_APPEND);

try {
    // SQL-запрос для получения данных о пакетах на указанную дату
    $sql_packages = "
        SELECT op.calories AS package, COUNT(dd.delivery_date) AS total_quantity
        FROM order_packages op
        LEFT JOIN delivery_dates dd ON op.id = dd.order_package_id
        LEFT JOIN orders o ON o.order_id = op.order_id
        WHERE dd.delivery_date = :delivery_date
        GROUP BY op.calories
        ORDER BY op.calories
    ";
    
    $stmt_packages = $pdo->prepare($sql_packages);
    $stmt_packages->bindValue(':delivery_date', $delivery_date, PDO::PARAM_STR);
    $stmt_packages->execute();
    $packages = $stmt_packages->fetchAll(PDO::FETCH_ASSOC);

    // Логируем полученные данные о пакетах
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/debug_log.log', "Полученные данные о пакетах: " . print_r($packages, true) . "\n", FILE_APPEND);

    if (!$packages) {
        echo json_encode(['error' => true, 'details' => 'Данные по пакетам не найдены.']);
        exit();
    }

    // SQL-запрос для получения блюд из таблицы weekly_menu для выбранных week_number и day_number
    $sql_dishes = "
        SELECT week_number, day_number, 
            dish_1_description, dish_1_ingredients, dish_1_allergens, dish_1_energy, dish_1_fat, dish_1_carbohydrates, dish_1_protein, dish_1_salt, dish_1_net_mass,
            dish_2_description, dish_2_ingredients, dish_2_allergens, dish_2_energy, dish_2_fat, dish_2_carbohydrates, dish_2_protein, dish_2_salt, dish_2_net_mass,
            dish_3_description, dish_3_ingredients, dish_3_allergens, dish_3_energy, dish_3_fat, dish_3_carbohydrates, dish_3_protein, dish_3_salt, dish_3_net_mass,
            dish_4_description, dish_4_ingredients, dish_4_allergens, dish_4_energy, dish_4_fat, dish_4_carbohydrates, dish_4_protein, dish_4_salt, dish_4_net_mass
        FROM weekly_menu
        WHERE week_number = :week_number AND day_number = :day_of_week
    ";

    $stmt_dishes = $pdo->prepare($sql_dishes);
    $stmt_dishes->bindValue(':week_number', $week_number, PDO::PARAM_INT);
    $stmt_dishes->bindValue(':day_of_week', $day_of_week, PDO::PARAM_INT);
    $stmt_dishes->execute();
    $dishes = $stmt_dishes->fetchAll(PDO::FETCH_ASSOC);

    // Логируем полученные данные о блюдах
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/debug_log.log', "Полученные данные о блюдах: " . print_r($dishes, true) . "\n", FILE_APPEND);

    // Проверка на наличие данных о блюдах
    if (!$dishes) {
        echo json_encode(['error' => true, 'details' => 'Данные о блюдах не найдены.']);
        exit();
    }

    // Формируем ответ с данными о пакетах и блюдах
    $response = [
        'error' => false,
        'delivery_date' => $delivery_date,
        'packages' => $packages,
        'dishes' => $dishes
    ];

    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/debug_log.log', "Формируемый ответ: " . print_r($response, true) . "\n", FILE_APPEND);
    echo json_encode($response);

} catch (PDOException $e) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/debug_log.log', "Ошибка при выполнении запроса: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => true, 'details' => 'Ошибка при выполнении запроса: ' . $e->getMessage()]);
}
?>
