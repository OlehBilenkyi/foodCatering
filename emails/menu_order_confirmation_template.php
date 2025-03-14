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
            background-color: #e6e0ff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border-radius: 10px;
            overflow: hidden;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        table th {
            background-color: #d1c4e9;
            color: #333;
            font-weight: 600;
        }
        table tr:nth-child(even) {
            background-color: #f3e5f5;
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
            table th, table td {
                padding: 8px;
                font-size: 12px;
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
            <p><strong>Data zamówienia:</strong> <?php echo htmlspecialchars($order_date ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Całkowita kwota:</strong> <?php echo htmlspecialchars($total_amount ?? '0.00 zł', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        
        <div class="section">
            <h3>🍽 Zamówione dania</h3>
            <?php if (!empty($order_items)): ?>
                <?php foreach ($order_items as $day): ?>
                    <h4>📅 Data dostawy: <?php echo htmlspecialchars($day['delivery_date'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><strong>Kwota za dzień:</strong> <?php echo htmlspecialchars($day['day_total_price'], ENT_QUOTES, 'UTF-8'); ?> zł</p>
                    <table>
                        <tr>
                            <th>Kategoria</th>
                            <th>Nazwa dania</th>
                            <th>Waga (g)</th>
                            <th>Cena (zł)</th>
                        </tr>
                        <?php foreach ($day['items'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($item['dish_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($item['weight'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Brak zamówionych dań.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h3>📍 Dane dostawy</h3>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($delivery_email, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>📞 Telefon:</strong> <?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>👤 Imię i nazwisko:</strong> <?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>🏠 Adres:</strong> <?php echo htmlspecialchars($street, ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($house_number, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>🏢 Mieszkanie:</strong> <?php echo htmlspecialchars($apartment, ENT_QUOTES, 'UTF-8'); ?> (Piętro: <?php echo htmlspecialchars($floor, ENT_QUOTES, 'UTF-8'); ?>)</p>
            <p><strong>🔑 Kod do klatki:</strong> <?php echo htmlspecialchars($entry_code, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <p class="footer">Dziękujemy za zamówienie w FoodCase! Smacznego! 🍽</p>
    </div>
</body>
</html>
