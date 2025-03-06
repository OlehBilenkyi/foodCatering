document.addEventListener('DOMContentLoaded', function () {
    let isGenerating = false; // Флаг для предотвращения многократного нажатия

    // Обработчик для кнопки "Генерация Наклеек"
    $('#generate-stickers').on('click', async function (e) {
        e.preventDefault();

        if (isGenerating) {
            console.log('Процесс генерации уже выполняется. Пожалуйста, подождите.');
            return; // Если генерация уже идет, прерываем выполнение
        }

        isGenerating = true; // Устанавливаем флаг, что генерация началась

        // Удаляем старые данные из localStorage перед записью новых данных
        if (localStorage.getItem('stickerData')) {
            console.log('Удаляем старые данные из localStorage перед записью новых');
            localStorage.removeItem('stickerData');
        }

        // Получаем значения недели и даты доставки
        const deliveryDate = $('#delivery_date').val();
        const weekNumber = $('#week_number').val();

        // Определяем день недели из даты доставки (1 - понедельник, 7 - воскресенье)
        const dayNumber = new Date(deliveryDate).getDay() || 7;

        // Проверяем, что все параметры выбраны
        if (!deliveryDate || !weekNumber) {
            console.error('Пожалуйста, выберите неделю и дату доставки.');
            isGenerating = false; // Сбрасываем флаг
            return;
        }

        console.log("Получены параметры для запроса:", { deliveryDate, weekNumber, dayNumber });

        // Отправляем AJAX-запрос для получения данных о пакетах и блюдах
        $.ajax({
            url: `/admin/get_sticker_data.php?delivery_date=${deliveryDate}&week_number=${weekNumber}&day_number=${dayNumber}&timestamp=${new Date().getTime()}`,
            type: 'GET',
            dataType: 'json',
            success: async function (response) {
                if (response.error) {
                    console.error('Ошибка получения данных: ' + response.details);
                    isGenerating = false; // Сбрасываем флаг
                    return;
                }

                console.log("Полученные данные для генерации наклеек:", response);

                // Подготовка данных для наклеек на основе ответа от сервера
                const stickerData = {
                    packages: [],
                    delivery_date: deliveryDate
                };

                response.packages.forEach(function (packageInfo) {
                    const packageCalories = packageInfo.package;
                    const totalQuantity = packageInfo.total_quantity;

                    // Генерируем наклейки для каждого пакета в определенном порядке
                    const order = ['Ś', 'O', 'P', 'K'];
                    order.forEach((label, index) => {
                        for (let i = 0; i < totalQuantity; i++) {
                            response.dishes.forEach(function (dish) {
                                const packageLabel = `${label}${packageCalories}`;

                                if (dish[`dish_${index + 1}_description`]) {
                                    stickerData.packages.push({
                                        package: packageLabel,
                                        total_quantity: 1,
                                        dishes: [
                                            {
                                                description: dish[`dish_${index + 1}_description`],
                                                ingredients: dish[`dish_${index + 1}_ingredients`],
                                                allergens: dish[`dish_${index + 1}_allergens`] || 'Brak информации',
                                                energy: parseFloat(dish[`dish_${index + 1}_energy`] * packageCalories).toFixed(2),
                                                fat: parseFloat(dish[`dish_${index + 1}_fat`] * packageCalories).toFixed(2),
                                                carbohydrates: parseFloat(dish[`dish_${index + 1}_carbohydrates`] * packageCalories).toFixed(2),
                                                protein: parseFloat(dish[`dish_${index + 1}_protein`] * packageCalories).toFixed(2),
                                                salt: parseFloat(dish[`dish_${index + 1}_salt`] * packageCalories).toFixed(2),
                                                net_mass: dish[`dish_${index + 1}_net_mass`] ? `${dish[`dish_${index + 1}_net_mass`]} g` : 'Brak информации'
                                            }
                                        ]
                                    });
                                }
                            });
                        }
                    });
                });

                console.log("Подготовленные данные для сохранения в localStorage:", stickerData);

                try {
                    // Сохраняем данные в localStorage
                    localStorage.setItem('stickerData', JSON.stringify(stickerData));

                    // Проверка успешности записи
                    if (localStorage.getItem('stickerData')) {
                        console.log('Данные для наклеек успешно сохранены в localStorage:', stickerData);

                        // Перенаправляем на страницу генерации наклеек
                        setTimeout(() => {
                            window.location.href = "/admin/sticker.html";
                        }, 300);
                    } else {
                        console.error('Ошибка: данные не были успешно сохранены.');
                        alert('Произошла ошибка при сохранении данных для генерации наклеек. Пожалуйста, повторите попытку.');
                    }
                } catch (error) {
                    console.error('Ошибка при сохранении данных в localStorage:', error.message);
                    alert('Ошибка при сохранении данных в localStorage. Проверьте наличие доступного места.');
                } finally {
                    isGenerating = false; // Сбрасываем флаг после завершения
                }
            },
            error: function () {
                console.error('Ошибка при получении данных о блюдах.');
                alert('Произошла ошибка при получении данных. Пожалуйста, повторите попытку позже.');
                isGenerating = false; // Сбрасываем флаг
            }
        });
    });

    // Сброс флага при смене страницы
    $(window).on('beforeunload', function () {
        isGenerating = false; // Сбрасываем флаг при смене страницы
    });
});
