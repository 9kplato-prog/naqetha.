<?php
// بدء الجلسة
session_start();

// تحديد الثوابت
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/");
define('ROOT_PATH', __DIR__);
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', BASE_URL . 'assets/');

// تضمين ملفات الإعدادات
require_once 'config/database.php';
require_once 'config/constants.php';

// تضمين الفئات
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Restaurant.php';
require_once 'classes/Admin.php';

// تضمين الدوال المساعدة
require_once 'includes/functions.php';

// التحقق من التثبيت
if (!file_exists('config/database.php')) {
    header('Location: install.php');
    exit;
}

// ربط قاعدة البيانات
$db = new Database();

// تحديد المستخدم الحالي
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = new User($db->getConnection());
    $user->getById($_SESSION['user_id']);
}

// توجيه الطلبات
$url = isset($_GET['url']) ? $_GET['url'] : 'landing';
$url_parts = explode('/', trim($url, '/'));
$page = $url_parts[0];

// تحديد الصفحة بناءً على الرابط
switch ($page) {
    case 'login':
        require 'pages/auth/login.php';
        break;
    case 'register':
        require 'pages/auth/register.php';
        break;
    case 'logout':
        require 'pages/auth/logout.php';
        break;
    case 'admin':
        if (!$user || $user->role !== 'admin') {
            header('Location: /login');
            exit;
        }
        $subpage = isset($url_parts[1]) ? $url_parts[1] : 'dashboard';
        require "pages/admin/$subpage.php";
        break;
    case 'member':
        if (!$user || ($user->role !== 'member' && $user->role !== 'moderator')) {
            header('Location: /login');
            exit;
        }
        $subpage = isset($url_parts[1]) ? $url_parts[1] : 'dashboard';
        require "pages/member/$subpage.php";
        break;
    case 'owner':
        if (!$user || $user->role !== 'restaurant_owner') {
            header('Location: /login');
            exit;
        }
        $subpage = isset($url_parts[1]) ? $url_parts[1] : 'dashboard';
        require "pages/owner/$subpage.php";
        break;
    case 'api':
        $api_file = isset($url_parts[1]) ? $url_parts[1] : '';
        if (file_exists("api/$api_file.php")) {
            require "api/$api_file.php";
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'API not found']);
        }
        break;
    case 'restaurant':
        require 'pages/landing/restaurant-details.php';
        break;
    case 'restaurants':
        require 'pages/landing/restaurants.php';
        break;
    default:
        require 'pages/landing/index.php';
}
?>