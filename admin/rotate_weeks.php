<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Подключение к базе данных

// Включение логирования ошибок для анализа в случае проблем
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

try {
    // Начинаем транзакцию для обновления базы данных
    $pdo->beginTransaction();

    // Получаем все записи из таблицы weekly_menu, отсортированные по week_number
    $stmt = $pdo->prepare("SELECT id, week_number FROM weekly_menu ORDER BY week_number ASC");
    $stmt->execute();
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Проверяем, если есть хотя бы одна запись
    if (count($weeks) > 0) {
        // Получаем ID первой недели и сохраняем ее week_number
        $firstWeekId = $weeks[0]['id'];
        $maxWeekNumber = $weeks[count($weeks) - 1]['week_number'];

        // Обновляем все остальные недели: сдвигаем их на одну позицию вперед
        for ($i = 1; $i < count($weeks); $i++) {
            $updateStmt = $pdo->prepare("UPDATE weekly_menu SET week_number = :week_number WHERE id = :id");
            $updateStmt->execute([
                ':week_number' => $weeks[$i]['week_number'] - 1,
                ':id' => $weeks[$i]['id']
            ]);
        }

        // Перемещаем первую неделю в конец, присваивая ей максимальный номер + 1
        $updateFirstWeekStmt = $pdo->prepare("UPDATE weekly_menu SET week_number = :week_number WHERE id = :id");
        $updateFirstWeekStmt->execute([
            ':week_number' => $maxWeekNumber,
            ':id' => $firstWeekId
        ]);

        // Логируем успешное перемещение недель в журнал аудита
        $logStmt = $pdo->prepare("INSERT INTO audit_log (action, week_number, created_at) VALUES (?, ?, NOW())");
        $logStmt->execute(['Перемещение недель', $weeks[0]['week_number']]);

        // Подтверждаем транзакцию
        $pdo->commit();
        header("Location: /admin/admin_menu.php?status=week_shifted"); // Перенаправление после успешной операции
        exit();
    } else {
        // Если записи в таблице отсутствуют, выводим сообщение
        header("Location: /admin/admin_menu.php?status=no_weeks_to_rotate");
        exit();
    }

} catch (Exception $e) {
    // В случае ошибки откатываем транзакцию
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Логирование ошибки для дальнейшего анализа
    error_log("Ошибка при перемещении недель: " . $e->getMessage());
    header("Location: /admin/admin_menu.php?status=rotate_error");
    exit();
}
?>
