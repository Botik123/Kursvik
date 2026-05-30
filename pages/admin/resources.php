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

// Получение списка ресурсов
$stmt = $pdo->prepare("
    SELECT r.*, rt.name as type_name 
    FROM resources r
    LEFT JOIN resource_types rt ON r.type_id = rt.id
    ORDER BY r.created_at DESC
");
$stmt->execute();
$resources = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM resource_types ORDER BY sort_order");
$resource_types = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление ресурсами</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar (такая же как в dashboard.php) -->
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
                    <a href="/booking_system/admin/resources" class="sidebar-item active flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 mb-1">
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
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Управление ресурсами</h1>
                        <p class="text-gray-500 mt-1">Добавление, редактирование и удаление ресурсов</p>
                    </div>
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg flex items-center gap-2 transition">
                        <i class="ri-add-line"></i>
                        Добавить ресурс
                    </button>
                </div>

                <!-- Resources Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($resources as $resource): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <span class="px-2.5 py-1 text-xs rounded-full bg-blue-100 text-blue-700">
                                    <?= htmlspecialchars($resource['type_name'] ?? 'Без типа') ?>
                                </span>
                                <span class="px-2.5 py-1 text-xs rounded-full
                                    <?= $resource['status'] === 'available' ? 'bg-green-100 text-green-700' : '' ?>
                                    <?= $resource['status'] === 'busy' ? 'bg-orange-100 text-orange-700' : '' ?>
                                    <?= $resource['status'] === 'maintenance' ? 'bg-red-100 text-red-700' : '' ?>
                                ">
                                    <?= $resource['status'] === 'available' ? 'Доступен' : '' ?>
                                    <?= $resource['status'] === 'busy' ? 'Занят' : '' ?>
                                    <?= $resource['status'] === 'maintenance' ? 'На ремонте' : '' ?>
                                </span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-2"><?= htmlspecialchars($resource['name']) ?></h3>
                            <p class="text-gray-500 text-sm line-clamp-2 mb-4"><?= htmlspecialchars(mb_substr($resource['description'] ?? '', 0, 100)) ?>...</p>
                            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                <div class="flex items-center gap-3 text-sm text-gray-500">
                                    <span class="flex items-center gap-1"><i class="ri-group-line"></i> <?= $resource['capacity'] ?> чел.</span>
                                    <span class="flex items-center gap-1"><i class="ri-map-pin-line"></i> <?= htmlspecialchars($resource['location'] ?? '—') ?></span>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="editResource(<?= htmlspecialchars(json_encode($resource)) ?>)" class="text-blue-600 hover:text-blue-800">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button onclick="deleteResource(<?= $resource['id'] ?>, '<?= htmlspecialchars($resource['name']) ?>')" class="text-red-600 hover:text-red-800">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно -->
    <div id="resourceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-800">Добавить ресурс</h2>
            </div>
            <form id="resourceForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="resourceId" value="0">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                    <input type="text" name="name" id="resourceName" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                    <textarea name="description" id="resourceDescription" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Тип ресурса</label>
                        <select name="type_id" id="resourceType" class="w-full px-3 py-2 border rounded-lg">
                            <?php foreach ($resource_types as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Вместимость</label>
                        <input type="number" name="capacity" id="resourceCapacity" value="1" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Расположение</label>
                    <input type="text" name="location" id="resourceLocation" class="w-full px-3 py-2 border rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Оснащение</label>
                    <textarea name="features" id="resourceFeatures" rows="2" class="w-full px-3 py-2 border rounded-lg" placeholder="Перечислите через запятую"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Правила использования</label>
                    <textarea name="rules" id="resourceRules" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                    <select name="status" id="resourceStatus" class="w-full px-3 py-2 border rounded-lg">
                        <option value="available">Доступен</option>
                        <option value="busy">Занят</option>
                        <option value="maintenance">На ремонте</option>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Отмена</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

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
        }
    </style>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Добавить ресурс';
            document.getElementById('formAction').value = 'add';
            document.getElementById('resourceId').value = '0';
            document.getElementById('resourceName').value = '';
            document.getElementById('resourceDescription').value = '';
            document.getElementById('resourceCapacity').value = '1';
            document.getElementById('resourceLocation').value = '';
            document.getElementById('resourceFeatures').value = '';
            document.getElementById('resourceRules').value = '';
            document.getElementById('resourceStatus').value = 'available';
            document.getElementById('resourceModal').classList.remove('hidden');
            document.getElementById('resourceModal').classList.add('flex');
        }
        
        function editResource(resource) {
            document.getElementById('modalTitle').textContent = 'Редактировать ресурс';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('resourceId').value = resource.id;
            document.getElementById('resourceName').value = resource.name;
            document.getElementById('resourceDescription').value = resource.description || '';
            document.getElementById('resourceType').value = resource.type_id;
            document.getElementById('resourceCapacity').value = resource.capacity;
            document.getElementById('resourceLocation').value = resource.location || '';
            document.getElementById('resourceFeatures').value = resource.features || '';
            document.getElementById('resourceRules').value = resource.rules || '';
            document.getElementById('resourceStatus').value = resource.status;
            document.getElementById('resourceModal').classList.remove('hidden');
            document.getElementById('resourceModal').classList.add('flex');
        }
        
        function deleteResource(id, name) {
            if (confirm(`Удалить ресурс "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('resourceModal').classList.add('hidden');
            document.getElementById('resourceModal').classList.remove('flex');
        }
    </script>
</body>
</html>