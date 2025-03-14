<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Проверяем активность сессии и запускаем её при необходимости
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключаем базу данных
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');

// Подключаем dotenv для загрузки переменных окружения
require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Получаем ключи Stripe из переменных окружения
$stripePublishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? null;
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;

if (!$stripePublishableKey || !$stripeSecretKey) {
    error_log("Ошибка: Переменные Stripe не установлены.");
    exit("Ошибка: Переменные Stripe не установлены.");
}

// Генерация CSRF-токена, если его ещё нет в сессии
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("Сгенерирован новый CSRF-токен: " . $_SESSION['csrf_token']);
} else {
    error_log("Используется существующий CSRF-токен из сессии: " . $_SESSION['csrf_token']);
}

$csrfToken = $_SESSION['csrf_token'];

// Инициализируем переменную $nonce
$nonce = isset($nonce) ? htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') : '';

// Логирование данных сессии на старте
error_log('Данные сессии на старте: ' . print_r($_SESSION, true));

// Обработка POST-запроса для сбора данных о заказе
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Логирование данных сессии до изменения
    error_log('Данные сессии до обновления: ' . print_r($_SESSION, true));

    // Проверяем CSRF-токен из POST-запроса
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log('Ошибка безопасности: Неверный CSRF-токен.');
        exit("Ошибка безопасности: Неверный CSRF-токен.");
    }
    error_log('CSRF-токен успешно проверен.');

    // Валидация и сохранение данных заказа
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        error_log("Ошибка: Неверный формат email.");
        exit("Ошибка: Неверный формат email.");
    }
    $_SESSION['customer_email'] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_phone'] = htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_fullname'] = htmlspecialchars($_POST['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_street'] = htmlspecialchars($_POST['street'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_house_number'] = htmlspecialchars($_POST['house_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_apartment'] = htmlspecialchars($_POST['apartment'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_floor'] = htmlspecialchars($_POST['floor'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_gate_code'] = htmlspecialchars($_POST['gate_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_klatka'] = htmlspecialchars($_POST['klatka'] ?? '', ENT_QUOTES, 'UTF-8');
    $_SESSION['customer_notes'] = htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8');

    // Логирование данных сессии после изменения
    error_log('Данные сессии после обновления: ' . print_r($_SESSION, true));

    // Сохранение информации о пакетах
    if (isset($_POST['packages']) && is_array($_POST['packages'])) {
        $_SESSION['packages'] = array_map(function ($package) {
            if (!isset($package['calories'], $package['price'], $package['quantity'], $package['uuid'])) {
                error_log("Ошибка: Пакет не содержит необходимых данных.");
                return null;
            }

            return [
                'uuid' => htmlspecialchars($package['uuid'], ENT_QUOTES, 'UTF-8'),
                'calories' => htmlspecialchars($package['calories'], ENT_QUOTES, 'UTF-8'),
                'price' => is_numeric($package['price']) ? number_format(floatval($package['price']), 2, '.', '') : 0,
                'quantity' => intval($package['quantity']),
                'dates' => array_map(function ($date) {
                    return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
                }, $package['dates'] ?? [])
            ];
        }, $_POST['packages']);
        $_SESSION['packages'] = array_filter($_SESSION['packages']);
    } else {
        $_SESSION['packages'] = [];
        error_log("Ошибка: Неверный формат данных пакетов.");
    }

    // Сохранение суммы для оплаты
    if (!isset($_POST['total_price']) || !is_numeric($_POST['total_price']) || $_POST['total_price'] <= 0) {
        error_log("Ошибка: Неверная или отсутствующая сумма для оплаты.");
        exit("Ошибка: Неверная или отсутствующая сумма для оплаты.");
    }
    $_SESSION['total_price'] = number_format(floatval($_POST['total_price']), 2, '.', '');

    // Логирование информации о сессии после сохранения всех данных заказа
    error_log('Данные сессии после сохранения всех данных заказа: ' . print_r($_SESSION, true));
}

// Заголовок безопасности Content-Security-Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://www.google.com/recaptcha/; frame-src 'self' https://www.google.com/recaptcha/ https://checkout.stripe.com; connect-src 'self' https://api.stripe.com; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");

// Логирование текущего CSRF-токена
error_log('CSRF-токен для формы: ' . $csrfToken);

// Получение данных из базы данных
$packages = [];
$discounts = [];
try {
    $stmt = $pdo->query("SELECT * FROM price ORDER BY id ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM discounts ORDER BY id ASC");
    $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Ошибка при выполнении SQL-запроса: " . $e->getMessage());
}
?>
<!-- Подключаем Stripe.js для оплаты -->
<script src="https://js.stripe.com/v3/" nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>"></script>

<?php
$pageTitle = "Food Case Catering";
$metaDescription = "Sprawdź nasze ceny na smaczne i zdrowe posiłki!";
$metaKeywords = "restauracja, ceny, jedzenie, obiady, kolacja";
$metaAuthor = "Twoja Restauracja";

include($_SERVER['DOCUMENT_ROOT'] . '/includes/head.php');
include($_SERVER['DOCUMENT_ROOT'] . '/includes/header.php');

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);
?>

<html lang="pl">
<head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Catering - Zdrowe posiłki i dostawa na każdą okazję | FoodCase Catering | Kraków, Polska</title>
<!-- Meta Description -->
<meta name="description" content="FOODCASE - najlepszy catering dietetyczny w Krakowie. Oferujemy zdrowe całodzienne posiłki z dostawą do domu: śniadania, obiady, przekąski, kolacje, posiłki niskokaloryczne, wegańskie, wegetariańskie, sportowe i ketogeniczne. Sprawdź nasze menu i zamów już teraz catering dietetyczny w Krakowie. FoodCase Catering - najlepsze zdrowe posiłki na imprezy, spotkania, dostawy do domów w Krakowie i całej Polsce. Oferujemy szeroki wybór cateringu dietetycznego, lunch boxów, jedzenia na eventy, imprezy rodzinne, wesela, konferencje i firmowe wydarzenia. Zamów zdrowe i smaczne posiłki już dziś! Catering Całodzienny w Krakowie z dostawą na cały dzień. Śniadania, obiady, przekąski, kolacje, diety sportowe i niskokaloryczne dostarczane prosto do Twoich drzwi. Wybierz wygodę i jakość każdego dnia! Nasze menu jest starannie skomponowane, aby zapewnić Ci zdrowe, smaczne i zbilansowane posiłki każdego dnia.">

<!-- Meta Keywords -->
<meta name="keywords" content="catering dietetyczny Kraków, zdrowe jedzenie Kraków, dostawa posiłków Kraków, catering na wynos Kraków, dieta pudełkowa Kraków, zdrowe obiady Kraków, FOODCASE catering, dieta, posiłki do domu Kraków, śniadania na wynos, catering całodzienny, catering dietetyczny Polska, zdrowa dieta, jedzenie z dostawą, dieta pudełkowa z dostawą, posiłki do domu Kraków, catering, catering dietetyczny, dostawa jedzenia, zdrowe posiłki, Kraków catering, Polska catering, catering firmowy, catering na eventy, catering na imprezy, dostawa posiłków Kraków, lunch box Kraków, posiłki dietetyczne Kraków, catering weselny, catering konferencyjny, jedzenie na zamówienie, zdrowa żywność, dostawa dietetycznych posiłków, catering na spotkania, jedzenie z dostawą, fit catering, catering bezglutenowy, catering wegański, catering wegetariański, posiłki na wynos, catering niskokaloryczny, catering sportowy, diety sportowe, posiłki wysokobiałkowe, catering ketogeniczny, dieta ketogeniczna, diety odchudzające, posiłki na przyjęcia, catering eventowy, catering biznesowy, dieta catering, fit jedzenie, posiłki bez laktozy, catering na wynos, dostawa zdrowego jedzenia, catering na każdą okazję, dostawa cateringu Kraków, najlepsze jedzenie na wynos, catering dla firm Kraków, catering weselny Kraków, catering na imprezy rodzinne, catering na urodziny, dostawa jedzenia na eventy, zamówienie cateringu, catering dietetyczny na zamówienie, zdrowe jedzenie z dostawą, catering okolicznościowy, catering dla rodzin, catering na domówki, dostawa obiadów, catering imprezowy, jedzenie z dostawą na miejsce, jedzenie na wynos w Krakowie, catering dla sportowców, fit catering Kraków, zdrowe posiłki na zamówienie, catering na konferencje Kraków, catering na spotkania firmowe, catering na każdą okazję Kraków, zdrowe menu Kraków, catering na wesela, catering z dostawą na miejsce, zamów catering na imprezę, catering weselny w Polsce, zdrowe jedzenie na wynos, catering na specjalne wydarzenia, jedzenie na przyjęcia Kraków, catering na specjalne zamówienia, catering z dostawą, catering na chrzciny, catering dla dzieci, catering na komunię, catering na rodzinne spotkania, jedzenie z dowozem Kraków, dostawa jedzenia na przyjęcia, catering na każdą okazję, catering dietetyczny w Polsce, catering z dostawą do domu, catering niskokaloryczny, zdrowe obiady z dostawą, posiłki dla sportowców, posiłki białkowe, diety na masę, zdrowe odżywianie Kraków">

<!-- Meta Robots -->
<meta name="robots" content="index, follow">

<!-- Canonical Link -->
<link rel="canonical" href="https://foodcasecatering.net/index2/" />

<!-- Дополнительные метатеги -->
<meta name="language" content="pl">
<meta name="author" content="FoodCase Catering">
<meta name="geo.region" content="PL-MA">
<meta name="geo.placename" content="Kraków, Polska">
<meta name="geo.position" content="50.0646501;19.9449799">
<meta name="ICBM" content="50.0646501, 19.9449799">

<!-- Meta tags для социальных сетей (Open Graph и Twitter) -->
<meta property="og:title" content="Catering - Zdrowe posiłki i dostawa na każdą okazję | FoodCase Catering | Kraków, Polska">
<meta property="og:description" content="Najlepszy catering w Krakowie! Oferujemy zdrowe i smaczne posiłki na imprezy, spotkania firmowe i rodzinne. W naszej ofercie znajdziesz również posiłki sportowe, niskokaloryczne, ketogeniczne i inne diety. Zobacz nasze oferty i zamów już teraz!">
<meta property="og:type" content="website">
<meta property="og:url" content="https://foodcasecatering.net/index2/">
<meta property="og:image" content="https://foodcasecatering.net/uploads_img/logo.png">
<meta property="og:locale" content="pl_PL">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="FoodCase Catering - Catering Dietetyczny w Krakowie i Polsce">
<meta name="twitter:description" content="Zdrowe jedzenie z dostawą w Krakowie. Oferujemy diety sportowe, ketogeniczne i posiłki niskokaloryczne. Złóż zamówienie na nasze najlepsze dania już dziś. Catering dla imprez, spotkań i specjalnych okazji!">
<meta name="twitter:image" content="https://foodcasecatering.net/uploads_img/logo.png">

<!-- Meta tags для локальной поисковой оптимизации -->
<meta name="business:contact_data:locality" content="Kraków">
<meta name="business:contact_data:region" content="Małopolskie">
<meta name="business:contact_data:country_name" content="Polska">
<meta name="business:contact_data:postal_code" content="31-000">
<meta name="business:contact_data:email" content="info@foodcasecatering.net">
<meta name="business:contact_data:phone_number" content="+48 123 456 789">

<!-- Meta tags для Google My Business и карты -->
<meta name="gmb:business_name" content="FoodCase Catering">
<meta name="gmb:description" content="Catering i zdrowe posiłki w Krakowie. Złóż zamówienie na najlepsze jedzenie na wynos i dostawę do domu lub biura. W naszej ofercie znajdziesz również diety sportowe, ketogeniczne i niskokaloryczne.">
<meta name="gmb:address" content="ul. Warszawska 123, 31-000 Kraków, Polska">
<meta name="gmb:phone_number" content="+48 123 456 789">

<!-- Дополнительные метатеги для увеличения видимости в поисковых системах -->
<meta name="subject" content="Catering w Krakowie - Najlepsze Zdrowe Posiłki, Diety Sportowe i Ketogeniczne">
<meta name="coverage" content="Kraków, Polska, Małopolska">
<meta name="distribution" content="Global">
<meta name="rating" content="General">
<meta name="target" content="all">
<meta name="HandheldFriendly" content="true">
<meta name="MobileOptimized" content="320">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/uuid/8.3.2/uuid.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>-->
<link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>


<body>
<main class="page-main">
    <div class="container">
        <h1 class="page-title">Złóż zamówienie</h1>

      <div class="steps-line">
        <div class="steps-line__item active">
          <div class="steps-line__num">1</div>
          Wybierz kaloryczność
        </div>
        <div class="steps-line__item">
          <div class="steps-line__num">2</div>
          Podaj dane dostawy
        </div>
        <div class="steps-line__item">
          <div class="steps-line__num">3</div>
          Podsumowania zamówienia
        </div>
        <div class="steps-line__item">
          <div class="steps-line__num">4</div>
          Płatność
        </div>
      </div>
      <div class="calc-modal pay__modal modal" id="calc">
        <div class="calc-modal__inner">
          <div class="calc-modal__close" data-modal-close>
            <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M1 23.5L23.5 1M1 1L23.5 23.5" stroke="#232324" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>

          <svg class="icon" width="120" height="139" viewBox="0 0 120 139" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="60" cy="69" r="57.5" stroke="#FF0000" stroke-width="5"/>
            <path d="M64.5625 27.3636L63.7457 87.4773H54.2713L53.4545 27.3636H64.5625ZM59.0085 111.653C56.9938 111.653 55.265 110.932 53.8221 109.489C52.3791 108.046 51.6577 106.317 51.6577 104.303C51.6577 102.288 52.3791 100.559 53.8221 99.1161C55.265 97.6732 56.9938 96.9517 59.0085 96.9517C61.0232 96.9517 62.752 97.6732 64.195 99.1161C65.6379 100.559 66.3594 102.288 66.3594 104.303C66.3594 105.637 66.0191 106.862 65.3384 107.978C64.685 109.094 63.8002 109.993 62.6839 110.673C61.5949 111.327 60.3698 111.653 59.0085 111.653Z" fill="#FF1313"/>
          </svg>
            
          <div class="calc-modal__title">Wybierz datę dostawy dla wszystkich paczek</div>

        </div>
      </div>
      <form action="/payments/process_order.php" method="POST" class="page-grid" id="order-form">
         <!--Добавляем CSRF токен -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <!-- Скрытые поля, чтобы сохранять динамические значения для пакетов -->
            <input type="hidden" id="hidden-calories" name="hidden-calories">
            <input type="hidden" id="hidden-price" name="hidden-price">
            <input type="hidden" id="hidden-quantity" name="hidden-quantity">
            <input type="hidden" id="hidden-delivery-dates" name="hidden-delivery-dates">
             <!-- Скрытое поле для сохранения order_id -->
        <input type="hidden" id="hidden-order-id" name="hidden-order-id">
        <div class="page-grid__main">

          <div class="page-grid__tab active product-tab">
            <div class="pay-widget">
              <div class="pay-widget__main">
                <div class="pay-widget__row">
                  <div class="pay-widget__title">Wybierz kaloryczność:</div>

                  <div class="pay-widget__info">
                    Zamów na 20 dni lub więcej i uzyskaj rabat: 20+ dni — rabat 4%, 24+ dni — rabat 5%, 28+ dni — rabat 7%
                  </div>


                  
                  <div class="pay-widget__grid">

                  <?php foreach ($packages as $index => $package): 
                      $name = htmlspecialchars($package['name']);
                      $price = htmlspecialchars($package['price']);
                      $image = htmlspecialchars($package['image']);
                      $uniqueId = "package-" . $index; // Генерируем уникальный ID для каждого пакета
                      $discount1 = isset($discounts[0]) ? abs(intval($discounts[0]['discount_percent'])) : 4;
                      $discount2 = isset($discounts[1]) ? abs(intval($discounts[1]['discount_percent'])) : 5;
                      $discount3 = isset($discounts[2]) ? abs(intval($discounts[2]['discount_percent'])) : 7;
                  ?>
                 <!-- Добавляем атрибуты data-* для удобства использования JS -->
                    <div class="pay-item" 
                                id="<?php echo $uniqueId; ?>" 
                                data-calories="<?php echo $name; ?>" 
                                data-price="<?php echo $price; ?>" 
                                data-discount="<?php echo $discount1; ?>" 
                                data-discount2="<?php echo $discount2; ?>" 
                                data-discount3="<?php echo $discount3; ?>"
                            >
                      <div class="pay-item__photo">
                        <img src="/uploads_img<?php echo $image; ?>" alt="<?php echo $name; ?> kalorii">
                      </div>
                      <div class="pay-item__title"><?php echo $name; ?> kalori</div>
                      <div class="price pay-item__price">
                        <?php echo $price; ?> zł
                      </div>
                      <!-- Кнопка выбора пакета, будем добавлять классы в JS -->
                      <div class="btn pay-item__btn">Wybierać</div>
                    </div>
                  <?php endforeach; ?>
                
                </div>

                  
                </div>
                <!-- Раздел с выбранными пакетами -->
<div class="pay-widget__row hidden second">
  <div class="pay-widget__heading-row">
    <div class="pay-widget__heading">Wybrane pakiety:</div>
    <!-- Новая надпись на противоположной стороне -->
    <div class="pay-widget__note">Zamówienia na niedzielę zostaną dostarczone w sobotę</div>
  </div>
  
  <div class="pay-widget__inner">
    <div class="pay-full-card">
      <div class="pay-full-card__photo">
        <img src="../assets/img/pay/6.png" alt="">
      </div>
      
      <div class="pay-full-card__data">
        <div class="pay-full-card__title"></div>
        <div class="pay-full-card__list">
          <div class="pay-full-card__item qn">
            <b>Ilość opakowań:</b> 
            <div class="pay-full-card__qn">1</div>
          </div>
          <div class="pay-full-card__item">
            <b>Data:</b>
            <div class="pay-full-card__dates">nie wybrano</div>
          </div>
          <div class="pay-full-card__item cost">
            <b>Cena za pakiet:</b>
            <div class="price">
              <span></span>
              <div class="price__old"></div>
            </div>
          </div>
          <div class="pay-full-card__item full-cost">
            <b>Całkowita kwota:</b>
            <div class="price">
              <span></span>
              <div class="price__old"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="pay-widget__aside">
      <input type="hidden" class="pay-widget__calendar">
    </div>
  </div>
</div>

                <div class="pay-widget__remove">
                  Usuń kolejny pakiet
                  <span>
                    <svg width="12" height="15" viewBox="0 0 12 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M11.5584 5.31135L9.57664 4.5823L10.3975 2.82228C10.5487 2.49825 10.3754 2.1268 10.0106 1.99256L4.72575 0.0485014C4.36094 -0.0856999 3.94265 0.0681447 3.79154 0.392175L2.97064 2.1522L0.988868 1.42315C0.624013 1.28895 0.205769 1.4428 0.0546121 1.76683C-0.0964972 2.09086 0.0767303 2.4623 0.441585 2.59655L3.084 3.5686L7.69011 5.263H1.70985C1.31497 5.263 0.994827 5.54732 0.994827 5.89802V14.365C0.994827 14.7157 1.31497 15 1.70985 15H10.2902C10.6851 15 11.0052 14.7157 11.0052 14.365V6.48254L11.0112 6.48474C11.1007 6.51764 11.1933 6.53326 11.2845 6.53326C11.5651 6.53326 11.8314 6.38564 11.9454 6.14107C12.0965 5.81704 11.9233 5.44555 11.5584 5.31135ZM4.29187 2.6382L4.8391 1.46485L8.80265 2.92291L8.25541 4.09626L6.27364 3.36725L4.29187 2.6382Z" fill="white"/>
                    </svg>
                  </span>
                  
                </div>
              </div>
            </div>
            <div class="btn pay-widget__more">Dodaj kolejny pakiet</div>

            
          </div>
          <div class="page-grid__tab">
            <div class="btn pay-total__prev"> 
              <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.70679 0.292787C5.89426 0.480314 5.99957 0.734622 5.99957 0.999786C5.99957 1.26495 5.89426 1.51926 5.70679 1.70679L2.41379 4.99979L5.70679 8.29279C5.88894 8.48139 5.98974 8.73399 5.98746 8.99619C5.98518 9.25838 5.88001 9.5092 5.6946 9.6946C5.5092 9.88001 5.25838 9.98518 4.99619 9.98746C4.73399 9.98974 4.48139 9.88894 4.29279 9.70679L0.292787 5.70679C0.105316 5.51926 0 5.26495 0 4.99979C0 4.73462 0.105316 4.48031 0.292787 4.29279L4.29279 0.292787C4.48031 0.105316 4.73462 0 4.99979 0C5.26495 0 5.51926 0.105316 5.70679 0.292787V0.292787Z" fill="black"/>
              </svg>
              Zmień kolejność
            </div>
            <div class="delivery-widget">
              <div class="delivery-widget__row">
                <div class="delivery-widget__title">Dane kontaktowe:</div>

                <div class="delivery-widget__grid">
                      <div class="delivery-widget__field large">
                        <div class="delivery-widget__label">E-mail</div>
                        <input type="email" name="email" id="email" required class="delivery-widget__input email-input" placeholder="E-mail">
                      </div>

                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Numer telefonu</div>
                    <input type="text" name="phone" required class="delivery-widget__input phone-input" placeholder="Numer telefonu">
                  </div>

                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Pełne imię i nazwisko</div>
                            <input type="text" name="fullname" required class="delivery-widget__input fullname-input" placeholder="Pełne imię i nazwisko">
                          </div>
                        </div>
                      </div>

              <div class="delivery-widget__row">
                <div class="delivery-widget__title">Dane dostawy:</div>

                <div class="delivery-widget__grid">
                  

                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Ulica</div>
                    <input type="text" name="street" required class="delivery-widget__input street-input" placeholder="Ulica">
                  </div>
                  

                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Dom</div>
                    <input type="text" name="house_number" required class="delivery-widget__input house-number-input" placeholder="Dom">
                  </div>

                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Klatka</div>
                    <input type="text" name="klatka" class="delivery-widget__input klatka-input" placeholder="Klatka">
                  </div>
                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Piętro</div>
                    <input type="text" name="floor" required class="delivery-widget__input floor-input" placeholder="Piętro">
                  </div>

                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Mieszkanie</div>
                    <input type="text" name="apartment" required class="delivery-widget__input apartment-input" placeholder="Mieszkanie">
                  </div>
                  <div class="delivery-widget__field">
                    <div class="delivery-widget__label">Kod do klatki</div>
                    <input type="text" name="gate_code" required class="delivery-widget__input gate-code-input" placeholder="Kod do klatki">
                  </div>

                  <div class="delivery-widget__field large">
                    <div class="delivery-widget__label">Uwagi</div>
                    <input type="text" name="notes" class="delivery-widget__input notes-input large" placeholder="Uwagi">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="page-grid__tab">
            <div class="btn pay-total__prev"> 
              <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.70679 0.292787C5.89426 0.480314 5.99957 0.734622 5.99957 0.999786C5.99957 1.26495 5.89426 1.51926 5.70679 1.70679L2.41379 4.99979L5.70679 8.29279C5.88894 8.48139 5.98974 8.73399 5.98746 8.99619C5.98518 9.25838 5.88001 9.5092 5.6946 9.6946C5.5092 9.88001 5.25838 9.98518 4.99619 9.98746C4.73399 9.98974 4.48139 9.88894 4.29279 9.70679L0.292787 5.70679C0.105316 5.51926 0 5.26495 0 4.99979C0 4.73462 0.105316 4.48031 0.292787 4.29279L4.29279 0.292787C4.48031 0.105316 4.73462 0 4.99979 0C5.26495 0 5.51926 0.105316 5.70679 0.292787V0.292787Z" fill="black"/>
              </svg>
              Еdytować dane
            </div>
            <div class="total-info">

              <div class="total-info__row">
                <h3 class="total-info__heading">Informacje o zamówieniu</h3>
                <div class="total-info__grid">
                  
                </div>
              </div>


              <div class="total-info__row">
                <h3 class="total-info__heading">Dane dostawy</h3>
                <div class="total-info__data"></div>
              </div>
            </div>
          </div>
  
          
        </div>
        <div class="page-grid__aside">
          <div class="pay-total">
            <div class="pay-total__item packgs">
              <b>Liczba pakietów:</b>
              <span id="package-count">0 pakietów</span>
            </div>
            <div class="pay-total__item total">
              <b>Сałkowity:</b>
              <span id="total-without-discount">0.00zł</span>
            </div>
            <div class="pay-total__item discount">
              <b>Rabat:</b>
              <span id="discount-amount" style="color: red;font-weight: bold;">0.00zł</span>
            </div>
            <div class="pay-total__item sum">
              <b>Razem do zapłaty:</b>
              <span id="total-price" style="color: #006A23;font-weight: bold;">0.00zł</span>
            </div>
            <label for="accept" class="accept-terms">
              <input type="checkbox" id="accept">
              <span>
              Zapoznałem się z zasadami strony i <a href="/regulamin/" target="_blank">Regulamin</a>.
            </span>
            </label>
            <div class="btn pay-total__send active" disabled>Podać adres dostawy</div>
            <div class="btn pay-total__send" disabled>Podsumowania zamówienia</div>
            <button type="submit" id="pay-button" class="btn pay-total__send active">Płatność</button>
          </div>
        </div>
      </form>

    </div>
  </main>
  
  <div class="cookie-banner">
  <div class="cookie-content">
<p>Nasza strona korzysta z plików cookies w celu zapewnienia pomyślnej realizacji zamówień.<span>🍪</span><a href="/privacy_policy/" class="cookie-policy-link">Polityka prywatności</a><span>🍪</span> </p>
    <div class="cookie-buttons">
      <span>🍪</span>
      <button class="cookie-accept-button">Zgadzać się</button>
      <button class="cookie-decline-button">Odmawiać</button>
      <span>🍪</span>
    </div>
  </div>
  <div class="cookie-icons">
    
  </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>

<script>
// Определяем текущую версию сайта
const currentVersion = 'v1.3.0'; // Измените версию на новую при каждом обновлении

// Проверяем версию в localStorage
if (localStorage.getItem('version') !== currentVersion) {
    console.log('Старая версия найдена: ', localStorage.getItem('version')); // Отладочное сообщение
    console.log('Обновляем версию на: ', currentVersion);
    // Если версии не совпадают, обновляем версию и перезагружаем страницу
    localStorage.setItem('version', currentVersion);
    window.location.reload(true);
} else {
    console.log('Текущая версия сайта актуальна:', currentVersion);
}

document.addEventListener("DOMContentLoaded", function () {
    // Код для кнопок уведомления о куки
    const cookieBanner = document.querySelector('.cookie-banner');
    const acceptCookieButton = document.querySelector('.cookie-accept-button');
    const declineCookieButton = document.querySelector('.cookie-decline-button');

    if (acceptCookieButton) {
        acceptCookieButton.addEventListener('click', function () {
            // Скрываем баннер при согласии
            cookieBanner.style.display = 'none';
        });
    }

    if (declineCookieButton) {
        declineCookieButton.addEventListener('click', function () {
            // Перенаправляем на главную страницу при отказе
            window.location.href = '/';
        });
    }

    // Код для кнопки "Оплатить"
    const payButton = document.getElementById("pay-button");
    if (payButton && !payButton.hasAttribute("data-listener-added")) {
        payButton.setAttribute("data-listener-added", "true");
        payButton.addEventListener("click", async function (event) {
            event.preventDefault();
            const acceptTerms = document.getElementById("accept");
            if (!acceptTerms || !acceptTerms.checked) {
                alert("Musisz zaakceptować regulamin.");
                return;
            }
            toggleLoadingState(payButton, true);

            // Получаем данные заказа
            const requestData = getOrderDetails();
            if (!requestData) {
                toggleLoadingState(payButton, false);
                return;
            }
            try {
                const response = await postData('/payments/process_order.php', requestData);

                if (response.status === 'success') {
                    // Обновляем CSRF-токен новым значением, полученным с сервера
                    const newCsrfToken = response.new_csrf_token;
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', newCsrfToken);
                    document.getElementById("hidden-order-id").value = response.order_id;
                    await redirectToPaymentSummary(newCsrfToken, requestData, response.order_id, payButton);
                } else {
                    throw new Error(response.message);
                }
            } catch (error) {
                alert('Произошла ошибка: ' + error.message);
                toggleLoadingState(payButton, false);
            }
        });
    }
});

function getFormData() {
    const formData = {
        email: document.getElementById("email").value.trim(),
        phone: document.querySelector(".phone-input").value.trim(),
        fullname: document.querySelector(".fullname-input").value.trim(),
        street: document.querySelector(".street-input").value.trim(),
        house_number: document.querySelector(".house-number-input").value.trim(),
        klatka: document.querySelector(".klatka-input").value.trim(),
        floor: document.querySelector(".floor-input").value.trim(),
        apartment: document.querySelector(".apartment-input").value.trim(),
        gate_code: document.querySelector(".gate-code-input").value.trim(),
        notes: document.querySelector(".notes-input").value.trim()
    };
    return formData;
}

function validateFormData({ email, phone, fullname, street, house_number, floor, apartment, gate_code }) {
    const isValid = email && phone && fullname && street && house_number && floor && apartment && gate_code;
    return isValid;
}

function getOrderDetails() {
    const formData = getFormData();
    if (!validateFormData(formData)) {
        alert("Proszę wypełnić wszystkie wymagane поля dostawy.");
        toggleLoadingState(payButton, false);
        return null; // Возвращаем null, если форма не валидна
    }
    const packageCards = document.querySelectorAll(".total-info__grid .pay-full-card");
    const packageDetails = [];

    packageCards.forEach(card => {
        // Извлечение данных из атрибутов data-* и элементов пакета
        const calories = card.querySelector(".pay-full-card__title").textContent.trim();
        const quantityText = card.querySelector(".pay-full-card__item.qn").textContent.trim();
        const quantity = parseInt(quantityText.replace(/(?:\spakiet[y|ów]?)$/, ''), 10);
        const datesElements = card.querySelectorAll(".pay-full-card__dates"); // Извлекаем элементы с датами доставки
        const pricePerPackage = parseFloat(card.getAttribute("data-price"));
        const totalCost = parseFloat(card.querySelector(".pay-full-card__item.full-cost .price span").textContent.trim().replace('zł', '').replace(',', '.'));

        // Извлекаем все даты из элементов .pay-full-card__dates
        const dates = Array.from(datesElements).map(dateElement => dateElement.textContent.trim()).filter(date => date);

console.log('Извлеченные даты:', dates);

        // Проверяем корректность данных
        if (calories && quantity && dates.length > 0 && pricePerPackage && totalCost) {
            const packageData = {
                calories,
                quantity: quantity,
                dates: dates, // Сохраняем массив дат
                price_per_package: pricePerPackage,
                total_cost: totalCost
            };
            packageDetails.push(packageData);
        }
    });

    if (packageDetails.length === 0) {
        alert("Nie wybrano żadnych pakietów. Proszę spróbować ponownie.");
        toggleLoadingState(payButton, false);
        return null; // Возвращаем null, если пакеты не выбраны
    }

    // Извлекаем общую сумму без скидки
    const totalElement = document.querySelector('.pay-total__item.total span');
    if (!totalElement) {
        console.error("Ошибка: не удалось найти элемент с общей суммой.");
        toggleLoadingState(payButton, false);
        return null;
    }
    const totalWithoutDiscount = parseFloat(totalElement.textContent.trim().replace('zł', '').replace(',', '.'));

    // Извлекаем сумму скидки
    const discountElement = document.querySelector('.pay-total__item.discount span');
    let discount = 0; // По умолчанию скидка равна 0
    if (discountElement) {
        discount = parseFloat(discountElement.textContent.trim().replace('zł', '').replace(',', '.'));
    }

    // Извлекаем итоговую сумму со скидкой
    const totalSumElement = document.querySelector('.pay-total__item.sum span');
    if (!totalSumElement) {
        console.error("Ошибка: не удалось найти элемент с итоговой суммой.");
        toggleLoadingState(payButton, false);
        return null;
    }
    const totalWithDiscount = parseFloat(totalSumElement.textContent.trim().replace('zł', '').replace(',', '.'));

    // Формируем данные для отправки
    const requestData = {
        ...formData,
        packages: packageDetails,
        total_without_discount: totalWithoutDiscount,
        discount: discount,
        total_price: totalWithDiscount,
        csrf_token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    };

    return requestData; // Возвращаем данные для отправки
}

function toggleLoadingState(button, isLoading) {
    if (isLoading) {
        button.classList.add("loading");
        button.disabled = true;
    } else {
        button.classList.remove("loading");
        button.disabled = false;
    }
}

async function postData(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-PHPSESSID': document.cookie.match(/PHPSESSID=[^;]+/)[0].split('=')[1]
        },
        body: JSON.stringify(data)
    });
    if (!response.ok) {
        throw new Error(`Ошибка при отправке данных: ${response.statusText}`);
    }
    const responseData = await response.json();
    return responseData;
}

