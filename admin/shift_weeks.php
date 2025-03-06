<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // Подключение к базе данных

// Включение логирования ошибок для анализа в случае проблем
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/error_log.log');
error_reporting(E_ALL); // Логируем все ошибки для тщательной диагностики

try {
    // Проверка наличия записей в таблице weekly_menu
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM weekly_menu");
    $stmt->execute();
    $weekCount = $stmt->fetchColumn();

    // Если недостаточно недель для сдвига
    if ($weekCount <= 1) {
        header('Location: /admin/admin_menu.php?status=no_weeks_to_rotate');
        exit();
    }

    // Начинаем транзакцию для сдвига недель, если не находимся в активной транзакции
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }

    // Удаляем первую неделю, используя подготовленное выражение
    $deleteStmt = $pdo->prepare("DELETE FROM weekly_menu WHERE week_number = :week_number");
    $deleteStmt->execute([':week_number' => 1]);

    // Сдвигаем все оставшиеся недели влево
    $updateStmt = $pdo->prepare("UPDATE weekly_menu SET week_number = week_number - 1 WHERE week_number > 1");
    $updateStmt->execute();

    // Логируем успешный сдвиг недель в журнал аудита
    $logStmt = $pdo->prepare("INSERT INTO audit_log (action, week_number, created_at) VALUES (?, ?, NOW())");
    $logStmt->execute(['Сдвиг недель влево', $weekCount]);

    // Подтверждаем транзакцию
    $pdo->commit();

    // Перенаправляем обратно на admin_menu.php с сообщением об успешном сдвиге
    header('Location: /admin/admin_menu.php?status=week_shifted');
    exit();

} catch (Exception $e) {
    // В случае ошибки откатываем транзакцию
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Логируем ошибку для дальнейшего анализа и перенаправляем с сообщением об ошибке
    error_log("Ошибка при сдвиге недель: " . $e->getMessage());
    header('Location: /admin/admin_menu.php?status=shift_error');
    exit();
}
?>
