<?php


session_start();

 // Проверка авторизации
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/admin.php');
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
header('Content-Type: text/html; charset=UTF-8');

if (!$pdo) {
    die("Ошибка подключения к базе данных");
}

// Обработка генерации наклеек
if (isset($_GET['generate_stickers'])) {
    header('Content-Type: application/json');
    try {
        $date_to_filter = $_GET['delivery_date'] ?? '';
        if (empty($date_to_filter)) {
            throw new Exception("Дата не указана.");
        }

        $sql = "
            SELECT
                oi.category AS package,
                mo.dish_name AS title_text,
                mo.dish_name AS ingredients,
                COALESCE(NULLIF(mo.dish_allergens, ''), 'Brak danych') AS allergens,
                COALESCE(mo.dish_energy, 0) AS energy,
                COALESCE(mo.dish_fat, 0) AS fat,
                COALESCE(mo.dish_carbohydrates, 0) AS carbohydrates,
                COALESCE(mo.dish_protein, 0) AS protein,
                COALESCE(mo.dish_salt, 0) AS salt,
                DATE_FORMAT(od.delivery_date, '%Y-%m-%d') AS delivery_date,
                oi.menu_options_id AS menu_options_id,
                oi.weight AS weight,
                o.order_id AS order_id
            FROM
                customer_menu_order_items oi
            INNER JOIN
                customer_menu_order_days od ON oi.order_day_id = od.order_day_id
            INNER JOIN
                customer_menu_orders o ON od.order_id = o.order_id
            LEFT JOIN
                menu_options mo ON oi.menu_options_id = mo.menu_options_id
            WHERE
                od.delivery_date = :delivery_date
            ORDER BY
                CASE oi.category
                    WHEN 'śniadanie' THEN 1
                    WHEN 'obiad' THEN 2
                    WHEN 'kolacja' THEN 3
                    ELSE 4
                END,
                oi.item_id ASC;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':delivery_date' => $date_to_filter]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$orders) {
            throw new Exception("Нет данных для даты: $date_to_filter");
        }

        $packages = [];
        foreach ($orders as $order) {
            $packages[] = [
    "package" => htmlspecialchars($order['package'] ?? 'Brak danych'),
    "title_text" => htmlspecialchars($order['title_text'] ?? 'Brak danych'),
    "ingredients" => htmlspecialchars($order['ingredients'] ?? 'Brak danych'),
    "allergens" => htmlspecialchars($order['allergens'] ?? 'Brak danych'),
    "energy" => number_format((float)$order['energy'], 2),
    "fat" => number_format((float)$order['fat'], 2),
    "carbohydrates" => number_format((float)$order['carbohydrates'], 2),
    "protein" => number_format((float)$order['protein'], 2),
    "salt" => number_format((float)$order['salt'], 2),
    "delivery_date" => $order['delivery_date'],
    "menu_options_id" => htmlspecialchars($order['menu_options_id'] ?? 'Brak danych'),
    "weight" => htmlspecialchars($order['weight'] ?? 'не указано'),  // ✅ Добавил weight
    "order_id" => htmlspecialchars($order['order_id'] ?? 'не указано')  // ✅ Добавил order_id
];

        }

        echo json_encode([
            "delivery_date" => $date_to_filter,
            "packages" => $packages
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

        error_log(json_encode($packages));

    } catch (Exception $e) {
        error_log("Ошибка генерации наклеек: " . $e->getMessage());
        echo json_encode([
            "error" => $e->getMessage(),
            "details" => "Проверьте правильность даты и наличие данных в БД"
        ]);
    }
    exit;
}

// Оригинальный функционал админ-панели
$status_filter = $_GET['status_filter'] ?? 'all';
$order_id_filter = trim($_GET['order_id'] ?? '');
$email_filter = trim($_GET['email'] ?? '');
$date_from_filter = trim($_GET['date_from'] ?? '');
$date_to_filter = trim($_GET['delivery_date'] ?? '');
$address_filter = trim($_GET['address'] ?? '');

$whereClauses = [];
$params = [];

