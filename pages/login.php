<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /booking_system/');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];
        
        logAction($user['id'], 'user_login');
        
        header('Location: /booking_system/');
        exit;
    } else {
        $error = 'Неверный email или пароль';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Система бронирования</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto text-white text-2xl">📅</div>
                <h2 class="text-3xl font-bold text-gray-900 mt-4">Вход в систему</h2>
                <p class="text-gray-600">Используйте корпоративную почту</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Пароль</label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                        Войти
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">Нет аккаунта? <a href="/booking_system/register" class="text-blue-600 hover:text-blue-700">Зарегистрироваться</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>