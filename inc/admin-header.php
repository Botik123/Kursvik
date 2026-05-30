<nav class="bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-8">
                <a href="/admin" class="text-xl font-bold">📊 Admin Panel</a>
                <div class="hidden md:flex space-x-6">
                    <a href="/admin" class="hover:text-gray-300">Дашборд</a>
                    <a href="/admin/resources" class="hover:text-gray-300">Ресурсы</a>
                    <a href="/admin/users" class="hover:text-gray-300">Пользователи</a>
                    <a href="/admin/bookings" class="hover:text-gray-300">Бронирования</a>
                    <a href="/admin/reports" class="hover:text-gray-300">Отчёты</a>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="/" class="text-gray-300 hover:text-white text-sm">На сайт</a>
                <a href="/logout" class="text-red-400 hover:text-red-300 text-sm">Выйти</a>
            </div>
        </div>
    </div>
</nav>