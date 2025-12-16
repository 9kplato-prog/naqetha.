<?php
// التحقق من صلاحيات صاحب المطعم
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'التقارير والإحصائيات';
$page_scripts = ['owner-reports.js'];

include 'includes/header.php';

// الحصول على مطعم المستخدم
$sql = "SELECT id, name FROM restaurants WHERE owner_id = ? LIMIT 1";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
$restaurant = $result->fetch_assoc();

if (!$restaurant) {
    echo '<div class="container"><div class="alert alert-danger">لم يتم العثور على مطعم مرتبط بحسابك</div></div>';
    include 'includes/footer.php';
    exit;
}

$restaurant_id = $restaurant['id'];

// الفترة الزمنية الافتراضية (آخر 30 يوم)
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = date('Y-m-d');

// معالجة طلب التقرير
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? $start_date;
    $end_date = $_POST['end_date'] ?? $end_date;
    $report_type = $_POST['report_type'] ?? 'overview';
}

// الحصول على إحصائيات عامة
$overview_stats = getRestaurantOverview($db, $restaurant_id, $start_date, $end_date);

// الحصول على توزيع التقييمات
$rating_stats = getRatingDistribution($db, $restaurant_id, $start_date, $end_date);

// الحصول على إحصائيات المهام
$task_stats = getTaskStatistics($db, $restaurant_id, $start_date, $end_date);

// الحصول على التقييمات الأخيرة
$recent_reviews = getRecentReviews($db, $restaurant_id, 10);

function getRestaurantOverview($db, $restaurant_id, $start_date, $end_date) {
    $stats = [];
    
    // عدد الزيارات (التقييمات)
    $sql = "SELECT COUNT(*) as visits FROM reviews 
            WHERE restaurant_id = ? AND created_at BETWEEN ? AND ? AND status = 'active'";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['visits'] = $result->fetch_assoc()['visits'];
    
    // متوسط التقييم
    $sql = "SELECT AVG(rating) as avg_rating FROM reviews 
            WHERE restaurant_id = ? AND created_at BETWEEN ? AND ? AND status = 'active'";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['avg_rating'] = round($result->fetch_assoc()['avg_rating'], 1);
    
    // عدد المهام المكتملة
    $sql = "SELECT COUNT(DISTINCT ut.id) as completed_tasks 
            FROM user_tasks ut 
            JOIN tasks t ON ut.task_id = t.id 
            WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at BETWEEN ? AND ?";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['completed_tasks'] = $result->fetch_assoc()['completed_tasks'];
    
    // الإيرادات المحتملة (بناءً على المهام)
    $sql = "SELECT SUM(t.discount_percentage) as potential_revenue 
            FROM user_tasks ut 
            JOIN tasks t ON ut.task_id = t.id 
            WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at BETWEEN ? AND ?";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['potential_revenue'] = $result->fetch_assoc()['potential_revenue'] ?? 0;
    
    // عدد المهام النشطة
    $sql = "SELECT COUNT(*) as active_tasks FROM tasks 
            WHERE restaurant_id = ? AND status = 'active' AND created_at BETWEEN ? AND ?";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['active_tasks'] = $result->fetch_assoc()['active_tasks'];
    
    return $stats;
}

function getRatingDistribution($db, $restaurant_id, $start_date, $end_date) {
    $sql = "SELECT rating, COUNT(*) as count FROM reviews 
            WHERE restaurant_id = ? AND created_at BETWEEN ? AND ? AND status = 'active' 
            GROUP BY rating ORDER BY rating DESC";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $distribution = [
        5 => ['count' => 0, 'percentage' => 0],
        4 => ['count' => 0, 'percentage' => 0],
        3 => ['count' => 0, 'percentage' => 0],
        2 => ['count' => 0, 'percentage' => 0],
        1 => ['count' => 0, 'percentage' => 0]
    ];
    
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $distribution[$row['rating']]['count'] = $row['count'];
        $total += $row['count'];
    }
    
    // حساب النسب المئوية
    foreach ($distribution as $rating => $data) {
        $distribution[$rating]['percentage'] = $total > 0 ? round(($data['count'] / $total) * 100, 1) : 0;
    }
    
    return [
        'distribution' => $distribution,
        'total' => $total
    ];
}

