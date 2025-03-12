<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Виправлений шлях до бази даних
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/functions.php'; // Підключаємо функції

// Перевірка авторизації адміністратора
check_admin_auth();

// Увімкнення логування помилок для запису у файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логування всіх помилок для ретельної діагностики

// Генерація CSRF-токена, якщо він ще не встановлений
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Отримуємо ID запису, який потрібно редагувати, і перевіряємо його валідність
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
if (!$id) {
    echo "<div class='alert alert-danger'>Невірний ID запису. Будь ласка, поверніться до списку та виберіть коректний запис.</div>";
    exit;
}

// Обробка POST-запиту на збереження змін
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Перевірка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<div class='alert alert-danger'>Неправильний CSRF-токен. Будь ласка, перезавантажте сторінку та спробуйте знову.</div>";
        exit;
    }

    // Збереження змін
    $week_number = filter_var($_POST['week_number'], FILTER_VALIDATE_INT);
    $day_number = filter_var($_POST['day_number'], FILTER_VALIDATE_INT);
    $dish_data = [];

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads_img/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    for ($i = 1; $i <= 4; $i++) {
        if (!empty($_FILES["dish_{$i}_image"]['name'])) {
            $fileName = time() . '_' . basename($_FILES["dish_{$i}_image"]['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES["dish_{$i}_image"]['tmp_name'], $filePath)) {
                $dish_data[$i]['image'] = $fileName;
            } else {
                echo "<div class='alert alert-danger'>Помилка завантаження файлу для страви {$i}.</div>";
                exit;
            }
        } else {
            $dish_data[$i]['image'] = htmlspecialchars(trim($_POST["dish_{$i}_image"]));
        }

        $dish_data[$i]['title'] = htmlspecialchars(trim($_POST["dish_{$i}_title"]));
        $dish_data[$i]['description'] = htmlspecialchars(trim($_POST["dish_{$i}_description"]));
        $dish_data[$i]['ingredients'] = htmlspecialchars(trim($_POST["dish_{$i}_ingredients"]));
        $dish_data[$i]['allergens'] = htmlspecialchars(trim($_POST["dish_{$i}_allergens"]));
        $dish_data[$i]['energy'] = filter_var($_POST["dish_{$i}_energy"], FILTER_VALIDATE_FLOAT);
        $dish_data[$i]['fat'] = filter_var($_POST["dish_{$i}_fat"], FILTER_VALIDATE_FLOAT);
        $dish_data[$i]['carbohydrates'] = filter_var($_POST["dish_{$i}_carbohydrates"], FILTER_VALIDATE_FLOAT);
        $dish_data[$i]['protein'] = filter_var($_POST["dish_{$i}_protein"], FILTER_VALIDATE_FLOAT);
        $dish_data[$i]['salt'] = filter_var($_POST["dish_{$i}_salt"], FILTER_VALIDATE_FLOAT);
        $dish_data[$i]['net_mass'] = filter_var($_POST["dish_{$i}_net_mass"], FILTER_VALIDATE_FLOAT);
    }

    if ($week_number && $day_number) {
        try {
            // Оновлюємо запис у базі даних
            $stmt = $pdo->prepare("UPDATE weekly_menu SET
                week_number = ?, day_number = ?,
                dish_1_image = ?, dish_2_image = ?, dish_3_image = ?, dish_4_image = ?,
                dish_1_title = ?, dish_2_title = ?, dish_3_title = ?, dish_4_title = ?,
                dish_1_description = ?, dish_2_description = ?, dish_3_description = ?, dish_4_description = ?,
                dish_1_ingredients = ?, dish_2_ingredients = ?, dish_3_ingredients = ?, dish_4_ingredients = ?,
                dish_1_allergens = ?, dish_2_allergens = ?, dish_3_allergens = ?, dish_4_allergens = ?,
                dish_1_energy = ?, dish_2_energy = ?, dish_3_energy = ?, dish_4_energy = ?,
                dish_1_fat = ?, dish_2_fat = ?, dish_3_fat = ?, dish_4_fat = ?,
                dish_1_carbohydrates = ?, dish_2_carbohydrates = ?, dish_3_carbohydrates = ?, dish_4_carbohydrates = ?,
                dish_1_protein = ?, dish_2_protein = ?, dish_3_protein = ?, dish_4_protein = ?,
                dish_1_salt = ?, dish_2_salt = ?, dish_3_salt = ?, dish_4_salt = ?,
                dish_1_net_mass = ?, dish_2_net_mass = ?, dish_3_net_mass = ?, dish_4_net_mass = ?
                WHERE id = ?");
            $stmt->execute([
                $week_number, $day_number,
                $dish_data[1]['image'], $dish_data[2]['image'], $dish_data[3]['image'], $dish_data[4]['image'],
                $dish_data[1]['title'], $dish_data[2]['title'], $dish_data[3]['title'], $dish_data[4]['title'],
                $dish_data[1]['description'], $dish_data[2]['description'], $dish_data[3]['description'], $dish_data[4]['description'],
                $dish_data[1]['ingredients'], $dish_data[2]['ingredients'], $dish_data[3]['ingredients'], $dish_data[4]['ingredients'],
                $dish_data[1]['allergens'], $dish_data[2]['allergens'], $dish_data[3]['allergens'], $dish_data[4]['allergens'],
                $dish_data[1]['energy'], $dish_data[2]['energy'], $dish_data[3]['energy'], $dish_data[4]['energy'],
                $dish_data[1]['fat'], $dish_data[2]['fat'], $dish_data[3]['fat'], $dish_data[4]['fat'],
                $dish_data[1]['carbohydrates'], $dish_data[2]['carbohydrates'], $dish_data[3]['carbohydrates'], $dish_data[4]['carbohydrates'],
                $dish_data[1]['protein'], $dish_data[2]['protein'], $dish_data[3]['protein'], $dish_data[4]['protein'],
                $dish_data[1]['salt'], $dish_data[2]['salt'], $dish_data[3]['salt'], $dish_data[4]['salt'],
                $dish_data[1]['net_mass'], $dish_data[2]['net_mass'], $dish_data[3]['net_mass'], $dish_data[4]['net_mass'],
                $id
            ]);

            // Перенаправлення після успішного збереження
            header('Location: /admin/admin_menu.php?status=success');
            exit;
        } catch (Exception $e) {
            // Обробка помилки: виводимо повідомлення про помилку та логуємо помилку
            error_log("Помилка при збереженні даних: " . $e->getMessage());
            echo "<div class='alert alert-danger'>Помилка при збереженні даних: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Помилка: всі поля обов'язкові для заповнення.</div>";
    }
}

// Отримуємо дані для редагування
$stmt = $pdo->prepare("SELECT * FROM weekly_menu WHERE id = ?");
$stmt->execute([$id]);
$menu = $stmt->fetch();
if (!$menu) {
    echo "<div class='alert alert-danger'>Запис не знайдено. Будь ласка, поверніться до списку та виберіть коректний запис.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Редагування запису меню на тиждень в адміністративній панелі FoodCase">
    <title>Редагування меню - FoodCase Admin</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            Редагування запису меню
        </div>
        <div class="card-body">
            <form method="POST" action="edit_menu.php?id=<?= htmlspecialchars($id) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="button-container">
                    <button type="submit" class="btn btn-success">Зберегти зміни</button>
                    <a href="/admin/admin_menu.php" class="btn btn-secondary">Відміна</a>
                </div>

                <div class="form-group">
                    <label for="week_number">Номер тижня</label>
                    <input type="number" id="week_number" name="week_number" class="input-control" value="<?= htmlspecialchars($menu['week_number']) ?>">
                </div>
                <div class="form-group">
                    <label for="day_number">Номер дня</label>
                    <input type="number" id="day_number" name="day_number" class="input-control" value="<?= htmlspecialchars($menu['day_number']) ?>">
                </div>

                <div class="form-grid">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="card mt-3">
                            <div class="card-header">
                                Страва <?= $i ?>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_title">Назва страви</label>
                                    <input type="text" id="dish_<?= $i ?>_title" name="dish_<?= $i ?>_title" class="input-control" value="<?= htmlspecialchars($menu["dish_{$i}_title"]) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Поточне зображення:</label>
                                    <?php if (!empty($menu["dish_{$i}_image"])): ?>
                                        <img src="/uploads_img/<?= htmlspecialchars($menu["dish_{$i}_image"]) ?>" alt="Текущее изображение" style="max-width: 150px;">
                                    <?php else: ?>
                                        <span>Зображення не завантажено</span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_image">Нове зображення:</label>
                                    <input type="file" name="dish_<?= $i ?>_image" id="dish_<?= $i ?>_image" onchange="previewImage(event, <?= $i ?>)">
                                    <img id="preview_<?= $i ?>" style="max-width: 150px; display: none;">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_description">Опис страви</label>
                                    <textarea id="dish_<?= $i ?>_description" name="dish_<?= $i ?>_description" class="input-control" rows="3"><?= htmlspecialchars($menu["dish_{$i}_description"]) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_ingredients">Склад</label>
                                    <textarea id="dish_<?= $i ?>_ingredients" name="dish_<?= $i ?>_ingredients" class="input-control" rows="3"><?= htmlspecialchars($menu["dish_{$i}_ingredients"]) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_allergens">Алергени</label>
                                    <textarea id="dish_<?= $i ?>_allergens" name="dish_<?= $i ?>_allergens" class="input-control" rows="2"><?= htmlspecialchars($menu["dish_{$i}_allergens"]) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_energy">Енергетична цінність (ккал)</label>
                                    <input type="number" step="0.000000000000001" id="dish_<?= $i ?>_energy" name="dish_<?= $i ?>_energy" class="input-control" value="<?= htmlspecialchars($menu["dish_{$i}_energy"]) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_fat">Жири (г)</label>
                                    <input type="number" step="0.000000000000001" id="dish_<?= $i ?>_fat" name="dish_<?= $i ?>_fat" class="input-control" value="<?= htmlspecialchars($menu["dish_{$i}_fat"]) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_carbohydrates">Вуглеводи (г)</label>
                                    <input type="number" step="0.000000000000001" id="dish_<?= $i ?>_carbohydrates" name="dish_<?= $i ?>_carbohydrates" class="input-control" value="<?= htmlspecialchars($menu["dish_{$i}_carbohydrates"]) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_protein">Білок (г)</label>
                                    <input type="number" step="0.000000000000001" id="dish_<?= $i ?>_protein" name="dish_<?= $i ?>_protein" class="input-control" value="<?= htmlspecialchars($menu["dish_{$i}_protein"]) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_salt">Сіль (г)</label>
                                    <input type="number" step="0.000000000000001" id="dish_<?= $i ?>_salt" name="dish_<?= $i ?>_salt" class="input-control" value="<?= htmlspecialchars($menu["dish_{$i}_salt"]) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="dish_<?= $i ?>_net_mass">Маса нетто (г)</label>
                                    <input type="number" step="0.000000000000001" id="dish_<?= $i ?>_net_mass" name="dish_<?= $i ?>_net_mass" class="input-control" value="<?= htmlspecialchars($menu["dish_{$i}_net_mass"]) ?>">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="button-container">
                    <button type="submit" class="btn btn-success">Зберегти зміни</button>
                    <a href="/admin/admin_menu.php" class="btn btn-secondary">Відміна</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

</body>
</html>
