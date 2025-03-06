<?php
// Включение отображения всех ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Логирование ошибок в файл (до запуска сессии)
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Запуск сессии для проверки авторизации
session_start();

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/admin.php');
    exit();
}

// Подключение конфигурации базы данных
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Проверяем соединение с базой данных
if (!isset($pdo) || !$pdo) {
    die("Ошибка подключения к базе данных.");
}

// Установка значений по умолчанию
$limit = 150; // Количество записей на странице
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$filterEmail = isset($_GET['email']) ? trim($_GET['email']) : '';
$filterId = isset($_GET['id']) ? trim($_GET['id']) : '';
$filterAddress = isset($_GET['address']) ? trim($_GET['address']) : '';
$filterDate = isset($_GET['delivery_date']) ? trim($_GET['delivery_date']) : '';
$filterWeek = isset($_GET['week_number']) ? trim($_GET['week_number']) : ''; // Добавлен фильтр недели
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'оплачен'; // По умолчанию "оплачен"

// Определяем день недели, если указана дата доставки
$dayOfWeek = '';
if (!empty($filterDate)) {
    $dayOfWeek = date('N', strtotime($filterDate)); // 1 (понедельник) - 7 (воскресенье)
}

// Основной SQL-запрос с фильтрами
$sql = "
    SELECT 
        o.order_id, o.customer_email, o.total_price, o.status, o.created_at, o.customer_phone, 
        o.customer_street, o.customer_house_number, o.customer_apartment, o.customer_floor, 
        o.customer_gate_code, o.customer_notes, o.customer_fullname, o.customer_klatka, o.nonce,
        op.id AS package_id, op.calories,
        GROUP_CONCAT(dd.delivery_date ORDER BY dd.delivery_date SEPARATOR ', ') AS delivery_dates
    FROM 
        orders o
    LEFT JOIN 
        order_packages op ON o.order_id = op.order_id
    LEFT JOIN 
        delivery_dates dd ON op.id = dd.order_package_id
    WHERE 1=1
";

// Применяем фильтры
if ($statusFilter === 'оплачен') {
    $sql .= " AND o.status = 'оплачен'";
}
if (!empty($filterEmail)) {
    $sql .= " AND o.customer_email LIKE :email";
}
if (!empty($filterId)) {
    $sql .= " AND o.order_id = :order_id";
}
if (!empty($filterAddress)) {
    $sql .= " AND CONCAT(o.customer_street, ' ', o.customer_house_number) LIKE :address";
}
if (!empty($filterDate)) {
    $sql .= " AND dd.delivery_date = :delivery_date";
}
if (!empty($filterWeek)) {
    $sql .= " AND WEEK(dd.delivery_date, 1) = :week_number"; // Фильтр по неделе
}

// Группировка и сортировка
$sql .= "
    GROUP BY 
        o.order_id, op.id
    ORDER BY 
        o.nonce IS NULL DESC, o.created_at DESC, o.order_id, op.id
    LIMIT :limit OFFSET :offset
";

