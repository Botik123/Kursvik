<?php
// Проверка сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: /booking_system/login');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Получение данных пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Получение бронирований пользователя
$stmt = $pdo->prepare("
    SELECT b.*, r.name as resource_name 
    FROM bookings b
    JOIN resources r ON b.resource_id = r.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC, b.start_time DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// Отмена бронирования
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = $_GET['cancel'];
    
    // Проверяем, можно ли отменить
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? AND status = 'confirmed'");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        $booking_time = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
        $now = time();
        
        if (($booking_time - $now) > 3600) { // больше часа до начала
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$booking_id]);
            sendBookingNotification($booking_id, 'cancelled');
            logAction($user_id, 'cancel_booking_' . $booking_id);
            header('Location: /booking_system/profile?msg=cancelled');
            exit;
        }
    }
}

// Подключаем хедер
require_once __DIR__ . '/../inc/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Боковое меню -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto text-white text-2xl">
                    <?= mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1) ?>
                </div>
                <h3 class="font-bold text-lg mt-3"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($user['position'] ?? 'Сотрудник') ?></p>
            </div>
            <nav class="space-y-2">
                <a href="/booking_system/profile" class="block px-4 py-2 bg-blue-50 text-blue-600 rounded-lg">Мои бронирования</a>
                <a href="/booking_system/logout" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Выйти</a>
            </nav>
        </div>
        
        <!-- Основной контент -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-6">Мои бронирования</h2>
                
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled'): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                        Бронирование успешно отменено
                    </div>
                <?php endif; ?>
                
                <?php if (empty($bookings)): ?>
                    <p class="text-gray-500 text-center py-8">У вас пока нет бронирований</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($bookings as $booking): ?>
                            <div class="border rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-bold text-lg"><?= htmlspecialchars($booking['resource_name']) ?></h3>
                                        <p class="text-gray-600 text-sm">
                                            <?= date('d.m.Y', strtotime($booking['booking_date'])) ?> • 
                                            <?= substr($booking['start_time'], 0, 5) ?> - <?= substr($booking['end_time'], 0, 5) ?>
                                        </p>
                                        <p class="text-gray-600 text-sm mt-2">Цель: <?= htmlspecialchars($booking['purpose']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium
                                            <?= $booking['status'] === 'confirmed' ? 'bg-green-100 text-green-700' : '' ?>
                                            <?= $booking['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' ?>
                                            <?= $booking['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : '' ?>
                                            <?= $booking['status'] === 'completed' ? 'bg-gray-100 text-gray-700' : '' ?>
                                        ">
                                            <?= $booking['status'] === 'confirmed' ? 'Подтверждено' : '' ?>
                                            <?= $booking['status'] === 'pending' ? 'Ожидает' : '' ?>
                                            <?= $booking['status'] === 'cancelled' ? 'Отменено' : '' ?>
                                            <?= $booking['status'] === 'completed' ? 'Завершено' : '' ?>
                                        </span>
                                        
                                        <?php if ($booking['status'] === 'confirmed'): ?>
                                            <?php
                                            $booking_time = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
                                            $can_cancel = ($booking_time - time()) > 3600;
                                            ?>
                                            <?php if ($can_cancel): ?>
                                                <a href="?cancel=<?= $booking['id'] ?>" 
                                                   class="block mt-2 text-red-600 text-sm hover:underline"
                                                   onclick="return confirm('Отменить бронирование?')">
                                                    Отменить
                                                </a>
                                            <?php else: ?>
                                                <p class="text-xs text-gray-500 mt-2">Отмена недоступна</p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>