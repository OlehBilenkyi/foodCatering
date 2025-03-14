<?php
session_start(); // Убедитесь, что эта строка находится в самом начале

// Проверяем, авторизован ли пользователь как администратор
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/admin.php'); // Перенаправляем на страницу авторизации, если пользователь не авторизован
    exit();
}

// Включаем логирование ошибок для записи в файл
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL);
?>
<?php
$pageTitle = "Наші Ціни - Ресторан";
$metaDescription = "Перегляньте наші ціни на смачні та здорові страви!";
$metaKeywords = "ресторан, ціни, їжа, обіди, вечеря";
$metaAuthor = "Ваш Ресторан";

include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>

<?php 
include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; // Подключаем шапку
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Адміністративна панель для керування сайтом FoodCase">
    <meta name="author" content="FoodCase">
    <title>Панель адміністратора</title>
    <link rel="stylesheet" href="/assets/stylescss/admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.9/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body>
<div class="admin-panel-container">
    <h2 class="admin-panel-header">Ласкаво просимо до Адмін Панелі</h2>
    <div class="admin-panel-buttons">
        <div class="buttons-group">
        <a href="/admin/admin_menu.php" class="btn btn-primary btn-admin">Управління тижневим меню</a>
        <a href="/admin/admin_price.php" class="btn btn-primary btn-admin">Управління цінами Меню Стандартне</a>
         <a href="/admin/orders_summary.php" class="btn btn-primary btn-admin">Таблиця замовлень Меню Стандартне</a> <!-- Новая кнопка -->
        </div>
        
        <div class="buttons-group">
        <a href="/admin/edit_menu_do_wyboru.php" class="btn btn-primary btn-admin">Управління Меню до Вибору</a>
       <a href="/admin/edit_prices_menu_do_wyboru.php" class="btn btn-primary btn-admin">Управління цінами  Меню до Вибору</a>
        <a href="/admin/admin_orders_menu_do_wyboru.php" class="btn btn-primary btn-admin">Таблиця замовлень Меню до Вибору</a> <!-- Новая кнопка -->
        </div>
    </div>
    <a href="/admin/logout.php" class="btn btn-danger btn-admin">Вийти</a>
</div>

<!-- Графік відвідувань -->
<div class="chart-container" style="position: relative; height:60vh; width:100%; margin: 0 auto;">
    <canvas id="visitsChart"></canvas>
</div>

