<?php require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';if(session_status()===PHP_SESSION_NONE){session_start();}ini_set('log_errors',1);ini_set('error_log',$_SERVER['DOCUMENT_ROOT'].'/logs/error_log.log');error_reporting(E_ALL);if(!isset($_SESSION['admin_logged_in'])||$_SESSION['admin_logged_in']!==true){header('Location: /admin/admin.php');exit();}if(empty($_SESSION['csrf_token'])){$_SESSION['csrf_token']=bin2hex(random_bytes(32));}$successMessage='';$errorMessage='';function getMenuPrices($pdo){return $pdo->query("SELECT * FROM menu_prices")->fetchAll();}function updateMenuPrice($pdo,$id,$newPrice){$stmt=$pdo->prepare("UPDATE menu_prices SET price = :price WHERE id = :id");return $stmt->execute(['price'=>$newPrice,'id'=>$id]);}function updateMenuWeight($pdo,$id,$newWeight){$stmt=$pdo->prepare("UPDATE menu_prices SET weight = :weight WHERE id = :id");return $stmt->execute(['weight'=>$newWeight,'id'=>$id]);}function deleteMenuPrice($pdo,$id){$stmt=$pdo->prepare("DELETE FROM menu_prices WHERE id = :id");return $stmt->execute(['id'=>$id]);}function addMenuPrice($pdo,$weight,$price,$category){$stmt=$pdo->prepare("INSERT INTO menu_prices (weight, price, category) VALUES (:weight, :price, :category)");return $stmt->execute(['weight'=>$weight,'price'=>$price,'category'=>$category]);}if($_SERVER['REQUEST_METHOD']==='POST'){if(!isset($_POST['csrf_token'])||$_POST['csrf_token']!==$_SESSION['csrf_token']){$errorMessage="CSRF token error.";}else{try{if(isset($_POST['update_price'])&&isset($_POST['price_id'])){$priceId=(int)$_POST['price_id'];$newPrice=(float)$_POST['price'];if($newPrice>0&&updateMenuPrice($pdo,$priceId,$newPrice)){$successMessage="Ціну оновлено!";}else{$errorMessage="Не вдалося оновити ціну.";}}if(isset($_POST['update_weight'])&&isset($_POST['price_id'])){$priceId=(int)$_POST['price_id'];$newWeight=(float)$_POST['weight'];if($newWeight>0&&updateMenuWeight($pdo,$priceId,$newWeight)){$successMessage="Вагу оновлено!";}else{$errorMessage="Не вдалося оновити вагу.";}}if(isset($_POST['delete_price'])&&isset($_POST['delete_id'])){$deleteId=(int)$_POST['delete_id'];if(deleteMenuPrice($pdo,$deleteId)){$successMessage="Запис видалено!";}else{$errorMessage="Помилка при видаленні.";}}if(isset($_POST['add_price'])){$newWeight=(float)$_POST['weight'];$newPrice=(float)$_POST['price'];$newCategory=trim($_POST['category']);if(!empty($newCategory)&&$newPrice>0&&$newWeight>0){if(addMenuPrice($pdo,$newWeight,$newPrice,$newCategory)){$successMessage="Запис додано!";}else{$errorMessage="Помилка при додаванні.";}}else{$errorMessage="Будь ласка, заповніть всі поля.";}}}catch(Exception $e){error_log("Помилка: ".$e->getMessage());$errorMessage="Системна помилка. Спробуйте пізніше.";}}}$menuPrices=getMenuPrices($pdo); ?>


<!doctype html>
<html lang=uk>
<head>
<meta charset=UTF-8>
<title>Управління цінами - Адмін Панель</title>
<link rel=stylesheet href=/assets/stylescss/admin_styles.css>
</head>
<body>
<div class=admin-container>
<a href=/admin/admin_panel.php class=btn-return>Повернутись в адмін-панель</a>
<h1>Управління цінами меню до вибора</h1>
<?php if (isset($successMessage)): ?>
<div class=success-message><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>
<?php if (isset($errorMessage)): ?>
<div class=error-message><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>
<table class=price-table>
<thead>
<tr>
<th>ID</th>
<th>Категорія</th>
<th>Вага (гр)</th>
<th>Оновити вагу</th>
<th>Ціна (PLN)</th>
<th>Оновити ціну</th>
<th>Дії</th>
</tr>
</thead>
<tbody>
<?php foreach ($menuPrices as $price): ?>
<tr>
<td><?= htmlspecialchars($price['id']) ?></td>
<td><?= htmlspecialchars($price['category']) ?></td>
<td><?= htmlspecialchars($price['weight']) ?></td>
<td>
<form method=post action="" class=action-form>
<input type=hidden name=csrf_token value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type=hidden name=price_id value="<?= htmlspecialchars($price['id']) ?>">
<input type=number step=0.01 name=weight value="<?= htmlspecialchars($price['weight']) ?>">
<button type=submit name=update_weight class=btn-update>Оновити</button>
</form>
</td>
<td><?= htmlspecialchars($price['price']) ?></td>
<td>
<form method=post action="" class=action-form>
<input type=hidden name=csrf_token value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type=hidden name=price_id value="<?= htmlspecialchars($price['id']) ?>">
<input type=number step=0.01 name=price value="<?= htmlspecialchars($price['price']) ?>">
<button type=submit name=update_price class=btn-update>Оновити</button>
</form>
</td>
<td>
<form method=post action="" class=action-form>
<input type=hidden name=csrf_token value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type=hidden name=delete_id value="<?= htmlspecialchars($price['id']) ?>">
<button type=submit name=delete_price class=btn-delete>Видалити</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class=add-price-form>
<h3>Додати новий запис</h3>
<form method=post action="">
<input type=hidden name=csrf_token value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<div class=form-group>
<label for=weight>Вага:</label>
<input type=number step=0.01 id=weight name=weight required>
</div>
<div class=form-group>
<label for=price>Ціна (PLN):</label>
<input type=number step=0.01 id=price name=price required>
</div>
<div class=form-group>
<label for=category>Категорія:</label>
<input id=category name=category required>
</div>
<button type=submit name=add_price class=btn-add>Додати</button>
</form>
</div>
<a href=/admin/admin_panel.php class=btn-return>Повернутись в адмін-панель</a>
</div>
</body>
</html>
