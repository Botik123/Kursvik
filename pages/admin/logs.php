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

// Получение логов
$stmt = $pdo->prepare("
    SELECT l.*, u.last_name, u.first_name, u.email 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 50
");
$stmt->execute();
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи действий</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .sidebar-item { transition: all 0.2s ease; }
        .sidebar-item:hover { background-color: #f3f4f6; }
        .sidebar-item.active { background-color: #eff6ff; color: #2563eb; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
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
                    <a href="/booking_system/admin/reports" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-file-chart-line"></i> Отчёты
                    </a>
                </div>
                <div class="mb-6">
                    <p class="text-xs uppercase text-gray-400 font-semibold mb-3">Настройки</p>
                    <a href="/booking_system/admin/settings" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <i class="ri-settings-line"></i> Настройки
                    </a>
                    <a href="/booking_system/admin/logs" class="sidebar-item active flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
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

        <main class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-800">Логи действий</h1>
                    <p class="text-gray-500 mt-1">История действий пользователей в системе</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Пользователь</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действие</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP адрес</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата и время</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        Логи не найдены
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-gray-600"><?= $log['id'] ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($log['user_id']): ?>
                                            <p class="font-medium text-gray-800"><?= htmlspecialchars($log['last_name'] . ' ' . $log['first_name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($log['email']) ?></p>
                                        <?php else: ?>
                                            <span class="text-gray-400">Система</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($log['action']) ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($log['ip_address']) ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
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