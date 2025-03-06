document.addEventListener('DOMContentLoaded', function () {
    // Проверка на нахождение на второй странице
    const urlParams = new URLSearchParams(window.location.search);
    const page = parseInt(urlParams.get('page'), 10) || 1;

    if (page !== 2) {
        alert('Генерация наклеек доступна только на второй странице.');
        window.location.href = "/admin/orders_summary.php";
        return;
    }

    // Получаем данные из localStorage
    const packageStickerDataJSON = localStorage.getItem('packageStickerData');
    if (!packageStickerDataJSON) {
        alert('Данные для генерации наклеек отсутствуют.');
        console.error('packageStickerData отсутствует в localStorage');
        window.location.href = "/admin/orders_summary.php";
        return;
    }

    const packageStickerData = JSON.parse(packageStickerDataJSON);

    if (!packageStickerData || !packageStickerData.packages || packageStickerData.packages.length === 0) {
        alert('Нет данных для генерации наклеек.');
        console.error('packageStickerData.packages отсутствует или пуст');
        window.location.href = "/admin/orders_summary.php";
        return;
    }

    const container = document.getElementById('sticker-container');

    // Уникальные данные для генерации (исключаем дубли)
    const uniquePackages = [];
    const uniqueAddresses = new Set();
    packageStickerData.packages.forEach((packageInfo) => {
        const uniqueKey = `${packageInfo.package_type}-${packageInfo.address}`;
        if (!uniqueAddresses.has(uniqueKey)) {
            uniquePackages.push(packageInfo);
            uniqueAddresses.add(uniqueKey);
        }
    });

    console.log("Итоговые уникальные данные для генерации:", uniquePackages);

    // Генерация наклеек
    uniquePackages.forEach(({ package_type, address }, index) => {
        const stickerElement = document.createElement('div');
        stickerElement.classList.add('sticker-paket');

        stickerElement.innerHTML = `
            <div class="sticker-paket-header">
                <h1 class="title-text">Menu: Standardowe ${package_type} калорий</h1>
                <img class="logo-image" src="/assets/img/sticker_container.png" alt="Logo" />
            </div>
            <div class="sticker-paket-info">
                <div class="info-block">
                    <span class="info-label">Adres:</span>
                    <span class="info-value">${address}</span>
                </div>
                <div class="info-block">
                    <span class="info-label">Data:</span>
                    <span class="info-value">${packageStickerData.delivery_date}</span>
                </div>
            </div>
        `;

        container.appendChild(stickerElement);
    });

    console.log("Генерация наклеек завершена. Контейнер обновлен.");

    // Очистка данных из localStorage после печати
    const printButton = document.getElementById('print-button');
    if (printButton) {
        printButton.addEventListener('click', () => {
            localStorage.removeItem('packageStickerData');
            alert('Данные очищены после печати.');
            window.print(); // Добавлено для автоматической печати
        });
    }
});
