<?php
// التحقق من صلاحيات المالك
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'إدارة التقييمات';
$page_scripts = ['owner-ratings.js'];

include 'includes/header.php';

// الحصول على معرّف المطعم من الرابط (اختياري)
$restaurant_id = $_GET['restaurant_id'] ?? 0;

// الحصول على مطاعم المالك للفلترة
$sql = "SELECT id, name FROM restaurants WHERE owner_id = ? AND status = 'active' ORDER BY name";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();

$owner_restaurants = [];
while ($row = $result->fetch_assoc()) {
    $owner_restaurants[] = $row;
}

// الحصول على التقييمات
$where_clause = "r.restaurant_id IN (SELECT id FROM restaurants WHERE owner_id = ?)";
$params = [$user->getId()];
$types = "i";

if ($restaurant_id > 0) {
    $where_clause .= " AND r.restaurant_id = ?";
    $params[] = $restaurant_id;
    $types .= "i";
}

$sql = "SELECT r.*, u.name as user_name, u.avatar as user_avatar,
               res.name as restaurant_name, res.slug as restaurant_slug
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN restaurants res ON r.restaurant_id = res.id
        WHERE $where_clause AND r.status = 'active'
        ORDER BY r.created_at DESC
        LIMIT 20";

$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-star"></i> إدارة التقييمات</h2>
    
    <!-- فلترة التقييمات -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="restaurantFilter" class="form-label">المطعم:</label>
                    <select id="restaurantFilter" class="form-select" onchange="filterReviews()">
                        <option value="0">جميع المطاعم</option>
                        <?php foreach ($owner_restaurants as $restaurant): ?>
                        <option value="<?php echo $restaurant['id']; ?>" <?php echo $restaurant_id == $restaurant['id'] ? 'selected' : ''; ?>>
                            <?php echo $restaurant['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="ratingFilter" class="form-label">التقييم:</label>
                    <select id="ratingFilter" class="form-select" onchange="filterReviews()">
                        <option value="0">جميع التقييمات</option>
                        <option value="5">5 نجوم</option>
                        <option value="4">4 نجوم</option>
                        <option value="3">3 نجوم</option>
                        <option value="2">2 نجوم</option>
                        <option value="1">1 نجمة</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="dateFilter" class="form-label">الفترة:</label>
                    <select id="dateFilter" class="form-select" onchange="filterReviews()">
                        <option value="all">جميع الأوقات</option>
                        <option value="today">اليوم</option>
                        <option value="week">أخر أسبوع</option>
                        <option value="month">أخر شهر</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- إحصائيات التقييمات -->
    <div class="row mb-4">
        <?php
        // حساب إحصائيات التقييمات
        $rating_stats = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0
        ];
        
        $total_reviews = 0;
        $average_rating = 0;
        
        foreach ($reviews as $review) {
            $rating_stats[$review['rating']]++;
            $total_reviews++;
            $average_rating += $review['rating'];
        }
        
        if ($total_reviews > 0) {
            $average_rating = round($average_rating / $total_reviews, 1);
        }
        ?>
        
        <div class="col-md-3 mb-3">
            <div class="rating-stat-card">
                <div class="stat-number"><?php echo $average_rating; ?></div>
                <div class="stat-label">متوسط التقييم</div>
                <div class="rating-stars">
                    <?php echo getRatingStars($average_rating); ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="rating-stat-card">
                <div class="stat-number"><?php echo $total_reviews; ?></div>
                <div class="stat-label">إجمالي التقييمات</div>
                <div class="stat-icon">
                    <i class="fas fa-comment"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="rating-stat-card">
                <div class="stat-number"><?php echo $rating_stats[5]; ?></div>
                <div class="stat-label">تقييمات 5 نجوم</div>
                <div class="rating-stars">
                    <i class="fas fa-star text-warning"></i>
                    <i class="fas fa-star text-warning"></i>
                    <i class="fas fa-star text-warning"></i>
                    <i class="fas fa-star text-warning"></i>
                    <i class="fas fa-star text-warning"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="rating-stat-card">
                <div class="stat-number"><?php echo $rating_stats[1]; ?></div>
                <div class="stat-label">تقييمات 1 نجمة</div>
                <div class="rating-stars">
                    <i class="fas fa-star text-warning"></i>
                    <i class="far fa-star text-warning"></i>
                    <i class="far fa-star text-warning"></i>
                    <i class="far fa-star text-warning"></i>
                    <i class="far fa-star text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- قائمة التقييمات -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="card-title mb-0">آخر التقييمات</h5>
                <div class="btn-group">
                    <button class="btn btn-outline-primary btn-sm" onclick="exportReviews()">
                        <i class="fas fa-download"></i> تصدير
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="refreshReviews()">
                        <i class="fas fa-sync"></i> تحديث
                    </button>
                </div>
            </div>
            
            <div id="reviewsContainer">
                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card mb-4">
                        <div class="review-header">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3">
                                    <?php if ($review['user_avatar'] && $review['user_avatar'] !== 'default.png'): ?>
                                    <img src="/uploads/images/<?php echo $review['user_avatar']; ?>" 
                                         alt="<?php echo $review['user_name']; ?>"
                                         class="rounded-circle">
                                    <?php else: ?>
                                    <div class="avatar-initials">
                                        <?php echo mb_substr($review['user_name'], 0, 1, 'UTF-8'); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h6 class="mb-1"><?php echo $review['user_name']; ?></h6>
                                    <div class="rating-stars">
                                        <?php echo getRatingStars($review['rating']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="review-meta">
                                <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></small>
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-utensils"></i> <?php echo $review['restaurant_name']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="review-body mt-3">
                            <?php if ($review['comment']): ?>
                            <p><?php echo nl2br($review['comment']); ?></p>
                            <?php else: ?>
                            <p class="text-muted fst-italic">لا يوجد تعليق</p>
                            <?php endif; ?>
                            
                            <?php if ($review['images']): 
                                $images = json_decode($review['images'], true);
                                if (is_array($images) && count($images) > 0):
                            ?>
                            <div class="review-images mt-3">
                                <div class="row g-2">
                                    <?php foreach ($images as $image): ?>
                                    <div class="col-3">
                                        <a href="/uploads/images/<?php echo $image; ?>" target="_blank">
                                            <img src="/uploads/images/<?php echo $image; ?>" 
                                                 alt="صورة التقييم" 
                                                 class="img-thumbnail">
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; endif; ?>
                        </div>
                        
                        <div class="review-footer mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="review-actions">
                                    <button class="btn btn-sm btn-success" onclick="replyToReview(<?php echo $review['id']; ?>)">
                                        <i class="fas fa-reply"></i> رد
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="thankReviewer(<?php echo $review['id']; ?>)">
                                        <i class="fas fa-heart"></i> شكر
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="reportReview(<?php echo $review['id']; ?>)">
                                        <i class="fas fa-flag"></i> إبلاغ
                                    </button>
                                </div>
                                
                                <div class="review-stats">
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-thumbs-up"></i> <?php echo $review['helpful_count']; ?>
                                    </span>
                                    <?php if ($review['is_verified']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle"></i> تم التحقق
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($review['reply']): ?>
                            <div class="reply-section mt-3 p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><i class="fas fa-reply"></i> ردك:</strong>
                                    <small class="text-muted"><?php echo $review['replied_at'] ? date('Y-m-d H:i', strtotime($review['replied_at'])) : ''; ?></small>
                                </div>
                                <p class="mb-0"><?php echo nl2br($review['reply']); ?></p>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-warning" onclick="editReply(<?php echo $review['id']; ?>)">
                                        <i class="fas fa-edit"></i> تعديل الرد
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-star fa-3x text-muted mb-3"></i>
                        <h4>لا توجد تقييمات بعد</h4>
                        <p class="text-muted">لم تحصل مطاعمك على أي تقييمات حتى الآن</p>
                        <a href="/owner/tasks" class="btn btn-primary">
                            <i class="fas fa-tasks"></i> أنشئ مهام لجذب المزيد من التقييمات
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- مودال الرد على التقييم -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">الرد على التقييم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="replyForm">
                    <input type="hidden" id="reply_review_id">
                    
                    <div class="mb-3">
                        <label for="reply_message" class="form-label">نص الرد <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reply_message" rows="5" required></textarea>
                        <small class="text-muted">الرد الجيد يساعد في بناء علاقة جيدة مع الزبائن</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <small>سيكون ردك مرئياً للجميع تحت التقييم</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="submitReply()">إرسال الرد</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال شكر المقيّم -->
<div class="modal fade" id="thankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">شكر المقيّم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-heart fa-3x text-danger mb-3"></i>
                    <h5>هل تريد شكر هذا المقيّم على تقييمه؟</h5>
                    <p class="text-muted">سيتم إرسال إشعار شكر للمستخدم</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="confirmThankBtn">نعم، أشكره</button>
            </div>
        </div>
    </div>
</div>

<script>
// تصفية التقييمات
function filterReviews() {
    const restaurantId = document.getElementById('restaurantFilter').value;
    const rating = document.getElementById('ratingFilter').value;
    const period = document.getElementById('dateFilter').value;
    
    let url = `/owner/ratings`;
    const params = [];
    
    if (restaurantId > 0) params.push(`restaurant_id=${restaurantId}`);
    if (rating > 0) params.push(`rating=${rating}`);
    if (period !== 'all') params.push(`period=${period}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    window.location.href = url;
}

// الرد على التقييم
function replyToReview(reviewId) {
    document.getElementById('reply_review_id').value = reviewId;
    document.getElementById('reply_message').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('replyModal'));
    modal.show();
}

// إرسال الرد
async function submitReply() {
    const reviewId = document.getElementById('reply_review_id').value;
    const message = document.getElementById('reply_message').value;
    
    if (!message.trim()) {
        showNotification('خطأ', 'يرجى كتابة نص الرد', 'error');
        return;
    }
    
    try {
        const response = await fetch('/api/owner?action=reply-to-review', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `review_id=${reviewId}&reply=${encodeURIComponent(message)}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', 'تم إرسال الرد بنجاح');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('replyModal'));
            modal.hide();
            
            // تحديث الصفحة بعد 2 ثانية
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error submitting reply:', error);
        showNotification('خطأ', 'حدث خطأ أثناء إرسال الرد', 'error');
    }
}

// تعديل الرد
function editReply(reviewId) {
    // يمكن إضافة منطق لتعديل الرد
    const currentReply = document.querySelector(`[data-review="${reviewId}"] .reply-text`)?.textContent;
    if (currentReply) {
        document.getElementById('reply_review_id').value = reviewId;
        document.getElementById('reply_message').value = currentReply;
        
        const modal = new bootstrap.Modal(document.getElementById('replyModal'));
        modal.show();
    }
}

// شكر المقيّم
let currentReviewIdForThank = 0;

function thankReviewer(reviewId) {
    currentReviewIdForThank = reviewId;
    
    const modal = new bootstrap.Modal(document.getElementById('thankModal'));
    modal.show();
}

document.getElementById('confirmThankBtn').addEventListener('click', async function() {
    try {
        const response = await fetch('/api/owner?action=thank-reviewer', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `review_id=${currentReviewIdForThank}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showNotification('نجاح', 'تم شكر المقيّم بنجاح');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('thankModal'));
            modal.hide();
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    } catch (error) {
        console.error('Error thanking reviewer:', error);
        showNotification('خطأ', 'حدث خطأ أثناء شكر المقيّم', 'error');
    }
});

// إبلاغ عن التقييم
function reportReview(reviewId) {
    if (confirm('هل تريد الإبلاغ عن هذا التقييم؟')) {
        fetch('/api/owner?action=report-review', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `review_id=${reviewId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('تم', 'تم الإبلاغ عن التقييم وسيتم مراجعته');
            } else {
                showNotification('خطأ', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error reporting review:', error);
            showNotification('خطأ', 'حدث خطأ أثناء الإبلاغ', 'error');
        });
    }
}

// تصدير التقييمات
function exportReviews() {
    const restaurantId = document.getElementById('restaurantFilter').value;
    const rating = document.getElementById('ratingFilter').value;
    const period = document.getElementById('dateFilter').value;
    
    let url = `/api/owner?action=export-reviews`;
    const params = [];
    
    if (restaurantId > 0) params.push(`restaurant_id=${restaurantId}`);
    if (rating > 0) params.push(`rating=${rating}`);
    if (period !== 'all') params.push(`period=${period}`);
    
    if (params.length > 0) {
        url += '&' + params.join('&');
    }
    
    window.open(url, '_blank');
}

// تحديث التقييمات
function refreshReviews() {
    location.reload();
}
</script>

<style>
.rating-stat-card {
    text-align: center;
    padding: 25px;
    background-color: white;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow);
    height: 100%;
}

.rating-stat-card .stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.rating-stat-card .stat-label {
    color: var(--gray-color);
    margin-bottom: 15px;
}

.rating-stat-card .stat-icon {
    font-size: 2rem;
    color: var(--primary-color);
}

.review-card {
    padding: 20px;
    background-color: white;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 15px;
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
}

.review-meta {
    text-align: right;
}

.review-body {
    color: var(--dark-color);
}

.review-images .img-thumbnail {
    width: 100%;
    height: 100px;
    object-fit: cover;
    cursor: pointer;
    transition: var(--transition);
}

.review-images .img-thumbnail:hover {
    transform: scale(1.05);
}

.review-actions .btn {
    margin-right: 5px;
}

.review-actions .btn:last-child {
    margin-right: 0;
}

.reply-section {
    border-right: 4px solid var(--secondary-color);
}

.review-stats .badge {
    font-size: 0.8rem;
}

@media (max-width: 768px) {
    .review-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .review-meta {
        text-align: left;
        width: 100%;
    }
    
    .review-footer {
        flex-direction: column;
        gap: 15px;
    }
    
    .review-actions {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .review-actions .btn {
        flex: 1;
        margin-right: 0;
        min-width: 80px;
    }
}
</style>

<?php
include 'includes/footer.php';
?>