<?php ini_set('display_errors','0');ini_set('log_errors','1');ini_set('error_log',$_SERVER['DOCUMENT_ROOT'].'/logs/error_log.log');error_reporting(E_ALL);header("Cache-Control: no-cache, no-store, must-revalidate");header("Pragma: no-cache");header("Expires: 0");if(session_status()===PHP_SESSION_NONE){session_start();error_log("Сессия запущена. ID сессии: ".session_id());session_regenerate_id(true);error_log("Session ID перегенерирован.");}include($_SERVER['DOCUMENT_ROOT'].'/config/db.php');require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';use Dotenv\Dotenv;$dotenv=Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);$dotenv->load();$stripePublishableKey=$_ENV['STRIPE_PUBLISHABLE_KEY']?? '';if(!$stripePublishableKey){error_log("Stripe Publishable Key не установлен.");}if(!isset($_SESSION['csrf_token'])){$_SESSION['csrf_token']=bin2hex(random_bytes(32));error_log("Новый CSRF-токен сгенерирован: ".$_SESSION['csrf_token']);}else{error_log("Используется существующий CSRF-токен: ".$_SESSION['csrf_token']);}$csrf_token=$_SESSION['csrf_token'];error_log("Текущее содержимое сессии: ".print_r($_SESSION,true));if(!$pdo){error_log("[ERROR] Ошибка подключения к базе данных.");exit('Не удалось подключиться к базе данных.');}function getMenuItemsByCategory(PDO $pdo,string $category):array{try{$query="SELECT menu_options_id, category, dish_name, dish_image, dish_title, dish_description, dish_ingredients, dish_allergens, dish_energy, dish_fat, dish_carbohydrates, dish_protein, dish_salt, dish_net_mass \n                  FROM menu_options \n                  WHERE category = :category";$stmt=$pdo->prepare($query);$stmt->bindParam(':category',$category,PDO::PARAM_STR);$stmt->execute();return $stmt->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log("[ERROR] Ошибка при выполнении запроса getMenuItemsByCategory: ".$e->getMessage());return[];}}function getFirstFourMenuItems(array $menuItems,int $limit=50):array{return array_slice($menuItems,0,$limit);}try{$menuItemsSniadanie=getFirstFourMenuItems(getMenuItemsByCategory($pdo,'Śniadanie'));$menuItemsObiad=getFirstFourMenuItems(getMenuItemsByCategory($pdo,'Obiad'));$menuItemsKolacja=getFirstFourMenuItems(getMenuItemsByCategory($pdo,'Kolacja'));}catch(Exception $e){error_log("[ERROR] Ошибка при получении данных категорий: ".$e->getMessage());exit('Ошибка при загрузке данных категорий.');}$categories=['Sniadanie','Obiad','Kolacja','Przekaska'];function getAllMenuPrices(PDO $pdo,array $categories):array{try{$placeholders=implode(',',array_fill(0,count($categories),'?'));$query="SELECT * FROM menu_prices WHERE category IN ($placeholders)";$stmt=$pdo->prepare($query);$stmt->execute($categories);return $stmt->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log("[ERROR] Ошибка при выполнении запроса getAllMenuPrices: ".$e->getMessage());return[];}}try{$menuData=getAllMenuPrices($pdo,$categories);$groupedData=[];foreach($menuData as $item){$groupedData[$item['category']][]=$item;}}catch(Exception $e){error_log("[ERROR] Ошибка при загрузке цен: ".$e->getMessage());$groupedData=[];}if($_SERVER['REQUEST_METHOD']==='POST'){try{$json=file_get_contents('php://input');$data=json_decode($json,true);if(!$data){error_log("[ERROR] Не удалось декодировать JSON из тела запроса.");echo json_encode(['status'=>'error','message'=>'Неверный формат данных.']);exit();}error_log("Перед отправкой ответа CSRF-токен в сессии: ".$_SESSION['csrf_token']);error_log("Полученный из запроса CSRF-токен: ".($data['csrf_token']?? 'null'));if(empty($data['csrf_token'])||!hash_equals($_SESSION['csrf_token'],$data['csrf_token'])){error_log('[WARNING] CSRF токен отсутствует или не совпадает.');echo json_encode(['status'=>'error','message'=>'Неверный CSRF токен. Операция отклонена.']);exit();}error_log("POST данные: ".print_r($data,true));$requiredFields=['email','total_price'];foreach($requiredFields as $field){$value=$data[$field]?? '';if(is_array($value)){$value=trim(reset($value));}else{$value=trim($value);}if(empty($value)){throw new Exception("Не заполнено обязательное поле: $field");}}$email=filter_var(trim($data['email']),FILTER_VALIDATE_EMAIL);if(!$email){throw new Exception("Неверный формат email: ".$data['email']);}$totalPrice=floatval($data['total_price']);if($totalPrice<=0){throw new Exception("Неверная сумма оплаты: $totalPrice");}$_SESSION['order']=['email'=>$email,'total_price'=>$totalPrice,'details'=>$data['details']??[]];error_log("Данные заказа успешно сохранены: ".print_r($_SESSION['order'],true));$stmt=$pdo->prepare("INSERT INTO customer_menu_order_items (category, dish_name, weight, price, menu_options_id) VALUES (:category, :dish_name, :weight, :price, :menu_options_id)");foreach($data['order_days']as $orderDay){foreach($orderDay['items']as $item){$stmt->execute([':category'=>$item['category'],':dish_name'=>$item['dish_name'],':weight'=>$item['weight'],':price'=>$item['price'],':menu_options_id'=>$item['menu_options_id']]);error_log("Вставляем блюдо: category={$item['category']}, dishName={$item['dish_name']}, weight={$item['weight']}, price={$item['price']}, menu_options_id={$item['menu_options_id']}");}}$newCsrfToken=bin2hex(random_bytes(32));$_SESSION['csrf_token']=$newCsrfToken;error_log("✅ Новый CSRF-токен создан: ".$_SESSION['csrf_token']);echo json_encode(['status'=>'success','message'=>'Заказ успешно обработан.','order_id'=>$orderId ?? null,'new_csrf_token'=>$newCsrfToken]);exit();}catch(Exception $e){error_log("[ERROR] Ошибка при обработке POST-запроса: ".$e->getMessage());echo json_encode(['status'=>'error','message'=>$e->getMessage()]);exit();}}$pageTitle="Food Case Catering";$metaDescription="Sprawdź nasze ceny na smaczne i zdrowe posiłki!";$metaKeywords="restauracja, ceny, jedzenie, obiady, kolacja";$metaAuthor="Twoja Restauracja";include($_SERVER['DOCUMENT_ROOT'].'/includes/head.php');include($_SERVER['DOCUMENT_ROOT'].'/includes/header.php');ini_set('log_errors',1);ini_set('error_log',$_SERVER['DOCUMENT_ROOT'].'/logs/error_log.log');error_reporting(E_ALL); ?>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Catering - Zdrowe posiłki i dostawa na każdą okazję | FoodCase Catering | Kraków, Polska</title>
<meta name="description" content="FOODCASE - najlepszy catering dietetyczny w Krakowie. Oferujemy zdrowe całodzienne posiłki z dostawą do domu: śniadania, obiady, przekąski, kolacje, posiłki niskokaloryczne, wegańskie, wegetariańskie, sportowe i ketogeniczne. Sprawdź nasze menu i zamów już teraz catering dietetyczny w Krakowie. FoodCase Catering - najlepsze zdrowe posiłki na imprezy, spotkania, dostawy do domów w Krakowie i całej Polsce. Oferujemy szeroki wybór cateringu dietetycznego, lunch boxów, jedzenia na eventy, imprezy rodzinne, wesela, konferencje i firmowe wydarzenia. Zamów zdrowe i smaczne posiłki już dziś! Catering Całodzienny w Krakowie z dostawą na cały dzień. Śniadania, obiady, przekąski, kolacje, diety sportowe i niskokaloryczne dostarczane prosto do Twoich drzwi. Wybierz wygodę i jakość każdego dnia! Nasze menu jest starannie skomponowane, aby zapewnić Ci zdrowe, smaczne i zbilansowane posiłki każdego dnia.">
<meta name="keywords" content="catering dietetyczny Kraków, zdrowe jedzenie Kraków, dostawa posiłków Kraków, catering na wynos Kraków, dieta pudełkowa Kraków, zdrowe obiady Kraków, FOODCASE catering, dieta, posiłki do domu Kraków, śniadania na wynos, catering całodzienny, catering dietetyczny Polska, zdrowa dieta, jedzenie z dostawą, dieta pudełkowa z dostawą, posiłki do domu Kraków, catering, catering dietetyczny, dostawa jedzenia, zdrowe posiłki, Kraków catering, Polska catering, catering firmowy, catering na eventy, catering na imprezy, dostawa posiłków Kraków, lunch box Kraków, posiłki dietetyczne Kraków, catering weselny, catering konferencyjny, jedzenie na zamówienie, zdrowa żywność, dostawa dietetycznych posiłków, catering na spotkania, jedzenie z dostawą, fit catering, catering bezglutenowy, catering wegański, catering wegetariański, posiłki na wynos, catering niskokaloryczny, catering sportowy, diety sportowe, posiłki wysokobiałkowe, catering ketogeniczny, dieta ketogeniczna, diety odchudzające, posiłki na przyjęcia, catering eventowy, catering biznesowy, dieta catering, fit jedzenie, posiłki bez laktozy, catering na wynos, dostawa zdrowego jedzenia, catering na każdą okazję, dostawa cateringu Kraków, najlepsze jedzenie na wynos, catering dla firm Kraków, catering weselny Kraków, catering na imprezy rodzinne, catering na urodziny, dostawa jedzenia na eventy, zamówienie cateringu, catering dietetyczny na zamówienie, zdrowe jedzenie z dostawą, catering okolicznościowy, catering dla rodzin, catering na domówki, dostawa obiadów, catering imprezowy, jedzenie z dostawą na miejsce, jedzenie na wynos w Krakowie, catering dla sportowców, fit catering Kraków, zdrowe posiłki na zamówienie, catering na konferencje Kraków, catering na spotkania firmowe, catering na każdą okazję Kraków, zdrowe menu Kraków, catering na wesela, catering z dostawą na miejsce, zamów catering na imprezę, catering weselny w Polsce, zdrowe jedzenie na wynos, catering na specjalne wydarzenia, jedzenie na przyjęcia Kraków, catering na specjalne zamówienia, catering z dostawą, catering na chrzciny, catering dla dzieci, catering na komunię, catering na rodzinne spotkania, jedzenie z dowozem Kraków, dostawa jedzenia na przyjęcia, catering na każdą okazję, catering dietetyczny w Polsce, catering z dostawą do domu, catering niskokaloryczny, zdrowe obiady z dostawą, posiłki dla sportowców, posiłki białkowe, diety na masę, zdrowe odżywianie Kraków">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://foodcasecatering.net/index2/" />
<meta name="language" content="pl">
<meta name="author" content="FoodCase Catering">
<meta name="geo.region" content="PL-MA">
<meta name="geo.placename" content="Kraków, Polska">
<meta name="geo.position" content="50.0646501;19.9449799">
<meta name="ICBM" content="50.0646501, 19.9449799">
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
<meta name="business:contact_data:locality" content="Kraków">
<meta name="business:contact_data:region" content="Małopolskie">
<meta name="business:contact_data:country_name" content="Polska">
<meta name="business:contact_data:postal_code" content="31-000">
<meta name="business:contact_data:email" content="info@foodcasecatering.net">
<meta name="business:contact_data:phone_number" content="+48 123 456 789">
<meta name="gmb:business_name" content="FoodCase Catering">
<meta name="gmb:description" content="Catering i zdrowe posiłki w Krakowie. Złóż zamówienie na najlepsze jedzenie na wynos i dostawę do domu lub biura. W naszej ofercie znajdziesz również diety sportowe, ketogeniczne i niskokaloryczne.">
<meta name="gmb:address" content="ul. Warszawska 123, 31-000 Kraków, Polska">
<meta name="gmb:phone_number" content="+48 123 456 789">
<meta name="subject" content="Catering w Krakowie - Najlepsze Zdrowe Posiłki, Diety Sportowe i Ketogeniczne">
<meta name="coverage" content="Kraków, Polska, Małopolska">
<meta name="distribution" content="Global">
<meta name="rating" content="General">
<meta name="target" content="all">
<meta name="HandheldFriendly" content="true">
<meta name="MobileOptimized" content="320">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<script src="https://cdn.jsdelivr.net/npm/air-datepicker/locale/air-datepicker.pl.js"></script>
<script src="https://cdn.jsdelivr.net/npm/air-datepicker@latest/air-datepicker.min.js" defer></script>
<script src="https://js.stripe.com/v3/" nonce="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"></script>
<style>
    /* Пример простой стилизации */
    

