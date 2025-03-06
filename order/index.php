<?php
// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Старт сессии для защиты от CSRF
session_start();

// Проверка наличия CSRF-токена в запросе
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Ошибка CSRF: Некорректный CSRF-токен.");
        echo json_encode(["status" => "error", "message" => "Nieprawidłowy token CSRF. Odśwież stronę i spróbuj ponownie."]);
        exit;
    }

    // Получаем и декодируем входные данные JSON
    $input = file_get_contents("php://input");
    $orderData = json_decode($input, true);

    // Проверка правильности формата входных данных
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Ошибка JSON: Некорректные данные JSON.");
        echo json_encode(["status" => "error", "message" => "Niepoprawne dane JSON."]);
        exit;
    }

    // Извлечение данных из JSON
    $items = $orderData['items'] ?? null;
    $address = $orderData['address'] ?? null;
    $date = $orderData['date'] ?? null;

    // Валидация данных
    if (empty($items) || !is_array($items) || empty($address) || empty($date)) {
        error_log("Ошибка данных: Недостаточно данных для оформления заказа.");
        echo json_encode(["status" => "error", "message" => "Brak wymaganych danych zamówienia."]);
        exit;
    }

    // Дополнительная валидация: проверка формата даты
    if (!validateDate($date, 'Y-m-d')) {
        error_log("Ошибка валидации даты: Неправильный формат даты.");
        echo json_encode(["status" => "error", "message" => "Niepoprawny format daty."]);
        exit;
    }

    // Отправка email с использованием правильного формата заголовков
    $to = "biuro@foodcasepl.com";
    $subject = "NOWE ZAMÓWIENIE! ;)"; // Субъект без неформальных обращений для лучшего восприятия
    $message = "Zamówiono: " . implode(", ", array_map('htmlspecialchars', $items)) . "\nAdres: " . htmlspecialchars($address) . "\nData dostawy: " . htmlspecialchars($date);
    $headers = [
        "From: no-reply@foodcasepl.com",
        "Content-Type: text/plain; charset=UTF-8",
        "MIME-Version: 1.0",
    ];

    // Отправляем email и возвращаем JSON-ответ
    if (mail($to, $subject, $message, implode("\r\n", $headers))) {
        echo json_encode(["status" => "success", "message" => "Zamówienie zostało pomyślnie wysłane."]);
    } else {
        error_log("Ошибка отправки почты: Не удалось отправить заказ.");
        echo json_encode(["status" => "error", "message" => "Wystąpił błąd podczas wysyłania zamówienia."]);
    }
}

/**
 * Функция валидации даты
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
