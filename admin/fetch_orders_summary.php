<?php
// Включаем отображение всех ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Логирование ошибок в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Подключаем файл с конфигурацией базы данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверяем, что соединение с базой данных установлено
if (!$pdo) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ошибка подключения к базе данных.']);
    exit;
}

// Проверяем, что запрос является GET и параметр term передан
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['term'])) {
    $searchTerm = trim($_GET['term']);

    if (!empty($searchTerm)) {
        try {
            // SQL-запрос для поиска совпадений по email или адресу
            $stmt = $pdo->prepare("
                SELECT DISTINCT 
                    CONCAT(o.customer_street, ' ', o.customer_house_number) AS address,
                    o.customer_email AS email
                FROM orders o
                WHERE 
                    o.customer_email LIKE :searchTerm OR 
                    CONCAT(o.customer_street, ' ', o.customer_house_number) LIKE :searchTerm
                LIMIT 10
            ");
            $stmt->bindValue(':searchTerm', '%' . $searchTerm . '%', PDO::PARAM_STR);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Логируем полученные результаты
            error_log("[DEBUG] SQL Results: " . print_r($results, true));

            // Формируем данные для автозаполнения
            $autocompleteData = [];
            foreach ($results as $row) {
                if (!empty($row['email'])) {
                    $autocompleteData[] = [
                        'label' => htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'),
                        'value' => htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'),
                        'type' => 'email'
                    ];
                }
                if (!empty($row['address'])) {
                    $autocompleteData[] = [
                        'label' => htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8'),
                        'value' => htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8'),
                        'type' => 'address'
                    ];
                }
            }

            // Проверяем, является ли массив данных корректным
            if (!is_array($autocompleteData)) {
                error_log("[ERROR] Некорректный формат данных для автозаполнения.");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Некорректный формат данных.']);
                exit;
            }

            // Логируем данные перед отправкой
            error_log("[DEBUG] Autocomplete Data: " . json_encode($autocompleteData, JSON_UNESCAPED_UNICODE));

            // Если данные найдены, возвращаем их в формате JSON
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($autocompleteData, JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $e) {
            error_log("Ошибка при выполнении запроса: " . $e->getMessage());
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Ошибка при выполнении запроса.']);
            exit;
        }
    } else {
        // Если пустой запрос, возвращаем пустой массив
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }
} else {
    // Если неверный метод или отсутствует параметр term
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Некорректный запрос']);
    exit;
}
?>
