<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Database.php';

$db = new Database();
$response = ['status' => 'error', 'message' => 'Invalid request'];

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-products':
        $category = $_GET['category'] ?? '';
        $limit = $_GET['limit'] ?? 12;
        $offset = $_GET['offset'] ?? 0;
        
        $where_clause = "WHERE is_active = 1";
        $params = [];
        $types = '';
        
        if ($category && $category !== 'all') {
            $where_clause .= " AND category = ?";
            $params[] = $category;
            $types .= 's';
        }
        
        $sql = "SELECT * FROM store_products 
                $where_clause 
                ORDER BY sort_order, points_required, name 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $db->getConnection()->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        
        $response = [
            'status' => 'success',
            'data' => $products
        ];
        break;
        
    case 'redeem':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'يجب تسجيل الدخول أولاً';
            break;
        }
        
        $product_id = $_POST['product_id'] ?? 0;
        $details = $_POST['details'] ?? '';
        
        try {
            $db->beginTransaction();
            
            // الحصول على معلومات المنتج
            $sql = "SELECT * FROM store_products WHERE id = ? AND is_active = 1 FOR UPDATE";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            if (!$product) {
                throw new Exception('المنتج غير متوفر');
            }
            
            // التحقق من المخزون
            if ($product['stock'] == 0) {
                throw new Exception('المنتج غير متوفر في المخزون');
            }
            
            // الحصول على رصيد المستخدم
            $user_sql = "SELECT points FROM users WHERE id = ? FOR UPDATE";
            $user_stmt = $db->getConnection()->prepare($user_sql);
            $user_stmt->bind_param("i", $_SESSION['user_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            
            if ($user['points'] < $product['points_required']) {
                throw new Exception('رصيد النقاط غير كافي');
            }
            
            // إنشاء رقم الطلب
            $order_number = 'ORD-' . time() . '-' . rand(1000, 9999);
            
            // إنشاء الطلب
            $order_sql = "INSERT INTO store_orders (order_number, user_id, product_id, points_paid, details, status) 
                         VALUES (?, ?, ?, ?, ?, 'pending')";
            $order_stmt = $db->getConnection()->prepare($order_sql);
            $order_stmt->bind_param("siiis", $order_number, $_SESSION['user_id'], $product_id, 
                                   $product['points_required'], $details);
            $order_stmt->execute();
            $order_id = $order_stmt->insert_id;
            
            // خصم النقاط من رصيد المستخدم
            $deduct_sql = "UPDATE users SET points = points - ? WHERE id = ?";
            $deduct_stmt = $db->getConnection()->prepare($deduct_sql);
            $deduct_stmt->bind_param("ii", $product['points_required'], $_SESSION['user_id']);
            $deduct_stmt->execute();
            
            // تحديث المخزون
            if ($product['stock'] > 0) {
                $stock_sql = "UPDATE store_products SET stock = stock - 1 WHERE id = ?";
                $stock_stmt = $db->getConnection()->prepare($stock_sql);
                $stock_stmt->bind_param("i", $product_id);
                $stock_stmt->execute();
            }
            
            // تسجيل المعاملة
            $transaction_sql = "INSERT INTO transactions (user_id, type, amount, description, reference_id, reference_type, status) 
                               VALUES (?, 'redeem', ?, 'استبدال نقاط', ?, 'store_order', 'completed')";
            $transaction_stmt = $db->getConnection()->prepare($transaction_sql);
            $transaction_stmt->bind_param("iii", $_SESSION['user_id'], $product['points_required'], $order_id);
            $transaction_stmt->execute();
            
            // إرسال إشعار للأدمن
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, link) 
                                SELECT id, 'طلب جديد', 'طلب استبدال جديد في المتجر', 'info', '/admin/store/orders' 
                                FROM users WHERE role = 'admin'";
            $db->getConnection()->query($notification_sql);
            
            $db->commit();
            
            $response = [
                'status' => 'success',
                'message' => 'تم تقديم طلب الاستبدال بنجاح',
                'data' => [
                    'order_id' => $order_id,
                    'order_number' => $order_number,
                    'product_name' => $product['name'],
                    'points_paid' => $product['points_required']
                ]
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            $response['message'] = $e->getMessage();
        }
        break;
        
    case 'get-user-orders':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'يجب تسجيل الدخول أولاً';
            break;
        }
        
        $status = $_GET['status'] ?? '';
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $where_clauses = ["o.user_id = ?"];
        $params = [$_SESSION['user_id']];
        $types = "i";
        
        if ($status) {
            $where_clauses[] = "o.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT o.*, p.name as product_name, p.category, p.image,
                       CASE p.category
                           WHEN 'mobile_balance' THEN 'رصيد جوال'
                           WHEN 'coupons' THEN 'كوبونات'
                           WHEN 'bank_transfer' THEN 'تحويل بنكي'
                           WHEN 'tickets' THEN 'تذاكر'
                           ELSE 'أخرى'
                       END as category_name
                FROM store_orders o
                JOIN store_products p ON o.product_id = p.id
                WHERE $where_sql
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            // فك تشفير التفاصيل إذا كانت موجودة
            if ($row['details']) {
                $details = json_decode($row['details'], true);
                if ($details && isset($details['code'])) {
                    $row['code'] = $details['code'];
                }
            }
            $orders[] = $row;
        }
        
        $response = [
            'status' => 'success',
            'data' => $orders
        ];
        break;
        
    case 'get-categories':
        $sql = "SELECT DISTINCT category FROM store_products WHERE is_active = 1 ORDER BY category";
        $result = $db->getConnection()->query($sql);
        
        $categories = [
            ['value' => 'all', 'name' => 'جميع الفئات']
        ];
        
        while ($row = $result->fetch_assoc()) {
            $category_name = '';
            switch ($row['category']) {
                case 'mobile_balance': $category_name = 'رصيد جوال'; break;
                case 'coupons': $category_name = 'كوبونات'; break;
                case 'bank_transfer': $category_name = 'تحويل بنكي'; break;
                case 'tickets': $category_name = 'تذاكر'; break;
                default: $category_name = 'أخرى';
            }
            
            $categories[] = [
                'value' => $row['category'],
                'name' => $category_name
            ];
        }
        
        $response = [
            'status' => 'success',
            'data' => $categories
        ];
        break;
}

echo json_encode($response);
?>