try {
    $stmt = $pdo->prepare($sql);

    // Привязка параметров
    if (!empty($filterEmail)) {
        $stmt->bindValue(':email', "%$filterEmail%", PDO::PARAM_STR);
    }
    if (!empty($filterId)) {
        $stmt->bindValue(':order_id', $filterId, PDO::PARAM_INT);
    }
    if (!empty($filterAddress)) {
        $stmt->bindValue(':address', "%$filterAddress%", PDO::PARAM_STR);
    }
    if (!empty($filterDate)) {
        $stmt->bindValue(':delivery_date', $filterDate, PDO::PARAM_STR);
    }
    if (!empty($filterWeek)) {
        $stmt->bindValue(':week_number', $filterWeek, PDO::PARAM_INT);
    }

    // Параметры пагинации
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем общее количество заказов для пагинации
    $totalSql = "SELECT COUNT(DISTINCT o.order_id) FROM orders o";
    $totalStmt = $pdo->query($totalSql);
    $totalOrders = $totalStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);

    // Получаем информацию о пакетах для выбранной даты доставки
    $packagesInfo = [];
    $totalQuantity = 0;
    $overallDivided = 0;

    if (!empty($filterDate)) {
        $packageSql = "
            SELECT 
                op.calories AS package, 
                COUNT(dd.delivery_date) AS total_quantity
            FROM 
                order_packages op
            LEFT JOIN 
                delivery_dates dd ON op.id = dd.order_package_id
            LEFT JOIN 
                orders o ON o.order_id = op.order_id
            WHERE 
                dd.delivery_date = :delivery_date
        ";

        if ($statusFilter === 'оплачен') {
            $packageSql .= " AND o.status = 'оплачен'";
        }

        $packageSql .= "
            GROUP BY op.calories
            ORDER BY op.calories
        ";

        $packageStmt = $pdo->prepare($packageSql);
        $packageStmt->bindValue(':delivery_date', $filterDate, PDO::PARAM_STR);
        $packageStmt->execute();
        $packagesInfo = $packageStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($packagesInfo as $package) {
            $totalQuantity += $package['total_quantity'];
        }

        $overallDivided = round($totalQuantity * 4, 2);
    }

    // Получаем общее количество клиентов
    $countClientsSql = "SELECT COUNT(DISTINCT customer_email) AS total_clients FROM orders";
    $countClientsStmt = $pdo->query($countClientsSql);
    $totalClients = $countClientsStmt->fetch(PDO::FETCH_ASSOC)['total_clients'];

    // Передаем данные в JS для автозаполнения
    $autocompleteData = [];
    $autocompleteSql = "
        SELECT DISTINCT customer_email AS value 
        FROM orders 
        UNION 
        SELECT DISTINCT CONCAT(customer_street, ' ', customer_house_number) AS value 
        FROM orders
    ";
    $autocompleteStmt = $pdo->query($autocompleteSql);
    $autocompleteData = $autocompleteStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<script>window.autocompleteData = " . json_encode($autocompleteData) . ";</script>";

} catch (PDOException $e) {
    error_log("Ошибка выполнения запроса: " . $e->getMessage());
    die("Ошибка выполнения запроса.");
}
?>





<script>
    window.presetPackagesInfo = [];
</script>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Зведена таблиця замовлень - Адмін Панель</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" type="text/css" href="../assets/css/global.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/uk.js"></script>
    <script src="/assets/scriptjs/sticker_generation_poka_ne_eby_orders_summary.php.js"></script> <!-- Основний скрипт генерації наклейок -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="/assets/scriptjs/optimized_autocomplete.js"></script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; ?>

<div class="admin-container">
    <h1>Таблиця замовлень</h1>

    <!-- Основной контейнер для управления и фильтрации -->
    <div class="filter-and-control-container">

        <!-- Верхняя часть с кнопками добавления и возврата, расположенные по центру -->
        <div class="top-button-container" style="text-align: center; margin-bottom: 20px;">
            <a href="/admin/add_order.php" class="add-order-button">Додати нове замовлення</a>
            <a href="/admin/admin_panel.php" class="btn-return">Повернутися в адмін-панель</a>
        </div>

        <!-- Форма фильтрации и генерации наклеек -->
 

<form method="GET" class="filter-form">
    <div class="form-left-section" style="float: left; margin-bottom: 15px;">
        <input type="text" id="id-filter" name="id" placeholder="ID Замовлення" value="<?= htmlspecialchars($filterId) ?>">
        <input type="text" id="email-filter" name="email" placeholder="Email Клієнта" value="<?= htmlspecialchars($filterEmail) ?>">
        <input type="text" id="address-filter" name="address" placeholder="Адреса Клієнта" value="<?= htmlspecialchars($filterAddress) ?>">
        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>"> <!-- Скрытое поле -->
        <button type="submit" class="filter-button">Фільтрувати</button>
        <button type="button" onclick="window.location.href='orders_summary.php'" class="filter-button">Скинути</button>
    </div>
</form>

<form class="filter-form" method="GET" action="">
    <div class="status-filter-container" style="text-align: center; margin-bottom: 15px;">
        <label>
            <input type="radio" name="status_filter" value="all" <?= (isset($statusFilter) && $statusFilter === 'all') ? 'checked' : '' ?>>
            Всі замовлення
        </label>
        <label>
            <input type="radio" name="status_filter" value="оплачен" <?= (!isset($statusFilter) || $statusFilter === 'оплачен') ? 'checked' : '' ?>>
            Тільки оплачені
        </label>
    </div>
</form>



            <!-- Правый блок формы: поля выбора даты и недели + кнопка генерации наклеек -->
            <div class="form-right-section" style="float: right;">
                <input type="text" id="delivery_date" name="delivery_date" placeholder="Дата Доставки" value="<?= htmlspecialchars($filterDate) ?>">
