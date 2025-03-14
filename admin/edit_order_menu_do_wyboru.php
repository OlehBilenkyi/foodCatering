<?php
session_start();

// Проверяем, авторизован ли администратор
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
if (!$pdo) {
    die("Ошибка подключения к базе данных");
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$delivery_date = isset($_GET['delivery_date']) ? $_GET['delivery_date'] : '';

if ($order_id <= 0 || empty($delivery_date)) {
    die("Неверный ID заказа или дата доставки");
}


// Получаем данные основного заказа
$stmt = $pdo->prepare("SELECT * FROM customer_menu_orders WHERE order_id = :order_id");
$stmt->execute([':order_id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die("Заказ не найден");
}

// Получаем данные дня доставки
$stmt = $pdo->prepare("SELECT * FROM customer_menu_order_days WHERE order_id = :order_id AND delivery_date = :delivery_date");
$stmt->execute([':order_id' => $order_id, ':delivery_date' => $delivery_date]);
$order_day = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order_day) {
    die("Данные дня доставки не найдены для order_id = $order_id и delivery_date = $delivery_date");
}

// Получаем данные блюд
$stmt = $pdo->prepare("SELECT * FROM customer_menu_order_items WHERE order_day_id = :order_day_id");
$stmt->execute([':order_day_id' => $order_day['order_day_id']]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Если форма отправлена – обновляем заказ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Валидация и фильтрация входных данных
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone']);
        $fullname = trim($_POST['fullname']);
        $street = trim($_POST['street']);
        $house_number = trim($_POST['house_number']);
        $building = trim($_POST['building']);
        $floor = trim($_POST['floor']);
        $apartment = trim($_POST['apartment']);
        $entry_code = trim($_POST['gate_code']);
        $notes = trim($_POST['notes']);
        $total_price = trim($_POST['total_price']);
        $delivery_date = trim($_POST['delivery_date']);
        $day_total_price = trim($_POST['day_total_price']);
        $status = trim($_POST['status']); // Извлечение поля статуса

        // Обновление основного заказа
        $stmtUpdate = $pdo->prepare("
            UPDATE customer_menu_orders
            SET delivery_email = :email,
                phone = :phone,
                full_name = :fullname,
                street = :street,
                house_number = :house_number,
                building = :building,
                floor = :floor,
                apartment = :apartment,
                entry_code = :entry_code,
                notes = :notes,
                total_price = :total_price,
                status = :status  
            WHERE order_id = :order_id
        ");
        $stmtUpdate->execute([
            ':email'       => $email,
            ':phone'       => $phone,
            ':fullname'    => $fullname,
            ':street'      => $street,
            ':house_number'=> $house_number,
            ':building'    => $building,
            ':floor'       => $floor,
            ':apartment'   => $apartment,
            ':entry_code'  => $entry_code,
            ':notes'       => $notes,
            ':total_price' => $total_price,
            ':status'      => $status,  
            ':order_id'    => $order_id
        ]);

        // Обновление данных дня доставки
        $stmtUpdate = $pdo->prepare("
            UPDATE customer_menu_order_days
            SET delivery_date = :delivery_date,
                day_total_price = :day_total_price
            WHERE order_id = :order_id
        ");
        $stmtUpdate->execute([
            ':delivery_date' => $delivery_date,
            ':day_total_price' => $day_total_price,
            ':order_id' => $order_id
        ]);

        // Обновление данных блюд
        $categories = $_POST['category'] ?? [];
        $dish_names = $_POST['dish_name'] ?? [];
        $weights = $_POST['weight'] ?? [];
        $prices = $_POST['price'] ?? [];
        $menu_options_ids = $_POST['menu_options_id'] ?? [];

        // Удаляем старые блюда
        $stmtDelete = $pdo->prepare("DELETE FROM customer_menu_order_items WHERE order_day_id = :order_day_id");
        $stmtDelete->execute([':order_day_id' => $order_day['order_day_id']]);

        // Вставка новых блюд
        for ($i = 0; $i < count($categories); $i++) {
            $sql = "INSERT INTO customer_menu_order_items
                        (order_day_id, category, dish_name, weight, price, menu_options_id)
                    VALUES
                        (:order_day_id, :category, :dish_name, :weight, :price, :menu_options_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':order_day_id'    => $order_day['order_day_id'],
                ':category'        => $categories[$i],
                ':dish_name'       => $dish_names[$i],
                ':weight'          => $weights[$i],
                ':price'           => $prices[$i],
                ':menu_options_id' => (empty($menu_options_ids[$i]) ? null : $menu_options_ids[$i]),
            ]);
        }

        $pdo->commit();
        $_SESSION['message'] = "Дані успішно збережені.";
        // Перезагрузка страницы для отображения сообщения
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "?order_id=$order_id&delivery_date=$delivery_date';</script>";
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['error'] = "Ошибка при обновлении заказа: " . $e->getMessage();
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать заказ #<?= htmlspecialchars($order['order_id']) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/l10n/uk.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-group button {
            padding: 10px 20px;
            background-color: #1565c0;
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }
        .form-group button:hover {
            background-color: #003c8f;
        }
        .return-buttons {
            margin: 20px 0;
            text-align: center;
        }
        .return-buttons a {
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            color: white;
            background-color: #1565c0;
            transition: background-color 0.3s ease;
            margin: 0 10px;
        }
        .return-buttons a:hover {
            background-color: #003c8f;
        }
        .dish-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .dish-item {
            padding: 10px;
            border-radius: 5px;
            background-color: #fff;
        }
        .form-control[readonly] {
            background-color: #fff;
        }
    </style>
</head>
<body>
<div class="form-container">
    <div class="return-buttons">
    <a href="admin_orders_menu_do_wyboru.php">Назад до Таблиці замовлень</a>
    <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
</div>
    <h1 class="mt-4 mb-4">Редагування замовлення #<?= htmlspecialchars($order['order_id']) ?></h1>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
        <?php unset($_SESSION['message']); endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); endif; ?>
    <form method="POST" action="">
        <div class="form-group">
            <label for="fullname">Повне ім'я клієнта:</label>
            <input type="text" name="fullname" id="fullname" class="form-control" value="<?= htmlspecialchars($order['full_name']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email клієнта:</label>
            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($order['delivery_email']) ?>" required>
        </div>
        <div class="form-group">
            <label for="phone">Телефон клієнта:</label>
            <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($order['phone']) ?>" required>
        </div>
        <div class="form-group">
            <label for="street">Вулиця:</label>
            <input type="text" name="street" id="street" class="form-control" value="<?= htmlspecialchars($order['street']) ?>" required>
        </div>
        <div class="form-group">
            <label for="house_number">Будинок:</label>
            <input type="text" name="house_number" id="house_number" class="form-control" value="<?= htmlspecialchars($order['house_number']) ?>" required>
        </div>
        <div class="form-group">
            <label for="apartment">Квартира</label>
            <input type="text" name="apartment" id="apartment" class="form-control" value="<?= htmlspecialchars($order['apartment']) ?>" required>
        </div>
        <div class="form-group">
            <label for="floor">Поверх:</label>
            <input type="text" name="floor" id="floor" class="form-control" value="<?= htmlspecialchars($order['floor']) ?>" required>
        </div>
        <div class="form-group">
            <label for="building">Під'їзд (Klatka):</label>
            <input type="text" name="building" id="building" class="form-control" value="<?= htmlspecialchars($order['building']) ?>">
        </div>
        <div class="form-group">
            <label for="gate_code">Код дверей:</label>
            <input type="text" name="gate_code" id="gate_code" class="form-control" value="<?= htmlspecialchars($order['entry_code']) ?>">
        </div>
        <div class="form-group">
            <label for="notes">Примітки:</label>
            <textarea name="notes" id="notes" class="form-control"><?= htmlspecialchars($order['notes']) ?></textarea>
        </div>
        <div class="form-group">
            <label for="total_price">Загальна сума:</label>
            <input type="number" step="0.01" name="total_price" id="total_price" value="<?= htmlspecialchars($order['total_price']) ?>" required>
        </div>

        <div class="form-group">
            <label for="status">Статус замовлення:</label>
            <select name="status" id="status" required>
                <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
        </div>

        <div class="form-group">
            <label for="delivery_date">Дата доставки:</label>
            <input type="date" name="delivery_date" id="delivery_date" class="form-control delivery-dates-input" value="<?= htmlspecialchars($order_day['delivery_date']) ?>" required>
        </div>

        <div class="form-group">
            <label for="day_total_price">Сума за день:</label>
            <input type="number" step="0.01" name="day_total_price" id="day_total_price" value="<?= htmlspecialchars($order_day['day_total_price']) ?>" required>
        </div>
        <div class="form-group">
            <label>Блюда:</label>
            <div class="dish-container">
            <?php foreach ($order_items as $index => $item): ?>
                <div class="dish-item">
                    <label for="category">Категорія:</label>
                    <input type="text" name="category[]" placeholder="Категория" value="<?= htmlspecialchars($item['category']) ?>" required>
                    <label for="dish_name">Назва страви:</label>
                    <input type="text" name="dish_name[]" placeholder="Название блюда" value="<?= htmlspecialchars($item['dish_name']) ?>" required>
                    <label for="weight">Вага:</label>
                    <input type="text" name="weight[]" placeholder="Вес" value="<?= htmlspecialchars($item['weight']) ?>" required>
                    <label for="price">Ціна:</label>
                    <input type="text" name="price[]" placeholder="Цена" value="<?= htmlspecialchars($item['price']) ?>" required>
                    <label for="menu_options_id">ID:</label>
                    <input type="text" name="menu_options_id[]" placeholder="ID опции меню" value="<?= htmlspecialchars($item['menu_options_id']) ?>">
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <button type="submit">Зберегти зміни</button>
        </div>
    </form>
</div>
<div class="return-buttons">
    <a href="admin_orders_menu_do_wyboru.php">Назад до Таблиці замовлень</a>
    <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
</div>
<script>
// Добавляем код инициализации календарей
document.addEventListener('DOMContentLoaded', function () {
    initializeCalendars();
});

function initializeCalendars() {
    const dateInputs = document.querySelectorAll('.delivery-dates-input');
    dateInputs.forEach(function(input) {
        flatpickr(input, {
            mode: "single", // Режим выбора одной даты
            dateFormat: "Y-m-d", // Формат даты
            locale: "uk", // Указываем локаль (украинская)
            firstDayOfWeek: 1 // Начинаем неделю с понедельника
        });
    });
}
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
