<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Restaurant.php';

$db = new Database();
$response = ['status' => 'error', 'message' => 'Invalid request'];

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-all':
        $city = $_GET['city'] ?? 'all';
        $category_id = $_GET['category_id'] ?? '';
        $search = $_GET['search'] ?? '';
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $filters = [];
        if ($city !== 'all') $filters['city'] = $city;
        if ($category_id) $filters['category_id'] = $category_id;
        if ($search) $filters['search'] = $search;
        
        $restaurants = Restaurant::getAll($db->getConnection(), $filters, $limit, $offset);
        
        $response = [
            'status' => 'success',
            'data' => $restaurants,
            'total' => count($restaurants)
        ];
        break;
        
    case 'get-top':
        $city = $_GET['city'] ?? 'all';
        $limit = $_GET['limit'] ?? 6;
        
        $restaurants = Restaurant::getTopRestaurants($db->getConnection(), ($city !== 'all' ? $city : null), $limit);
        
        $response = [
            'status' => 'success',
            'data' => $restaurants
        ];
        break;
        
    case 'get-details':
        $id = $_GET['id'] ?? 0;
        $slug = $_GET['slug'] ?? '';
        
        $restaurant = new Restaurant($db->getConnection());
        
        if ($id) {
            $restaurant->getById($id);
        } elseif ($slug) {
            $restaurant->getBySlug($slug);
        }
        
        if ($restaurant->getId()) {
            $reviews = $restaurant->getReviews(10, 0);
            $rating_distribution = $restaurant->getRatingDistribution();
            
            $response = [
                'status' => 'success',
                'data' => [
                    'id' => $restaurant->getId(),
                    'name' => $restaurant->getName(),
                    'slug' => $restaurant->getSlug(),
                    'description' => $restaurant->getDescription(),
                    'city' => $restaurant->getCity(),
                    'address' => $restaurant->getAddress(),
                    'phone' => $restaurant->getPhone(),
                    'email' => $restaurant->getEmail(),
                    'logo' => $restaurant->getLogo(),
                    'cover_image' => $restaurant->getCoverImage(),
                    'category_id' => $restaurant->getCategoryId(),
                    'category_name' => $restaurant->getCategoryName(),
                    'owner_id' => $restaurant->getOwnerId(),
                    'owner_name' => $restaurant->getOwnerName(),
                    'rating' => $restaurant->getRating(),
                    'total_reviews' => $restaurant->getTotalReviews(),
                    'is_featured' => $restaurant->isFeatured(),
                    'status' => $restaurant->getStatus(),
                    'latitude' => $restaurant->getLatitude(),
                    'longitude' => $restaurant->getLongitude(),
                    'created_at' => $restaurant->getCreatedAt(),
                    'reviews' => $reviews,
                    'rating_distribution' => $rating_distribution
                ]
            ];
        } else {
            $response['message'] = 'المطعم غير موجود';
        }
        break;
        
    case 'get-reviews':
        $restaurant_id = $_GET['restaurant_id'] ?? 0;
        $rating = $_GET['rating'] ?? null;
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $restaurant = new Restaurant($db->getConnection());
        if ($restaurant->getById($restaurant_id)) {
            $reviews = $restaurant->getReviews($limit, $offset, $rating);
            
            $response = [
                'status' => 'success',
                'data' => $reviews
            ];
        } else {
            $response['message'] = 'المطعم غير موجود';
        }
        break;
        
    case 'add-review':
        if (!isset($_SESSION['user_id'])) {
            $response['message'] = 'يجب تسجيل الدخول أولاً';
            break;
        }
        
        $restaurant_id = $_POST['restaurant_id'] ?? 0;
        $rating = $_POST['rating'] ?? 0;
        $comment = $_POST['comment'] ?? '';
        
        // التحقق من البيانات
        if ($rating < 1 || $rating > 5) {
            $response['message'] = 'التقييم يجب أن يكون بين 1 و 5';
            break;
        }
        
        // التحقق من عدم وجود تقييم سابق
        $sql = "SELECT id FROM reviews WHERE user_id = ? AND restaurant_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->bind_param("ii", $_SESSION['user_id'], $restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response['message'] = 'لقد قمت بتقييم هذا المطعم مسبقاً';
            break;
        }
        
        try {
            $db->beginTransaction();
            
            // إضافة التقييم
            $sql = "INSERT INTO reviews (user_id, restaurant_id, rating, comment, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("iiis", $_SESSION['user_id'], $restaurant_id, $rating, $comment);
            $stmt->execute();
            
            // تحديث معدل التقييم للمطعم
            $sql = "UPDATE restaurants SET 
                    rating = (SELECT AVG(rating) FROM reviews WHERE restaurant_id = ? AND status = 'active'),
                    total_reviews = (SELECT COUNT(*) FROM reviews WHERE restaurant_id = ? AND status = 'active')
                    WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("iii", $restaurant_id, $restaurant_id, $restaurant_id);
            $stmt->execute();
            
            // منح النقاط للمستخدم
            $user_sql = "UPDATE users SET points = points + ? WHERE id = ?";
            $user_stmt = $db->getConnection()->prepare($user_sql);
            $points = POINTS_PER_REVIEW;
            $user_stmt->bind_param("ii", $points, $_SESSION['user_id']);
            $user_stmt->execute();
            
            // تسجيل المعاملة
            $transaction_sql = "INSERT INTO transactions (user_id, type, amount, description, status) 
                               VALUES (?, 'earn', ?, 'نقاط التقييم', 'completed')";
            $transaction_stmt = $db->getConnection()->prepare($transaction_sql);
            $transaction_stmt->bind_param("ii", $_SESSION['user_id'], $points);
            $transaction_stmt->execute();
            
            $db->commit();
            
            $response = [
                'status' => 'success',
                'message' => 'تم إضافة التقييم بنجاح',
                'data' => [
                    'review_id' => $stmt->insert_id,
                    'points_earned' => $points
                ]
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            $response['message'] = 'حدث خطأ أثناء إضافة التقييم: ' . $e->getMessage();
        }
        break;
        
    case 'get-categories':
        $sql = "SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name";
        $result = $db->getConnection()->query($sql);
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        $response = [
            'status' => 'success',
            'data' => $categories
        ];
        break;
}

echo json_encode($response);
?>