.disabled {
  pointer-events: auto; /* важно, чтобы события не блокировались */
  opacity: 1;
  cursor: pointer;
}.add-food{width: 210px;text-align: center;border-radius:10px;font-size:14px;font-weight:500;line-height:42px;color:#fff;background:#0056d3;transition:background-color 0.5s,transform 0.3s;cursor:pointer}.page-grid__tab{width:100%;display:none;position:relative}.page-grid__tab.active{display:block}.pay-widget{display:flex;flex-direction:column;width:100%;position:relative;border-bottom:1px solid #fff}.pay-widget__main{display:flex;flex-direction:column}.pay-widget__row{padding:20px 0;position:relative;border-bottom:1px solid #e0e0e0}.pay-widget__row:first-child{padding-top:0}.pay-widget__title{text-align:center;margin-bottom:20px;font-size:35px;font-weight:700;line-height:1}.calendar-container{display:flex;justify-content:space-evenly;padding-bottom:30px;flex-direction:column;align-items:center;gap:15px}.select-date{font-weight:600;font-size:26px}.pay-widget__heading{text-align:center;font-size:25px;font-weight:600;margin-bottom:20px}.pay-wInfo,.select-weight{font-size:25px}.pay-wInfo__text{padding-top:10px;padding-bottom:20px;font-size:25px}.pay-widget__grid{margin-top:20px;display:flex;gap:15px;flex-direction:column;overflow:inherit}.pay-full-card__photo{margin:-15px 0 -15px -20px;display:none}.pay-full-card__data{flex:1 1 auto}.pay-full-card__title{font-size:22px;font-weight:600}.pay-full-card__item{display:flex;align-items:flex-start;gap:5px;font-size:14px;line-height:16px;padding:14px 0;border-bottom:1px solid #fff}.pay-full-card__item:last-child{padding-bottom:0;border-bottom:0}.price{display:flex;align-items:center;gap:6px;font-size:16px}.price__old{font-weight:500;color:red;text-decoration:line-through}.menu-item{display:flex;flex-direction:row;background:#fff;border-radius:10px;box-shadow:0 0 20px rgb(0 0 0 / .13);overflow:hidden}.menu-card__heading h6{font-size:14px}.menu-card__photo{flex-shrink:0;width:35%}.pay-full-card__data,.menu-card__row{overflow-wrap:break-word;word-break:break-word;white-space:normal}.pay-full-card__data{padding:10px;white-space:normal;word-wrap:break-word;overflow:hidden;text-overflow:ellipsis;-webkit-line-clamp:3;-webkit-box-orient:vertical}.menu-card__row{display:flex;flex-wrap:wrap;gap:5px}.menu-item:last-child{margin-right:0}.waga-button_sniad,.waga-button_obiad,.waga-button_przekaska,.waga-button_kolacja{display:inline-flex;align-items:center;justify-content:center;padding:12px;border-radius:10px;font-size:14px;font-weight:500;line-height:20px;color:#fff;background:#0056d3;transition:background-color 0.5s,transform 0.3s;cursor:pointer}.waga-button_sniad:hover,.waga-button_obiad:hover,.waga-button_przekaska:hover,.waga-button_kolacja:hover,.add-food:hover{background-color:#0040a8;transform:scale(1.05)}.waga_sniad,.waga_obiad,.waga_kolacja{display:flex;gap:10px}.zl_sniad-zl,.zl_obiad-zl,.zl_przekaska-zl,.zl_kolacja-zl{align-items:center;padding-bottom:10px;display:flex;justify-content:space-evenly;gap:10px;flex-direction:column}.zl_przekaska{text-align:center}.btn.pay-widget__more.hidden{display:none}#blocks-container .page-grid__tab{margin-bottom:20px}.menu-item{transition:transform 0.3s ease,box-shadow 0.3s ease}.menu-item:hover{transform:scale(1.05);box-shadow:0 0 15px rgb(0 123 255 / .6)}.menu-item.selected{border:2px solid #00d1ff;box-shadow:0 0 20px #00d1ff;transform:scale(1.05);transition:all 0.3s ease}.pay-item__btn{border:1px solid #ccc;padding:10px 15px;margin:5px;border-radius:5px;cursor:pointer;transition:all 0.3s ease}.pay-item__btn.active{border:2px solid #0af;background-color:#e6f7ff;color:#005580;font-weight:700;box-shadow:0 0 10px rgb(0 170 255 / .5)}.pay-item__btn:hover{background-color:#f5f5f5;border-color:#aaa}.pay-total__send.disabled{background-color:#ddd;color:#aaa;pointer-events:none}.pay-item__btn.active{border:2px solid #007BFF;background-color:#E6F7FF;color:#0056b3;font-weight:700;box-shadow:0 0 8px rgb(0 123 255 / .6)}.pay-total__send.disabled{background-color:#ccc;color:#666;cursor:not-allowed}.pay-total__send{background-color:#007BFF;color:#fff;padding:10px 20px;font-size:16px;border:none;border-radius:5px;cursor:pointer;transition:all 0.3s ease}.pay-total__send:hover{background-color:#0056b3}.pay-total__send.disabled{background-color:#ccc;color:#666;cursor:not-allowed}@media (min-width:993px){.pay-widget__grid{margin-top:50px;gap:25px}}@media (min-width:767px){.pay-widget__grid{display:grid;grid-template-columns:1fr 1fr}.menu-card__row{font-size:14px}}@media (min-width:560px){.calendar-container{flex-direction:row;align-items:flex-start;gap:0}.waga_sniad,.waga_obiad,.waga_kolacja,.zl_sniad-zl,.zl_obiad-zl,.zl_kolacja-zl{flex-direction:row}}@media (min-width:365px){.menu-card__heading h6{font-size:18px}}@media (min-width:120px){.menu-card__row{font-size:14px}}.pay-widget__row:not(.additional-category) .remove-category{display:none}.pay-widget__row.additional-category .remove-category{display:block}#accept-label{pointer-events:auto;cursor:pointer}.pay-widget__heading-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.pay-widget__note{font-weight:700;color:#333;font-size:14px}.cookie-banner{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:90%;max-width:800px;background-color:#f7f7f7;border-radius:15px 15px 0 0;border:1px solid #e0e0e0;box-shadow:0 -4px 10px rgb(0 0 0 / .1);padding:20px;display:flex;flex-direction:column;align-items:center;text-align:center;z-index:1000;animation:slide-up 0.5s ease-in-out}@keyframes slide-up{from{transform:translate(-50%,100%)}to{transform:translateX(-50%)}}.cookie-content p{color:#333;font-family:'Poppins',Arial,sans-serif;font-size:16px;margin-bottom:15px;line-height:1.6}.cookie-policy-link{color:#007bff;font-weight:700;text-decoration:none}.cookie-policy-link:hover{text-decoration:underline;color:#0056b3}.cookie-buttons{display:flex;gap:15px;justify-content:center}.cookie-accept-button,.cookie-decline-button{padding:10px 20px;font-size:16px;font-family:'Poppins',Arial,sans-serif;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:all 0.3s ease-in-out}.cookie-accept-button{background-color:#007bff;color:#fff}.cookie-accept-button:hover{background-color:#0056b3}.cookie-decline-button{background-color:#6c757d;color:#fff}.cookie-decline-button:hover{background-color:#5a6268}#pay-button.loading{pointer-events:none;opacity:.8;color:#fff0;background-color:rgb(0 0 0 / .1);position:relative}#pay-button.loading::after{content:"";position:absolute;top:50%;left:50%;width:24px;height:24px;margin:-12px 0 0 -12px;border:3px solid #fff;border-top-color:#0056D2;border-radius:50%;animation:spin 0.7s linear infinite;z-index:10}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}</style>
</head>
<body>
<main class=page-main>
<div class=container>
<h1 class=page-title>Złóż zamówienie</h1>
<div class=steps-line>
<div class="steps-line__item active">
<div class=steps-line__num>1</div>
Wybierz kaloryczność
</div>
<div class=steps-line__item>
<div class=steps-line__num>2</div>
Podaj dane dostawy
</div>
<div class=steps-line__item>
<div class=steps-line__num>3</div>
Podsumowania zamówienia
</div>
<div class=steps-line__item>
<div class=steps-line__num>4</div>
Płatność
</div>
</div>
<div class="calc-modal pay__modal modal" id=calc>
<div class=calc-modal__inner>
<div class=calc-modal__close data-modal-close>
<svg width=25 height=25 viewBox="0 0 25 25" fill=none xmlns=http://www.w3.org/2000/svg>
<path d="M1 23.5L23.5 1M1 1L23.5 23.5" stroke=#232324 stroke-width=1.5 stroke-linecap=round stroke-linejoin=round />
</svg>
</div>
<svg class=icon width=120 height=139 viewBox="0 0 120 139" fill=none xmlns=http://www.w3.org/2000/svg>
<circle cx=60 cy=69 r=57.5 stroke=#FF0000 stroke-width=5 />
<path d="M64.5625 27.3636L63.7457 87.4773H54.2713L53.4545 27.3636H64.5625ZM59.0085 111.653C56.9938 111.653 55.265 110.932 53.8221 109.489C52.3791 108.046 51.6577 106.317 51.6577 104.303C51.6577 102.288 52.3791 100.559 53.8221 99.1161C55.265 97.6732 56.9938 96.9517 59.0085 96.9517C61.0232 96.9517 62.752 97.6732 64.195 99.1161C65.6379 100.559 66.3594 102.288 66.3594 104.303C66.3594 105.637 66.0191 106.862 65.3384 107.978C64.685 109.094 63.8002 109.993 62.6839 110.673C61.5949 111.327 60.3698 111.653 59.0085 111.653Z" fill=#FF1313 />
</svg>
<div class=calc-modal__title>Wybierz datę dostawy dla wszystkich paczek</div>
</div>



</div>
<form action=/payments/process_order_do_wyboru.php method=POST class=page-grid id=order-form>
<input type=hidden name=csrf_token value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
<input type=hidden id=hidden-order-id name=hidden-order-id>
<div class=page-grid__main>
<div id=template-block class="page-grid__tab active product-tab">
<div class=pay-widget-container>
</div>
<div hidden class="btn pay-widget__more hidden">Dodaj następną datę</div>
<div class="btn pay-widget__moreV2">Dodaj następną datę</div>
</div>
<div class=page-grid__tab>
<div class="btn pay-total__prev">
<svg width=6 height=10 viewBox="0 0 6 10" fill=none xmlns=http://www.w3.org/2000/svg>
<path fill-rule=evenodd clip-rule=evenodd d="M5.70679 0.292787C5.89426 0.480314 5.99957 0.734622 5.99957 0.999786C5.99957 1.26495 5.89426 1.51926 5.70679 1.70679L2.41379 4.99979L5.70679 8.29279C5.88894 8.48139 5.98974 8.73399 5.98746 8.99619C5.98518 9.25838 5.88001 9.5092 5.6946 9.6946C5.5092 9.88001 5.25838 9.98518 4.99619 9.98746C4.73399 9.98974 4.48139 9.88894 4.29279 9.70679L0.292787 5.70679C0.105316 5.51926 0 5.26495 0 4.99979C0 4.73462 0.105316 4.48031 0.292787 4.29279L4.29279 0.292787C4.48031 0.105316 4.73462 0 4.99979 0C5.26495 0 5.51926 0.105316 5.70679 0.292787V0.292787Z" fill=black />
</svg>
Zmień kolejność
</div>
<div class=delivery-widget>
<div class=delivery-widget__row>
<div class=delivery-widget__title>Dane kontaktowe:</div>
<div class=delivery-widget__grid>
<div class="delivery-widget__field large">
<div class=delivery-widget__label>E-mail</div>
<input type=email name=email id=email required class="delivery-widget__input email-input" placeholder=E-mail>
</div>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Numer telefonu</div>
<input name=phone id=phone required class="delivery-widget__input phone-input" placeholder="Numer telefonu">
</div>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Pełne imię i nazwisko</div>
<input name=fullname id=fullname required class="delivery-widget__input fullname-input" placeholder="Pełne imię i nazwisko">
</div>
</div>
</div>
<div class=delivery-widget__row>
<div class=delivery-widget__title>Dane dostawy:</div>
<div class=delivery-widget__grid>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Ulica</div>
<input name=street id=street required class="delivery-widget__input street-input" placeholder=Ulica>
</div>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Dom</div>
<input name=house_number id=house_number required class="delivery-widget__input house-number-input" placeholder=Dom>
</div>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Klatka</div>
<input name=klatka id=klatka class="delivery-widget__input klatka-input" placeholder=Klatka>
</div>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Piętro</div>
<input name=floor id=floor required class="delivery-widget__input floor-input" placeholder=Piętro>
</div>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Mieszkanie</div>
<input name=apartment id=apartment required class="delivery-widget__input apartment-input" placeholder=Mieszkanie>
</div>
<div class=delivery-widget__field>
<div class=delivery-widget__label>Kod do klatki</div>
<input name=gate_code id=gate_code required class="delivery-widget__input gate-code-input" placeholder="Kod do klatki">
</div>
<div class="delivery-widget__field large">
<div class=delivery-widget__label>Uwagi</div>
<input name=notes id=notes class="delivery-widget__input notes-input large" placeholder=Uwagi>
</div>
</div>
</div>
</div>
</div>
<div class=page-grid__tab>
<div class="btn pay-total__prev">
<svg width=6 height=10 viewBox="0 0 6 10" fill=none xmlns=http://www.w3.org/2000/svg>
<path fill-rule=evenodd clip-rule=evenodd d="M5.70679 0.292787C5.89426 0.480314 5.99957 0.734622 5.99957 0.999786C5.99957 1.26495 5.89426 1.51926 5.70679 1.70679L2.41379 4.99979L5.70679 8.29279C5.88894 8.48139 5.98974 8.73399 5.98746 8.99619C5.98518 9.25838 5.88001 9.5092 5.6946 9.6946C5.5092 9.88001 5.25838 9.98518 4.99619 9.98746C4.73399 9.98974 4.48139 9.88894 4.29279 9.70679L0.292787 5.70679C0.105316 5.51926 0 5.26495 0 4.99979C0 4.73462 0.105316 4.48031 0.292787 4.29279L4.29279 0.292787C4.48031 0.105316 4.73462 0 4.99979 0C5.26495 0 5.51926 0.105316 5.70679 0.292787V0.292787Z" fill=black />
</svg>
Еdytować dane
</div>
<div class=total-info>
<div class=total-info__row>
<h3 class=total-info__heading>Informacje o zamówieniu</h3>
<div class="total-info__grid menu_do_wyboru" id=order-summary>
</div>
</div>
<div class=total-info__row>
<h3 class=total-info__heading>Dane dostawy</h3>
<div class=total-info__data></div>
</div>
</div>
</div>
</div>
<div class=page-grid__aside>
<div class=pay-total>
<div class="pay-total__item packgs">
<b>Wybrane daty:</b>
<span id=package-count>Jeszcze nie wybrano żadnej daty.</span>
</div>
<div class="pay-total__item total">
<b>Liczba dat:</b>
<span id=total-without-discount>0</span>
</div>
<div class="pay-total__item discount">
<b>Rabat:</b>
<span id=discount-amount style=color:red;font-weight:700>0.00zł</span>
</div>
<div class="pay-total__item sum">
<b>Razem do zapłaty:</b>
<span id=total-price style=color:#006a23;font-weight:700>0.00zł</span>
</div>
<label for=accept id=accept-label class="accept-terms disabled">
<input type=checkbox id=accept class="disabled" disabled>
<span>
Zapoznałem się z zasadami strony i <a href=/regulamin/ target=_blank>Regulamin</a>.
</span>
</label>
<div class="btn pay-total__send btn_waga active" disabled>Podać adres dostawy</div>
<div class="btn pay-total__send" disabled>Podsumowania zamówienia</div>
<button type=submit id=pay-button class="btn pay-total__send active">Płatność</button>
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
// Глобальный массив для хранения уникальных дат (только для отображения в UI).
let e = [];

// Функция, которая обновляет UI (текстовые поля) на основе массива e.
const t = () => {
  const packageCount = document.getElementById("package-count");         // <span id="package-count"></span>
  const totalWithoutDiscount = document.getElementById("total-without-discount"); // <span id="total-without-discount"></span>

  if (packageCount && totalWithoutDiscount) {
    // Выводим даты через запятую
    packageCount.textContent = e.join(", ");
    // Выводим общее количество дат
    totalWithoutDiscount.textContent = e.length;
  }
};

document.addEventListener("click", (event) => {
    const removePackageElem = event.target.closest(".remove-package");
    if (removePackageElem) {
        const payWidget = removePackageElem.closest(".pay-widget");
        if (payWidget) {
            const selectedDateInput = payWidget.querySelector('input[name^="selected-date"]');
            if (selectedDateInput) {
                const selectedDate = selectedDateInput.value;
                e = e.filter((date) => date !== selectedDate); // Удаление даты из массива
            }
            payWidget.remove();
            a.saveOrderDataToStorage();
            t(); // Обновление отображения
            return;
        }
    }

    const removeCategoryElem = event.target.closest(".remove-category");
    if (removeCategoryElem) {
        const additionalCategory = removeCategoryElem.closest(".additional-category");
        if (additionalCategory) {
            additionalCategory.remove();
            a.saveOrderDataToStorage();
            t(); // Обновление отображения
        }
    }

    const cookieAcceptBtn = event.target.closest('.cookie-accept-button');
    if (cookieAcceptBtn) {
        document.cookie = "cookieConsent=true; path=/; max-age=" + 60 * 60 * 24 * 365;
        const cookieBanner = document.querySelector('.cookie-banner');
        if (cookieBanner) {
            cookieBanner.style.display = 'none';
        }
    }

    const cookieDeclineBtn = event.target.closest('.cookie-decline-button');
    if (cookieDeclineBtn) {
        document.cookie = "cookieConsent=false; path=/; max-age=" + 60 * 60 * 24 * 365;
        const cookieBanner = document.querySelector('.cookie-banner');
        if (cookieBanner) {
            cookieBanner.style.display = 'none';
        }
    }
});

document.querySelectorAll(".pay-widget section[data-category]").forEach((sectionElem) => {});

class a {
  static saveOrderDataToStorage() {
    const data = { orders: [], totalPrice: 0 };

    document.querySelectorAll(".pay-widget").forEach(widget => {
      const dateInput = widget.querySelector('[name^="selected-date"]');
      const dateValue = dateInput ? dateInput.value.trim() : "";
      if (dateValue) {
        const order = {
          date: dateValue,
          sniad: [],
          obiad: [],
          kolacja: []
        };
        ["sniad", "obiad", "kolacja"].forEach(category => {
          order[category] = this.getCleanedSectionData(widget, category);
        });
        data.orders.push(order);
      }
    });

    data.totalPrice = this.calculateTotalPrice(data);

    sessionStorage.setItem("orderData", JSON.stringify(data));

    // После сохранения данных даём чуть времени (setTimeout) и обновляем всё:
    setTimeout(() => {
      this.updateTotalInfo();
      this.updateOrderSummary();
      // Вызываем наш новый метод, который берёт уникальные даты и выводит их
      this.updateSelectedDatesUI();
    }, 100);
  }

  // МЕТОД, КОТОРЫЙ ВЫВОДИТ УНИКАЛЬНЫЕ ДАТЫ В #package-count И ИХ КОЛ-ВО В #total-without-discount
  static updateSelectedDatesUI() {
    const orderData = this.getOrderData(); // { orders: [...], totalPrice: ... }
    // Собираем все даты из массива orders и делаем их уникальными
    e = orderData.orders.map(o => o.date);
    // Запоминаем их в глобальный массив e (чтобы функция t() могла их вывести)

    // Обновляем отображение
    t();
  }

  static getCleanedSectionData(widget, category) {
    return this.getSectionData(widget, category)
      .filter(item => item.value.trim() !== "" && parseFloat(item.weight) > 0);
  }

  static getSectionData(widget, category) {
    const data = [];
    widget.querySelectorAll(`.pay-widget__row[data-category="${category}"]:not(.template-row)`)
      .forEach(row => {
        const dishInput = row.querySelector(`input[id^="hidden-${category}"][name^="hidden-${category}-menu-item-pay-full-card"]`);
        if (!dishInput) return;
        const weightInput = row.querySelector(`input[id^="hidden-${category}-data-weight"]`);
        const priceInput = row.querySelector(`input[id^="hidden-${category}-data-price"]`);
        const optionsInput = row.querySelector(`input[name^="selected_${category}_menu_options_id"]`);
        data.push({
          value: dishInput.value.trim() || "Nie wybrano",
          weight: weightInput ? weightInput.value : "0",
          price: priceInput ? parseFloat(priceInput.value) : 0,
          menu_options_id: optionsInput ? optionsInput.value : ""
        });
      });
    return data;
  }

  static calculateTotalPrice(orderData) {
    let total = 0;
    orderData.orders.forEach(order => {
      ["sniad", "obiad", "kolacja"].forEach(category => {
        order[category].forEach(dish => {
          total += parseFloat(dish.price) || 0;
        });
      });
    });
    return total.toFixed(2);
  }

  static getOrderData() {
    return JSON.parse(sessionStorage.getItem("orderData")) || { orders: [] };
  }

  static updateTotalInfo() {
    const container = document.querySelector(".total-info__grid.menu_do_wyboru");
    if (!container) return;

    const orderData = this.getOrderData();
    const dates = orderData.orders.map(o => o.date).join(", ") || "Nie wybrano";
    const totalPrice = this.calculateTotalPrice(orderData);

    container.innerHTML = `
      <p><strong>Razem do zapłaty:</strong> <span style="color: #006A23; font-weight: bold;">${totalPrice} zł</span></p>
      <p><strong>Wybrane daty:</strong> <span>${dates}</span></p>
    `;

    const priceElem = document.getElementById("total-price");
    if (priceElem) {
      priceElem.textContent = `${totalPrice} zł`;
    }
  }

  static updateOrderSummary() {
    const summaryElem = document.getElementById("order-summary");
    if (!summaryElem) return;

    const orderData = this.getOrderData();
    let html = "";
    orderData.orders.forEach(order => {
      if (order.sniad.length || order.obiad.length || order.kolacja.length) {
        html += `
          <div class="order-summary-block">
            <p><strong>Data: ${order.date}</strong></p>
            ${this.renderCleanedCategories("Śniadanie", order.sniad)}
            ${this.renderCleanedCategories("Obiad", order.obiad)}
            ${this.renderCleanedCategories("Kolacja", order.kolacja)}
            <p><strong>Razem za dzień: ${this.calculateDayTotal(order)} zł</strong></p>
          </div>
          <hr>
        `;
      }
    });
    summaryElem.innerHTML = html || "<p>Brak danych o zamówieniu.</p>";
  }

  static renderCleanedCategories(categoryName, dishes) {
    if (!dishes.length) return "";
    let html = `<p><strong>${categoryName}:</strong></p><ul>`;
    dishes.forEach(dish => {
      html += `<li>${dish.value} (${dish.weight}g) - ${dish.price.toFixed(2)} zł</li>`;
    });
    html += "</ul>";
    return html;
  }

  static calculateDayTotal(order) {
    const dishes = order.sniad.concat(order.obiad, order.kolacja);
    return dishes.reduce((sum, dish) => sum + (parseFloat(dish.price) || 0), 0).toFixed(2);
  }

  static resetOrderData() {
    sessionStorage.removeItem("orderData");
    this.updateTotalInfo();
    this.updateOrderSummary();
    this.updateSelectedDatesUI(); // чтобы сразу очистить и поля #package-count, #total-without-discount
  }
}

document.addEventListener("change", event => {
    if (event.target.matches(".pay-widget .air-datepicker input")) {
        const selectedDate = event.target.value;
        if (!e.includes(selectedDate)) {
            e.push(selectedDate); // Добавление даты в массив, если её ещё нет
        }
        a.saveOrderDataToStorage();
        t(); // Обновление отображения
    }
});

document.addEventListener("DOMContentLoaded", () => {
  // Обновление информации заказа и баннера cookies
  a.updateTotalInfo();
  a.updateOrderSummary();

  const cookieBanner = document.querySelector('.cookie-banner');
  if (cookieBanner) {
    const cookieConsent = document.cookie
      .split('; ')
      .find(row => row.startsWith('cookieConsent='));
    cookieBanner.style.display = 'block';
  }

  new s(".pay-widget-container", "#menu-widget-template");
  r.updateAcceptCheckboxState();

  const calendarElem = document.querySelector("#delivery-calendar");
  if (calendarElem) {
    n(calendarElem);
  }

  // Работа с чекбоксом и его меткой
  const acceptLabel = document.getElementById("accept-label");
  const acceptCheckbox = document.getElementById("accept");

  if (acceptLabel && acceptCheckbox) {
    acceptLabel.addEventListener("click", (event) => {
      if (acceptCheckbox.disabled) {
        event.preventDefault();
        // Находим контейнер модального окна по id "calc"
        const modal = document.getElementById("calc");
        if (modal) {
          // Скрываем все внутренние блоки модального окна
          modal.querySelectorAll(".calc-modal__inner, .calc-modal__inner-checkbox")
            .forEach(inner => inner.style.display = "none");

          // Отображаем только блок с ошибкой по чекбоксу
          const errorModalInner = modal.querySelector(".calc-modal__inner-checkbox");
          if (errorModalInner) {
            errorModalInner.style.display = "block";
          }
          // Показываем контейнер модального окна
          modal.style.display = "block";

          // Автоматически закрываем модальное окно через 3 секунды
          setTimeout(() => {
            modal.style.display = "none";
          }, 3000);
        } else {
          // Если модальное окно не найдено, используем alert как резерв
          alert("Najpierw wybierz wszystkie dania на странице");
        }
      }
    });
  }
});


document.querySelectorAll(".steps-line__item").forEach((elem, index) => {
    elem.addEventListener("click", () => {
        if (index === 2) {
            a.updateOrderSummary();
        }
    });
});

const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
        if (mutation.attributeName === "class" && mutation.target.classList.contains("active")) {
            a.updateOrderSummary();
        }
    });
});
document.querySelectorAll(".page-grid__tab").forEach(tab => {
    observer.observe(tab, { attributes: true });
});

