<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';

session_start();

$db = new Database();
$response = ['status' => 'error', 'message' => 'Invalid request'];

// التحقق من صلاحيات الأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'غير مصرح لك بالوصول';
    echo json_encode($response);
    exit;
}

$admin = new Admin($db->getConnection(), $_SESSION['user_id']);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-dashboard-stats':
        $stats = $admin->getDashboardStats();
        $response = ['status' => 'success', 'data' => $stats];
        break;
        
    case 'get-restaurants':
        $filters = [
            'status' => $_GET['status'] ?? '',
            'city' => $_GET['city'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        $limit = $_GET['limit'] ?? 20;
        $offset = $_GET['offset'] ?? 0;
        
        $restaurants = $admin->getRestaurants($filters, $limit, $offset);
        $response = ['status' => 'success', 'data' => $restaurants];
        break;
        
    case 'get-restaurant':
        $restaurant_id = $_GET['id'] ?? 0;
        
        $sql = "SELECT r.*, c.name as category_name, u.name as owner_name, 
                       u.email as owner_email, u.phone as owner_phone
                FROM restaurants r 
                LEFT JOIN categories c ON r.category_id = c.id 
                LEFT JOIN users u ON r.owner_id = u.id 
                WHERE r.id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $restaurant = $result->fetch_assoc();
        
        if ($restaurant) {
            $response = ['status' => 'success', 'data' => $restaurant];
        } else {
            $response['message'] = 'المطعم غير موجود';
        }
        break;
        
    case 'get-restaurant-permissions':
        $restaurant_id = $_GET['restaurant_id'] ?? 0;
        
        $sql = "SELECT * FROM restaurant_permissions WHERE restaurant_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $permissions = $result->fetch_assoc();
        
        $response = ['status' => 'success', 'data' => $permissions ?: [
            'max_discount' => 30,
            'max_tasks' => 10,
            'can_feature' => 0,
            'can_priority' => 0,
            'custom_settings' => null
        ]];
        break;
        
    case 'update-restaurant-permissions':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($admin->manageRestaurant($data['restaurant_id'], 'update_permissions', $data)) {
            $response = ['status' => 'success', 'message' => 'تم تحديث الصلاحيات بنجاح'];
        } else {
            $response['message'] = 'حدث خطأ أثناء تحديث الصلاحيات';
        }
        break;
        
    case 'get-restaurant-owners':
        $sql = "SELECT id, name, email FROM users WHERE role = 'restaurant_owner' ORDER BY name";
        $result = $db->getConnection()->query($sql);
        
        $owners = [];
        while ($row = $result->fetch_assoc()) {
            $owners[] = $row;
        }
        
        $response = ['status' => 'success', 'data' => $owners];
        break;
        
    case 'get-category':
        $category_id = $_GET['id'] ?? 0;
        
        $sql = "SELECT * FROM categories WHERE id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        
        if ($category) {
            $response = ['status' => 'success', 'data' => $category];
        } else {
            $response['message'] = 'التصنيف غير موجود';
        }
        break;
        
    case 'recent-activity':
        $limit = $_GET['limit'] ?? 5;
        
        $sql = "SELECT al.*, u.name as user_name 
                FROM activity_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                ORDER BY al.created_at DESC 
                LIMIT ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        $response = ['status' => 'success', 'data' => $activities];
        break;
        
    case 'new-users-today':
        $sql = "SELECT id, name, email, created_at FROM users 
                WHERE DATE(created_at) = CURDATE() 
                ORDER BY created_at DESC";
        $result = $db->getConnection()->query($sql);
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        $response = ['status' => 'success', 'data' => $users];
        break;
        
    case 'new-restaurants-today':
        $sql = "SELECT id, name, city, created_at FROM restaurants 
                WHERE DATE(created_at) = CURDATE() 
                ORDER BY created_at DESC";
        $result = $db->getConnection()->query($sql);
        
        $restaurants = [];
        while ($row = $result->fetch_assoc()) {
            $restaurants[] = $row;
        }
        
        $response = ['status' => 'success', 'data' => $restaurants];
        break;
        
    case 'stats':
        $period = $_GET['period'] ?? '7days';
        $days = $period === '7days' ? 7 : ($period === '30days' ? 30 : ($period === '90days' ? 90 : 365));
        
        // إحصائيات المستخدمين
        $users_data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $sql = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $users_data['labels'][] = date('d/m', strtotime($date));
            $users_data['data'][] = $row['count'];
        }
        
        // إحصائيات التقييمات
        $reviews_data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $sql = "SELECT COUNT(*) as count FROM reviews WHERE DATE(created_at) = ? AND status = 'active'";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $reviews_data['labels'][] = date('d/m', strtotime($date));
            $reviews_data['data'][] = $row['count'];
        }
        
        $response = [
            'status' => 'success',
            'data' => [
                'users' => $users_data,
                'reviews' => $reviews_data
            ]
        ];
        break;
        
    case 'toggle-dark-mode':
        $dark_mode = $_POST['dark_mode'] ?? 0;
        $_SESSION['dark_mode'] = $dark_mode;
        
        // حفظ التفضيل في قاعدة البيانات
        $sql = "UPDATE users SET dark_mode = ? WHERE id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $dark_mode, $_SESSION['user_id']);
        $stmt->execute();
        
        $response = ['status' => 'success', 'message' => 'تم تحديث الوضع'];
        break;
        
    case 'save-custom-css':
        $css = $_POST['css'] ?? '';
        
        // حفظ CSS المخصص
        $sql = "INSERT INTO design_settings (setting_key, setting_value, setting_type, category) 
                VALUES ('custom_css', ?, 'text', 'custom') 
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ss", $css, $css);
        
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'تم حفظ CSS المخصص'];
        } else {
            $response['message'] = 'حدث خطأ أثناء حفظ CSS';
        }
        break;
        
    case 'generate-best100':
        if ($admin->generateTop100Restaurants()) {
            $response = ['status' => 'success', 'message' => 'تم إنشاء قائمة أفضل 100 مطعم بنجاح'];
        } else {
            $response['message'] = 'حدث خطأ أثناء إنشاء القائمة';
        }
        break;
        
    default:
        $response['message'] = 'إجراء غير معروف';
}

echo json_encode($response);
?>