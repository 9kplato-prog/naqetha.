<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Database.php';

session_start();

$db = new Database();
$response = ['status' => 'error', 'message' => 'Invalid request'];

// التحقق من صلاحيات صاحب المطعم
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'restaurant_owner') {
    $response['message'] = 'غير مصرح لك بالوصول';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-restaurant-stats':
        $restaurant_id = $_GET['restaurant_id'] ?? 0;
        
        // التحقق من أن المطعم يتبع المستخدم
        $sql = "SELECT id FROM restaurants WHERE id = ? AND owner_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $restaurant_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'غير مصرح لك بالوصول لهذا المطعم';
            break;
        }
        
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        
        // إحصائيات عامة
        $stats = [];
        
        // عدد الزيارات
        $sql = "SELECT COUNT(*) as visits FROM reviews 
                WHERE restaurant_id = ? AND created_at BETWEEN ? AND ? AND status = 'active'";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['visits'] = $result->fetch_assoc()['visits'];
        
        // متوسط التقييم
        $sql = "SELECT AVG(rating) as avg_rating FROM reviews 
                WHERE restaurant_id = ? AND created_at BETWEEN ? AND ? AND status = 'active'";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['avg_rating'] = round($result->fetch_assoc()['avg_rating'], 1);
        
        // عدد المهام المكتملة
        $sql = "SELECT COUNT(DISTINCT ut.id) as completed_tasks 
                FROM user_tasks ut 
                JOIN tasks t ON ut.task_id = t.id 
                WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at BETWEEN ? AND ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['completed_tasks'] = $result->fetch_assoc()['completed_tasks'];
        
        $response = ['status' => 'success', 'data' => $stats];
        break;
        
    case 'mark-as-read':
        $message_id = $_POST['message_id'] ?? 0;
        
        $sql = "UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND receiver_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $message_id, $user_id);
        
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'تم تمييز الرسالة كمقروءة'];
        } else {
            $response['message'] = 'حدث خطأ أثناء تحديث الرسالة';
        }
        break;
        
    case 'delete-message':
        $message_id = $_POST['message_id'] ?? 0;
        $type = $_POST['type'] ?? '';
        
        if ($type === 'sent') {
            $sql = "UPDATE messages SET sender_deleted = 1 WHERE id = ? AND sender_id = ?";
        } else {
            $sql = "UPDATE messages SET receiver_deleted = 1 WHERE id = ? AND receiver_id = ?";
        }
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $message_id, $user_id);
        
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'تم حذف الرسالة'];
        } else {
            $response['message'] = 'حدث خطأ أثناء حذف الرسالة';
        }
        break;
        
    case 'reply-to-review':
        $review_id = $_POST['review_id'] ?? 0;
        $reply = $_POST['reply'] ?? '';
        
        // التحقق من أن التقييم يتبع مطعم المستخدم
        $sql = "SELECT r.id FROM reviews rev 
                JOIN restaurants r ON rev.restaurant_id = r.id 
                WHERE rev.id = ? AND r.owner_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $review_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'غير مصرح لك بالرد على هذا التقييم';
            break;
        }
        
        $sql = "UPDATE reviews SET reply = ?, replied_at = NOW() WHERE id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("si", $reply, $review_id);
        
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'تم إرسال الرد بنجاح'];
        } else {
            $response['message'] = 'حدث خطأ أثناء إرسال الرد';
        }
        break;
        
    case 'export-report':
        $format = $_GET['format'] ?? 'pdf';
        $period = $_GET['period'] ?? 'current';
        
        // الحصول على مطعم المستخدم
        $sql = "SELECT id, name FROM restaurants WHERE owner_id = ? LIMIT 1";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $restaurant = $result->fetch_assoc();
        
        if (!$restaurant) {
            $response['message'] = 'لم يتم العثور على مطعم';
            break;
        }
        
        // تحديد الفترة الزمنية
        $today = date('Y-m-d');
        switch ($period) {
            case 'last_week':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'last_month':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'last_quarter':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'last_year':
                $start_date = date('Y-m-d', strtotime('-365 days'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        
        // جمع بيانات التقرير
        $report_data = [
            'restaurant_name' => $restaurant['name'],
            'period' => "$start_date إلى $today",
            'generated_at' => date('Y-m-d H:i:s'),
            'visits' => 0,
            'avg_rating' => 0,
            'completed_tasks' => 0
        ];
        
        // إحصائيات التقييمات
        $sql = "SELECT COUNT(*) as visits, AVG(rating) as avg_rating FROM reviews 
                WHERE restaurant_id = ? AND created_at BETWEEN ? AND ? AND status = 'active'";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iss", $restaurant['id'], $start_date, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $report_data['visits'] = $row['visits'];
        $report_data['avg_rating'] = round($row['avg_rating'], 1);
        
        // إحصائيات المهام
        $sql = "SELECT COUNT(DISTINCT ut.id) as completed_tasks 
                FROM user_tasks ut 
                JOIN tasks t ON ut.task_id = t.id 
                WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at BETWEEN ? AND ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iss", $restaurant['id'], $start_date, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $report_data['completed_tasks'] = $row['completed_tasks'];
        
        // إنشاء التقرير حسب النوع
        if ($format === 'pdf') {
            // محاكاة إنشاء PDF
            $filename = "تقرير_" . preg_replace('/\s+/', '_', $restaurant['name']) . "_" . date('Y-m-d') . ".pdf";
            $response = [
                'status' => 'success',
                'data' => [
                    'download_url' => "/reports/$filename",
                    'filename' => $filename
                ]
            ];
        } else {
            $response = ['status' => 'success', 'data' => $report_data];
        }
        break;
        
    case 'subscribe':
        $plan = $_POST['plan'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        
        // الحصول على مطعم المستخدم
        $sql = "SELECT id FROM restaurants WHERE owner_id = ? LIMIT 1";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $restaurant = $result->fetch_assoc();
        
        if (!$restaurant) {
            $response['message'] = 'لم يتم العثور على مطعم';
            break;
        }
        
        // تحديث صلاحيات المطعم حسب الخطة
        $permissions = [];
        switch ($plan) {
            case 'pro':
                $permissions = [
                    'max_discount' => 50,
                    'max_tasks' => 9999,
                    'can_feature' => 1,
                    'can_priority' => 1
                ];
                break;
            case 'enterprise':
                $permissions = [
                    'max_discount' => 70,
                    'max_tasks' => 9999,
                    'can_feature' => 1,
                    'can_priority' => 1
                ];
                break;
            default:
                $permissions = [
                    'max_discount' => 30,
                    'max_tasks' => 10,
                    'can_feature' => 0,
                    'can_priority' => 0
                ];
        }
        
        $sql = "INSERT INTO restaurant_permissions (restaurant_id, max_discount, max_tasks, can_feature, can_priority) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                max_discount = VALUES(max_discount),
                max_tasks = VALUES(max_tasks),
                can_feature = VALUES(can_feature),
                can_priority = VALUES(can_priority)";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iiiii", 
            $restaurant['id'],
            $permissions['max_discount'],
            $permissions['max_tasks'],
            $permissions['can_feature'],
            $permissions['can_priority']
        );
        
        if ($stmt->execute()) {
            // تسجيل عملية الاشتراك
            $sql = "INSERT INTO subscriptions (user_id, restaurant_id, plan, amount, payment_method, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $db->getConnection()->prepare($sql);
            $amount = $plan === 'pro' ? 199 : ($plan === 'enterprise' ? 499 : 0);
            $stmt->bind_param("iisds", $user_id, $restaurant['id'], $plan, $amount, $payment_method);
            $stmt->execute();
            
            $response = ['status' => 'success', 'message' => 'تم تفعيل الاشتراك بنجاح'];
        } else {
            $response['message'] = 'حدث خطأ أثناء تفعيل الاشتراك';
        }
        break;
        
    case 'get-ticket':
        $ticket_id = $_GET['ticket_id'] ?? 0;
        
        $sql = "SELECT * FROM support_tickets WHERE id = ? AND user_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $ticket_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket = $result->fetch_assoc();
        
        if ($ticket) {
            // الحصول على الردود
            $sql = "SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $ticket_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $replies = [];
            while ($row = $result->fetch_assoc()) {
                $replies[] = $row;
            }
            
            $ticket['replies'] = $replies;
            $response = ['status' => 'success', 'data' => $ticket];
        } else {
            $response['message'] = 'التذكرة غير موجودة';
        }
        break;
        
    case 'reply-to-ticket':
        $ticket_id = $_POST['ticket_id'] ?? 0;
        $message = $_POST['message'] ?? '';
        
        // التحقق من أن التذكرة تتبع المستخدم
        $sql = "SELECT id FROM support_tickets WHERE id = ? AND user_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $ticket_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'غير مصرح لك بالرد على هذه التذكرة';
            break;
        }
        
        // إضافة الرد
        $sql = "INSERT INTO ticket_replies (ticket_id, user_id, message, is_admin, created_at) 
                VALUES (?, ?, ?, 0, NOW())";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("iis", $ticket_id, $user_id, $message);
        
        if ($stmt->execute()) {
            // تحديث حالة التذكرة
            $sql = "UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $ticket_id);
            $stmt->execute();
            
            $response = ['status' => 'success', 'message' => 'تم إرسال الرد بنجاح'];
        } else {
            $response['message'] = 'حدث خطأ أثناء إرسال الرد';
        }
        break;
        
    default:
        $response['message'] = 'إجراء غير معروف';
}

echo json_encode($response);
?>