document.addEventListener("click", e => {
    const menuItem = e.target.closest(".menu-item.pay-full-card");
    if (!menuItem) return;
    const section = menuItem.closest("section[data-category]");
    if (!section) return;
    const selectedInput = section.querySelector("input.selected-menu-options-id");
    if (!selectedInput) return;
    section.querySelectorAll(".menu-item.pay-full-card").forEach(mi => {
        mi.classList.remove("selected");
    });
    menuItem.classList.add("selected");
    const optionsId = menuItem.getAttribute("data-menu-options-id") || "";
    selectedInput.value = optionsId;
});

class i {
  constructor(category, section) {
    this.category = category;
    this.section = section;
    this.buttons = section.querySelectorAll(".pay-item__btn");
    this.menuItems = section.querySelectorAll(`.menu-items_${category} .menu-item`);
    this.fieldWeight = section.querySelector(`input[id^="hidden-${category}-data-weight"]`);
    this.fieldPrice = section.querySelector(`input[id^="hidden-${category}-data-price"]`);
    this.hiddenDishField = section.querySelector(
      `input[id^="hidden-${category}"][name^="hidden-${category}-menu-item-pay-full-card"]`
    );

    if (this.fieldWeight && this.fieldPrice && this.hiddenDishField && this.buttons.length && this.menuItems.length) {
      this.init();
    }
  }