if ($status_filter === 'paid') {
    $whereClauses[] = "o.status = 'paid'";
}
if (!empty($order_id_filter)) {
    $whereClauses[] = "o.order_id = :order_id";
    $params[':order_id'] = $order_id_filter;
}
if (!empty($email_filter)) {
    $whereClauses[] = "o.delivery_email LIKE :email";
    $params[':email'] = "%{$email_filter}%";
}
if (!empty($date_from_filter) && !empty($date_to_filter)) {
    $whereClauses[] = "od.delivery_date BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from_filter;
    $params[':date_to'] = $date_to_filter;
} elseif (!empty($date_from_filter)) {
    $whereClauses[] = "od.delivery_date >= :date_from";
    $params[':date_from'] = $date_from_filter;
} elseif (!empty($date_to_filter)) {
    $whereClauses[] = "od.delivery_date = :date_to";
    $params[':date_to'] = $date_to_filter;
}
if (!empty($address_filter)) {
    $whereClauses[] = "o.street LIKE :address";
    $params[':address'] = "%{$address_filter}%";
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$sql = "
    SELECT
        o.order_id,
        o.full_name,
        o.phone,
        o.delivery_email,
        o.street,
        o.house_number,
        o.building,
        o.floor,
        o.apartment,
        o.entry_code,
        o.notes,
        o.total_price,
        o.order_date,
        DATE_FORMAT(od.delivery_date, '%Y-%m-%d') AS delivery_date,
        o.nonce_menu,
        GROUP_CONCAT(
            CONCAT(
                CASE
                    WHEN oi.category = 'śniadanie' THEN 'S'
                    WHEN oi.category = 'obiad' THEN 'O'
                    WHEN oi.category = 'kolacja' THEN 'K'
                    ELSE oi.category
                END,
                '-', oi.menu_options_id,
                '-', oi.weight,
                '-', o.order_id
            ) ORDER BY oi.weight ASC SEPARATOR ' | '
        ) AS items_info
    FROM customer_menu_orders o
    INNER JOIN customer_menu_order_days od ON o.order_id = od.order_id
    INNER JOIN customer_menu_order_items oi ON od.order_day_id = oi.order_day_id
    $whereSQL
    GROUP BY o.order_id, od.delivery_date
    ORDER BY o.order_date DESC, od.delivery_date ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$packageCounts = ['S' => [], 'O' => [], 'K' => []];
$totalCount = 0;

if (!empty($date_to_filter)) {
    $sqlPackages = "
        SELECT
            CASE
                WHEN oi.category = 'śniadanie' THEN 'S'
                WHEN oi.category = 'obiad' THEN 'O'
                WHEN oi.category = 'kolacja' THEN 'K'
                ELSE oi.category
            END AS category,
            COUNT(*) AS count,
            GROUP_CONCAT(
                CONCAT(
                    CASE
                        WHEN oi.category = 'śniadanie' THEN 'S'
                        WHEN oi.category = 'obiad' THEN 'O'
                        WHEN oi.category = 'kolacja' THEN 'K'
                        ELSE oi.category
                    END,
                    '-', oi.menu_options_id,
                    '-', oi.weight,
                    '-', o.order_id
                ) ORDER BY oi.weight ASC SEPARATOR ' | '
            ) AS items_info
        FROM customer_menu_orders o
        INNER JOIN customer_menu_order_days od ON o.order_id = od.order_id
        INNER JOIN customer_menu_order_items oi ON od.order_day_id = oi.order_day_id
        WHERE od.delivery_date = :delivery_date
        GROUP BY category
        ORDER BY category
    ";
    $stmtPackages = $pdo->prepare($sqlPackages);
    $stmtPackages->execute([':delivery_date' => $date_to_filter]);
    $packageGroups = $stmtPackages->fetchAll(PDO::FETCH_ASSOC);

    foreach ($packageGroups as $group) {
        $category = $group['category'];
        $count = (int)$group['count'];
        $totalCount += $count;
        $items = explode(' | ', $group['items_info']);
        $packageCounts[$category] = $items;
        $packageCounts["{$category}_count"] = $count;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сводная таблица заказов - Админ Панель</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/global.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/uk.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="/assets/scriptjs/optimized_autocomplete.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .h-size { font-size: 22px; padding-top: 70px }
        main .order-summary-table-filter { width: 700px; margin: 0 auto 20px }
        .modal-content-del { display: flex; justify-content: space-evenly }
        .modal-content-suc { display: flex; flex-direction: column }
        .modal-delete-buttons { display: flex; gap: 40px }
        .ok { width: 70px }
        .success-text { background-color: #1da81d; border-radius: 6px; padding: 6px; color: #fff }
        @media (max-width: 1024px) {
            .admin-container { padding: 10px }
            .order-summary-table { font-size: 14px }
            .order-summary-table th, .order-summary-table td { padding: 8px }
            .filter-buttons input, .form-right-section input { width: 100%; max-width: 200px }
            .form-right-section { flex-wrap: wrap; justify-content: center }
            .filter-buttons { flex-wrap: wrap; justify-content: center }
            .filter-button, .btn-return, .add-order-button { font-size: 14px; padding: 10px }
        }
        @media (max-width: 768px) {
            .admin-container { padding: 5px }
            .order-summary-table { display: block; overflow-x: auto; white-space: nowrap }
            .order-summary-table th, .order-summary-table td { font-size: 12px; padding: 6px }
            .filter-buttons { flex-direction: column; align-items: center }
            .form-right-section { flex-direction: column; align-items: center }
            .filter-buttons input, .form-right-section input { width: 100%; max-width: 250px }
            .filter-button, .btn-return, .add-order-button { width: 100%; max-width: 300px; font-size: 12px; padding: 8px }
            .details-button, .edit-button, .delete-button { font-size: 12px; padding: 6px }
            .modal { width: 95%; max-width: 400px }
        }
        @media (max-width: 480px) {
            .admin-container { padding: 2px }
            .order-summary-table th, .order-summary-table td { font-size: 10px; padding: 4px }
            .filter-buttons { flex-direction: column; gap: 5px }
            .form-right-section { flex-direction: column; gap: 5px }
            .filter-buttons input, .form-right-section input { width: 100%; max-width: 200px; font-size: 12px }
            .filter-button, .btn-return, .add-order-button { font-size: 10px; padding: 6px }
            .details-button, .edit-button, .delete-button { font-size: 10px; padding: 5px }
            .modal { width: 98%; max-width: 350px }
            .modal-header h3 { font-size: 14px }
            .modal-close { font-size: 1.2rem }
        }
        body { font-family: Arial, sans-serif; background-color: #f7f7f7; margin: 0; padding: 0 }
        .admin-container { width: 100%; max-width: none; margin: 0 auto; padding: 0; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) }
        .form-right-section .generate-stickers-button { padding: 8px 15px; background-color: #1565c0; color: #fff; border: none; border-radius: 30px; cursor: pointer; font-weight: 700; text-align: center; transition: background-color 0.3s ease }
        .form-right-section .generate-stickers-button:hover { background-color: #003c8f }
        .generate-stickers-button-bag { background-color: rgba(0, 0, 0, 0.1); color: #000 }
        .generate-stickers-button-bag:hover { background-color: rgba(0, 0, 0, 0.05); color: #000 }
        header, main, footer { padding: 10px }
        header h1 { text-align: center; color: #1565c0 }
        .order-summary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px }
        .order-summary-table th, .order-summary-table td { border: 1px solid #ddd; padding: 5px; font-size: 14px }
        .order-summary-table th { background-color: #f2f2f2; font-weight: 700 }
        .order-summary-table tr:nth-child(even) { background-color: #f9f9f9 }
        .order-summary-table tr:hover { background-color: #f1f1f1 }
        .new-order { background-color: #e8f5e9; animation: highlightNewOrder 3s ease-in-out infinite alternate }
        .viewed-order { background-color: #fff }
        @keyframes highlightNewOrder { 0% { background-color: #e8f5e9 } 100% { background-color: #c8e6c9 } }
        .form-right-section { float: right; display: flex; align-items: center; gap: 10px; margin-bottom: 30px }
        .form-left-section { display: flex; flex-direction: column; align-items: start; gap: 20px }
        .filter-buttons input, .form-right-section input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 150px }
        .filter-buttons { display: flex; align-items: center; gap: 10px }
        .radio-buttons { font-size: 14px; text-align: center; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; gap: 10px }
        .filter-button, .btn-return, .add-order-button, .details-button, .edit-button, .delete-button, .generate-stickers-button { padding: 8px 15px; border: none; border-radius: 30px; cursor: pointer; font-weight: 700; text-decoration: none; transition: background-color 0.3s ease }
        .filter-button { background-color: #1565c0; color: #fff }
        .filter-button:hover { background-color: #003c8f }
        .add-order-button { background-color: #28a745; color: #fff; padding: 12px 20px }
        .add-order-button:hover { transform: scale(1.05); box-shadow: 0 0 20px rgba(40, 167, 69, 0.5) }
        .btn-return { background-color: #1565c0; color: #fff; margin: 5px }
        .btn-return:hover { background-color: #003c8f }
        .three_buttons { display: flex; gap: 5px }
        .details-button { background-color: #007bff; color: #fff; border-radius: 4px }
        .details-button:hover { background-color: #0056b3 }
        .edit-button { background-color: #1565c0; color: #fff }
        .edit-button:hover { background-color: #003c8f }
        .delete-button { background-color: #d32f2f; color: #fff }
        .delete-button:hover { background-color: #b71c1c }
        .narrow-column { width: 70px; text-align: center }
        .narrow-info { width: 120px; max-width: 120px; text-align: center; overflow: hidden; text-overflow: ellipsis }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.3); display: none; z-index: 500; justify-content: center; align-items: center }
        .modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 500px; background: #fff; border-radius: 10px; padding: 20px; display: none; z-index: 1000; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); animation: fadeInModal 0.5s }
        @keyframes fadeInModal { 0% { transform: translate(-50%, -70%); opacity: 0 } 100% { transform: translate(-50%, -50%); opacity: 1 } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; margin-bottom: 15px }
        .modal-header h3 { margin: 0; color: #1565c0 }
        .modal-close { font-size: 1.5rem; color: #d32f2f; cursor: pointer; transition: color 0.3s }
        .modal-close:hover { color: #b71c1c }
        @media (max-width: 768px) {
            .filter-form { flex-direction: column }
            .form-left-section, .form-right-section { flex-direction: column; align-items: stretch }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header>
            <h1>Таблиця замовлень</h1>
            <div class="top-button-container" style="text-align: center;">
                <a href="add_order_menu_do_wyboru.php" class="add-order-button">Додати нове замовлення</a>
                <a href="admin_panel.php" class="btn-return">Повернутися в адмін-панель</a>
            </div>
        </header>
        <main>
            <form method="GET" class="filter-form">
    <div class="form-left-section">
        <div class="filter-buttons">
            <input type="text" id="id-filter" name="order_id" placeholder="ID Замовлення" value="<?= htmlspecialchars($order_id_filter) ?>">
            <input type="text" id="email-filter" name="email" placeholder="Email Клієнта" value="<?= htmlspecialchars($email_filter) ?>">
            <input type="text" id="address-filter" name="address" placeholder="Адреса Клієнта" value="<?= htmlspecialchars($address_filter) ?>">
            <button type="submit" class="filter-button">Фільтрувати</button>
            <button type="button" id="reset-filters" class="filter-button">Скинути</button>
        </div>
        <div class="radio-buttons">
    <label>
        <input type="radio" name="status_filter" value="all" <?= $status_filter === 'all' ? 'checked' : '' ?> onclick="this.form.submit();">
        Всі замовлення
    </label>
    <label>
        <input type="radio" name="status_filter" value="paid" <?= $status_filter === 'paid' ? 'checked' : '' ?> onclick="this.form.submit();">
        Тільки оплачені
    </label>
</div>

    </div>
    <div class="form-right-section">
        <input type="text" id="delivery_date" name="delivery_date" placeholder="Дата Доставки" value="<?= htmlspecialchars($date_to_filter) ?>">
        <a href="/admin/sticker_order_menu_do_wyboru_SYDOCHKI.html" target="_blank">
            <button type="button" id="generate-stickers" class="generate-stickers-button">Генерація Наклеек</button>
        </a>
        <a href="/admin/sticker_order_menu_do_wyboru_paket.html" id="generate-package-stickers-link">
            <button type="button" class="generate-stickers-button-bag">Генерація Наклеек на Пакети</button>
        </a>
    </div>
</form>

            <?php if (!empty($date_to_filter)) : ?>
                <h2 class="h-size">Інформація про Страви на доставку у вибраний день</h2>
                <table class="order-summary-table order-summary-table-filter">
                    <thead>
                        <tr>
                            <th>S</th>
                            <th>O</th>
                            <th>K</th>
                            <th>Загальна кількість (S, O, K)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php if (!empty($packageCounts['S'])) : ?>
                                    <?php foreach ($packageCounts['S'] as $item) : ?>
                                        <?php
                                        $itemParts = explode('-', $item);
                                        if (count($itemParts) >= 4) {
                                            echo htmlspecialchars("S-{$itemParts[1]}-{$itemParts[2]}-{$itemParts[3]}");
                                        }
                                        ?>
                                        <br>
                                    <?php endforeach; ?>
                                    (<?= htmlspecialchars($packageCounts['S_count']) ?>)
                                <?php else : ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($packageCounts['O'])) : ?>
                                    <?php foreach ($packageCounts['O'] as $item) : ?>
                                        <?php
                                        $itemParts = explode('-', $item);
                                        if (count($itemParts) >= 4) {
                                            echo htmlspecialchars("O-{$itemParts[1]}-{$itemParts[2]}-{$itemParts[3]}");
                                        }
                                        ?>
                                        <br>
                                    <?php endforeach; ?>
                                    (<?= htmlspecialchars($packageCounts['O_count']) ?>)
                                <?php else : ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($packageCounts['K'])) : ?>
                                    <?php foreach ($packageCounts['K'] as $item) : ?>
                                        <?php
                                        $itemParts = explode('-', $item);
                                        if (count($itemParts) >= 4) {
                                            echo htmlspecialchars("K-{$itemParts[1]}-{$itemParts[2]}-{$itemParts[3]}");
                                        }
                                        ?>
                                        <br>
                                    <?php endforeach; ?>
                                    (<?= htmlspecialchars($packageCounts['K_count']) ?>)
                                <?php else : ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td><?= $totalCount ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <table class="order-summary-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>ID</th>
                        <th>Повне ім'я клієнта</th>
                        <th>Телефон клієнта</th>
                        <th>Вулиця</th>
                        <th>Будинок</th>
                        <th>Під'їзд (Klatka)</th>
                        <th>Поверх</th>
                        <th>Квартира</th>
                        <th class="narrow-column">Код дверей</th>
                        <th class="narrow-info">Примітки</th>
                        <th>Страви</th>
                        <th>Дії</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($orders as $order): ?>
                        <tr id="row-<?= $order['order_id'] ?>-<?= $order['delivery_date'] ?>" class="<?= empty($order['nonce_menu']) ? 'new-order' : 'viewed-order' ?>">
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['full_name']) ?></td>
                            <td><?= htmlspecialchars($order['phone']) ?></td>
                            <td><?= htmlspecialchars($order['street']) ?></td>
                            <td><?= htmlspecialchars($order['house_number']) ?></td>
                            <td><?= htmlspecialchars($order['building']) ?></td>
                            <td><?= htmlspecialchars($order['floor']) ?></td>
                            <td><?= htmlspecialchars($order['apartment']) ?></td>
                            <td><?= htmlspecialchars($order['entry_code']) ?></td>
                            <td class="narrow-column"><?= htmlspecialchars($order['notes']) ?></td>
                            <td><?= htmlspecialchars($order['items_info']) ?></td>
                            <td class="three_buttons">
                                <a class="details-button" onclick="showDetailsModal('<?= $order['order_id'] ?>', '<?= $order['delivery_date'] ?>')">Деталі</a>
                                <div id="details-<?= $order['order_id'] ?>-<?= $order['delivery_date'] ?>" class="hidden-details" style="display: none;">
                                    <strong>Email:</strong> <?= htmlspecialchars($order['delivery_email']) ?><br>
                                    <strong>Загальна сума:</strong> <?= htmlspecialchars($order['total_price']) ?><br>
                                    <strong>Дата Створення:</strong> <?= htmlspecialchars($order['order_date']) ?><br>
                                    <strong>Дата Доставки:</strong> <?= htmlspecialchars($order['delivery_date']) ?><br>
                                </div>
                                <a href="edit_order_menu_do_wyboru.php?order_id=<?= urlencode($order['order_id']) ?>&delivery_date=<?= urlencode($order['delivery_date']) ?>" class="edit-button">Редагувати</a>
                                <a href="#" class="delete-button" onclick="openDeleteModal('<?= $order['order_id'] ?>', '<?= $order['delivery_date'] ?>')">Видалити</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
        <footer>
            <!-- Дополнительная информация или ссылки -->
        </footer>
    </div>

    <div class="modal-overlay" id="modal-overlay" onclick="closeDetailsModal()"></div>
    <div class="modal" id="modal-details">
        <div class="modal-header">
            <h3>Деталі Замовлення</h3>
            <span class="modal-close" onclick="closeDetailsModal()">&times;</span>
        </div>
        <div class="modal-content" id="modal-content"></div>
    </div>

    <div class="modal-overlay" id="modal-delete-overlay" onclick="closeDeleteModal()"></div>
    <div class="modal" id="modal-delete">
        <div class="modal-header">
            <h3>Ви впевнені, що хочете видалити це замовлення?</h3>
            <span class="modal-close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-content modal-content-del">
            <div class="modal-delete-buttons">
                <button id="confirm-delete" class="delete-button" data-order-id="">Так, видалити</button>
                <button class="edit-button" onclick="closeDeleteModal()">Скасувати</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-success-overlay" onclick="closeSuccessModal()"></div>
    <div class="modal" id="modal-success">
        <div class="modal-header">
            <h3>Замовлення успішно видалено</h3>
            <span class="modal-close" onclick="closeSuccessModal()">&times;</span>
        </div>
        <div class="modal-content-suc">
            <p class="success-text">Замовлення було успішно видалено з системи.</p>
            <button class="edit-button ok" onclick="closeSuccessModal()">OK</button>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    // Установите значение по умолчанию для радиокнопок
    const statusFilter = document.querySelector('input[name="status_filter"][value="paid"]');
    if (statusFilter && !document.querySelector('input[name="status_filter"]:checked')) {
        statusFilter.checked = true;
    }

    // Добавьте обработчики событий для радиокнопок
    document.querySelectorAll('input[name="status_filter"]').forEach(radio => {
        radio.addEventListener('change', function () {
            this.form.submit();
        });
    });

    const confirmDeleteBtn = document.getElementById('confirm-delete');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async function () {
            const orderId = this.getAttribute('data-order-id');
            const deliveryDate = this.getAttribute('data-delivery-date');
            if (!orderId || !deliveryDate) return;

            try {
                const response = await fetch('/admin/delete_order_menu_do_wyboru.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ order_id: orderId, delivery_date: deliveryDate })
                });

                const result = await response.text();
                if (result === 'Заказ успешно удалён') {
                    document.getElementById(`row-${orderId}-${deliveryDate}`)?.remove();
                    closeDeleteModal();
                    openSuccessModal();
                } else {
                    alert("Ошибка при удалении заказа.");
                }
            } catch (error) {
                console.error('Ошибка при попытке удалить заказ:', error);
                alert("Ошибка при попытке удалить заказ.");
            }
        });
    }

    window.showDetailsModal = function (orderId, deliveryDate) {
        const detailsElement = document.getElementById(`details-${orderId}-${deliveryDate}`);
        if (detailsElement) {
            document.getElementById('modal-content').innerHTML = detailsElement.innerHTML;
            document.getElementById(`row-${orderId}-${deliveryDate}`)?.classList.add('highlight-row');
            document.getElementById('modal-overlay').style.display = 'flex';
            document.getElementById('modal-details').style.display = 'block';
        }
    };

    window.closeDetailsModal = function () {
        document.getElementById('modal-overlay').style.display = 'none';
        document.getElementById('modal-details').style.display = 'none';
        document.querySelectorAll('.highlight-row').forEach(row => row.classList.remove('highlight-row'));
    };

    window.openDeleteModal = function (orderId, deliveryDate) {
        document.getElementById('confirm-delete').setAttribute('data-order-id', orderId);
        document.getElementById('confirm-delete').setAttribute('data-delivery-date', deliveryDate);
        document.getElementById('modal-delete-overlay').style.display = 'flex';
        document.getElementById('modal-delete').style.display = 'block';
    };

    window.closeDeleteModal = function () {
        document.getElementById('modal-delete-overlay').style.display = 'none';
        document.getElementById('modal-delete').style.display = 'none';
    };

    window.openSuccessModal = function () {
        document.getElementById('modal-success-overlay').style.display = 'flex';
        document.getElementById('modal-success').style.display = 'block';
    };

    window.closeSuccessModal = function () {
        document.getElementById('modal-success-overlay').style.display = 'none';
        document.getElementById('modal-success').style.display = 'none';
    };

    window.resetFilters = function () {
        document.getElementById('id-filter').value = '';
        document.getElementById('email-filter').value = '';
        document.getElementById('address-filter').value = '';
        document.getElementById('delivery_date').value = '';

        const statusFilter = document.querySelector('input[name="status_filter"][value="all"]');
        if (statusFilter) statusFilter.checked = true;

        const filterForm = document.querySelector('form.filter-form');
        if (filterForm) {
            filterForm.submit();
        }
    };

    const resetButton = document.getElementById('reset-filters');
    if (resetButton) {
        resetButton.addEventListener("click", resetFilters);
    }

    const setupDynamicAutocomplete = (inputId, sourceUrl) => {
        const inputField = document.getElementById(inputId);
        if (!inputField) return console.warn(`Поле с ID "${inputId}" не найдено.`);

        $(inputField).autocomplete({
            source: async (request, response) => {
                try {
                    const url = new URL(sourceUrl, window.location.origin);
                    url.searchParams.append('term', request.term);
                    const res = await fetch(url);
                    const data = await res.json();
                    response(data.map(item => ({
                        label: item.label || item.value,
                        value: item.value
                    })));
                } catch (error) {
                    console.error('Ошибка автозаполнения:', error);
                    response([]);
                }
            },
            minLength: 2,
            select: (event, ui) => console.log(`Выбрано: ${ui.item.label} (${ui.item.value})`)
        });
    };

    if (typeof flatpickr !== 'undefined') {
        flatpickr("#delivery_date", {
            dateFormat: "Y-m-d",
            locale: "uk",
            weekStart: 1,
            onChange: (selectedDates, dateStr) => {
                const url = new URL(window.location.href);
                url.searchParams.set('delivery_date', dateStr);
                window.location.href = url.toString();
            }
        });
    }

    ['email-filter', 'address-filter'].forEach(id => setupDynamicAutocomplete(id, '/admin/fetch_orders_summary.php'));

    document.getElementById('generate-package-stickers-link')?.addEventListener('click', async e => {
        e.preventDefault();

        const deliveryDate = document.getElementById('delivery_date').value;
        if (!deliveryDate) return alert("Пожалуйста, выберите дату доставки.");

        const packageStickerData = { delivery_date: deliveryDate, packages: [] };
        document.querySelectorAll('.order-summary-table tbody tr').forEach(row => {
            const street = row.querySelectorAll('td')[4]?.innerText.trim();
            const houseNumber = row.querySelectorAll('td')[5]?.innerText.trim();
            const apartment = row.querySelectorAll('td')[8]?.innerText.trim();
            if (street) {
                packageStickerData.packages.push({
                    address: street,
                    houseNumber: houseNumber,
                    apartment: apartment
                });
            }
        });

        if (!packageStickerData.packages.length) return alert("Не найдено адресов для генерации наклеек.");

        localStorage.setItem('packageStickerData', JSON.stringify(packageStickerData));
        window.open('/admin/sticker_order_menu_do_wyboru_paket.html', '_blank');
    });

    document.getElementById('generate-stickers')?.addEventListener('click', async e => {
        e.preventDefault();

        const deliveryDate = document.getElementById('delivery_date').value;
        if (!deliveryDate) return alert("Пожалуйста, выберите дату доставки.");

        const packageStickerData = { delivery_date: deliveryDate, packages: [] };
        document.querySelectorAll('.order-summary-table tbody tr').forEach(row => {
            const itemsInfo = row.querySelectorAll('td')[11]?.innerText.trim();
            if (itemsInfo) {
                itemsInfo.split(' | ').forEach(item => {
                    const [category, weight, orderId] = item.split('-');
                    if (category && weight && orderId) {
                        packageStickerData.packages.push({ package: `${category}-${weight}-${orderId}`, category, weight, orderId });
                    }
                });
            }
        });

        if (!packageStickerData.packages.length) return alert("Нет данных для генерации наклеек.");

        localStorage.setItem('stickerDataSYDOCHKI', JSON.stringify(packageStickerData));
        window.open('/admin/sticker_order_menu_do_wyboru_SYDOCHKI.html', '_blank');
    });

    function markOrderAsViewed(orderId) {
        fetch('/admin/mark_order_menu_viewed.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ order_id: orderId })
        })
            .then(response => response.text())
            .then(result => {
                if (result !== 'success') {
                    console.error('Ошибка при обновлении статуса заказа.');
                }
            })
            .catch(error => {
                console.error('Ошибка при попытке обновить статус заказа:', error);
            });
    }

    // Добавляем обработчик событий клика для каждой строки
    document.querySelectorAll('.order-summary-table tbody tr').forEach(row => {
        row.addEventListener('click', function () {
            row.classList.remove('new-order');
            row.classList.add('viewed-order');

            const orderId = row.getAttribute('id')?.match(/\d+/)[0];
            if (orderId) {
                markOrderAsViewed(orderId);
            }
        });
    });

    console.log("Скрипт загружен");
});
</script>

</body>
</html>
