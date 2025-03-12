<?php error_reporting(E_ALL);ini_set('display_errors',1);ini_set('log_errors',1);ini_set('error_log',$_SERVER['DOCUMENT_ROOT'].'/logs/error_log.log');session_start();if(!isset($_SESSION['admin_logged_in'])||$_SESSION['admin_logged_in']!==true){header('Location: /admin/admin.php');exit();}require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';if(!$pdo){die("Помилка підключення до бази даних.");}$notificationMessage="";if($_SERVER['REQUEST_METHOD']==='POST'){$customerFullname=isset($_POST['customer_fullname'])?trim($_POST['customer_fullname']):'';$customerEmail=isset($_POST['customer_email'])?trim($_POST['customer_email']):'';$customerPhone=isset($_POST['customer_phone'])?trim($_POST['customer_phone']):'';$customerStreet=isset($_POST['customer_street'])?trim($_POST['customer_street']):'';$customerHouseNumber=isset($_POST['customer_house_number'])?trim($_POST['customer_house_number']):'';$customerApartment=isset($_POST['customer_apartment'])?trim($_POST['customer_apartment']):'';$customerFloor=isset($_POST['customer_floor'])?trim($_POST['customer_floor']):'';$customerGateCode=isset($_POST['customer_gate_code'])?trim($_POST['customer_gate_code']):'';$customerKlatka=isset($_POST['customer_klatka'])?trim($_POST['customer_klatka']):'';$customerNotes=isset($_POST['customer_notes'])?trim($_POST['customer_notes']):'';$totalPrice=isset($_POST['total_price'])?trim($_POST['total_price']):'';$status=isset($_POST['status'])?trim($_POST['status']):'pending';$packages=isset($_POST['packages'])?$_POST['packages']:[];if($customerFullname!==''&&$customerEmail!==''&&$customerPhone!==''&&$totalPrice!==''&&!empty($packages)){try{$stmt=$pdo->prepare("INSERT INTO orders (customer_fullname, customer_email, customer_phone, customer_street, customer_house_number, customer_apartment, customer_floor, customer_gate_code, customer_klatka, customer_notes, total_price, created_at, status) VALUES (:customer_fullname, :customer_email, :customer_phone, :customer_street, :customer_house_number, :customer_apartment, :customer_floor, :customer_gate_code, :customer_klatka, :customer_notes, :total_price, NOW(), :status)");$stmt->execute([':customer_fullname'=>htmlspecialchars($customerFullname ?? '',ENT_QUOTES,'UTF-8'),':customer_email'=>htmlspecialchars($customerEmail ?? '',ENT_QUOTES,'UTF-8'),':customer_phone'=>htmlspecialchars($customerPhone ?? '',ENT_QUOTES,'UTF-8'),':customer_street'=>htmlspecialchars($customerStreet ?? '',ENT_QUOTES,'UTF-8'),':customer_house_number'=>htmlspecialchars($customerHouseNumber ?? '',ENT_QUOTES,'UTF-8'),':customer_apartment'=>htmlspecialchars($customerApartment ?? '',ENT_QUOTES,'UTF-8'),':customer_floor'=>htmlspecialchars($customerFloor ?? '',ENT_QUOTES,'UTF-8'),':customer_gate_code'=>htmlspecialchars($customerGateCode ?? '',ENT_QUOTES,'UTF-8'),':customer_klatka'=>htmlspecialchars($customerKlatka ?? '',ENT_QUOTES,'UTF-8'),':customer_notes'=>htmlspecialchars($customerNotes ?? '',ENT_QUOTES,'UTF-8'),':total_price'=>$totalPrice,':status'=>htmlspecialchars($status ?? 'pending',ENT_QUOTES,'UTF-8')]);$orderId=$pdo->lastInsertId();error_log("Новий заказ додано успішно з ID: ".$orderId);foreach($packages as $index=>$package){error_log("Обробляємо пакет №".($index+1).": ".print_r($package,true));if(isset($package['delivery_dates'])&&is_string($package['delivery_dates'])){$package['delivery_dates']=explode(',',$package['delivery_dates']);}if(isset($package['calories'],$package['quantity'],$package['delivery_dates'])&&is_numeric($package['calories'])&&is_numeric($package['quantity'])&&is_array($package['delivery_dates'])&&!empty($package['delivery_dates'])){$stmt=$pdo->prepare("INSERT INTO order_packages (order_id, calories, quantity) VALUES (:order_id, :calories, :quantity)");$stmt->execute([':order_id'=>$orderId,':calories'=>intval($package['calories']),':quantity'=>intval($package['quantity'])]);$orderPackageId=$pdo->lastInsertId();error_log("Пакет додано успішно з ID: ".$orderPackageId);foreach($package['delivery_dates']as $date){if(!empty($date)){$stmtDate=$pdo->prepare("INSERT INTO delivery_dates (order_package_id, delivery_date) VALUES (:order_package_id, :delivery_date)");$stmtDate->execute([':order_package_id'=>$orderPackageId,':delivery_date'=>trim($date)]);error_log("Дата доставки додана успішно для пакету ID: ".$orderPackageId.", дата: ".$date);}else{error_log("Попередження: дата доставки пуста для пакету ID: ".$orderPackageId);}}}else{error_log("Помилка: дані пакету некоректні або відсутні. Пакет №".($index+1).": ".print_r($package,true));}}$notificationMessage="Новий заказ успішно додано.";}catch(PDOException $e){error_log('Помилка при додаванні замовлення: '.$e->getMessage());$notificationMessage='Помилка при додаванні замовлення: '.$e->getMessage();}}else{$notificationMessage='Будь ласка, заповніть всі обов’язкові поля.';}} ?>


