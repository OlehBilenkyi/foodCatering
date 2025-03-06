<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');
session_start();

// Генерация нового идентификатора сессии для защиты от фиксации сессий
session_regenerate_id(true);

// Включаем логирование ошибок
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

header('Content-Type: text/html; charset=UTF-8');

$messages = [];
$success_message = "";

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
    error_log("Успешное подключение к базе данных для пользователя " . $_ENV['DB_USER'] . ".");
} catch (PDOException $e) {
    error_log("Ошибка подключения к базе данных: " . $e->getMessage());
    die("Ошибка подключения к базе данных");
}

// Обработка данных при сохранении заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
    $customer_fullname = htmlspecialchars($_POST['customer_fullname'] ?? '', ENT_QUOTES, 'UTF-8');
    $customer_email = htmlspecialchars($_POST['customer_email'] ?? '', ENT_QUOTES, 'UTF-8');
    $customer_phone = htmlspecialchars($_POST['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8');
    $customer_street = htmlspecialchars($_POST['customer_street'] ?? '', ENT_QUOTES, 'UTF-8');
    $customer_house_number = htmlspecialchars($_POST['customer_house_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $customer_apartment = htmlspecialchars($_POST['customer_apartment'] ?? '', ENT_QUOTES, 'UTF-8');
    $customer_floor = htmlspecialchars($_POST['customer_floor'] ?? '', ENT_QUOTES, 'UTF-8');
    $customer_gate_code = htmlspecialchars($_POST['customer_gate_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $total_price = floatval($_POST['total_price'] ?? 0);
    $status = htmlspecialchars($_POST['status'] ?? '', ENT_QUOTES, 'UTF-8');

    try {
        if ($order_id) {
            // Обновление существующего заказа
            $stmt = $pdo->prepare("UPDATE orders SET 
                customer_fullname = :customer_fullname,
                customer_email = :customer_email,
                customer_phone = :customer_phone,
                customer_street = :customer_street,
                customer_house_number = :customer_house_number,
                customer_apartment = :customer_apartment,
                customer_floor = :customer_floor,
                customer_gate_code = :customer_gate_code,
                total_price = :total_price,
                status = :status
                WHERE order_id = :order_id");

            $stmt->execute([
                ':customer_fullname' => $customer_fullname,
                ':customer_email' => $customer_email,
                ':customer_phone' => $customer_phone,
                ':customer_street' => $customer_street,
                ':customer_house_number' => $customer_house_number,
                ':customer_apartment' => $customer_apartment,
                ':customer_floor' => $customer_floor,
                ':customer_gate_code' => $customer_gate_code,
                ':total_price' => $total_price,
                ':status' => $status,
                ':order_id' => $order_id
            ]);

            $success_message = "Заказ успешно обновлен, order_id: " . $order_id;
            error_log("Заказ успешно обновлен, order_id: " . $order_id);
        } else {
            // Вставка нового заказа
            $stmt = $pdo->prepare("INSERT INTO orders (
                customer_fullname, customer_email, customer_phone, customer_street, customer_house_number, 
                customer_apartment, customer_floor, customer_gate_code, total_price, status, created_at
            ) VALUES (
                :customer_fullname, :customer_email, :customer_phone, :customer_street, :customer_house_number, 
                :customer_apartment, :customer_floor, :customer_gate_code, :total_price, :status, NOW())");

            $stmt->execute([
                ':customer_fullname' => $customer_fullname,
                ':customer_email' => $customer_email,
                ':customer_phone' => $customer_phone,
                ':customer_street' => $customer_street,
                ':customer_house_number' => $customer_house_number,
                ':customer_apartment' => $customer_apartment,
                ':customer_floor' => $customer_floor,
                ':customer_gate_code' => $customer_gate_code,
                ':total_price' => $total_price,
                ':status' => $status
            ]);

            $order_id = $pdo->lastInsertId();
            $success_message = "Новый заказ успешно добавлен, order_id: " . $order_id;
            error_log("Новый заказ успешно добавлен, order_id: " . $order_id);
        }
    } catch (PDOException $e) {
        error_log("Ошибка при сохранении заказа: " . $e->getMessage());
        die("Ошибка при сохранении заказа");
    }
}

// Получение всех заказов для отображения в таблице
try {
    $stmt = $pdo->query("SELECT * FROM orders");
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка получения данных о заказах: " . $e->getMessage());
    die("Ошибка получения данных о заказах");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление Заказами и Пакетами - FOODCASE</title>
    <link rel="stylesheet" type="text/css" href="../assets/stylescss/admin_styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" type="text/css" href="../assets/css/global.css">
    <style>
        /* Улучшенные стили */
        .form-group-right {
            padding-left: 20px;
            margin-top: 20px;
        }
        .package-item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .package-item-inner {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        button[type="button"] {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button[type="button"]:hover {
            background-color: #0056b3;
        }
        .btn-primary {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #218838;
        }
        .orders-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .orders-table th, .orders-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .orders-table th {
            background-color: #f2f2f2;
            text-align: center;
        }
        .orders-table td {
            text-align: center;
        }
        .edit-btn, .delete-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .edit-btn {
            background-color: #ffc107;
        }
        .edit-btn:hover {
            background-color: #e0a800;
        }
        .delete-btn {
            background-color: #dc3545;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .success-message {
            background-color: #28a745;
            color: white;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; ?>
    <div class="container">
        <h1>Управление Заказами и Пакетами</h1>
        <a href="/admin/orders_vue.php" class="btn btn-primary">Таблица с заказами</a>

        <!-- Сообщение об успехе -->
        <?php if (!empty($success_message)) : ?>
            <div class="success-message">
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>
        
        <form action="manage_orders.php" method="POST" class="form-horizontal">
            <div class="form-group-left">
                <!-- Информация о заказчике -->
                <input type="hidden" name="order_id" id="order_id">
                <label for="customer_fullname">Имя клиента:</label>
                <input type="text" id="customer_fullname" name="customer_fullname">
                <label for="customer_email">Email:</label>
                <input type="email" id="customer_email" name="customer_email">
                <label for="customer_phone">Телефон:</label>
                <input type="text" id="customer_phone" name="customer_phone">
                <label for="customer_street">Адрес:</label>
                <input type="text" id="customer_street" name="customer_street">
                <label for="customer_house_number">Дом:</label>
                <input type="text" id="customer_house_number" name="customer_house_number">
                <label for="customer_apartment">Квартира:</label>
                <input type="text" id="customer_apartment" name="customer_apartment">
                <label for="customer_floor">Этаж:</label>
                <input type="text" id="customer_floor" name="customer_floor">
                <label for="customer_gate_code">Код домофона:</label>
                <input type="text" id="customer_gate_code" name="customer_gate_code">
                <label for="total_price">Сумма заказа:</label>
                <input type="text" id="total_price" name="total_price">
                <label for="status">Статус:</label>
                <input type="text" id="status" name="status">
            </div>

            <div class="form-group-right">
                <!-- Добавление пакетов и дат доставки -->
                <div id="packages-container">
                    <h3>Пакеты</h3>
                </div>
                <button type="button" onclick="addPackage()">Добавить пакет</button>
            </div>
            <div class="clearfix"></div>
            <button type="submit" class="btn-primary">Сохранить</button>
        </form>

        <!-- Таблица существующих заказов -->
        <h2>Существующие заказы</h2>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>ID заказа</th>
                    <th>Имя клиента</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo $order['order_id']; ?></td>
                        <td><?php echo $order['customer_fullname']; ?></td>
                        <td><?php echo $order['customer_email']; ?></td>
                        <td><?php echo $order['customer_phone']; ?></td>
                        <td><?php echo $order['total_price']; ?></td>
                        <td><?php echo $order['status']; ?></td>
                        <td><?php echo $order['created_at']; ?></td>
                        <td>
                            <button class="edit-btn" onclick="editOrder(<?php echo $order['order_id']; ?>)">Редактировать</button>
                            <button class="delete-btn" onclick="deleteOrder(<?php echo $order['order_id']; ?>)">Удалить</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
            function addPackage() {
                const container = document.getElementById('packages-container');
                const packageIndex = container.children.length;
            
                const packageDiv = document.createElement('div');
                packageDiv.classList.add('package-item');
            
                packageDiv.innerHTML = `
                    <div class="package-item-inner">
                        <label>Калории пакета:</label>
                        <input type="text" name="packages[${packageIndex}][calories]" placeholder="Введите калории">
                        
                        <label>Количество:</label>
                        <input type="number" name="packages[${packageIndex}][quantity]" placeholder="Введите количество">
                        
                        <label>Даты доставки:</label>
                        <input type="text" name="packages[${packageIndex}][delivery_dates]" class="delivery-dates-${packageIndex}" placeholder="Выберите даты">
                        
                        <button type="button" onclick="removePackage(this)">Удалить пакет</button>
                    </div>
                `;
            
                container.appendChild(packageDiv);
                flatpickr(`.delivery-dates-${packageIndex}`, {
                    mode: "multiple",
                    dateFormat: "Y-m-d",
                    onChange: function(selectedDates, dateStr, instance) {
                        // Присвоение строкового значения выбранных дат для отправки на сервер
                        const input = instance.element;
                        input.value = dateStr; // dateStr - это строка с выбранными датами, разделенными запятыми
                    }
                });

                // Логирование для отладки
                console.log(`Пакет добавлен: index=${packageIndex}`);
            }

                function removePackage(button) {
                    const packageItem = button.parentNode.parentNode;
                    packageItem.parentNode.removeChild(packageItem);
                    console.log('Пакет удален');
                }
    
                function editOrder(orderId) {
                    // Установить ID заказа в скрытое поле
                    document.getElementById('order_id').value = orderId;
                
                    // Выполнить AJAX-запрос для получения данных о заказе
                    fetch(`get_order_data.php?order_id=${orderId}`)
                        .then(response => response.json())
                        .then(orderData => {
                            // Заполнить форму данными из ответа
                            document.getElementById('customer_fullname').value = orderData.customer_fullname;
                            document.getElementById('customer_email').value = orderData.customer_email;
                            document.getElementById('customer_phone').value = orderData.customer_phone;
                            document.getElementById('customer_street').value = orderData.customer_street;
                            document.getElementById('customer_house_number').value = orderData.customer_house_number;
                            document.getElementById('customer_apartment').value = orderData.customer_apartment;
                            document.getElementById('customer_floor').value = orderData.customer_floor;
                            document.getElementById('customer_gate_code').value = orderData.customer_gate_code;
                            document.getElementById('total_price').value = orderData.total_price;
                            document.getElementById('status').value = orderData.status;
                        })
                        .catch(error => console.error('Ошибка при получении данных заказа:', error));
                    
                    console.log(`Редактирование заказа с ID: ${orderId}`);
                }

            function deleteOrder(orderId) {
                if (confirm('Вы уверены, что хотите удалить этот заказ?')) {
                    // Выполнить AJAX-запрос для удаления заказа
                    fetch(`delete_order.php?order_id=${orderId}`, { method: 'DELETE' })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                alert('Заказ успешно удален');
                                // Перезагрузить страницу для обновления списка заказов
                                location.reload();
                            } else {
                                alert('Ошибка при удалении заказа');
                            }
                        })
                        .catch(error => console.error('Ошибка при удалении заказа:', error));
                }
            }
        </script>
    </div>
</body>
</html>