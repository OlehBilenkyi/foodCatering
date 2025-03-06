<?php
// Инициализация сессии и безопасность
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$nonce = bin2hex(random_bytes(16));
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; connect-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");

// Защита от слишком частых запросов
$ip = $_SERVER['REMOTE_ADDR'];
$time = time();

if (isset($_SESSION['last_submission_time']) && isset($_SESSION['submission_count'])) {
    if ($ip === $_SESSION['ip'] && ($time - $_SESSION['last_submission_time']) < 60) {
        $_SESSION['submission_count']++;
    } else {
        $_SESSION['submission_count'] = 1;
    }
    $_SESSION['last_submission_time'] = $time;

    if ($_SESSION['submission_count'] > 5) {
        die('Zbyt wiele prób wysyłania formularza. Proszę spróbować ponownie później.');
    }
} else {
    $_SESSION['submission_count'] = 1;
    $_SESSION['last_submission_time'] = $time;
    $_SESSION['ip'] = $ip;
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
                    <textarea name="message" class="feedback__textarea" minlength="15" required></textarea>
                    <div class="feedback__field-error" style="display: none; color: red; margin-top: 5px;">
                        Minimalna liczba znaków to 15
                    </div>
                </div>
                <button class="btn feedback__button" type="submit">Wysłać</button>
            </form>
            <img src="/assets/img/feedback/1.png" alt="Feedback Image" class="feedback__image">
        </div>
    </div>
</div>

<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <div class="modal-message">
            <div class="modal-icon">&#10004;</div>
            <p>Twoja wiadomość została pomyślnie wysłana. Dziękujemy za Twoją opinię!</p>
        </div>
    </div>
</div>

<script nonce="<?= $nonce ?>">
    document.addEventListener('DOMContentLoaded', function () {
        const feedbackForm = document.getElementById('feedbackForm');
        const nameField = feedbackForm.querySelector('input[name="name"]');
        const emailField = feedbackForm.querySelector('input[name="email"]');
        const messageField = feedbackForm.querySelector('textarea[name="message"]');
        const emailError = feedbackForm.querySelector('input[name="email"] + .feedback__field-error');
        const messageError = feedbackForm.querySelector('textarea[name="message"] + .feedback__field-error');
        const confirmationModal = document.getElementById('confirmationModal');
        const closeModal = document.querySelector('.close-btn');

        feedbackForm.addEventListener('submit', function (e) {
            e.preventDefault();

            let isValid = true;

            // Проверка на минимальную длину сообщения (обязательно для заполнения)
            if (!messageField.value.trim() || messageField.value.trim().length < 15) {
                messageField.classList.add('error');
                messageError.style.display = 'block';
                messageError.textContent = 'Minimalna liczba znaków to 15';
                isValid = false;
            } else {
                messageField.classList.remove('error');
                messageError.style.display = 'none';
            }

            // Проверка на корректность email (не обязательно, но если заполнен, должен быть правильным)
            if (emailField.value.trim() && !validateEmail(emailField.value.trim())) {
                emailField.classList.add('error');
                emailError.style.display = 'block';
                emailError.textContent = 'Proszę wprowadzić poprawny adres email';
                isValid = false;
            } else {
                emailField.classList.remove('error');
                emailError.style.display = 'none';
            }

            if (!isValid) {
                return;
            }

            // Проверка времени заполнения формы (антибот защита)
            const formTimestamp = parseInt(feedbackForm.querySelector('input[name="form_timestamp"]').value, 10);
            const currentTime = Math.floor(Date.now() / 1000);
            if (currentTime - formTimestamp < 5) {
                alert('Proszę poczekać kilka sekund przed wysłaniem formularza.');
                return;
            }

            // AJAX запрос
            const formData = new FormData(feedbackForm);

            fetch('/admin/send_recomends.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Показать модальное окно об успешной отправке
                    confirmationModal.style.display = 'block';
                } else {
                    alert('Błąd: ' + (data.errors ? data.errors.join(', ') : 'Wystąpił nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Błąd przy wysyłaniu formularza:', error);
                alert('Wystąpił błąd podczas przetwarzania Twojej wiadomości. Proszę spróbować ponownie później.');
            });
        });

        // Закрытие модального окна
        closeModal.addEventListener('click', function () {
            confirmationModal.style.display = 'none';
        });

        window.onclick = function(event) {
            if (event.target === confirmationModal) {
                confirmationModal.style.display = 'none';
            }
        };

        // Функция для проверки правильности email
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    });
</script>

<style>
    .modal {
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
