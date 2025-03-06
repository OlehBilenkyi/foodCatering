<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Stripe\Webhook;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error_log.log');

// Подгружаем переменные окружения из .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Секретный ключ вебхука
$endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

if (!$endpoint_secret) {
    error_log("Ошибка: Секретный ключ вебхука отсутствует.");
    http_response_code(500);
    exit();
}

// Подключение к базе данных
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
    error_log("Успешное подключение к базе данных для пользователя " . $_ENV['DB_USER']);
} catch (PDOException $e) {
    error_log("Ошибка подключения к базе данных: " . $e->getMessage());
    http_response_code(500);
    exit();
}

// Читаем запрос
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;
$event = null;

try {
    if (!$sig_header) {
        error_log("Ошибка: Отсутствует заголовок Stripe Signature.");
        http_response_code(400);
        exit();
    }

    $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    error_log('Событие от Stripe успешно верифицировано.');
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    error_log("Недопустимое тело запроса: " . $e->getMessage());
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    error_log("Подпись не прошла проверку: " . $e->getMessage());
    exit();
}

// Проверка и обработка события вебхука
try {
    if ($event->type === 'checkout.session.completed') {
        error_log('Получено событие checkout.session.completed');
        $session = $event->data->object;

        // Извлекаем order_id из метаданных
        $orderId = $session->metadata->order_id ?? null;
        error_log("Полученный order_id из метаданных: " . $orderId);

        if ($orderId) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id");
                $stmt->execute([':order_id' => $orderId]);
                $order = $stmt->fetch();

                if ($order) {
                    error_log("Найден заказ с order_id $orderId, текущий статус: " . $order['status']);

                    if ($order['status'] === 'оплачен') {
                        error_log("Предотвращено повторное выполнение запроса для order_id: " . $orderId);
                        http_response_code(200);
                        exit();
                    }

                    $stmt = $pdo->prepare("UPDATE orders SET status = 'оплачен' WHERE order_id = :order_id");
                    $stmt->execute([':order_id' => $orderId]);
                    error_log("Заказ с order_id $orderId успешно обновлен на статус 'оплачен'.");

                    // Генерируем детали пакетов и дат доставки для email
                    $package_details = generatePackageDetails($pdo, $orderId);

                    if (empty($package_details)) {
                        error_log("Ошибка: Детали пакетов для order_id $orderId отсутствуют.");
                        http_response_code(500);
                        exit();
                    }

                    // Подготавливаем данные для шаблона email
                    $order_date = $order['created_at'];
                    $total_amount = $order['total_price'] . ' zł';
                    $customer_email = $order['customer_email'];
                    $customer_phone = $order['customer_phone'];
                    $customer_name = $order['customer_fullname'];
                    $customer_street = $order['customer_street'];
                    $customer_house_number = $order['customer_house_number'];
                    $customer_floor = $order['customer_floor'];
                    $customer_apartment = $order['customer_apartment'];
                    $customer_gate_code = $order['customer_gate_code'];
                    $customer_notes = $order['customer_notes'];
                    $customer_klatka = $order['customer_klatka']; // Добавляем klatka

                    // Логирование содержимого package_details
                    error_log("Содержимое переменной \$package_details для отправки в шаблон: " . $package_details);

                    ob_start();
                    include __DIR__ . '/../emails/order_confirmation_template.php';
                    $templateContent = ob_get_clean();

                    $customerEmail = $order['customer_email'];
                    $sellerEmail = $_ENV['SMTP_USERNAME'];

                    // Отправка email покупателю и продавцу
                    $customerSent = sendEmail($customerEmail, 'Potwierdzenie zamówienia - FoodCase', $templateContent);
                    if ($customerSent) {
                        error_log("Письмо покупателю успешно отправлено на: " . $customerEmail);
                    } else {
                        error_log("Ошибка при отправке письма покупателю на: " . $customerEmail);
                    }

                    $sellerSent = sendEmail($sellerEmail, 'Nowe zamówienie - FoodCase', $templateContent);
                    if ($sellerSent) {
                        error_log("Письмо продавцу успешно отправлено на: " . $sellerEmail);
                    } else {
                        error_log("Ошибка при отправке письма продавцу на: " . $sellerEmail . ". Проверьте настройки SMTP.");
                    }
                } else {
                    error_log("Ошибка: заказ с order_id $orderId не найден в базе данных.");
                    http_response_code(404);
                }
            } catch (PDOException $e) {
                error_log("Ошибка при выполнении запроса к базе данных: " . $e->getMessage());
                http_response_code(500);
                exit();
            }
        } else {
            error_log("Ошибка: order_id не найден в metadata.");
            http_response_code(400);
            exit();
        }

    } else {
        error_log('Получено неопознанное событие: ' . $event->type);
        http_response_code(400);
    }

    http_response_code(200);
} catch (Exception $e) {
    error_log("Ошибка обработки вебхука: " . $e->getMessage());
    http_response_code(500);
    exit();
}

// Функция для отправки email с использованием SMTP
function sendEmail($email, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_USERNAME'], 'FoodCase'); // Основной email отправителя
        $mail->addReplyTo($_ENV['SMTP_USERNAME'], 'FoodCase Support'); // Email для ответов
        $mail->addAddress($email);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->isHTML(true);

        // Вставляем изображение логотипа как встроенное изображение
        $mail->AddEmbeddedImage('../assets/img/logo.png', 'logo_image'); // Замените путь к логотипу на правильный
        $body = str_replace('src="https://foodcasecatering.net/assets/img/logo.png"', 'src="cid:logo_image"', $body);

        // Устанавливаем тело письма
        $mail->Body = $body;

        // Отправляем письмо
        $mail->send();
        error_log("Email успешно отправлен на $email");
        return true;
    } catch (Exception $e) {
        error_log("Ошибка при отправке email на $email: " . $e->getMessage());
        return false;
    }
}

// Функция для генерации деталей пакета для email, включая даты доставки
function generatePackageDetails($pdo, $orderId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM order_packages WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $orderId]);
        $packages = $stmt->fetchAll();

        $packageDetailsHtml = '';
        foreach ($packages as $package) {
            $stmtDates = $pdo->prepare("SELECT delivery_date FROM delivery_dates WHERE order_package_id = :order_package_id");
            $stmtDates->execute([':order_package_id' => $package['id']]);
            $dates = $stmtDates->fetchAll();
            $deliveryDates = array_map(function($date) {
                return htmlspecialchars($date['delivery_date'], ENT_QUOTES, 'UTF-8');
            }, $dates);
            $deliveryDatesString = implode(', ', $deliveryDates);
            $packageDetailsHtml .= 'Kalorie: ' . htmlspecialchars($package['calories'], ENT_QUOTES, 'UTF-8') . '<br>' .
                                   'Ilość: ' . intval($package['quantity']) . '<br>' .
                                   'Daty dostawy: ' . $deliveryDatesString . '<br><br>';
        }
        return $packageDetailsHtml;
    } catch (PDOException $e) {
        error_log("Ошибка при получении данных о пакетах и датах доставки: " . $e->getMessage());
        return 'Błąd przy uzyskiwaniu szczegółów pakietów.';
    }
}

?>