async function redirectToPaymentSummary(csrfToken, requestData, orderId, payButton) {
    try {
        const response = await postData('/payments/payment_summary.php', {
            ...requestData,
            csrf_token: csrfToken,
            order_id: orderId
        });

        if (response.id) {
            const stripe = Stripe('<?= htmlspecialchars($stripePublishableKey, ENT_QUOTES, 'UTF-8') ?>');
            stripe.redirectToCheckout({ sessionId: response.id });
        } else {
            throw new Error('Ошибка получения идентификатора сессии');
        }
    } catch (error) {
        alert('Произошла ошибка: ' + error.message);
        toggleLoadingState(payButton, false);
    }
}
</script>







<!-- Добавление structured data (schema) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FoodEstablishment",
  "name": "FoodCase Catering",
  "description": "FoodCase Catering - najlepsze zdrowe posiłki na imprezy, spotkania i dostawy do domów w Krakowie i całej Polsce.",
  "image": "https://foodcasecatering.net/assets/img/logo-w.png",
  "address": {
    "@type": "PostalAddress",
    // "streetAddress": "ul. Warszawska 123",
    "addressLocality": "Kraków",
    "postalCode": "31-000",
    "addressCountry": "PL"
  },
  "telephone": "+48 123 456 789",
  "servesCuisine": "Zdrowe posiłki, Catering dietetyczny",
  "url": "https://foodcasecatering.net",
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 50.0646501,
    "longitude": 19.9449799
  },
  "priceRange": "$$"
}
</script>







