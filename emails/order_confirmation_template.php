<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Potwierdzenie zamówienia - FoodCase</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f8f8f8;
        }
        h1 {
            color: #111;
            text-align: center;
        }
        .order-container {
            background-color: #ffffff;
            border: 1px solid #ddd;
            padding: 20px;
            margin-top: 20px;
            border-radius: 5px;
        }
        .order-image {
            display: block;
            width: 100px;
            margin: 0 auto 15px auto;
        }
        .section-title {
            color: #8200FF;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .section-content {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table td {
            padding: 3px 0;
            vertical-align: top;
        }
        table td:first-child {
            font-weight: bold;
            padding-right: 10px;
            color: #555;
        }
        .package-details {
            margin-bottom: 10px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <h1>Potwierdzenie zamówienia - FoodCase</h1>

    <div class="order-container">
        <!-- Путь к логотипу изменен на абсолютный URL -->
        <img class="order-image" src="https://foodcasecatering.net/assets/img/logo.png" alt="Zdjęcie produktu">

        <div class="section-content">
            <p><strong>Data zamówienia:</strong> <?php echo htmlspecialchars($order_date, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Całkowita kwota:</strong> <?php echo htmlspecialchars($total_amount, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="section-content">
            <h3 class="section-title">Szczegóły pakietów:</h3>
            <div class="package-details">
                <?php echo $package_details; ?>
            </div>
        </div>

        <div class="section-content">
            <h3 class="section-title">Dane dostawy:</h3>
            <table>
                <tr>
                    <td>Email:</td>
                    <td><?php echo htmlspecialchars($customer_email, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Numer telefonu:</td>
                    <td><?php echo htmlspecialchars($customer_phone, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Pełne imię i nazwisko:</td>
                    <td><?php echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Ulica:</td>
                    <td><?php echo htmlspecialchars($customer_street, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Dom:</td>
                    <td><?php echo htmlspecialchars($customer_house_number, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Piętro:</td>
                    <td><?php echo htmlspecialchars($customer_floor, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Mieszkanie:</td>
                    <td><?php echo htmlspecialchars($customer_apartment, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Kod do klatki:</td>
                    <td><?php echo htmlspecialchars($customer_gate_code, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Klatka:</td> <!-- Добавлено поле для Klatka -->
                    <td><?php echo htmlspecialchars($customer_klatka, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td>Uwagi:</td>
                    <td><?php echo htmlspecialchars($customer_notes, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>