<!-- Деталі відвідувань -->
<div class="visit-details">
    <h3>Деталі відвідувань:</h3>
        <div class="filter-container">
        <input type="text" id="filterInput" placeholder="Введіть значення для фільтрації">
        <button id="filterButton" class="btn btn-primary">Фільтрувати</button>
    </div>
    <button id="deleteAllButton" class="btn btn-danger">Видалити всі вибрані</button>
    <div class="pagination-container">
        <div id="paginationTop" class="pagination-buttons"></div>
    </div>
    <table id="visitTable" class="table">
        
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAll"></th>
                <th data-sort="visit_date">Дата <span class="sort-indicator"></span></th>
                <th data-sort="visit_count">Кількість відвідувань <span class="sort-indicator"></span></th>
                <th data-sort="ip_address">IP-адреса <span class="sort-indicator"></span></th>
                <th data-sort="country">Країна <span class="sort-indicator"></span></th>
                <th data-sort="city">Місто <span class="sort-indicator"></span></th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <!-- Дані будуть додані динамічно -->
        </tbody>
    </table>
    <div class="pagination-container">
        <div id="paginationBottom" class="pagination-buttons"></div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    class VisitManager {
        constructor() {
            this.data = {};
            this.sortOrder = {};
            this.currentPage = 1;
            this.rowsPerPage = 30;
            this.currentDate = new Date(); // текущая дата для фильтрации по месяцам
            this.filteredData = []; // Данные для фильтрации
        }

        // Инициализация
        async init() {
            try {
                await this.fetchVisitsData(this.currentDate);
                this.filteredData = [...this.data.visits]; // Изначально все данные доступны для отображения
                this.renderTable(this.currentPage);
                this.setupSorting();
                this.setupFiltering();
                this.setupSelectAll();
                await this.fetchOrderData(this.currentDate);
            } catch (error) {
                console.error('Помилка завантаження даних:', error);
            }
        }

        // Получение данных о посещениях
        async fetchVisitsData(date) {
            const year = date.getFullYear();
            const month = date.getMonth() + 1; // месяцы нумеруются с 0

            const response = await fetch(`/admin/get_visits_data.php?year=${year}&month=${month}`, {
                credentials: 'include'
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Помилка сервера:', errorText);
                throw new Error('Помилка отримання даних з сервера');
            }

            const responseData = await response.json();

            // Фильтруем записи только для посетителей из Польши
            this.data.visits = responseData.visits.filter(visit => visit.country === 'Poland');

            if (!this.data.visits || !Array.isArray(this.data.visits) || this.data.visits.length === 0) {
                throw new Error('Немає даних для відображення');
            }

            // Сортируем данные по убыванию даты посещения
            this.data.visits.sort((a, b) => new Date(b.visit_date) - new Date(a.visit_date));
        }

        // Рендеринг таблицы
        renderTable(page = 1) {
            const tableBody = document.querySelector("#visitTable tbody");
            if (!tableBody) {
                console.error("Таблиця з id #visitTable не знайдена на сторінці.");
                return;
            }
            tableBody.innerHTML = '';

            const start = (page - 1) * this.rowsPerPage;
            const end = Math.min(start + this.rowsPerPage, this.filteredData.length);
            const currentPageVisits = this.filteredData.slice(start, end);

            currentPageVisits.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="checkbox" class="select-record" data-ip="${item.ip_address}" data-date="${item.visit_date}"></td>
                    <td>${item.visit_date || 'Невідомо'}</td>
                    <td>${item.visit_count || 0}</td>
                    <td>${item.ip_address || 'Невідомо'}</td>
                    <td>${item.country || 'Невідомо'}</td>
                    <td>${item.city || 'Невідомо'}</td>
                    <td>
                        <button class="delete-button" 
                                data-ip="${item.ip_address}" 
                                data-date="${item.visit_date}">
                            <i class="fas fa-trash-alt"></i> Видалити
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            this.setupPagination(page);
        }

        // Настройка выделения всех записей
        setupSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const recordCheckboxes = document.querySelectorAll('.select-record');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function () {
                    recordCheckboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }
        }

        // Настройка пагинации
        setupPagination(currentPage) {
            const paginationTop = document.getElementById('paginationTop');
            const paginationBottom = document.getElementById('paginationBottom');
            paginationTop.innerHTML = '';
            paginationBottom.innerHTML = '';

            const paginationHTML = this.generatePaginationHTML(currentPage);
            paginationTop.innerHTML = paginationHTML;
            paginationBottom.innerHTML = paginationHTML;

            document.querySelectorAll('.pagination-button').forEach(button => {
                button.addEventListener('click', () => {
                    const page = parseInt(button.getAttribute('data-page'));
                    this.currentPage = page;
                    this.renderTable(page);
                });
            });
        }

        // Генерация HTML для пагинации
        generatePaginationHTML(currentPage) {
            let html = '';
            const totalPages = Math.ceil(this.filteredData.length / this.rowsPerPage);

            if (currentPage > 1) {
                html += `<button class="pagination-button" data-page="${currentPage - 1}">&lt;</button>`;
            }

            for (let i = 1; i <= totalPages && i <= 10; i++) {
                if (i === currentPage) {
                    html += `<button class="pagination-button current-page" data-page="${i}">${i}</button>`;
                } else {
                    html += `<button class="pagination-button" data-page="${i}">${i}</button>`;
                }
            }

            if (totalPages > 10) {
                html += `<span>...</span>`;
            }

            if (currentPage < totalPages) {
                html += `<button class="pagination-button" data-page="${currentPage + 1}">&gt;</button>`;
            }

            return html;
        }

        // Настройка сортировки таблицы
        setupSorting() {
            document.querySelectorAll('#visitTable th[data-sort]').forEach(header => {
                const sortField = header.getAttribute('data-sort');
                let span = document.createElement('span');
                span.className = 'sort-indicator';
                header.appendChild(span);

                header.addEventListener('click', () => {
                    let order = this.sortOrder[sortField] || 'asc';

                    // Сортируем только текущую страницу
                    this.filteredData.sort((a, b) => {
                        if (a[sortField] < b[sortField]) return order === 'asc' ? -1 : 1;
                        if (a[sortField] > b[sortField]) return order === 'asc' ? 1 : -1;
                        return 0;
                    });

                    this.sortOrder[sortField] = order === 'asc' ? 'desc' : 'asc';

                    // Обновляем индикатор сортировки
                    document.querySelectorAll('.sort-indicator').forEach(span => span.textContent = '');
                    const indicator = order === 'asc' ? '▲' : '▼';
                    header.querySelector('.sort-indicator').textContent = indicator;

                    // Обновляем данные в таблице после сортировки
                    this.renderTable(this.currentPage);
                });
            });
        }

        // Настройка фильтрации таблицы
        setupFiltering() {
            const filterButton = document.getElementById('filterButton');
            const filterInput = document.getElementById('filterInput');

            if (filterButton && filterInput) {
                filterButton.addEventListener('click', () => {
                    const searchTerm = filterInput.value.toLowerCase();

                    this.filteredData = this.data.visits.filter(visit =>
                        visit.ip_address.toLowerCase().includes(searchTerm) ||
                        visit.country.toLowerCase().includes(searchTerm) ||
                        visit.city.toLowerCase().includes(searchTerm)
                    );

                    this.renderTable(1);
                });
            }
        }

        // Получение данных о заказах и построение графика
        async fetchOrderData(date) {
            try {
                const year = date.getFullYear();
                const month = date.getMonth() + 1;

                const response = await fetch(`/admin/get_order_data.php?year=${year}&month=${month}`, {
                    credentials: 'include'
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Помилка сервера:', errorText);
                    throw new Error('Помилка отримання даних з сервера');
                }

                const orderData = await response.json();
                if (orderData.error) {
                    throw new Error(orderData.error);
                }

                this.createChart(orderData);
            } catch (error) {
                console.error('Помилка завантаження даних замовлень:', error);
            }
        }

        // Создание графика посещений и заказов
        createChart(orderData) {
            const labels = [];
            const visitCounts = [];
            const orderCounts = [];
            const currentYear = this.currentDate.getFullYear();
            const currentMonth = this.currentDate.getMonth();

            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            for (let day = 1; day <= daysInMonth; day++) {
                const dateString = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                labels.push(dateString);

                const visitData = this.data.visits.find(v => v.visit_date === dateString);
                visitCounts.push(visitData ? visitData.visit_count : 0);

                const orderDataEntry = orderData.orders.find(order => order.order_date === dateString);
                orderCounts.push(orderDataEntry ? orderDataEntry.order_count : 0);
            }

            const ctx = document.getElementById('visitsChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Відвідування',
                            data: visitCounts,
                            backgroundColor: 'rgba(75, 192, 192, 0.5)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Кількість замовлень',
                            data: orderCounts,
                            backgroundColor: 'rgba(255, 99, 132, 0.5)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            type: 'line',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }

    const visitManager = new VisitManager();
    visitManager.init();
});
</script>

<style>

@media (max-width: 1024px) {
    .admin-panel-buttons {
        flex-direction: column;
        align-items: center;
    }

    .buttons-group {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .form-group, .filter-group {
        display: block;
        width: 100%;
        margin-right: 0;
        text-align: center;
    }

    .pagination-buttons {
        flex-wrap: wrap;
        gap: 5px;
    }

    .pagination-buttons button {
        padding: 8px 12px;
        font-size: 14px;
    }

    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

    table th, table td {
        padding: 8px;
    }

    .chart-pagination button {
        padding: 8px 12px;
        font-size: 14px;
    }
}

@media (max-width: 768px) {
    .admin-panel-buttons {
        gap: 10px;
    }

    .buttons-group {
        width: 100%;
    }

    .pagination-buttons {
        justify-content: center;
    }

    .pagination-buttons button {
        font-size: 12px;
        padding: 6px 10px;
    }

    table th, table td {
        padding: 6px;
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .admin-panel-buttons {
        flex-direction: column;
        gap: 8px;
    }

    .pagination-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }

    .pagination-buttons button {
        font-size: 12px;
        padding: 5px 8px;
    }

    table th, table td {
        padding: 5px;
        font-size: 12px;
    }

    .chart-pagination {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .chart-pagination button {
        font-size: 12px;
        padding: 6px 10px;
    }
}


body{font-family:Arial,sans-serif}.admin-panel-buttons{display:flex;flex-wrap:wrap;gap:20px;justify-content:center;margin-bottom:15px}.buttons-group{display:flex;gap:15px}.buttons-group:first-of-type{border-bottom:2px solid #ddd;padding-bottom:20px;margin-bottom:0}.form-group,.filter-group{margin-bottom:15px;display:inline-block;width:auto;margin-right:20px}.pagination-buttons{display:flex;justify-content:center;align-items:center;margin:10px 0}.pagination-buttons button{margin:0 5px;padding:5px 10px;border:1px solid #ccc;background-color:#f0f0f0;cursor:pointer;transition:background-color 0.3s}.pagination-buttons button:hover{background-color:#e0e0e0}.pagination-buttons .current-page{background-color:#007bff;color:#fff}table{width:100%;border-collapse:collapse}table th,table td{padding:10px;border:1px solid #ddd;text-align:center}table th{cursor:pointer}.sort-indicator{margin-left:5px}.chart-pagination{text-align:center;margin-top:10px}.chart-pagination button{margin:5px}</style>

</body>
</html>
