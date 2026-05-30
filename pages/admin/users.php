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
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($action === 'toggle_block' && $user_id && $user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $new_status = $user['status'] === 'active' ? 'blocked' : 'active';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $user_id])) {
            $message = $new_status === 'active' ? 'Пользователь разблокирован' : 'Пользователь заблокирован';
            logAction($_SESSION['user_id'], $new_status === 'active' ? 'unblock_user_' . $user_id : 'block_user_' . $user_id);
            
            if ($new_status === 'blocked') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE user_id = ? AND status IN ('pending', 'confirmed')");
                $stmt->execute([$user_id]);
            }
        }
    } elseif ($action === 'change_role' && $user_id && $user_id != $_SESSION['user_id']) {
        $new_role = $_POST['role'] ?? 'user';
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        if ($stmt->execute([$new_role, $user_id])) {
            $message = 'Роль пользователя изменена';
            logAction($_SESSION['user_id'], 'change_role_user_' . $user_id);
        }
    }
}

// Получение списка пользователей
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями</title>
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
                    <a href="/booking_system/admin/users" class="sidebar-item active flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
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
                    <h1 class="text-3xl font-bold text-gray-800">Управление пользователями</h1>
                    <p class="text-gray-500 mt-1">Просмотр, блокировка и изменение ролей пользователей</p>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ФИО</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Должность</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-600"><?= $user['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-800"><?= htmlspecialchars($user['last_name'] . ' ' . $user['first_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($user['middle_name'] ?? '') ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($user['position'] ?? '—') ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="role" onchange="this.form.submit()" class="text-sm border rounded px-2 py-1">
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Пользователь</option>
                                            <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Менеджер</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                        </select>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">Вы</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded-full text-xs <?= $user['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= $user['status'] === 'active' ? 'Активен' : 'Заблокирован' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="text-<?= $user['status'] === 'active' ? 'red' : 'green' ?>-600 hover:underline text-sm">
                                            <?= $user['status'] === 'active' ? 'Заблокировать' : 'Разблокировать' ?>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>