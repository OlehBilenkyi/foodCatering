// sticker_generation.js
document.addEventListener('DOMContentLoaded', function () {
    // Получаем данные из localStorage
    const response = JSON.parse(localStorage.getItem('stickerData'));

    // Логируем данные, загруженные из localStorage
    console.log("Загруженные данные из localStorage для наклеек:", response);

    // Если данных нет, возвращаемся назад на страницу заказов
    if (!response || !response.packages || response.packages.length === 0) {
        alert('Данные для генерации наклеек отсутствуют.');
        window.location.href = "/admin/orders_summary.php";
        return;
    }

    // Найти контейнер для наклеек
    const stickersContainer = document.getElementById('stickers-container');

    // Генерация HTML для каждой наклейки
    response.packages.forEach(function (packageInfo) {
        packageInfo.dishes.forEach(function (dish) {
            // Создаем новый элемент для наклейки
            const stickerElement = document.createElement('div');
            stickerElement.classList.add('sticker');

            stickerElement.innerHTML = `
                <div class="title">
                    <h1 class="title__text">${dish.description}</h1>
                    <div class="callorii">${packageInfo.package}</div>
                </div>
                <div class="composition">
                    <div class="composition__text">
                        Skladniki: ${dish.ingredients}
                    </div>
                    <div>
                        <img src="/assets/img/sticker.png" alt="img" class="img" />
                    </div>
                </div>
                <div class="desc">
                    <h2 class="desc__title">Wartosc odzywcza w porcji:</h2>
                    <div class="portion">
                        <div class="energy">Wartosc energetyczna: ${dish.energy} kcal</div>
                        <div class="fat">Tluszcz: ${dish.fat} g</div>
                        <div class="carbohydrates">Weglowodany: ${dish.carbohydrates} g</div>
                        <div class="protein">Bialko: ${dish.protein} g</div>
                        <div class="sol">Sol: ${dish.salt} g</div>
                    </div>
                    <div class="desc__dowm">
                        Wartosc odzywcza jest przyblizona и может się różnić.
                    </div>
                </div>
                <div class="footer">
                    <div class="footer__text">
                        Przechowywać w temperaturze od 0°C до 8°C. Po otrzymaniu spożyć в ciągu 24 godzin.
                    </div>
                    <div class="data">Data produkcji: ${response.delivery_date}</div>
                </div>
            `;

            // Добавить созданную наклейку в контейнер
            stickersContainer.appendChild(stickerElement);
        });
    });

    // Очистка данных из localStorage после генерации наклеек
    localStorage.removeItem('stickerData');
});
