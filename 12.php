<script>
// Определяем текущую версию сайта
const currentVersion = 'v1.3.0'; // Измените версию на новую при каждом обновлении

// Проверяем версию в localStorage
if (localStorage.getItem('version') !== currentVersion) {
    console.log('Старая версия найдена: ', localStorage.getItem('version')); // Отладочное сообщение
    console.log('Обновляем версию на: ', currentVersion);
    // Если версии не совпадают, обновляем версию и перезагружаем страницу
    localStorage.setItem('version', currentVersion);
    window.location.reload(true);
} else {
    console.log('Текущая версия сайта актуальна:', currentVersion);
}

document.addEventListener("DOMContentLoaded", function () {
    // Код для кнопок уведомления о куки
    const cookieBanner = document.querySelector('.cookie-banner');
    const acceptCookieButton = document.querySelector('.cookie-accept-button');
    const declineCookieButton = document.querySelector('.cookie-decline-button');

    if (acceptCookieButton) {
        acceptCookieButton.addEventListener('click', function () {
            // Скрываем баннер при согласии
            cookieBanner.style.display = 'none';
        });
    }

    if (declineCookieButton) {
        declineCookieButton.addEventListener('click', function () {
            // Перенаправляем на главную страницу при отказе
            window.location.href = '/';
        });
    }

    // Код для кнопки "Оплатить"
    const payButton = document.getElementById("pay-button");
    if (payButton && !payButton.hasAttribute("data-listener-added")) {
        payButton.setAttribute("data-listener-added", "true");
        payButton.addEventListener("click", async function (event) {
            event.preventDefault();
            const acceptTerms = document.getElementById("accept");
            if (!acceptTerms || !acceptTerms.checked) {
                alert("Musisz zaakceptować regulamin.");
                return;
            }
            toggleLoadingState(payButton, true);

            // Получаем данные заказа
            const requestData = getOrderDetails();
            if (!requestData) {
                toggleLoadingState(payButton, false);
                return;
            }
            try {
                const response = await postData('/payments/process_order.php', requestData);

                if (response.status === 'success') {
                    // Обновляем CSRF-токен новым значением, полученным с сервера
                    const newCsrfToken = response.new_csrf_token;
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', newCsrfToken);
                    document.getElementById("hidden-order-id").value = response.order_id;
                    await redirectToPaymentSummary(newCsrfToken, requestData, response.order_id, payButton);
                } else {
                    throw new Error(response.message);
                }
            } catch (error) {
                alert('Произошла ошибка: ' + error.message);
                toggleLoadingState(payButton, false);
            }
        });
    }
});

function getFormData() {
    const formData = {
        email: document.getElementById("email").value.trim(),
        phone: document.querySelector(".phone-input").value.trim(),
        fullname: document.querySelector(".fullname-input").value.trim(),
        street: document.querySelector(".street-input").value.trim(),
        house_number: document.querySelector(".house-number-input").value.trim(),
        klatka: document.querySelector(".klatka-input").value.trim(),
        floor: document.querySelector(".floor-input").value.trim(),
        apartment: document.querySelector(".apartment-input").value.trim(),
        gate_code: document.querySelector(".gate-code-input").value.trim(),
        notes: document.querySelector(".notes-input").value.trim()
    };
    return formData;
}

function validateFormData({ email, phone, fullname, street, house_number, floor, apartment, gate_code }) {
    const isValid = email && phone && fullname && street && house_number && floor && apartment && gate_code;
    return isValid;
}

