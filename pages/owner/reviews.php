<?php
// التحقق من صلاحيات المالك
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'آخر التقييمات';
$page_scripts = ['owner-reviews.js'];

include 'includes/header.php';

// الحصول على آخر 100 تقييم لمطاعم المالك
$sql = "SELECT r.*, u.name as user_name, u.avatar as user_avatar,
               res.name as restaurant_name, res.slug as restaurant_slug,
               CASE 
                   WHEN r.rating = 5 THEN 'ممتاز'
                   WHEN r.rating = 4 THEN 'جيد جداً'
                   WHEN r.rating = 3 THEN 'جيد'
                   WHEN r.rating = 2 THEN 'مقبول'
                   WHEN r.rating = 1 THEN 'ضعيف'
               END as rating_text
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN restaurants res ON r.restaurant_id = res.id
        WHERE res.owner_id = ? AND r.status = 'active'
        ORDER BY r.created_at DESC
        LIMIT 100";

$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-comment"></i> آخر التقييمات</h2>
    
    <!-- نظرة عامة -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">تحليل التقييمات</h5>
                    <div id="reviewsAnalysisChart" style="height: 200px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">ملخص سريع</h5>
                    <div class="quick-summary">
                        <div class="summary-item">
                            <div class="summary-icon bg-primary">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="summary-details">
                                <h4><?php echo count($reviews); ?></h4>
                                <p>إجمالي التقييمات</p>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-icon bg-success">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="summary-details">
                                <h4>
                                    <?php
                                    // حساب متوسط التقييم
                                    $total_rating = 0;
                                    foreach ($reviews as $review) {
                                        $total_rating += $review['rating'];
                                    }
                                    echo count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 0;
                                    ?>
                                </h4>
                                <p>متوسط التقييم</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- فلترة التقييمات -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="filterRestaurant" class="form-label">المطعم:</label>
                    <select id="filterRestaurant" class="form-select" onchange="filterReviews()">
                        <option value="all">جميع المطاعم</option>
                        <?php
                        // الحصول على مطاعم المالك
                        $restaurants_sql = "SELECT id, name FROM restaurants WHERE owner_id = ? ORDER BY name";
                        $restaurants_stmt = $db->getConnection()->prepare($restaurants_sql);
                        $restaurants_stmt->bind_param("i", $user->getId());
                        $restaurants_stmt->execute();
                        $restaurants_result = $restaurants_stmt->get_result();
                        
                        while ($restaurant = $restaurants_result->fetch_assoc()) {
                            echo "<option value='{$restaurant['id']}'>{$restaurant['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filterRating" class="form-label">التقييم:</label>
                    <select id="filterRating" class="form-select" onchange="filterReviews()">
                        <option value="all">جميع التقييمات</option>
                        <option value="5">5 نجوم - ممتاز</option>
                        <option value="4">4 نجوم - جيد جداً</option>
                        <option value="3">3 نجوم - جيد</option>
                        <option value="2">2 نجوم - مقبول</option>
                        <option value="1">1 نجمة - ضعيف</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filterDate" class="form-label">التاريخ:</label>
                    <input type="date" id="filterDate" class="form-control" onchange="filterReviews()">
                </div>
                
                <div class="col-md-3">
                    <label for="filterKeyword" class="form-label">بحث:</label>
                    <input type="text" id="filterKeyword" class="form-control" placeholder="ابحث في التعليقات..." onkeyup="filterReviews()">
                </div>
            </div>
        </div>
    </div>
    
    <!-- قائمة التقييمات -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="card-title mb-0">قائمة التقييمات</h5>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="exportReviews()">
                        <i class="fas fa-download"></i> تصدير PDF
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="refreshPage()">
                        <i class="fas fa-sync"></i> تحديث
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="reviewsTable">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>المستخدم</th>
                            <th>المطعم</th>
                            <th>التقييم</th>
                            <th>التعليق</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reviews) > 0): ?>
                            <?php foreach ($reviews as $index => $review): ?>
                            <tr class="review-row" 
                                data-restaurant="<?php echo $review['restaurant_id']; ?>"
                                data-rating="<?php echo $review['rating']; ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($review['created_at'])); ?>"
                                data-keyword="<?php echo htmlspecialchars($review['comment'] ?? ''); ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?php if ($review['user_avatar'] && $review['user_avatar'] !== 'default.png'): ?>
                                            <img src="/uploads/images/<?php echo $review['user_avatar']; ?>" 
                                                 alt="<?php echo $review['user_name']; ?>"
                                                 class="rounded-circle" width="32" height="32">
                                            <?php else: ?>
                                            <div class="avatar-initials-sm">
                                                <?php echo mb_substr($review['user_name'], 0, 1, 'UTF-8'); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo $review['user_name']; ?></strong>
                                            <?php if ($review['is_verified']): ?>
                                            <br><small class="text-success"><i class="fas fa-check-circle"></i> تم التحقق</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="/restaurant/<?php echo $review['restaurant_slug']; ?>" target="_blank" class="text-decoration-none">
                                        <?php echo $review['restaurant_name']; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rating-badge rating-<?php echo $review['rating']; ?> me-2">
                                            <?php echo $review['rating']; ?>
                                        </div>
                                        <div class="rating-text">
                                            <?php echo $review['rating_text']; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($review['comment']): ?>
                                    <div class="comment-preview" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($review['comment']); ?>">
                                        <?php echo substr($review['comment'], 0, 50); ?>...
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted fst-italic">لا يوجد تعليق</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('Y-m-d', strtotime($review['created_at'])); ?><br>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($review['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewReviewDetails(<?php echo $review['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" onclick="replyToReview(<?php echo $review['id']; ?>)">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="shareReview(<?php echo $review['id']; ?>)">
                                            <i class="fas fa-share"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-comment-slash fa-2x text-muted mb-3"></i>
                                    <p>لا توجد تقييمات بعد</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ترقيم الصفحات -->
            <?php if (count($reviews) > 0): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <span class="text-muted">عرض <?php echo min(20, count($reviews)); ?> من <?php echo count($reviews); ?> تقييم</span>
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">السابق</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">التالي</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- مودال تفاصيل التقييم -->
<div class="modal fade" id="reviewDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل التقييم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reviewDetailsContent">
                    <!-- سيتم تحميل محتوى التقييم هنا -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال الرد على التقييم -->
