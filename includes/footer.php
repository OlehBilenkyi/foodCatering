<footer class="footer" itemscope itemtype="http://schema.org/Organization">
  <div class="container footer__container">
    <div class="footer__left">
      <div class="footer__data">
        <img src="../assets/img/logo-w.png" alt="" class="footer__logo" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
        <div class="footer__slogan">Nasza firma zapewnia wygodną dostawę gotowych dań do Twojego domu</div>
      </div>
      <div class="footer__email">
        Email:
        <a href="mailto:biuro@foodcasepl.com">biuro@foodcasepl.com</a>
      </div>
    </div>
    <div class="footer__right">
      <div class="footer__col">
        <div class="footer__title">Nawigacja:</div>
        <ul class="footer__nav">
          <li class="footer__item"><a href="/index.php" class="footer__link">Strona główna</a></li>

          <li class="footer__item">
            <a href="<?= ($_SERVER['PHP_SELF'] == '/index.php') ? '#menu' : '/index.php#menu' ?>" class="footer__link js-scroll-link">Zobacz menu</a>
          </li>

          <li class="footer__item"><a href="/prices/" class="footer__link">Zobacz ceny</a></li>
       
          <li class="footer__item"><a href="/privacy_policy/" class="footer__link">Polityka prywatności</a></li>
          <li class="footer__item"><a href="/regulamin/" class="footer__link">Regulamin</a></li>
        </ul>
      </div>

      <div class="footer__col">
        <div class="footer__title">Metody płatności:</div>
        <div class="footer__partners">
          <div class="footer__partner">
            <img src="../assets/img/footer/1.png" alt="Płatność BLIK">
          </div>
          <div class="footer__partner">
            <img src="../assets/img/footer/2.png" alt="Płatność kartą Visa">
          </div>

          <br>

          <!-- <div class="footer__partner">
            <img src="/img/mastercard.svg" alt="Płatność kartą Mastercard">
          </div> -->

        </div>
      </div>

      <div class="footer__col">
        <div class="footer__title">Kontakt:</div>
        <div class="footer__socials">
          <a href="maito:biuro@foodcasepl.com" class="footer__social" rel="noopener noreferrer" itemprop="email">
            <svg width="45" height="45" viewBox="0 0 45 45" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="22.5" cy="22.5" r="22.5" fill="white"/>
              <path d="M12 16.7501L19.75 22.5625C21.0834 23.5625 22.9166 23.5625 24.25 22.5625L32 16.75" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M30.75 14.25H13.25C11.8693 14.25 10.75 15.3693 10.75 16.75V29.25C10.75 30.6307 11.8693 31.75 13.25 31.75H30.75C32.1307 31.75 33.25 30.6307 33.25 29.25V16.75C33.25 15.3693 32.1307 14.25 30.75 14.25Z" stroke="black" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </a>
          <a href="https://www.instagram.com/foodcase_krakow" target="_blank" rel="noopener noreferrer" itemprop="sameAs" class="footer__social">
            <svg width="45" height="45" viewBox="0 0 45 45" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="22.5" cy="22.5" r="22.5" fill="white"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M23 30.5C27.1421 30.5 30.5 27.1421 30.5 23C30.5 18.8579 27.1421 15.5 23 15.5C18.8579 15.5 15.5 18.8579 15.5 23C15.5 27.1421 18.8579 30.5 23 30.5ZM23 28C25.7614 28 28 25.7614 28 23C28 20.2386 25.7614 18 23 18C20.2386 18 18 20.2386 18 23C18 25.7614 20.2386 28 23 28Z" fill="#0F0F0F"/>
              <path d="M30.5 14.25C29.8096 14.25 29.25 14.8097 29.25 15.5C29.25 16.1904 29.8096 16.75 30.5 16.75C31.1904 16.75 31.75 16.1904 31.75 15.5C31.75 14.8097 31.1904 14.25 30.5 14.25Z" fill="#0F0F0F"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M10.0674 13.3451C9.25 14.9494 9.25 17.0496 9.25 21.25V24.75C9.25 28.9504 9.25 31.0506 10.0674 32.6549C10.7865 34.0661 11.9339 35.2135 13.3451 35.9325C14.9494 36.75 17.0496 36.75 21.25 36.75H24.75C28.9504 36.75 31.0506 36.75 32.6549 35.9325C34.0661 35.2135 35.2135 34.0661 35.9325 32.6549C36.75 31.0506 36.75 28.9504 36.75 24.75V21.25C36.75 17.0496 36.75 14.9494 35.9325 13.3451C35.2135 11.9339 34.0661 10.7865 32.6549 10.0674C31.0506 9.25 28.9504 9.25 24.75 9.25H21.25C17.0496 9.25 14.9494 9.25 13.3451 10.0674C11.9339 10.7865 10.7865 11.9339 10.0674 13.3451ZM24.75 11.75H21.25C19.1086 11.75 17.6528 11.752 16.5276 11.8439C15.4316 11.9334 14.871 12.0957 14.4801 12.295C13.5392 12.7743 12.7743 13.5392 12.295 14.4801C12.0957 14.871 11.9334 15.4316 11.8439 16.5276C11.752 17.6528 11.75 19.1086 11.75 21.25V24.75C11.75 26.8915 11.752 28.3471 11.8439 29.4724C11.9334 30.5685 12.0957 31.129 12.295 31.52C12.7743 32.4608 13.5392 33.2256 14.4801 33.705C14.871 33.9042 15.4316 34.0666 16.5276 34.1561C17.6528 34.248 19.1086 34.25 21.25 34.25H24.75C26.8915 34.25 28.3471 34.248 29.4724 34.1561C30.5685 34.0666 31.129 33.9042 31.52 33.705C32.4608 33.2256 33.2256 32.4608 33.705 31.52C33.9042 31.129 34.0666 30.5685 34.1561 29.4724C34.248 28.3471 34.25 26.8915 34.25 24.75V21.25C34.25 19.1086 34.248 17.6528 34.1561 16.5276C34.0666 15.4316 33.9042 14.871 33.705 14.4801C33.2256 13.5392 32.4608 12.7743 31.52 12.295C31.129 12.0957 30.5685 11.9334 29.4724 11.8439C28.3471 11.752 26.8915 11.75 24.75 11.75Z" fill="#0F0F0F"/>
            </svg>
          </a>
        </div>
        
        
      </div>

    </div>
  </div>
  <div class="footer__copyright">&copy; <?= date('Y') ?> <span itemprop="name">FoodCase</span>. Wszelkie prawa zastrzeżone.</div>
</footer>



<script src="../assets/js/global.js?v=3" defer></script>



