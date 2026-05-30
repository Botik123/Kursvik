<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Доступ запрещён');
}

require_once __DIR__ . '/../../config/database.php';

$pdo = getDBConnection();
$format = $_GET['format'] ?? 'csv';
$type = $_GET['type'] ?? 'bookings';

// Получаем данные для отчёта
if ($type === 'bookings') {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            CONCAT(u.last_name, ' ', u.first_name) as user_name,
            u.email,
            u.department,
            r.name as resource_name,
            rt.name as resource_type,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.purpose,
            b.status,
            b.created_at
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN resources r ON b.resource_id = r.id
        LEFT JOIN resource_types rt ON r.type_id = rt.id
        WHERE b.booking_date BETWEEN ? AND ?
        ORDER BY b.booking_date DESC, b.start_time
    ");
    $stmt->execute([$start_date, $end_date]);
    $data = $stmt->fetchAll();
    
    $filename = "bookings_report_{$start_date}_to_{$end_date}";
    
} elseif ($type === 'users') {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            CONCAT(u.last_name, ' ', u.first_name, ' ', COALESCE(u.middle_name, '')) as full_name,
            u.email,
            u.phone,
            u.position,
            u.department,
            u.role,
            u.status,
            COUNT(b.id) as total_bookings,
            SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            DATE_FORMAT(u.created_at, '%d.%m.%Y') as registered_date
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id
        GROUP BY u.id
        ORDER BY total_bookings DESC
    ");
    $stmt->execute();
    $data = $stmt->fetchAll();
    $filename = "users_report_" . date('Y-m-d');
    
} elseif ($type === 'resources') {
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.name as resource_name,
            rt.name as resource_type,
            r.capacity,
            r.location,
            r.status,
            COUNT(b.id) as total_bookings,
            SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
        FROM resources r
        LEFT JOIN resource_types rt ON r.type_id = rt.id
        LEFT JOIN bookings b ON r.id = b.resource_id
        GROUP BY r.id
        ORDER BY total_bookings DESC
    ");
    $stmt->execute();
    $data = $stmt->fetchAll();
    $filename = "resources_report_" . date('Y-m-d');
}

// Экспорт в зависимости от формата
if ($format === 'csv') {
    exportCSV($data, $filename);
} elseif ($format === 'excel') {
    exportExcel($data, $filename);
} elseif ($format === 'pdf') {
    exportPDF($data, $filename, $type);
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Добавляем BOM для корректной работы с русскими буквами в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Заголовки
    if (!empty($data)) {
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Данные
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $filename) {
    // Создаем HTML таблицу для Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Отчёт</title>';
    echo '<style>
        th { background-color: #4F81BD; color: white; padding: 8px; }
        td { padding: 6px; border: 1px solid #ddd; }
        table { border-collapse: collapse; width: 100%; }
    </style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>Отчёт по системе бронирования</h2>';
    echo '<p>Дата формирования: ' . date('d.m.Y H:i:s') . '</p>';
    
    if (!empty($data)) {
        echo '<table border="1">';
        // Заголовки
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        
        // Данные
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>Нет данных для отображения</p>';
    }
    
    echo '</body></html>';
    exit;
}

function exportPDF($data, $filename, $type) {
    // Используем HTML + CSS для PDF (браузер сам предложит печать)
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $filename . '.html"');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Отчёт</title>';
    echo '<style>
        @media print {
            body { margin: 0; padding: 20px; }
            button { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            color: #2563eb;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        .info {
            background: #f3f4f6;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #4F81BD;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-confirmed { color: #16a34a; font-weight: bold; }
        .status-pending { color: #eab308; font-weight: bold; }
        .status-cancelled { color: #dc2626; font-weight: bold; }
        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        button:hover {
            background: #1d4ed8;
        }
    </style>';
    echo '<script>
        function printReport() {
            window.print();
        }
    </script>';
    echo '</head>';
    echo '<body>';
    
    echo '<button onclick="printReport()">📄 Сохранить как PDF</button>';
    
    echo '<h1>Отчёт по системе бронирования ресурсов</h1>';
    echo '<div class="info">';
    echo '<p><strong>Дата формирования:</strong> ' . date('d.m.Y H:i:s') . '</p>';
    echo '<p><strong>Тип отчёта:</strong> ' . ($type === 'bookings' ? 'Бронирования' : ($type === 'users' ? 'Пользователи' : 'Ресурсы')) . '</p>';
    echo '<p><strong>Сформировал:</strong> ' . htmlspecialchars($_SESSION['user_name'] ?? 'Администратор') . '</p>';
    echo '</div>';
    
    if (!empty($data)) {
        echo '<table>';
        echo '<thead><tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $key => $cell) {
                $class = '';
                if ($key === 'status') {
                    if ($cell === 'confirmed') $class = 'status-confirmed';
                    elseif ($cell === 'pending') $class = 'status-pending';
                    elseif ($cell === 'cancelled') $class = 'status-cancelled';
                }
                echo '<td class="' . $class . '">' . htmlspecialchars($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Нет данных для отображения</p>';
    }
    
    echo '<div class="info" style="margin-top: 30px;">';
    echo '<p><small>Отчёт сгенерирован автоматически системой бронирования ресурсов</small></p>';
    echo '</div>';
    
    echo '</body></html>';
    exit;
}
?>