  init() {
    this.resetState();
    this.buttons.forEach((btn) => {
      btn.addEventListener("click", () => {
        this.selectWeight(btn);
        this.syncOrderManager();
        r.updateAcceptCheckboxState();
      });
    });
    this.menuItems.forEach((menuItem) => {
      menuItem.addEventListener("click", () => {
        this.selectMenuCard(menuItem);
        this.syncOrderManager();
        r.updateAcceptCheckboxState();
      });
    });
  }

  resetState() {
    this.buttons.forEach((btn) => btn.classList.remove("active"));
    this.menuItems.forEach((menuItem) => menuItem.classList.remove("selected"));
    this.fieldWeight.value = "0";
    this.fieldPrice.value = "0";
    this.hiddenDishField.value = "";
  }

  selectWeight(btn) {
    this.buttons.forEach((b) => b.classList.remove("active"));
    btn.classList.add("active");

    const weight = btn.dataset.weight || "0";
    const price = btn.dataset.price || "0";

    this.fieldWeight.value = weight;
    this.fieldPrice.value = price;

    this.syncOrderManager();
  }

  selectMenuCard(menuItem) {
    this.menuItems.forEach((mi) => mi.classList.remove("selected"));
    menuItem.classList.add("selected");

    const dishName = menuItem.querySelector(".menu-card__heading h6")?.textContent || "";
    this.hiddenDishField.value = dishName;

    const menuOptionsField = this.section.querySelector(
      `input.selected-menu-options-id[name^="selected_${this.category}_menu_options_id"]`
    );
    let menuOptionsId = "";
    if (menuOptionsField) {
      menuOptionsId = menuItem.getAttribute("data-menu-options-id") || "";
      menuOptionsField.value = menuOptionsId;
    }

    this.syncOrderManager();
  }

