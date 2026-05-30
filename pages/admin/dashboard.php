<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /booking_system/login');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../inc/functions.php';

$pdo = getDBConnection();

// Статистика
// Всего пользователей
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

// Всего ресурсов
$stmt = $pdo->query("SELECT COUNT(*) FROM resources");
$total_resources = $stmt->fetchColumn();

// Бронирований сегодня
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()");
$stmt->execute();
$today_bookings = $stmt->fetchColumn();

// Ожидают подтверждения
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$stmt->execute();
$pending_bookings = $stmt->fetchColumn();

// Загрузка системы (процент занятости ресурсов на сегодня)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN b.id IS NOT NULL THEN 1 ELSE 0 END) as busy
    FROM resources r
    LEFT JOIN bookings b ON r.id = b.resource_id 
        AND b.booking_date = CURDATE() 
        AND b.status NOT IN ('cancelled', 'completed')
");
$stmt->execute();
$load_data = $stmt->fetch();
$system_load = $load_data['total'] > 0 ? round(($load_data['busy'] / $load_data['total']) * 100) : 0;

// Последние бронирования
$stmt = $pdo->prepare("
    SELECT b.*, u.last_name, u.first_name, u.email, r.name as resource_name 
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN resources r ON b.resource_id = r.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_bookings = $stmt->fetchAll();

// Популярные ресурсы
$stmt = $pdo->prepare("
    SELECT r.name, COUNT(b.id) as bookings_count 
    FROM resources r
    LEFT JOIN bookings b ON r.id = b.resource_id
    GROUP BY r.id
    ORDER BY bookings_count DESC
    LIMIT 5
");
$stmt->execute();
$popular_resources = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора - Система бронирования</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .sidebar-item {
            transition: all 0.2s ease;
        }
        .sidebar-item:hover {
            background-color: #f3f4f6;
        }
        .sidebar-item.active {
            background-color: #eff6ff;
            color: #2563eb;
            border-right: 3px solid #2563eb;
        }
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
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
                    <a href="/booking_system/admin" class="sidebar-item active flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-dashboard-line"></i>
                        <span>Дашборд</span>
                    </a>
                    <a href="/booking_system/admin/resources" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-building-line"></i>
                        <span>Ресурсы</span>
                    </a>
                    <a href="/booking_system/admin/users" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-user-line"></i>
                        <span>Пользователи</span>
                    </a>
                    <a href="/booking_system/admin/bookings" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-calendar-line"></i>
                        <span>Бронирования</span>
                    </a>
                    <a href="/booking_system/admin/reports" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-file-chart-line"></i>
                        <span>Отчёты</span>
                    </a>
                </div>
                <div class="mb-6">
                    <p class="text-xs uppercase text-gray-400 font-semibold mb-3">Настройки</p>
                    <a href="/booking_system/admin/settings" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-settings-line"></i>
                        <span>Настройки</span>
                    </a>
                    <a href="/booking_system/admin/logs" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-history-line"></i>
                        <span>Логи</span>
                    </a>
                </div>
            </nav>
            <div class="absolute bottom-0 w-64 p-4 border-t">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm">
                        <?= mb_substr($_SESSION['user_name'] ?? 'A', 0, 1) ?>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></p>
                        <p class="text-xs text-gray-500">Администратор</p>
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
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Панель управления</h1>
                    <p class="text-gray-500 mt-1">Добро пожаловать, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Администратор') ?>!</p>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="ri-user-line text-2xl text-blue-600"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $total_users ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Всего пользователей</p>
                        <p class="text-xs text-green-500 mt-2">+5% за месяц</p>
                    </div>

                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="ri-building-line text-2xl text-green-600"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $total_resources ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Доступных ресурсов</p>
                        <p class="text-xs text-gray-500 mt-2">4 типа</p>
                    </div>

                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="ri-calendar-line text-2xl text-orange-600"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $today_bookings ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Бронирований сегодня</p>
                        <p class="text-xs text-orange-500 mt-2">На сегодня</p>
                    </div>

                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="ri-time-line text-2xl text-yellow-600"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $pending_bookings ?></span>
                        </div>
                        <p class="text-gray-500 text-sm">Ожидают подтверждения</p>
                        <p class="text-xs text-yellow-500 mt-2">Требуют внимания</p>
                    </div>

                    <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="ri-pie-chart-line text-2xl text-purple-600"></i>
                            </div>
                            <span class="text-2xl font-bold text-gray-800"><?= $system_load ?>%</span>
                        </div>
                        <p class="text-gray-500 text-sm">Загрузка системы</p>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                            <div class="bg-purple-600 h-1.5 rounded-full" style="width: <?= $system_load ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Загрузка по типам ресурсов -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Загрузка по типам ресурсов</h3>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT rt.name as type_name, 
                                   COUNT(DISTINCT r.id) as total,
                                   COUNT(DISTINCT CASE WHEN b.id IS NOT NULL AND b.booking_date = CURDATE() THEN b.id END) as busy
                            FROM resource_types rt
                            LEFT JOIN resources r ON rt.id = r.type_id
                            LEFT JOIN bookings b ON r.id = b.resource_id AND b.booking_date = CURDATE() AND b.status NOT IN ('cancelled', 'completed')
                            GROUP BY rt.id
                        ");
                        $stmt->execute();
                        $type_load = $stmt->fetchAll();
                        ?>
                        <div class="space-y-4">
                            <?php foreach ($type_load as $type): 
                                $percent = $type['total'] > 0 ? round(($type['busy'] / $type['total']) * 100) : 0;
                                $colors = ['blue', 'green', 'orange', 'purple'];
                                $color = $colors[array_rand($colors)];
                            ?>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600"><?= htmlspecialchars($type['type_name']) ?></span>
                                    <span class="font-medium text-gray-800"><?= $percent ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-<?= $color ?>-600 h-2 rounded-full" style="width: <?= $percent ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Популярные ресурсы -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Топ популярных ресурсов</h3>
                        <div class="space-y-3">
                            <?php foreach ($popular_resources as $resource): ?>
                            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="ri-building-line text-blue-600 text-sm"></i>
                                    </div>
                                    <span class="text-gray-700"><?= htmlspecialchars($resource['name']) ?></span>
                                </div>
                                <span class="font-semibold text-blue-600"><?= $resource['bookings_count'] ?> броней</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800">Последние бронирования</h3>
                        <p class="text-sm text-gray-500 mt-1">Список последних 10 бронирований в системе</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Пользователь</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ресурс</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Время</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($recent_bookings as $booking): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-medium text-gray-800"><?= htmlspecialchars($booking['last_name'] . ' ' . $booking['first_name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($booking['email']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($booking['resource_name']) ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= date('d.m.Y', strtotime($booking['booking_date'])) ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= substr($booking['start_time'], 0, 5) ?> - <?= substr($booking['end_time'], 0, 5) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full
                                            <?= $booking['status'] === 'confirmed' ? 'bg-green-100 text-green-700' : '' ?>
                                            <?= $booking['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' ?>
                                            <?= $booking['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : '' ?>
                                        ">
                                            <?= $booking['status'] === 'confirmed' ? 'Подтверждено' : '' ?>
                                            <?= $booking['status'] === 'pending' ? 'Ожидает' : '' ?>
                                            <?= $booking['status'] === 'cancelled' ? 'Отменено' : '' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button class="text-blue-600 hover:text-blue-800">
                                            <i class="ri-eye-line"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>