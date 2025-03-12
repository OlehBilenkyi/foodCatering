<?php require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';if(session_status()===PHP_SESSION_NONE){session_start();}if(!isset($_SESSION['admin_logged_in'])||$_SESSION['admin_logged_in']!==true){header('Location: /admin/admin.php');exit();}if(empty($_SESSION['csrf_token'])){$_SESSION['csrf_token']=bin2hex(random_bytes(32));}$successMessage='';$errorMessage='';if($_SERVER['REQUEST_METHOD']==='POST'){try{if(!isset($_POST['csrf_token'])||$_POST['csrf_token']!==$_SESSION['csrf_token']){throw new Exception("Ошибка CSRF токена. Повторите попытку.");}$delivery_email=trim($_POST['delivery_email']?? '');$phone=trim($_POST['phone']?? '');$full_name=trim($_POST['full_name']?? '');$street=trim($_POST['street']?? '');$house_number=trim($_POST['house_number']?? '');$building=trim($_POST['building']?? '');$floor=trim($_POST['floor']?? '');$apartment=trim($_POST['apartment']?? '');$entry_code=trim($_POST['entry_code']?? '');$notes=trim($_POST['notes']?? '');$total_price=trim($_POST['total_price']?? '0.00');$status='pending';$delivery_date=trim($_POST['delivery_date']?? '');$day_total_price=trim($_POST['day_total_price']?? '0.00');$categories=$_POST['category']??[];$dish_names=$_POST['dish_name']??[];$weights=$_POST['weight']??[];$prices=$_POST['price']??[];$menu_options_ids=$_POST['menu_options_id']??[];if(empty($delivery_email)||empty($phone)||empty($full_name)||empty($street)||empty($house_number)){throw new Exception("Пожалуйста, заполните обязательные поля заказа (контакты и адрес).");}if(empty($delivery_date)){throw new Exception("Введите дату доставки.");}if(count($categories)!==count($dish_names)||count($categories)!==count($weights)||count($categories)!==count($prices)){throw new Exception("Количество блюд и их параметров не совпадает.");}$has_sniadanie=false;$has_obiad=false;$has_kolacja=false;foreach($categories as $category){if($category==='śniadanie')$has_sniadanie=true;if($category==='obiad')$has_obiad=true;if($category==='kolacja')$has_kolacja=true;}if(!$has_sniadanie||!$has_obiad||!$has_kolacja){throw new Exception("Заказ должен содержать минимум одно блюдо из каждой категории: śniadanie, obiad, kolacja.");}$pdo->beginTransaction();$sql="INSERT INTO customer_menu_orders\n                    (order_date, total_price, delivery_email, phone, full_name, street, house_number, building, floor, apartment, entry_code, notes, status)\n                VALUES\n                    (NOW(), :total_price, :delivery_email, :phone, :full_name, :street, :house_number, :building, :floor, :apartment, :entry_code, :notes, :status)";$stmt=$pdo->prepare($sql);$stmt->execute([':total_price'=>$total_price,':delivery_email'=>$delivery_email,':phone'=>$phone,':full_name'=>$full_name,':street'=>$street,':house_number'=>$house_number,':building'=>$building,':floor'=>$floor,':apartment'=>$apartment,':entry_code'=>$entry_code,':notes'=>$notes,':status'=>$status,]);$order_id=$pdo->lastInsertId();$sql="INSERT INTO customer_menu_order_days\n                    (order_id, delivery_date, day_total_price)\n                VALUES\n                    (:order_id, :delivery_date, :day_total_price)";$stmt=$pdo->prepare($sql);$stmt->execute([':order_id'=>$order_id,':delivery_date'=>$delivery_date,':day_total_price'=>$day_total_price,]);$order_day_id=$pdo->lastInsertId();for($i=0;$i<count($categories);$i++){$sql="INSERT INTO customer_menu_order_items\n                        (order_day_id, category, dish_name, weight, price, menu_options_id)\n                    VALUES\n                        (:order_day_id, :category, :dish_name, :weight, :price, :menu_options_id)";$stmt=$pdo->prepare($sql);$stmt->execute([':order_day_id'=>$order_day_id,':category'=>$categories[$i],':dish_name'=>$dish_names[$i],':weight'=>$weights[$i],':price'=>$prices[$i],':menu_options_id'=>(empty($menu_options_ids[$i])?null:$menu_options_ids[$i]),]);}$pdo->commit();$successMessage="Заказ успешно добавлен!";}catch(Exception $e){$pdo->rollBack();error_log($e->getMessage());$errorMessage=$e->getMessage();}} ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавление заказа</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/l10n/uk.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.css">

    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    
    <style>
        
