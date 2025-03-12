document.addEventListener('DOMContentLoaded', function () {
    /**
     * Функция для настройки автозаполнения
     * @param {string} inputId - ID поля ввода
     * @param {string} sourceUrl - URL для получения данных
     * @param {function} mapResults - Функция для преобразования данных в формат, понятный autocomplete
     */
    function setupDynamicAutocomplete(inputId, sourceUrl, mapResults) {
        const inputField = document.getElementById(inputId);
        if (inputField) {
            $(inputField).autocomplete({
                source: function (request, response) {
                    $.ajax({
                        url: sourceUrl, // URL для запроса
                        method: 'GET',
                        data: { term: request.term }, // Отправляем введенный текст
                        success: function (data) {
                            try {
                                console.log("Response from server:", data); // Логируем ответ для отладки
                                
                                // Проверяем, если данные уже JSON, парсим их
                                const parsedData = typeof data === 'string' ? JSON.parse(data) : data;

                                // Проверяем, является ли результат массивом
                                if (Array.isArray(parsedData)) {
                                    const results = mapResults(parsedData);
                                    response(results); // Передаем данные в autocomplete
                                } else {
                                    console.error("Ожидался массив, получен другой формат данных:", parsedData);
                                    response([]);
                                }
                            } catch (e) {
                                console.error("Ошибка при парсинге данных для автозаполнения:", e);
                                response([]);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error(`Ошибка при загрузке данных для автозаполнения: ${status} - ${error}`);
                            response([]);
                        }
                    });
                },
                minLength: 1, // Минимальное количество символов для активации
                select: function (event, ui) {
                    // Действия при выборе элемента из списка
                    console.log(`Выбрано: ${ui.item.label} (${ui.item.value})`);
                }
            });
        } else {
            console.warn(`Поле с ID "${inputId}" не найдено.`);
        }
    }

    /**
     * Универсальная функция для маппинга данных
     * @param {Array} data - Данные из запроса
     * @param {string} field - Поле, по которому нужно маппить данные
     * @returns {Array} - Сформированный массив для autocomplete
     */
    function mapAutocompleteData(data, field) {
        return data
            .filter(item => item[field]) // Фильтруем только те элементы, у которых есть нужное поле
            .map(item => ({
                label: item[field], // Значение для отображения
                value: item[field]  // Значение для вставки в поле
            }));
    }

    // Настраиваем автозаполнение для email
    setupDynamicAutocomplete(
        'email-filter', // ID поля ввода
        '/admin/fetch_orders_summary.php', // URL для запроса данных
        function (data) {
            return mapAutocompleteData(data, 'email');
        }
    );

    // Настраиваем автозаполнение для адреса
    setupDynamicAutocomplete(
        'address-filter', // ID поля ввода
        '/admin/fetch_orders_summary.php', // URL для запроса данных
        function (data) {
            return mapAutocompleteData(data, 'address');
        }
    );
});
