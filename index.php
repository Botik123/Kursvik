<?php
// Автоматически определяем базовый путь
define('BASE_PATH', '/booking_system/');

// Подключаем файлы
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/inc/functions.php';

// Получаем URI и убираем базовый путь
$request_uri = $_SERVER['REQUEST_URI'];
$request_uri = str_replace(BASE_PATH, '', $request_uri);
$request_uri = rtrim($request_uri, '/');
if ($request_uri === '') $request_uri = '/';

// Маршруты
switch ($request_uri) {
    // ========== ПУБЛИЧНЫЕ СТРАНИЦЫ ==========
    case '/':
    case 'index.php':
        require_once __DIR__ . '/pages/home.php';
        break;
    
    // Авторизация
    case 'login':
        require_once __DIR__ . '/pages/login.php';
        break;
    
    case 'register':
        require_once __DIR__ . '/pages/register.php';
        break;
    
    case 'logout':
        require_once __DIR__ . '/pages/logout.php';
        break;
    
    // Личный кабинет
    case 'profile':
        require_once __DIR__ . '/pages/profile.php';
        break;
    
    // Ресурсы
    case 'resources':
        require_once __DIR__ . '/pages/resources.php';
        break;
    
    // ========== АДМИН-ПАНЕЛЬ ==========
    // Главная админки
    case 'admin':
        require_once __DIR__ . '/pages/admin/dashboard.php';
        break;
    
    // Управление ресурсами
    case 'admin/resources':
        require_once __DIR__ . '/pages/admin/resources.php';
        break;
    
    // Управление пользователями
    case 'admin/users':
        require_once __DIR__ . '/pages/admin/users.php';
        break;
    
    // Управление бронированиями
    case 'admin/bookings':
        require_once __DIR__ . '/pages/admin/bookings.php';
        break;
    
    // Отчёты
    case 'admin/reports':
        require_once __DIR__ . '/pages/admin/reports.php';
        break;
    
    // Экспорт отчётов (API)
    case 'admin/export':
        require_once __DIR__ . '/pages/admin/export.php';
        break;
    
    // Настройки
    case 'admin/settings':
        require_once __DIR__ . '/pages/admin/settings.php';
        break;
    
    // Логи
    case 'admin/logs':
        require_once __DIR__ . '/pages/admin/logs.php';
        break;
    
    // ========== ДИНАМИЧЕСКИЕ МАРШРУТЫ ==========
    default:
        // Детальная страница ресурса: /resources/1
        if (preg_match('#^resources/(\d+)$#', $request_uri, $matches)) {
            $_GET['id'] = $matches[1];
            require_once __DIR__ . '/pages/resource-detail.php';
        } 
        // Страница бронирования: /booking/1
        elseif (preg_match('#^booking/(\d+)$#', $request_uri, $matches)) {
            $_GET['resource_id'] = $matches[1];
            require_once __DIR__ . '/pages/booking.php';
        }
        else {
            require_once __DIR__ . '/pages/not-found.php';
        }
        break;
}
?>