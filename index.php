<?php
$nonce = base64_encode(random_bytes(16));
// Подключаем базу данных и логирование ошибок
include($_SERVER['DOCUMENT_ROOT'] . '/config/db.php');
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');

// Вставьте это в самом начале index.php и prices/index.php (или в любом другом файле, где нужны отзывы)
if (strpos($_SERVER['REQUEST_URI'], 'feedback') !== false) {
    include 'includes/feedback.php';
}

// Старт сессии для защиты от CSRF
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Подключение autoload для использования Dotenv и других функций
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Загружаем переменные окружения из файла .env
$dotenv = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->load();

// Функция для получения IP-адреса клиента
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]); // Берем первый IP-адрес из списка, так как их может быть несколько
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    } else {
        return null; // Возвращаем null, если IP не удалось получить
    }
}

// Обновленная функция для получения информации о геопозиции
function getGeoInfo($ip) {
    $url = "http://ip-api.com/json/$ip";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Устанавливаем таймаут запроса в 2 секунды
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("Ошибка: Не удалось получить данные о геопозиции для IP: $ip. Ошибка cURL: " . curl_error($ch));
        curl_close($ch);
        return ['country' => 'Неизвестно', 'city' => 'Неизвестно'];
    }

    curl_close($ch);

    $geoData = json_decode($response, true);
    if ($geoData['status'] !== 'success') {
        error_log("Ошибка API: " . $geoData['message']);
        return ['country' => 'Неизвестно', 'city' => 'Неизвестно'];
    }

    return [
        'country' => $geoData['country'] ?? 'Неизвестно',
        'city' => $geoData['city'] ?? 'Неизвестно',
    ];
}

// Функция для записи посещения в базу данных
function recordPageVisit($pdo, $ip, $geoInfo) {
    try {
        $country = $geoInfo['country'];
        $city = $geoInfo['city'];

        $stmt = $pdo->prepare("INSERT INTO page_visits (visit_date, ip_address, country, city) VALUES (NOW(), ?, ?, ?)");
        $stmt->execute([$ip, $country, $city]);
    } catch (Exception $e) {
        error_log("Ошибка записи данных о посещении: " . $e->getMessage());
    }
}

// Получаем IP пользователя
$ip = getUserIP();

// Проверяем, что IP корректный и записываем посещение в базу данных
if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
    $geoInfo = getGeoInfo($ip);
    recordPageVisit($pdo, $ip, $geoInfo);
} else {
    error_log("Некорректный IP-адрес: $ip");
}

// Установка текущей даты как начальной даты
$startDate = new DateTime(); // Текущая дата

// Генерация дат для каждой недели, начиная с текущей даты
$weekDates = [];
for ($i = 0; $i < 4; $i++) {
    $weekStart = (clone $startDate)->modify('this week')->modify("+$i week");
    $weekEnd = (clone $weekStart)->modify('+6 days');
    $weekDates[] = [
        'start' => $weekStart->format('d.m.Y'),
        'end' => $weekEnd->format('d.m.Y'),
    ];
}