@media (max-width: 480px) {
    body {
        font-size: 14px;
    }

    .admin-container {
        padding: 5px;
        border-radius: 0;
        box-shadow: none;
    }

    .form-right-section,
    .form-left-section {
        width: 100%;
        float: none;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 15px;
    }

    .filter-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }

    .filter-buttons input,
    .form-right-section input {
        width: 100%;
        max-width: none;
        padding: 10px;
        font-size: 16px;
    }

    .order-summary-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

    .order-summary-table th,
    .order-summary-table td {
        padding: 8px 5px;
        font-size: 12px;
        min-width: 80px;
    }

    .narrow-column,
    .narrow-info {
        width: 60px;
        max-width: 60px;
    }

    .radio-buttons {
        flex-direction: column;
        gap: 5px;
    }

    .three_buttons {
        flex-direction: column;
        gap: 5px;
    }

    .filter-button,
    .generate-stickers-button,
    .details-button,
    .edit-button,
    .delete-button {
        width: 100%;
        padding: 10px;
        font-size: 14px;
    }

    .modal {
        width: 95%;
        padding: 10px;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
    }

    header h1 {
        font-size: 1.5em;
        padding: 10px 0;
    }

    .add-order-button {
        width: 100%;
        text-align: center;
        padding: 12px;
        margin-bottom: 10px;
    }

    .order-summary-table tr {
        display: flex;
        flex-wrap: wrap;
        border-bottom: 2px solid #ddd;
    }

    .order-summary-table td {
        flex: 1 1 45%;
        border: none;
        padding: 8px 5px;
    }

    .order-summary-table th {
        display: none;
    }

    .order-summary-table td::before {
        content: attr(data-label);
        font-weight: bold;
        display: block;
        margin-bottom: 3px;
        color: #1565c0;
        font-size: 0.9em;
    }
}

@media (max-width: 360px) {
    body {
        font-size: 13px;
    }
    
    .order-summary-table td {
        flex: 1 1 100%;
    }
    
    .filter-buttons input {
        font-size: 14px;
    }
    
    .modal {
        top: 10px;
        max-height: 90vh;
        overflow-y: auto;
    }
}

