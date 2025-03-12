<?php require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';require_once $_SERVER['DOCUMENT_ROOT'].'/admin/functions.php';if(session_status()===PHP_SESSION_NONE){session_start();}ini_set('log_errors',1);ini_set('error_log',$_SERVER['DOCUMENT_ROOT'].'/logs/error_log.log');error_reporting(E_ALL);if(!isset($_SESSION['admin_logged_in'])||$_SESSION['admin_logged_in']!==true){header('Location: /admin/admin.php');exit();}if(empty($_SESSION['csrf_token'])){$_SESSION['csrf_token']=bin2hex(random_bytes(32));}if($_SERVER['REQUEST_METHOD']==='POST'){if(!isset($_POST['csrf_token'])||$_POST['csrf_token']!==$_SESSION['csrf_token']){header('Location: /admin/admin_menu.php?status=csrf_error');exit();}}$successMessage='';if(isset($_GET['status'])){$statusMessages=['success'=>"Зміни успішно збережені!",'week_shifted'=>"Порядок тижнів змінено!",'deleted'=>"Запис успішно видалено!",'csrf_error'=>"Помилка CSRF-токена. Будь ласка, спробуйте ще раз.",'not_found'=>"Запис не знайдено або вже видалено.",'no_weeks_to_rotate'=>"Немає тижнів для переміщення.",'rotate_error'=>"Помилка при переміщенні тижнів. Спробуйте ще раз.",'add_error'=>"Помилка при додаванні нового запису. Будь ласка, спробуйте ще раз."];if(array_key_exists($_GET['status'],$statusMessages)){$successMessage=$statusMessages[$_GET['status']];}}if($_SERVER['REQUEST_METHOD']=='POST'&&isset($_POST['week_number'])){if(!isset($_POST['csrf_token'])||$_POST['csrf_token']!==$_SESSION['csrf_token']){header('Location: /admin/admin_menu.php?status=csrf_error');exit();}$week_number=htmlspecialchars($_POST['week_number']?? '');$day_number=htmlspecialchars($_POST['day_number']?? '');$dish_data=[];$uploadDir=$_SERVER['DOCUMENT_ROOT'].'/uploads_img/';if(!is_dir($uploadDir)){mkdir($uploadDir,0777,true);}for($i=1;$i<=4;$i++){$imageName='';if(!empty($_FILES["dish_{$i}_image"]['name'])){$fileName=time().'_'.basename($_FILES["dish_{$i}_image"]['name']);$filePath=$uploadDir.$fileName;if(move_uploaded_file($_FILES["dish_{$i}_image"]['tmp_name'],$filePath)){$imageName=$fileName;}else{header('Location: /admin/admin_menu.php?status=add_error');exit();}}$dish_data[$i]=['image'=>$imageName,'title'=>htmlspecialchars($_POST["dish_{$i}_title"]?? ''),'description'=>htmlspecialchars($_POST["dish_{$i}_description"]?? ''),'ingredients'=>htmlspecialchars($_POST["dish_{$i}_ingredients"]?? ''),'allergens'=>htmlspecialchars($_POST["dish_{$i}_allergens"]?? ''),'energy'=>htmlspecialchars($_POST["dish_{$i}_energy"]?? '0'),'fat'=>htmlspecialchars($_POST["dish_{$i}_fat"]?? '0'),'carbohydrates'=>htmlspecialchars($_POST["dish_{$i}_carbohydrates"]?? '0'),'protein'=>htmlspecialchars($_POST["dish_{$i}_protein"]?? '0'),'salt'=>htmlspecialchars($_POST["dish_{$i}_salt"]?? '0'),'net_mass'=>htmlspecialchars($_POST["dish_{$i}_net_mass"]?? '0')];}$valid=true;foreach($dish_data as $dish){if(empty($dish['image'])||empty($dish['title'])||empty($dish['description'])||empty($dish['ingredients'])||empty($dish['allergens'])){$valid=false;break;}}if(!empty($week_number)&&!empty($day_number)&&$valid){try{$stmt=$pdo->prepare("INSERT INTO weekly_menu (\n                week_number, day_number,\n                dish_1_image, dish_1_title, dish_1_description, dish_1_ingredients, dish_1_allergens, dish_1_energy, dish_1_fat, dish_1_carbohydrates, dish_1_protein, dish_1_salt, dish_1_net_mass,\n                dish_2_image, dish_2_title, dish_2_description, dish_2_ingredients, dish_2_allergens, dish_2_energy, dish_2_fat, dish_2_carbohydrates, dish_2_protein, dish_2_salt, dish_2_net_mass,\n                dish_3_image, dish_3_title, dish_3_description, dish_3_ingredients, dish_3_allergens, dish_3_energy, dish_3_fat, dish_3_carbohydrates, dish_3_protein, dish_3_salt, dish_3_net_mass,\n                dish_4_image, dish_4_title, dish_4_description, dish_4_ingredients, dish_4_allergens, dish_4_energy, dish_4_fat, dish_4_carbohydrates, dish_4_protein, dish_4_salt, dish_4_net_mass\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");$values=[$week_number,$day_number];for($i=1;$i<=4;$i++){$values=array_merge($values,array_values($dish_data[$i]));}$stmt->execute($values);header('Location: /admin/admin_menu.php?status=success');exit();}catch(Exception $e){error_log("Помилка при додаванні нового запису: ".$e->getMessage());header('Location: /admin/admin_menu.php?status=add_error');exit();}}}if(isset($_GET['move_week'])){try{$pdo->beginTransaction();$stmt=$pdo->prepare("SELECT MAX(week_number) FROM weekly_menu");$stmt->execute();$maxWeekNumber=$stmt->fetchColumn();if($maxWeekNumber){$stmt=$pdo->prepare("UPDATE weekly_menu SET week_number = :new_week_number WHERE week_number = 1");$stmt->execute(['new_week_number'=>$maxWeekNumber+1]);$stmt=$pdo->prepare("UPDATE weekly_menu SET week_number = week_number - 1 WHERE week_number > 1");$stmt->execute();$pdo->commit();header("Location: /admin/admin_menu.php?status=week_shifted");}else{header('Location: /admin/admin_menu.php?status=no_weeks_to_rotate');}}catch(Exception $e){$pdo->rollBack();error_log("Помилка при переміщенні тижня: ".$e->getMessage());header('Location: /admin/admin_menu.php?status=rotate_error');}exit();}try{$stmt=$pdo->query("SELECT * FROM weekly_menu ORDER BY week_number, day_number");$menus=$stmt->fetchAll();}catch(Exception $e){error_log("Помилка при витяганні даних: ".$e->getMessage());$menus=[];} ?>


