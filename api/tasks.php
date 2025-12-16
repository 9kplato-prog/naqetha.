<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Restaurant.php';

$db = new Database();
$response = ['status' => 'error', 'message' => 'Invalid request'];

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-available':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'يجب تسجيل الدخول أولاً';
            break;
        }
        
        $city = $_GET['city'] ?? '';
        $category_id = $_GET['category_id'] ?? '';
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $where_clauses = ["t.status = 'available'", "t.current_participants < t.max_participants"];
        $params = [];
        $types = '';
        
        // التحقق من عدم حجز المستخدم للمهمة مسبقاً
        $where_clauses[] = "t.id NOT IN (SELECT task_id FROM user_tasks WHERE user_id = ? AND status != 'cancelled')";
        $params[] = $_SESSION['user_id'];
        $types .= 'i';
        
        if ($city) {
            $where_clauses[] = "r.city = ?";
            $params[] = $city;
            $types .= 's';
        }
        
        if ($category_id) {
            $where_clauses[] = "r.category_id = ?";
            $params[] = $category_id;
            $types .= 'i';
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT t.*, r.name as restaurant_name, r.city, r.logo, 
                       c.name as category_name, c.color as category_color
                FROM tasks t
                JOIN restaurants r ON t.restaurant_id = r.id
                LEFT JOIN categories c ON r.category_id = c.id
                WHERE $where_sql
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        
        $response = [
            'status' => 'success',
            'data' => $tasks
        ];
        break;
        
    case 'reserve':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'يجب تسجيل الدخول أولاً';
            break;
        }
        
        $task_id = $_POST['task_id'] ?? 0;
        
        try {
            $db->beginTransaction();
            
            // التحقق من توفر المهمة
            $sql = "SELECT * FROM tasks WHERE id = ? AND status = 'available' 
                    AND current_participants < max_participants
                    FOR UPDATE";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $task = $result->fetch_assoc();
            
            if (!$task) {
                throw new Exception('المهمة غير متاحة أو وصلت للحد الأقصى للمشاركين');
            }
            
            // التحقق من عدم حجز المستخدم للمهمة مسبقاً
            $sql = "SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ? AND status != 'cancelled'";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("ii", $_SESSION['user_id'], $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception('لقد حجزت هذه المهمة مسبقاً');
            }
            
            // التحقق من عدد المهام النشطة للمستخدم
            $sql = "SELECT COUNT(*) as active_tasks FROM user_tasks 
                    WHERE user_id = ? AND status IN ('reserved', 'in_progress')";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['active_tasks'];
            
            if ($count >= MAX_TASKS_PER_USER) {
                throw new Exception('لديك الحد الأقصى من المهام النشطة');
            }
            
            // إنشاء كود الخصم
            $discount_code = generateDiscountCode($task_id);
            $code_expires = date('Y-m-d H:i:s', strtotime('+' . TASK_EXPIRY_HOURS . ' hours'));
            
            // حجز المهمة
            $sql = "INSERT INTO user_tasks (user_id, task_id, status, discount_code, code_expires) 
                    VALUES (?, ?, 'reserved', ?, ?)";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("iiss", $_SESSION['user_id'], $task_id, $discount_code, $code_expires);
            $stmt->execute();
            $user_task_id = $stmt->insert_id;
            
            // تحديث عدد المشاركين
            $sql = "UPDATE tasks SET current_participants = current_participants + 1 WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            
            // إذا وصل عدد المشاركين للحد الأقصى، إغلاق المهمة
            if ($task['current_participants'] + 1 >= $task['max_participants']) {
                $sql = "UPDATE tasks SET status = 'active' WHERE id = ?";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->bind_param("i", $task_id);
                $stmt->execute();
            }
            
            $db->commit();
            
            $response = [
                'status' => 'success',
                'message' => 'تم حجز المهمة بنجاح',
                'data' => [
                    'user_task_id' => $user_task_id,
                    'discount_code' => $discount_code,
                    'code_expires' => $code_expires,
                    'task_title' => $task['title'],
                    'restaurant_name' => $task['restaurant_name'],
                    'discount_percentage' => $task['discount_percentage'],
                    'points_reward' => $task['points_reward']
                ]
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            $response['message'] = $e->getMessage();
        }
        break;
        
    case 'get-user-tasks':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'يجب تسجيل الدخول أولاً';
            break;
        }
        
        $status = $_GET['status'] ?? '';
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $where_clauses = ["ut.user_id = ?"];
        $params = [$_SESSION['user_id']];
        $types = "i";
        
        if ($status) {
            $where_clauses[] = "ut.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT ut.*, t.title, t.description, t.points_reward, t.discount_percentage,
                       r.name as restaurant_name, r.city, r.logo, r.rating,
                       c.name as category_name, c.color as category_color
                FROM user_tasks ut
                JOIN tasks t ON ut.task_id = t.id
                JOIN restaurants r ON t.restaurant_id = r.id
                LEFT JOIN categories c ON r.category_id = c.id
                WHERE $where_sql
                ORDER BY ut.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
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
        
    case 'complete-task':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'يجب تسجيل الدخول أولاً';
            break;
        }
        
        $user_task_id = $_POST['user_task_id'] ?? 0;
        $review_link = $_POST['review_link'] ?? '';
        $rating = $_POST['rating'] ?? 0;
        $comment = $_POST['comment'] ?? '';
        
        try {
            $db->beginTransaction();
            
            // التحقق من المهمة
            $sql = "SELECT ut.*, t.points_reward, t.restaurant_id, t.title
                    FROM user_tasks ut
                    JOIN tasks t ON ut.task_id = t.id
                    WHERE ut.id = ? AND ut.user_id = ? AND ut.status = 'reserved'
                    FOR UPDATE";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("ii", $user_task_id, $_SESSION['user_id']);
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
            $review_stmt->bind_param("iiiss", $_SESSION['user_id'], $user_task['restaurant_id'], 
                                     $user_task_id, $rating, $comment);
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
            $update_stmt->bind_param("sii", $review_link, $rating, $user_task_id);
            $update_stmt->execute();
            
            // منح النقاط للمستخدم
            $user_sql = "UPDATE users SET points = points + ? WHERE id = ?";
            $user_stmt = $db->getConnection()->prepare($user_sql);
            $user_stmt->bind_param("ii", $user_task['points_reward'], $_SESSION['user_id']);
            $user_stmt->execute();
            
            // تسجيل المعاملة
            $transaction_sql = "INSERT INTO transactions (user_id, type, amount, description, reference_id, reference_type, status) 
                               VALUES (?, 'earn', ?, 'نقاط إكمال المهمة', ?, 'task', 'completed')";
            $transaction_stmt = $db->getConnection()->prepare($transaction_sql);
            $transaction_stmt->bind_param("iii", $_SESSION['user_id'], $user_task['points_reward'], $user_task_id);
            $transaction_stmt->execute();
            
            // تحديث معدل التقييم للمطعم
            $restaurant_sql = "UPDATE restaurants SET 
                              rating = (SELECT AVG(rating) FROM reviews WHERE restaurant_id = ? AND status = 'active'),
                              total_reviews = (SELECT COUNT(*) FROM reviews WHERE restaurant_id = ? AND status = 'active')
                              WHERE id = ?";
            $restaurant_stmt = $db->getConnection()->prepare($restaurant_sql);
            $restaurant_stmt->bind_param("iii", $user_task['restaurant_id'], $user_task['restaurant_id'], 
                                         $user_task['restaurant_id']);
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
        
    case 'get-task-details':
        $task_id = $_GET['task_id'] ?? 0;
        
        $sql = "SELECT t.*, r.name as restaurant_name, r.city, r.address, r.phone, r.logo,
                       r.rating as restaurant_rating, r.total_reviews,
                       c.name as category_name, c.color as category_color
                FROM tasks t
                JOIN restaurants r ON t.restaurant_id = r.id
                LEFT JOIN categories c ON r.category_id = c.id
                WHERE t.id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $task = $result->fetch_assoc();
        
        if ($task) {
            // الحصول على تقييمات المطعم
            $reviews_sql = "SELECT r.*, u.name as user_name, u.avatar as user_avatar
                           FROM reviews r
                           JOIN users u ON r.user_id = u.id
                           WHERE r.restaurant_id = ? AND r.status = 'active'
                           ORDER BY r.created_at DESC
                           LIMIT 5";
            $reviews_stmt = $db->getConnection()->prepare($reviews_sql);
            $reviews_stmt->bind_param("i", $task['restaurant_id']);
            $reviews_stmt->execute();
            $reviews_result = $reviews_stmt->get_result();
            
            $reviews = [];
            while ($row = $reviews_result->fetch_assoc()) {
                $reviews[] = $row;
            }
            
            $task['reviews'] = $reviews;
            
            $response = [
                'status' => 'success',
                'data' => $task
            ];
        } else {
            $response['message'] = 'المهمة غير موجودة';
        }
        break;
}

function generateDiscountCode($task_id) {
    $prefix = 'NQT';
    $random = strtoupper(bin2hex(random_bytes(3)));
    return "$prefix-$task_id-$random";
}

echo json_encode($response);
?>