function getOrderDetails() {
    const formData = getFormData();
    if (!validateFormData(formData)) {
        alert("Proszę wypełnić wszystkie wymagane поля dostawy.");
        toggleLoadingState(payButton, false);
        return null; // Возвращаем null, если форма не валидна
    }
    const packageCards = document.querySelectorAll(".total-info__grid .pay-full-card");
    const packageDetails = [];

    packageCards.forEach(card => {
        // Извлечение данных из атрибутов data-* и элементов пакета
        const calories = card.querySelector(".pay-full-card__title").textContent.trim();
        const quantityText = card.querySelector(".pay-full-card__item.qn").textContent.trim();
        const quantity = parseInt(quantityText.replace(/(?:\spakiet[y|ów]?)$/, ''), 10);
        const datesElements = card.querySelectorAll(".pay-full-card__dates"); // Извлекаем элементы с датами доставки
        const pricePerPackage = parseFloat(card.getAttribute("data-price"));
        const totalCost = parseFloat(card.querySelector(".pay-full-card__item.full-cost .price span").textContent.trim().replace('zł', '').replace(',', '.'));

        // Извлекаем все даты из элементов .pay-full-card__dates
        const dates = Array.from(datesElements).map(dateElement => dateElement.textContent.trim()).filter(date => date);

        // Проверяем корректность данных
        if (calories && quantity && dates.length > 0 && pricePerPackage && totalCost) {
            const packageData = {
                calories,
                quantity: quantity,
                dates: dates, // Сохраняем массив дат
                price_per_package: pricePerPackage,
                total_cost: totalCost
            };
            packageDetails.push(packageData);
        }
    });

    if (packageDetails.length === 0) {
        alert("Nie wybrano żadnych pakietów. Proszę spróbować ponownie.");
        toggleLoadingState(payButton, false);
        return null; // Возвращаем null, если пакеты не выбраны
    }

    // Извлекаем общую сумму без скидки
    const totalElement = document.querySelector('.pay-total__item.total span');
    if (!totalElement) {
        console.error("Ошибка: не удалось найти элемент с общей суммой.");
        toggleLoadingState(payButton, false);
        return null;
    }
    const totalWithoutDiscount = parseFloat(totalElement.textContent.trim().replace('zł', '').replace(',', '.'));

    // Извлекаем сумму скидки
    const discountElement = document.querySelector('.pay-total__item.discount span');
    let discount = 0; // По умолчанию скидка равна 0
    if (discountElement) {
        discount = parseFloat(discountElement.textContent.trim().replace('zł', '').replace(',', '.'));
    }

    // Извлекаем итоговую сумму со скидкой
    const totalSumElement = document.querySelector('.pay-total__item.sum span');
    if (!totalSumElement) {
        console.error("Ошибка: не удалось найти элемент с итоговой суммой.");
        toggleLoadingState(payButton, false);
        return null;
    }
    const totalWithDiscount = parseFloat(totalSumElement.textContent.trim().replace('zł', '').replace(',', '.'));

    // Формируем данные для отправки
    const requestData = {
        ...formData,
        packages: packageDetails,
        total_without_discount: totalWithoutDiscount,
        discount: discount,
        total_price: totalWithDiscount,
        csrf_token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    };

    return requestData; // Возвращаем данные для отправки
}

function toggleLoadingState(button, isLoading) {
    if (isLoading) {
        button.classList.add("loading");
        button.disabled = true;
    } else {
        button.classList.remove("loading");
        button.disabled = false;
    }
}

async function postData(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-PHPSESSID': document.cookie.match(/PHPSESSID=[^;]+/)[0].split('=')[1]
        },
        body: JSON.stringify(data)
    });
    if (!response.ok) {
        throw new Error(`Ошибка при отправке данных: ${response.statusText}`);
    }
    const responseData = await response.json();
    return responseData;
}

async function redirectToPaymentSummary(csrfToken, requestData, orderId, payButton) {
    try {
        const response = await postData('/payments/payment_summary.php', {
            ...requestData,
            csrf_token: csrfToken,
            order_id: orderId
        });

        if (response.id) {
            const stripe = Stripe('<?= htmlspecialchars($stripePublishableKey, ENT_QUOTES, 'UTF-8') ?>');
            stripe.redirectToCheckout({ sessionId: response.id });
        } else {
            throw new Error('Ошибка получения идентификатора сессии');
        }
    } catch (error) {
        alert('Произошла ошибка: ' + error.message);
        toggleLoadingState(payButton, false);
    }
}
</script>

<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Проверяем активность сессии и запускаем её при необходимости
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключаем базу данных
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');

// Подключаем dotenv для загрузки переменных окружения
require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Получаем ключи Stripe из переменных окружения
$stripePublishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? null;
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;

if (!$stripePublishableKey || !$stripeSecretKey) {
    error_log("Ошибка: Переменные Stripe не установлены.");
    exit("Ошибка: Переменные Stripe не установлены.");
}

// Генерация CSRF-токена, если его ещё нет в сессии
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("Сгенерирован новый CSRF-токен: " . $_SESSION['csrf_token']);
} else {
    error_log("Используется существующий CSRF-токен из сессии: " . $_SESSION['csrf_token']);
}

