<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Подключение к базе данных

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Стартуем сессию для использования CSRF токена
}

// Проверка, авторизован ли пользователь как администратор
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/admin.php');
    exit();
}

// Включение логгирования ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);



// Генерация CSRF-токена, если он еще не установлен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Переменные для хранения сообщений о статусе
$successMessage = '';
$errorMessage = '';

// Функция для получения данных из таблицы
function getMenuOptions($pdo) {
    $sql = "SELECT * FROM menu_options";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для обновления данных в таблице
function updateMenuOption($pdo, $id, $data) {
    $sql = "UPDATE menu_options SET
            category = :category,
            dish_name = :dish_name,
            dish_image = :dish_image,
            dish_title = :dish_title,
            dish_description = :dish_description,
            dish_ingredients = :dish_ingredients,
            dish_allergens = :dish_allergens,
            dish_energy = :dish_energy,
            dish_fat = :dish_fat,
            dish_carbohydrates = :dish_carbohydrates,
            dish_protein = :dish_protein,
            dish_salt = :dish_salt,
            dish_net_mass = :dish_net_mass
            WHERE menu_options_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

// Функция для добавления новой записи
function addMenuOption($pdo, $data) {
    $sql = "INSERT INTO menu_options (
            category,
            dish_name,
            dish_image,
            dish_title,
            dish_description,
            dish_ingredients,
            dish_allergens,
            dish_energy,
            dish_fat,
            dish_carbohydrates,
            dish_protein,
            dish_salt,
            dish_net_mass
            ) VALUES (
            :category,
            :dish_name,
            :dish_image,
            :dish_title,
            :dish_description,
            :dish_ingredients,
            :dish_allergens,
            :dish_energy,
            :dish_fat,
            :dish_carbohydrates,
            :dish_protein,
            :dish_salt,
            :dish_net_mass
            )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

// Функция для загрузки изображения
function uploadImage($file) {
    $target_dir = "../uploads_img_menu_do_wyboru/";
    $target_file = $target_dir . basename($file["name"]);

    if ($file["error"] === UPLOAD_ERR_OK) {
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($imageFileType, $allowedTypes)) {
            throw new Exception("Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.");
        }

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $target_file;
        } else {
            throw new Exception("Error uploading image.");
        }
    }
    return '';
}

