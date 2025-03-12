const tf = require('@tensorflow/tfjs');
const axios = require('axios');
const { ImageAnnotatorClient } = require('@google-cloud/vision');

// --- Recommendation System with TensorFlow.js ---

// Пример данных: предпочтения пользователей по товарам (1 - нравится, 0 - не нравится)
const data = [
    [1, 0, 1, 0, 1], // User1
    [1, 1, 0, 1, 0], // User2
    [0, 1, 1, 0, 0], // User3
    [1, 1, 1, 0, 1], // User4
    [0, 1, 1, 1, 1]  // User5
];

// Преобразуем данные в тензор
const tensorData = tf.tensor2d(data);

// Строим модель для рекомендательной системы
const model = tf.sequential();

// Добавляем слой для нейронной сети
model.add(tf.layers.dense({
    units: 4,
    activation: 'relu',
    inputShape: [5]
}));

// Добавляем выходной слой
model.add(tf.layers.dense({
    units: 5,
    activation: 'sigmoid'
}));

// Компилируем модель
model.compile({
    optimizer: 'adam',
    loss: 'binaryCrossentropy',
    metrics: ['accuracy']
});

// Функция для тренировки модели
async function trainModel() {
    await model.fit(tensorData, tensorData, {
        epochs: 100
    });
    console.log("Модель обучена!");
}

// Функция для получения рекомендаций
async function getRecommendations(userPreferences) {
    const userTensor = tf.tensor2d([userPreferences]);
    const recommendations = model.predict(userTensor);
    recommendations.print();
}

// --- GPT-3 Integration for Enhancing Recommendations ---

async function getGPTResponse(prompt) {
    const response = await axios.post('https://api.openai.com/v1/completions', {
        model: 'text-davinci-003',  // Выбор модели GPT-3
        prompt: prompt,
        max_tokens: 100,
        temperature: 0.7
    }, {
        headers: {
            'Authorization': `Bearer YOUR_API_KEY`  // Замените на ваш API ключ
        }
    });

    console.log(response.data.choices[0].text);
}

// Пример запроса к GPT-3 для улучшения рекомендательной системы
async function enhanceRecommendationSystem() {
    await getGPTResponse("Как мне улучшить свою рекомендательную систему?");
}

// --- Google Cloud Vision API for Image Analysis ---

const client = new ImageAnnotatorClient();

// Функция для анализа изображения с помощью Google Vision
async function analyzeImage(imagePath) {
    const [result] = await client.labelDetection(imagePath);
    const labels = result.labelAnnotations;
    console.log('Labels:');
    labels.forEach(label => console.log(label.description));
}

// Пример использования Google Vision для анализа изображения
async function processImage() {
    await analyzeImage('./image.jpg'); // Замените на путь к вашему изображению
}

// --- Main Execution Flow ---

async function main() {
    // Обучение модели
    await trainModel();

    // Пример предпочтений нового пользователя
    const newUserPreferences = [1, 0, 0, 1, 0];
    await getRecommendations(newUserPreferences);

    // Взаимодействие с GPT-3 для улучшения системы
    await enhanceRecommendationSystem();

    // Анализ изображения
    await processImage();
}

main().catch(err => console.error(err));
