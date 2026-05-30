<?php require_once __DIR__ . '/../inc/header.php'; ?>

<div class="min-h-screen bg-gray-50">
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Каталог ресурсов</h1>

            <div class="space-y-4">
                <div class="relative">
                    <input type="text"
                           id="searchInput"
                           placeholder="Поиск по названию ресурса..."
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="flex flex-wrap gap-4">
                    <select id="typeFilter" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="all">Все типы</option>
                        <option value="meeting-room">Переговорная</option>
                        <option value="equipment">Оборудование</option>
                        <option value="transport">Транспорт</option>
                        <option value="parking">Парковка</option>
                    </select>

                    <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="all">Все статусы</option>
                        <option value="available">Доступен</option>
                        <option value="busy">Занят</option>
                        <option value="maintenance">На обслуживании</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="resultsCount" class="mb-4 text-gray-600"></div>
        <div id="resourcesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
    </div>
</div>

<script>
const resources = [
    { id: 1, name: "Переговорная «Альфа»", type: "meeting-room", typeLabel: "Переговорная", description: "Просторная комната на 12 человек с проектором", status: "available", availability: "Доступна сегодня: 14:00-18:00" },
    { id: 2, name: "Переговорная «Бета»", type: "meeting-room", typeLabel: "Переговорная", description: "Уютная комната на 6 человек", status: "available", availability: "Доступна сегодня: весь день" },
    { id: 3, name: "Toyota Camry", type: "transport", typeLabel: "Транспорт", description: "Служебный автомобиль", status: "available", availability: "Доступен после 15:00" },
    { id: 4, name: "Проектор Epson", type: "equipment", typeLabel: "Оборудование", description: "Проектор для презентаций", status: "available", availability: "Доступен" },
    { id: 5, name: "Парковочное место №15", type: "parking", typeLabel: "Парковка", description: "Крытое парковочное место", status: "available", availability: "Доступно" }
];

function getStatusColor(status) {
    return status === 'available' ? 'bg-green-500' : status === 'busy' ? 'bg-orange-500' : 'bg-gray-500';
}

function getStatusLabel(status) {
    return status === 'available' ? 'Доступен' : status === 'busy' ? 'Занят' : 'На обслуживании';
}

function renderResources() {
    const searchQuery = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;

    const filtered = resources.filter(r => {
        return r.name.toLowerCase().includes(searchQuery) &&
               (typeFilter === 'all' || r.type === typeFilter) &&
               (statusFilter === 'all' || r.status === statusFilter);
    });

    document.getElementById('resultsCount').innerHTML = `Найдено ресурсов: ${filtered.length}`;
    
    const grid = document.getElementById('resourcesGrid');
    if (filtered.length === 0) {
        grid.innerHTML = '<div class="text-center py-12"><p class="text-gray-500 text-lg">Ресурсы не найдены</p></div>';
        return;
    }

    grid.innerHTML = filtered.map(r => `
        <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow p-6">
            <div class="flex items-start justify-between mb-3">
                <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded">${r.typeLabel}</span>
                <div class="flex items-center">
                    <div class="w-2 h-2 rounded-full ${getStatusColor(r.status)}"></div>
                    <span class="ml-2 text-xs text-gray-600">${getStatusLabel(r.status)}</span>
                </div>
            </div>
            <h3 class="font-bold text-lg text-gray-900 mb-2">${r.name}</h3>
            <p class="text-sm text-gray-600 mb-4">${r.description}</p>
            <div class="text-sm text-gray-500 mb-4">${r.availability}</div>
            <a href="/resources/${r.id}" class="block w-full text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">Подробнее</a>
        </div>
    `).join('');
}

document.getElementById('searchInput').addEventListener('input', renderResources);
document.getElementById('typeFilter').addEventListener('change', renderResources);
document.getElementById('statusFilter').addEventListener('change', renderResources);
renderResources();
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>