  syncOrderManager() {
    a.updateTotalInfo();
    a.saveOrderDataToStorage();
    a.updateOrderSummary();
  }

  isComplete() {
    const isWeightSet = this.fieldWeight.value.trim() !== "0";
    const isPriceSet = this.fieldPrice.value.trim() !== "0";
    const isMenuSelected = [...this.menuItems].some((item) => item.classList.contains("selected"));
    return isWeightSet && isPriceSet && isMenuSelected;
  }
}





class r {
    static categories = [];

    static addCategory(category, section) {
        const instance = new i(category, section);
        if (instance.fieldWeight && instance.fieldPrice && instance.hiddenDishField) {
            this.categories.push(instance);
        }
    }

    static updateAcceptCheckboxState() {
        const sections = document.querySelectorAll(".pay-widget section[data-category]");
        let allComplete = true;
        sections.forEach((section) => {
            const category = section.dataset.category;
            const instance = this.categories.find(
                (inst) => inst.category === category && inst.section === section
            );
            if (instance) {
                if (!instance.isComplete()) allComplete = false;
            } else {
                this.addCategory(category, section);
                allComplete = false;
            }
        });
        const checkbox = document.getElementById("accept");
        const acceptTerms = document.querySelector(".accept-terms");
        if (checkbox && acceptTerms) {
            if (allComplete) {
                checkbox.removeAttribute("disabled");
                acceptTerms.classList.remove("disabled");
            } else {
                checkbox.setAttribute("disabled", "true");
                acceptTerms.classList.add("disabled");
            }
        }
    }

    static duplicateSection(section) {
        const clone = section.cloneNode(true);
        const uniqueId = `${Date.now()}-${Math.random().toString(36).substring(2, 8)}`;
        clone.querySelectorAll("[id]").forEach((el) => {
            el.id = `${el.id}-${uniqueId}`;
        });
        clone.querySelectorAll("[name]").forEach((el) => {
            const originalName = el.getAttribute("name") || "";
            if (originalName.startsWith("selected-date")) {
                el.setAttribute("name", `selected-date-${uniqueId}`);
            } else {
                el.setAttribute("name", `${originalName}-${uniqueId}`);
            }
        });
        clone.querySelectorAll('input[type="hidden"]').forEach((el) => {
            el.value = "";
        });
        clone.querySelectorAll(".pay-item__btn").forEach((el) => {
            el.classList.remove("active");
        });
        clone.querySelectorAll(".menu-item").forEach((el) => {
            el.classList.remove("selected");
            el.classList.add("disabled");
        });
        clone.classList.add("additional-category");
        section.insertAdjacentElement("afterend", clone);
        const cat = section.dataset.category;
        if (cat) {
            new i(cat, clone);
            this.updateAcceptCheckboxState();
        }
    }

    static initializeAddFoodButtons(container) {
        container.querySelectorAll(".add-food [data-add-category]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const section = container.closest(`section[data-category="${btn.dataset.addCategory}"]`);
                if (section) {
                    this.duplicateSection(section);
                }
            });
        });
    }

    static initializeCalendar(container) {
        const calendarElem = container.querySelector(".calendar-container #delivery-calendar");
        if (calendarElem && !calendarElem.dataset.initialized) {
            n(calendarElem, container);
            calendarElem.dataset.initialized = "true";
        }
    }
}

class s {
    constructor(selectorContainer, selectorTemplate) {
        this.container = document.querySelector(selectorContainer);
        this.template = document.querySelector(selectorTemplate);
        this.blockCounter = 0;
        if (this.container && this.template) {
            this.init();
        }
    }

