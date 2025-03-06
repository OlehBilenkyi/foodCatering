<!DOCTYPE html>
<html lang="pl">

<?php
$nonce = base64_encode(random_bytes(16));
// Вставьте это в самом начале index.php и prices/index.php (или в любом другом файле, где нужны отзывы)
if (strpos($_SERVER['REQUEST_URI'], 'feedback') !== false) {
    include 'includes/feedback.php';
}

    if (session_status() == PHP_SESSION_NONE) {
        session_start(); // Стартуем сессию, если она еще не начата
    }

    // Проверяем, создан ли CSRF-токен, если нет - создаем его
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Основная информация о странице
    $pageTitle = "Nasze Ceny - Restauracja";
    $metaDescription = "Sprawdź nasze ceny na smaczne i zdrowe posiłki!";
    $metaKeywords = "restauracja, ceny, jedzenie, obiady, kolacja";
    $metaAuthor = "Twoja Restauracja";

    include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>

<body>
<?php 
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; // Подключаем шапку
    include $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Подключаем базу данных

    // Подготовка и выполнение запроса для получения данных о ценах
    try {
        $stmt = $pdo->prepare("SELECT name, price, image FROM price ORDER BY id ASC");
        $stmt->execute();
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Ошибка при выполнении SQL-запроса: " . $e->getMessage());
        $prices = [];
    }

    // Скидки для колонок 20, 24, 28 дней
    $discounts = [
        20 => 4,  // Скидка 4% на 20 дней
        24 => 5,  // Скидка 5% на 24 дня
        28 => 7   // Скидка 7% на 28 дней
    ];
?>


<main class="page-main">
    <h1 class="page-title">Cennic uslug cateringowych</h1>
    <div class="price-list">
      <div class="container">
        <div class="price-list__table">
          <table>
            <thead>
              <th>Kalorii</th>
              <th>1 dzien</th>
              <th>20 dnu <b>(-4%)</b></th>
              <th>24 dnu <b>(-5%)</b></th>
              <th>24 dnu <b>(-7%)</b></th>
            </thead>
            <tbody>
                <?php foreach ($prices as $price): 
                    $name = htmlspecialchars($price['name']);
                    $pricePerDay = floatval($price['price']);
                    $image = htmlspecialchars($price['image']);
                    
                    // Расчет стоимости на 20 дней с учетом скидки 4%
                    $price20DaysOriginal = $pricePerDay * 20;
                    $price20DaysDiscounted = $price20DaysOriginal * (1 - $discounts[20] / 100);
                    
                    // Расчет стоимости на 24 дня с учетом скидки 5%
                    $price24DaysOriginal = $pricePerDay * 24;
                    $price24DaysDiscounted = $price24DaysOriginal * (1 - $discounts[24] / 100);
                    
                    // Расчет стоимости на 28 дней с учетом скидки 7%
                    $price28DaysOriginal = $pricePerDay * 28;
                    $price28DaysDiscounted = $price28DaysOriginal * (1 - $discounts[28] / 100);
                ?>
                <tr>
                    <td>
                        <span>
                            <img src="/uploads_img<?php echo $image; ?>" alt="<?php echo $name; ?> kalorii">
                            <?php echo $name; ?> Kalorii
                        </span>
                    </td>

                    <td>
                        <?php echo number_format($pricePerDay, 2, '.', ''); ?> zł
                    </td>
                    <td>
                        <div class="price">
                          <?php echo number_format($price20DaysDiscounted, 2, '.', ''); ?> zł
                          <div class="price__old"><?php echo number_format($price20DaysOriginal, 2, '.', ''); ?> zł</div>
                        </div>
                    </td>
                    <td>
                        <div class="price">
                          <?php echo number_format($price24DaysDiscounted, 2, '.', ''); ?> zł
                          <div class="price__old"><?php echo number_format($price24DaysOriginal, 2, '.', ''); ?> zł</div>
                        </div>
                    </td>
                    <td>
                        <div class="price">
                          <?php echo number_format($price28DaysDiscounted, 2, '.', ''); ?> zł
                          <div class="price__old"><?php echo number_format($price28DaysOriginal, 2, '.', ''); ?> zł</div>
                        </div>
                    </td>
                    
                </tr>
                <?php endforeach; ?>
              
            </tbody>
          </table>
        </div>

        <!--<div class="price-list__grid">-->
        <!--  <div class="price-list__col">-->
          <!--  <p>Zamowienie do 19 dni:</p>-->

          <!--  <ul>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--  </ul>-->
          <!--</div>-->
          <!--<div class="price-list__col">-->
          <!--  <p>Zamowienie do 19 dni:</p>-->

          <!--  <ul>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--  </ul>-->
          <!--</div>-->
          <!--<div class="price-list__col">-->
          <!--  <p>Zamowienie do 19 dni:</p>-->

          <!--  <ul>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--  </ul>-->
          <!--</div>-->
          <!--<div class="price-list__col">-->
          <!--  <p>Zamowienie do 19 dni:</p>-->

          <!--  <ul>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--    <li>Dostepny od ponidzialku do soboty</li>-->
          <!--  </ul>-->
        <!--  </div>-->
        <!--</div>-->

        <a href="/index2" class="btn price-list__btn">Złóż zamówienie</a>
      </div>
    </div>


    <!-- section -->
    <section class="section is-white">
      <div class="container section__container">
        <!-- advants -->

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
                  <option value="male" selected>Człowiek</option>
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

  
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/feedback.php'; ?> 

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?> <!-- Подключаем подвал -->



</body>
</html>