<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Додати нове замовлення</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/l10n/uk.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.css">
    <style>body{font-family:Arial,sans-serif;background-color:#f0f2f5;color:#333}.form-container{max-width:800px;margin:20px auto;padding:25px;border-radius:10px;background-color:#fff;box-shadow:0 0 20px rgb(0 0 0 / .1)}.form-group{margin-bottom:20px;display:flex;flex-direction:column;width:100%}.form-group label{margin-bottom:8px;font-weight:700;color:#555}.form-group input,.form-group textarea,.form-group select{padding:12px;border:1px solid #ccc;border-radius:5px;font-size:16px;transition:border-color 0.3s;width:100%;box-sizing:border-box}.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:#1565c0;outline:none}.form-group button{padding:15px;background-color:#1565c0;color:#fff;border:none;border-radius:30px;cursor:pointer;font-weight:700;font-size:18px;transition:background-color 0.3s ease}.form-group button:hover{background-color:#00897b}.button-add-package{padding:15px;background-color:#1565c0;color:#fff;border:none;border-radius:30px;cursor:pointer;font-weight:700;font-size:18px;transition:background-color 0.3s ease}.button-add-package:hover{background-color:#004ba0}.notification{position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:20px;background-color:#28a745;color:#fff;font-weight:700;border-radius:10px;box-shadow:0 0 10px rgb(0 0 0 / .2);z-index:1000;opacity:0;transition:opacity 0.5s ease,transform 0.5s ease}.notification.show{opacity:1;transform:translateX(-50%) translateY(0)}.notification.error{background-color:#dc3545}.package-group{border:1px solid #ddd;padding:20px;margin-bottom:20px;border-radius:8px;background-color:#fafafa;position:relative}.package-group h3{margin:0;margin-bottom:15px;color:#444}.delete-package-button{position:absolute;top:15px;right:15px;background-color:#f44336;color:#fff;border:none;border-radius:50%;width:30px;height:30px;cursor:pointer;font-size:16px;line-height:30px;text-align:center}.delete-package-button:hover{background-color:#d32f2f}.return-buttons{margin-top:20px;display:flex;justify-content:space-between}.return-buttons a{text-decoration:none;padding:12px 30px;border-radius:8px;font-weight:700;color:#fff;background-color:#1565c0;transition:background-color 0.3s ease}.return-buttons a:hover{background-color:#003c8f}</style>
</head>
<body>
    
    <?php if (!empty($notificationMessage)): ?>
        <div class="notification <?= strpos($notificationMessage, 'успішно') !== false ? 'success show' : 'error show' ?>">
            <?= htmlspecialchars($notificationMessage) ?>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const notification = document.querySelector('.notification');
                notification.style.display = 'block';
                setTimeout(function () {
                    notification.classList.add('show');
                    setTimeout(function () {
                        notification.classList.remove('show');
                    }, 4000);
                }, 100);
            });
        </script>
    <?php endif; ?>

