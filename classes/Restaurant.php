<?php
class Restaurant {
    private $db;
    private $restaurant_id;
    private $name;
    private $slug;
    private $description;
    private $city;
    private $address;
    private $phone;
    private $email;
    private $logo;
    private $cover_image;
    private $category_id;
    private $owner_id;
    private $rating;
    private $total_reviews;
    private $is_featured;
    private $status;
    private $latitude;
    private $longitude;
    private $created_at;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    public function create($data) {
        // إنشاء slug
        $slug = $this->generateSlug($data['name']);
        
        try {
            $this->db->begin_transaction();
            
            $sql = "INSERT INTO restaurants (name, slug, description, city, address, phone, email, category_id, owner_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sssssssiis",
                $data['name'],
                $slug,
                $data['description'],
                $data['city'],
                $data['address'],
                $data['phone'],
                $data['email'],
                $data['category_id'],
                $data['owner_id'],
                $data['status']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('فشل إنشاء المطعم');
            }
            
            $this->restaurant_id = $stmt->insert_id;
            
            // إنشاء صلاحيات افتراضية للمطعم
            $this->createDefaultPermissions();
            
            // إرسال إشعار للأدمن
            $this->sendNewRestaurantNotification();
            
            $this->db->commit();
            
            // تحميل بيانات المطعم
            $this->getById($this->restaurant_id);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getById($id) {
        $sql = "SELECT r.*, c.name as category_name, u.name as owner_name 
                FROM restaurants r 
                LEFT JOIN categories c ON r.category_id = c.id 
                LEFT JOIN users u ON r.owner_id = u.id 
                WHERE r.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $restaurant = $result->fetch_assoc();
            $this->loadRestaurantData($restaurant);
            return true;
        }
        
        return false;
    }
    
    public function getBySlug($slug) {
        $sql = "SELECT r.*, c.name as category_name, u.name as owner_name 
                FROM restaurants r 
                LEFT JOIN categories c ON r.category_id = c.id 
                LEFT JOIN users u ON r.owner_id = u.id 
                WHERE r.slug = ? AND r.status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $restaurant = $result->fetch_assoc();
            $this->loadRestaurantData($restaurant);
            return true;
        }
        
