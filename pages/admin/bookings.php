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
$message = '';

// Обработка изменения статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'] ?? 0;
    $new_status = $_POST['status'] ?? '';
    
    if ($booking_id && in_array($new_status, ['confirmed', 'cancelled'])) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $booking_id])) {
            sendBookingNotification($booking_id, $new_status);
            $message = 'Статус бронирования изменён';
            logAction($_SESSION['user_id'], 'change_booking_status_' . $booking_id . '_to_' . $new_status);
        }
    }
}

// Получение фильтров
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Запрос бронирований
$sql = "
    SELECT b.*, u.last_name, u.first_name, u.email, r.name as resource_name 
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN resources r ON b.resource_id = r.id
    WHERE 1=1
";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
}
if ($date_filter) {
    $sql .= " AND b.booking_date = ?";
    $params[] = $date_filter;
}

$sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Статистика по статусам
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM bookings 
    GROUP BY status
");
$status_counts = [];
while ($row = $stmt->fetch()) {
    $status_counts[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление бронированиями</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .sidebar-item { transition: all 0.2s ease; }
        .sidebar-item:hover { background-color: #f3f4f6; }
        .sidebar-item.active { background-color: #eff6ff; color: #2563eb; }
        .status-badge { padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 500; }
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
                    <a href="/booking_system/admin/bookings" class="sidebar-item active flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-calendar-line"></i>
                        <span>Бронирования</span>
                    </a>
                    <a href="/booking_system/admin/reports" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-file-chart-line"></i>
                        <span>Отчёты</span>
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
                    <h1 class="text-3xl font-bold text-gray-800">Управление бронированиями</h1>
                    <p class="text-gray-500 mt-1">Просмотр и управление всеми бронированиями в системе</p>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                            <select name="status" class="px-3 py-2 border rounded-lg">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Все</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Ожидают</option>
                                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Подтверждены</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Отменены</option>
                                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Завершены</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Дата</label>
                            <input type="date" name="date" value="<?= $date_filter ?>" class="px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Применить</button>
                            <a href="/booking_system/admin/bookings" class="px-4 py-2 border rounded-lg hover:bg-gray-50 ml-2">Сбросить</a>
                        </div>
                    </form>
                </div>

                <!-- Status Cards -->
                <div class="grid grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-gray-800"><?= array_sum($status_counts) ?></div>
                        <div class="text-sm text-gray-500">Всего</div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-yellow-600"><?= $status_counts['pending'] ?? 0 ?></div>
                        <div class="text-sm text-gray-500">Ожидают</div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-green-600"><?= $status_counts['confirmed'] ?? 0 ?></div>
                        <div class="text-sm text-gray-500">Подтверждены</div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600"><?= $status_counts['completed'] ?? 0 ?></div>
                        <div class="text-sm text-gray-500">Завершены</div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-red-600"><?= $status_counts['cancelled'] ?? 0 ?></div>
                        <div class="text-sm text-gray-500">Отменены</div>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Пользователь</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ресурс</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Время</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        Бронирования не найдены
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-gray-600"><?= $booking['id'] ?></td>
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
                                        <span class="status-badge
                                            <?= $booking['status'] === 'confirmed' ? 'bg-green-100 text-green-700' : '' ?>
                                            <?= $booking['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' ?>
                                            <?= $booking['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : '' ?>
                                            <?= $booking['status'] === 'completed' ? 'bg-blue-100 text-blue-700' : '' ?>
                                        ">
                                            <?= $booking['status'] === 'confirmed' ? 'Подтверждено' : '' ?>
                                            <?= $booking['status'] === 'pending' ? 'Ожидает' : '' ?>
                                            <?= $booking['status'] === 'cancelled' ? 'Отменено' : '' ?>
                                            <?= $booking['status'] === 'completed' ? 'Завершено' : '' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($booking['status'] === 'pending'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="text-green-600 hover:text-green-800 text-sm mr-2">
                                                <i class="ri-checkbox-circle-line"></i> Подтвердить
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                                <i class="ri-close-circle-line"></i> Отменить
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>