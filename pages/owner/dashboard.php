<?php
// التحقق من صلاحيات المالك
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'لوحة تحكم المالك';
$page_scripts = ['owner-dashboard.js'];

include 'includes/header.php';

// الحصول على مطاعم المالك
$sql = "SELECT r.*, c.name as category_name, c.color as category_color,
               (SELECT COUNT(*) FROM tasks WHERE restaurant_id = r.id AND status = 'active') as active_tasks,
               (SELECT COUNT(*) FROM reviews WHERE restaurant_id = r.id AND status = 'active') as today_reviews
        FROM restaurants r
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE r.owner_id = ? AND r.status = 'active'
        ORDER BY r.created_at DESC";

$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();

$restaurants = [];
while ($row = $result->fetch_assoc()) {
    $restaurants[] = $row;
}

// الحصول على إحصائيات المالك
$owner_stats = [
    'total_restaurants' => count($restaurants),
    'total_reviews' => 0,
    'active_tasks' => 0,
    'total_rating' => 0
];

foreach ($restaurants as $restaurant) {
    $owner_stats['total_reviews'] += $restaurant['total_reviews'];
    $owner_stats['active_tasks'] += $restaurant['active_tasks'];
    $owner_stats['total_rating'] += $restaurant['rating'];
}

if (count($restaurants) > 0) {
    $owner_stats['avg_rating'] = round($owner_stats['total_rating'] / count($restaurants), 1);
} else {
    $owner_stats['avg_rating'] = 0;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-home"></i> لوحة تحكم المالك</h2>
    
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="owner-stat-card">
                <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                <div class="stat-info">
                    <h3><?php echo $owner_stats['total_restaurants']; ?></h3>
                    <p>مطاعم مسجلة</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="owner-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #0f8c66);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $owner_stats['avg_rating']; ?></h3>
                    <p>متوسط التقييم</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="owner-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #0c7bb3);">
                    <i class="fas fa-comment"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $owner_stats['total_reviews']; ?></h3>
                    <p>إجمالي التقييمات</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="owner-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $owner_stats['active_tasks']; ?></h3>
                    <p>مهام نشطة</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- قائمة المطاعم -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">مطاعمك</h5>
                        <button class="btn btn-primary" onclick="window.location.href='/owner/restaurant/add'">
                            <i class="fas fa-plus"></i> إضافة مطعم جديد
                        </button>
                    </div>
                    
                    <?php if (count($restaurants) > 0): ?>
                        <div class="row">
                            <?php foreach ($restaurants as $restaurant): ?>
                            <div class="col-md-6 mb-4">
                                <div class="restaurant-card">
                                    <div class="restaurant-header">
                                        <div class="restaurant-info">
                                            <h5><?php echo $restaurant['name']; ?></h5>
                                            <div class="restaurant-meta">
                                                <span class="badge" style="background-color: <?php echo $restaurant['category_color']; ?>">
                                                    <?php echo $restaurant['category_name']; ?>
                                                </span>
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo $restaurant['city']; ?></span>
                                            </div>
                                        </div>
                                        <div class="restaurant-rating">
                                            <div class="rating-stars">
                                                <?php echo getRatingStars($restaurant['rating']); ?>
                                            </div>
                                            <span><?php echo number_format($restaurant['rating'], 1); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="restaurant-stats mt-3">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="stat">
                                                    <h6><?php echo $restaurant['active_tasks']; ?></h6>
                                                    <small>مهام نشطة</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stat">
                                                    <h6><?php echo $restaurant['today_reviews']; ?></h6>
                                                    <small>تقييمات اليوم</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="restaurant-actions mt-3">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="window.location.href='/restaurant/<?php echo $restaurant['slug']; ?>'">
                                            <i class="fas fa-eye"></i> عرض
                                        </button>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="window.location.href='/owner/restaurant/edit/<?php echo $restaurant['id']; ?>'">
                                            <i class="fas fa-edit"></i> تعديل
                                        </button>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="window.location.href='/owner/restaurant/<?php echo $restaurant['id']; ?>/tasks'">
                                            <i class="fas fa-tasks"></i> المهام
                                        </button>
                                        <button class="btn btn-sm btn-info" 
                                                onclick="window.location.href='/owner/restaurant/<?php echo $restaurant['id']; ?>/analytics'">
                                            <i class="fas fa-chart-bar"></i> إحصائيات
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                            <h4>لا توجد مطاعم مسجلة</h4>
                            <p class="text-muted mb-4">ابدأ بإضافة مطعمك الأول لجذب المزيد من الزبائن</p>
                            <button class="btn btn-primary btn-lg" onclick="window.location.href='/owner/restaurant/add'">
                                <i class="fas fa-plus"></i> إضافة أول مطعم
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- أحدث التقييمات -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">أحدث التقييمات على مطاعمك</h5>
                        <a href="/owner/reviews" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    
                    <div id="recentReviews">
                        <!-- سيتم تحميل التقييمات هنا ديناميكياً -->
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <!-- إحصائيات سريعة -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">إحصائيات سريعة</h5>
                    
                    <div class="quick-stats">
                        <div class="stat-item">
                            <div class="stat-icon bg-primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-details">
                                <h6>متابعون جدد</h6>
                                <p>+24 هذا الأسبوع</p>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon bg-success">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="stat-details">
                                <h6>مشاهدات الصفحة</h6>
                                <p>1,234 هذا الشهر</p>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon bg-warning">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-details">
                                <h6>نمو المبيعات</h6>
                                <p>+15% هذا الشهر</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- نصائح للمالك -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">نصائح لتحسين أداء مطعمك</h5>
                    
                    <div class="tips-list">
                        <div class="tip-item">
                            <div class="tip-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div class="tip-content">
                                <h6>رد على التقييمات</h6>
                                <p>الرد على التقييمات يزيد من ثقة الزبائن</p>
                            </div>
                        </div>
                        
                        <div class="tip-item">
                            <div class="tip-icon">
                                <i class="fas fa-gift"></i>
                            </div>
                            <div class="tip-content">
                                <h6>عروض خاصة</h6>
                                <p>قدم عروضاً حصرية لجذب المزيد من الزبائن</p>
                            </div>
                        </div>
                        
                        <div class="tip-item">
                            <div class="tip-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="tip-content">
                                <h6>شارك تحديثاتك</h6>
                                <p>شارك أخبار مطعمك لجذب المتابعين</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- روابط سريعة -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">روابط سريعة</h5>
                    
                    <div class="quick-links">
                        <a href="/owner/ratings" class="quick-link">
                            <i class="fas fa-star"></i>
                            <span>إدارة التقييمات</span>
                        </a>
                        
                        <a href="/owner/tasks" class="quick-link">
                            <i class="fas fa-tasks"></i>
                            <span>إدارة المهام</span>
                        </a>
                        
                        <a href="/owner/reports" class="quick-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>التقارير</span>
                        </a>
                        
                        <a href="/owner/messages" class="quick-link">
                            <i class="fas fa-comments"></i>
                            <span>المراسلات</span>
                        </a>
                        
                        <a href="/owner/support" class="quick-link">
                            <i class="fas fa-headset"></i>
                            <span>الدعم الفني</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل أحدث التقييمات
async function loadRecentReviews() {
    try {
        const response = await fetch('/api/owner?action=recent-reviews');
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            if (data.data.length === 0) {
                html = '<div class="text-center py-3"><p>لا توجد تقييمات حديثة</p></div>';
            } else {
                data.data.forEach(review => {
                    const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
                    const timeAgo = formatRelativeTime(review.created_at);
                    
                    html += `
                        <div class="review-item mb-3 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">${review.restaurant_name}</h6>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            ${review.user_name.charAt(0)}
                                        </div>
                                        <div>
                                            <strong>${review.user_name}</strong>
                                            <div class="rating-stars" style="color: #ffc107;">${stars}</div>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">${timeAgo}</small>
                            </div>
                            
                            ${review.comment ? `
                            <p class="mb-2">${review.comment}</p>
                            ` : ''}
                            
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="replyToReview(${review.id})">
                                    <i class="fas fa-reply"></i> رد
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="viewReviewDetails(${review.id})">
                                    <i class="fas fa-eye"></i> تفاصيل
                                </button>
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('recentReviews').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
    }
}

// دالة المساعدة لصياغة الوقت النسبي
function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);
    
    if (diffDay > 0) {
        return `قبل ${diffDay} يوم`;
    } else if (diffHour > 0) {
        return `قبل ${diffHour} ساعة`;
    } else if (diffMin > 0) {
        return `قبل ${diffMin} دقيقة`;
    } else {
        return 'الآن';
    }
}

// تحميل البيانات عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadRecentReviews();
});
</script>

<style>
.owner-stat-card {
    display: flex;
    align-items: center;
    padding: 20px;
    background-color: white;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow);
    height: 100%;
}

.owner-stat-card .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    font-size: 1.5rem;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}

.owner-stat-card .stat-info h3 {
    margin-bottom: 5px;
    color: var(--dark-color);
    font-size: 1.8rem;
}

.owner-stat-card .stat-info p {
    margin-bottom: 0;
    color: var(--gray-color);
}

.restaurant-card {
    padding: 20px;
    background-color: white;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow);
    height: 100%;
    transition: var(--transition);
}

.restaurant-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.restaurant-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.restaurant-info h5 {
    margin-bottom: 10px;
    color: var(--dark-color);
}

.restaurant-meta {
    display: flex;
    gap: 10px;
    align-items: center;
}

.restaurant-meta .badge {
    padding: 5px 10px;
    border-radius: 20px;
}

.restaurant-rating {
    text-align: center;
}

.restaurant-rating .rating-stars {
    margin-bottom: 5px;
}

.restaurant-stats {
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    padding: 15px 0;
}

.restaurant-stats .stat h6 {
    color: var(--primary-color);
    margin-bottom: 5px;
    font-size: 1.2rem;
}

.restaurant-stats .stat small {
    color: var(--gray-color);
}

.restaurant-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.quick-stats .stat-item {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.quick-stats .stat-item:last-child {
    margin-bottom: 0;
}

.quick-stats .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    font-size: 1.2rem;
}

.quick-stats .stat-details h6 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.quick-stats .stat-details p {
    margin-bottom: 0;
    color: var(--gray-color);
    font-size: 0.9rem;
}

.tips-list .tip-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
}

.tips-list .tip-item:last-child {
    margin-bottom: 0;
}

.tips-list .tip-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    background-color: var(--light-color);
    color: var(--primary-color);
}

.tips-list .tip-content h6 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.tips-list .tip-content p {
    margin-bottom: 0;
    color: var(--gray-color);
    font-size: 0.9rem;
}

.quick-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.quick-link {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    background-color: var(--light-color);
    border-radius: var(--border-radius);
    color: var(--dark-color);
    text-decoration: none;
    transition: var(--transition);
    border: 1px solid var(--border-color);
}

.quick-link:hover {
    background-color: var(--primary-color);
    color: white;
    transform: translateX(-5px);
}

.quick-link i {
    margin-right: 10px;
    font-size: 1.2rem;
}

.review-item .user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}
</style>

<?php
include 'includes/footer.php';
?>