<select name="week_number" id="week_number" required>
    <option value="">Виберіть неделю</option>
    <option value="1" <?= $filterWeek == "1" ? 'selected' : '' ?>>Неделя 1</option>
    <option value="2" <?= $filterWeek == "2" ? 'selected' : '' ?>>Неделя 2</option>
    <option value="3" <?= $filterWeek == "3" ? 'selected' : '' ?>>Неделя 3</option>
    <option value="4" <?= $filterWeek == "4" ? 'selected' : '' ?>>Неделя 4</option>
</select>
                    <!-- Кнопка для генерации наклеек -->
                    <a href="/admin/sticker.html" target="_blank">
                        <button id="generate-stickers" class="generate-stickers-button">Генерація Наклеек</button>
                    </a>
                    <a href="/admin/sticker_paket.html" id="generate-package-stickers-link">
                        <button class="generate-package-stickers-button">Генерация Наклеек на Пакеты</button>
                    </a>
            </div>

            <div style="clear: both;"></div> <!-- Очищаем флоаты для корректного отображения -->
        </form>
    </div>
</div>


<div class="packages-info">
    <?php if (!empty($packagesInfo)): ?>
        <h2>Інформація про Пакети на доставку в обраний день</h2>
        <table class="order-summary-table">
            <thead>
                <tr>
                    <th>Пакет</th>
                    <th>Кількість</th>
                    <th>Загальна кількість Пакетів</th>
                    <th>Загальна кількість наклейок на Судочки.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packagesInfo as $index => $package): ?>
                    <tr>
                        <td><?= htmlspecialchars($package['package']) ?></td>
                        <td><?= htmlspecialchars($package['total_quantity']) ?> шт</td>
                        <?php if ($index === 0): ?> <!-- Отображаем общее количество только в первой строке -->
                            <td rowspan="<?= count($packagesInfo) ?>">
                                <?= htmlspecialchars($totalQuantity) ?> шт
                            </td>
                            <td rowspan="<?= count($packagesInfo) ?>">
                                <?= htmlspecialchars($overallDivided) ?> шт
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>


        <!-- Права інформація о кількості клієнтів -->
        <div class="client-count-inline">
            Загальна кількість клієнтів: <?= htmlspecialchars($totalClients) ?> <!-- Отображаем загальну кількість клієнтів справа -->
        </div>
    </div>

<?php if (!empty($result)): ?>
    <table class="order-summary-table">
        <thead>
            <tr>
                <th>№</th>
                <th>ID</th>
                <th>Повне ім'я клієнта</th>
                <th>Телефон</th>
                <th>Калорійність</th>
                <th>Вулиця</th>
                <th>Будинок</th>
                <th>Під'їзд</th>
                <th>Поверх</th>
                <th>Квартира</th>
                <th class="narrow-column">Код дверей</th>
                <th>Примітки</th>
                <th class="narrow-info">Інформація про Пакети</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($result as $index => $row): ?>
                <tr id="row-<?= $row['order_id'] ?>" class="<?= empty($row['nonce']) ? 'new-order' : 'viewed-order' ?>">
                    <td><?= $index + 1 ?></td> <!-- Добавлено: порядковый номер клиента -->
                    <td><?= htmlspecialchars($row['order_id']) ?></td>
                    <td><?= htmlspecialchars($row['customer_fullname']) ?></td>
                    <td><?= htmlspecialchars($row['customer_phone']) ?></td>
                    <td><?= htmlspecialchars($row['calories']) ?></td>
                    <td><?= htmlspecialchars($row['customer_street']) ?></td>
                    <td><?= htmlspecialchars($row['customer_house_number']) ?></td>
                    <td><?= htmlspecialchars($row['customer_klatka']) ?></td>
                    <td><?= htmlspecialchars($row['customer_floor']) ?></td>
                    <td><?= htmlspecialchars($row['customer_apartment']) ?></td>
                    <td class="narrow-column"><?= htmlspecialchars($row['customer_gate_code']) ?></td>
                    <td><?= htmlspecialchars($row['customer_notes']) ?></td>
                    <td class="narrow-info">
                        <a class="details-button" style="display: block; text-align: center;" onclick="showDetailsModal(<?= $row['order_id'] ?>)">Деталі</a>
                        <div id="details-<?= $row['order_id'] ?>" class="hidden-details" style="display: none;">
                            <strong>Email:</strong> <?= htmlspecialchars($row['customer_email']) ?><br>
                            <strong>Загальна Сума:</strong> <?= htmlspecialchars($row['total_price']) ?><br>
                            <strong>Статус:</strong> <?= htmlspecialchars($row['status']) ?><br>
                            <strong>Дата Створення:</strong> <?= htmlspecialchars($row['created_at']) ?><br>
                            <strong>Дати Доставки:</strong> <?= htmlspecialchars($row['delivery_dates']) ?><br>
                        </div>
                    </td>
                    <td>
                        <button class="edit-button" onclick="editOrder(<?= $row['order_id'] ?>)">Редагувати</button>
                        <button class="delete-button" onclick="openDeleteModal(<?= $row['order_id'] ?>)">Видалити</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Немає даних для відображення.</p>
