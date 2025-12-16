<?php
// التحقق من صلاحيات العضو
if (!isset($user) || !in_array($user->getRole(), ['member', 'moderator'])) {
    header('Location: /login');
    exit;
}

$page_title = 'لوحة تحكم العضو';
$page_scripts = ['member-dashboard.js'];

include 'includes/header.php';

// الحصول على إحصائيات العضو
$stats = $user->getStatistics();

// الحصول على المهام النشطة
$active_tasks = [];
$sql = "SELECT ut.*, t.title, t.description, t.points_reward, t.discount_percentage,
               r.name as restaurant_name, r.city, r.logo
        FROM user_tasks ut
        JOIN tasks t ON ut.task_id = t.id
        JOIN restaurants r ON t.restaurant_id = r.id
        WHERE ut.user_id = ? AND ut.status IN ('reserved', 'in_progress')
        ORDER BY ut.created_at DESC
        LIMIT 3";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $active_tasks[] = $row;
}

// الحصول على أحدث التقييمات
$recent_reviews = [];
$sql = "SELECT r.*, res.name as restaurant_name, res.slug as restaurant_slug
        FROM reviews r
        JOIN restaurants res ON r.restaurant_id = res.id
        WHERE r.user_id = ? AND r.status = 'active'
        ORDER BY r.created_at DESC
        LIMIT 5";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_reviews[] = $row;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-home"></i> الصفحة الرئيسية</h2>
    
    <!-- رصيد النقاط والإحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <h3><?php echo formatPoints($user->getPoints()); ?></h3>
                    <p>رصيد النقاط</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success-color), #0f8c66);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['completed_tasks']; ?></h3>
                    <p>المهام المكتملة</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info-color), #0c7bb3);">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['active_tasks']; ?></h3>
                    <p>المهام النشطة</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning-color), #d97706);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_reviews']; ?></h3>
                    <p>التقييمات المضافة</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- المهام النشطة -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">مهامك النشطة</h5>
                        <a href="/member/tasks" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    
                    <?php if (count($active_tasks) > 0): ?>
                        <?php foreach ($active_tasks as $task): 
                            $is_expired = strtotime($task['code_expires']) < time();
                        ?>
                        <div class="task-item mb-3 p-3 border rounded <?php echo $is_expired ? 'border-danger' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6><?php echo $task['title']; ?></h6>
                                    <p class="mb-1">
                                        <i class="fas fa-utensils"></i> <?php echo $task['restaurant_name']; ?>
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-map-marker-alt"></i> <?php echo $task['city']; ?>
                                    </p>
                                    <div class="d-flex gap-2 mt-2">
                                        <span class="badge bg-primary"><?php echo $task['points_reward']; ?> نقطة</span>
                                        <span class="badge bg-success">خصم <?php echo $task['discount_percentage']; ?>%</span>
                                        <?php if ($is_expired): ?>
                                        <span class="badge bg-danger">منتهي</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <?php if ($is_expired): ?>
                                    <button class="btn btn-sm btn-danger" onclick="renewTask(<?php echo $task['id']; ?>)">
                                        تجديد
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-primary" onclick="showTaskCode(<?php echo $task['id']; ?>)">
                                        عرض الكود
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($task['description']): ?>
                            <p class="mt-2 mb-0 small text-muted"><?php echo $task['description']; ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    تنتهي في: <?php echo date('Y-m-d H:i', strtotime($task['code_expires'])); ?>
                                </small>
                                <button class="btn btn-sm btn-success" onclick="completeTask(<?php echo $task['id']; ?>)">
                                    <i class="fas fa-check-circle"></i> إكمال المهمة
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tasks fa-2x text-muted mb-3"></i>
                            <p>لا توجد مهام نشطة حالياً</p>
                            <a href="/tasks" class="btn btn-primary">استعرض المهام المتاحة</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- أفضل المطاعم في مدينتك -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">أفضل المطاعم في <?php echo $user->getCity(); ?></h5>
                        <a href="/restaurants?city=<?php echo urlencode($user->getCity()); ?>" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    
                    <div id="cityRestaurants">
                        <!-- سيتم تحميل المطاعم هنا ديناميكياً -->
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
            <!-- أحدث التقييمات -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">أحدث تقييماتك</h5>
                    
                    <?php if (count($recent_reviews) > 0): ?>
                        <?php foreach ($recent_reviews as $review): ?>
                        <div class="review-item mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo $review['restaurant_name']; ?></h6>
                                    <div class="rating-stars small">
                                        <?php echo getRatingStars($review['rating']); ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo formatRelativeTime($review['created_at']); ?></small>
                            </div>
                            <?php if ($review['comment']): ?>
                            <p class="mt-2 mb-0 small"><?php echo substr($review['comment'], 0, 60); ?>...</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="/member/profile#reviews" class="btn btn-sm btn-outline-primary">عرض جميع التقييمات</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-star fa-2x text-muted mb-2"></i>
                            <p>لا توجد تقييمات بعد</p>
                            <a href="/restaurants" class="btn btn-sm btn-primary">ابدأ بالتقييم</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- نصائح سريعة -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">نصائح سريعة</h5>
                    
                    <div class="tip-item mb-3">
                        <div class="tip-icon bg-primary">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="tip-content">
                            <h6>كسب المزيد من النقاط</h6>
                            <p>اكمل مهام جديدة لتحصل على نقاط أكثر</p>
                        </div>
                    </div>
                    
                    <div class="tip-item mb-3">
                        <div class="tip-icon bg-success">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="tip-content">
                            <h6>استبدل نقاطك</h6>
                            <p>استبدل نقاطك بهدايا من متجر النقاط</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <div class="tip-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="tip-content">
                            <h6>انتبه للوقت</h6>
                            <p>أكمل مهامك قبل انتهاء صلاحية الكود</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- روابط سريعة -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">روابط سريعة</h5>
                    
                    <div class="quick-links">
                        <a href="/member/tasks" class="quick-link">
                            <i class="fas fa-tasks"></i>
                            <span>المهام المتاحة</span>
                        </a>
                        
                        <a href="/member/store" class="quick-link">
                            <i class="fas fa-gift"></i>
                            <span>متجر النقاط</span>
                        </a>
                        
                        <a href="/member/leaderboard" class="quick-link">
                            <i class="fas fa-trophy"></i>
                            <span>المتصدرون</span>
                        </a>
                        
                        <a href="/member/profile" class="quick-link">
                            <i class="fas fa-user"></i>
                            <span>حسابي</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال عرض كود المهمة -->