// Генерация дат для каждого дня недели, начиная с текущей даты
$days = [];
for ($week = 0; $week < 4; $week++) {
    $weekStartDate = new DateTime($weekDates[$week]['start']);
    for ($day = 0; $day < 7; $day++) { // Убираем воскресенье, только 6 дней
        $days[$week][$day] = (clone $weekStartDate)->modify("+$day day")->format('d.m.Y');
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Логирование неудачной проверки CSRF
        error_log('[WARNING] CSRF токен отсутствует или не совпадает.');
        exit('Неверный CSRF токен. Операция отклонена.');
    }

    // Получаем входные данные
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    // Проверяем, содержит ли данные сообщение об ошибке
    if (isset($data['message'])) {
        $errorMessage = $data['message'];

        // Если есть информация об ошибке, добавляем её к сообщению
        if (isset($data['error'])) {
            $errorMessage .= ' | Details: ' . $data['error'];
        }

        // Добавляем дополнительные данные, такие как user-agent и IP-адрес для лучшего анализа
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Неизвестный User-Agent';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Неизвестный IP';
        $errorMessage .= ' | User-Agent: ' . $userAgent . ' | IP: ' . $ipAddress;

        // Форматируем строку для лога с текущей датой и временем
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $errorMessage . PHP_EOL;

        // Записываем сообщение в лог-файл
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    } else {
        // Логируем ошибку, если сообщение отсутствует
        $logEntry = date('Y-m-d H:i:s') . ' - Некорректный запрос: отсутствует сообщение об ошибке.' . PHP_EOL;
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
} else {
    // Логируем ошибку, если метод запроса не POST
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Неизвестный User-Agent';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Неизвестный IP';
    $logEntry = date('Y-m-d H:i:s') . ' - Некорректный метод запроса: ожидается POST. | User-Agent: ' . $userAgent . ' | IP: ' . $ipAddress . PHP_EOL;
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log';
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
<?php
header("Content-Security-Policy: script-src 'self' https://www.google.com https://www.gstatic.com 'unsafe-inline';");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
  <?php
    $pageTitle = "Catering Całodzienny w Krakowie - Śniadania, Obiady i Kolacje | FOODCASE";
    $metaDescription = "Catering całodzienny w Krakowie - zamów śniadania, obiady i kolacje z dostawą na cały dzień od FOODCASE.";
    $metaKeywords = "catering całodzienny, Kraków, dostawa jedzenia, śniadania, obiady, kolacje, FOODCASE";
    header("Content-Security-Policy: script-src 'self' https://www.google.com https://www.gstatic.com 'unsafe-inline';");
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
  ?>
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
<meta property="og:image" content="https://foodcasecatering.net/uploads_img/logo.png?v=1.3">
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
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '1546132289627600');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=1546132289627600&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
</head>

<body>
    
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; ?> <!-- Исправленный путь к хедеру -->
    <!-- promo -->
  <div class="promo">
    <div class="container promo__container">
      <div class="promo__inner">
        <h1 class="promo__title">
          Dostawa gotowych posiłków
          <!-- <span class="eco">ECO</span> -->
        </h1>
        <div class="promo__text">
          <p>Wygodne posiłki na twój aktywny dzień.</p>
          <p class="accent">
            
            <span>
              <svg width="39" height="39" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                <rect width="39" height="39" fill="url(#pattern0_193_1022)"/>
                <defs>
                <pattern id="pattern0_193_1022" patternContentUnits="objectBoundingBox" width="1" height="1">
                <use xlink:href="#image0_193_1022" transform="scale(0.0078125)"/>
                </pattern>
                <image id="image0_193_1022" width="128" height="128" xlink:href="data:image/png;base64,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"/>
                </defs>
              </svg>
            </span>
            Nasze opakowanie nie szkodzi nature.
          </p>
        </div>
        <a href="#menu" class="btn promo__btn js-scroll-link">Zobacz menu</a>
      </div>
      <img src="../assets/img/promo/1.png" alt="">

      <!-- <picture>
        <source type="image/webp" srcset="../assets/img/promo/1.webp">
        <img src="../assets/img/promo/1.png" width="685" height="551" alt="">
      </picture> -->
      
    </div>
  </div>
  <!-- end promo -->


  <!-- section -->
  <section class="section is-white">
    <div class="container section__container">
      <!-- advants -->
      <div class="advants">
        <div class="advants__item">
          <div class="advants__icon">
            <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M34.4099 20.7358L33.4517 16.9032C33.7136 16.8475 33.9102 16.6151 33.9102 16.3367V15.7187C33.9102 14.377 32.8186 13.2855 31.477 13.2855H27.1128V12.011C27.1128 11.3508 26.5757 10.8137 25.9155 10.8137H17.8005L22.7319 8.84113C23.029 8.72232 23.1734 8.38513 23.0546 8.08805C22.9358 7.79103 22.5988 7.64643 22.3015 7.76531L18.2638 9.38037C19.4942 8.59094 20.7115 7.72209 21.1749 7.11448C22.0771 5.93157 21.8486 4.2352 20.6657 3.33297C19.4828 2.43066 17.7864 2.65924 16.8842 3.84215C16.225 4.70659 15.3552 7.13551 14.7923 8.84712C14.2293 7.13566 13.3595 4.70659 12.7003 3.84215C11.7982 2.65924 10.1017 2.4308 8.91881 3.33297C7.73589 4.23513 7.50746 5.93149 8.40962 7.11448C8.87306 7.72216 10.0903 8.59094 11.3208 9.38037L7.283 7.76531C6.98577 7.64629 6.64873 7.79096 6.52992 8.08805C6.41104 8.38513 6.55558 8.72232 6.85266 8.84113L11.7841 10.8137H3.66907C3.00885 10.8137 2.47177 11.3508 2.47177 12.011V22.5162C2.47177 22.8362 2.73113 23.0956 3.05113 23.0956C3.37105 23.0956 3.63048 22.8362 3.63048 22.5162V12.011C3.63048 11.9897 3.64775 11.9724 3.66907 11.9724H25.9154C25.9367 11.9724 25.954 11.9897 25.954 12.011V22.5164C25.954 22.8363 26.2133 23.0957 26.5333 23.0957C26.8533 23.0957 27.1127 22.8364 27.1127 22.5164V21.8596H33.9466C33.9472 21.8596 33.9477 21.8598 33.9484 21.8598C33.949 21.8598 33.9496 21.8597 33.9502 21.8597C34.7911 21.8604 35.5051 22.4123 35.7502 23.1728H33.9488C33.6289 23.1728 33.3694 23.4322 33.3694 23.7522V24.9881C33.3694 25.989 34.1837 26.8033 35.1847 26.8033H35.8413V29.3524H34.3265C33.8288 27.9156 32.4629 26.8805 30.859 26.8805C29.2551 26.8805 27.8891 27.9155 27.3916 29.3524H27.1126V24.988C27.1126 24.6681 26.8533 24.4087 26.5333 24.4087C26.2133 24.4087 25.9539 24.668 25.9539 24.988V29.3523H13.934C13.4364 27.9155 12.0705 26.8804 10.4666 26.8804C8.86266 26.8804 7.49669 27.9154 6.99914 29.3523H3.66907C3.64775 29.3523 3.63048 29.335 3.63048 29.3137V28.0391H6.14092C6.46083 28.0391 6.72027 27.7798 6.72027 27.4598C6.72027 27.1398 6.46091 26.8804 6.14092 26.8804H0.579353C0.259434 26.8804 0 27.1398 0 27.4598C0 27.7798 0.259361 28.0391 0.579353 28.0391H2.47185V29.3137C2.47185 29.9739 3.00892 30.511 3.66914 30.511H6.79853C6.79839 30.5238 6.79752 30.5366 6.79752 30.5496C6.79752 32.5727 8.44351 34.2186 10.4666 34.2186C12.4897 34.2186 14.1357 32.5727 14.1357 30.5496C14.1357 30.5366 14.1348 30.5238 14.1346 30.511H27.1909C27.1907 30.5238 27.1899 30.5366 27.1899 30.5496C27.1899 32.5727 28.8359 34.2186 30.8589 34.2186C32.882 34.2186 34.528 32.5727 34.528 30.5496C34.528 30.5366 34.5271 30.5238 34.527 30.511H36.4206C36.7405 30.511 36.9999 30.2516 36.9999 29.9316V23.7521C37 22.2263 35.8743 20.9586 34.4099 20.7358ZM12.4947 8.74841C10.2095 7.32832 9.53162 6.67496 9.33094 6.41177C8.81619 5.73688 8.94656 4.76903 9.62152 4.25428C9.89974 4.04211 10.2277 3.93957 10.5532 3.93957C11.0173 3.93957 11.4765 4.14812 11.7791 4.54486C12.3395 5.27966 13.2172 7.75281 13.7966 9.53495C13.4056 9.30471 12.9561 9.03523 12.4947 8.74841ZM15.7881 9.53488C16.3675 7.75281 17.2452 5.27973 17.8055 4.54479C18.3203 3.8699 19.2881 3.73961 19.963 4.25428C20.638 4.76903 20.7683 5.73688 20.2536 6.41177C20.0529 6.67489 19.3751 7.32832 17.09 8.74834C16.6287 9.03509 16.1792 9.30464 15.7881 9.53488ZM27.1128 14.4441H31.477C32.1798 14.4441 32.7516 15.0159 32.7516 15.7186V15.7572H27.1128V14.4441ZM27.1128 20.7009V16.9159H32.2606L33.2068 20.7009H27.1128ZM10.4666 33.06C9.08234 33.06 7.95616 31.9338 7.95616 30.5496C7.95616 29.1653 9.08234 28.0391 10.4666 28.0391C11.8508 28.0391 12.977 29.1653 12.977 30.5496C12.977 31.9338 11.8508 33.06 10.4666 33.06ZM30.8591 33.06C29.4748 33.06 28.3486 31.9338 28.3486 30.5496C28.3486 29.1653 29.4748 28.0391 30.8591 28.0391C32.2433 28.0391 33.3695 29.1653 33.3695 30.5496C33.3695 31.9338 32.2433 33.06 30.8591 33.06ZM35.8414 25.6445H35.1848C34.8227 25.6445 34.5282 25.35 34.5282 24.9879V24.3313H35.8413V25.6445H35.8414Z" fill="white"/>
              <path d="M10.4668 29.3523C9.80661 29.3523 9.26953 29.8893 9.26953 30.5496C9.26953 31.2098 9.80661 31.7469 10.4668 31.7469C11.127 31.7469 11.6641 31.2098 11.6641 30.5496C11.6641 29.8893 11.127 29.3523 10.4668 29.3523Z" fill="white"/>
              <path d="M30.8589 29.3523C30.1987 29.3523 29.6616 29.8893 29.6616 30.5496C29.6616 31.2098 30.1987 31.7469 30.8589 31.7469C31.5191 31.7469 32.0562 31.2098 32.0562 30.5496C32.0562 29.8893 31.5191 29.3523 30.8589 29.3523Z" fill="white"/>
              <path d="M24.0618 26.8804H15.4104C15.0905 26.8804 14.8311 27.1398 14.8311 27.4598C14.8311 27.7798 15.0904 28.0391 15.4104 28.0391H24.0618C24.3817 28.0391 24.6411 27.7798 24.6411 27.4598C24.6411 27.1398 24.3818 26.8804 24.0618 26.8804Z" fill="white"/>
              <path d="M9.23066 24.4087H1.81519C1.49527 24.4087 1.23584 24.668 1.23584 24.988C1.23584 25.308 1.4952 25.5674 1.81519 25.5674H9.23066C9.55058 25.5674 9.81001 25.308 9.81001 24.988C9.81001 24.668 9.55058 24.4087 9.23066 24.4087Z" fill="white"/>
              <path d="M9.23089 15.7572H6.75904C6.43912 15.7572 6.17969 16.0166 6.17969 16.3366V21.2802C6.17969 21.6001 6.43905 21.8596 6.75904 21.8596C7.07903 21.8596 7.33839 21.6002 7.33839 21.2802V19.3877H8.61294C8.93286 19.3877 9.1923 19.1284 9.1923 18.8084C9.1923 18.4884 8.93294 18.229 8.61294 18.229H7.33839V16.9159H9.23089C9.55081 16.9159 9.81024 16.6566 9.81024 16.3366C9.81017 16.0167 9.55081 15.7572 9.23089 15.7572Z" fill="white"/>
              <path d="M13.7901 19.6594C14.3698 19.2796 14.7539 18.6247 14.7539 17.8815C14.7539 16.7101 13.801 15.7572 12.6296 15.7572H11.0847C10.7648 15.7572 10.5054 16.0166 10.5054 16.3366V21.2802C10.5054 21.6001 10.7647 21.8596 11.0847 21.8596C11.4047 21.8596 11.6641 21.6002 11.6641 21.2802V20.0057H12.6285L13.6924 21.6015C13.8041 21.7689 13.9877 21.8596 14.1749 21.8596C14.2854 21.8596 14.397 21.828 14.4957 21.7621C14.762 21.5847 14.8339 21.225 14.6564 20.9588L13.7901 19.6594ZM12.6296 18.847H11.6641V16.9159H12.6296C13.1621 16.9159 13.5952 17.349 13.5952 17.8815C13.5952 18.4139 13.1621 18.847 12.6296 18.847Z" fill="white"/>
              <path d="M18.4999 20.7009H16.6074V19.3858C16.6202 19.3867 16.633 19.3878 16.646 19.3878H17.8819C18.2018 19.3878 18.4613 19.1284 18.4613 18.8084C18.4613 18.4884 18.2019 18.2291 17.8819 18.2291H16.646C16.6329 18.2291 16.6202 18.2302 16.6074 18.231V16.9159H18.4999C18.8198 16.9159 19.0793 16.6566 19.0793 16.3366C19.0793 16.0167 18.8199 15.7572 18.4999 15.7572H16.0281C15.7082 15.7572 15.4487 16.0166 15.4487 16.3366V21.2802C15.4487 21.6001 15.7081 21.8596 16.0281 21.8596H18.4999C18.8198 21.8596 19.0793 21.6002 19.0793 21.2802C19.0793 20.9602 18.8198 20.7009 18.4999 20.7009Z" fill="white"/>
              <path d="M22.8256 20.7009H20.9331V19.3878H21.5897C21.9096 19.3878 22.1691 19.1284 22.1691 18.8084C22.1691 18.4884 21.9097 18.2291 21.5897 18.2291H20.9331V16.9159H22.8256C23.1455 16.9159 23.405 16.6566 23.405 16.3366C23.405 16.0167 23.1456 15.7572 22.8256 15.7572H20.3538C20.0338 15.7572 19.7744 16.0166 19.7744 16.3366V21.2802C19.7744 21.6001 20.0338 21.8596 20.3538 21.8596H22.8256C23.1455 21.8596 23.405 21.6002 23.405 21.2802C23.405 20.9602 23.1455 20.7009 22.8256 20.7009Z" fill="white"/>
            </svg>
          </div>
          <div class="advants__text">Bezpłatna dostawa na terenie miasta Kraków</div>
        </div>
        <div class="advants__item">
          <div class="advants__icon">
            <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M34.4099 20.7358L33.4517 16.9032C33.7136 16.8475 33.9102 16.6151 33.9102 16.3367V15.7187C33.9102 14.377 32.8186 13.2855 31.477 13.2855H27.1128V12.011C27.1128 11.3508 26.5757 10.8137 25.9155 10.8137H17.8005L22.7319 8.84113C23.029 8.72232 23.1734 8.38513 23.0546 8.08805C22.9358 7.79103 22.5988 7.64643 22.3015 7.76531L18.2638 9.38037C19.4942 8.59094 20.7115 7.72209 21.1749 7.11448C22.0771 5.93157 21.8486 4.2352 20.6657 3.33297C19.4828 2.43066 17.7864 2.65924 16.8842 3.84215C16.225 4.70659 15.3552 7.13551 14.7923 8.84712C14.2293 7.13566 13.3595 4.70659 12.7003 3.84215C11.7982 2.65924 10.1017 2.4308 8.91881 3.33297C7.73589 4.23513 7.50746 5.93149 8.40962 7.11448C8.87306 7.72216 10.0903 8.59094 11.3208 9.38037L7.283 7.76531C6.98577 7.64629 6.64873 7.79096 6.52992 8.08805C6.41104 8.38513 6.55558 8.72232 6.85266 8.84113L11.7841 10.8137H3.66907C3.00885 10.8137 2.47177 11.3508 2.47177 12.011V22.5162C2.47177 22.8362 2.73113 23.0956 3.05113 23.0956C3.37105 23.0956 3.63048 22.8362 3.63048 22.5162V12.011C3.63048 11.9897 3.64775 11.9724 3.66907 11.9724H25.9154C25.9367 11.9724 25.954 11.9897 25.954 12.011V22.5164C25.954 22.8363 26.2133 23.0957 26.5333 23.0957C26.8533 23.0957 27.1127 22.8364 27.1127 22.5164V21.8596H33.9466C33.9472 21.8596 33.9477 21.8598 33.9484 21.8598C33.949 21.8598 33.9496 21.8597 33.9502 21.8597C34.7911 21.8604 35.5051 22.4123 35.7502 23.1728H33.9488C33.6289 23.1728 33.3694 23.4322 33.3694 23.7522V24.9881C33.3694 25.989 34.1837 26.8033 35.1847 26.8033H35.8413V29.3524H34.3265C33.8288 27.9156 32.4629 26.8805 30.859 26.8805C29.2551 26.8805 27.8891 27.9155 27.3916 29.3524H27.1126V24.988C27.1126 24.6681 26.8533 24.4087 26.5333 24.4087C26.2133 24.4087 25.9539 24.668 25.9539 24.988V29.3523H13.934C13.4364 27.9155 12.0705 26.8804 10.4666 26.8804C8.86266 26.8804 7.49669 27.9154 6.99914 29.3523H3.66907C3.64775 29.3523 3.63048 29.335 3.63048 29.3137V28.0391H6.14092C6.46083 28.0391 6.72027 27.7798 6.72027 27.4598C6.72027 27.1398 6.46091 26.8804 6.14092 26.8804H0.579353C0.259434 26.8804 0 27.1398 0 27.4598C0 27.7798 0.259361 28.0391 0.579353 28.0391H2.47185V29.3137C2.47185 29.9739 3.00892 30.511 3.66914 30.511H6.79853C6.79839 30.5238 6.79752 30.5366 6.79752 30.5496C6.79752 32.5727 8.44351 34.2186 10.4666 34.2186C12.4897 34.2186 14.1357 32.5727 14.1357 30.5496C14.1357 30.5366 14.1348 30.5238 14.1346 30.511H27.1909C27.1907 30.5238 27.1899 30.5366 27.1899 30.5496C27.1899 32.5727 28.8359 34.2186 30.8589 34.2186C32.882 34.2186 34.528 32.5727 34.528 30.5496C34.528 30.5366 34.5271 30.5238 34.527 30.511H36.4206C36.7405 30.511 36.9999 30.2516 36.9999 29.9316V23.7521C37 22.2263 35.8743 20.9586 34.4099 20.7358ZM12.4947 8.74841C10.2095 7.32832 9.53162 6.67496 9.33094 6.41177C8.81619 5.73688 8.94656 4.76903 9.62152 4.25428C9.89974 4.04211 10.2277 3.93957 10.5532 3.93957C11.0173 3.93957 11.4765 4.14812 11.7791 4.54486C12.3395 5.27966 13.2172 7.75281 13.7966 9.53495C13.4056 9.30471 12.9561 9.03523 12.4947 8.74841ZM15.7881 9.53488C16.3675 7.75281 17.2452 5.27973 17.8055 4.54479C18.3203 3.8699 19.2881 3.73961 19.963 4.25428C20.638 4.76903 20.7683 5.73688 20.2536 6.41177C20.0529 6.67489 19.3751 7.32832 17.09 8.74834C16.6287 9.03509 16.1792 9.30464 15.7881 9.53488ZM27.1128 14.4441H31.477C32.1798 14.4441 32.7516 15.0159 32.7516 15.7186V15.7572H27.1128V14.4441ZM27.1128 20.7009V16.9159H32.2606L33.2068 20.7009H27.1128ZM10.4666 33.06C9.08234 33.06 7.95616 31.9338 7.95616 30.5496C7.95616 29.1653 9.08234 28.0391 10.4666 28.0391C11.8508 28.0391 12.977 29.1653 12.977 30.5496C12.977 31.9338 11.8508 33.06 10.4666 33.06ZM30.8591 33.06C29.4748 33.06 28.3486 31.9338 28.3486 30.5496C28.3486 29.1653 29.4748 28.0391 30.8591 28.0391C32.2433 28.0391 33.3695 29.1653 33.3695 30.5496C33.3695 31.9338 32.2433 33.06 30.8591 33.06ZM35.8414 25.6445H35.1848C34.8227 25.6445 34.5282 25.35 34.5282 24.9879V24.3313H35.8413V25.6445H35.8414Z" fill="white"/>
              <path d="M10.4668 29.3523C9.80661 29.3523 9.26953 29.8893 9.26953 30.5496C9.26953 31.2098 9.80661 31.7469 10.4668 31.7469C11.127 31.7469 11.6641 31.2098 11.6641 30.5496C11.6641 29.8893 11.127 29.3523 10.4668 29.3523Z" fill="white"/>
              <path d="M30.8589 29.3523C30.1987 29.3523 29.6616 29.8893 29.6616 30.5496C29.6616 31.2098 30.1987 31.7469 30.8589 31.7469C31.5191 31.7469 32.0562 31.2098 32.0562 30.5496C32.0562 29.8893 31.5191 29.3523 30.8589 29.3523Z" fill="white"/>
              <path d="M24.0618 26.8804H15.4104C15.0905 26.8804 14.8311 27.1398 14.8311 27.4598C14.8311 27.7798 15.0904 28.0391 15.4104 28.0391H24.0618C24.3817 28.0391 24.6411 27.7798 24.6411 27.4598C24.6411 27.1398 24.3818 26.8804 24.0618 26.8804Z" fill="white"/>
              <path d="M9.23066 24.4087H1.81519C1.49527 24.4087 1.23584 24.668 1.23584 24.988C1.23584 25.308 1.4952 25.5674 1.81519 25.5674H9.23066C9.55058 25.5674 9.81001 25.308 9.81001 24.988C9.81001 24.668 9.55058 24.4087 9.23066 24.4087Z" fill="white"/>
            </svg>
          </div>
          <div class="advants__text">Dostawa od poniedziałku do soboty do godziny 8 rano</div>
        </div>
        <div class="advants__item">
          <div class="advants__icon">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M18.6665 29.3333H13.3332C8.30485 29.3333 5.7907 29.3333 4.2286 27.7712C2.6665 26.2092 2.6665 23.6949 2.6665 18.6667V16C2.6665 10.9717 2.6665 8.45754 4.2286 6.89544C5.7907 5.33334 8.30485 5.33334 13.3332 5.33334H18.6665C23.6948 5.33334 26.209 5.33334 27.771 6.89544C29.3332 8.45754 29.3332 10.9717 29.3332 16V18.6667C29.3332 23.6949 29.3332 26.2092 27.771 27.7712C26.9001 28.6421 25.7333 29.0275 23.9998 29.198" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M9.3335 5.33334V3.33334" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M22.6665 5.33334V3.33334" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M28.6665 12H22.1665H14.3332M2.6665 12H7.83317" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M24.0002 22.6667C24.0002 23.4031 23.4032 24 22.6668 24C21.9304 24 21.3335 23.4031 21.3335 22.6667C21.3335 21.9303 21.9304 21.3333 22.6668 21.3333C23.4032 21.3333 24.0002 21.9303 24.0002 22.6667Z" fill="white"/>
              <path d="M24.0002 17.3333C24.0002 18.0697 23.4032 18.6667 22.6668 18.6667C21.9304 18.6667 21.3335 18.0697 21.3335 17.3333C21.3335 16.5969 21.9304 16 22.6668 16C23.4032 16 24.0002 16.5969 24.0002 17.3333Z" fill="white"/>
              <path d="M17.3332 22.6667C17.3332 23.4031 16.7362 24 15.9998 24C15.2634 24 14.6665 23.4031 14.6665 22.6667C14.6665 21.9303 15.2634 21.3333 15.9998 21.3333C16.7362 21.3333 17.3332 21.9303 17.3332 22.6667Z" fill="white"/>
              <path d="M17.3332 17.3333C17.3332 18.0697 16.7362 18.6667 15.9998 18.6667C15.2634 18.6667 14.6665 18.0697 14.6665 17.3333C14.6665 16.5969 15.2634 16 15.9998 16C16.7362 16 17.3332 16.5969 17.3332 17.3333Z" fill="white"/>
              <path d="M10.6667 22.6667C10.6667 23.4031 10.0697 24 9.33333 24C8.59696 24 8 23.4031 8 22.6667C8 21.9303 8.59696 21.3333 9.33333 21.3333C10.0697 21.3333 10.6667 21.9303 10.6667 22.6667Z" fill="white"/>
              <path d="M10.6667 17.3333C10.6667 18.0697 10.0697 18.6667 9.33333 18.6667C8.59696 18.6667 8 18.0697 8 17.3333C8 16.5969 8.59696 16 9.33333 16C10.0697 16 10.6667 16.5969 10.6667 17.3333Z" fill="white"/>
            </svg>
          </div>
          <div class="advants__text">Zamawiaj dostawę na dogodną dla Ciebie liczbę dni.</div>
        </div>
        <div class="advants__item">
          <div class="advants__icon">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M30.6668 16C30.6668 16.4727 30.6444 16.9401 30.6007 17.4013C29.8452 16.6455 28.9607 16.0187 27.983 15.5567C27.7498 9.1397 22.4742 4.00912 16.0002 4.00912C9.37778 4.00912 4.00927 9.37762 4.00927 16C4.00927 22.474 9.13986 27.7496 15.5568 27.9828C16.0188 28.9605 16.6456 29.8451 17.4015 30.6005C16.9403 30.6443 16.4728 30.6667 16.0002 30.6667C7.89999 30.6667 1.3335 24.1001 1.3335 16C1.3335 7.89984 7.89999 1.33334 16.0002 1.33334C24.1003 1.33334 30.6668 7.89984 30.6668 16Z" fill="white"/>
              <path d="M17.3332 15.8416L18.4566 16.4903C17.7128 17.0403 17.0534 17.6983 16.502 18.4409L15.3564 17.7795C15.1248 17.6457 14.9485 17.4531 14.8354 17.2313C14.6665 16.9699 14.6665 16.6223 14.6665 16.6223V7.99999C14.6665 7.2636 15.2634 6.66666 15.9998 6.66666C16.7362 6.66666 17.3332 7.26362 17.3332 7.99999V15.8416Z" fill="white"/>
              <path d="M20.3905 23.6095C20.9112 23.0888 21.7554 23.0888 22.2761 23.6095L23.1309 24.4643L25.663 21.9321C26.1838 21.4115 27.028 21.4115 27.5486 21.9321C28.0694 22.4529 28.0694 23.2971 27.5486 23.8179L24.1617 27.2048C24.0465 27.32 23.9156 27.4097 23.7757 27.4739C23.265 27.7725 22.5982 27.7029 22.1604 27.2649L20.3905 25.4951C19.8698 24.9744 19.8698 24.1301 20.3905 23.6095Z" fill="white"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M24 32C28.4183 32 32 28.4183 32 24C32 19.5817 28.4183 16 24 16C19.5817 16 16 19.5817 16 24C16 28.4183 19.5817 32 24 32ZM24 29.3575C21.0412 29.3575 18.6425 26.9588 18.6425 24C18.6425 21.0412 21.0412 18.6425 24 18.6425C26.9588 18.6425 29.3575 21.0412 29.3575 24C29.3575 26.9588 26.9588 29.3575 24 29.3575Z" fill="white"/>
            </svg>
          </div>
          <div class="advants__text">Zamówienia przyjmowane są do godziny 21:00 dnia przed dostawą</div>
        </div>
      </div>
      <!-- end advants -->

      <div class="calc">
        <h2 class="calc__title">Kalkulator kalorii</h2>
        <div class="calc__grid">
          <div class="calc__field">
            <div class="calc__label">Wiek (lata):</div>
            <div class="calc__range">
              <input type="tel" min="10" max="80" value="10" name="age">
              <input type="range" min="10" max="80">
            </div>
          </div>
          <div class="calc__field">
            <div class="calc__label">Płeć:</div>
            <div class="calc__select">
              <select name="gender">
                <option value="male" selected>Mężczyzna</option>
                <option value="female">Kobieta</option>
              </select>
            </div>
            
          </div>
          <div class="calc__field">
            <div class="calc__label">Waga (kg):</div>
            <div class="calc__range">
              <input type="tel" min="10" max="200" value="75" name="weight">
              <input type="range" min="10" max="200">
            </div>
          </div>
          <div class="calc__field">
            <div class="calc__label">Wysokość (cm):</div>
            <div class="calc__range">
              <input type="tel" min="100" max="300" value="180" name="height">
              <input type="range" min="100" max="300">
            </div>
          </div>
          <div class="calc__field">
            <div class="calc__label">Poziom aktywności:</div>

            <div class="calc__select">
              <select name="activity">
                <option value="1.2" selected>Minimum</option>
                <option value="1.375">Lekka aktywność</option>
                <option value="1.55">Umiarkowana aktywność</option>
                <option value="1.725">Wysoka aktywność</option>
                <option value="1.9">Bardzo duża aktywność</option>
              </select>
            </div>
            
          </div>
        </div>
        <button class="btn calc__btn" data-modal-id="calc">Obliczać</button>

        

        <div class="calc-modal calc__modal modal" id="calc">
          <div class="calc-modal__inner">
            <div class="calc-modal__close" data-modal-close>
              <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 23.5L23.5 1M1 1L23.5 23.5" stroke="#232324" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>

              <div class="calc-modal__row">
                <div class="calc-modal__title">Twoje wyniki:</div>
                <div class="calc-modal__result">Podstawowa przemiana materii (BMR): <span class="bmr"></span> kcal/dzień</div>
                <div class="calc-modal__result">Zapotrzebowanie kaloryczne: <span class="calories"></span> kcal/dzień</div>
              </div>

              <div class="calc-modal__row">
                <div class="calc-modal__title">Polecamy Cię:</div>
                <div class="calc-modal__result">Pakiet <span class="recomend"></span> kcal</div>
              </div>

              <a href="#" class="btn calc-modal__btn">Zamów pakiet <span class="btn-recomend"></span> kalorii</a>
            
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- end section -->

  <!-- section -->
    <section class="section" id="menu">
      <div class="container section__container">
          <h2 class="section__title">Zobaczyć Menu</h2>

          <div class="menu">
              <div class="menu__nav">
                  <?php foreach ($weekDates as $index => $date): ?>
                      <div class="menu__nav-item <?= $index === 0 ? 'active' : '' ?>" data-week="<?= $index + 1 ?>">
                          <?php
                          // Получаем начальную и конечную дату
                          $startDate = new DateTime($date['start']);
                          $endDate = new DateTime($date['end']);

                          // Форматируем даты
                          $startFormatted = $startDate->format('d.m');
                          $endFormatted = $endDate->format('d.m');
                          ?>

                          Tydzień <?= $index + 1 ?> <?= $startFormatted ?> - <?= $endFormatted ?>
                      </div>
                  <?php endforeach; ?>
              </div>

              <?php foreach ($days as $weekIndex => $weekDays): ?>
                  <div class="menu__tab <?= $weekIndex === 0 ? 'active' : '' ?>">
                      <div class="menu__head">
                          <div class="menu__title">
                              <?php
                              // Получаем начальную и конечную дату для каждой недели
                              $startDate = new DateTime($weekDates[$weekIndex]['start']);
                              $endDate = new DateTime($weekDates[$weekIndex]['end']);

                              // Форматируем даты
                              $startFormatted = $startDate->format('d.m');
                              $endFormatted = $endDate->format('d.m');

                              // Выводим номер недели и форматированные даты
                              ?>
                              Tydzień <?= $weekIndex + 1 ?>: <?= $startFormatted ?> - <?= $endFormatted ?>
                          </div>

                          <div class="day-nav menu__day-nav">
                              <?php foreach ($weekDays as $dayIndex => $dayDate): ?>
                                  <div class="day-nav__item <?= $dayIndex === 0 ? 'active' : '' ?>" data-day="<?= $dayIndex + 1 ?>">
                                      <?php 
                                      $dayNameMap = [
                                          'Monday' => 'Poniedziałek',
                                          'Tuesday' => 'Wtorek',
                                          'Wednesday' => 'Środa',
                                          'Thursday' => 'Czwartek',
                                          'Friday' => 'Piątek',
                                          'Saturday' => 'Sobota',
                                          'Sunday' => 'Niedziela'
                                      ];
                                      $dayName = DateTime::createFromFormat('d.m.Y', $dayDate)->format('l');
                                      echo $dayNameMap[$dayName];
                                      ?>
                                  </div>
                              <?php endforeach; ?>
                          </div>
                      </div>

                      <?php foreach ($weekDays as $dayIndex => $dayDate): ?>
                          <div class="menu__day-tab <?= $dayIndex === 0 ? 'active' : '' ?>">
                              <div class="menu__grid">
                                  <?php
                                  // Запрос данных о блюдах для текущей недели и дня
                                  try {
                                      // Выбираем данные для текущей недели и дня
                                      $stmt = $pdo->prepare("SELECT * FROM weekly_menu WHERE week_number = ? AND day_number = ?");
                                      $stmt->execute([$weekIndex + 1, $dayIndex + 1]);
                                      $menu = $stmt->fetch(PDO::FETCH_ASSOC);

                                      if ($menu) {
                                          for ($i = 1; $i <= 4; $i++) {
                                              if (!empty($menu["dish_{$i}_title"])) {
                                                  $imgSrc = htmlspecialchars($menu["dish_{$i}_image"]);
                                                  $title = htmlspecialchars($menu["dish_{$i}_title"]);
                                                  $description = nl2br(htmlspecialchars($menu["dish_{$i}_description"]));
                                                  $ingredients = nl2br(htmlspecialchars($menu["dish_{$i}_ingredients"]));
                                                  $allergens = nl2br(htmlspecialchars($menu["dish_{$i}_allergens"]));

                                                  echo "<div class='menu-card'>";
                                                      echo "<div class='menu-card__photo'>";
                                                          echo "<img src='/uploads_img/$imgSrc' alt='Danie $i na dzień $dayIndex' loading='lazy' />";
                                                      echo "</div>";

                                                      echo "<div class='menu-card__data'>";
                                                          echo "<div class='menu-card__heading'>$title</div>"; // Заголовок блюда
                                                          echo "<div class='menu-card__row'>";
                                                              echo "<div class='menu-card__text'>$description</div>"; // Описание
                                                          echo "</div>";

                                                          echo "<div class='menu-card__row'>";
                                                              echo "<div class='menu-card__title'>Skladniki:</div>";
                                                              echo "<div class='menu-card__text'>$ingredients</div>"; // Ингредиенты
                                                          echo "</div>";

                                                          echo "<div class='menu-card__row'>";
                                                              echo "<div class='menu-card__title'>Alergeny:</div>";
                                                              echo "<div class='menu-card__text'>$allergens</div>"; // Аллергены
                                                          echo "</div>";
                                                      echo "</div>";
                                                  echo "</div>";
                                              }
                                          }
                                      } else {
                                          echo "<p class='no-data'>Brak danych na ten dzień</p>";
                                      }
                                  } catch (Exception $e) {
                                      error_log("Ошибка при выполнении SQL-запроса: " . $e->getMessage());
                                      echo "<p>Wystąpił błąd. Prosimy spróbować ponownie później.</p>";
                                  }
                                  ?>
                              </div>
                              <a href="/index2/" class="btn menu__btn">Złóż zamówienie</a>
                          </div>
                      <?php endforeach; ?>
                  </div>
              <?php endforeach; ?>
          </div>
      </div>
  </section>
  <!-- end section -->


  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/feedback.php'; ?> 


  <?php
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
  ?>



</body>
</html>


