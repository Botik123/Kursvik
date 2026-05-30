<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /booking_system/login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$pdo = getDBConnection();

// Статистика по месяцам
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(booking_date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings
    GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$monthly_stats = $stmt->fetchAll();

// Активность пользователей
$stmt = $pdo->query("
    SELECT u.last_name, u.first_name, COUNT(b.id) as bookings_count
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    GROUP BY u.id
    ORDER BY bookings_count DESC
    LIMIT 10
");
$user_activity = $stmt->fetchAll();

// Доступные даты для фильтрации
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'bookings';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчёты - Админ панель</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .sidebar-item { transition: all 0.2s ease; }
        .sidebar-item:hover { background-color: #f3f4f6; }
        .sidebar-item.active { background-color: #eff6ff; color: #2563eb; }
        .stat-card { transition: transform 0.2s ease; }
        .stat-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg">
            <div class="p-6 border-b">
                <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="ri-dashboard-line text-blue-600"></i>
                    AdminPanel
                </h1>
                <p class="text-xs text-gray-500 mt-1">Система бронирования</p>
            </div>
            <nav class="p-4">
                <div class="mb-6">
                    <p class="text-xs uppercase text-gray-400 font-semibold mb-3">Основное</p>
                    <a href="/booking_system/admin" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-dashboard-line"></i> Дашборд
                    </a>
                    <a href="/booking_system/admin/resources" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-building-line"></i> Ресурсы
                    </a>
                    <a href="/booking_system/admin/users" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-user-line"></i> Пользователи
                    </a>
                    <a href="/booking_system/admin/bookings" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-calendar-line"></i> Бронирования
                    </a>
                    <a href="/booking_system/admin/reports" class="sidebar-item active flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-file-chart-line"></i> Отчёты
                    </a>
                </div>
                <div class="mb-6">
                    <p class="text-xs uppercase text-gray-400 font-semibold mb-3">Настройки</p>
                    <a href="/booking_system/admin/settings" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-settings-line"></i> Настройки
                    </a>
                    <a href="/booking_system/admin/logs" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-history-line"></i> Логи
                    </a>
                </div>
            </nav>
            <div class="absolute bottom-0 w-64 p-4 border-t">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm">A</div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-700">Администратор</p>
                        <p class="text-xs text-gray-500">admin@company.com</p>
                    </div>
                    <a href="/booking_system/logout" class="text-gray-400 hover:text-red-500">
                        <i class="ri-logout-box-line"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-800">Аналитика и отчёты</h1>
                    <p class="text-gray-500 mt-1">Статистика использования системы и экспорт данных</p>
                </div>

                <!-- Статистика -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
                    $total = $stmt->fetchColumn();
                    
                    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM bookings WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $active = $stmt->fetchColumn();
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM resources WHERE status = 'available'");
                    $available = $stmt->fetchColumn();
                    
                    $stmt = $pdo->query("SELECT ROUND(AVG(total)) as avg FROM (SELECT COUNT(*) as total FROM bookings GROUP BY booking_date) as daily");
                    $avg = $stmt->fetchColumn();
                    ?>
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="ri-calendar-line text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $total ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Всего бронирований</p>
                    </div>
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="ri-user-line text-green-600 text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $active ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Активных пользователей</p>
                    </div>
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="ri-building-line text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $available ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Доступных ресурсов</p>
                    </div>
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="ri-bar-chart-line text-orange-600 text-xl"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $avg ?: 0 ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Бронирований в день</p>
                    </div>
                </div>

                <!-- Графики -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Динамика бронирований</h3>
                        <canvas id="bookingsChart" height="250"></canvas>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Топ активных пользователей</h3>
                        <div class="space-y-3">
                            <?php foreach ($user_activity as $user): ?>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-700"><?= htmlspecialchars($user['last_name'] . ' ' . $user['first_name']) ?></span>
                                <span class="font-semibold text-blue-600"><?= $user['bookings_count'] ?> броней</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Экспорт отчётов -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Экспорт отчётов</h3>
                    
                    <form id="exportForm" method="GET" action="/booking_system/admin/export.php">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Тип отчёта</label>
                                <select id="reportType" name="type" class="w-full px-3 py-2 border rounded-lg">
                                    <option value="bookings" <?= $report_type === 'bookings' ? 'selected' : '' ?>>Бронирования</option>
                                    <option value="users" <?= $report_type === 'users' ? 'selected' : '' ?>>Пользователи</option>
                                    <option value="resources" <?= $report_type === 'resources' ? 'selected' : '' ?>>Ресурсы</option>
                                </select>
                            </div>
                            <div id="dateRange">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Дата с</label>
                                <input type="date" name="start_date" value="<?= $start_date ?>" class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div id="dateRangeEnd">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Дата по</label>
                                <input type="date" name="end_date" value="<?= $end_date ?>" class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Формат</label>
                                <select id="exportFormat" name="format" class="w-full px-3 py-2 border rounded-lg">
                                    <option value="csv">CSV</option>
                                    <option value="excel">Excel (XLS)</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                        </div>
                        
                        <input type="hidden" name="report_type" value="<?= $report_type ?>">
                        
                        <div class="flex gap-4">
                            <button type="submit" class="flex items-center gap-2 px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                <i class="ri-download-line"></i> Экспортировать
                            </button>
                            <button type="button" onclick="window.open('/booking_system/admin/export.php?format=excel&type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>', '_blank')" class="flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="ri-file-excel-line"></i> Excel
                            </button>
                            <button type="button" onclick="window.open('/booking_system/admin/export.php?format=pdf&type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>', '_blank')" class="flex items-center gap-2 px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                <i class="ri-file-pdf-line"></i> PDF
                            </button>
                            <button type="button" onclick="window.open('/booking_system/admin/export.php?format=csv&type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>', '_blank')" class="flex items-center gap-2 px-6 py-2.5 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                                <i class="ri-file-csv-line"></i> CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // График бронирований
        const ctx = document.getElementById('bookingsChart').getContext('2d');
        const months = <?= json_encode(array_column(array_reverse($monthly_stats), 'month')) ?>;
        const totals = <?= json_encode(array_column(array_reverse($monthly_stats), 'total')) ?>;
        const confirmed = <?= json_encode(array_column(array_reverse($monthly_stats), 'confirmed')) ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Всего бронирований',
                        data: totals,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Подтверждено',
                        data: confirmed,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
        
        // Показывать/скрывать диапазон дат для разных типов отчётов
        const reportType = document.getElementById('reportType');
        const dateRange = document.getElementById('dateRange');
        const dateRangeEnd = document.getElementById('dateRangeEnd');
        
        function toggleDateRange() {
            if (reportType.value === 'bookings') {
                dateRange.style.display = 'block';
                dateRangeEnd.style.display = 'block';
            } else {
                dateRange.style.display = 'none';
                dateRangeEnd.style.display = 'none';
            }
        }
        
        reportType.addEventListener('change', toggleDateRange);
        toggleDateRange();
        
        // Обработка формы экспорта
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const format = document.getElementById('exportFormat').value;
            const type = document.getElementById('reportType').value;
            let url = '/booking_system/admin/export.php?format=' + format + '&type=' + type;
            
            if (type === 'bookings') {
                const startDate = document.querySelector('input[name="start_date"]').value;
                const endDate = document.querySelector('input[name="end_date"]').value;
                url += '&start_date=' + startDate + '&end_date=' + endDate;
            }
            
            window.open(url, '_blank');
        });
    </script>
</body>
</html>