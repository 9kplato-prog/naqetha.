<?php
class Admin {
    private $db;
    private $user_id;
    
    public function __construct($db_connection, $user_id) {
        $this->db = $db_connection;
        $this->user_id = $user_id;
    }
    
    public function getDashboardStats() {
        $stats = [];
        
        // إجمالي المستخدمين
        $sql = "SELECT COUNT(*) as total FROM users";
        $result = $this->db->query($sql);
        $stats['total_users'] = $result->fetch_assoc()['total'];
        
        // إجمالي المطاعم
        $sql = "SELECT COUNT(*) as total FROM restaurants";
        $result = $this->db->query($sql);
        $stats['total_restaurants'] = $result->fetch_assoc()['total'];
        
        // إجمالي التقييمات
        $sql = "SELECT COUNT(*) as total FROM reviews WHERE status = 'active'";
        $result = $this->db->query($sql);
        $stats['total_reviews'] = $result->fetch_assoc()['total'];
        
        // إجمالي المهام النشطة
        $sql = "SELECT COUNT(*) as total FROM tasks WHERE status = 'active'";
        $result = $this->db->query($sql);
        $stats['active_tasks'] = $result->fetch_assoc()['total'];
        
        // إجمالي النقاط الموزعة
        $sql = "SELECT SUM(amount) as total FROM transactions WHERE type = 'earn' AND status = 'completed'";
        $result = $this->db->query($sql);
        $stats['total_points_earned'] = $result->fetch_assoc()['total'] ?? 0;
        
        // إجمالي النقاط المستخدمة
        $sql = "SELECT SUM(amount) as total FROM transactions WHERE type = 'redeem' AND status = 'completed'";
        $result = $this->db->query($sql);
        $stats['total_points_redeemed'] = $result->fetch_assoc()['total'] ?? 0;
        
        // عدد طلبات المتجر المعلقة
        $sql = "SELECT COUNT(*) as total FROM store_orders WHERE status = 'pending'";
        $result = $this->db->query($sql);
        $stats['pending_store_orders'] = $result->fetch_assoc()['total'];
        
        // عدد التقييمات المبلغ عنها
        $sql = "SELECT COUNT(*) as total FROM reviews WHERE status = 'reported'";
        $result = $this->db->query($sql);
        $stats['reported_reviews'] = $result->fetch_assoc()['total'];
        
        // عدد المستخدمين الجدد اليوم
        $sql = "SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()";
        $result = $this->db->query($sql);
        $stats['new_users_today'] = $result->fetch_assoc()['total'];
        
        // عدد المطاعم الجديدة اليوم
        $sql = "SELECT COUNT(*) as total FROM restaurants WHERE DATE(created_at) = CURDATE()";
        $result = $this->db->query($sql);
        $stats['new_restaurants_today'] = $result->fetch_assoc()['total'];
        
        return $stats;
    }
    
