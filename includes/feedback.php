<?php
// Инициализация сессии и безопасность
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding("UTF-8");

// Генерация nonce для CSP
$nonce = bin2hex(random_bytes(16));

// CSRF-токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSP для безопасности
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; connect-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");

// Фильтрация IP-адреса (учет прокси)
$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim(end($ipList)); // Берем последний IP в цепочке
}

// Ограничение частоты отправки запросов (Rate limiting)
$time = time();
$timeWindow = 60; // Временное окно (в секундах)
$maxAttempts = 5; // Макс. число попыток за $timeWindow

if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = [];
}

// Очистка старых записей
$_SESSION['rate_limit'] = array_filter($_SESSION['rate_limit'], fn($t) => $t > ($time - $timeWindow));

// Добавление текущей попытки
$_SESSION['rate_limit'][] = $time;

// Проверка лимита
if (count($_SESSION['rate_limit']) > $maxAttempts) {
    die('Слишком много попыток отправки формы. Попробуйте позже.');
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/global.css">
    <title>Formularz opinii</title>
    
    
</head>
<body>
<div class="feedback">
    <div class="container feedback__container">
        <h2 class="feedback__title">Twoje uwagi dotyczące diety</h2>
        <div class="feedback__caption" style="text-align: center; margin-top: -20px; margin-bottom: 50px;">
            Podaj swoje dane do kontaktu zwrotnego.
        </div>
        <div class="feedback__grid">
            <form class="feedback__form js-form" id="feedbackForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="form_timestamp" value="<?= time(); ?>">
                <div class="feedback__field">
                    <label class="feedback__label">Twoje imię</label>
                    <input type="text" name="name" class="feedback__input">
                </div>
                <div class="feedback__field">
                    <label class="feedback__label">Twój email</label>
                    <input type="email" name="email" class="feedback__input">
                    <div class="feedback__field-error" style="display: none; color: red; margin-top: 5px;">
                        Proszę wprowadzić poprawny adres email
                    </div>
                </div>
                <div class="feedback__field">
                    <label class="feedback__label">Twój komentarz</label>
                    <textarea name="message" class="feedback__textarea" minlength="15" placeholder="Minimalna liczba znaków to 15" required></textarea>
                    <div class="feedback__field-error" style="display: none; color: red; margin-top: 5px;">
                        Minimalna liczba znaków to 15
                    </div>
                </div>
                <button class="btn feedback__button" type="submit">Wyślij</button>
            </form>
            <img src="/assets/img/feedback/1.png" alt="Feedback Image" class="feedback__image">
        </div>
    </div>
</div>

<div id="confirmationModal" class="modal-feedback">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <div class="modal-message">
            <div class="modal-icon">&#10004;</div>
            <p>Twoja wiadomość została pomyślnie wysłana. Dziękujemy za Twoją opinię!</p>
        </div>
    </div>
</div>

<script nonce="<?= $nonce ?>">
class FeedbackFormHandler {
    constructor(formSelector, modalSelector) {
        this.form = document.querySelector(formSelector);
        this.modal = document.querySelector(modalSelector);
        this.submitButton = this.form?.querySelector('button[type="submit"]');
        this.messageField = this.form?.querySelector('[name="message"]');
        this.emailField = this.form?.querySelector('[name="email"]');
        this.messageError = this.messageField?.nextElementSibling; // Poprawny selektor
        this.emailError = this.emailField?.nextElementSibling; // Poprawny selektor
        this.closeButton = this.modal?.querySelector('.close-btn');

        if (!this.form) {
            // console.error('Formularz nie został znaleziony.');
            return;
        }

        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        if (this.closeButton) {
            this.closeButton.addEventListener('click', () => this.closeModal());
        }
        window.addEventListener('click', (event) => {
            if (event.target === this.modal) {
                this.closeModal();
            }
        });
    }

    closeModal() {
        if (this.modal) this.modal.style.display = 'none';
    }

    validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    validateForm() {
        let isValid = true;

        if (!this.messageField?.value.trim() || this.messageField.value.trim().length < 15) {
            this.messageField?.classList.add('error');
            if (this.messageError) {
                this.messageError.style.display = 'block';
                this.messageError.textContent = 'Minimalna liczba znaków to 15';
            }
            isValid = false;
        } else {
            this.messageField?.classList.remove('error');
            if (this.messageError) this.messageError.style.display = 'none';
        }

        if (this.emailField?.value.trim() && !this.validateEmail(this.emailField.value.trim())) {
            this.emailField?.classList.add('error');
            if (this.emailError) {
                this.emailError.style.display = 'block';
                this.emailError.textContent = 'Proszę wprowadzić poprawny adres email';
            }
            isValid = false;
        } else {
            this.emailField?.classList.remove('error');
            if (this.emailError) this.emailError.style.display = 'none';
        }

        return isValid;
    }

    async handleSubmit(event) {
        event.preventDefault();

        if (!this.validateForm()) return;

        const formTimestamp = parseInt(this.form.querySelector('input[name="form_timestamp"]')?.value, 10);
        const currentTime = Math.floor(Date.now() / 1000);
        if (currentTime - formTimestamp < 5) {
            // alert('Proszę poczekać kilka sekund przed wysłaniem formularza.');
            return;
        }

        const formData = new FormData(this.form);
        if (this.submitButton) this.submitButton.disabled = true;

        try {
            const response = await fetch('/admin/send_recomends.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();
            if (data.success) {
                this.form.reset();
                if (this.modal) this.modal.style.display = 'block';
                setTimeout(() => {
                    if (this.submitButton) this.submitButton.disabled = false;
                }, 30000);
            } else {
                // alert('Błąd: ' + (data.errors ? data.errors.join(', ') : 'Wystąpił nieznany błąd'));
                if (this.submitButton) this.submitButton.disabled = false;
            }
        } catch (error) {
            // console.error('Błąd przy wysyłaniu formularza:', error);
            // alert('Wystąpił błąd podczas przetwarzania Twojej wiadomości. Proszę spróbować ponownie później.');
            if (this.submitButton) this.submitButton.disabled = false;
        }
    }
}

// Inicjalizacja
document.addEventListener('DOMContentLoaded', () => {
    new FeedbackFormHandler('#feedbackForm', '#confirmationModal');
});
</script>


<style>
    .modal-feedback {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 400px;
        text-align: center;
        border-radius: 10px;
    }

    .close-btn {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close-btn:hover,
    .close-btn:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    .modal-icon {
        font-size: 50px;
        color: green;
        margin-bottom: 15px;
    }

    .modal-message p {
        font-size: 18px;
    }
    .feedback{
        border-radius: 0;
    }
    .feedback__field textarea.error,
    .feedback__field input.error {
        border-color: red;
    }

    .feedback__grid {
        display: flex;
        align-items: flex-start;
        gap: 20px;
    }

    .feedback__image {
        max-width: 300px;
        height: auto;
    }
</style>
</body>
</html>