<div class="modal fade" id="taskCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">كود الخصم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="barcode mb-3" id="taskCodeBarcode"></div>
                    <h4 id="taskCodeText" class="mb-3"></h4>
                    <p class="text-muted" id="taskCodeExpiry"></p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        كود الخصم صالح لمرة واحدة فقط
                    </p>
                    <button class="btn btn-outline-primary" onclick="copyTaskCode()">
                        <i class="fas fa-copy"></i> نسخ الكود
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال إكمال المهمة -->
<div class="modal fade" id="completeTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إكمال المهمة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="completeTaskForm">
                    <input type="hidden" id="complete_task_id">
                    
                    <div class="mb-3">
                        <label class="form-label">رابط مراجعتك على خرائط جوجل <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="review_link" required>
                        <small class="text-muted">أدخل رابط المراجعة التي رفعتها على خرائط جوجل</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">تقييم المطعم <span class="text-danger">*</span></label>
                        <div class="rating-input">
                            <div class="stars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="complete_star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                                <label for="complete_star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="review_comment" class="form-label">تعليق (اختياري)</label>
                        <textarea class="form-control" id="review_comment" rows="3" placeholder="شارك تجربتك مع الآخرين..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تنبيه:</strong> يجب أن تكون المراجعة حقيقية ومتوافقة مع شروط الاستخدام
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="submitCompleteTask()">إكمال المهمة</button>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل المطاعم عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadCityRestaurants();
});

