<?php
// Увімкнення відображення всіх помилок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Логування помилок у файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Підключення файлу з конфігурацією бази даних
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// Перевірка, що підключення до бази даних встановлено
if (!$pdo) {
    die("Помилка підключення до бази даних.");
}

// Перевірка GET-запиту для відображення форми редагування
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];

    try {
        // Отримання інформації про замовлення
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id");
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            die("Замовлення не знайдено.");
        }

        // Отримання інформації про пакети замовлення
        $stmtPackages = $pdo->prepare("SELECT * FROM order_packages WHERE order_id = :order_id");
        $stmtPackages->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmtPackages->execute();
        $packages = $stmtPackages->fetchAll(PDO::FETCH_ASSOC);

        // Отримання інформації про дати доставки
        $deliveryDates = [];
        foreach ($packages as $package) {
            $stmtDates = $pdo->prepare("SELECT delivery_date FROM delivery_dates WHERE order_package_id = :order_package_id");
            $stmtDates->bindValue(':order_package_id', $package['id'], PDO::PARAM_INT);
            $stmtDates->execute();
            $dates = $stmtDates->fetchAll(PDO::FETCH_COLUMN);
            $deliveryDates[$package['id']] = $dates;
        }
    } catch (PDOException $e) {
        die("Помилка при отриманні даних замовлення: " . $e->getMessage());
    }

    // Відображення форми редагування
    ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редагування замовлення</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/l10n/uk.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .big-order-title{
           font-size: 40px; 
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
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    width: 100%; /* Убедитесь, что контейнер занимает всю ширину */
}

.form-group label {
    margin-bottom: 8px;
    font-weight: bold;
    color: #555;
}

.form-group input, .form-group textarea, .form-group select {
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
    transition: border-color 0.3s;
    width: 100%; /* Убедитесь, что поля ввода занимают всю ширину */
    box-sizing: border-box; /* Включаем padding и border в общую ширину */
}
        .form-group button {
    font-size: 18px;
    width: 222px;
    padding: 10px 20px;
    background-color: #1565c0;
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s ease;
    margin:  10px 0;
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
        
        
    </style>
</head>
<body>
    <div class="form-container">
        <div class="return-buttons">
        <a href="/admin/orders_summary.php">Назад до Таблиці замовлень</a>
        <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
    </div>
        <h2 class="big-order-title">Редагування замовлення #<?= htmlspecialchars($order['order_id']) ?></h2>
        <form action="edit_orders_summary.php" method="POST">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">

            <div class="form-group">
                <label for="customer_fullname">Повне ім'я клієнта:</label>
                <input type="text" name="customer_fullname" id="customer_fullname" value="<?= htmlspecialchars($order['customer_fullname']) ?>" required>
            </div>

            <div class="form-group">
                <label for="customer_email">Email клієнта:</label>
                <input type="email" name="customer_email" id="customer_email" value="<?= htmlspecialchars($order['customer_email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="customer_phone">Телефон клієнта:</label>
                <input type="text" name="customer_phone" id="customer_phone" value="<?= htmlspecialchars($order['customer_phone']) ?>" required>
            </div>

            <div class="form-group">
                <label for="customer_street">Вулиця:</label>
                <input type="text" name="customer_street" id="customer_street" value="<?= htmlspecialchars($order['customer_street']) ?>" required>
            </div>

            <div class="form-group">
                <label for="customer_house_number">Будинок:</label>
                <input type="text" name="customer_house_number" id="customer_house_number" value="<?= htmlspecialchars($order['customer_house_number']) ?>" required>
            </div>

            <div class="form-group">
                <label for="customer_apartment">Квартира:</label>
                <input type="text" name="customer_apartment" id="customer_apartment" value="<?= htmlspecialchars($order['customer_apartment']) ?>" required>
            </div>

            <div class="form-group">
                <label for="customer_floor">Поверх:</label>
                <input type="text" name="customer_floor" id="customer_floor" value="<?= htmlspecialchars($order['customer_floor']) ?>" required>
            </div>

            <div class="form-group">
                <label for="customer_klatka">Під'їзд (Klatka):</label>
                <input type="text" name="customer_klatka" id="customer_klatka" value="<?= htmlspecialchars($order['customer_klatka']) ?>">
            </div>

            <div class="form-group">
                <label for="customer_gate_code">Код дверей:</label>
                <input type="text" name="customer_gate_code" id="customer_gate_code" value="<?= htmlspecialchars($order['customer_gate_code']) ?>">
            </div>

            <div class="form-group">
                <label for="customer_notes">Примітки:</label>
                <textarea name="customer_notes" id="customer_notes" rows="3"><?= htmlspecialchars($order['customer_notes']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="total_price">Загальна сума:</label>
                <input type="number" step="0.01" name="total_price" id="total_price" value="<?= htmlspecialchars($order['total_price']) ?>" required>
            </div>

            <div class="form-group">
                <label for="status">Статус замовлення:</label>
                <select name="status" id="status">
                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>pending</option>
                    <option value="оплачен" <?= $order['status'] === 'оплачен' ? 'selected' : '' ?>>оплачен</option>
                </select>
            </div>

            <!-- Редагування пакетів -->
            <?php foreach ($packages as $index => $package): ?>
                <div class="form-group package-group">
                    <h3>Пакет #<?= $index + 1 ?></h3>
                    <input type="hidden" name="packages[<?= $index ?>][package_id]" value="<?= htmlspecialchars($package['id']) ?>">

                    <label for="calories_<?= $index ?>">Калорії:</label>
                    <input type="number" name="packages[<?= $index ?>][calories]" id="calories_<?= $index ?>" value="<?= htmlspecialchars($package['calories']) ?>">

                    <label for="quantity_<?= $index ?>">Кількість:</label>
                    <input type="number" name="packages[<?= $index ?>][quantity]" id="quantity_<?= $index ?>" value="<?= htmlspecialchars($package['quantity']) ?>">

                    <label for="delivery_dates_<?= $index ?>">Дати доставки:</label>
                    <?php 
                    $deliveryDatesString = '';
                    if (isset($deliveryDates[$package['id']])) {
                        $deliveryDatesString = implode(', ', $deliveryDates[$package['id']]);
                    }
                    ?>
                    <input type="text" name="packages[<?= $index ?>][delivery_dates]" id="delivery_dates_<?= $index ?>" value="<?= htmlspecialchars($deliveryDatesString) ?>" class="delivery-dates-picker">
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <button type="submit">Зберегти зміни</button>
            </div>
            <div class="return-buttons">
        <a href="/admin/orders_summary.php">Назад до Таблиці замовлень</a>
        <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
    </div>
        </form>
    </div>

    

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const datePickers = document.querySelectorAll('.delivery-dates-picker');
            
            datePickers.forEach(picker => {
                // Отримання обраних дат з поточного значення поля
                let preselectedDates = picker.value.split(',').map(date => date.trim());
                
                // Ініціалізація календаря з попередньо обраними датами
                flatpickr(picker, {
                    mode: "multiple",
                    dateFormat: "Y-m-d",
                    weekStart: 1, // Календар починається з понеділка
                    locale: "uk", // Використання української локалізації
                    defaultDate: preselectedDates, // Попередньо обрані дати
                    onChange: function (selectedDates, dateStr, instance) {
                        // Оновлюємо значення в input при кожній зміні
                        picker.value = selectedDates.map(date => instance.formatDate(date, "Y-m-d")).join(', ');
                    }
                });
            });
        });
    </script>
