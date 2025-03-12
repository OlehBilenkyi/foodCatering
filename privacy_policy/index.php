<?php
// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log'); // Путь уже был корректным

// Начинаем сессию для защиты от CSRF атак
session_start();

// Генерация CSRF-токена для защиты формы
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="pl">
    
<?php
    $pageTitle = "Regulamin - Twoja Restauracja";
    $metaDescription = "Regulamin zamówień w naszej restauracji. Zapoznaj się z zasadami i warunkami, które obowiązują podczas składania zamówień.";
    $metaKeywords = "regulamin, restauracja, zasady zamówień, dostawa";
    $metaAuthor = "Twoja Restauracja";

    include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>
<link rel="icon" type="image/x-icon" href="/favicon.ico">

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; ?> <!-- Подключаем шапку -->

    <main class="page-main">
    <div class="container">
      <h1 class="page-title">Polityka prywatności</h1>

      <div class="page-grid revert">
        <div class="page-grid__main">
          <article class="article">
            <p><span>Strona internetowa <a target="_blank" href="https://www.foodcasepl.com/">https://www.foodcasepl.com/</a> jest własnością FOODCASE ANDRII NOS, która jest administratorem Twoich danych osobowych.</span></p>
            <p><span>Przyjęliśmy tę Politykę prywatności, która określa, w jaki sposób przetwarzamy informacje zbierane przez <a target="_blank" href="https://www.foodcasepl.com/">https://www.foodcasepl.com/</a>, a także wyjaśnia, dlaczego musimy gromadzić pewne dane osobowe o Tobie. Dlatego musisz przeczytać tę Politykę prywatności przed skorzystaniem ze strony internetowej <a target="_blank" href="https://www.foodcasepl.com/">https://www.foodcasepl.com/</a>.</span></p>
            <p>Dbamy o Twoje dane osobowe i zobowiązujemy się do zapewnienia ich poufności oraz bezpieczeństwa.
            </p>

            <h2 id="1">Informacje osobiste, które zbieramy:</h2>
            <p><span>Kiedy odwiedzasz stronę <a target="_blank" href="https://www.foodcasepl.com/">https://www.foodcasepl.com/</a>, automatycznie zbieramy pewne informacje o Twoim urządzeniu, w tym informacje o przeglądarce internetowej, adresie IP, strefie czasowej oraz niektórych plikach cookie zainstalowanych na Twoim urządzeniu. Ponadto, gdy przeglądasz Stronę, zbieramy informacje o poszczególnych stronach internetowych lub produktach, które oglądasz, jakie strony internetowe lub zapytania w wyszukiwarce skierowały Cię na Stronę oraz jak wchodzisz w interakcję ze Stroną. Te automatycznie zebrane informacje nazywamy „Informacjami o urządzeniu”. Ponadto możemy zbierać dane osobowe, które nam przekazujesz (w tym między innymi imię, nazwisko, adres, informacje o płatności itp.) podczas rejestracji, aby móc zrealizować umowę.</span></p>

            <h2 id="2">Dlaczego przetwarzamy Twoje dane?</h2>
            <p>Naszym głównym priorytetem jest bezpieczeństwo danych klientów i dlatego możemy przetwarzać jedynie minimalne dane użytkowników, tylko tyle, ile jest absolutnie konieczne do utrzymania strony internetowej. Informacje zbierane automatycznie są wykorzystywane wyłącznie do identyfikacji potencjalnych przypadków nadużyć oraz ustalenia danych statystycznych dotyczących korzystania z witryny internetowej. Te dane statystyczne nie są w inny sposób agregowane w taki sposób, aby można było zidentyfikować konkretnego użytkownika systemu.</p>
            <p><span>Możesz odwiedzić stronę internetową bez ujawniania nam, kim jesteś, ani bez podawania żadnych informacji, które mogłyby Cię zidentyfikować jako konkretnego, możliwego do zidentyfikowania użytkownika. Jeśli jednak chcesz skorzystać z niektórych funkcji strony internetowej lub chcesz otrzymywać nasz newsletter lub podać inne szczegóły, wypełniając formularz, możesz przekazać nam swoje dane osobowe, takie jak adres e-mail, imię, nazwisko, miasto zamieszkania, organizacja, numer telefonu. Możesz zdecydować się na nieprzekazywanie nam swoich danych osobowych, ale wtedy możesz nie być w stanie skorzystać z niektórych funkcji strony internetowej. Na przykład nie będziesz w stanie otrzymywać naszego newslettera lub skontaktować się z nami bezpośrednio ze strony internetowej. Użytkownicy, którzy nie są pewni, jakie informacje są obowiązkowe, mogą skontaktować się z nami pod adresem <a target="_blank" href="mailto:biuro@foodcasepl.com">biuro@foodcasepl.com</a>.</span></p>
            
            <h2 id="3">Twoje prawa:</h2>
            <p>Jeśli jesteś rezydentem Europy, masz następujące prawa dotyczące Twoich danych osobowych:
            </p>
            <ul><li><p ><span >Prawo do informacji.</span></p></li><li><p  ><span >Prawo dostępu.</span></p></li><li><p  ><span >Prawo do sprostowania.</span></p></li><li><p  ><span >Prawo do usunięcia.</span></p></li><li><p  ><span >Prawo do ograniczenia przetwarzania.</span></p></li><li><p  ><span >Prawo do przenoszenia danych.</span></p></li><li><p  ><span >Prawo do sprzeciwu.</span></p></li><li><p  ><span >Prawa związane z automatycznym podejmowaniem decyzji i profilowaniem.</span></p></li></ul>
            <p>Jeśli chcesz skorzystać z tego prawa, skontaktuj się z nami za pomocą poniższych danych kontaktowych.

            </p>
            <p>
              Ponadto, jeśli jesteś rezydentem Europy, zaznaczamy, że przetwarzamy Twoje informacje w celu realizacji umów, które możemy mieć z Tobą (na przykład, jeśli składasz zamówienie przez Stronę), lub w inny sposób w celu realizacji naszych uzasadnionych interesów biznesowych wymienionych powyżej. Ponadto pamiętaj, że Twoje informacje mogą być przekazywane poza Europę, w tym do Kanady i Stanów Zjednoczonych.
            </p>
            <h2 id="4">Linki do innych stron internetowych:</h2>
            <p>Nasza strona internetowa może zawierać linki do innych stron internetowych, które nie są naszą własnością ani nie są przez nas kontrolowane. Prosimy pamiętać, że nie ponosimy odpowiedzialności za praktyki prywatności tych innych stron internetowych lub stron trzecich. Zachęcamy do zachowania ostrożności po opuszczeniu naszej strony internetowej i do zapoznania się z politykami prywatności każdej strony internetowej, która może zbierać dane osobowe.
            </p>
            <h2 id="5">Bezpieczeństwo informacji:
            </h2>
            <p>Chronimy informacje, które nam przekazujesz, na serwerach komputerowych w kontrolowanym, bezpiecznym środowisku, chronionym przed nieautoryzowanym dostępem, użyciem lub ujawnieniem. Stosujemy odpowiednie środki administracyjne, techniczne i fizyczne, aby chronić przed nieautoryzowanym dostępem, użyciem, modyfikacją i ujawnieniem danych osobowych, które znajdują się pod naszym nadzorem i kontrolą. Niemniej jednak, żadna transmisja danych przez Internet ani sieć bezprzewodową nie może być całkowicie gwarantowana.
            </p>
            <h2 id="6">
              Ujawnienie prawne:
            </h2>
            <p>Ujawnimy wszelkie informacje, które zbieramy, używamy lub otrzymujemy, jeśli będzie to wymagane lub dozwolone przez prawo, na przykład w celu spełnienia wezwania do sądu lub podobnego procesu prawnego, a także gdy w dobrej wierze uznamy, że ujawnienie jest konieczne do ochrony naszych praw, ochrony Twojego bezpieczeństwa lub bezpieczeństwa innych osób, prowadzenia śledztwa w sprawie oszustwa lub udzielenia odpowiedzi na żądanie rządu.
            </p>
            <h2 id="7">Dane kontaktowe:</h2>
            <p>Jeśli chcesz skontaktować się z nami, aby dowiedzieć się więcej o tej Polityce, lub skontaktować się z nami w sprawie wszelkich kwestii związanych z prawami indywidualnymi i Twoimi danymi osobowymi, możesz wysłać e-mail na adres <a href="mailto:biuro@foodcasepl.com.">biuro@foodcasepl.com.</a>
            </p>

            <h2 id="8">Informacja o plikach cookie:</h2>