<?php endif; ?>
</div>



    <!-- Пагінація -->
    <div class="pagination">
        <?php
        // Создаємо строку с параметрами фільтрів для передачі на інші сторінки
        $queryString = http_build_query(array_merge($_GET, ['page' => null]));

        // Генерація ссілки на попередню сторінку
        if ($page > 1): ?>
            <a href="?<?= $queryString ?>&page=<?= $page - 1 ?>">« Попередня</a>
        <?php endif;

        // Генерація ссілок на всі сторінки
        for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= $queryString ?>&page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor;

        // Генерація ссілки на наступну сторінку
        if ($page < $totalPages): ?>
            <a href="?<?= $queryString ?>&page=<?= $page + 1 ?>">Наступна »</a>
        <?php endif; ?>
    </div>

    <!-- Контейнер для наклеек -->
    <div id="stickers-container" class="sticker-container"></div>
    <button id="print-stickers" style="display: none;">Печать Наклеек</button>
</div>


<!-- Модальне вікно для Деталей -->
<div class="modal-overlay" id="modal-overlay" style="display: none;" onclick="closeDetailsModal()"></div>
<div class="modal" id="modal-details" style="display: none;">
    <div class="modal-header">
        <h3>Деталі Замовлення</h3>
        <span class="modal-close" onclick="closeDetailsModal()">&times;</span>
    </div>
    <div class="modal-content" id="modal-content"></div>
</div>

<!-- Модальне вікно для Підтвердження Видалення -->
<div class="modal-overlay" id="modal-delete-overlay" style="display: none;" onclick="closeDeleteModal()"></div>
<div class="modal" id="modal-delete" style="display: none;">
    <div class="modal-header">
        <h3>Ви впевнені, що хочете видалити це замовлення?</h3>
        <span class="modal-close" onclick="closeDeleteModal()">&times;</span>
    </div>
    <div class="modal-content">
        <div class="modal-delete-buttons">
            <button id="confirm-delete" class="delete-button">Так, видалити</button>
            <button class="edit-button" onclick="closeDeleteModal()">Скасувати</button>
        </div>
    </div>
</div>