<style>
/* Контейнер для заголовка и заметки */
.pay-widget__heading-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

/* Стили для заметки */
.pay-widget__note {
  font-weight: bold;  /* Сделать текст жирным */
  color: #333;        /* Цвет текста для лучшей видимости */
  font-size: 14px;    /* Размер шрифта */
}
/* Основной стиль баннера */
.cookie-banner {
  position: fixed;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 90%;
  max-width: 800px;
  background-color: #f7f7f7; /* Светло-серый для соответствия чистому стилю */
  border-radius: 15px 15px 0 0;
  border: 1px solid #e0e0e0; /* Легкая граница для четкости */
  box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.1);
  padding: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  z-index: 1000;
  animation: slide-up 0.5s ease-in-out;
}

/* Анимация появления баннера */
@keyframes slide-up {
  from {
    transform: translate(-50%, 100%);
  }
  to {
    transform: translateX(-50%);
  }
}

/* Контент внутри баннера */
.cookie-content p {
  color: #333; /* Тёмный оттенок для читаемости */
  font-family: 'Poppins', Arial, sans-serif;
  font-size: 16px;
  margin-bottom: 15px;
  line-height: 1.6;
}

/* Ссылка на политику */
.cookie-policy-link {
  color: #007bff; /* Синий цвет для соответствия стилю сайта */
  font-weight: bold;
  text-decoration: none;
}