    public function createCategory($data) {
        // التحقق من عدم وجود تصنيف بنفس الاسم
        $check_sql = "SELECT id FROM categories WHERE name = ?";
        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param("s", $data['name']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception('هذا التصنيف موجود مسبقاً');
        }
        
        // إنشاء slug
        $slug = $this->generateSlug($data['name']);
        
        $sql = "INSERT INTO categories (name, slug, icon, color, sort_order, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssii",
            $data['name'],
            $slug,
            $data['icon'],
            $data['color'],
            $data['sort_order'],
            $this->user_id
        );
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        
        return false;
    }
    
    public function updateCategory($category_id, $data) {
        $allowed_fields = ['name', 'icon', 'color', 'sort_order', 'is_active'];
        $updates = [];
        $params = [];
        $types = '';
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= $field === 'sort_order' || $field === 'is_active' ? 'i' : 's';
            }
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $params[] = $category_id;
        $types .= 'i';
        
        $sql = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
    
    public function deleteCategory($category_id) {
        // التحقق من عدم وجود مطاعم مرتبطة بالتصنيف
        $check_sql = "SELECT COUNT(*) as count FROM restaurants WHERE category_id = ?";
        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            throw new Exception("لا يمكن حذف التصنيف لأنه مرتبط بـ $count مطعم");
        }
        
        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $category_id);
        
        return $stmt->execute();
    }
    
    public function getCategories($active_only = false) {
        $where_clause = $active_only ? "WHERE is_active = 1" : "";
        $sql = "SELECT * FROM categories $where_clause ORDER BY sort_order, name";
        $result = $this->db->query($sql);
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        return $categories;
    }
    
    public function manageRestaurant($restaurant_id, $action, $data = []) {
        switch ($action) {
            case 'approve':
                $sql = "UPDATE restaurants SET status = 'active' WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("i", $restaurant_id);
                break;
                
            case 'suspend':
                $sql = "UPDATE restaurants SET status = 'suspended' WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("i", $restaurant_id);
                break;
                
            case 'feature':
                $sql = "UPDATE restaurants SET is_featured = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $featured = $data['featured'] ? 1 : 0;
                $stmt->bind_param("ii", $featured, $restaurant_id);
                break;
                
            case 'update_permissions':
                $sql = "UPDATE restaurant_permissions SET 
                        max_discount = ?, 
                        max_tasks = ?, 
                        can_feature = ?, 
                        can_priority = ?, 
                        custom_settings = ? 
                        WHERE restaurant_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("iiissi",
                    $data['max_discount'],
                    $data['max_tasks'],
                    $data['can_feature'],
                    $data['can_priority'],
                    $data['custom_settings'],
                    $restaurant_id
                );
                break;
                
            case 'delete':
                $sql = "DELETE FROM restaurants WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("i", $restaurant_id);
                break;
                
            default:
                throw new Exception('إجراء غير معروف');
        }
        
        return $stmt->execute();
    }
    
    public function getRestaurants($filters = [], $limit = 20, $offset = 0) {
        $where_clauses = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "r.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['city'])) {
            $where_clauses[] = "r.city = ?";
            $params[] = $filters['city'];
            $types .= 's';
        }
        
        if (!empty($filters['category_id'])) {
            $where_clauses[] = "r.category_id = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(r.name LIKE ? OR r.email LIKE ? OR u.name LIKE ?)";
            $search_term = "%{$filters['search']}%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";
        
        $sql = "SELECT r.*, c.name as category_name, u.name as owner_name, 
                       u.email as owner_email, u.phone as owner_phone,
                       rp.max_discount, rp.max_tasks, rp.can_feature, rp.can_priority
                FROM restaurants r 
                LEFT JOIN categories c ON r.category_id = c.id 
                LEFT JOIN users u ON r.owner_id = u.id 
                LEFT JOIN restaurant_permissions rp ON r.id = rp.restaurant_id 
                $where_sql 
                ORDER BY r.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $restaurants = [];
        while ($row = $result->fetch_assoc()) {
            $restaurants[] = $row;
        }
        
        return $restaurants;
    }
    
    public function getUsers($filters = [], $limit = 20, $offset = 0) {
        $where_clauses = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['role'])) {
            $where_clauses[] = "role = ?";
            $params[] = $filters['role'];
            $types .= 's';
        }
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['city'])) {
            $where_clauses[] = "city = ?";
            $params[] = $filters['city'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $search_term = "%{$filters['search']}%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";
        
        $sql = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    public function updateUserRole($user_id, $new_role) {
        $allowed_roles = ['admin', 'moderator', 'restaurant_owner', 'member'];
        
        if (!in_array($new_role, $allowed_roles)) {
            throw new Exception('دور غير صالح');
        }
        
        // لا يمكن تغيير دور الأدمن الرئيسي
        if ($user_id == $this->user_id && $new_role != 'admin') {
            throw new Exception('لا يمكن تغيير دور الأدمن الرئيسي');
        }
        
        $sql = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $new_role, $user_id);
        
        if ($stmt->execute()) {
            // إذا تم ترقية المستخدم إلى أدمن، إنشاء صلاحيات افتراضية
            if ($new_role == 'admin') {
                $this->createAdminPermissions($user_id);
            }
            
            return true;
        }
        
        return false;
    }
    
    public function updateUserStatus($user_id, $new_status) {
        $allowed_statuses = ['active', 'suspended', 'pending'];
        
        if (!in_array($new_status, $allowed_statuses)) {
            throw new Exception('حالة غير صالحة');
        }
        
        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $new_status, $user_id);
        
        return $stmt->execute();
    }
    
    public function getStoreOrders($filters = [], $limit = 20, $offset = 0) {
        $where_clauses = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "o.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['category'])) {
            $where_clauses[] = "p.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR o.order_number LIKE ?)";
            $search_term = "%{$filters['search']}%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";
        
        $sql = "SELECT o.*, p.name as product_name, p.category as product_category,
                       u.name as user_name, u.email as user_email, u.phone as user_phone,
                       a.name as admin_name
                FROM store_orders o 
                JOIN store_products p ON o.product_id = p.id 
                JOIN users u ON o.user_id = u.id 
                LEFT JOIN users a ON o.sent_by = a.id 
                $where_sql 
                ORDER BY o.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        return $orders;
    }
    
    public function updateOrderStatus($order_id, $status, $notes = null) {
        $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
        
        if (!in_array($status, $allowed_statuses)) {
            throw new Exception('حالة غير صالحة');
        }
        
        $updates = ["status = ?"];
        $params = [$status];
        $types = "s";
        
        if ($status == 'completed') {
            $updates[] = "sent_at = NOW()";
            $updates[] = "sent_by = ?";
            $params[] = $this->user_id;
            $types .= "i";
        }
        
        if ($notes) {
            $updates[] = "admin_notes = ?";
            $params[] = $notes;
            $types .= "s";
        }
        
        $params[] = $order_id;
        $types .= "i";
        
        $sql = "UPDATE store_orders SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
    
    public function sendOrderCode($order_id, $code) {
        $sql = "UPDATE store_orders SET code_sent = 1, details = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $details = json_encode(['code' => $code, 'sent_at' => date('Y-m-d H:i:s')]);
        $stmt->bind_param("si", $details, $order_id);
        
        if ($stmt->execute()) {
            // إرسال إشعار للمستخدم
            $this->sendOrderNotification($order_id, $code);
            return true;
        }
        
        return false;
    }
    
    public function getReviews($filters = [], $limit = 20, $offset = 0) {
        $where_clauses = ["r.status != 'deleted'"];
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "r.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['restaurant_id'])) {
            $where_clauses[] = "r.restaurant_id = ?";
            $params[] = $filters['restaurant_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['rating'])) {
            $where_clauses[] = "r.rating = ?";
            $params[] = $filters['rating'];
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(u.name LIKE ? OR res.name LIKE ? OR r.comment LIKE ?)";
            $search_term = "%{$filters['search']}%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT r.*, u.name as user_name, u.email as user_email,
                       res.name as restaurant_name, res.city as restaurant_city
                FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                JOIN restaurants res ON r.restaurant_id = res.id 
                WHERE $where_sql 
                ORDER BY r.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        return $reviews;
    }
    
    public function updateReviewStatus($review_id, $status, $reason = null) {
        $allowed_statuses = ['active', 'hidden', 'reported', 'deleted'];
        
        if (!in_array($status, $allowed_statuses)) {
            throw new Exception('حالة غير صالحة');
        }
        
        $sql = "UPDATE reviews SET status = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $status, $review_id);
        
        return $stmt->execute();
    }
    
    public function getDesignSettings($category = null) {
        $where_clause = $category ? "WHERE category = ?" : "";
        $sql = "SELECT * FROM design_settings $where_clause ORDER BY category, sort_order";
        
        if ($category) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->db->query($sql);
        }
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row;
        }
        
        return $settings;
    }
    
    public function updateDesignSetting($key, $value, $type = 'text') {
        $sql = "INSERT INTO design_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssss", $key, $value, $type, $value, $type);
        
        return $stmt->execute();
    }
    
    public function updateMultipleDesignSettings($settings) {
        try {
            $this->db->begin_transaction();
            
            foreach ($settings as $key => $value) {
                $sql = "UPDATE design_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getReports($type, $start_date, $end_date) {
        $reports = [];
        
        switch ($type) {
            case 'users':
                $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                        FROM users 
                        WHERE created_at BETWEEN ? AND ? 
                        GROUP BY DATE(created_at) 
                        ORDER BY date";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $reports[] = $row;
                }
                break;
                
            case 'restaurants':
                $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                        FROM restaurants 
                        WHERE created_at BETWEEN ? AND ? 
                        GROUP BY DATE(created_at) 
                        ORDER BY date";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $reports[] = $row;
                }
                break;
                
            case 'reviews':
                $sql = "SELECT DATE(created_at) as date, COUNT(*) as count, AVG(rating) as avg_rating 
                        FROM reviews 
                        WHERE created_at BETWEEN ? AND ? 
                        GROUP BY DATE(created_at) 
                        ORDER BY date";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $reports[] = $row;
                }
                break;
                
            case 'tasks':
                $sql = "SELECT DATE(created_at) as date, COUNT(*) as count, 
                        SUM(points_reward) as total_points 
                        FROM tasks 
                        WHERE created_at BETWEEN ? AND ? 
                        GROUP BY DATE(created_at) 
                        ORDER BY date";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $reports[] = $row;
                }
                break;
                
            case 'transactions':
                $sql = "SELECT type, SUM(amount) as total_amount, COUNT(*) as count 
                        FROM transactions 
                        WHERE created_at BETWEEN ? AND ? AND status = 'completed' 
                        GROUP BY type";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $reports[] = $row;
                }
                break;
        }
        
        return $reports;
    }
    
    public function generateTop100Restaurants() {
        try {
            $this->db->begin_transaction();
            
            // حذف قائمة الأسبوع الماضي
            $week_number = date('W');
            $year = date('Y');
            
            $delete_sql = "DELETE FROM top_restaurants WHERE week_number = ? AND year = ?";
            $delete_stmt = $this->db->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $week_number, $year);
            $delete_stmt->execute();
            
            // الحصول على أفضل 100 مطعم لهذا الأسبوع
            $sql = "SELECT id, rating, total_reviews 
                    FROM restaurants 
                    WHERE status = 'active' 
                    ORDER BY rating DESC, total_reviews DESC 
                    LIMIT 100";
            $result = $this->db->query($sql);
            
            $position = 1;
            while ($row = $result->fetch_assoc()) {
                $insert_sql = "INSERT INTO top_restaurants (restaurant_id, week_number, year, position, rating, total_reviews) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $this->db->prepare($insert_sql);
                $insert_stmt->bind_param("iiiiii",
                    $row['id'],
                    $week_number,
                    $year,
                    $position,
                    $row['rating'],
                    $row['total_reviews']
                );
                $insert_stmt->execute();
                $position++;
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function generateSlug($name) {
        $slug = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = mb_strtolower($slug, 'UTF-8');
        
        // التحقق من عدم تكرار الـ slug
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->categorySlugExists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    private function categorySlugExists($slug) {
        $sql = "SELECT id FROM categories WHERE slug = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    private function createAdminPermissions($user_id) {
        $sql = "INSERT INTO admin_permissions (user_id) VALUES (?) 
                ON DUPLICATE KEY UPDATE user_id = user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    private function sendOrderNotification($order_id, $code) {
        // الحصول على معلومات الطلب
        $sql = "SELECT o.user_id, o.order_number, p.name as product_name 
                FROM store_orders o 
                JOIN store_products p ON o.product_id = p.id 
                WHERE o.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        if ($order) {
            // إرسال إشعار للمستخدم
            $title = "كود الطلب #{$order['order_number']}";
            $message = "تم إرسال كود $code لمنتج {$order['product_name']}";
            $link = "/member/orders";
            
            $sql = "INSERT INTO notifications (user_id, title, message, type, link) 
                    VALUES (?, ?, ?, 'success', ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("isss", $order['user_id'], $title, $message, $link);
            $stmt->execute();
        }
    }
}
?>