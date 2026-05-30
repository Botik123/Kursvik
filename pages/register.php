<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /booking_system/');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Валидация
    if (empty($last_name) || empty($first_name) || empty($email) || empty($password)) {
        $error = 'Пожалуйста, заполните все обязательные поля';
    } elseif ($password !== $confirm_password) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 8) {
        $error = 'Пароль должен содержать минимум 8 символов';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Пароль должен содержать заглавную букву и цифру';
    } else {
        $pdo = getDBConnection();
        
        // Проверка существования email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким email уже существует';
        } else {
            // Создание пользователя
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (last_name, first_name, middle_name, email, phone, position, department, password, role, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user', 'active')
            ");
            
            if ($stmt->execute([$last_name, $first_name, $middle_name, $email, $phone, $position, $department, $hashed_password])) {
                $success = 'Регистрация успешна! Теперь вы можете войти в систему.';
                // Очищаем POST данные
                $_POST = [];
            } else {
                $error = 'Ошибка при регистрации. Попробуйте позже.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Система бронирования</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4">
        <div class="max-w-2xl mx-auto">
            <div class="text-center mb-8">
                <div class="text-4xl mb-2">📅</div>
                <h2 class="text-3xl font-bold text-gray-900">Регистрация</h2>
                <p class="text-gray-600">Создайте аккаунт для доступа к системе</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                        <?= htmlspecialchars($success) ?>
                        <a href="/booking_system/login" class="underline ml-2">Войти</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Фамилия *</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Имя *</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required 
                                   class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Отчество</label>
                            <input type="text" name="middle_name" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Корпоративный Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required 
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Контактный телефон *</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required 
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Должность *</label>
                            <input type="text" name="position" value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" required 
                                   class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Отдел *</label>
                            <select name="department" required class="w-full px-3 py-2 border rounded-lg">
                                <option value="">Выберите отдел</option>
                                <option value="IT" <?= ($_POST['department'] ?? '') === 'IT' ? 'selected' : '' ?>>IT отдел</option>
                                <option value="HR" <?= ($_POST['department'] ?? '') === 'HR' ? 'selected' : '' ?>>HR отдел</option>
                                <option value="Sales" <?= ($_POST['department'] ?? '') === 'Sales' ? 'selected' : '' ?>>Отдел продаж</option>
                                <option value="Marketing" <?= ($_POST['department'] ?? '') === 'Marketing' ? 'selected' : '' ?>>Отдел маркетинга</option>
                                <option value="Finance" <?= ($_POST['department'] ?? '') === 'Finance' ? 'selected' : '' ?>>Финансовый отдел</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Пароль *</label>
                            <input type="password" name="password" required 
                                   class="w-full px-3 py-2 border rounded-lg">
                            <p class="text-xs text-gray-500 mt-1">Минимум 8 символов, заглавная буква и цифра</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Подтверждение пароля *</label>
                            <input type="password" name="confirm_password" required 
                                   class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                        Зарегистрироваться
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">Уже есть аккаунт? <a href="/booking_system/login" class="text-blue-600 hover:underline">Войти</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>