<script>
// Определяем текущую версию сайта
const currentVersion = 'v1.3.0'; // Измените версию на новую при каждом обновлении
document.addEventListener('DOMContentLoaded', function () {
    // Инициализация календаря для выбора даты с использованием flatpickr
    flatpickr("#delivery_date", {
        dateFormat: "Y-m-d",
        locale: "uk",
        weekStart: 1, // Начало недели с понедельника
        onChange: function (selectedDates, dateStr) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('delivery_date', dateStr);

            // Добавляем текущий фильтр статуса в URL
            const statusFilter = document.querySelector('input[name="status_filter"]:checked').value;
            currentUrl.searchParams.set('status_filter', statusFilter);

            window.location.href = currentUrl.toString();
        }
    });

    // Функция для настройки автозаполнения
    function setupDynamicAutocomplete(inputId, sourceUrl) {
        const inputField = document.getElementById(inputId);
        if (inputField) {
            $(inputField).autocomplete({
                source: function (request, response) {
                    $.ajax({
                        url: sourceUrl, // URL для получения данных
                        method: 'GET',
                        data: { term: request.term }, // Отправляем введенный текст
                        success: function (data) {
                            if (Array.isArray(data)) {
                                const results = data.map(item => ({
                                    label: item.label || item.value,
                                    value: item.value
                                }));
                                response(results);
                            } else {
                                console.error("Ошибка: ожидался массив объектов.");
                                response([]);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error(`Ошибка при загрузке данных для автозаполнения: ${error}`);
                            response([]);
                        }
                    });
                },
                minLength: 2,
                select: function (event, ui) {
                    console.log(`Выбрано: ${ui.item.label} (${ui.item.value})`);
                }
            });
        } else {
            console.warn(`Поле с ID "${inputId}" не найдено.`);
        }
    }

    // Настраиваем автозаполнение для email и адреса
    setupDynamicAutocomplete('email-filter', '/admin/fetch_orders_summary.php');
    setupDynamicAutocomplete('address-filter', '/admin/fetch_orders_summary.php');

    // Обработчик для кнопки "Генерация Наклеек на Пакеты"
    document.getElementById('generate-package-stickers-link').addEventListener('click', function (e) {
        e.preventDefault();

        const deliveryDate = document.getElementById('delivery_date').value;

        if (!deliveryDate) {
            alert("Пожалуйста, выберите дату доставки.");
            return;
        }

        const packageStickerData = {
            delivery_date: deliveryDate,
            packages: []
        };

        const packageRows = document.querySelectorAll('.packages-info table tbody tr');
        const packageMap = new Map();

        packageRows.forEach(row => {
            const packageTypeElem = row.querySelector('td:nth-child(1)');
            const quantityElem = row.querySelector('td:nth-child(2)');

            if (!packageTypeElem || !quantityElem) {
                console.warn("Пропущена строка из-за отсутствия данных", row);
                return;
            }

            const packageType = packageTypeElem.innerText.trim();
            const quantity = parseInt(quantityElem.innerText.trim(), 10);

            if (!quantity || quantity <= 0) {
                console.warn("Пропущена строка с некорректным количеством", { packageType, quantity });
                return;
            }

            packageMap.set(packageType, quantity);
        });

        const orderRows = document.querySelectorAll('.order-summary-table tbody tr');
        if (!orderRows || orderRows.length === 0) {
            alert("Нет данных в таблице заказов!");
            console.error("Таблица заказов пуста.");
            return;
        }

        const packageCountTracker = new Map();

        orderRows.forEach(row => {
            const streetElem = row.querySelector('td:nth-child(6)');
            const houseElem = row.querySelector('td:nth-child(7)');
            const apartmentElem = row.querySelector('td:nth-child(10)');
            const caloriesElem = row.querySelector('td:nth-child(5)');

            if (!streetElem || !houseElem || !caloriesElem) {
                console.warn("Пропущена строка из-за отсутствия данных", row);
                return;
            }

            const street = streetElem.innerText.trim() || "Не указано";
            const house = houseElem.innerText.trim() || "Не указано";
            const apartment = apartmentElem ? apartmentElem.innerText.trim() : "";
            const calories = caloriesElem.innerText.trim();

            const address = `${street} ${house}${apartment ? " / " + apartment : ""}`;

            if (!packageMap.has(calories)) {
                console.warn("Пропущена строка, так как калорийность не указана в таблице пакетов", calories);
                return;
            }

            const maxQuantity = packageMap.get(calories);

            if (!packageCountTracker.has(address)) {
                packageCountTracker.set(address, new Map());
            }

            const addressTracker = packageCountTracker.get(address);
            if (!addressTracker.has(calories)) {
                addressTracker.set(calories, 0);
            }

            const currentCount = addressTracker.get(calories);

            if (currentCount < maxQuantity) {
                packageStickerData.packages.push({
                    package_type: calories,
                    address: address
                });
                addressTracker.set(calories, currentCount + 1);
            }
        });

        if (!packageStickerData.packages.length) {
            alert("Нет данных для генерации наклеек!");
            return;
        }

        localStorage.setItem('packageStickerData', JSON.stringify(packageStickerData));
        window.open('/admin/sticker_paket.html', '_blank');
    });

    // Добавляем обработчик события для всех радиокнопок фильтрации по статусу
    const statusFilterRadios = document.querySelectorAll('input[name="status_filter"]');
    statusFilterRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('status_filter', this.value);

            const deliveryDate = document.getElementById('delivery_date').value;
            if (deliveryDate) {
                currentUrl.searchParams.set('delivery_date', deliveryDate);
            }

            const weekNumber = document.getElementById('week_number').value;
            if (weekNumber) {
                currentUrl.searchParams.set('week_number', weekNumber);
            }

            window.location.href = currentUrl.toString();
        });
    });

    // Добавляем обработчик для строк таблицы заказов (кликабельность)
    const orderTable = document.querySelector('.order-summary-table');
    if (orderTable) {
        orderTable.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('click', function () {
                row.classList.remove('new-order');
                row.classList.add('viewed-order');

                const orderId = row.getAttribute('id')?.replace('row-', '');
                if (orderId) {
                    markOrderAsViewed(orderId);
                }
            });
        });
    }

    // Функция для отправки AJAX-запроса для обновления статуса
    function markOrderAsViewed(orderId) {
        $.ajax({
            url: '/admin/mark_order_viewed.php',
            type: 'POST',
            data: { order_id: orderId },
            success: function (response) {
                if (response !== 'success') {
                  
                }
            },
            error: function () {
                alert("Сталася помилка при спробі оновити статус замовлення.");
            }
        });
    }

    // Добавляем обработчик для подтверждения удаления заказа
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            const orderId = this.getAttribute('data-order-id');
            if (orderId) {
                $.ajax({
                    url: '/admin/remove_orders_summary.php',
                    type: 'POST',
                    data: { order_id: orderId },
                    success: function (response) {
                        if (response === 'success') {
                            document.getElementById('row-' + orderId).remove();
                            closeDeleteModal();
                        } else {
                            alert("Помилка при видаленні замовлення.");
                        }
                    },
                    error: function () {
                        alert("Сталася помилка при спробі видалити замовлення.");
                    }
                });
            }
        });
    }

    // Функция для закрытия модального окна удаления
    function closeDeleteModal() {
        document.getElementById('modal-delete-overlay').style.display = 'none';
        document.getElementById('modal-delete').style.display = 'none';
    }
});


