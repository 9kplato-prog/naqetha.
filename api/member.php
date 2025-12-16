<?php
header('Content-Type: application/json');
session_start();

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

$db = new Database();
$response = ['status' => 'error', 'message' => 'Invalid request'];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'يجب تسجيل الدخول أولاً';
    echo json_encode($response);
    exit;
}

$user = new User($db->getConnection());
$user->getById($_SESSION['user_id']);

// التحقق من صلاحية المستخدم
if (!in_array($user->getRole(), ['member', 'moderator'])) {
    $response['message'] = 'غير مصرح لك بالوصول';
    echo json_encode($response);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-tasks':
        $status = $_GET['status'] ?? '';
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $sql = "SELECT ut.*, t.title, t.description, t.points_reward, t.discount_percentage,
                       r.name as restaurant_name, r.city, r.logo, r.slug as restaurant_slug,
                       c.name as category_name, c.color as category_color
                FROM user_tasks ut
                JOIN tasks t ON ut.task_id = t.id
                JOIN restaurants r ON t.restaurant_id = r.id
                LEFT JOIN categories c ON r.category_id = c.id
                WHERE ut.user_id = ?";
        
        $params = [$user->getId()];
        $types = "i";
        
        if ($status) {
            $sql .= " AND ut.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $sql .= " ORDER BY ut.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            // التحقق من صلاحية الكود
            if ($row['status'] === 'reserved' && strtotime($row['code_expires']) < time()) {
                $row['status'] = 'expired';
            }
            $tasks[] = $row;
        }
        
        $response = [
            'status' => 'success',
            'data' => $tasks
        ];
        break;
        
    case 'get-task-code':
        $task_id = $_GET['task_id'] ?? 0;
        
        $sql = "SELECT discount_code, code_expires FROM user_tasks 
                WHERE id = ? AND user_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $task_id, $user->getId());
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $task = $result->fetch_assoc();
            
            $response = [
                'status' => 'success',
                'data' => [
                    'discount_code' => $task['discount_code'],
                    'expires_at' => date('Y-m-d H:i', strtotime($task['code_expires']))
                ]
            ];
        } else {
            $response['message'] = 'المهمة غير موجودة';
        }
        break;
        
    case 'complete-task':
        $task_id = $_POST['task_id'] ?? 0;
        $review_link = $_POST['review_link'] ?? '';
        $rating = $_POST['rating'] ?? 0;
        $comment = $_POST['comment'] ?? '';
        
        // التحقق من البيانات
        if ($rating < 1 || $rating > 5) {
            $response['message'] = 'التقييم يجب أن يكون بين 1 و 5';
            break;
        }
        
        try {
            $db->beginTransaction();
            
            // التحقق من المهمة
            $sql = "SELECT ut.*, t.points_reward, t.restaurant_id, t.title
                    FROM user_tasks ut
                    JOIN tasks t ON ut.task_id = t.id
                    WHERE ut.id = ? AND ut.user_id = ? AND ut.status = 'reserved'
                    FOR UPDATE";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("ii", $task_id, $user->getId());
            $stmt->execute();
            $result = $stmt->get_result();
            $user_task = $result->fetch_assoc();
            
            if (!$user_task) {
                throw new Exception('المهمة غير موجودة أو غير قابلة للإكمال');
            }
            
            // التحقق من صلاحية الكود
            if (strtotime($user_task['code_expires']) < time()) {
                throw new Exception('كود الخصم منتهي الصلاحية');
            }
            
            // إضافة التقييم
            $review_sql = "INSERT INTO reviews (user_id, restaurant_id, user_task_id, rating, comment, is_verified, created_at) 
                          VALUES (?, ?, ?, ?, ?, 1, NOW())";
            $review_stmt = $db->getConnection()->prepare($review_sql);
            $review_stmt->bind_param("iiiss", 
                $user->getId(), 
                $user_task['restaurant_id'], 
                $task_id, 
                $rating, 
                $comment
            );
            $review_stmt->execute();
            $review_id = $review_stmt->insert_id;
            
            // تحديث مهمة المستخدم
            $update_sql = "UPDATE user_tasks SET 
                          status = 'completed',
                          review_link = ?,
                          rating = ?,
                          completed_at = NOW()
                          WHERE id = ?";
            $update_stmt = $db->getConnection()->prepare($update_sql);
            $update_stmt->bind_param("sii", $review_link, $rating, $task_id);
            $update_stmt->execute();
            
            // منح النقاط للمستخدم
            $user->addPoints($user_task['points_reward'], 'إكمال مهمة: ' . $user_task['title'], $task_id, 'task');
            
            // تحديث معدل التقييم للمطعم
            $restaurant_sql = "UPDATE restaurants SET 
                              rating = (SELECT AVG(rating) FROM reviews WHERE restaurant_id = ? AND status = 'active'),
                              total_reviews = (SELECT COUNT(*) FROM reviews WHERE restaurant_id = ? AND status = 'active')
                              WHERE id = ?";
            $restaurant_stmt = $db->getConnection()->prepare($restaurant_sql);
            $restaurant_stmt->bind_param("iii", 
                $user_task['restaurant_id'], 
                $user_task['restaurant_id'], 
                $user_task['restaurant_id']
            );
            $restaurant_stmt->execute();
            
            $db->commit();
            
            $response = [
                'status' => 'success',
                'message' => 'تم إكمال المهمة بنجاح',
                'data' => [
                    'points_earned' => $user_task['points_reward'],
                    'review_id' => $review_id
                ]
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            $response['message'] = $e->getMessage();
        }
        break;
        
    case 'renew-task':
        $task_id = $_POST['task_id'] ?? 0;
        
        try {
            $db->beginTransaction();
            
            // التحقق من المهمة
            $sql = "SELECT * FROM user_tasks 
                    WHERE id = ? AND user_id = ? AND status = 'reserved'
                    FOR UPDATE";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("ii", $task_id, $user->getId());
            $stmt->execute();
            $result = $stmt->get_result();
            $user_task = $result->fetch_assoc();
            
            if (!$user_task) {
                throw new Exception('المهمة غير موجودة أو غير قابلة للتجديد');
            }
            
            // إنشاء كود جديد
            $new_code = generateDiscountCode($user_task['task_id']);
            $new_expiry = date('Y-m-d H:i:s', strtotime('+' . TASK_EXPIRY_HOURS . ' hours'));
            
            // تحديث المهمة
            $update_sql = "UPDATE user_tasks SET 
                          discount_code = ?,
                          code_expires = ?
                          WHERE id = ?";
            $update_stmt = $db->getConnection()->prepare($update_sql);
            $update_stmt->bind_param("ssi", $new_code, $new_expiry, $task_id);
            $update_stmt->execute();
            
            $db->commit();
            
            $response = [
                'status' => 'success',
                'message' => 'تم تجديد المهمة بنجاح',
                'data' => [
                    'new_code' => $new_code,
                    'expires_at' => $new_expiry
                ]
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            $response['message'] = $e->getMessage();
        }
        break;
        
    case 'get-store-orders':
        $status = $_GET['status'] ?? '';
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $sql = "SELECT o.*, p.name as product_name, p.category,
                       CASE p.category
                           WHEN 'mobile_balance' THEN 'رصيد جوال'
                           WHEN 'coupons' THEN 'كوبونات'
                           WHEN 'bank_transfer' THEN 'تحويل بنكي'
                           WHEN 'tickets' THEN 'تذاكر'
                           ELSE 'أخرى'
                       END as category_name
                FROM store_orders o
                JOIN store_products p ON o.product_id = p.id
                WHERE o.user_id = ?";
        
        $params = [$user->getId()];
        $types = "i";
        
        if ($status) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        $response = [
            'status' => 'success',
            'data' => $orders
        ];
        break;
        
    case 'get-statistics':
        $stats = $user->getStatistics();
        
        $response = [
            'status' => 'success',
            'data' => $stats
        ];
        break;
        
    case 'get-leaderboard':
        $limit = $_GET['limit'] ?? 50;
        $period = $_GET['period'] ?? 'monthly'; // weekly, monthly, all
        
        $sql = "SELECT u.id, u.name, u.avatar, u.points,
                       COUNT(DISTINCT ut.id) as completed_tasks,
                       COUNT(DISTINCT r.id) as total_reviews,
                       DENSE_RANK() OVER (ORDER BY u.points DESC) as rank
                FROM users u
                LEFT JOIN user_tasks ut ON u.id = ut.user_id AND ut.status = 'completed'
                LEFT JOIN reviews r ON u.id = r.user_id AND r.status = 'active'
                WHERE u.role = 'member' AND u.status = 'active'";
        
        if ($period === 'weekly') {
            $sql .= " AND (ut.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) OR ut.completed_at IS NULL)";
        } elseif ($period === 'monthly') {
            $sql .= " AND (ut.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR ut.completed_at IS NULL)";
        }
        
        $sql .= " GROUP BY u.id ORDER BY u.points DESC LIMIT ?";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaderboard = [];
        while ($row = $result->fetch_assoc()) {
            $leaderboard[] = $row;
        }
        
        // إضافة ترتيب المستخدم الحالي
        $user_rank = 0;
        $user_index = array_search($user->getId(), array_column($leaderboard, 'id'));
        if ($user_index !== false) {
            $user_rank = $leaderboard[$user_index]['rank'];
        } else {
            // إذا لم يكن المستخدم في القائمة، جلب ترتيبه
            $rank_sql = "SELECT COUNT(*) + 1 as rank 
                        FROM users 
                        WHERE points > (SELECT points FROM users WHERE id = ?) 
                        AND role = 'member' AND status = 'active'";
            $rank_stmt = $db->getConnection()->prepare($rank_sql);
            $rank_stmt->bind_param("i", $user->getId());
            $rank_stmt->execute();
            $rank_result = $rank_stmt->get_result();
            $user_rank = $rank_result->fetch_assoc()['rank'];
        }
        
        $response = [
            'status' => 'success',
            'data' => [
                'leaderboard' => $leaderboard,
                'user_rank' => $user_rank,
                'user_points' => $user->getPoints()
            ]
        ];
        break;
        
    case 'update-profile':
        $data = [
            'name' => $_POST['name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'city' => $_POST['city'] ?? '',
            'birthdate' => $_POST['birthdate'] ?? '',
            'bank_name' => $_POST['bank_name'] ?? '',
            'bank_account_name' => $_POST['bank_account_name'] ?? '',
            'iban' => $_POST['iban'] ?? ''
        ];
        
        // إزالة الحقول الفارغة
        $data = array_filter($data);
        
        if (empty($data)) {
            $response['message'] = 'لم يتم إرسال أي بيانات للتحديث';
            break;
        }
        
        if ($user->updateProfile($data)) {
            $response = [
                'status' => 'success',
                'message' => 'تم تحديث الملف الشخصي بنجاح'
            ];
        } else {
            $response['message'] = 'حدث خطأ أثناء تحديث الملف الشخصي';
        }
        break;
        
    case 'update-password':
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $response['message'] = 'جميع الحقول مطلوبة';
            break;
        }
        
        if ($new_password !== $confirm_password) {
            $response['message'] = 'كلمة المرور الجديدة غير متطابقة';
            break;
        }
        
        try {
            if ($user->updatePassword($current_password, $new_password)) {
                $response = [
                    'status' => 'success',
                    'message' => 'تم تحديث كلمة المرور بنجاح'
                ];
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        break;
        
    case 'update-avatar':
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'لم يتم رفع الصورة';
            break;
        }
        
        try {
            $filename = uploadFile($_FILES['avatar'], 'image');
            
            if ($user->updateAvatar($filename)) {
                $response = [
                    'status' => 'success',
                    'message' => 'تم تحديث الصورة بنجاح',
                    'data' => [
                        'avatar_url' => '/uploads/images/' . $filename
                    ]
                ];
            } else {
                $response['message'] = 'حدث خطأ أثناء تحديث الصورة';
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        break;
        
    default:
        $response['message'] = 'إجراء غير معروف';
        break;
}

function generateDiscountCode($task_id) {
    $prefix = 'NQT';
    $random = strtoupper(bin2hex(random_bytes(3)));
    return "$prefix-$task_id-$random";
}

function uploadFile($file, $type = 'image') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('حدث خطأ في رفع الملف');
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('حجم الملف أكبر من المسموح به');
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $allowed_types)) {
        throw new Exception('نوع الملف غير مسموح به');
    }
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $upload_path = '../uploads/images/';
    
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    $destination = $upload_path . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('فشل في حفظ الملف');
    }
    
    return $filename;
}

echo json_encode($response);
?>