.order-form-container{max-width:800px;margin:20px auto;padding:25px;border-radius:10px;background-color:#fff;box-shadow:0 0 20px rgb(0 0 0 / .1)}.order-form-container h1{text-align:center;margin-bottom:20px}.order-form-container h2{margin-top:30px;border-bottom:1px solid #ccc;padding-bottom:5px}.order-form-container label{display:block;margin-bottom:5px;font-weight:700}.order-form-container input[type="text"],.order-form-container input[type="email"],.order-form-container input[type="number"],.order-form-container input[type="date"],.order-form-container textarea,.order-form-container select{width:100%;padding:8px;margin-bottom:15px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}.order-form-container input[type="submit"],.order-form-container button{background-color:#28a745;color:#fff;border:none;padding:12px 20px;border-radius:4px;cursor:pointer;font-size:1rem}.order-form-container input[type="submit"]:hover,.order-form-container button:hover{background-color:#218838}.message{text-align:center;font-size:1.2rem;margin-bottom:15px}.success{color:green}.error{color:red}.section{margin-bottom:30px}.dish-item{position:relative}.remove-btn{margin-bottom:15px;top:10px;right:10px;background-color:#dc3545}.remove-btn:hover{background-color:#c82333}.dish-item hr{border:0;border-top:1px solid #ccc;margin:20px 0}.dish-container-big{display:flex;justify-content:space-evenly;gap:5px}.return-buttons{display:flex;justify-content:space-evenly}.return-buttons a{text-decoration:none;padding:12px 30px;border-radius:8px;font-weight:700;color:#fff;background-color:#1565c0;transition:background-color 0.3s ease}.return-buttons a:hover{background-color:#003c8f}.return-buttons-down{margin-top:20px}</style>
</head>
<body>
    
<div class="order-form-container">
    <div class="return-buttons">
        <a href="/admin/admin_orders_menu_do_wyboru.php">Назад до Таблиці замовлень</a>
        <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
    </div>
    <h1>Додати нове замовлення</h1>

    <?php if (!empty($successMessage)): ?>
        <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>
    <?php if (!empty($errorMessage)): ?>
        <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <!-- Раздел для таблицы orders -->
        <div class="section">
         
            

            <label for="full_name">Повне ім'я клієнта:</label>
            <input type="text" id="full_name" name="full_name" required>

            <label for="delivery_email">Email клієнта:</label>
            <input type="email" id="delivery_email" name="delivery_email" required>

            <label for="phone">Телефон клієнта:</label>
            <input type="text" id="phone" name="phone" required>

           

            <label for="street">Вулиця:</label>
            <input type="text" id="street" name="street" required>

            <label for="house_number">Будинок:</label>
            <input type="text" id="house_number" name="house_number" required>

            <label for="apartment">Квартира:</label>
            <input type="text" id="apartment" name="apartment">
            
            <label for="floor">Поверх:</label>
            <input type="text" id="floor" name="floor">

            <label for="building">Під'їзд (Klatka):</label>
            <input type="text" id="building" name="building">

            <label for="entry_code">Код дверей:</label>
            <input type="text" id="entry_code" name="entry_code">

            <label for="notes">Примітки:</label>
            <textarea id="notes" name="notes" rows="4"></textarea>
            
            <label for="total_price">Загальна сума:</label>
            <input type="number" step="0.01" id="total_price" name="total_price" value="0.00">
            
            <div class="form-group">
            <label for="status">Статус замовлення:</label>
            <select name="status" id="status" required>
                <option value="paid">Paid</option>
                <option value="pending">Pending</option>
            </select>
        </div>
            
        </div>

        <!-- Раздел для таблицы order_day -->
        <div class="section">
            <h2>Дані доставки</h2>
           
            <div class="form-group">
                <label for="delivery_date">Дати доставки:</label>
                <input type="date" name="delivery_date" class="delivery-dates-input" id="delivery_date" readonly="readonly" required>
            </div>

            <label for="day_total_price">Ціна доставки:</label>
            <input type="number" step="0.01" id="day_total_price" name="day_total_price" value="0.00">
        </div>

        <!-- Раздел для таблицы items -->
        <div class="section" id="dish-section">
            <h2>Інформація про страви</h2>
            
            <div id="dish-container" class="dish-container-big">
                <!-- Блюдо по умолчанию: śniadanie -->
                <div class="dish-item">
                    <input type="hidden" name="category[]" value="śniadanie">
                    <label for="category_1">Категорія:</label>
                    <input type="text" id="category_1" value="śniadanie" disabled>

                    <label for="dish_name_1">Назва страви:</label>
                    <input type="text" id="dish_name_1" name="dish_name[]" required>

                    <label for="weight_1">Вага (г):</label>
                    <input type="number" id="weight_1" name="weight[]" required>

                    <label for="price_1">Ціна:</label>
                    <input type="number" step="0.01" id="price_1" name="price[]" required>

                    <label for="menu_options_id_1">№Страви (необов'язково):</label>
                    <input type="text" id="menu_options_id_1" name="menu_options_id[]">

                    
                </div>

                <!-- Блюдо по умолчанию: obiad -->
                <div class="dish-item">
                    <input type="hidden" name="category[]" value="obiad">
                    <label for="category_2">Категорія:</label>
                    <input type="text" id="category_2" value="obiad" disabled>

                    <label for="dish_name_2">Назва страви:</label>
                    <input type="text" id="dish_name_2" name="dish_name[]" required>

                    <label for="weight_2">Вага  (г):</label>
                    <input type="number" id="weight_2" name="weight[]" required>

                    <label for="price_2">Ціна:</label>
                    <input type="number" step="0.01" id="price_2" name="price[]" required>

                    <label for="menu_options_id_2">№Страви (необов'язково):</label>
                    <input type="text" id="menu_options_id_2" name="menu_options_id[]">

                    
                </div>

                <!-- Блюдо по умолчанию: kolacja -->
                <div class="dish-item">
                    <input type="hidden" name="category[]" value="kolacja">
                    <label for="category_3">Категорія:</label>
                    <input type="text" id="category_3" value="kolacja" disabled>

                    <label for="dish_name_3">Назва страви:</label>
                    <input type="text" id="dish_name_3" name="dish_name[]" required>

                    <label for="weight_3">Вага  (г):</label>
                    <input type="number" id="weight_3" name="weight[]" required>

                    <label for="price_3">Ціна:</label>
                    <input type="number" step="0.01" id="price_3" name="price[]" required>

                    <label for="menu_options_id_3">№Страви (необов'язково):</label>
                    <input type="text" id="menu_options_id_3" name="menu_options_id[]">

                    
                </div>
            </div>
            <button type="button" id="add-dish-btn">Додати ще одну страву</button>
        </div>

        <input type="submit" value="Додати замовлення">
        <div class="return-buttons return-buttons-down ">
        <a href="/admin/admin_orders_menu_do_wyboru.php">Назад до Таблиці замовлень</a>
        <a href="/admin/admin_panel.php">Повернутися в адмін-панель</a>
    </div>
    </form>
</div>

<script>
  document.getElementById('add-dish-btn').addEventListener('click', function() {
    const container = document.getElementById('dish-container');
    const dishCount = container.parentNode.children.length + 1; // Увеличиваем счетчик для новых блюд

    const newDish = document.createElement('div');
    newDish.className = 'dish-item';
    newDish.innerHTML = `
        <label for="category_${dishCount}">Категорія:</label>
        <select id="category_${dishCount}" name="category[]" required>
            <option value="">-- Виберіть категорію --</option>
            <option value="śniadanie">śniadanie</option>
            <option value="obiad">obiad</option>
            <option value="kolacja">kolacja</option>
        </select>

        <label for="dish_name_${dishCount}">Назва страви:</label>
        <input type="text" id="dish_name_${dishCount}" name="dish_name[]" required>

        <label for="weight_${dishCount}">Вага  (г):</label>
        <input type="number" id="weight_${dishCount}" name="weight[]" required>

        <label for="price_${dishCount}">Ціна:</label>
        <input type="number" step="0.01" id="price_${dishCount}" name="price[]" required>

        <label for="menu_options_id_${dishCount}">№Страви (необов'язково):</label>
        <input type="text" id="menu_options_id_${dishCount}" name="menu_options_id[]">

        <button type="button" class="remove-btn" onclick="removeDish(this)">Видалити</button>
        <hr> <!-- Добавляем разделительную линию для визуального отделения -->
    `;

    // Добавляем новый элемент после контейнера dish-container
    container.parentNode.insertBefore(newDish, container.nextSibling);
});

function removeDish(button) {
    const dishItem = button.parentElement;
    dishItem.remove();
}

// Добавляем код инициализации календарей
document.addEventListener('DOMContentLoaded', function () {
    initializeCalendars();
});

function initializeCalendars() {
    const dateInputs = document.querySelectorAll('.delivery-dates-input');
    dateInputs.forEach(function(input) {
        flatpickr(input, {
             mode: "single", // Режим выбора одной даты
            dateFormat: "Y-m-d",
            locale: "uk", // Указуємо локаль
            firstDayOfWeek: 1 // Починаємо тиждень з понеділка
        });
    });
}
</script>

</body>
</html>