// تحميل أفضل المطاعم في المدينة
async function loadCityRestaurants() {
    try {
        const response = await fetch(`/api/restaurants?action=get-top&city=<?php echo urlencode($user->getCity()); ?>&limit=3`);
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            data.data.forEach(restaurant => {
                const ratingStars = getRatingStars(restaurant.rating);
                
                html += `
                    <div class="restaurant-item mb-3">
                        <div class="d-flex align-items-start">
                            <div class="restaurant-avatar me-3">
                                ${restaurant.name.charAt(0)}
                            </div>
                            <div style="flex: 1;">
                                <h6 class="mb-1">${restaurant.name}</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rating-stars me-2">${ratingStars}</div>
                                    <span>${restaurant.rating}</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="/restaurant/${restaurant.slug}" class="btn btn-sm btn-outline-primary">التفاصيل</a>
                                    <button class="btn btn-sm btn-primary" onclick="viewTasks(${restaurant.id})">عرض المهام</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('cityRestaurants').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading restaurants:', error);
    }
}

// عرض كود المهمة
async function showTaskCode(taskId) {
    try {
        const response = await fetch(`/api/member?action=get-task-code&task_id=${taskId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            document.getElementById('taskCodeText').textContent = data.data.discount_code;
            document.getElementById('taskCodeExpiry').textContent = `ينتهي في: ${data.data.expires_at}`;
            
            // إنشاء باركود نصي
            const barcode = document.getElementById('taskCodeBarcode');
            barcode.innerHTML = '';
            for (let i = 0; i < data.data.discount_code.length; i++) {
                const bar = document.createElement('div');
                bar.style.display = 'inline-block';
                bar.style.height = '40px';
                bar.style.width = '3px';
                bar.style.backgroundColor = i % 2 === 0 ? '#000' : '#fff';
                bar.style.margin = '0 1px';
                barcode.appendChild(bar);
            }
            
            const modal = new bootstrap.Modal(document.getElementById('taskCodeModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading task code:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل كود المهمة', 'error');
    }
}

// نسخ كود المهمة
function copyTaskCode() {
    const code = document.getElementById('taskCodeText').textContent;
    navigator.clipboard.writeText(code).then(() => {
        showNotification('نجاح', 'تم نسخ الكود إلى الحافظة');
    });
}

// إكمال المهمة
function completeTask(taskId) {
    document.getElementById('complete_task_id').value = taskId;
    
    const modal = new bootstrap.Modal(document.getElementById('completeTaskModal'));
    modal.show();
}

// إرسال إكمال المهمة
async function submitCompleteTask() {
    const taskId = document.getElementById('complete_task_id').value;
    const reviewLink = document.getElementById('review_link').value;
    const rating = document.querySelector('input[name="rating"]:checked');
    const comment = document.getElementById('review_comment').value;
    
    if (!rating) {
        showNotification('خطأ', 'يرجى اختيار تقييم', 'error');
        return;
    }
    
    if (!reviewLink) {
        showNotification('خطأ', 'يرجى إدخال رابط المراجعة', 'error');
        return;
    }
    
    try {
        const response = await fetch('/api/member?action=complete-task', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `task_id=${taskId}&review_link=${encodeURIComponent(reviewLink)}&rating=${rating.value}&comment=${encodeURIComponent(comment)}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', `تم إكمال المهمة بنجاح! ربحت ${data.data.points_earned} نقطة`);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('completeTaskModal'));
            modal.hide();
            
            // تحديث الصفحة بعد 2 ثانية
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error completing task:', error);
        showNotification('خطأ', 'حدث خطأ أثناء إكمال المهمة', 'error');
    }
}

// تجديد المهمة
async function renewTask(taskId) {
    if (confirm('هل تريد تجديد هذه المهمة؟ سيتم إنشاء كود خصم جديد')) {
        try {
            const response = await fetch('/api/member?action=renew-task', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `task_id=${taskId}`
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                showNotification('نجاح', 'تم تجديد المهمة بنجاح');
                location.reload();
            } else {
                showNotification('خطأ', data.message, 'error');
            }
        } catch (error) {
            console.error('Error renewing task:', error);
            showNotification('خطأ', 'حدث خطأ أثناء تجديد المهمة', 'error');
        }
    }
}

// عرض مهام مطعم معين
function viewTasks(restaurantId) {
    window.location.href = `/tasks?restaurant_id=${restaurantId}`;
}

// دالة المساعدة لعرض النجوم
function getRatingStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = (rating - fullStars) >= 0.5;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    
    let stars = '';
    
    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="fas fa-star text-warning"></i>';
    }
    
    if (hasHalfStar) {
        stars += '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="far fa-star text-warning"></i>';
    }
    
    return stars;
}
</script>

<style>
.restaurant-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.tip-item:last-child {
    margin-bottom: 0;
}

.tip-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    flex-shrink: 0;
}

.tip-content h6 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.tip-content p {
    margin-bottom: 0;
    color: var(--gray-color);
    font-size: 0.9rem;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.quick-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
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
    transform: translateY(-2px);
}

.quick-link i {
    font-size: 1.5rem;
    margin-bottom: 8px;
}

.quick-link span {
    font-size: 0.9rem;
}

.barcode {
    padding: 20px;
    background-color: white;
    border-radius: 10px;
    border: 1px dashed #ccc;
    direction: ltr;
}

.rating-input .stars {
    direction: ltr;
    display: flex;
    justify-content: center;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-input label {
    color: #ddd;
    font-size: 2rem;
    padding: 0 5px;
    cursor: pointer;
    transition: var(--transition);
}

.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input:checked ~ label {
    color: #ffc107;
}
</style>

<?php
include 'includes/footer.php';
?>