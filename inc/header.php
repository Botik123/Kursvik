<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Базовый путь для ссылок
$base = '/booking_system/';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система бронирования ресурсов</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body>
    <nav class="bg-white shadow-md border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="<?= $base ?>" class="text-xl font-bold text-gray-800">📅 Бронирование</a>
                    <div class="hidden md:flex space-x-6">
                        <a href="<?= $base ?>" class="text-gray-600 hover:text-blue-600">Главная</a>
                        <a href="<?= $base ?>resources" class="text-gray-600 hover:text-blue-600">Ресурсы</a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?= $base ?>profile" class="text-gray-600 hover:text-blue-600">Мои бронирования</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="text-gray-600"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <a href="<?= $base ?>admin" class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm">Админка</a>
                        <?php endif; ?>
                        <a href="<?= $base ?>logout" class="text-red-600 hover:text-red-700">Выйти</a>
                    <?php else: ?>
                        <a href="<?= $base ?>login" class="text-blue-600 hover:text-blue-700">Вход</a>
                        <a href="<?= $base ?>register" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Регистрация</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main>