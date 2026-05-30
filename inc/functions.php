<?php
require_once __DIR__ . '/../config/database.php';

// Проверка конфликта бронирований
function checkBookingConflict($resource_id, $booking_date, $start_time, $end_time, $exclude_booking_id = null) {
    $pdo = getDBConnection();
    
    $sql = "SELECT COUNT(*) FROM bookings 
            WHERE resource_id = ? 
            AND booking_date = ? 
            AND status NOT IN ('cancelled', 'completed')
            AND ((start_time < ? AND end_time > ?) 
                 OR (start_time < ? AND end_time > ?)
                 OR (start_time >= ? AND end_time <= ?))";
    
    $params = [$resource_id, $booking_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time];
    
    if ($exclude_booking_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_booking_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() > 0;
}

// Получение доступных слотов для ресурса
function getAvailableSlots($resource_id, $booking_date) {
    $pdo = getDBConnection();
    
    // Получаем все бронирования на эту дату
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM bookings 
                           WHERE resource_id = ? AND booking_date = ? 
                           AND status NOT IN ('cancelled', 'completed')");
    $stmt->execute([$resource_id, $booking_date]);
    $bookings = $stmt->fetchAll();
    
    // Генерируем все возможные слоты (9:00 - 18:00, шаг 30 мин)
    $slots = [];
    $start = strtotime('09:00');
    $end = strtotime('18:00');
    
    for ($time = $start; $time < $end; $time += 1800) {
        $slot_start = date('H:i', $time);
        $slot_end = date('H:i', $time + 1800);
        
        $is_busy = false;
        foreach ($bookings as $booking) {
            if ($slot_start < $booking['end_time'] && $slot_end > $booking['start_time']) {
                $is_busy = true;
                break;
            }
        }
        
        $slots[] = [
            'start' => $slot_start,
            'end' => $slot_end,
            'status' => $is_busy ? 'busy' : 'free'
        ];
    }
    
    return $slots;
}

// Отправка email-уведомления
function sendEmailNotification($to, $subject, $message) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: no-reply@booking-system.com\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Отправка уведомления о бронировании
function sendBookingNotification($booking_id, $status) {
    $pdo = getDBConnection();
    
    // Получаем данные о бронировании
    $stmt = $pdo->prepare("
        SELECT b.*, u.email, u.last_name, u.first_name, r.name as resource_name 
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN resources r ON b.resource_id = r.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) return false;
    
    $status_text = [
        'pending' => 'ожидает подтверждения',
        'confirmed' => 'подтверждено',
        'cancelled' => 'отменено',
        'completed' => 'завершено'
    ];
    
    $subject = "Бронирование ресурса «{$booking['resource_name']}» - " . $status_text[$status];
    
    $message = "
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Уважаемый(ая) {$booking['last_name']} {$booking['first_name']}!</h2>
            <p>Ваше бронирование ресурса <strong>{$booking['resource_name']}</strong> было <strong>{$status_text[$status]}</strong>.</p>
            <h3>Детали бронирования:</h3>
            <ul>
                <li>Дата: " . date('d.m.Y', strtotime($booking['booking_date'])) . "</li>
                <li>Время: {$booking['start_time']} - {$booking['end_time']}</li>
                <li>Цель: {$booking['purpose']}</li>
            </ul>
            <p>С уважением,<br>Система бронирования ресурсов</p>
        </body>
        </html>
    ";
    
    return sendEmailNotification($booking['email'], $subject, $message);
}

// Логирование действий пользователя
function logAction($user_id, $action) {
    $pdo = getDBConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    return $stmt->execute([$user_id, $action, $ip]);
}

// Проверка прав доступа
function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
}

function requireRole($role) {
    requireAuth();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        header('Location: /');
        exit;
    }
}
?>