<p>Na naszej stronie internetowej używamy plików cookie, aby zapewnić prawidłowe działanie naszych usług oraz dostarczyć użytkownikom jak najlepsze doświadczenia. Pliki cookie to niewielkie pliki tekstowe zapisywane na Twoim urządzeniu. Używamy ich do zarządzania sesją, przetwarzania płatności, usprawnienia procesu zamówienia, wysyłania powiadomień o statusie zamówienia oraz do analizy ruchu na stronie w celu poprawy naszych usług.</p>
<p>Możesz wyrazić zgodę na użycie wszystkich plików cookie lub dostosować swoje preferencje. Używamy następujących kategorii plików cookie:
    <ul>
        <li><strong>Niezbędne pliki cookie:</strong> są wymagane do działania strony i nie wymagają zgody.</li>
        <li><strong>Funkcjonalne pliki cookie:</strong> pomagają w poprawie funkcjonalności i doświadczenia użytkownika na stronie.</li>
        <li><strong>Analityczne pliki cookie:</strong> są wykorzystywane do analizy ruchu na stronie i poprawy naszych usług.</li>
        <li><strong>Marketingowe pliki cookie:</strong> pomagają w personalizacji reklam zgodnie z Twoimi zainteresowaniami.</li>
    </ul>
</p>
<p>Możesz zarządzać swoimi preferencjami dotyczącymi plików cookie za pośrednictwem specjalnego menu ustawień, a także w każdej chwili zmienić swoje preferencje poprzez ustawienia przeglądarki. Więcej informacji o naszych praktykach znajdziesz w pełnej Polityce Prywatności powyżej.</p>
            
          </article>
        </div>
        <div class="page-grid__aside hidden-mob">
          <ul class="page-nav">
            <li class="page-nav__item">
              <a href="#1" class="page-nav__link">Informacje osobiste, które zbieramy</a>
            </li>
            <li class="page-nav__item">
              <a href="#2" class="page-nav__link">Dlaczego przetwarzamy Twoje dane?</a>
            </li>
            <li class="page-nav__item">
              <a href="#3" class="page-nav__link">Twoje prawa:</a>
            </li>
            <li class="page-nav__item">
              <a href="#4" class="page-nav__link">Linki do innych stron internetowych</a>
            </li>
            <li class="page-nav__item">
              <a href="#5" class="page-nav__link">Bezpieczeństwo informacji</a>
            </li>
            <li class="page-nav__item">
              <a href="#6" class="page-nav__link">Ujawnienie prawne</a>
            </li>
            <li class="page-nav__item">
              <a href="#7" class="page-nav__link">Dane kontaktowe</a>
            </li>
            <li class="page-nav__item">
              <a href="#8" class="page-nav__link">Informacja o plikach cookie</a>
            </li>
          </ul>
        </div>
      </div>

    </div>
  </main>


    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?> <!-- Подключаем футер -->

</body>
</html>
