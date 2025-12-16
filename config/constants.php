<?php
// أكواد الحالة
define('SUCCESS', 'success');
define('ERROR', 'error');
define('WARNING', 'warning');
define('INFO', 'info');

// أدوار المستخدمين
define('ROLE_ADMIN', 'admin');
define('ROLE_MODERATOR', 'moderator');
define('ROLE_RESTAURANT_OWNER', 'restaurant_owner');
define('ROLE_MEMBER', 'member');

// حالات المهام
define('TASK_AVAILABLE', 'available');
define('TASK_ACTIVE', 'active');
define('TASK_COMPLETED', 'completed');
define('TASK_CANCELLED', 'cancelled');

// حالات طلبات المتجر
define('ORDER_PENDING', 'pending');
define('ORDER_PROCESSING', 'processing');
define('ORDER_COMPLETED', 'completed');
define('ORDER_CANCELLED', 'cancelled');

// حالات التقييمات
define('REVIEW_ACTIVE', 'active');
define('REVIEW_HIDDEN', 'hidden');
define('REVIEW_REPORTED', 'reported');
define('REVIEW_DELETED', 'deleted');

// فئات متجر النقاط
define('CATEGORY_MOBILE_BALANCE', 'mobile_balance');
define('CATEGORY_COUPONS', 'coupons');
define('CATEGORY_BANK_TRANSFER', 'bank_transfer');
define('CATEGORY_TICKETS', 'tickets');
define('CATEGORY_OTHER', 'other');

// أنواع المعاملات
define('TRANSACTION_EARN', 'earn');
define('TRANSACTION_REDEEM', 'redeem');
define('TRANSACTION_WITHDRAW', 'withdraw');
define('TRANSACTION_TRANSFER', 'transfer');
define('TRANSACTION_BONUS', 'bonus');

// المدن المتاحة
$cities = [
    'الرياض',
    'جدة',
    'الدمام',
    'مكة',
    'المدينة',
    'الطائف',
    'الأحساء',
    'تبوك',
    'القصيم',
    'حائل',
    'جازان',
    'نجران',
    'الباحة',
    'الجوف'
];

// ألوان التصميم الافتراضية
$default_colors = [
    'primary' => '#ff6b35',
    'secondary' => '#2a9d8f',
    'dark' => '#264653',
    'light' => '#f8f9fa',
    'success' => '#28a745',
    'warning' => '#ffc107',
    'danger' => '#dc3545',
    'info' => '#0ea5e9'
];
?>