        return false;
    }
    
    private function loadRestaurantData($restaurant) {
        $this->restaurant_id = $restaurant['id'];
        $this->name = $restaurant['name'];
        $this->slug = $restaurant['slug'];
        $this->description = $restaurant['description'];
        $this->city = $restaurant['city'];
        $this->address = $restaurant['address'];
        $this->phone = $restaurant['phone'];
        $this->email = $restaurant['email'];
        $this->logo = $restaurant['logo'];
        $this->cover_image = $restaurant['cover_image'];
        $this->category_id = $restaurant['category_id'];
        $this->category_name = $restaurant['category_name'] ?? '';
        $this->owner_id = $restaurant['owner_id'];
        $this->owner_name = $restaurant['owner_name'] ?? '';
        $this->rating = $restaurant['rating'];
        $this->total_reviews = $restaurant['total_reviews'];
        $this->is_featured = $restaurant['is_featured'];
        $this->status = $restaurant['status'];
        $this->latitude = $restaurant['latitude'];
        $this->longitude = $restaurant['longitude'];
        $this->created_at = $restaurant['created_at'];
    }
    
    public function update($data) {
        $allowed_fields = ['name', 'description', 'city', 'address', 'phone', 'email', 'category_id', 'status'];
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
        
        $params[] = $this->restaurant_id;
        $types .= 'i';
        
        $sql = "UPDATE restaurants SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // تحديث خصائص الكائن
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $this->$field = $data[$field];
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    public function updateImages($logo_path = null, $cover_path = null) {
        $updates = [];
        $params = [];
        $types = '';
        
        if ($logo_path) {
            $updates[] = "logo = ?";
            $params[] = $logo_path;
            $types .= 's';
            $this->logo = $logo_path;
        }
        
        if ($cover_path) {
            $updates[] = "cover_image = ?";
            $params[] = $cover_path;
            $types .= 's';
            $this->cover_image = $cover_path;
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $params[] = $this->restaurant_id;
        $types .= 'i';
        
        $sql = "UPDATE restaurants SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
    
    public function updateLocation($latitude, $longitude) {
        $sql = "UPDATE restaurants SET latitude = ?, longitude = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ddi", $latitude, $longitude, $this->restaurant_id);
        
        if ($stmt->execute()) {
            $this->latitude = $latitude;
            $this->longitude = $longitude;
            return true;
        }
        
        return false;
    }
    
    public function getReviews($limit = 10, $offset = 0, $rating = null) {
        $where_clause = "WHERE restaurant_id = ?";
        $params = [$this->restaurant_id];
        $types = "i";
        
        if ($rating) {
            $where_clause .= " AND rating = ?";
            $params[] = $rating;
            $types .= "i";
        }
        
        $sql = "SELECT r.*, u.name as user_name, u.avatar as user_avatar 
                FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                $where_clause AND r.status = 'active' 
                ORDER BY r.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        return $reviews;
    }
    
    public function getRatingDistribution() {
        $sql = "SELECT rating, COUNT(*) as count FROM reviews 
                WHERE restaurant_id = ? AND status = 'active' 
                GROUP BY rating ORDER BY rating DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $distribution = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0
        ];
        
        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $distribution[$row['rating']] = $row['count'];
            $total += $row['count'];
        }
        
        // حساب النسب المئوية
        foreach ($distribution as $rating => $count) {
            $distribution[$rating] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0
            ];
        }
        
        return [
            'distribution' => $distribution,
            'total' => $total,
            'average' => $this->rating
        ];
    }
    
    public function getTasks($status = null, $limit = 10, $offset = 0) {
        $where_clause = "WHERE restaurant_id = ?";
        $params = [$this->restaurant_id];
        $types = "i";
        
        if ($status) {
            $where_clause .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        $sql = "SELECT * FROM tasks $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        
        return $tasks;
    }
    
    public function getStatistics($period = 'month') {
        $stats = [];
        
        // تحديد تاريخ البدء بناءً على الفترة
        $start_date = date('Y-m-d', strtotime("-1 $period"));
        
        // عدد الزيارات (التقييمات)
        $sql = "SELECT COUNT(*) as visits FROM reviews 
                WHERE restaurant_id = ? AND created_at >= ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $this->restaurant_id, $start_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['visits'] = $result->fetch_assoc()['visits'];
        
        // متوسط التقييم
        $sql = "SELECT AVG(rating) as avg_rating FROM reviews 
                WHERE restaurant_id = ? AND created_at >= ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $this->restaurant_id, $start_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['avg_rating'] = round($result->fetch_assoc()['avg_rating'], 1);
        
        // عدد المهام المكتملة
        $sql = "SELECT COUNT(DISTINCT ut.id) as completed_tasks 
                FROM user_tasks ut 
                JOIN tasks t ON ut.task_id = t.id 
                WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at >= ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $this->restaurant_id, $start_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['completed_tasks'] = $result->fetch_assoc()['completed_tasks'];
        
        // الإيرادات المحتملة (بناءً على المهام)
        $sql = "SELECT SUM(t.discount_percentage) as potential_revenue 
                FROM user_tasks ut 
                JOIN tasks t ON ut.task_id = t.id 
                WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at >= ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $this->restaurant_id, $start_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['potential_revenue'] = $result->fetch_assoc()['potential_revenue'] ?? 0;
        
        return $stats;
    }
    
    public function isOwner($user_id) {
        return $this->owner_id == $user_id;
    }
    
    private function generateSlug($name) {
        // تحويل النص إلى slug
        $slug = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = mb_strtolower($slug, 'UTF-8');
        
        // التحقق من عدم تكرار الـ slug
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    private function slugExists($slug) {
        $sql = "SELECT id FROM restaurants WHERE slug = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    private function createDefaultPermissions() {
        $sql = "INSERT INTO restaurant_permissions (restaurant_id, max_discount, max_tasks) VALUES (?, 30, 10)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $this->restaurant_id);
        $stmt->execute();
    }
    
    private function sendNewRestaurantNotification() {
        // إرسال إشعار للأدمن بوجود مطعم جديد
        $sql = "INSERT INTO notifications (user_id, title, message, type, link) 
                SELECT id, 'مطعم جديد', 'تم إنشاء مطعم جديد في النظام', 'info', '/admin/restaurants' 
                FROM users WHERE role = 'admin'";
        $this->db->query($sql);
    }
    
    // Getters للخصائص
    public function getId() { return $this->restaurant_id; }
    public function getName() { return $this->name; }
    public function getSlug() { return $this->slug; }
    public function getDescription() { return $this->description; }
    public function getCity() { return $this->city; }
    public function getAddress() { return $this->address; }
    public function getPhone() { return $this->phone; }
    public function getEmail() { return $this->email; }
    public function getLogo() { return $this->logo; }
    public function getCoverImage() { return $this->cover_image; }
    public function getCategoryId() { return $this->category_id; }
    public function getCategoryName() { return $this->category_name; }
    public function getOwnerId() { return $this->owner_id; }
    public function getOwnerName() { return $this->owner_name; }
    public function getRating() { return $this->rating; }
    public function getTotalReviews() { return $this->total_reviews; }
    public function isFeatured() { return $this->is_featured; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
    
    // Static methods للاستعلامات العامة
    public static function getAll($db, $filters = [], $limit = 10, $offset = 0) {
        $where_clauses = ["r.status = 'active'"];
        $params = [];
        $types = '';
        
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
            $where_clauses[] = "(r.name LIKE ? OR r.description LIKE ?)";
            $search_term = "%{$filters['search']}%";
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ss';
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT r.*, c.name as category_name, c.color as category_color 
                FROM restaurants r 
                LEFT JOIN categories c ON r.category_id = c.id 
                WHERE $where_sql 
                ORDER BY r.rating DESC, r.total_reviews DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $db->prepare($sql);
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
    
    public static function getTopRestaurants($db, $city = null, $limit = 100) {
        $where_clause = "WHERE r.status = 'active'";
        $params = [];
        $types = '';
        
        if ($city) {
            $where_clause .= " AND r.city = ?";
            $params[] = $city;
            $types .= 's';
        }
        
        $sql = "SELECT r.*, c.name as category_name 
                FROM restaurants r 
                LEFT JOIN categories c ON r.category_id = c.id 
                $where_clause 
                ORDER BY r.rating DESC, r.total_reviews DESC 
                LIMIT ?";
        
        $params[] = $limit;
        $types .= 'i';
        
        $stmt = $db->prepare($sql);
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
}
?>