<!DOCTYPE html>
<html lang="uk">
<head>
    <?php
    $pageTitle = "Наші Ціни - Ресторан";
    $metaDescription = "Перегляньте наші ціни на смачні та здорові страви!";
    $metaKeywords = "ресторан, ціни, їжа, обіди, вечеря";
    $metaAuthor = "Ваш Ресторан";

    include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';

    // Переконайтесь, що змінна $nonce визначена
    $nonce = $_SESSION['nonce'] ?? bin2hex(random_bytes(16)); // Переконайтесь, що $nonce завжди має значення
    ?>
    <title>Адмін - Управління меню на тиждень</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <style>.action-button{margin-right:15px}.compact-buttons .action-button:last-child{margin-right:0}.compact-buttons{display:flex;gap:15px}.dish-container{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px}.error-message{color:red;margin-top:5px;font-size:.9em}.button-container{display:flex;justify-content:center;gap:15px;margin-top:20px}.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgb(0 0 0 / .5)}.modal-content{background-color:#fff;margin:15% auto;padding:20px;border:1px solid #888;width:30%;text-align:center}.modal-buttons{margin-top:20px}.modal-buttons .btn{margin:0 10px}</style>
    <script>
    // Функція для закриття повідомлень про успіх
    function closeAlert(element) {
        element.style.display = 'none';
    }

    // Функція для перевірки форми перед відправкою
    function validateForm(event) {
        let isValid = true;
        const requiredFields = document.querySelectorAll('.input-control[required]');
        requiredFields.forEach(function (field) {
            if (field.value.trim() === '') {
                isValid = false;
                const errorSpan = field.nextElementSibling;
                if (errorSpan && errorSpan.classList.contains('error-message')) {
                    errorSpan.style.display = 'block';
                }
            } else {
                const errorSpan = field.nextElementSibling;
                if (errorSpan && errorSpan.classList.contains('error-message')) {
                    errorSpan.style.display = 'none';
                }
            }
        });

        if (!isValid) {
            event.preventDefault();
        }
    }

    // Функція для показу модального вікна підтвердження видалення
    function confirmDelete(url) {
        const modal = document.getElementById('deleteModal');
        const confirmButton = document.getElementById('confirmDeleteButton');
        confirmButton.onclick = function() {
            window.location.href = url;
        };
        modal.style.display = 'block';
    }

    // Функція для закриття модального вікна
    function closeModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    // Функція для предпросмотра загружаемого изображения
    function previewImage(event, index) {
        const input = event.target;
        const reader = new FileReader();
        reader.onload = function() {
            const img = document.getElementById('preview_' + index);
            img.src = reader.result;
            img.style.display = 'block';
        }
        if (input.files[0]) {
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</head>
<body>
<div class="admin-container mt-3">
    <div class="button-container">
        <form method="get" action="/admin/admin_menu.php">
            <button type="submit" name="move_week" class="btn-warning button-item">Перемістити поточний тиждень в кінець</button>
        </form>
        <a href="/admin/admin_panel.php" class="btn-secondary button-item">Повернутися в адмін-панель</a>
    </div>
</div>


<div class="admin-container mt-5">
    <h3 class="text-center">Існуючі записи</h3>
    <table class="table compact-table">
        <thead class="thead-dark">
        <tr>
            <th>Тиждень</th>
            <th>День</th>
            <th>Зображення страв</th>
            <th>Опис страв</th>
            <th>Дії</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($menus)) : ?>
            <?php foreach ($menus as $menu) : ?>
                <tr>
                    <td><?= htmlspecialchars($menu['week_number']) ?></td>
                    <td><?= htmlspecialchars($menu['day_number']) ?></td>
                    <td>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <img src='/uploads_img/<?= htmlspecialchars($menu["dish_{$i}_image"]) ?>' alt='Dish Image <?= $i ?>' class="dish-img">
                        <?php endfor; ?>
                    </td>
                    <td class='dish-description'>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <strong><?= htmlspecialchars($menu["dish_{$i}_title"]) ?>:</strong> <?= htmlspecialchars($menu["dish_{$i}_description"]) ?><br>
                            <em>Енергетична цінність:</em> <?= htmlspecialchars($menu["dish_{$i}_energy"]) ?> ккал<br>
                            <em>Жири:</em> <?= htmlspecialchars($menu["dish_{$i}_fat"]) ?> г<br>
                            <em>Вуглеводи:</em> <?= htmlspecialchars($menu["dish_{$i}_carbohydrates"]) ?> г<br>
                            <em>Білок:</em> <?= htmlspecialchars($menu["dish_{$i}_protein"]) ?> г<br>
                            <em>Сіль:</em> <?= htmlspecialchars($menu["dish_{$i}_salt"]) ?> г<br>
                            <em>Маса нетто:</em> <?= htmlspecialchars($menu["dish_{$i}_net_mass"]) ?> г<br>
                        <?php endfor; ?>
                    </td>
                    <td class='compact-buttons'>
                        <a href='/admin/edit_menu.php?id=<?= $menu['id'] ?>' class='btn btn-primary action-button'>Змінити</a>
                        <button onclick="confirmDelete('/admin/delete_dish.php?id=<?= $menu['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>')" class='btn btn-danger action-button delete-button'>Видалити</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan='5' class='text-center'>Немає записів для відображення.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>



<div class="admin-container mt-5">
    <?php if (!empty($successMessage)) : ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($successMessage) ?>
            <span class="close-alert" onclick="closeAlert(this.parentElement)">&times;</span>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            Додади меню на тиждень
        </div>

        <div class="card-body">
            <form method="POST" action="/admin/admin_menu.php" enctype="multipart/form-data" onsubmit="validateForm(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="form-group">
                    <label for="week_number">Номер тижня <span class="required">*</span></label>
                    <input type="number" id="week_number" name="week_number" class="input-control" required>
                    <span class="error-message" style="display: none;">Будь ласка, заповніть номер тижня</span>
                </div>
                <div class="form-group">
                    <label for="day_number">Номер дня <span class="required">*</span></label>
                    <input type="number" id="day_number" name="day_number" class="input-control" required>
                    <span class="error-message" style="display: none;">Будь ласка, заповніть номер дня</span>
                </div>
                <div class="dish-container">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="card mt-3">
                            <div class="card-header">
                                Страва <?= $i ?>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_title">Назва страви <span class="required">*</span></label>
                                    <input type="text" id="dish_<?= $i ?>_title" name="dish_<?= $i ?>_title" class="input-control" required>
                                    <span class="error-message" style="display: none;">Будь ласка, заповніть назву страви</span>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_image">Назва зображення <span class="required">*</span></label>
                                    <input type="file" id="dish_<?= $i ?>_image" name="dish_<?= $i ?>_image" class="input-control" required onchange="previewImage(event, <?= $i ?>)">
                                    <img id="preview_<?= $i ?>" style="max-width: 150px; display: none;">
                                    <span class="error-message" style="display: none;">Будь ласка, вкажіть зображення</span>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_description">Опис страви <span class="required">*</span></label>
                                    <textarea id="dish_<?= $i ?>_description" name="dish_<?= $i ?>_description" class="input-control" rows="3" required></textarea>
                                    <span class="error-message" style="display: none;">Будь ласка, заповніть опис страви</span>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_ingredients">Склад <span class="required">*</span></label>
                                    <textarea id="dish_<?= $i ?>_ingredients" name="dish_<?= $i ?>_ingredients" class="input-control" rows="3" required></textarea>
                                    <span class="error-message" style="display: none;">Будь ласка, заповніть склад страви</span>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_allergens">Алергени <span class="required">*</span></label>
                                    <textarea id="dish_<?= $i ?>_allergens" name="dish_<?= $i ?>_allergens" class="input-control" rows="2" required></textarea>
                                    <span class="error-message" style="display: none;">Будь ласка, вкажіть алергени</span>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_energy">Енергетична цінність (ккал)</label>
                                    <input type="number" step="0.01" id="dish_<?= $i ?>_energy" name="dish_<?= $i ?>_energy" class="input-control">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_fat">Жири (г)</label>
                                    <input type="number" step="0.01" id="dish_<?= $i ?>_fat" name="dish_<?= $i ?>_fat" class="input-control">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_carbohydrates">Вуглеводи (г)</label>
                                    <input type="number" step="0.01" id="dish_<?= $i ?>_carbohydrates" name="dish_<?= $i ?>_carbohydrates" class="input-control">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_protein">Білок (г)</label>
                                    <input type="number" step="0.01" id="dish_<?= $i ?>_protein" name="dish_<?= $i ?>_protein" class="input-control">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_salt">Сіль (г)</label>
                                    <input type="number" step="0.01" id="dish_<?= $i ?>_salt" name="dish_<?= $i ?>_salt" class="input-control">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_net_mass">Маса нетто (г)</label>
                                    <input type="number" step="0.01" id="dish_<?= $i ?>_net_mass" name="dish_<?= $i ?>_net_mass" class="input-control">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <button type="submit" class="btn btn-success mt-3">Зберегти</button>
                <div class="button-container">
                </div>
            </form>
        </div>
    </div>
</div>

<div class="admin-container mt-3">
    <div class="button-container">
        <form method="get" action="/admin/admin_menu.php">
            <button type="submit" name="move_week" class="btn-warning button-item">Перемістити поточний тиждень в кінець</button>
        </form>
        <a href="/admin/admin_panel.php" class="btn-secondary button-item">Повернутися в адмін-панель</a>
    </div>
</div>

<!-- Модальне вікно для підтвердження видалення -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Підтвердження видалення</h2>
        <p>Ви впевнені, що хочете видалити цей запис?</p>
        <div class="modal-buttons">
            <button id="confirmDeleteButton" class="btn btn-danger">Видалити</button>
            <button onclick="closeModal()" class="btn btn-secondary">Скасувати</button>
        </div>
    </div>
</div>

</body>
</html>
