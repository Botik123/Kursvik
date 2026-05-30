RewriteEngine On
RewriteBase /booking_system/

# Если файл или папка существуют, не перенаправлять
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Все запросы перенаправлять на index.php
RewriteRule ^(.*)$ index.php [QSA,L]
