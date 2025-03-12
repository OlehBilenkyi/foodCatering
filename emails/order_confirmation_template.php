<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Potwierdzenie zamówienia - FoodCase</title>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            max-width: 100%;
            margin: 0;
            background-color: #f8f8f8;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background-color: #e6e0ff;
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #5e35b1;
            font-weight: 600;
        }
        .logo {
            display: block;
            margin: 0 auto;
            width: 150px;
        }
        .section {
            padding: 15px;
            border-radius: 5px;
            background-color: #ffffff;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .section h3 {
            color: #5e35b1;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .icon {
            width: 20px;
            vertical-align: middle;
            margin-right: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }

        /* Адаптация для мобильных устройств */
        @media screen and (max-width: 768px) {
            .container {
                width: 90%;
                padding: 15px;
            }
            .logo {
                width: 120px;
            }
            .section {
                padding: 10px;
            }
            .section h3 {
                font-size: 1.1em;
            }
            .footer {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img class="logo" src="https://foodcasecatering.net/assets/img/logo.png" alt="FoodCase">
        <h1>Potwierdzenie zamówienia</h1>
        
        <div class="section">
            <h3>📦 Szczegóły zamówienia</h3>
            <p><strong>Numer zamówienia:</strong> <?php echo htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Data zamówienia:</strong> <?php echo htmlspecialchars($order_date, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Całkowita kwota:</strong> <?php echo htmlspecialchars($total_amount, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="section">
            <h3>🍽 Szczegóły pakietów</h3>
            <p><?php echo $package_details; ?></p>
        </div>

        <div class="section">
            <h3><img class="icon" src="https://cdn-icons-png.flaticon.com/512/684/684908.png" alt="Location"> Dane dostawy</h3>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer_email, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>📞 Telefon:</strong> <?php echo htmlspecialchars($customer_phone, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>👤 Imię i nazwisko:</strong> <?php echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>🏠 Adres:</strong> <?php echo htmlspecialchars($customer_street, ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($customer_house_number, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>🏢 Mieszkanie:</strong> <?php echo htmlspecialchars($customer_apartment, ENT_QUOTES, 'UTF-8'); ?> (Piętro: <?php echo htmlspecialchars($customer_floor, ENT_QUOTES, 'UTF-8'); ?>)</p>
            <p><strong>🔑 Kod do klatki:</strong> <?php echo htmlspecialchars($customer_gate_code, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <p class="footer">Dziękujemy za zamówienie w FoodCase! Smacznego! 🍽</p>
    </div>
</body>
</html>