</body>
</html>
    <?php
    exit();
}

// Перевірка POST-запиту для оновлення замовлення
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    if ($orderId > 0) {
        try {
            // Оновлення інформації про замовлення
            $sql = "UPDATE orders SET ";
            $params = [];
            foreach ($_POST as $field => $value) {
                if ($field === 'order_id' || strpos($field, 'packages') === 0) {
                    continue;
                }
                $sql .= "$field = :$field, ";
                $params[$field] = trim($value);
            }
            $sql = rtrim($sql, ', ') . " WHERE order_id = :order_id";
            $params['order_id'] = $orderId;

            $stmt = $pdo->prepare($sql);

            foreach ($params as $field => $value) {
                $stmt->bindValue(":$field", $value, PDO::PARAM_STR);
            }

            $stmt->execute();

            // Оновлення інформації про пакети
            if (isset($_POST['packages']) && is_array($_POST['packages'])) {
                foreach ($_POST['packages'] as $index => $package) {
                    $packageId = $package['package_id'];
                    $calories = $package['calories'];
                    $quantity = $package['quantity'];

                    // Оновлення пакета
                    $stmt = $pdo->prepare("UPDATE order_packages SET calories = :calories, quantity = :quantity WHERE id = :package_id");
                    $stmt->bindValue(':calories', $calories, PDO::PARAM_INT);
                    $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
                    $stmt->bindValue(':package_id', $packageId, PDO::PARAM_INT);
                    $stmt->execute();

                    // Оновлення дат доставки
                    $stmtDeleteDates = $pdo->prepare("DELETE FROM delivery_dates WHERE order_package_id = :order_package_id");
                    $stmtDeleteDates->bindValue(':order_package_id', $packageId, PDO::PARAM_INT);
                    $stmtDeleteDates->execute();

                    if (!empty($package['delivery_dates'])) {
                        $deliveryDatesArray = explode(',', $package['delivery_dates']);
                        foreach ($deliveryDatesArray as $date) {
                            $stmtInsertDate = $pdo->prepare("INSERT INTO delivery_dates (order_package_id, delivery_date) VALUES (:order_package_id, :delivery_date)");
                            $stmtInsertDate->bindValue(':order_package_id', $packageId, PDO::PARAM_INT);
                            $stmtInsertDate->bindValue(':delivery_date', trim($date), PDO::PARAM_STR);
                            $stmtInsertDate->execute();
                        }
                    }
                }
            }

            // Перенаправлення після успішного збереження
            header("Location: /admin/orders_summary.php");
            exit();
        } catch (PDOException $e) {
            echo 'Помилка при оновленні замовлення: ' . $e->getMessage();
        }
    } else {
        echo 'Будь ласка, заповніть усі обов’язкові поля.';
    }
} else {
    echo 'Невірний запит.';
}
