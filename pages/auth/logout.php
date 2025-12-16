<?php
session_start();

// تسجيل خروج المستخدم
if (isset($_SESSION['user_id'])) {
    // تسجيل سجل النشاط
    if (file_exists('../config/database.php')) {
        require_once '../config/database.php';
        require_once '../classes/Database.php';
        
        $db = new Database();
        $user_id = $_SESSION['user_id'];
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                VALUES (?, 'logout', 'تم تسجيل الخروج من النظام', ?, ?)";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iss", $user_id, $ip, $user_agent);
        $stmt->execute();
    }
}

// حذف جميع بيانات الجلسة
$_SESSION = array();

// إذا تم استخدام كوكيز الجلسة، حذفها
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// حذف كوكيز تذكرني
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// تدمير الجلسة
session_destroy();

// توجيه المستخدم للصفحة الرئيسية
header('Location: /');
exit;
?>