$csrfToken = $_SESSION['csrf_token'];

// Инициализируем переменную $nonce
$nonce = isset($nonce) ? htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') : '';

// Логирование данных сессии на старте
error_log('Данные сессии на старте: ' . print_r($_SESSION, true));

// Обработка POST-запроса для сбора данных о заказе
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Логирование данных сессии до изменения
    error_log('Данные сессии до обновления: ' . print_r($_SESSION, true));

    // Проверяем CSRF-токен из POST-запроса
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log('Ошибка безопасности: Неверный CSRF-токен.');
        exit("Ошибка безопасности: Неверный CSRF-токен.");
    }
    error_log('CSRF-токен успешно проверен.');

    // Валидация и сохранение данных заказа
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        error_log("Ошибка: Неверный формат email.");
        exit("Ошибка: Неверный формат email.");
    }
    $_SESSION['customer_email'] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_phone'] = htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_fullname'] = htmlspecialchars($_POST['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_street'] = htmlspecialchars($_POST['street'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_house_number'] = htmlspecialchars($_POST['house_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_apartment'] = htmlspecialchars($_POST['apartment'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_floor'] = htmlspecialchars($_POST['floor'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_gate_code'] = htmlspecialchars($_POST['gate_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_klatka'] = htmlspecialchars($_POST['klatka'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_notes'] = htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8');

    // Логирование данных сессии после изменения
    error_log('Данные сессии после обновления: ' . print_r($_SESSION, true));

    // Сохранение информации о пакетах
    if (isset($_POST['packages']) && is_array($_POST['packages'])) {
        $_SESSION['packages'] = array_map(function ($package) {
            if (!isset($package['calories'], $package['price'], $package['quantity'], $package['uuid'])) {
                error_log("Ошибка: Пакет не содержит необходимых данных.");
                return null;
            }

            return [
                'uuid' => htmlspecialchars($package['uuid'], ENT_QUOTES, 'UTF-8'),
                'calories' => htmlspecialchars($package['calories'], ENT_QUOTES, 'UTF-8'),
                'price' => is_numeric($package['price']) ? number_format(floatval($package['price']), 2, '.', '') : 0,
                'quantity' => intval($package['quantity']),
                'dates' => array_map(function ($date) {
                    return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
                }, $package['dates'] ?? [])
            ];
        }, $_POST['packages']);
        $_SESSION['packages'] = array_filter($_SESSION['packages']);
    } else {
        $_SESSION['packages'] = [];
        error_log("Ошибка: Неверный формат данных пакетов.");
    }

    // Сохранение суммы для оплаты
    if (!isset($_POST['total_price']) || !is_numeric($_POST['total_price']) || $_POST['total_price'] <= 0) {
        error_log("Ошибка: Неверная или отсутствующая сумма для оплаты.");
        exit("Ошибка: Неверная или отсутствующая сумма для оплаты.");
    }
    $_SESSION['total_price'] = number_format(floatval($_POST['total_price']), 2, '.', '');

    // Логирование информации о сессии после сохранения всех данных заказа
    error_log('Данные сессии после сохранения всех данных заказа: ' . print_r($_SESSION, true));
}

// Заголовок безопасности Content-Security-Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://www.google.com/recaptcha/; frame-src 'self' https://www.google.com/recaptcha/ https://checkout.stripe.com; connect-src 'self' https://api.stripe.com; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");

// Логирование текущего CSRF-токена
error_log('CSRF-токен для формы: ' . $csrfToken);

// Получение данных из базы данных
$packages = [];
$discounts = [];
try {
    $stmt = $pdo->query("SELECT * FROM price ORDER BY id ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM discounts ORDER BY id ASC");
    $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Ошибка при выполнении SQL-запроса: " . $e->getMessage());
}
?>
<!-- Подключаем Stripe.js для оплаты -->
<script src="https://js.stripe.com/v3/" nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>"></script>

<?php
$pageTitle = "Food Case Catering";
$metaDescription = "Sprawdź nasze ceny na smaczne i zdrowe posiłki!";
$metaKeywords = "restauracja, ceny, jedzenie, obiady, kolacja";
$metaAuthor = "Twoja Restauracja";

include($_SERVER['DOCUMENT_ROOT'] . '/includes/head.php');
include($_SERVER['DOCUMENT_ROOT'] . '/includes/header.php');

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);
?>


<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');

// Проверяем, запущена ли сессия
if (session_status() === PHP_SESSION_NONE) {
    // Настройки параметров куки для сессии перед запуском сессии
    session_set_cookie_params([
        'lifetime' => 14400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'None'
    ]);

    session_start();
    session_regenerate_id(true);
}

// Включаем логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Проверка на наличие данных POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Ошибка: Неподдерживаемый метод запроса.');
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Получаем данные из POST-запроса (JSON)
$rawInput = file_get_contents('php://input');
error_log("Полученные сырые данные JSON: " . $rawInput);

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Ошибка разбора JSON: ' . json_last_error_msg());
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit();
}
error_log("Сырые входящие данные: " . print_r($input, true));