<div class="form-container">
    <div class="return-buttons">
        <a href="/admin/orders_summary.php">Назад до Таблиці замовлень</a>
        <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
    </div>
    <h2>Додати нове замовлення</h2>
    <form action="add_order.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-group">
            <label for="customer_fullname">Повне ім'я клієнта:</label>
            <input type="text" name="customer_fullname" id="customer_fullname" required>
        </div>

        <div class="form-group">
            <label for="customer_email">Email клієнта:</label>
            <input type="email" name="customer_email" id="customer_email" required>
        </div>

        <div class="form-group">
            <label for="customer_phone">Телефон клієнта:</label>
            <input type="text" name="customer_phone" id="customer_phone" required>
        </div>

        <div class="form-group">
            <label for="customer_street">Вулиця:</label>
            <input type="text" name="customer_street" id="customer_street" required>
        </div>

        <div class="form-group">
            <label for="customer_house_number">Будинок:</label>
            <input type="text" name="customer_house_number" id="customer_house_number" required>
        </div>

        <div class="form-group">
            <label for="customer_apartment">Квартира:</label>
            <input type="text" name="customer_apartment" id="customer_apartment" required>
        </div>

        <div class="form-group">
            <label for="customer_floor">Поверх:</label>
            <input type="text" name="customer_floor" id="customer_floor" required>
        </div>

        <div class="form-group">
            <label for="customer_klatka">Під'їзд (Klatka):</label> <!-- Нове поле Klatka -->
            <input type="text" name="customer_klatka" id="customer_klatka">
        </div>

        <div class="form-group">
            <label for="customer_gate_code">Код дверей:</label>
            <input type="text" name="customer_gate_code" id="customer_gate_code">
        </div>

        <div class="form-group">
            <label for="customer_notes">Примітки:</label>
            <textarea name="customer_notes" id="customer_notes" rows="3"></textarea>
        </div>

        <div class="form-group">
            <label for="total_price">Загальна сума:</label>
            <input type="number" step="0.01" name="total_price" id="total_price" required>
        </div>

        <div class="form-group">
            <label for="status">Статус замовлення:</label>
            <select name="status" id="status" required>
                <option value="pending">pending</option>
                <option value="оплачен">оплачен</option>
            </select>
        </div>

        <div id="package-container">
            <div class="package-group" id="package-1">
                <h3>Пакет 1</h3>
                <button type="button" class="delete-package-button" onclick="deletePackage(1)">&times;</button>
                <div class="form-group">
                    <label for="packages_0_calories">Калорії пакета:</label>
                    <input type="number" name="packages[0][calories]" id="packages_0_calories" required>
                </div>
                <div class="form-group">
                    <label for="packages_0_quantity">Кількість:</label>
                    <input type="number" name="packages[0][quantity]" id="packages_0_quantity" required>
                </div>
                <div class="form-group">
                    <label for="packages_0_delivery_dates">Дати доставки:</label>
                    <input type="text" name="packages[0][delivery_dates]" class="delivery-dates-input" id="packages_0_delivery_dates" readonly="readonly" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <button type="button" class="button-add-package" onclick="addPackage()">Додати ще пакет</button>
        </div>

        <div class="form-group">
            <button type="submit">Додати замовлення</button>
        </div>
    </form>

    <!-- Кнопки повернення -->
    <div class="return-buttons">
        <a href="/admin/orders_summary.php">Назад до Таблиці замовлень</a>
        <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
    </div>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            initializeCalendars();
        });

        function initializeCalendars() {
            const dateInputs = document.querySelectorAll('.delivery-dates-input');
            dateInputs.forEach(function(input) {
                flatpickr(input, {
                    mode: "multiple",
                    dateFormat: "Y-m-d",
                    locale: "uk", // Указуємо локаль
                    firstDayOfWeek: 1 // Починаємо тиждень з понеділка
                });
            });
        }

        let packageCount = 1;
        function addPackage() {
            packageCount++;
            const packageContainer = document.getElementById('package-container');
            const packageGroup = document.createElement('div');
            packageGroup.classList.add('package-group');
            packageGroup.setAttribute('id', `package-${packageCount}`);
            packageGroup.innerHTML = `
                <h3>Пакет ${packageCount}</h3>
                <button type="button" class="delete-package-button" onclick="deletePackage(${packageCount})">&times;</button>
                <div class="form-group">
                    <label for="packages_${packageCount - 1}_calories">Калорії пакета:</label>
                    <input type="number" name="packages[${packageCount - 1}][calories]" id="packages_${packageCount - 1}_calories" required>
                </div>
                <div class="form-group">
                    <label for="packages_${packageCount - 1}_quantity">Кількість:</label>
                    <input type="number" name="packages[${packageCount - 1}][quantity]" id="packages_${packageCount - 1}_quantity" required>
                </div>
                <div class="form-group">
                    <label for="packages_${packageCount - 1}_delivery_dates">Дати доставки:</label>
                    <input type="text" name="packages[${packageCount - 1}][delivery_dates]" class="delivery-dates-input" id="packages_${packageCount - 1}_delivery_dates" readonly="readonly" required>
                </div>
            `;
            packageContainer.appendChild(packageGroup);
            initializeCalendars();
        }

        function deletePackage(id) {
            const packageElement = document.getElementById(`package-${id}`);
            if (packageElement) {
                packageElement.remove();
            }
        }
    </script>
</body>
</html>