    init() {
        this.addInitialBlock();

        const moreBtn = document.querySelector(".pay-widget__moreV2");
        if (moreBtn) {
            moreBtn.addEventListener("click", () => {
                this.addNewBlock();
            });
        }

        this.observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(node => {
                        if (
                            node.parentElement === this.container &&
                            node.classList &&
                            node.classList.contains("pay-widget") &&
                            !node.hasAttribute("data-initialized")
                        ) {
                            node.setAttribute("data-initialized", "true");
                            this.initializeComponents(node);
                        }
                    });
                }
            });
        });
        this.observer.observe(this.container, { childList: true, subtree: false });

        const containerElem = document.querySelector(".pay-widget-container");
        if (containerElem) {
            containerElem.addEventListener("click", e => {
                if (e.target.matches(".add-food [data-add-category]")) {
                    const cat = e.target.dataset.addCategory;
                    const section = e.target.closest(`section[data-category="${cat}"]`);
                    if (section) {
                        r.duplicateSection(section);
                    }
                }
            });
        }
    }

    addInitialBlock() {
        const clone = this.template.content.cloneNode(true);
        const payWidget = clone.querySelector(".pay-widget");
        if (payWidget) {
            payWidget.setAttribute("data-initialized", "true");
            this.container.appendChild(clone);
            this.initializeComponents(payWidget);
        }
    }

    addNewBlock() {
        const fragment = this.template.content.cloneNode(true);
        this.blockCounter++;
        this.updateAttributes(fragment, this.blockCounter);
        this.container.appendChild(fragment);
        const newWidget = this.container.lastElementChild;
        this.initializeComponents(newWidget);
        a.saveOrderDataToStorage();
    }

    updateAttributes(fragment, counter) {
        fragment.querySelectorAll("[id]").forEach(el => {
            el.id = `${el.id}-${counter}`;
        });
        fragment.querySelectorAll("[name]").forEach(el => {
            const nameAttr = el.getAttribute("name") || "";
            if (nameAttr.startsWith("selected-date")) {
                el.setAttribute("name", `selected-date-${counter}`);
            } else {
                el.setAttribute("name", `${nameAttr}-${counter}`);
            }
        });
    }

    initializeComponents(widgetEl) {
        if (widgetEl.dataset.initialized === "true") return;
        widgetEl.dataset.initialized = "true";

        widgetEl.querySelectorAll("section[data-category]").forEach(section => {
            const cat = section.dataset.category;
            if (cat) {
                new i(cat, section);
            }
        });

        const cal = widgetEl.querySelector('.calendar-container div[id^="delivery-calendar"]');
        if (cal && !cal.dataset.initialized) {
            n(cal, widgetEl);
            cal.dataset.initialized = "true";
        }

        r.updateAcceptCheckboxState();
    }
}

const n = (e, i = null) => {
  if (!e) return;

  // Текущая дата и "сегодня"
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  // Вычисляем минимальную дату: завтрашний день или послезавтра, если время >= 21:00
  const minDate = new Date(today);
  minDate.setDate(today.getDate() + 2);

  new AirDatepicker(e, {
  locale: {
    days: ["Niedziela", "Poniedziałek", "Wtorek", "Środa", "Czwartek", "Piątek", "Sobota"],
    daysMin: ["Nd", "Pn", "Wt", "Śr", "Czw", "Pt", "So"],  // Используем daysMin вместо daysShort
    months: ["Styczeń", "Luty", "Marzec", "Kwiecień", "Maj", "Czerwiec", "Lipiec", "Sierpień", "Wrzesień", "Październik", "Listopad", "Grudzień"],
    monthsShort: ["Sty", "Lut", "Mar", "Kwi", "Maj", "Cze", "Lip", "Sie", "Wrz", "Paź", "Lis", "Gru"]
  },
  minDate: minDate,
  isDisabled: function(date) {
    return date < minDate || date.getDay() === 0 || date.getDay() === 6;
  },
  multipleDates: false,
  range: false,
  onSelect: ({ date: selectedDate }) => {
    if (selectedDate) {
      const widget = e.closest(".pay-widget");
      const dateInput = widget.querySelector('input[name^="selected-date"]');
      if (dateInput) {
        // Форматируем дату в польском формате
        const formattedDate = selectedDate.toLocaleDateString("pl-PL", {
          day: "2-digit",
          month: "2-digit",
          year: "numeric"
        });
        dateInput.value = formattedDate;
        const dateDisplay = widget.querySelector(".pay-full-card__dates");
        if (dateDisplay) {
          dateDisplay.textContent = formattedDate;
        }
        // Обновляем данные заказа
        const orderData = a.getOrderData();
        let orderEntry = orderData.orders.find(entry => entry.date === formattedDate);
        if (!orderEntry) {
          orderEntry = { date: formattedDate, sniad: [], obiad: [], kolacja: [] };
          orderData.orders.push(orderEntry);
        }
        sessionStorage.setItem("orderData", JSON.stringify(orderData));
        t(); // Обновляем UI с датами
        a.saveOrderDataToStorage();
      }
    }
  }
});

};

// Очистка данных при обновлении страницы
window.addEventListener("beforeunload", () => {
    a.resetOrderData();
});

</script>

<script>
  // Инициализация Stripe с использованием ключа из PHP
  const stripe = Stripe('<?= htmlspecialchars($stripePublishableKey, ENT_QUOTES, 'UTF-8') ?>');

  class App {
    constructor() {
      document.addEventListener('DOMContentLoaded', () => {
        this.initPayButton();
        this.initCardSelection(); // Инициализируем выбор карточек для каждой секции
      });
    }

    // Получает CSRF-токен из метатега
    getCsrfToken = () => {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      return csrfMeta ? csrfMeta.getAttribute('content') : null;
    };

    // Обновляет CSRF-токен в метатеге и скрытом поле
    updateCsrfToken = (newToken) => {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfInput = document.querySelector('input[name="csrf_token"]');

      if (csrfMeta) {
        csrfMeta.setAttribute('content', newToken);
      }
      if (csrfInput) {
        csrfInput.value = newToken;
      }
    };

    // Инициализация кнопки "Płatność"
    initPayButton = () => {
      const payButton = document.getElementById("pay-button");
      if (!payButton) {
        return;
      }

      payButton.addEventListener("click", async (event) => {
        event.preventDefault();

        // Проверяем, отмечен ли чекбокс (если он есть)
        const acceptCheckbox = document.getElementById("accept");
        if (acceptCheckbox && !acceptCheckbox.checked) {
          return;
        }

        // Защита от двойного клика
        if (payButton.disabled) return;
        payButton.disabled = true;

        try {
          await this.sendOrder();
        } catch (error) {
          console.error('Ошибка при отправке заказа:', error);
          alert(error.message);
        }
        payButton.disabled = false; // Разблокируем кнопку после завершения
      });
    };

    // Инициализация выбора карточек для каждой категории
    initCardSelection = () => {
      const categories = ['sniad', 'obiad', 'kolacja'];
      categories.forEach((category) => {
        const section = document.querySelector(`section[data-category="${category}"]`);
        if (!section) return;
        const hiddenField = section.querySelector('input.selected-menu-options-id');
        const cards = section.querySelectorAll('.menu-item.pay-full-card');
        if (!hiddenField) return;
        cards.forEach((card) => {
          card.addEventListener('click', () => {
            // Сбрасываем выделение для всех карточек в данной секции
            cards.forEach((c) => c.classList.remove('selected'));
            card.classList.add('selected');
            // Получаем значение menu_options_id из data-атрибута карточки
            const menuOptionsId = card.getAttribute('data-menu-options-id') || "";
            // Сохраняем выбранное значение в скрытом поле этой секции
            hiddenField.value = menuOptionsId;
          });
        });
      });
    };

    // Преобразует элементы заказа, добавляя поле category и menu_options_id
   mapOrderItems = (items = [], categoryKey) => {
  const categoryMap = {
    sniad: 'śniadanie',
    obiad: 'obiad',
    kolacja: 'kolacja'
  };
  return items.map(item => ({
    dish_name: item.value || item.dish_name || '',
    weight: item.weight,
    price: item.price,
    category: categoryMap[categoryKey] || categoryKey,
    menu_options_id: item.menu_options_id // Берем индивидуальное значение из объекта
  }));
};


    // Собирает данные заказа из формы и OrderManager
    getOrderDetails = () => {
      const csrfToken = this.getCsrfToken();
      if (!csrfToken) {
        return null;
      }

      // Получаем сумму заказа из элемента с id "total-price"
      const totalPriceEl = document.getElementById("total-price");
      const totalPrice = totalPriceEl
        ? totalPriceEl.textContent.trim().replace("zł", "").replace(",", ".")
        : "0";

      // Получаем значение из поля "klatka" для записи в колонку building
      const building = document.getElementById("klatka")?.value.trim() || "";

      // Получаем данные из OrderManager, если он определён
      let orderData = {};
      if (typeof a?.getOrderData === 'function') {
        orderData = a.getOrderData();
      }

      // Функция для извлечения всех значений menu_options_id для конкретной категории
      const getAllMenuOptionsIds = (category) => {
        const selectedFields = document.querySelectorAll(`.selected-menu-options-id[name^="selected_${category}_menu_options_id"]`);
        return Array.from(selectedFields).map(field => field.value);
      };

      // Извлекаем все значения menu_options_id для каждой категории
      const selectedSniadIds = getAllMenuOptionsIds('sniad');
      const selectedObiadIds = getAllMenuOptionsIds('obiad');
      const selectedKolacjaIds = getAllMenuOptionsIds('kolacja');

      let orderDays = [];
if (Array.isArray(orderData.orders) && orderData.orders.length > 0) {
  orderDays = orderData.orders.map(order => {
    let computedDayTotal = 0;
    let items = [];
    const categories = ['sniad', 'obiad', 'kolacja'];
    categories.forEach((cat) => {
      if (Array.isArray(order[cat])) {
        order[cat].forEach(item => {
          computedDayTotal += parseFloat(item.price) || 0;
        });
        items = items.concat(this.mapOrderItems(order[cat], cat));
      }
    });
    let formattedDate = order.date;
    if (order.date.includes('.')) {
      const parts = order.date.split('.');
      if (parts.length === 3) {
        formattedDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
      }
    }
    return {
      delivery_date: formattedDate,
      day_total_price: computedDayTotal.toFixed(2),
      items: items
    };
  });
      }

      if (orderDays.length === 0) {
        return null;
      }

      return {
        csrf_token: csrfToken,
        email: document.getElementById("email")?.value.trim() || "",
        phone: document.getElementById("phone")?.value.trim() || "",
        fullname: document.getElementById("fullname")?.value.trim() || "",
        street: document.getElementById("street")?.value.trim() || "",
        house_number: document.getElementById("house_number")?.value.trim() || "",
        floor: document.getElementById("floor")?.value.trim() || "",
        apartment: document.getElementById("apartment")?.value.trim() || "",
        building: building,
        gate_code: document.getElementById("gate_code")?.value.trim() || "",
        notes: document.getElementById("notes")?.value.trim() || "",
        total_price: totalPrice,
        order_days: orderDays  // Передаем подробные данные по каждому дню заказа
      };
    };

    // Обновляет CSRF-токен перед отправкой запроса
    refreshCSRFToken = async () => {
      try {
        const response = await fetch('/csrf_token.php', { credentials: 'same-origin' });
        const data = await response.json();
        if (data.csrf_token) {
          this.updateCsrfToken(data.csrf_token);
        }
      } catch (error) {
        console.error('Ошибка при обновлении CSRF-токена:', error);
      }
    };

    // Отправляет заказ: сначала сохраняем заказ, затем создаём сессию оплаты
   sendOrder = async () => {
  // Обновляем CSRF-токен перед отправкой
  await this.refreshCSRFToken();

  // Собираем все данные заказа
  const requestData = this.getOrderDetails();
  if (!requestData) return;

  try {
    // --------------------------
    // Шаг 1: Сохранение заказа
    // --------------------------
    // Добавляем action = "save_order"
    requestData.action = 'save_order';

    // Отправляем заказ на сервер
    const result = await this.postData('/payments/process_order_do_wyboru.php', requestData);

    // Проверяем ответ
    if (result.status !== 'success') {
      throw new Error(result.message);
    }

    // Если сервер вернул новый CSRF-токен — обновляем на клиенте
    if (result.new_csrf_token) {
      this.updateCsrfToken(result.new_csrf_token);
    }

    // --------------------------
    // Шаг 2: Создание Stripe-сессии
    // --------------------------
    // Формируем объект для второго запроса
    const paymentData = {
      action: 'create_session',            // ВАЖНО: ставим "create_session"
      order_id: result.order_id,           // Используем order_id из ответа предыдущего шага
      total_price: requestData.total_price,
      email: requestData.email,
      csrf_token: this.getCsrfToken(),
      order_days: requestData.order_days   // Если нужно, передаем детальную информацию о заказе
    };

    // Отправляем запрос на тот же файл
    const paymentResult = await this.postData('/payments/payment_summary_do_wyboru.php', paymentData);

    // Если всё успешно, перенаправляем на Stripe
    if (paymentResult.status === 'success' && paymentResult.id) {
      stripe.redirectToCheckout({ sessionId: paymentResult.id });
    } else {
      throw new Error(paymentResult.message || 'Ошибка при создании сессии оплаты');
    }

  } catch (error) {
    console.error('Ошибка в sendOrder:', error);
    alert(error.message);
  }
};


    // Отправляет POST-запрос с данными в формате JSON (с передачей cookies)
    postData = (url, data) => {
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      });
    };
  }

  new App();
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