<div class="modal fade" id="reviewReplyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">الرد على التقييم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reviewReplyForm">
                    <input type="hidden" id="reply_review_id">
                    
                    <div class="mb-3">
                        <label for="reply_message" class="form-label">نص الرد</label>
                        <textarea class="form-control" id="reply_message" rows="4" required></textarea>
                        <small class="text-muted">سيظهر ردك تحت التقييم</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <small>الردود الجيدة تساعد في بناء سمعة إيجابية للمطعم</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="submitReviewReply()">إرسال الرد</button>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل مخطط تحليل التقييمات
document.addEventListener('DOMContentLoaded', function() {
    loadReviewsAnalysis();
    
    // تفعيل tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// تحميل تحليل التقييمات
async function loadReviewsAnalysis() {
    try {
        const response = await fetch('/api/owner?action=get-reviews-analysis');
        const data = await response.json();
        
        if (data.status === 'success') {
            // إنشاء مخطط دائري
            const chartData = {
                labels: ['5 نجوم', '4 نجوم', '3 نجوم', '2 نجوم', '1 نجمة'],
                datasets: [{
                    data: [
                        data.data.rating_5 || 0,
                        data.data.rating_4 || 0,
                        data.data.rating_3 || 0,
                        data.data.rating_2 || 0,
                        data.data.rating_1 || 0
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#20c997',
                        '#ffc107',
                        '#fd7e14',
                        '#dc3545'
                    ]
                }]
            };
            
            const ctx = document.getElementById('reviewsAnalysisChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            rtl: true,
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: "'Cairo', 'Tajawal', sans-serif"
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading analysis:', error);
    }
}

// تصفية التقييمات
function filterReviews() {
    const restaurant = document.getElementById('filterRestaurant').value;
    const rating = document.getElementById('filterRating').value;
    const date = document.getElementById('filterDate').value;
    const keyword = document.getElementById('filterKeyword').value.toLowerCase();
    
    const rows = document.querySelectorAll('#reviewsTable .review-row');
    
    rows.forEach(row => {
        const rowRestaurant = row.getAttribute('data-restaurant');
        const rowRating = row.getAttribute('data-rating');
        const rowDate = row.getAttribute('data-date');
        const rowKeyword = row.getAttribute('data-keyword').toLowerCase();
        
        const showRestaurant = restaurant === 'all' || rowRestaurant === restaurant;
        const showRating = rating === 'all' || rowRating === rating;
        const showDate = !date || rowDate === date;
        const showKeyword = !keyword || rowKeyword.includes(keyword);
        
        if (showRestaurant && showRating && showDate && showKeyword) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// عرض تفاصيل التقييم
async function viewReviewDetails(reviewId) {
    try {
        const response = await fetch(`/api/owner?action=get-review-details&review_id=${reviewId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const review = data.data;
            const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
            
            let html = `
                <div class="review-details">
                    <div class="review-header mb-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4>${review.restaurant_name}</h4>
                                <div class="d-flex align-items-center mt-2">
                                    <div class="user-avatar me-3">
                                        ${review.user_avatar && review.user_avatar !== 'default.png' ? 
                                            `<img src="/uploads/images/${review.user_avatar}" class="rounded-circle" width="60" height="60">` :
                                            `<div class="avatar-initials-md">${review.user_name.charAt(0)}</div>`
                                        }
                                    </div>
                                    <div>
                                        <h5>${review.user_name}</h5>
                                        <div class="rating-display">
                                            <div class="stars" style="color: #ffc107; font-size: 1.5rem;">${stars}</div>
                                            <span class="ms-2">${review.rating_text}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted">${new Date(review.created_at).toLocaleDateString('ar-SA')}</div>
                                <div class="text-muted">${new Date(review.created_at).toLocaleTimeString('ar-SA')}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="review-content mb-4">
                        <h5>التعليق:</h5>
                        <div class="p-3 bg-light rounded">
                            ${review.comment ? review.comment : '<p class="text-muted fst-italic">لا يوجد تعليق</p>'}
                        </div>
                    </div>
                    
                    ${review.images && review.images.length > 0 ? `
                    <div class="review-images mb-4">
                        <h5>الصور المرفقة:</h5>
                        <div class="row g-2">
                            ${review.images.map(img => `
                                <div class="col-4">
                                    <a href="/uploads/images/${img}" target="_blank">
                                        <img src="/uploads/images/${img}" class="img-thumbnail w-100">
                                    </a>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                    
                    ${review.reply ? `
                    <div class="review-reply mb-4">
                        <h5>ردك:</h5>
                        <div class="p-3 bg-success bg-opacity-10 rounded border border-success">
                            <div class="d-flex justify-content-between mb-2">
                                <strong><i class="fas fa-reply"></i> رد المالك</strong>
                                <small class="text-muted">${new Date(review.replied_at).toLocaleDateString('ar-SA')}</small>
                            </div>
                            <p class="mb-0">${review.reply}</p>
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="review-stats">
                        <div class="row">
                            <div class="col-6">
                                <div class="stat-item">
                                    <i class="fas fa-thumbs-up"></i>
                                    <span>${review.helpful_count} إعجاب</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item">
                                    <i class="fas fa-eye"></i>
                                    <span>${review.view_count || 0} مشاهدة</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('reviewDetailsContent').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('reviewDetailsModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading review details:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل تفاصيل التقييم', 'error');
    }
}

// الرد على التقييم
function replyToReview(reviewId) {
    document.getElementById('reply_review_id').value = reviewId;
    document.getElementById('reply_message').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('reviewReplyModal'));
    modal.show();
}

// إرسال الرد على التقييم
async function submitReviewReply() {
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
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('reviewReplyModal'));
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

// مشاركة التقييم
function shareReview(reviewId) {
    // يمكن إضافة منطق المشاركة على وسائل التواصل الاجتماعي
    const url = `${window.location.origin}/review/${reviewId}`;
    if (navigator.share) {
        navigator.share({
            title: 'تقييم مطعم',
            text: 'شاهد هذا التقييم الرائع',
            url: url
        });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            showNotification('نجاح', 'تم نسخ رابط التقييم إلى الحافظة');
        });
    }
}

// تصدير التقييمات PDF
function exportReviews() {
    const restaurant = document.getElementById('filterRestaurant').value;
    const rating = document.getElementById('filterRating').value;
    const date = document.getElementById('filterDate').value;
    
    let url = `/api/owner?action=export-reviews-pdf`;
    const params = [];
    
    if (restaurant !== 'all') params.push(`restaurant_id=${restaurant}`);
    if (rating !== 'all') params.push(`rating=${rating}`);
    if (date) params.push(`date=${date}`);
    
    if (params.length > 0) {
        url += '&' + params.join('&');
    }
    
    window.open(url, '_blank');
}

// تحديث الصفحة
function refreshPage() {
    location.reload();
}
</script>

<style>
.summary-item {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.summary-item:last-child {
    margin-bottom: 0;
}

.summary-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    font-size: 1.5rem;
}

.summary-details h4 {
    margin-bottom: 5px;
    color: var(--dark-color);
    font-size: 1.8rem;
}

.summary-details p {
    margin-bottom: 0;
    color: var(--gray-color);
}

.avatar-initials-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
}

.avatar-initials-md {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    font-weight: bold;
    font-size: 1.5rem;
}

.rating-badge {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.rating-5 { background-color: #28a745; }
.rating-4 { background-color: #20c997; }
.rating-3 { background-color: #ffc107; }
.rating-2 { background-color: #fd7e14; }
.rating-1 { background-color: #dc3545; }

.comment-preview {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: help;
}

.review-details .user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.review-details .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.review-stats .stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    background-color: var(--light-color);
    border-radius: var(--border-radius);
}

.review-stats .stat-item i {
    color: var(--primary-color);
}

.table-hover tbody tr:hover {
    background-color: rgba(var(--primary-color-rgb), 0.05);
}

.table th {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--dark-color);
    font-weight: 600;
}
</style>

<?php
include 'includes/footer.php';
?>