function getTaskStatistics($db, $restaurant_id, $start_date, $end_date) {
    $stats = [];
    
    // عدد المهام المنشورة
    $sql = "SELECT COUNT(*) as total_tasks FROM tasks 
            WHERE restaurant_id = ? AND created_at BETWEEN ? AND ?";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_tasks'] = $result->fetch_assoc()['total_tasks'];
    
    // معدل إكمال المهام
    $sql = "SELECT 
            (SELECT COUNT(*) FROM tasks WHERE restaurant_id = ? AND created_at BETWEEN ? AND ?) as total,
            (SELECT COUNT(DISTINCT ut.id) FROM user_tasks ut JOIN tasks t ON ut.task_id = t.id 
             WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at BETWEEN ? AND ?) as completed";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("ississ", $restaurant_id, $start_date, $end_date, $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['completion_rate'] = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100, 1) : 0;
    
    // النقاط الموزعة
    $sql = "SELECT SUM(t.points_reward) as total_points 
            FROM user_tasks ut 
            JOIN tasks t ON ut.task_id = t.id 
            WHERE t.restaurant_id = ? AND ut.status = 'completed' AND ut.completed_at BETWEEN ? AND ?";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("iss", $restaurant_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_points'] = $result->fetch_assoc()['total_points'] ?? 0;
    
    return $stats;
}