<template id=menu-widget-template>
<div class=pay-widget>
<div class="pay-widget__remove remove-package">
Usuń ten pakiet
<span>
<svg width=12 height=15 viewBox="0 0 12 15" fill=none xmlns=http://www.w3.org/2000/svg>
<path d="M11.5584 5.31135L9.57664 4.5823L10.3975 2.82228C10.5487 2.49825 10.3754 2.1268 10.0106 1.99256L4.72575 0.0485014C4.36094 -0.0856999 3.94265 0.0681447 3.79154 0.392175L2.97064 2.1522L0.988868 1.42315C0.624013 1.28895 0.205769 1.4428 0.0546121 1.76683C-0.0964972 2.09086 0.0767303 2.4623 0.441585 2.59655L3.084 3.5686L7.69011 5.263H1.70985C1.31497 5.263 0.994827 5.54732 0.994827 5.89802V14.365C0.994827 14.7157 1.31497 15 1.70985 15H10.2902C10.6851 15 11.0052 14.7157 11.0052 14.365V6.48254L11.0112 6.48474C11.1007 6.51764 11.1933 6.53326 11.2845 6.53326C11.5651 6.53326 11.8314 6.38564 11.9454 6.14107C12.0965 5.81704 11.9233 5.44555 11.5584 5.31135Z" fill=white />
</svg>
</span>
</div>
<div class=pay-widget__main>
<div class=pay-widget__row>
<div class=pay-widget__title>Menu do wyboru:</div>
<section class=kalendarz>
<div class=calendar-container>
<div class=select-date>Wybierz datę:</div>
<div id=delivery-calendar></div>
<input type=hidden id=selected-date name=selected-date>
</div>
</section>
<div class="pay-widget__row hidden second">
<div class=pay-widget__heading-row>
<div class=pay-widget__heading>Wybrane Menu:</div>
</div>
<div class=pay-widget__inner>
<div class=pay-full-card>
<div class=pay-full-card__data>
<div class=pay-full-card__title></div>
<div class=pay-full-card__list>
<div class="pay-full-card__item qn">
<b>Ilość opakowań:</b>
<div class=pay-full-card__qn>1</div>
</div>
<div class=pay-full-card__item>
<b>Data:</b>
<div class=pay-full-card__dates>nie wybrano</div>
</div>
<div class="pay-full-card__item full-sniad">
<b><strong>Śniadania:</strong></b>
<div id=hidden-sniad name=hidden-sniad-menu-item-pay-full-card>
<span></span>
<div id=hidden-sniad-data-weight name=hidden-sniad-data-weight><span></span></div>
</div>
</div>
<div class="pay-full-card__item full-obiad">
<b><strong>Obiad:</strong></b>
<div id=hidden-obiad name=hidden-obiad-menu-item-pay-full-card>
<span></span>
<div id=hidden-obiad-data-weight name=hidden-obiad-data-weight><span></span></div>
</div>
</div>
<div class="pay-full-card__item full-kolacja">
<b><strong>Kolacja:</strong></b>
<div id=hidden-kolacja name=hidden-kolacja-menu-item-pay-full-card>
<span></span>
<div id=hidden-kolacja-data-weight name=hidden-kolacja-data-weight><span></span></div>
</div>
</div>
<div class="pay-full-card__item cost">
<b>Cena za pakiet:</b>
<div class=price>
<span></span>
<div class=price__old></div>
</div>
</div>
</div>
</div>
</div>
<div class=pay-widget__aside>
<input class=pay-widget__calendar>
</div>
</div>
</div>
<div class=menu_do_wyboru>
<section class="zl_sniad pay-widget__row" data-category=sniad data-clonable=true>
<div class="pay-widget__remove remove-category">Usuń tę Śniadania<span>
<svg width=12 height=15 viewBox="0 0 12 15" fill=none xmlns=http://www.w3.org/2000/svg>
<path d="M11.5584 5.31135L9.57664 4.5823L10.3975 2.82228C10.5487 2.49825 10.3754 2.1268 10.0106 1.99256L4.72575 0.0485014C4.36094 -0.0856999 3.94265 0.0681447 3.79154 0.392175L2.97064 2.1522L0.988868 1.42315C0.624013 1.28895 0.205769 1.4428 0.0546121 1.76683C-0.0964972 2.09086 0.0767303 2.4623 0.441585 2.59655L3.084 3.5686L7.69011 5.263H1.70985C1.31497 5.263 0.994827 5.54732 0.994827 5.89802V14.365C0.994827 14.7157 1.31497 15 1.70985 15H10.2902C10.6851 15 11.0052 14.7157 11.0052 14.365V6.48254L11.0112 6.48474C11.1007 6.51764 11.1933 6.53326 11.2845 6.53326C11.5651 6.53326 11.8314 6.38564 11.9454 6.14107C12.0965 5.81704 11.9233 5.44555 11.5584 5.31135Z" fill=white />
</svg>
</span>
</div>
<h2 class=pay-widget__heading>Śniadania</h2>
<input type=hidden id=hidden-sniad-data-price name=hidden-sniad-data-price data-reset=true>
<input type=hidden id=hidden-sniad-data-weight name=hidden-sniad-data-weight data-reset=true>
<input type=hidden id=hidden-sniad name=hidden-sniad-menu-item-pay-full-card data-reset=true>
<input type=hidden class=selected-menu-options-id name=selected_sniad_menu_options_id>
<div class=zl_sniad-zl>
<div class=select-weight>Zważ wagę</div>
<div class=waga_sniad>
<?php if (!empty($groupedData['Sniadanie'])): ?>
<?php foreach ($groupedData['Sniadanie'] as $price): ?>
<div class="waga-button_sniad pay-item__btn" data-weight="<?= htmlspecialchars($price['weight']) ?>" data-price="<?= htmlspecialchars($price['price']) ?>" data-reset=true>
<?= htmlspecialchars($price['weight']) ?>g - <?= htmlspecialchars($price['price']) ?> zł
</div>
<?php endforeach; ?>
<?php else: ?>
<div>Nie znaleziono цен dla Śniadanie.</div>
<?php endif; ?>
</div>
</div>
<div class=pay-wInfo>Wybierz śniadanie:</div>
<div class="menu-items_sniad pay-widget__grid menu__grid">
<?php if (!empty($menuItemsSniadanie)): ?>
<?php $firstFourSniadanie = getFirstFourMenuItems($menuItemsSniadanie); ?>
<?php foreach ($firstFourSniadanie as $item): ?>
<div class="menu-item pay-full-card" data-menu-options-id="<?= htmlspecialchars($item['menu_options_id'], ENT_QUOTES, 'UTF-8') ?>">
<div class=menu-card__photo> <img src="<?= htmlspecialchars($item['dish_image']); ?>" alt="<?= htmlspecialchars($item['dish_name']); ?>"></div>
<div class="pay-full-card__data menu-card__data">
<div class=menu-card__heading><h6><?= htmlspecialchars($item['dish_name']); ?></h6></div>
<div class=menu-card__row><div class=menu-card__title>Opis:</div><div class=menu-card__text>
<?= htmlspecialchars($item['dish_description']); ?></div></div>
<div class=menu-card__row><div class=menu-card__title>Skladniki:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_ingredients']); ?></div></div>
<div class=menu-card__row><div class=menu-card__title>Allergens:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_allergens']); ?></div></div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<p>Данных по категории "Śniadania" не найдено.</p>
<?php endif; ?>
<div class=add-food style="grid-column: 1 / -1;
  justify-self: center;" >
