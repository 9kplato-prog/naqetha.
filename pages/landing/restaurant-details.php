<?php
$restaurant_id = $_GET['id'] ?? 0;
$restaurant_slug = $_GET['slug'] ?? '';

if (!$restaurant_id && !$restaurant_slug) {
    header('Location: /restaurants');
    exit;
}

$restaurant = new Restaurant($db->getConnection());

if ($restaurant_id) {
    $restaurant->getById($restaurant_id);
} else {
    $restaurant->getBySlug($restaurant_slug);
}

if (!$restaurant->getId()) {
    header('Location: /restaurants');
    exit;
}

$page_title = $restaurant->getName();
$page_scripts = ['restaurant-details.js'];

// الحصول على إحصائيات التقييم
$rating_distribution = $restaurant->getRatingDistribution();

// الحصول على آخر التقييمات
$reviews = $restaurant->getReviews(10, 0);

// الحصول على المهام المتاحة
$tasks = $restaurant->getTasks('available', 5, 0);

include 'includes/header.php';
?>

<div class="container">
    <!-- مسار التنقل -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
            <li class="breadcrumb-item"><a href="/restaurants">المطاعم</a></li>
            <li class="breadcrumb-item active"><?php echo $restaurant->getName(); ?></li>
        </ol>
    </nav>
    
    <!-- صورة الغلاف -->
    <div class="restaurant-cover mb-4">
        <?php if ($restaurant->getCoverImage()): ?>
        <img src="/uploads/images/<?php echo $restaurant->getCoverImage(); ?>" class="img-fluid rounded" alt="<?php echo $restaurant->getName(); ?>">
        <?php else: ?>
        <div class="cover-placeholder">
            <i class="fas fa-utensils"></i>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- معلومات المطعم -->
    <div class="row mb-5">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start mb-4">
                        <?php if ($restaurant->getLogo()): ?>
                        <img src="/uploads/images/<?php echo $restaurant->getLogo(); ?>" class="restaurant-logo me-3" alt="<?php echo $restaurant->getName(); ?>">
                        <?php endif; ?>
                        
                        <div>
                            <h1 class="card-title"><?php echo $restaurant->getName(); ?></h1>
                            <div class="d-flex align-items-center mb-2">
                                <div class="rating-stars me-2">
                                    <?php
                                    $rating = $restaurant->getRating();
                                    $fullStars = floor($rating);
                                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                    
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $fullStars) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } elseif ($hasHalfStar && $i == $fullStars + 1) {
                                            echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-warning"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="rating-number"><?php echo number_format($rating, 1); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo $restaurant->getTotalReviews(); ?> تقييم</span>
                            </div>
                            
                            <div class="restaurant-meta">
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo $restaurant->getCity(); ?> - <?php echo $restaurant->getAddress(); ?></p>
                                <?php if ($restaurant->getPhone()): ?>
                                <p><i class="fas fa-phone"></i> <a href="tel:<?php echo $restaurant->getPhone(); ?>"><?php echo $restaurant->getPhone(); ?></a></p>
                                <?php endif; ?>
                                <?php if ($restaurant->getEmail()): ?>
                                <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo $restaurant->getEmail(); ?>"><?php echo $restaurant->getEmail(); ?></a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($restaurant->getDescription()): ?>
                    <div class="restaurant-description mb-4">
                        <h5>عن المطعم</h5>
                        <p><?php echo nl2br($restaurant->getDescription()); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- خريطة الموقع -->
                    <?php if ($restaurant->getLatitude() && $restaurant->getLongitude()): ?>
                    <div class="restaurant-map mb-4">
                        <h5>الموقع</h5>
                        <div id="map" style="height: 300px; border-radius: var(--border-radius);"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- توزيع التقييمات -->
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">توزيع التقييمات</h5>
                    <div class="rating-summary">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="rating-average text-center">
                                    <div class="score"><?php echo number_format($rating_distribution['average'], 1); ?></div>
                                    <div class="rating-stars">
                                        <?php echo getRatingStars($rating_distribution['average']); ?>
                                    </div>
                                    <p>من <?php echo $rating_distribution['total']; ?> تقييم</p>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="rating-bars">
                                    <?php for ($i = 5; $i >= 1; $i--): 
                                        $percentage = $rating_distribution['distribution'][$i]['percentage'];
                                    ?>
                                    <div class="rating-bar mb-2">
                                        <span class="label"><?php echo $i; ?> نجوم</span>
                                        <div class="bar">
                                            <div class="fill" style="width: <?php echo $percentage; ?>%;"></div>
                                        </div>
                                        <span class="percentage"><?php echo $percentage; ?>%</span>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- آخر التقييمات -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">آخر التقييمات</h5>
                        <button class="btn btn-outline-primary btn-sm" onclick="viewAllReviews()">
                            عرض جميع التقييمات
                        </button>
                    </div>
                    
                    <div id="reviewsList">
                        <?php if (count($reviews) > 0): ?>
                            <?php foreach ($reviews as $review): ?>
                            <div class="review-card mb-3">
                                <div class="review-header">
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?php echo mb_substr($review['user_name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo $review['user_name']; ?></strong>
                                            <div class="rating-stars">
                                                <?php echo getRatingStars($review['rating']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo formatRelativeTime($review['created_at']); ?></small>
                                </div>
                                <div class="review-body mt-2">
                                    <p><?php echo nl2br($review['comment']); ?></p>
                                </div>
                                <?php if ($review['reply']): ?>
                                <div class="review-reply mt-3 p-3 bg-light rounded">
                                    <strong><i class="fas fa-reply"></i> رد المطعم:</strong>
                                    <p class="mb-0 mt-1"><?php echo nl2br($review['reply']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                <p>لا توجد تقييمات بعد</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <!-- المهام المتاحة -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">المهام المتاحة</h5>
                    
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach ($tasks as $task): ?>
                        <div class="task-item mb-3 p-3 border rounded">
                            <h6><?php echo $task['title']; ?></h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge bg-primary"><?php echo $task['points_reward']; ?> نقطة</span>
                                <span class="badge bg-success">خصم <?php echo $task['discount_percentage']; ?>%</span>
                            </div>
                            <p class="small mb-2"><?php echo $task['description']; ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">المتبقي: <?php echo $task['max_participants'] - $task['current_participants']; ?></small>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="btn btn-primary btn-sm" onclick="reserveTask(<?php echo $task['id']; ?>)">
                                    احجز المهمة
                                </button>
                                <?php else: ?>
                                <a href="/login" class="btn btn-outline-primary btn-sm">سجل الدخول للحجز</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <a href="/tasks?restaurant_id=<?php echo $restaurant->getId(); ?>" class="btn btn-outline-primary w-100">
                            عرض جميع المهام
                        </a>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-tasks fa-2x text-muted mb-2"></i>
                            <p>لا توجد مهام متاحة حالياً</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- إحصائيات المطعم -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">إحصائيات المطعم</h5>
                    <div class="stats-grid">
                        <div class="stat-item text-center p-3">
                            <div class="stat-number"><?php echo $restaurant->getTotalReviews(); ?></div>
                            <div class="stat-label">إجمالي التقييمات</div>
                        </div>
                        <div class="stat-item text-center p-3">
                            <div class="stat-number"><?php echo number_format($restaurant->getRating(), 1); ?></div>
                            <div class="stat-label">متوسط التقييم</div>
                        </div>
                    </div>
                    
                    <!-- إضافة تقييم -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="mt-4">
                        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                            <i class="fas fa-star"></i> أضف تقييمك
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="mt-4">
                        <a href="/login?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-sign-in-alt"></i> سجل الدخول لتقييم المطعم
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- مشاركة المطعم -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">شارك المطعم</h5>
                    <div class="social-share">
                        <button class="btn btn-outline-primary btn-sm me-2" onclick="shareOnFacebook()">
                            <i class="fab fa-facebook"></i>
                        </button>
                        <button class="btn btn-outline-info btn-sm me-2" onclick="shareOnTwitter()">
                            <i class="fab fa-twitter"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm me-2" onclick="shareOnWhatsApp()">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyLink()">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال إضافة تقييم -->
<?php if (isset($_SESSION['user_id'])): ?>
<div class="modal fade" id="addReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">أضف تقييمك</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addReviewForm">
                    <input type="hidden" id="restaurant_id" value="<?php echo $restaurant->getId(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">التقييم</label>
                        <div class="rating-input">
                            <div class="stars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                                <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="comment" class="form-label">التعليق (اختياري)</label>
                        <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="شارك تجربتك مع الآخرين..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <small>سيتم منحك <?php echo POINTS_PER_REVIEW; ?> نقطة مقابل كل تقييم تضيفه</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="submitReview()">إضافة التقييم</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- مودال حجز المهمة -->
<div class="modal fade" id="reserveTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">حجز المهمة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="taskDetails"></div>
                
                <div class="legal-agreement mt-3">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>إشعار قانوني:</strong> أنت مسؤول عن جميع التقييمات والمراجعات التي تقدمها.
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="legalAgreement">
                        <label class="form-check-label" for="legalAgreement">
                            أوافق على أنني أتحمل المسؤولية الكاملة عن التقييم الذي سأرفعه
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="confirmReserveBtn" onclick="confirmReserveTask()">تأكيد الحجز</button>
            </div>
        </div>
    </div>
</div>

<script>
// تهيئة الخريطة
<?php if ($restaurant->getLatitude() && $restaurant->getLongitude()): ?>
function initMap() {
    const map = L.map('map').setView([<?php echo $restaurant->getLatitude(); ?>, <?php echo $restaurant->getLongitude(); ?>], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    L.marker([<?php echo $restaurant->getLatitude(); ?>, <?php echo $restaurant->getLongitude(); ?>])
        .addTo(map)
        .bindPopup('<?php echo addslashes($restaurant->getName()); ?>')
        .openPopup();
}

// تحميل مكتبة Leaflet
if (document.getElementById('map')) {
    const leafletCSS = document.createElement('link');
    leafletCSS.rel = 'stylesheet';
    leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(leafletCSS);
    
    const leafletScript = document.createElement('script');
    leafletScript.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    leafletScript.onload = initMap;
    document.head.appendChild(leafletScript);
}
<?php endif; ?>

// إضافة تقييم
async function submitReview() {
    const rating = document.querySelector('input[name="rating"]:checked');
    const comment = document.getElementById('comment').value;
    const restaurantId = document.getElementById('restaurant_id').value;
    
    if (!rating) {
        showNotification('خطأ', 'يرجى اختيار تقييم', 'error');
        return;
    }
    
    try {
        const response = await fetch('/api/restaurants?action=add-review', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `restaurant_id=${restaurantId}&rating=${rating.value}&comment=${encodeURIComponent(comment)}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', 'تم إضافة تقييمك بنجاح!');
            
            // إغلاق المودال
            const modal = bootstrap.Modal.getInstance(document.getElementById('addReviewModal'));
            modal.hide();
            
            // تحديث الصفحة بعد 2 ثانية
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error submitting review:', error);
        showNotification('خطأ', 'حدث خطأ أثناء إضافة التقييم', 'error');
    }
}

// حجز مهمة
async function reserveTask(taskId) {
    try {
        const response = await fetch(`/api/tasks?action=get-task-details&task_id=${taskId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const task = data.data;
            
            document.getElementById('taskDetails').innerHTML = `
                <h6>${task.title}</h6>
                <p>${task.description}</p>
                <div class="row mb-2">
                    <div class="col-6">
                        <strong>النقاط:</strong> ${task.points_reward}
                    </div>
                    <div class="col-6">
                        <strong>الخصم:</strong> ${task.discount_percentage}%
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-6">
                        <strong>المطعم:</strong> ${task.restaurant_name}
                    </div>
                    <div class="col-6">
                        <strong>المدينة:</strong> ${task.city}
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <strong>المتبقي:</strong> ${task.max_participants - task.current_participants} من ${task.max_participants}
                    </div>
                </div>
                <hr>
                <p><strong>كيف تعمل المهمة:</strong></p>
                <ol>
                    <li>احصل على كود الخصم بعد الحجز</li>
                    <li>زُر المطعم واستخدم الكود</li>
                    <li>ارفع مراجعتك على خرائط جوجل</li>
                    <li>أدخل رابط المراجعة في النظام</li>
                    <li>احصل على ${task.points_reward} نقطة</li>
                </ol>
            `;
            
            // تخزين معرف المهمة في الزر
            document.getElementById('confirmReserveBtn').setAttribute('data-task-id', taskId);
            
            // إظهار المودال
            const modal = new bootstrap.Modal(document.getElementById('reserveTaskModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading task details:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل تفاصيل المهمة', 'error');
    }
}

// تأكيد حجز المهمة
async function confirmReserveTask() {
    const taskId = document.getElementById('confirmReserveBtn').getAttribute('data-task-id');
    const agreement = document.getElementById('legalAgreement');
    
    if (!agreement.checked) {
        showNotification('خطأ', 'يجب الموافقة على الإشعار القانوني', 'error');
        return;
    }
    
    try {
        const response = await fetch('/api/tasks?action=reserve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `task_id=${taskId}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', 'تم حجز المهمة بنجاح! تحقق من بريدك الإلكتروني للحصول على كود الخصم');
            
            // إغلاق المودال
            const modal = bootstrap.Modal.getInstance(document.getElementById('reserveTaskModal'));
            modal.hide();
            
            // تحديث الصفحة بعد 2 ثانية
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error reserving task:', error);
        showNotification('خطأ', 'حدث خطأ أثناء حجز المهمة', 'error');
    }
}

// مشاركة المطعم
function shareOnFacebook() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent(`جربت ${document.title} وكانت تجربة رائعة!`);
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${text}`, '_blank');
}

function shareOnTwitter() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent(`جربت ${document.title} وكانت تجربة رائعة!`);
    window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank');
}

function shareOnWhatsApp() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent(`جربت ${document.title} وكانت تجربة رائعة!`);
    window.open(`https://wa.me/?text=${text}%20${url}`, '_blank');
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        showNotification('نجاح', 'تم نسخ الرابط إلى الحافظة');
    });
}

// عرض جميع التقييمات
function viewAllReviews() {
    window.location.href = `/restaurant/<?php echo $restaurant->getId(); ?>/reviews`;
}
</script>

<style>
.restaurant-logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary-color);
}

.cover-placeholder {
    height: 300px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 4rem;
}

.rating-average {
    padding: 20px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: var(--border-radius);
}

.rating-average .score {
    font-size: 3rem;
    font-weight: bold;
}

.rating-bar .bar {
    flex: 1;
    height: 10px;
    background-color: #e2e8f0;
    border-radius: 5px;
    overflow: hidden;
}

.rating-bar .fill {
    height: 100%;
    background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.stat-item {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--card-bg);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--gray-color);
}
</style>

<?php
include 'includes/footer.php';
?>