function getRecentReviews($db, $restaurant_id, $limit = 10) {
    $sql = "SELECT r.*, u.name as user_name, u.avatar as user_avatar 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.restaurant_id = ? AND r.status = 'active' 
            ORDER BY r.created_at DESC 
            LIMIT ?";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bind_param("ii", $restaurant_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    
    return $reviews;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</h2>
    
    <div class="row mb-4">
        <div class="col-lg-12">
            <!-- فلترة التقرير -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">فلترة التقرير</h5>
                    
                    <form method="POST" id="reportFilterForm" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">نوع التقرير</label>
                            <select class="form-control" id="report_type" name="report_type">
                                <option value="overview" <?php echo ($report_type ?? 'overview') === 'overview' ? 'selected' : ''; ?>>نظرة عامة</option>
                                <option value="ratings" <?php echo ($report_type ?? 'overview') === 'ratings' ? 'selected' : ''; ?>>توزيع التقييمات</option>
                                <option value="tasks" <?php echo ($report_type ?? 'overview') === 'tasks' ? 'selected' : ''; ?>>إحصائيات المهام</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> تطبيق الفلتر
                            </button>
                        </div>
                    </form>
                    
                    <!-- فترات زمنية سريعة -->
                    <div class="quick-periods mt-3">
                        <span>فترات سريعة:</span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="setPeriod('7days')">آخر 7 أيام</button>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="setPeriod('30days')">آخر 30 يوم</button>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="setPeriod('90days')">آخر 90 يوم</button>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="setPeriod('year')">السنة الحالية</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الإحصائيات الرئيسية -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="report-card">
                <div class="report-icon bg-primary">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="report-info">
                    <h3><?php echo number_format($overview_stats['visits']); ?></h3>
                    <p>عدد الزيارات</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="report-card">
                <div class="report-icon bg-success">
                    <i class="fas fa-star"></i>
                </div>
                <div class="report-info">
                    <h3><?php echo $overview_stats['avg_rating']; ?></h3>
                    <p>متوسط التقييم</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="report-card">
                <div class="report-icon bg-info">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="report-info">
                    <h3><?php echo number_format($overview_stats['completed_tasks']); ?></h3>
                    <p>مهام مكتملة</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="report-card">
                <div class="report-icon bg-warning">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="report-info">
                    <h3><?php echo number_format($overview_stats['potential_revenue']); ?>%</h3>
                    <p>إيرادات محتملة</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- توزيع التقييمات -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">توزيع التقييمات</h5>
                    
                    <div class="rating-distribution">
                        <?php for ($i = 5; $i >= 1; $i--): 
                            $percentage = $rating_stats['distribution'][$i]['percentage'];
                        ?>
                        <div class="rating-bar mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>
                                    <?php echo str_repeat('★', $i) . str_repeat('☆', 5 - $i); ?>
                                    <small class="ms-2">(<?php echo $rating_stats['distribution'][$i]['count']; ?>)</small>
                                </span>
                                <span><?php echo $percentage; ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                        
                        <div class="total-reviews text-center mt-4">
                            <h5>إجمالي التقييمات: <?php echo $rating_stats['total']; ?></h5>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- إحصائيات المهام -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">إحصائيات المهام</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="task-stat text-center">
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <h3><?php echo $task_stats['total_tasks']; ?></h3>
                                <p>مهام منشورة</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="task-stat text-center">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3><?php echo $task_stats['completion_rate']; ?>%</h3>
                                <p>معدل الإكمال</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="task-stat text-center">
                                <div class="stat-icon bg-warning">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <h3><?php echo number_format($task_stats['total_points']); ?></h3>
                                <p>نقاط موزعة</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="task-stat text-center">
                                <div class="stat-icon bg-info">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <h3><?php echo $overview_stats['active_tasks']; ?></h3>
                                <p>مهام نشطة</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- التقييمات الأخيرة -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">آخر التقييمات</h5>
                        <span class="badge bg-primary"><?php echo count($recent_reviews); ?> تقييم</span>
                    </div>
                    
                    <?php if (count($recent_reviews) > 0): ?>
                        <div class="reviews-list">
                            <?php foreach ($recent_reviews as $review): ?>
                            <div class="review-item p-3 border rounded mb-3">
                                <div class="d-flex align-items-start mb-2">
                                    <div class="user-avatar me-3">
                                        <?php if ($review['user_avatar'] && $review['user_avatar'] !== 'default.png'): ?>
                                        <img src="/uploads/images/<?php echo $review['user_avatar']; ?>" 
                                             class="img-fluid rounded-circle" 
                                             alt="<?php echo $review['user_name']; ?>">
                                        <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?php echo mb_substr($review['user_name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?php echo $review['user_name']; ?></strong>
                                                <div class="rating-stars small mt-1">
                                                    <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo formatRelativeTime($review['created_at']); ?></small>
                                        </div>
                                        
                                        <?php if ($review['comment']): ?>
                                        <p class="mt-2 mb-0"><?php echo nl2br($review['comment']); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($review['reply']): ?>
                                        <div class="review-reply mt-3 p-2 bg-light rounded">
                                            <strong><i class="fas fa-reply"></i> ردك:</strong>
                                            <p class="mb-0 mt-1"><?php echo nl2br($review['reply']); ?></p>
                                        </div>
                                        <?php else: ?>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary" onclick="replyToReview(<?php echo $review['id']; ?>)">
                                                <i class="fas fa-reply"></i> رد على التقييم
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-star fa-2x text-muted mb-3"></i>
                            <p>لا توجد تقييمات في الفترة المحددة</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- تصدير التقرير -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">تصدير التقرير</h5>
                    
                    <div class="export-options">
                        <div class="mb-3">
                            <label class="form-label">نوع الملف</label>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <button class="btn btn-outline-primary w-100" onclick="exportReport('pdf')">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </button>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button class="btn btn-outline-success w-100" onclick="exportReport('excel')">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </button>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button class="btn btn-outline-secondary w-100" onclick="exportReport('print')">
                                        <i class="fas fa-print"></i> طباعة
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الفترة</label>
                            <select class="form-control" id="export_period">
                                <option value="current">الفترة الحالية</option>
                                <option value="last_week">الأسبوع الماضي</option>
                                <option value="last_month">الشهر الماضي</option>
                                <option value="last_quarter">الربع الأخير</option>
                                <option value="last_year">السنة الماضية</option>
                            </select>
                        </div>
                        
                        <div>
                            <button class="btn btn-primary w-100" onclick="generateExport()">
                                <i class="fas fa-download"></i> إنشاء وتحميل التقرير
                            </button>
                        </div>
                    </div>
                </div>
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
                        <label for="reply_message" class="form-label">الرد <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reply_message" rows="4" required 
                                  placeholder="اكتب ردك على التقييم هنا..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <small>الرد سيظهر للمستخدم وجميع زوار صفحة المطعم</small>
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

<script>
// تعيين فترات زمنية سريعة
function setPeriod(period) {
    const today = new Date();
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    let start = new Date();
    
    switch (period) {
        case '7days':
            start.setDate(today.getDate() - 7);
            break;
        case '30days':
            start.setDate(today.getDate() - 30);
            break;
        case '90days':
            start.setDate(today.getDate() - 90);
            break;
        case 'year':
            start.setFullYear(today.getFullYear(), 0, 1);
            break;
    }
    
    startDate.value = start.toISOString().split('T')[0];
    endDate.value = today.toISOString().split('T')[0];
    
    // إرسال النموذج تلقائياً
    document.getElementById('reportFilterForm').submit();
}

// الرد على التقييم
function replyToReview(reviewId) {
    document.getElementById('reply_review_id').value = reviewId;
    
    const modal = new bootstrap.Modal(document.getElementById('replyModal'));
    modal.show();
}

// إرسال الرد
function submitReply() {
    const reviewId = document.getElementById('reply_review_id').value;
    const replyMessage = document.getElementById('reply_message').value;
    
    if (!replyMessage.trim()) {
        showNotification('خطأ', 'يرجى كتابة الرد', 'error');
        return;
    }
    
    fetch('/api/owner?action=reply-to-review', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `review_id=${reviewId}&reply=${encodeURIComponent(replyMessage)}`
    })
    .then(response => response.json())
    .then(data => {
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
    })
    .catch(error => {
        console.error('Error submitting reply:', error);
        showNotification('خطأ', 'حدث خطأ أثناء إرسال الرد', 'error');
    });
}

// تصدير التقرير
function exportReport(format) {
    const period = document.getElementById('export_period').value;
    
    let url = `/api/owner?action=export-report&format=${format}&period=${period}`;
    
    if (format === 'print') {
        window.open(url, '_blank');
    } else {
        // تنزيل الملف
        const a = document.createElement('a');
        a.href = url;
        a.download = `تقرير_مطعم_${new Date().toISOString().split('T')[0]}.${format}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

// إنشاء وتحميل التقرير
function generateExport() {
    const format = document.querySelector('input[name="export_format"]:checked')?.value || 'pdf';
    exportReport(format);
}
</script>

<style>
.report-card {
    display: flex;
    align-items: center;
    padding: 20px;
    background-color: var(--card-bg);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.report-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-color);
}

.report-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    color: white;
    font-size: 1.5rem;
}

.report-info h3 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.report-info p {
    margin: 0;
    color: var(--gray-color);
}

.rating-bar .progress {
    background-color: var(--light-color);
    border-radius: 5px;
}

.total-reviews {
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.total-reviews h5 {
    color: var(--primary-color);
}

.task-stat {
    padding: 20px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--card-bg);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    color: white;
    font-size: 1.2rem;
}

.task-stat h3 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.task-stat p {
    margin: 0;
    color: var(--gray-color);
    font-size: 0.9rem;
}

.user-avatar {
    width: 50px;
    height: 50px;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.avatar-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.review-reply {
    background-color: rgba(var(--primary-color-rgb), 0.05);
    border-right: 3px solid var(--primary-color);
}

.quick-periods {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.quick-periods span {
    font-weight: 600;
    color: var(--dark-color);
}

.export-options .btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
</style>

<?php
include 'includes/footer.php';
?>