<?php
// Подключение к базе данных
require_once('../config/db.php');

// Проверяем, что подключение к базе данных успешно установлено
if (!isset($pdo) || $pdo === null) {
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit();
}

// Проверяем, была ли передана дата и номер недели
if (isset($_GET['date'])) {
    try {
        // Получаем выбранную дату из GET параметров
        $date = $_GET['date'];

        // Определяем день недели на основе даты (1 - Пн, 7 - Вс)
        $day_number = date('N', strtotime($date));

        // SQL-запрос для получения данных из таблицы weekly_menu
        $query = "
            SELECT dish_1_description, dish_1_ingredients, dish_2_description, dish_2_ingredients,
                   dish_3_description, dish_3_ingredients, dish_4_description, dish_4_ingredients
            FROM weekly_menu
            WHERE week_number = 1 AND day_number = :day_number
        ";

        // Подготовка и выполнение запроса с использованием PDO
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':day_number', $day_number, PDO::PARAM_INT);
        $stmt->execute();

        // Формируем массив данных для ответа
        $weeklyMenu = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Возвращаем данные в формате JSON
        echo json_encode(['dishes' => $weeklyMenu]);
    } catch (PDOException $e) {
        error_log("Ошибка при выполнении запроса: " . $e->getMessage()); // Логирование ошибки
        echo json_encode(['error' => 'Ошибка при выполнении запроса']);
        exit();
    }
} else {
    echo json_encode(['error' => 'Дата не передана']);
    exit();
}
?>
