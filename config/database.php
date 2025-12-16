<?php
// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'nuqtaha');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع
define('SITE_NAME', 'نقطها');
define('SITE_URL', 'http://localhost/nuqtaha/');
define('SITE_EMAIL', 'info@nuqtaha.com');

// إعدادات الأمان
define('SECRET_KEY', 'your-secret-key-here-change-in-production');
define('JWT_SECRET', 'your-jwt-secret-change-in-production');
define('ENCRYPTION_KEY', 'your-encryption-key-here');

// إعدادات التحميل
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// إعدادات الجلسة
define('SESSION_LIFETIME', 24 * 60 * 60); // 24 ساعة

// إعدادات التطبيق
define('POINTS_PER_REVIEW', 100);
define('MAX_TASKS_PER_USER', 3);
define('TASK_EXPIRY_HOURS', 24);
?>