// Функции для управления модальными окнами и статусами
function showDetailsModal(orderId) {
    const detailsElement = document.getElementById('details-' + orderId);
    const modalContent = document.getElementById('modal-content');
    modalContent.innerHTML = detailsElement.innerHTML;

    document.getElementById('row-' + orderId).classList.add('highlight-row');
    document.getElementById('modal-overlay').style.display = 'block';
    document.getElementById('modal-details').style.display = 'block';
}

function closeDetailsModal() {
    document.getElementById('modal-overlay').style.display = 'none';
    document.getElementById('modal-details').style.display = 'none';

    const highlightedRows = document.querySelectorAll('.highlight-row');
    highlightedRows.forEach(row => {
        row.classList.remove('highlight-row');
    });
}

function openDeleteModal(orderId) {
    document.getElementById('confirm-delete').setAttribute('data-order-id', orderId);
    document.getElementById('modal-delete-overlay').style.display = 'block';
    document.getElementById('modal-delete').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('modal-delete-overlay').style.display = 'none';
    document.getElementById('modal-delete').style.display = 'none';
}

function editOrder(orderId) {
    window.location.href = '/admin/edit_orders_summary.php?order_id=' + orderId;
}
</script>



<style>
/* Стили для новых заказов */
.new-order {
    background-color: #e8f5e9; /* Зеленый фон */
    animation: highlightNewOrder 3s ease-in-out infinite alternate;
}

/* Стили для просмотренных заказов */
.viewed-order {
    background-color: #ffffff; /* Белый фон */
    animation: none;
}

