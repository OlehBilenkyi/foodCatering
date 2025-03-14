<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);

// Security Enhancements
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// CSRF Token Generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Connection Check
if (!isset($pdo) || !$pdo) {
    error_log("Database connection error");
    die("System error. Please try later.");
}

// User Authentication
$user_email = $_SESSION['user_email'] ?? null;
if (!$user_email) {
    header("Location: /users/auth/login.php");
    exit();
}

// Fetch Orders with Optimized Query
try {
    $stmt = $pdo->prepare("
        SELECT 
            o.order_id, 
            o.order_date, 
            o.total_price, 
            d.order_day_id, 
            d.delivery_date, 
            i.category, 
            i.dish_name, 
            i.weight, 
            i.price
        FROM customer_menu_orders o
        LEFT JOIN customer_menu_order_days d ON o.order_id = d.order_id
        LEFT JOIN customer_menu_order_items i ON d.order_day_id = i.order_day_id
        WHERE o.delivery_email = ? 
        AND o.status = 'paid'
        ORDER BY o.order_date DESC, d.delivery_date ASC
    ");
    $stmt->execute([$user_email]);
    $ordersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process Data Structure
// Process Data Structure
$orders = [];
foreach ($ordersData as $row) {
    $orderId = $row['order_id'];

    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'order_id' => $orderId,
            'order_date' => $row['order_date'],
            'total_price' => $row['total_price'],
            'days' => []
        ];
    }

    $dayId = $row['order_day_id'];
    if (empty($dayId)) continue;

    if (!isset($orders[$orderId]['days'][$dayId])) {
        $orders[$orderId]['days'][$dayId] = [
            'delivery_date' => $row['delivery_date'],
            'meals' => [
                'Śniadanie' => null, // Initially no meal
                'Obiad' => null,
                'Kolacja' => null
            ]
        ];
    }

    if (!empty($row['category'])) {
        $category = mb_convert_case($row['category'], MB_CASE_TITLE, "UTF-8");
        if (in_array($category, ['Śniadanie', 'Obiad', 'Kolacja']) && $orders[$orderId]['days'][$dayId]['meals'][$category] === null) {
            // Only add meal if it's not already set for this category
            $orders[$orderId]['days'][$dayId]['meals'][$category] = [
                'dish_name' => $row['dish_name'] ?? 'N/D',
                'weight' => $row['weight'] ?? 'N/D',
                'price' => $row['price'] ?? 'N/D'
            ];
        }
    }
}

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("System error. Please try later.");
}
?>

<!DOCTYPE html>
<html lang="pl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moje zamówienia - FoodCase</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3.3.4/air-datepicker.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A2A2A;
            --secondary: #FF6B6B;
            --accent: #4ECDC4;
            --background: #FFFFFF;
            --text: #2A2A2A;
            --transition: all 0.3s ease;
        }

        [data-theme="dark"] {
            --primary: #FFFFFF;
            --background: #1A1A1A;
            --text: #FFFFFF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
            padding: 2rem;
        }

        .order-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(var(--primary), 0.05);
            border-radius: 1rem;
        }

        .order-card {
            background: rgba(255,255,255,0.95);
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: var(--transition);
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .day-accordion {
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding: 1rem;
        }

        .meal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .meal-card {
            background: rgba(var(--accent), 0.05);
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .date-picker {
            cursor: pointer;
            color: var(--accent);
        }

        .notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: 500;
            z-index: 1000;
        }

        .notification.success {
            background: #4CAF50;
            color: white;
        }

        .notification.error {
            background: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <div class="order-container">
        <div class="order-header">
            <h1>Twoje zamówienia 🍴</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Powrót
            </a>
        </div>

  <?php if (empty($orders)): ?>
    <div class="no-orders">
        <i class="fas fa-box-open fa-3x"></i>
        <p>Brak aktywnych zamówień</p>
    </div>
<?php else: ?>
    <?php foreach ($orders as $order): ?>
        <div class="order-card">
            <div class="order-info">
                <h3>Zamówienie #<?= htmlspecialchars($order['order_id']) ?></h3>
                <p>Data zamówienia: <?= date('d.m.Y H:i', strtotime($order['order_date'])) ?></p>
                <p class="total-price">Suma: <?= htmlspecialchars($order['total_price']) ?> zł</p>
            </div>

            <?php foreach ($order['days'] as $dayId => $day): ?>
                <div class="day-accordion">
                    <div class="day-header">
                        <i class="fas fa-calendar-alt date-picker" 
                           data-day-id="<?= $dayId ?>"
                           data-date="<?= date('d-m-Y', strtotime($day['delivery_date'])) ?>"></i>
                        <span><?= date('d M Y', strtotime($day['delivery_date'])) ?></span>
                    </div>
                    
                    <div class="meal-grid">
                        <?php 
                        // Проверяем, если для этого дня есть блюда
                        foreach ($day['meals'] as $category => $meal): 
                            // Игнорируем пустые блюда
                            if ($meal['dish_name'] === 'N/D') continue;
                        ?>
                            <div class="meal-card">
                                <h4><?= htmlspecialchars($category) ?></h4>
                                <p><?= htmlspecialchars($meal['dish_name']) ?></p>
                                <small><?= htmlspecialchars($meal['weight']) ?>g</small>
                                <div class="price"><?= htmlspecialchars($meal['price']) ?> zł</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/air-datepicker@latest/air-datepicker.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.date-picker').forEach(picker => {
                const initialDate = picker.dataset.date;
                const dayId = picker.dataset.dayId;

                new AirDatepicker(picker, {
                    dateFormat: 'dd-mm-yyyy',
                    minDate: new Date(),
                    selectedDates: [initialDate],
                    onSelect: ({ formattedDate }) => {
                        updateDeliveryDate(dayId, formattedDate);
                    }
                });
            });
        });

        async function updateDeliveryDate(dayId, newDate) {
            try {
                const response = await fetch('update_delivery_date_custom_menu_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                    },
                    body: JSON.stringify({
                        order_day_id: dayId,
                        new_delivery_date: newDate
                    })
                });

                const data = await response.json();
                if (data.success) {
                    showNotification('Data dostawy zaktualizowana pomyślnie!', 'success');
                } else {
                    throw new Error(data.message || 'Wystąpił nieznany błąd');
                }
            } catch (error) {
                showNotification(`Błąd: ${error.message}`, 'error');
            }
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 3000);
        }
    </script>
</body>
</html>