<div class=add-food_sniad data-add-category=sniad>
Dodaj dodatkowe Śniadanie
</div>
</div>
</div>
</section>
<section class="zl_obiad pay-widget__row" data-category=obiad data-clonable=true>
<div class="pay-widget__remove remove-category">Usuń tę Obiad<span>
<svg width=12 height=15 viewBox="0 0 12 15" fill=none xmlns=http://www.w3.org/2000/svg>
<path d="M11.5584 5.31135L9.57664 4.5823L10.3975 2.82228C10.5487 2.49825 10.3754 2.1268 10.0106 1.99256L4.72575 0.0485014C4.36094 -0.0856999 3.94265 0.0681447 3.79154 0.392175L2.97064 2.1522L0.988868 1.42315C0.624013 1.28895 0.205769 1.4428 0.0546121 1.76683C-0.0964972 2.09086 0.0767303 2.4623 0.441585 2.59655L3.084 3.5686L7.69011 5.263H1.70985C1.31497 5.263 0.994827 5.54732 0.994827 5.89802V14.365C0.994827 14.7157 1.31497 15 1.70985 15H10.2902C10.6851 15 11.0052 14.7157 11.0052 14.365V6.48254L11.0112 6.48474C11.1007 6.51764 11.1933 6.53326 11.2845 6.53326C11.5651 6.53326 11.8314 6.38564 11.9454 6.14107C12.0965 5.81704 11.9233 5.44555 11.5584 5.31135Z" fill=white />
</svg>
</span>
</div>
<h2 class=pay-widget__heading>Obiad</h2>
<input type=hidden id=hidden-obiad-data-price name=hidden-obiad-data-price data-reset=true>
<input type=hidden id=hidden-obiad-data-weight name=hidden-obiad-data-weight data-reset=true>
<input type=hidden id=hidden-obiad name=hidden-obiad-menu-item-pay-full-card data-reset=true>
<input type=hidden class=selected-menu-options-id name=selected_obiad_menu_options_id>
<div class=zl_obiad-zl>
<div class=select-weight>Zważ wagę</div>
<div class=waga_obiad>
<?php if (!empty($groupedData['Obiad'])): ?>
<?php foreach ($groupedData['Obiad'] as $price): ?>
<a class="waga-button_obiad pay-item__btn" data-weight="<?= htmlspecialchars($price['weight']) ?>" data-price="<?= htmlspecialchars($price['price']) ?>" data-reset=true>
<?= htmlspecialchars($price['weight']) ?>g - <?= htmlspecialchars($price['price']) ?> zł
</a>
<?php endforeach; ?>
<?php else: ?>
<div>Nie znaleziono цен для Obiad.</div>
<?php endif; ?>
</div>
</div>
<div class=pay-wInfo>Wybierz Obiad:</div>
<div class="menu-items_obiad pay-widget__grid menu__grid">
<?php if (!empty($menuItemsObiad)): ?>
<?php foreach ($menuItemsObiad as $item): ?>
<div class="menu-item pay-full-card" data-menu-options-id="<?= htmlspecialchars($item['menu_options_id'], ENT_QUOTES, 'UTF-8') ?>">
<div class=menu-card__photo> <img src="<?= htmlspecialchars($item['dish_image']); ?>" alt="<?= htmlspecialchars($item['dish_name']); ?>"> </div>
<div class="pay-full-card__data menu-card__data">
<div class=menu-card__heading><h6><?= htmlspecialchars($item['dish_name']); ?></h6></div>
<div class=menu-card__row><div class=menu-card__title>Opis:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_description']); ?></div></div>
<div class=menu-card__row><div class=menu-card__title>Skladniki:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_ingredients']); ?></div></div>
<div class=menu-card__row><div class=menu-card__title>Allergens:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_allergens']); ?></div></div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<p>Данных по категории "Obiad" не найдено.</p>
<?php endif; ?>
<div class=add-food style="grid-column: 1 / -1;
  justify-self: center;">
<div class=add-food_obiad data-add-category=obiad>
Dodaj dodatkowe Obiad
</div>
</div>
</div>
</section>
<section class="zl_kolacja pay-widget__row" data-category=kolacja data-clonable=true>
<div class="pay-widget__remove remove-category">Usuń tę Kolacja<span>
<svg width=12 height=15 viewBox="0 0 12 15" fill=none xmlns=http://www.w3.org/2000/svg>
<path d="M11.5584 5.31135L9.57664 4.5823L10.3975 2.82228C10.5487 2.49825 10.3754 2.1268 10.0106 1.99256L4.72575 0.0485014C4.36094 -0.0856999 3.94265 0.0681447 3.79154 0.392175L2.97064 2.1522L0.988868 1.42315C0.624013 1.28895 0.205769 1.4428 0.0546121 1.76683C-0.0964972 2.09086 0.0767303 2.4623 0.441585 2.59655L3.084 3.5686L7.69011 5.263H1.70985C1.31497 5.263 0.994827 5.54732 0.994827 5.89802V14.365C0.994827 14.7157 1.31497 15 1.70985 15H10.2902C10.6851 15 11.0052 14.7157 11.0052 14.365V6.48254L11.0112 6.48474C11.1007 6.51764 11.1933 6.53326 11.2845 6.53326C11.5651 6.53326 11.8314 6.38564 11.9454 6.14107C12.0965 5.81704 11.9233 5.44555 11.5584 5.31135Z" fill=white />
</svg>
</span>
</div>
<h2 class=pay-widget__heading>Kolacja</h2>
<input type=hidden id=hidden-kolacja-data-price name=hidden-kolacja-data-price data-reset=true>
<input type=hidden id=hidden-kolacja-data-weight name=hidden-kolacja-data-weight data-reset=true>
<input type=hidden id=hidden-kolacja name=hidden-kolacja-menu-item-pay-full-card data-reset=true>
<input type=hidden class=selected-menu-options-id name=selected_kolacja_menu_options_id>
<div class=zl_kolacja-zl>
<div class=select-weight>Zważ wagę</div>
<div class=waga_kolacja>
<?php if (!empty($groupedData['Kolacja'])): ?>
<?php foreach ($groupedData['Kolacja'] as $price): ?>
<a class="waga-button_kolacja pay-item__btn" data-weight="<?= htmlspecialchars($price['weight']) ?>" data-price="<?= htmlspecialchars($price['price']) ?>" data-reset=true>
<?= htmlspecialchars($price['weight']) ?>g - <?= htmlspecialchars($price['price']) ?> zł
</a>
<?php endforeach; ?>
<?php else: ?>
<div>Nie znalezionо цен для Kolacja.</div>
<?php endif; ?>
</div>
</div>
<div class=pay-wInfo>Wybierz Kolacja:</div>
<div class="menu-items_kolacja pay-widget__grid menu__grid">
<?php if (!empty($menuItemsKolacja)): ?>
<?php foreach ($menuItemsKolacja as $item): ?>
<div class="menu-item pay-full-card" data-menu-options-id="<?= htmlspecialchars($item['menu_options_id'], ENT_QUOTES, 'UTF-8') ?>">
<div class=menu-card__photo><img src="<?= htmlspecialchars($item['dish_image']); ?>" alt="<?= htmlspecialchars($item['dish_name']); ?>"></div>
<div class="pay-full-card__data menu-card__data">
<div class=menu-card__heading><h6><?= htmlspecialchars($item['dish_name']); ?></h6></div>
<div class=menu-card__row><div class=menu-card__title>Opis:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_description']); ?></div></div>
<div class=menu-card__row><div class=menu-card__title>Skladniki:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_ingredients']); ?></div></div>
<div class=menu-card__row><div class=menu-card__title>Allergens:</div><div class=menu-card__text><?= htmlspecialchars($item['dish_allergens']); ?></div></div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<p>Данных по категории "Kolacja" не найдено.</p>
<?php endif; ?>
<div class=add-food style="grid-column: 1 / -1;
  justify-self: center;">
<div class=add-food_kolacja data-add-category=kolacja>
Dodaj dodatkowe Kolacja
</div>
</div>
</div>
</section></div>


</div>
<div class="pay-widget__row hidden second">
<div class=pay-widget__heading-row>
<div class=pay-widget__heading>Wybrane Menu:</div>
</div>
</div>
</div>
</div>
</template>

<div id="error-message" style="
  display: none;
  position: fixed;
  top: 20px;
  right: 20px;
  background-color: #f8d7da;
  color: #721c24;
  padding: 10px 20px;
  border: 1px solid #f5c6cb;
  border-radius: 5px;
  z-index: 1000;">
</div>

</body>
</html>
 