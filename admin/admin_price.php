<?php
// Подключаем базу данных и логирование ошибок
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php'); // Исправленный путь к базе данных
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Старт сессии для защиты от CSRF


// Генерация CSRF токена и сохранение в сессии, если его нет
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Генерируем случайный токен
}

// Проверка отладочной информации для проверки сессии
error_log("Debug: is_admin value in session - " . var_export($_SESSION['is_admin'], true));

// Проверяем авторизацию администратора
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    error_log("Admin Price Access Attempt - is_admin: " . ($_SESSION['is_admin'] ?? 'not set'));
    header('Location: /admin/admin.php');
    exit;
}

// Если данные были отправлены, обновляем цену, удаляем или добавляем запись
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF токен перед выполнением каких-либо действий
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Ошибка CSRF токена");
        $errorMessage = "Помилка безпеки. Будь ласка, оновіть сторінку і спробуйте ще раз.";
    } else {
        // Если токен корректен, продолжаем выполнение действий
        if (isset($_POST['price_id']) && isset($_POST['update_price'])) {
            // Обновление цены
            $priceId = (int)$_POST['price_id'];
            $newPrice = (float)$_POST['price'];

            try {
                $stmt = $pdo->prepare("UPDATE price SET price = ? WHERE id = ?");
                $stmt->execute([$newPrice, $priceId]);
                if ($stmt->rowCount() > 0) {
                    $successMessage = "Ціну успішно оновлено!";
                } else {
                    $errorMessage = "Не вдалося оновити ціну. Запис не знайдено.";
                }
            } catch (Exception $e) {
                error_log("Помилка при оновленні ціни: " . $e->getMessage());
                $errorMessage = "Помилка при оновленні ціни. Спробуйте пізніше.";
            }
        } elseif (isset($_POST['delete_id']) && isset($_POST['delete_price'])) {
            // Удаление записи
            $deleteId = (int)$_POST['delete_id'];
            
            // Логируем delete_id перед удалением
            error_log("Debug: delete_id value before deletion - " . var_export($deleteId, true));

            try {
                $stmt = $pdo->prepare("DELETE FROM price WHERE id = ?");
                $stmt->execute([$deleteId]);
                if ($stmt->rowCount() > 0) {
                    $successMessage = "Запис успішно видалено!";
                } else {
                    error_log("Debug: Не вдалося видалити запис з id: " . $deleteId);
                    $errorMessage = "Помилка при видаленні запису. Запис не була знайдена або не видалена.";
                }
            } catch (Exception $e) {
                error_log("Помилка при видаленні запису: " . $e->getMessage());
                $errorMessage = "Помилка при видаленні запису. Спробуйте пізніше.";
            }
        } elseif (isset($_POST['add_price'])) {
            // Добавление новой записи
            $newName = trim($_POST['name']);
            $newPrice = (float)$_POST['price'];

            if (!empty($newName) && $newPrice > 0) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO price (name, price) VALUES (?, ?)");
                    $stmt->execute([$newName, $newPrice]);
                    if ($stmt->rowCount() > 0) {
                        $successMessage = "Новий запис успішно додано!";
                    } else {
                        $errorMessage = "Помилка при додаванні нового запису.";
                    }
                } catch (Exception $e) {
                    error_log("Помилка при додаванні нового запису: " . $e->getMessage());
                    $errorMessage = "Помилка при додаванні нового запису. Спробуйте пізніше.";
                }
            } else {
                $errorMessage = "Будь ласка, заповніть всі поля коректно.";
            }
        }
    }
}

// Получаем текущие данные о продуктах
try {
    $stmt = $pdo->query("SELECT * FROM price");
    $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Помилка при отриманні даних про ціни: " . $e->getMessage());
    $prices = [];
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
<?php
$pageTitle = "Наші Ціни - Ресторан";
$metaDescription = "Перегляньте наші ціни на смачні та здорові страви!";
$metaKeywords = "ресторан, ціни, їжа, обіди, вечеря";
$metaAuthor = "Ваш Ресторан";

include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>
    <title>Управління цінами - Адмін Панель</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
</head>
<body>
<?php 
include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; // Подключаем шапку ?>
<div class="admin-container">
    <h1>Управління цінами</h1>

    <?php if (isset($successMessage)): ?>
        <div class="success-message"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>
    <?php if (isset($errorMessage)): ?>
        <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <table class="price-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Найменування</th>
                <th>Поточна ціна (PLN)</th>
                <th>Нова ціна (PLN)</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prices as $price): ?>
                <tr>
                    <td><?= htmlspecialchars($price['id']) ?></td>
                    <td><?= htmlspecialchars($price['name']) ?></td>
                    <td><?= htmlspecialchars($price['price']) ?></td>
                    <td>
                        <form method="post" action="" class="action-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="price_id" value="<?= htmlspecialchars($price['id']) ?>">
                            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($price['price']) ?>">
                            <button type="submit" name="update_price" class="btn-update">Оновити</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="" class="action-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($price['id']) ?>">
                            <button type="submit" name="delete_price" class="btn-delete">Видалити</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Форма для додавання нового запису -->
    <div class="add-price-form">
        <h3>Додати новий запис</h3>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="name">Найменування:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="price">Ціна (PLN):</label>
                <input type="number" step="0.01" id="price" name="price" required>
            </div>
            <button type="submit" name="add_price" class="btn-add">Додати</button>
        </form>
    </div>

    <a href="/admin/admin_panel.php" class="btn-return">Повернутись в адмін-панель</a>
</div>
<style>
@media (max-width: 1024px) {
    .admin-container {
        padding: 10px;
    }

    .price-table {
        font-size: 14px;
    }

    .price-table th, .price-table td {
        padding: 8px;
    }

    .action-form input[type="number"] {
        width: 80px;
    }

    .btn-update, .btn-delete, .btn-add {
        font-size: 14px;
        padding: 6px 10px;
    }
}

@media (max-width: 768px) {
    .price-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

    .price-table th, .price-table td {
        padding: 6px;
        font-size: 12px;
    }

    .form-group {
        display: block;
        width: 100%;
        text-align: center;
    }

    .form-group input {
        width: 100%;
        max-width: 300px;
        margin: 5px auto;
    }

    .btn-update, .btn-delete, .btn-add {
        width: 100%;
        max-width: 300px;
        font-size: 12px;
        padding: 6px;
    }

    .btn-return {
        display: block;
        text-align: center;
        margin-top: 10px;
    }
}

@media (max-width: 480px) {
    .admin-container {
        padding: 5px;
    }

    .price-table th, .price-table td {
        padding: 4px;
        font-size: 10px;
    }

    .action-form input[type="number"] {
        width: 60px;
        font-size: 12px;
    }

    .btn-update, .btn-delete, .btn-add {
        font-size: 10px;
        padding: 4px 8px;
    }

    .form-group input {
        font-size: 12px;
        padding: 6px;
    }

    .btn-return {
        font-size: 12px;
        padding: 6px 10px;
    }
}

</style>
</body>
</html>