if (empty($input)) {
    error_log('Ошибка: Входящие данные отсутствуют');
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Empty form input']);
    exit();
}

// Проверка наличия CSRF-токена
$csrfToken = $input['csrf_token'] ?? null;
if (!$csrfToken || !isset($_SESSION['csrf_token'])) {
    error_log('Ошибка CSRF: отсутствует токен в сессии или в форме.');
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'CSRF token missing']);
    exit();
}

// Проверяем соответствие CSRF-токенов
if ($csrfToken !== $_SESSION['csrf_token']) {
    error_log('Ошибка CSRF: Несоответствие токенов.');
    error_log('Ожидалось: ' . $_SESSION['csrf_token'] . ', Получено: ' . $csrfToken);
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'CSRF token mismatch']);
    exit();
}
error_log('CSRF-токен успешно проверен');

// Проверка корректности числовых данных
if (!isset($input['total_price']) || !is_numeric($input['total_price']) || floatval($input['total_price']) <= 0) {
    error_log('Ошибка: Неверная или отсутствующая общая сумма.');
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid total price']);
    exit();
}
$totalPrice = number_format(floatval($input['total_price']), 2, '.', '');

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
    exit("Произошла ошибка при подключении к базе данных.");
}

// Запись данных в базу данных (без total_without_discount и discount)
try {
    $stmt = $pdo->prepare("
        INSERT INTO orders (customer_email, total_price, status, created_at, customer_phone, customer_street, customer_house_number, customer_apartment, customer_floor, customer_gate_code, customer_notes, customer_fullname, customer_klatka)
        VALUES (:customer_email, :total_price, 'pending', NOW(), :customer_phone, :customer_street, :customer_house_number, :customer_apartment, :customer_floor, :customer_gate_code, :customer_notes, :customer_fullname, :customer_klatka)
    ");

    $stmt->execute([
        ':customer_email' => htmlspecialchars($input['email'] ?? ''),
        ':total_price' => $totalPrice,
        ':customer_phone' => htmlspecialchars($input['phone'] ?? ''),
        ':customer_street' => htmlspecialchars($input['street'] ?? ''),
        ':customer_house_number' => htmlspecialchars($input['house_number'] ?? ''),
        ':customer_apartment' => htmlspecialchars($input['apartment'] ?? ''),
        ':customer_floor' => htmlspecialchars($input['floor'] ?? ''),
        ':customer_gate_code' => htmlspecialchars($input['gate_code'] ?? ''),
        ':customer_notes' => htmlspecialchars($input['notes'] ?? ''),
        ':customer_fullname' => htmlspecialchars($input['fullname'] ?? ''),
        ':customer_klatka' => htmlspecialchars($input['klatka'] ?? '')
    ]);
    error_log('Запись заказа завершена успешно.');

    // Получаем ID последнего вставленного заказа
    $orderId = $pdo->lastInsertId();
    error_log('Получен ID последнего вставленного заказа: ' . $orderId);

} catch (PDOException $e) {
    error_log("Ошибка записи в базу данных: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database insert error']);
    exit();
}

// Запись данных о пакетах в таблицу order_packages
$packages = $input['packages'] ?? [];
if ($packages && is_array($packages)) {
    foreach ($packages as $package) {
        if (
            !isset($package['calories']) || !is_string($package['calories']) ||
            !isset($package['quantity']) || !is_numeric($package['quantity']) ||
            !isset($package['dates']) || !is_array($package['dates'])
        ) {
            error_log('Ошибка: Пакеты отсутствуют или данные неверного формата.');
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid package data']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO order_packages (order_id, calories, quantity)
                VALUES (:order_id, :calories, :quantity)
            ");
            $stmt->execute([
                ':order_id' => $orderId,
                ':calories' => htmlspecialchars($package['calories']),
                ':quantity' => intval($package['quantity'])
            ]);

            // Получаем последний вставленный ID для order_package
            $orderPackageId = $pdo->lastInsertId();
            error_log("Создан новый пакет с ID: " . $orderPackageId);

            // Разделение дат, если они пришли в одной строке
            $deliveryDates = [];
            foreach ($package['dates'] as $deliveryDate) {
                // Если даты объединены в строку, разбиваем её
                if (strpos($deliveryDate, ',') !== false) {
                    $deliveryDates = array_merge($deliveryDates, array_map('trim', explode(',', $deliveryDate)));
                } else {
                    $deliveryDates[] = trim($deliveryDate);
                }
            }

            // Сохранение всех дат доставки в таблице delivery_dates
            foreach ($deliveryDates as $deliveryDate) {
                error_log("Сохранение даты доставки: " . $deliveryDate);
                $stmt = $pdo->prepare("
                    INSERT INTO delivery_dates (order_package_id, delivery_date)
                    VALUES (:order_package_id, :delivery_date)
                ");
                $stmt->execute([
                    ':order_package_id' => $orderPackageId,
                    ':delivery_date' => $deliveryDate
                ]);
                error_log("Дата доставки " . $deliveryDate . " успешно сохранена для order_package_id: " . $orderPackageId);
            }
        } catch (PDOException $e) {
            error_log("Ошибка записи в базу данных пакетов: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database insert error for packages']);
            exit();
        }
    }
}

error_log("Данные заказа и пакетов успешно сохранены в базу данных.");

// Генерация нового CSRF-токена
$newCsrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $newCsrfToken;
$_SESSION['order_id'] = $orderId;
error_log("Сгенерирован новый CSRF-токен: " . $newCsrfToken);

// Формируем ответ клиенту с подтверждением успеха и новым CSRF-токеном
$response = [
    'status' => 'success',
    'message' => 'Данные заказа успешно обработаны и сохранены.',
    'new_csrf_token' => $newCsrfToken,
    'order_id' => $orderId
];
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>

<?php
// payment_summary.php

require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;
use Stripe\Stripe;
use Stripe\Checkout\Session;

session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

error_log('====== Начало работы payment_summary.php ======');
error_log('Сгенерирован новый идентификатор сессии: ' . session_id());
error_log('Данные сессии на старте: ' . print_r($_SESSION, true));

// Инициализация переменных окружения
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
error_log('Загружены переменные окружения из .env');

// Устанавливаем заголовок для JSON-ответа
header('Content-Type: application/json');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Неподдерживаемый метод запроса');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
    exit();
}

// Логируем заголовки запроса
$headers = getallheaders();
error_log('Заголовки запроса: ' . print_r($headers, true));

// Получаем и декодируем входящие данные
$rawInput = file_get_contents('php://input');
error_log('Полученные сырые данные JSON: ' . $rawInput);
$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Ошибка разбора JSON: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit();
}
error_log('Входящие данные от клиента: ' . print_r($input, true));

// Проверяем, установлен ли секретный ключ Stripe
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
if (!$stripeSecretKey) {
    error_log("Ошибка: Stripe Secret Key не установлен.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Stripe Secret Key is not set']);
    exit();
}
error_log('Секретный ключ Stripe успешно загружен');

try {
    // Устанавливаем API-ключ Stripe
    Stripe::setApiKey($stripeSecretKey);
    error_log('API ключ Stripe успешно установлен');

    // Валидация и проверка email для квитанции
    $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Неверный формат email.');
    }
    error_log('Проверенный email: ' . $email);

    // Проверка данных о сумме
    if (!isset($input['total_price']) || !is_numeric($input['total_price']) || $input['total_price'] <= 0) {
        throw new Exception('Неверная или отсутствующая общая сумма.');
    }
    // Перевод суммы в grosze (1 PLN = 100 groszy)
    $totalPrice = floatval($input['total_price']) * 100;
    error_log('Общая сумма заказа: ' . $totalPrice . ' grosze');

    // Проверяем наличие данных о пакетах
    if (empty($input['packages']) || !is_array($input['packages'])) {
        throw new Exception('Отсутствуют данные о пакетах или они не определены.');
    }
    error_log('Количество пакетов: ' . count($input['packages']));

    // Проверка наличия order_id
    $orderId = $input['order_id'] ?? null;
    if (!$orderId) {
        throw new Exception('order_id отсутствует в входящих данных');
    }
    error_log('Используемый order_id для метаданных Stripe: ' . $orderId);

    // Извлекаем информацию о скидке и сумме без скидки (для отображения)
    $discount = floatval($input['discount'] ?? 0);
    $totalWithoutDiscount = floatval($input['total_without_discount'] ?? 0);
    $discountFactor = 1;
    if ($totalWithoutDiscount > 0 && $discount > 0) {
        $discountFactor = ($totalWithoutDiscount - $discount) / $totalWithoutDiscount;
    }
    error_log('Коэффициент скидки: ' . $discountFactor);

    // Формируем массив line_items для Stripe Checkout
    $lineItems = [];
    foreach ($input['packages'] as $package) {
        // Валидация данных каждого пакета
        if (!isset($package['calories']) || !is_string($package['calories'])) {
            throw new Exception('Неверные данные: calories отсутствует или не является строкой.');
        }
        if (!isset($package['quantity']) || !is_numeric($package['quantity']) || $package['quantity'] <= 0) {
            throw new Exception('Неверные данные: quantity отсутствует, не является числом или меньше либо равно 0.');
        }
        if (!isset($package['price_per_package']) || !is_numeric($package['price_per_package']) || $package['price_per_package'] <= 0) {
            throw new Exception('Неверные данные: price_per_package отсутствует, не является числом или меньше либо равно 0.');
        }

        // Применяем скидку к цене за пакет (для отображения)
        $discountedPrice = floatval($package['price_per_package']) * $discountFactor;

        // Сбор данных о датах доставки
        $deliveryDates = '';
        if (isset($package['dates']) && is_array($package['dates'])) {
            $deliveryDates = implode(', ', array_map('htmlspecialchars', $package['dates']));
        }

        // Фильтрация данных для безопасности
        $filteredPackage = [
            'calories' => htmlspecialchars($package['calories'], ENT_QUOTES, 'UTF-8'),
            'price'    => number_format($discountedPrice, 2, '.', ''),
            'quantity' => intval($package['quantity']),
            'dates'    => $deliveryDates
        ];

        $lineItems[] = [
            'price_data' => [
                'currency'     => 'pln',
                'product_data' => [
                    'name'        => 'Paczka: ' . $filteredPackage['calories'],
                    'description' => 'Kalorie: ' . $filteredPackage['calories'] .
                                     '. Ilość: ' . $filteredPackage['quantity'] .
                                     '. Terminy dostaw: ' . $filteredPackage['dates'],
                    'images'      => ['https://foodcasecatering.net/assets/img/logo.png'],
                ],
                // Преобразуем цену за пакет в grosze
                'unit_amount'  => intval($filteredPackage['price'] * 100),
            ],
            'quantity' => $filteredPackage['quantity'],
        ];
    }
    error_log('Собранные line items для Stripe: ' . print_r($lineItems, true));

    // Создаем Stripe Checkout Session
    // Обратите внимание: параметр 'customer_email' используется для отправки клиенту электронных квитанций.
    error_log('Пытаемся создать Stripe Checkout Session...');
    $session = Session::create([
        'payment_method_types' => ['card', 'blik'],
        'line_items'           => $lineItems,
        'mode'                 => 'payment',
        'success_url'          => 'https://test1.foodcasecatering.net/payments/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => 'https://test1.foodcasecatering.net/payments/cancel.php',
        'client_reference_id'  => session_id(),
        'customer_email'       => $email,
        'metadata'             => [
            'order_id' => strval($orderId)
        ],
    ]);
    error_log('Stripe Checkout Session успешно создан. ID: ' . $session->id);

    // Отправляем ID сессии клиенту
    echo json_encode(['id' => $session->id]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Ошибка при создании Stripe Checkout Session: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Stripe API error: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    error_log('Общая ошибка: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}

error_log('====== Конец работы payment_summary.php ======');
?>
