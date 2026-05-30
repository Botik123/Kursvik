<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$pdo = getDBConnection();

// Получаем все таблицы
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$output = "-- Резервная копия базы данных\n";
$output .= "-- Дата: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Пользователь: " . ($_SESSION['user_name'] ?? 'Admin') . "\n\n";
$output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Структура таблицы
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch();
    $output .= "DROP TABLE IF EXISTS `$table`;\n";
    $output .= $row['Create Table'] . ";\n\n";
    
    // Данные таблицы
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $values = array_map(function($value) use ($pdo) {
                return $value === null ? 'NULL' : $pdo->quote($value);
            }, array_values($row));
            
            $columns = array_keys($row);
            $columns_escaped = array_map(function($col) {
                return "`$col`";
            }, $columns);
            
            $output .= "INSERT INTO `$table` (" . implode(', ', $columns_escaped) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $output .= "\n";
    }
}

$output .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Отправка файла
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
header('Content-Length: ' . strlen($output));

echo $output;
exit;
?>