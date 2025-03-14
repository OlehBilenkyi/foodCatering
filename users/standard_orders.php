<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Проверка авторизации
if (!isset($_SESSION['user_email'])) {
    header("Location: /users/auth/login.php");
    exit();
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("[WARNING] CSRF token mismatch");
        header("HTTP/1.1 403 Forbidden");
        exit("Security error");
    }
}

// Database connection check
if (!isset($pdo)) {
    die("Database connection error");
}

class OrderService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getUserOrders($email) {
        $stmt = $this->pdo->prepare("
            SELECT
                o.order_id AS id,
                o.created_at,
                o.total_price,
                GROUP_CONCAT(DISTINCT d.delivery_date ORDER BY d.delivery_date) AS delivery_dates
            FROM orders o
            LEFT JOIN order_packages op ON o.order_id = op.order_id
            LEFT JOIN delivery_dates d ON op.id = d.order_package_id
            WHERE o.customer_email = ? AND o.status = 'оплачен'
            GROUP BY o.order_id
            ORDER BY o.created_at DESC
        ");

        try {
            $stmt->execute([$email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Order fetch error: ".$e->getMessage());
            return [];
        }
    }
}

$orderService = new OrderService($pdo);
$orders = $orderService->getUserOrders($_SESSION['user_email']);
?>
<!DOCTYPE html>
<html lang="pl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - FoodCase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3.3.4/air-datepicker.min.css">
    <style>
        :root {
            --primary: #2A2A2A;
            --secondary: #FF6B6B;
            --accent: #4ECDC4;
            --background: #1A1A1A;
            --text: #FFFFFF;
            --transition: all 0.3s ease;
        }

        [data-theme="light"] {
            --background: #FFFFFF;
            --text: #2A2A2A;
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
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(255,255,255,0.05);
            border-radius: 1rem;
        }

        .order-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .order-card {
            background: rgba(255,255,255,0.05);
            border-radius: 1rem;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            transition: var(--transition);
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .theme-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: rgba(255,255,255,0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            cursor: pointer;
        }

        .datepicker-container {
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-box-open"></i> My Orders</h1>
            <a href="/users/dashboard.php" class="button">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-basket fa-3x"></i>
                <p>No orders found</p>
            </div>
        <?php else: ?>
            <div class="order-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <h3>Order #<?= htmlspecialchars($order['id']) ?></h3>
                            <div class="order-meta">
                                <span><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                                <div class="price"><?= number_format($order['total_price'], 2) ?> zł</div>
                            </div>
                        </div>

                        <div class="delivery-dates">
                            <div class="datepicker-container" data-dates="<?= htmlspecialchars($order['delivery_dates'] ?? '') ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="theme-toggle" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/air-datepicker@latest/air-datepicker.min.js"></script>
    <script>
        // Theme management
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            document.querySelector('.theme-toggle i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Initialize inline datepickers
        document.querySelectorAll('.datepicker-container').forEach(container => {
            const dates = container.dataset.dates.split(',').filter(d => d.trim());
            const validDates = dates.filter(d => !isNaN(new Date(d).getTime()));

            new AirDatepicker(container, {
                inline: true,
                multipleDates: validDates.map(d => new Date(d)),
                dateFormat: 'yyyy-MM-dd',
                multipleDatesSeparator: ', ',
                minDate: new Date(),
                buttons: ['clear'],
                autoClose: false
            });
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.querySelector('.theme-toggle i').className = savedTheme === 'dark'
            ? 'fas fa-moon'
            : 'fas fa-sun';
    </script>
</body>
</html>