.cookie-policy-link:hover {
  text-decoration: underline;
  color: #0056b3; /* Темный оттенок синего для ховера */
}

/* Кнопки */
.cookie-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
}

.cookie-accept-button,
.cookie-decline-button {
  padding: 10px 20px;
  font-size: 16px;
  font-family: 'Poppins', Arial, sans-serif;
  font-weight: bold;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease-in-out;
}

.cookie-accept-button {
  background-color: #007bff; /* Синий цвет в стиле основной кнопки */
  color: white;
}

.cookie-accept-button:hover {
  background-color: #0056b3; /* Более темный синий для ховера */
}

.cookie-decline-button {
  background-color: #6c757d; /* Серый для кнопки отклонения */
  color: white;
}

.cookie-decline-button:hover {
  background-color: #5a6268; /* Темнее для ховера */
}


    /* Класс для кнопки с эффектом загрузки */
#pay-button.loading {
    pointer-events: none;
    opacity: 0.8;
    color: transparent; /* Скрываем текст, чтобы был виден только спиннер */
    background-color: rgba(0, 0, 0, 0.1); /* Добавляем легкий полупрозрачный фон */
    position: relative;
}

#pay-button.loading::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 24px;
    height: 24px;
    margin: -12px 0 0 -12px;
    border: 3px solid #ffffff; /* Белый цвет для спиннера */
    border-top-color: #0056D2; /* Синий цвет для верхней границы, под цвет кнопки */
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    z-index: 10;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

</body>
</html>

