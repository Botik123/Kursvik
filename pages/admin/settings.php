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
$message = '';
$error = '';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $site_name = $_POST['site_name'] ?? '';
        $site_email = $_POST['site_email'] ?? '';
        $booking_min_duration = intval($_POST['booking_min_duration'] ?? 30);
        $booking_max_duration = intval($_POST['booking_max_duration'] ?? 240);
        $cancellation_deadline = intval($_POST['cancellation_deadline'] ?? 60);
        $working_hours_start = $_POST['working_hours_start'] ?? '09:00';
        $working_hours_end = $_POST['working_hours_end'] ?? '18:00';
        $time_slot_interval = intval($_POST['time_slot_interval'] ?? 30);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                   VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            
            $settings = [
                ['site_name', $site_name],
                ['site_email', $site_email],
                ['booking_min_duration', $booking_min_duration],
                ['booking_max_duration', $booking_max_duration],
                ['cancellation_deadline', $cancellation_deadline],
                ['working_hours_start', $working_hours_start],
                ['working_hours_end', $working_hours_end],
                ['time_slot_interval', $time_slot_interval]
            ];
            
            foreach ($settings as $setting) {
                $stmt->execute([$setting[0], $setting[1], $setting[1]]);
            }
            
            $message = 'Настройки успешно сохранены!';
            logAction($_SESSION['user_id'], 'updated_system_settings');
        } catch (Exception $e) {
            $error = 'Ошибка при сохранении настроек: ' . $e->getMessage();
        }
    }
    
    elseif ($action === 'add_holiday') {
        $holiday_date = $_POST['holiday_date'] ?? '';
        $holiday_name = $_POST['holiday_name'] ?? '';
        
        if ($holiday_date) {
            try {
                $stmt = $pdo->prepare("INSERT INTO holidays (holiday_date, name, is_working) VALUES (?, ?, 0)");
                $stmt->execute([$holiday_date, $holiday_name]);
                $message = 'Праздничный день добавлен';
                logAction($_SESSION['user_id'], 'added_holiday_' . $holiday_date);
            } catch (Exception $e) {
                $error = 'Такая дата уже существует';
            }
        }
    }
    
    elseif ($action === 'delete_holiday') {
        $holiday_id = $_POST['holiday_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
        $stmt->execute([$holiday_id]);
        $message = 'Праздничный день удалён';
        logAction($_SESSION['user_id'], 'deleted_holiday_' . $holiday_id);
    }
}

