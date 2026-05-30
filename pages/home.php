<?php require_once __DIR__ . '/../inc/header.php'; ?>

<div>
    <!-- Hero секция -->
    <section class="bg-gradient-to-br from-blue-600 to-blue-800 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-5xl font-bold mb-6">
                    Система бронирования корпоративных ресурсов
                </h1>
                <p class="text-xl mb-8 text-blue-100">
                    Упростите процесс планирования и использования общих ресурсов
                    вашей компании. Переговорные комнаты, оборудование, транспорт и
                    парковочные места — всё в одном месте.
                </p>
                <a href="/resources"
                   class="inline-flex items-center space-x-2 px-8 py-4 bg-white text-blue-600 rounded-lg font-semibold hover:bg-blue-50 transition-colors">
                    <span>Перейти к ресурсам</span>
                    <span>→</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Возможности системы -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-900">
                Возможности системы
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php
                $features = [
                    ['📅', 'Простое бронирование', 'Интуитивный календарь для быстрого выбора времени и ресурсов'],
                    ['👥', 'Управление доступом', 'Гибкая система ролей для сотрудников и администраторов'],
                    ['⚡', 'Мгновенные уведомления', 'Email-оповещения о статусе бронирования и напоминания'],
                    ['🛡️', 'Отчёты и аналитика', 'Подробная статистика использования ресурсов и активности']
                ];
                foreach ($features as $feature): ?>
                <div class="text-center">
                    <div class="text-4xl mb-4"><?= $feature[0] ?></div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-900"><?= $feature[1] ?></h3>
                    <p class="text-gray-600"><?= $feature[2] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Популярные ресурсы -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Популярные ресурсы</h2>
                <a href="/resources" class="text-blue-600 hover:text-blue-700 font-semibold flex items-center space-x-2">
                    <span>Все ресурсы</span>
                    <span>→</span>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php
                $popularResources = [
                    ['id' => 1, 'name' => 'Переговорная «Альфа»', 'type' => 'Переговорная', 'desc' => 'Просторная комната на 12 человек с проектором'],
                    ['id' => 2, 'name' => 'Toyota Camry', 'type' => 'Транспорт', 'desc' => 'Служебный автомобиль для деловых поездок'],
                    ['id' => 3, 'name' => 'Проектор Epson EB-2250U', 'type' => 'Оборудование', 'desc' => 'Высококачественный проектор для презентаций'],
                    ['id' => 4, 'name' => 'Парковочное место №15', 'type' => 'Парковка', 'desc' => 'Крытое парковочное место возле входа']
                ];
                foreach ($popularResources as $resource): ?>
                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow p-6">
                    <div class="flex items-start justify-between mb-3">
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded"><?= $resource['type'] ?></span>
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="ml-2 text-xs text-gray-600">Доступен</span>
                        </div>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2"><?= $resource['name'] ?></h3>
                    <p class="text-sm text-gray-600 mb-4"><?= $resource['desc'] ?></p>
                    <a href="/resources/<?= $resource['id'] ?>" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">
                        Подробнее →
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA секция -->
    <section class="py-16 bg-blue-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold mb-4">Готовы начать использовать систему?</h2>
            <p class="text-xl text-blue-100 mb-8">Зарегистрируйтесь сейчас и получите полный доступ ко всем функциям</p>
            <a href="/register" class="inline-block px-8 py-4 bg-white text-blue-600 rounded-lg font-semibold hover:bg-blue-50 transition-colors">
                Зарегистрироваться
            </a>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>