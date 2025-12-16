<?php
class User {
    private $db;
    private $user_id;
    private $name;
    private $email;
    private $phone;
    private $city;
    private $birthdate;
    private $avatar;
    private $points;
    private $bank_name;
    private $bank_account_name;
    private $iban;
    private $role;
    private $status;
    private $last_login;
    private $created_at;
    private $updated_at;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    public function login($email, $password) {
        $sql = "SELECT * FROM users WHERE email = ? AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $this->loadUserData($user);
                
                // تحديث وقت آخر تسجيل دخول
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $this->db->prepare($update_sql);
                $update_stmt->bind_param("i", $this->user_id);
                $update_stmt->execute();
                
                // تسجيل سجل النشاط
                $this->logActivity('login', 'تم تسجيل الدخول بنجاح');
                
                return true;
            }
        }
        
        return false;
    }
    
    public function register($data) {
        // التحقق من عدم وجود حساب بنفس البريد
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param("s", $data['email']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception('هذا البريد الإلكتروني مستخدم مسبقاً');
        }
        
        // تشفير كلمة المرور
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // إعداد بيانات المستخدم
        $user_data = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'city' => $data['city'],
            'birthdate' => $data['birthdate'],
            'password' => $hashed_password,
            'role' => ROLE_MEMBER,
            'status' => 'pending', // ينتظر الموافقة
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            // بدء المعاملة
            $this->db->begin_transaction();
            
            // إدراج المستخدم
            $sql = "INSERT INTO users (name, email, phone, city, birthdate, password, role, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sssssssss", 
                $user_data['name'],
                $user_data['email'],
                $user_data['phone'],
                $user_data['city'],
                $user_data['birthdate'],
                $user_data['password'],
                $user_data['role'],
                $user_data['status'],
                $user_data['created_at']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('فشل إنشاء الحساب');
            }
            
            $this->user_id = $stmt->insert_id;
            
            // إرسال إشعار للأدمن
            $this->sendNewUserNotification();
            
            // تسجيل سجل النشاط
            $this->logActivity('register', 'تم إنشاء حساب جديد');
            
            $this->db->commit();
            
            // تحميل بيانات المستخدم
            $this->getById($this->user_id);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $this->loadUserData($user);
            return true;
        }
        
        return false;
    }
    
    public function getByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $this->loadUserData($user);
            return true;
        }
        
        return false;
    }
    
    private function loadUserData($user) {
        $this->user_id = $user['id'];
        $this->name = $user['name'];
        $this->email = $user['email'];
        $this->phone = $user['phone'];
        $this->city = $user['city'];
        $this->birthdate = $user['birthdate'];
        $this->avatar = $user['avatar'];
        $this->points = $user['points'];
        $this->bank_name = $user['bank_name'];
        $this->bank_account_name = $user['bank_account_name'];
        $this->iban = $user['iban'];
        $this->role = $user['role'];
        $this->status = $user['status'];
        $this->last_login = $user['last_login'];
        $this->created_at = $user['created_at'];
        $this->updated_at = $user['updated_at'];
    }
    
    public function updateProfile($data) {
        $allowed_fields = ['name', 'phone', 'city', 'birthdate', 'bank_name', 'bank_account_name', 'iban'];
        $updates = [];
        $params = [];
        $types = '';
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field]) && $data[$field] !== $this->$field) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= 's';
            }
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $params[] = $this->user_id;
        $types .= 'i';
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // تحديث خصائص الكائن
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $this->$field = $data[$field];
                }
            }
            
            $this->logActivity('profile_update', 'تم تحديث الملف الشخصي');
            return true;
        }
        
        return false;
    }
    
    public function updatePassword($current_password, $new_password) {
        // التحقق من كلمة المرور الحالية
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user['password'])) {
            throw new Exception('كلمة المرور الحالية غير صحيحة');
        }
        
        // تحديث كلمة المرور
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $this->user_id);
        
        if ($stmt->execute()) {
            $this->logActivity('password_change', 'تم تغيير كلمة المرور');
            return true;
        }
        
        return false;
    }
    
    public function updateAvatar($avatar_path) {
        $sql = "UPDATE users SET avatar = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $avatar_path, $this->user_id);
        
        if ($stmt->execute()) {
            $this->avatar = $avatar_path;
            $this->logActivity('avatar_update', 'تم تحديث صورة الملف الشخصي');
            return true;
        }
        
        return false;
    }
    
    public function addPoints($points, $description, $reference_id = null, $reference_type = null) {
        $sql = "UPDATE users SET points = points + ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $points, $this->user_id);
        
        if ($stmt->execute()) {
            // تسجيل المعاملة
            $transaction_sql = "INSERT INTO transactions (user_id, type, amount, balance_after, description, reference_id, reference_type, status) 
                              VALUES (?, 'earn', ?, (SELECT points FROM users WHERE id = ?), ?, ?, ?, 'completed')";
            $transaction_stmt = $this->db->prepare($transaction_sql);
            $new_balance = $this->points + $points;
            $transaction_stmt->bind_param("iiisii", 
                $this->user_id, 
                $points, 
                $this->user_id,
                $description,
                $reference_id,
                $reference_type
            );
            $transaction_stmt->execute();
            
            $this->points = $new_balance;
            $this->logActivity('earn_points', "كسب $points نقطة: $description");
            
            return true;
        }
        
        return false;
    }
    
    public function deductPoints($points, $description, $reference_id = null, $reference_type = null) {
        if ($this->points < $points) {
            throw new Exception('رصيد النقاط غير كافي');
        }
        
        $sql = "UPDATE users SET points = points - ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $points, $this->user_id);
        
        if ($stmt->execute()) {
            // تسجيل المعاملة
            $transaction_sql = "INSERT INTO transactions (user_id, type, amount, balance_after, description, reference_id, reference_type, status) 
                              VALUES (?, 'redeem', ?, (SELECT points FROM users WHERE id = ?), ?, ?, ?, 'completed')";
            $transaction_stmt = $this->db->prepare($transaction_sql);
            $new_balance = $this->points - $points;
            $transaction_stmt->bind_param("iiisii", 
                $this->user_id, 
                $points, 
                $this->user_id,
                $description,
                $reference_id,
                $reference_type
            );
            $transaction_stmt->execute();
            
            $this->points = $new_balance;
            $this->logActivity('redeem_points', "خصم $points نقطة: $description");
            
            return true;
        }
        
        return false;
    }
    
    public function getStatistics() {
        $stats = [];
        
        // عدد المهام المكتملة
        $sql = "SELECT COUNT(*) as count FROM user_tasks WHERE user_id = ? AND status = 'completed'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['completed_tasks'] = $result->fetch_assoc()['count'];
        
        // عدد المهام النشطة
        $sql = "SELECT COUNT(*) as count FROM user_tasks WHERE user_id = ? AND status IN ('reserved', 'in_progress')";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['active_tasks'] = $result->fetch_assoc()['count'];
        
        // إجمالي النقاط المستخدمة
        $sql = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'redeem' AND status = 'completed'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['points_used'] = $result->fetch_assoc()['total'] ?? 0;
        
        // عدد التقييمات
        $sql = "SELECT COUNT(*) as count FROM reviews WHERE user_id = ? AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_reviews'] = $result->fetch_assoc()['count'];
        
        return $stats;
    }
    
    public function getStoreOrders($limit = 10, $offset = 0) {
        $sql = "SELECT o.*, p.name as product_name, p.category 
                FROM store_orders o 
                JOIN store_products p ON o.product_id = p.id 
                WHERE o.user_id = ? 
                ORDER BY o.created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $this->user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        return $orders;
    }
    
    public function getActivityLogs($limit = 20, $offset = 0) {
        $sql = "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $this->user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        return $logs;
    }
    
    private function sendNewUserNotification() {
        // إرسال إشعار للأدمن بوجود مستخدم جديد
        $sql = "INSERT INTO notifications (user_id, title, message, type, link) 
                SELECT id, 'مستخدم جديد', 'تم تسجيل مستخدم جديد في النظام', 'info', '/admin/users' 
                FROM users WHERE role = 'admin'";
        $this->db->query($sql);
    }
    
    private function logActivity($action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("issss", $this->user_id, $action, $description, $ip, $user_agent);
        $stmt->execute();
    }
    
    // Getters للخصائص
    public function getId() { return $this->user_id; }
    public function getName() { return $this->name; }
    public function getEmail() { return $this->email; }
    public function getPhone() { return $this->phone; }
    public function getCity() { return $this->city; }
    public function getAvatar() { return $this->avatar; }
    public function getPoints() { return $this->points; }
    public function getRole() { return $this->role; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
}
?>