/* Анимация для новых заказов */
@keyframes highlightNewOrder {
    0% {
        background-color: #e8f5e9;
    }
    100% {
        background-color: #c8e6c9;
    }
}


    /* Стили для кнопки "Деталі" */
    .details-button {
        background-color: #007bff; /* Синий фон */
        color: #fff; /* Белый текст */
        border: none; /* Убираем границы */
        padding: 5px 10px; /* Отступы внутри кнопки */
        border-radius: 4px; /* Скругленные углы */
        text-align: center; /* Центрируем текст */
        text-decoration: none; /* Убираем подчеркивание текста */
        font-size: 14px; /* Размер шрифта */
        cursor: pointer; /* Указатель при наведении */
        transition: background-color 0.3s ease; /* Плавный переход цвета фона */
    }

    /* Стили при наведении на кнопку "Деталі" */
    .details-button:hover {
        background-color: #0056b3; /* Более темный синий при наведении */
    }

    /* Стили при клике на кнопку "Деталі" */
    .details-button:active {
        background-color: #004494; /* Ещё темнее при клике */
    }

    /* Центрирование содержимого ячейки */
    .center-cell {
        text-align: center; /* Горизонтальное центрирование */
        vertical-align: middle; /* Вертикальное центрирование */
    }
    /* Стили для информации о количестве клиентов */
    .client-count-inline {
        text-align: right; /* Выравнивание текста справа */
        font-size: 1em; /* Размер текста */
        font-weight: bold; /* Немного выделить текст */
        margin-top: 10px; /* Отступ сверху, чтобы немного отделить от таблицы */
    }
    /* Стили для уменьшения ширины таблицы */
    .packages-info table.order-summary-table {
        width: 390px; /* Регулируем ширину для уменьшения */
        margin: 0 auto; /* Центрирование таблицы по горизонтали */
    }

    /* Уменьшаем отступы внутри ячеек */
    .packages-info table.order-summary-table th,
    .packages-info table.order-summary-table td {
        padding: 5px; /* Уменьшение отступов для компактного вида */
    }

    /* Уменьшаем размер шрифта заголовка */
    .packages-info h2 {
        font-size: 1.2em; /* Можно регулировать для нужного размера заголовка */
        text-align: center; /* Выравниваем заголовок по центру */
    }

    /* Уменьшаем общий размер шрифта таблицы для большей компактности */
    .packages-info table.order-summary-table {
        font-size: 0.9em; /* Меньший размер шрифта таблицы */
    }
        /* Настройка ширины колонок таблицы */
        .order-summary-table th:nth-child(7),
        .order-summary-table td:nth-child(7) {
            width: 80px; /* Уже колонка для "Код дверей" */
        }

        .order-summary-table th:last-child,
        .order-summary-table td:last-child {
            width: 250px; /* Более широкая колонка для "Дії" */
        }

        .order-summary-table td button {
            margin-right: 5px; /* Расстояние между кнопками для визуального комфорта */
        }
       /* Сужение колонки "Інформація про Пакети" */
    .narrow-info {
        width: 120px; /* Можно задать нужное значение ширины */
        max-width: 120px; /* Ограничение максимальной ширины */
        text-align: center; /* Центрирование содержимого для более аккуратного вида */
        overflow: hidden; /* Прячет часть текста, если она слишком длинная */
        text-overflow: ellipsis; /* Добавляет "..." в случае, если текст не помещается */
    }

    /* Сужение колонки "Код дверей" */
    .narrow-column {
        width: 70px; /* Настройте ширину по своему усмотрению */
        text-align: center; /* Центрирование содержимого */
    }
.info-container {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: nowrap;
    flex-direction: row-reverse;
}

.packages-info {
    flex: 1;
}