// Получение текущих настроек
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Установка значений по умолчанию
$default_settings = [
    'site_name' => 'Система бронирования ресурсов',
    'site_email' => 'noreply@booking-system.com',
    'booking_min_duration' => 30,
    'booking_max_duration' => 240,
    'cancellation_deadline' => 60,
    'working_hours_start' => '09:00',
    'working_hours_end' => '18:00',
    'time_slot_interval' => 30
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Получение списка праздников
$stmt = $pdo->query("SELECT * FROM holidays ORDER BY holiday_date DESC");
$holidays = $stmt->fetchAll();

// Получение email шаблонов
$email_templates = [
    'booking_confirmation' => [
        'subject' => 'Подтверждение бронирования',
        'body' => "Уважаемый(ая) {user_name}!\n\nВаше бронирование ресурса «{resource_name}» подтверждено.\n\nДата: {booking_date}\nВремя: {start_time} - {end_time}\nЦель: {purpose}\n\nС уважением,\nСистема бронирования"
    ],
    'booking_reminder' => [
        'subject' => 'Напоминание о бронировании',
        'body' => "Уважаемый(ая) {user_name}!\n\nНапоминаем, что завтра в {start_time} у вас забронирован ресурс «{resource_name}».\n\nС уважением,\nСистема бронирования"
    ],
    'booking_cancelled' => [
        'subject' => 'Бронирование отменено',
        'body' => "Уважаемый(ая) {user_name}!\n\nВаше бронирование ресурса «{resource_name}» было отменено.\n\nДата: {booking_date}\nВремя: {start_time} - {end_time}\n\nС уважением,\nСистема бронирования"
    ]
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки системы - Админ панель</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar-item { transition: all 0.2s ease; }
        .sidebar-item:hover { background-color: #f3f4f6; }
        .sidebar-item.active { background-color: #eff6ff; color: #2563eb; }
        .settings-card { transition: all 0.2s ease; }
        .settings-card:hover { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg">
            <div class="p-6 border-b">
                <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span class="text-blue-600">📊</span>
                    AdminPanel
                </h1>
                <p class="text-xs text-gray-500 mt-1">Система бронирования</p>
            </div>
            <nav class="p-4">
                <div class="mb-6">
                    <p class="text-xs uppercase text-gray-400 font-semibold mb-3">Основное</p>
                    <a href="/booking_system/admin" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <span>📊</span> Дашборд
                    </a>
                    <a href="/booking_system/admin/resources" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <span>🏢</span> Ресурсы
                    </a>
                    <a href="/booking_system/admin/users" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <span>👥</span> Пользователи
                    </a>
                    <a href="/booking_system/admin/bookings" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <span>📅</span> Бронирования
                    </a>
                    <a href="/booking_system/admin/reports" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <span>📈</span> Отчёты
                    </a>
                </div>
                <div class="mb-6">
                    <p class="text-xs uppercase text-gray-400 font-semibold mb-3">Настройки</p>
                    <a href="/booking_system/admin/settings" class="sidebar-item active flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <span>⚙️</span> Настройки
                    </a>
                    <a href="/booking_system/admin/logs" class="sidebar-item flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
                        <span>📋</span> Логи
                    </a>
                </div>
            </nav>
            <div class="absolute bottom-0 w-64 p-4 border-t">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm">
                        <?= mb_substr($_SESSION['user_name'] ?? 'A', 0, 1) ?>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Администратор') ?></p>
                        <p class="text-xs text-gray-500">admin@company.com</p>
                    </div>
                    <a href="/booking_system/logout" class="text-gray-400 hover:text-red-500">
                        <span>🚪</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-800">Настройки системы</h1>
                    <p class="text-gray-500 mt-1">Управление параметрами системы бронирования</p>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                        <span><?= htmlspecialchars($message) ?></span>
                        <button onclick="this.parentElement.remove()" class="text-green-700">✕</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
                        <span><?= htmlspecialchars($error) ?></span>
                        <button onclick="this.parentElement.remove()" class="text-red-700">✕</button>
                    </div>
                <?php endif; ?>

                <!-- Вкладки -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="flex gap-6">
                        <button onclick="showTab('general')" id="tab-general" class="pb-3 px-1 text-blue-600 border-b-2 border-blue-600 font-medium">Общие настройки</button>
                        <button onclick="showTab('holidays')" id="tab-holidays" class="pb-3 px-1 text-gray-500 hover:text-gray-700">Праздничные дни</button>
                        <button onclick="showTab('email')" id="tab-email" class="pb-3 px-1 text-gray-500 hover:text-gray-700">Email шаблоны</button>
                        <button onclick="showTab('backup')" id="tab-backup" class="pb-3 px-1 text-gray-500 hover:text-gray-700">Резервное копирование</button>
                    </nav>
                </div>

                <!-- Вкладка: Общие настройки -->
                <div id="general-tab" class="tab-content">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Основные параметры</h2>
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="save_settings">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Название сайта</label>
                                    <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']) ?>" 
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email для уведомлений</label>
                                    <input type="email" name="site_email" value="<?= htmlspecialchars($settings['site_email']) ?>" 
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <div class="border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-800 mb-4">Параметры бронирования</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Мин. длительность (мин)</label>
                                        <input type="number" name="booking_min_duration" value="<?= $settings['booking_min_duration'] ?>" 
                                               class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Макс. длительность (мин)</label>
                                        <input type="number" name="booking_max_duration" value="<?= $settings['booking_max_duration'] ?>" 
                                               class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Отмена за (мин до начала)</label>
                                        <input type="number" name="cancellation_deadline" value="<?= $settings['cancellation_deadline'] ?>" 
                                               class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Начало рабочего дня</label>
                                        <input type="time" name="working_hours_start" value="<?= $settings['working_hours_start'] ?>" 
                                               class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Конец рабочего дня</label>
                                        <input type="time" name="working_hours_end" value="<?= $settings['working_hours_end'] ?>" 
                                               class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Интервал слотов (мин)</label>
                                        <input type="number" name="time_slot_interval" value="<?= $settings['time_slot_interval'] ?>" 
                                               class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4">
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    Сохранить настройки
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Вкладка: Праздничные дни -->
                <div id="holidays-tab" class="tab-content hidden">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-gray-800">Праздничные и выходные дни</h2>
                            <button onclick="openHolidayModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                + Добавить праздник
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if (empty($holidays)): ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                                Нет добавленных праздничных дней
                                             </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($holidays as $holiday): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4"><?= date('d.m.Y', strtotime($holiday['holiday_date'])) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($holiday['name']) ?></td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Выходной</span>
                                             </td>
                                            <td class="px-6 py-4">
                                                <form method="POST" class="inline" onsubmit="return confirm('Удалить этот праздничный день?')">
                                                    <input type="hidden" name="action" value="delete_holiday">
                                                    <input type="hidden" name="holiday_id" value="<?= $holiday['id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                                        <span>🗑️</span> Удалить
                                                    </button>
                                                </form>
                                             </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Вкладка: Email шаблоны -->
                <div id="email-tab" class="tab-content hidden">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Email шаблоны уведомлений</h2>
                        <p class="text-gray-500 mb-6">Настройка текста email-уведомлений. Доступные переменные: {user_name}, {resource_name}, {booking_date}, {start_time}, {end_time}, {purpose}</p>
                        
                        <?php foreach ($email_templates as $key => $template): ?>
                        <div class="border rounded-lg mb-6 overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b">
                                <h3 class="font-semibold text-gray-800">
                                    <?= $key === 'booking_confirmation' ? '📧 Подтверждение бронирования' : '' ?>
                                    <?= $key === 'booking_reminder' ? '⏰ Напоминание о бронировании' : '' ?>
                                    <?= $key === 'booking_cancelled' ? '❌ Отмена бронирования' : '' ?>
                                </h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Тема письма</label>
                                    <input type="text" value="<?= htmlspecialchars($template['subject']) ?>" 
                                           class="w-full px-3 py-2 border rounded-lg bg-gray-50">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Текст письма</label>
                                    <textarea rows="6" class="w-full px-3 py-2 border rounded-lg font-mono text-sm bg-gray-50"><?= htmlspecialchars($template['body']) ?></textarea>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="flex justify-end pt-4">
                            <button class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition" disabled>
                                Сохранить шаблоны (в разработке)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Вкладка: Резервное копирование -->
                <div id="backup-tab" class="tab-content hidden">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Резервное копирование базы данных</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-blue-50 rounded-lg p-6 text-center">
                                <div class="text-4xl mb-3">💾</div>
                                <h3 class="font-semibold text-gray-800 mb-2">Создать резервную копию</h3>
                                <p class="text-sm text-gray-600 mb-4">Создание полной копии базы данных в формате SQL</p>
                                <button onclick="createBackup()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Создать бэкап
                                </button>
                            </div>
                            
                            <div class="bg-green-50 rounded-lg p-6 text-center">
                                <div class="text-4xl mb-3">📁</div>
                                <h3 class="font-semibold text-gray-800 mb-2">Восстановить из бэкапа</h3>
                                <p class="text-sm text-gray-600 mb-4">Восстановление данных из ранее созданной копии</p>
                                <input type="file" id="backupFile" accept=".sql,.zip" class="hidden">
                                <button onclick="document.getElementById('backupFile').click()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                    Выбрать файл
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t">
                            <h3 class="font-medium text-gray-800 mb-3">Последние резервные копии</h3>
                            <div id="backupList" class="space-y-2">
                                <p class="text-gray-500 text-sm">Список резервных копий будет отображаться здесь</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно для добавления праздника -->
    <div id="holidayModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold text-gray-800">Добавить праздничный день</h2>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_holiday">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата</label>
                    <input type="date" name="holiday_date" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название праздника</label>
                    <input type="text" name="holiday_name" placeholder="Например: Новый год" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeHolidayModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Отмена</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Добавить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Скрыть все вкладки
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Показать выбранную вкладку
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Обновить стиль кнопок
            const buttons = ['general', 'holidays', 'email', 'backup'];
            buttons.forEach(btn => {
                const btnEl = document.getElementById('tab-' + btn);
                if (btn === tabName) {
                    btnEl.classList.add('text-blue-600', 'border-blue-600');
                    btnEl.classList.remove('text-gray-500');
                } else {
                    btnEl.classList.remove('text-blue-600', 'border-blue-600');
                    btnEl.classList.add('text-gray-500');
                }
            });
        }
        
        function openHolidayModal() {
            document.getElementById('holidayModal').classList.remove('hidden');
            document.getElementById('holidayModal').classList.add('flex');
        }
        
        function closeHolidayModal() {
            document.getElementById('holidayModal').classList.add('hidden');
            document.getElementById('holidayModal').classList.remove('flex');
        }
        
        function createBackup() {
            fetch('/booking_system/admin/backup.php')
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'backup_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.sql';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    alert('Резервная копия создана успешно!');
                })
                .catch(error => {
                    alert('Ошибка при создании резервной копии');
                });
        }
        
        // Обработка загрузки файла для восстановления
        document.getElementById('backupFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && confirm('Восстановить данные из файла ' + file.name + '? Это действие необратимо!')) {
                const formData = new FormData();
                formData.append('backup_file', file);
                
                fetch('/booking_system/admin/restore.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => {
                    alert('Ошибка при восстановлении');
                });
            }
        });
    </script>
</body>
</html>