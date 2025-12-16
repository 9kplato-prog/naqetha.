<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'إدارة التعليقات';
$page_scripts = ['admin-comments.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// معالجة الطلبات
$action = $_GET['action'] ?? '';
$review_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update':
            $status = $_POST['status'] ?? '';
            $reply = $_POST['reply'] ?? '';
            
            if ($status) {
                $admin->updateReviewStatus($review_id, $status);
            }
            
            if ($reply !== '') {
                $sql = "UPDATE reviews SET reply = ?, replied_at = NOW() WHERE id = ?";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->bind_param("si", $reply, $review_id);
                $stmt->execute();
            }
            
            $success = 'تم تحديث التقييم بنجاح';
            break;
            
        case 'delete':
            $admin->updateReviewStatus($review_id, 'deleted');
            $success = 'تم حذف التقييم بنجاح';
            break;
    }
}

// الحصول على التقييمات
$filters = [
    'status' => $_GET['status'] ?? '',
    'rating' => $_GET['rating'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$reviews = $admin->getReviews($filters, 20, 0);
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-comment-slash"></i> إدارة التعليقات والتقييمات</h2>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <!-- فلترة التقييمات -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="active" <?php echo ($filters['status'] === 'active') ? 'selected' : ''; ?>>نشط</option>
                        <option value="hidden" <?php echo ($filters['status'] === 'hidden') ? 'selected' : ''; ?>>مخفي</option>
                        <option value="reported" <?php echo ($filters['status'] === 'reported') ? 'selected' : ''; ?>>مبلغ عنه</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">التقييم</label>
                    <select name="rating" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع التقييمات</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($filters['rating'] == $i) ? 'selected' : ''; ?>>
                            <?php echo $i; ?> نجوم
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">بحث</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ابحث في التعليقات..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- قائمة التقييمات -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التقييم</th>
                            <th>المستخدم</th>
                            <th>المطعم</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reviews) > 0): ?>
                            <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td>
                                    <div class="rating-stars small mb-1">
                                        <?php echo getRatingStars($review['rating']); ?>
                                    </div>
                                    <div class="comment-preview">
                                        <?php if ($review['comment']): ?>
                                        <p class="mb-0"><?php echo substr($review['comment'], 0, 80); ?>...</p>
                                        <?php else: ?>
                                        <p class="mb-0 text-muted">لا يوجد تعليق</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $review['user_name']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $review['user_email']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $review['restaurant_name']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $review['restaurant_city']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_badge = [
                                        'active' => 'success',
                                        'hidden' => 'warning',
                                        'reported' => 'danger',
                                        'deleted' => 'secondary'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_badge[$review['status']] ?? 'secondary'; ?>">
                                        <?php
                                        $status_names = [
                                            'active' => 'نشط',
                                            'hidden' => 'مخفي',
                                            'reported' => 'مبلغ عنه',
                                            'deleted' => 'محذوف'
                                        ];
                                        echo $status_names[$review['status']] ?? $review['status'];
                                        ?>
                                    </span>
                                    <?php if ($review['reported_count'] > 0): ?>
                                    <br>
                                    <small class="text-danger">تم الإبلاغ عنه <?php echo $review['reported_count']; ?> مرات</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('Y-m-d', strtotime($review['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-info" onclick="viewReview(<?php echo $review['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-warning" onclick="editReview(<?php echo $review['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteReview(<?php echo $review['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-comments fa-2x text-muted mb-3"></i>
                                    <p>لا توجد تقييمات</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- مودال عرض التقييم -->
<div class="modal fade" id="viewReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل التقييم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reviewDetails">
                    <!-- سيتم تحميل التفاصيل هنا ديناميكياً -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال تعديل التقييم -->
<div class="modal fade" id="editReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعديل التقييم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editReviewForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_review_id" name="id">
                    
                    <!-- سيتم تحميل النموذج هنا ديناميكياً -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="editReviewForm" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </div>
    </div>
</div>

<script>
// عرض تفاصيل التقييم
async function viewReview(reviewId) {
    try {
        const response = await fetch(`/api/admin?action=get-review&id=${reviewId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const review = data.data;
            const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
            
            let html = `
                <div class="review-details">
                    <div class="review-header mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>المستخدم:</h6>
                                <p>${review.user_name} (${review.user_email})</p>
                            </div>
                            <div class="col-md-6">
                                <h6>المطعم:</h6>
                                <p>${review.restaurant_name} - ${review.restaurant_city}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="review-content mb-4">
                        <h6>التقييم:</h6>
                        <div class="rating-stars large mb-2" style="color: #ffc107;">
                            ${stars}
                        </div>
                        
                        <h6>التعليق:</h6>
                        <div class="comment-box p-3 border rounded">
                            ${review.comment || '<p class="text-muted">لا يوجد تعليق</p>'}
                        </div>
                        
                        ${review.images ? `
                        <div class="mt-3">
                            <h6>الصور:</h6>
                            <div class="images-grid">
                                ${JSON.parse(review.images).map(img => 
                                    `<img src="/uploads/images/${img}" class="img-thumbnail me-2" style="max-height: 100px;">`
                                ).join('')}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${review.reply ? `
                    <div class="review-reply mb-4">
                        <h6>رد المطعم:</h6>
                        <div class="reply-box p-3 bg-light rounded">
                            ${review.reply}
                        </div>
                        <small class="text-muted">تم الرد في: ${review.replied_at}</small>
                    </div>
                    ` : ''}
                    
                    <div class="review-meta">
                        <h6>المعلومات:</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <small><strong>الحالة:</strong> ${review.status}</small>
                            </div>
                            <div class="col-md-4">
                                <small><strong>تم التحقق:</strong> ${review.is_verified ? 'نعم' : 'لا'}</small>
                            </div>
                            <div class="col-md-4">
                                <small><strong>التاريخ:</strong> ${review.created_at}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('reviewDetails').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('viewReviewModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading review:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل بيانات التقييم', 'error');
    }
}

// تعديل التقييم
async function editReview(reviewId) {
    try {
        const response = await fetch(`/api/admin?action=get-review&id=${reviewId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const review = data.data;
            
            document.getElementById('edit_review_id').value = review.id;
            
            let html = `
                <div class="mb-3">
                    <label class="form-label">الحالة</label>
                    <select class="form-control" name="status">
                        <option value="active" ${review.status === 'active' ? 'selected' : ''}>نشط</option>
                        <option value="hidden" ${review.status === 'hidden' ? 'selected' : ''}>مخفي</option>
                        <option value="reported" ${review.status === 'reported' ? 'selected' : ''}>مبلغ عنه</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">رد المطعم (اختياري)</label>
                    <textarea class="form-control" name="reply" rows="4">${review.reply || ''}</textarea>
                    <small class="text-muted">سيظهر هذا الرد كرد من المطعم على التقييم</small>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    يمكنك أيضاً حذف التقييم كاملاً باستخدام زر الحذف
                </div>
            `;
            
            document.querySelector('#editReviewForm .modal-body').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('editReviewModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading review:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل بيانات التقييم', 'error');
    }
}

// حذف التقييم
function deleteReview(reviewId) {
    if (confirm('هل أنت متأكد من حذف هذا التقييم؟')) {
        window.location.href = `/admin/comments?action=delete&id=${reviewId}`;
    }
}
</script>

<style>
.comment-preview {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rating-stars.large {
    font-size: 1.5rem;
}

.comment-box, .reply-box {
    background-color: var(--light-color);
    border-radius: var(--border-radius);
}

.images-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.images-grid img {
    max-width: 100px;
    max-height: 100px;
    object-fit: cover;
}

.review-details .row {
    margin-bottom: 10px;
}

.review-details h6 {
    color: var(--dark-color);
    margin-bottom: 5px;
}

.review-details p {
    color: var(--gray-color);
    margin-bottom: 0;
}
</style>

<?php
include 'includes/footer.php';
?>