.client-count {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    padding: 10px;
    background: #f7f7f7;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
/* Стили для управления размерами и выравниванием */
body {
    font-family: Arial, sans-serif;
    background-color: #f7f7f7;
    margin: 0;
    padding: 0;
}

.admin-container {
    width: 100%;
    max-width: none;
    margin: 0 auto;
    padding: 20px;
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

h1 {
    font-size: 24px;
    color: #1565c0;
    margin-bottom: 20px;
    text-align: center;
}

/* Контейнер для фильтрации и управления */
.filter-and-control-container {
    width: 100%;
    margin-bottom: 20px;
}

/* Верхняя часть с кнопками добавления и возвращения */
.top-button-container {
    text-align: center;
    margin-bottom: 20px;
}


.btn-return {
    padding: 8px 15px; /* Сделали кнопки компактнее */
    background-color: #1565c0;
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    transition: background-color 0.3s ease;
    margin: 5px;
}


.btn-return:hover {
    background-color: #003c8f;
}
.add-order-button {
    padding: 12px 20px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
}

.add-order-button:hover {
    transform: scale(1.05);
    box-shadow: 0px 0px 20px rgba(40, 167, 69, 0.5);
}


/* Форма фильтрации */
.filter-form {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

/* Левый блок формы: поля фильтрации по ID и Email, кнопки фильтрации и сброса */
.form-left-section {
    float: left;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.form-left-section input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 150px;
}

.filter-button {
    padding: 8px 15px;
    background-color: #1565c0;
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    transition: background-color 0.3s ease;
}

.filter-button:hover {
    background-color: #003c8f;
}

/* Центр: радио-кнопки для фильтрации по статусу */
.status-filter-container {
    text-align: center;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.status-filter-container label {
    font-size: 14px;
}

/* Правый блок формы: поля выбора даты и недели + кнопка генерации наклеек */
.form-right-section {
    float: right;
    display: flex;
    align-items: center;
    gap: 10px;
}

#delivery_date {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 150px;
}

#week_number {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 150px;
}

.generate-stickers-button {
    padding: 8px 15px;
    background-color: #1565c0;
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: bold;
    text-align: center;
    transition: background-color 0.3s ease;
}

.generate-stickers-button:hover {
    background-color: #003c8f;
}

/* Очистка float */
.clear {
    clear: both;
}


/* Таблица заказов */
.order-summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.order-summary-table th,
.order-summary-table td {
    border: 1px solid #ddd;
    padding: 10px 12px; /* Сделали таблицу компактнее */
    text-align: left;
    font-size: 14px;
}

.order-summary-table th {
    background-color: #f2f2f2;
    font-weight: bold;
}

.order-summary-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.order-summary-table tr.new-order {
    background-color: #e8f5e9;
    animation: highlightNewOrder 3s ease-in-out infinite alternate;
}

@keyframes highlightNewOrder {
    0% {
        background-color: #e8f5e9;
    }
    100% {
        background-color: #c8e6c9;
    }
}

.order-summary-table tr.viewed-order {
    animation: none;
    background-color: #ffffff;
}

.order-summary-table tr:hover {
    background-color: #f1f1f1;
}

/* Пагинация */
.pagination {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.pagination a {
    color: #00796b;
    padding: 6px 12px; /* Сделали кнопки пагинации компактнее */
    text-decoration: none;
    margin: 0 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pagination a.active {
    background-color: #004d40;
    color: white;
    border: 1px solid #004d40;
}

.pagination a:hover:not(.active) {
    background-color: #e0f2f1;
}

/* Кнопки для редактирования и удаления */
.edit-button,
.delete-button {
    padding: 8px 12px; /* Сделали кнопки компактнее */
    border: none;
    border-radius: 30px;
    background-color: #1565c0;
    color: white;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s ease;
    margin: 5px;
}

.edit-button:hover {
    background-color: #003c8f;
}

.delete-button {
    background-color: #d32f2f;
}

.delete-button:hover {
    background-color: #b71c1c;
}

/* Модальные окна */
.modal {
    position: fixed;
    top: 50%; /* Изменено: Установили по центру экрана по вертикали */
    left: 50%; /* Изменено: Установили по центру экрана по горизонтали */
    transform: translate(-50%, -50%); /* Изменено: Центрируем окно по X и Y */
    width: 40%; /* Можно оставить так же, или скорректировать по необходимости */
    max-width: 500px; /* Ограничили максимальную ширину */
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    display: none;
    z-index: 1000;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    animation: fadeInModal 0.5s;
}

@keyframes fadeInModal {
    0% {
        transform: translate(-50%, -70%);
        opacity: 0;
    }
    100% {
        transform: translate(-50%, -50%);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    margin-bottom: 15px;
}

.modal-header h3 {
    margin: 0;
    color: #1565c0;
}

.modal-content {
    padding: 10px 0;
    font-size: 14px;
    max-height: 300px; /* Ограничили максимальную высоту модального окна */
    overflow-y: auto; /* Добавили прокрутку для модального окна */
}

.modal-close {
    font-size: 1.5rem;
    color: #d32f2f;
    cursor: pointer;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #b71c1c;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.3);
    display: none;
    z-index: 500;
    display: flex; /* Добавлено: Flex для центровки */
    justify-content: center; /* Добавлено: Центрируем по горизонтали */
    align-items: center; /* Добавлено: Центрируем по вертикали */
}

.modal-delete-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.modal-delete-buttons button {
    width: 48%;
}

@media screen and (max-width: 768px) {
    .filter-container {
        grid-template-columns: 1fr;
    }

    .filter-form {
        flex-direction: column;
    }

    .filter-container .generate-stickers-container {
        align-items: center;
    }

    .order-summary-table th,
    .order-summary-table td {
        padding: 8px;
    }

    .pagination a {
        padding: 6px 12px;
        font-size: 12px;
    }
}


</style>


</body>
</html>