// Обработка формы для добавления или обновления записи
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("CSRF token error. Please try again.");
        }

        // Получение и фильтрация данных из формы
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $category = htmlspecialchars(trim($_POST['category'] ?? ''));
        $dish_name = htmlspecialchars(trim($_POST['dish_name'] ?? ''));
        $dish_title = htmlspecialchars(trim($_POST['dish_title'] ?? ''));
        $dish_description = htmlspecialchars(trim($_POST['dish_description'] ?? ''));
        $dish_ingredients = htmlspecialchars(trim($_POST['dish_ingredients'] ?? ''));
        $dish_allergens = htmlspecialchars(trim($_POST['dish_allergens'] ?? ''));
        $dish_energy = htmlspecialchars(trim($_POST['dish_energy'] ?? ''));
        $dish_fat = htmlspecialchars(trim($_POST['dish_fat'] ?? ''));
        $dish_carbohydrates = htmlspecialchars(trim($_POST['dish_carbohydrates'] ?? ''));
        $dish_protein = htmlspecialchars(trim($_POST['dish_protein'] ?? ''));
        $dish_salt = htmlspecialchars(trim($_POST['dish_salt'] ?? ''));
        $dish_net_mass = htmlspecialchars(trim($_POST['dish_net_mass'] ?? ''));

        // Если новое изображение не выбрано, оставляем текущее
        if (isset($_FILES["dish_image"]) && $_FILES["dish_image"]["error"] === UPLOAD_ERR_OK) {
            $dish_image = uploadImage($_FILES["dish_image"]);
        } else {
            $dish_image = $_POST['current_image'] ?? '';
        }

        $data = [
            ':id'                => $id,
            ':category'          => $category,
            ':dish_name'         => $dish_name,
            ':dish_image'        => $dish_image,
            ':dish_title'        => $dish_title,
            ':dish_description'  => $dish_description,
            ':dish_ingredients'  => $dish_ingredients,
            ':dish_allergens'    => $dish_allergens,
            ':dish_energy'       => $dish_energy,
            ':dish_fat'          => $dish_fat,
            ':dish_carbohydrates'=> $dish_carbohydrates,
            ':dish_protein'      => $dish_protein,
            ':dish_salt'         => $dish_salt,
            ':dish_net_mass'     => $dish_net_mass
        ];

        if ($id) {
            updateMenuOption($pdo, $id, $data);
            $successMessage = "Record updated successfully!";
        } else {
            unset($data[':id']);
            addMenuOption($pdo, $data);
            $successMessage = "Record added successfully!";
        }

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Получение всех данных для отображения
$menuOptions = getMenuOptions($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Menu</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <style>
        /* Стили для админки */
        .admin-container { max-width: 100%; margin: 40px 0; padding: 20px; background-color: #ffffff; border-radius: 10px; box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1); text-align: center; width: 100%; }
        .admin-container h1 { font-size: 2.5rem; color: #2c3e50; margin-bottom: 20px; }
        .admin-container table th.id-col, .admin-container table td.id-col { width: 18px; }
        .admin-container form { width: 97%; margin-bottom: 20px; }
        .admin-container label { display: block; margin-bottom: 5px; font-size: 1rem; color: #333; }
        .admin-container input[type="text"], .admin-container input[type="number"], .admin-container input[type="file"], .admin-container textarea { width: 300px; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
        .admin-container input[type="submit"], .admin-container button { padding: 12px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; transition: background-color 0.3s ease; }
        .bS input[type="submit"] { margin-left: 30px; width: 300px; }
        .admin-container input[type="submit"]:hover, .admin-container button:hover { background-color: #218838; }
        .admin-container table { width: 97%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        .admin-container table, .admin-container th, .admin-container td { border: 1px solid #ddd; }
        .admin-container th, .admin-container td { padding: 6px; text-align: left; word-wrap: break-word; }
        .admin-container th { background-color: #f4f4f4; position: sticky; top: 0; z-index: 1; }
        .dish-img { width: 100px; }
        .admin-container td img { max-width: 100px; height: auto; }
        .admin-container .actions { display: flex; gap: 10px; justify-content: center; flex-direction: column; }
        .admin-container .actions a { padding: 5px 10px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem; }
        .admin-container .actions a:hover { background-color: #0056b3; }
        .admin-container .actions .delete { background-color: #dc3545; }
        .admin-container .actions .delete:hover { background-color: #c82333; }
        .admin-container .success-message { color: green; margin: 20px 0; font-size: 1rem; }
        .admin-container .error-message { color: red; margin-top: 20px; font-size: 1rem; }
        .admin-container .btn-return { display: inline-block; margin: 20px auto; background-color: #007bff; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; text-align: center; transition: background-color 0.3s ease; }
        .admin-container .btn-return:hover { background-color: #0056b3; }
        .edit-form { display: flex; justify-content: space-evenly; padding: 10px; box-shadow: 0 0 8px rgba(0, 0, 0, 1.13); }
        .formi { display: flex; flex-direction: column; }
        /* Стили модальных окон */
        .modal-content { background-color: #fff; padding: 20px; margin: 10% auto; border-radius: 8px; width: 80%; max-width: 600px; position: relative; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
         .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .delete-modal-content {
            position: relative;
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 30%;
            text-align: center;
        }
        .modal-buttons {
            margin-top: 20px;
        }
        .modal-buttons .btn {
            margin: 0 10px;
        }
         .actions .btn-red, .btn-secondary{
            background-color: #d9534f;
            color: white;
        }
        .modal-buttons .btn-danger{
            background-color: #d9534f;
            color: white;
        }
        .modal-buttons .btn-danger:hover {
            background-color: #c9302c;
            color: black;
        }
        .actions .btn-red:hover {
            background-color: #c9302c;
            color: black;
        }
        .close{
            cursor: pointer;
            width: 24px;
            height: 24px;
            position: absolute;
            top: 4px;
            right: 17px;
            font-size: 33px;
        }
        #modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        body.modal-open { overflow: hidden; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 25px; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: #000; text-decoration: none; }
        form input, form textarea { width: 100%; padding: 12px; margin: 12px 0; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-size: 1rem; }
        form textarea { min-height: 100px; resize: vertical; }
        button[type="submit"], #close-modal { background-color: #28a745; color: white; border: none; border-radius: 8px; padding: 12px 20px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease; }
        button[type="submit"]:hover, #close-modal:hover { background-color: #218838; }
        button#close-modal { background-color: #f44336; font-size: 18px; padding: 10px 15px; margin-top: 10px; }
        button#close-modal:hover { background-color: #d32f2f; }
        @media (max-width: 768px) {
            .modal-content { width: 90%; }
            .close-btn { font-size: 24px; top: 5px; right: 15px; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h2>Menu Options</h2>
        <a href="/admin/admin_panel.php" class="btn-return">Повернутися в адмін-панель</a>
        <table class="main-table" id="menuTable">
            <tr>
                <th class="id-col">ID</th>
                <th>Категорія</th>
                <th>Назва страви</th>
                <th class="dish-img">Фото</th>
                <th>Заголовок</th>
                <th>Опис страви</th>
                <th>Інгредієнти</th>
                <th>Алергени</th>
                <th>Енергія</th>
                <th>Жири</th>
                <th>Вуглеводи</th>
                <th>Білки</th>
                <th>Сіль</th>
                <th>Маса страви</th>
                <th>Дії</th>
            </tr>
            <?php foreach ($menuOptions as $option): ?>
                <tr>
                    <td><?php echo htmlspecialchars($option['menu_options_id']); ?></td>
                    <td><?php echo htmlspecialchars($option['category']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_name']); ?></td>
                    <td>
                        <?php if (!empty($option['dish_image'])): ?>
                            <img src="<?php echo htmlspecialchars($option['dish_image']); ?>" width="100">
                        <?php else: ?>
                            No Image
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($option['dish_title']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_description']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_ingredients']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_allergens']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_energy']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_fat']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_carbohydrates']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_protein']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_salt']); ?></td>
                    <td><?php echo htmlspecialchars($option['dish_net_mass']); ?></td>
                    <td class="actions">
                        <button class="editBtn" data-id="<?php echo $option['menu_options_id']; ?>">Змінити</button>
                        <button onclick="confirmDelete('<?php echo $option['menu_options_id']; ?>')" class="delete-button btn-red">Видалити</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <a href="/admin/admin_panel.php" class="btn-return">Повернутися в адмін-панель</a>

        <!-- Модальное окно для подтверждения удаления -->
        <div id="deleteModal" class="modal">
            <div class="delete-modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Підтвердження видалення</h2>
                <p>Ви впевнені, що хочете видалити цей запис?</p>
                <div class="modal-buttons">
                    <button id="confirmDeleteButton" class="btn btn-danger">Видалити</button>
                    <button onclick="closeModal()" class="btn btn-secondary">Скасувати</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно редактирования -->
        <div id="modal-overlay" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <span class="close-btn">&times;</span>
                <h2>Редагування страви</h2>
                <form id="editForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" id="editId">
                    <input type="hidden" name="current_image" id="currentImage">

                    <label for="category">Категорія:</label>
                    <input type="text" id="category" name="category"><br>
                    <label for="dish_name">Назва страви:</label>
                    <input type="text" id="dish_name" name="dish_name"><br>
                    <label for="dish_image">Фото:</label>
                    <input type="file" id="dish_image" name="dish_image" accept="image/*"><br>
                    <div id="current-image-container">
                        <p>Current Image: <span id="current-image-text"></span></p>
                        <img id="current-image-preview" src="" alt="Current Image" style="max-width: 100px; height: auto; display: none;">
                    </div>
                    <div id="image-preview-container" style="display:none;">
                        <p>Preview:</p>
                        <img id="image-preview" src="" alt="Image Preview" style="max-width: 100px; height: auto;">
                    </div>
                    <label for="dish_title">Заголовок:</label>
                    <input type="text" id="dish_title" name="dish_title"><br>
                    <label for="dish_description">Опис страви:</label>
                    <textarea id="dish_description" name="dish_description"></textarea><br>
                    <label for="dish_ingredients">Інгредієнти:</label>
                    <textarea id="dish_ingredients" name="dish_ingredients"></textarea><br>
                    <label for="dish_allergens">Алергени:</label>
                    <textarea id="dish_allergens" name="dish_allergens"></textarea><br>
                    <label for="dish_energy">Енергія страви (ккал):</label>
                    <input type="number" id="dish_energy" name="dish_energy"><br>
                    <label for="dish_fat">Жири (г):</label>
                    <input type="number" id="dish_fat" name="dish_fat"><br>
                    <label for="dish_carbohydrates">Вуглеводи (г):</label>
                    <input type="number" id="dish_carbohydrates" name="dish_carbohydrates"><br>
                    <label for="dish_protein">Білки (г):</label>
                    <input type="number" id="dish_protein" name="dish_protein"><br>
                    <label for="dish_salt">Сіль (г):</label>
                    <input type="number" id="dish_salt" name="dish_salt"><br>
                    <label for="dish_net_mass">Маса страви (g):</label>
                    <input type="number" id="dish_net_mass" name="dish_net_mass"><br>
                    <button type="submit">Зберегти зміни</button>
                </form>
            </div>
        </div>

        <h1>Додати нову страву</h1>

        <?php if (!empty($successMessage)): ?>
            <div class="success-message"><?php echo $successMessage; ?></div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message"><?php echo $errorMessage; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="edit-form">
                <div class="formi">
                    <label for="category">Категорія:</label>
                    <input type="text" name="category"><br>
                    <label for="dish_name">Назва страви:</label>
                    <input type="text" name="dish_name"><br>
                    <label for="dish_image">Фото:</label>
                    <input type="file" name="dish_image"><br>
                    <label for="dish_title">Заголовок:</label>
                    <input type="text" name="dish_title"><br>
                    <label for="dish_description">Опис страви:</label>
                    <textarea name="dish_description"></textarea><br>
                </div>
                <div class="formi">
                    <label for="dish_ingredients">Інгредієнти:</label>
                    <textarea name="dish_ingredients"></textarea><br>
                    <label for="dish_allergens">Алергени:</label>
                    <textarea name="dish_allergens"></textarea><br>
                    <label for="dish_energy">Енергія страви (ккал):</label>
                    <input type="number" step="0.01" name="dish_energy"><br>
                    <label for="dish_fat">Жири (г):</label>
                    <input type="number" step="0.01" name="dish_fat"><br>
                </div>
                <div class="formi">
                    <label for="dish_carbohydrates">Вуглеводи (г):</label>
                    <input type="number" step="0.01" name="dish_carbohydrates"><br>
                    <label for="dish_protein">Білки (г):</label>
                    <input type="number" step="0.01" name="dish_protein"><br>
                    <label for="dish_salt">Сіль (г):</label>
                    <input type="number" step="0.01" name="dish_salt"><br>
                    <label for="dish_net_mass">Маса страви (g):</label>
                    <input type="number" step="0.01" name="dish_net_mass"><br>
                </div>
            </div>
            <div class="bS">
                <input type="submit" value="Додати страву">
            </div>
        </form>

        <a href="/admin/admin_panel.php" class="btn-return">Повернутися в адмін-панель</a>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.getElementById("modal-overlay");
            const closeBtn = document.querySelector(".close-btn");
            const editOptions = <?php echo json_encode($menuOptions); ?>;

            document.addEventListener("click", function(event) {
                if (event.target.classList.contains("editBtn")) {
                    event.preventDefault();
                    const id = event.target.getAttribute("data-id");
                    const option = editOptions.find(option => option.menu_options_id == id);

                    if (option) {
                        document.getElementById("editId").value = option.menu_options_id;
                        document.getElementById("currentImage").value = option.dish_image;
                        document.getElementById("category").value = option.category;
                        document.getElementById("dish_name").value = option.dish_name;
                        document.getElementById("dish_title").value = option.dish_title;
                        document.getElementById("dish_description").value = option.dish_description;
                        document.getElementById("dish_ingredients").value = option.dish_ingredients;
                        document.getElementById("dish_allergens").value = option.dish_allergens;
                        document.getElementById("dish_energy").value = option.dish_energy;
                        document.getElementById("dish_fat").value = option.dish_fat;
                        document.getElementById("dish_carbohydrates").value = option.dish_carbohydrates;
                        document.getElementById("dish_protein").value = option.dish_protein;
                        document.getElementById("dish_salt").value = option.dish_salt;
                        document.getElementById("dish_net_mass").value = option.dish_net_mass;

                        const currentImageText = document.getElementById("current-image-text");
                        const currentImagePreview = document.getElementById("current-image-preview");

                        if (option.dish_image) {
                            currentImageText.textContent = `Current Image: ${option.dish_image}`;
                            currentImagePreview.src = option.dish_image;
                            currentImagePreview.style.display = "block";
                        } else {
                            currentImageText.textContent = "No image selected.";
                            currentImagePreview.style.display = "none";
                        }
                    }

                    modal.style.display = "flex";
                    document.body.style.overflow = "hidden";
                }
            });

            closeBtn.addEventListener("click", function() {
                modal.style.display = "none";
                document.body.style.overflow = "auto";
            });

            window.addEventListener("click", function(event) {
                if (event.target === modal) {
                    modal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });

            document.querySelector('input[type="file"][name="dish_image"]').addEventListener('change', function(event) {
                const file = event.target.files[0];
                const imagePreview = document.getElementById("image-preview");
                const imagePreviewContainer = document.getElementById("image-preview-container");

                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Функция для показа модального окна подтверждения удаления
            window.confirmDelete = function(id) {
                const deleteModal = document.getElementById('deleteModal');
                const confirmButton = document.getElementById('confirmDeleteButton');
                confirmButton.onclick = function() {
                    window.location.href = `/admin/delete_dish_menu_do_wyboru.php?id=${id}&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>`;
                };
                deleteModal.style.display = 'block';
            };

            // Функция для закрытия модального окна подтверждения удаления
            window.closeModal = function() {
                document.getElementById('deleteModal').style.display = 'none';
            };
        });
    </script>
</body>
</html>
