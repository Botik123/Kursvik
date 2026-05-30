<?php
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: /booking_system/login');
    exit;
}

$resource_id = $_GET['resource_id'] ?? 0;
$pdo = getDBConnection();

// Получение информации о ресурсе
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch();

if (!$resource) {
    header('Location: /booking_system/resources');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_date = $_POST['booking_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    
    // Валидация
    if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
        $error = 'Дата не может быть в прошлом';
    } elseif ($start_time >= $end_time) {
        $error = 'Время окончания должно быть позже времени начала';
    } elseif (checkBookingConflict($resource_id, $booking_date, $start_time, $end_time)) {
        $error = 'Выбранное время уже занято. Пожалуйста, выберите другое время.';
    } else {
        // Создание бронирования
        $stmt = $pdo->prepare("
            INSERT INTO bookings (resource_id, user_id, booking_date, start_time, end_time, purpose, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([$resource_id, $_SESSION['user_id'], $booking_date, $start_time, $end_time, $purpose])) {
            $booking_id = $pdo->lastInsertId();
            sendBookingNotification($booking_id, 'pending');
            logAction($_SESSION['user_id'], 'create_booking_' . $booking_id);
            $success = 'Бронирование создано! Ожидайте подтверждения на email.';
        } else {
            $error = 'Ошибка при создании бронирования';
        }
    }
}
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-4">Бронирование ресурса</h1>
        <p class="text-gray-600 mb-6"><?= htmlspecialchars($resource['name']) ?></p>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?= htmlspecialchars($success) ?>
                <a href="/booking_system/profile" class="underline ml-2">Перейти к бронированиям</a>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Дата</label>
                <input type="date" name="booking_date" required min="<?= date('Y-m-d') ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Время начала</label>
                    <select name="start_time" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Выберите время</option>
                        <?php for ($h = 9; $h < 18; $h++): ?>
                            <?php for ($m = 0; $m < 60; $m += 30): ?>
                                <option value="<?= sprintf('%02d:%02d', $h, $m) ?>">
                                    <?= sprintf('%02d:%02d', $h, $m) ?>
                                </option>
                            <?php endfor; ?>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Время окончания</label>
                    <select name="end_time" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Выберите время</option>
                        <?php for ($h = 9; $h <= 18; $h++): ?>
                            <?php for ($m = 0; $m < 60; $m += 30): ?>
                                <?php if (!($h == 18 && $m > 0)): ?>
                                    <option value="<?= sprintf('%02d:%02d', $h, $m) ?>">
                                        <?= sprintf('%02d:%02d', $h, $m) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endfor; ?>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Цель использования</label>
                <textarea name="purpose" rows="3" required class="w-full px-4 py-2 border rounded-lg"></textarea>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                Забронировать
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>