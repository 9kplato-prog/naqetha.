<?php
// التحقق من صلاحيات العضو
if (!isset($user) || !in_array($user->getRole(), ['member', 'moderator'])) {
    header('Location: /login');
    exit;
}

$page_title = 'الإحصائيات';
$page_scripts = ['statistics.js'];

include 'includes/header.php';

// الحصول على إحصائيات العضو
$stats = $user->getStatistics();

// الحصول على سجل المعاملات
$transactions = $user->getStoreOrders(20, 0);

// الحصول على النشاط الأخير
$activity_logs = $user->getActivityLogs(10, 0);

// الحصول على بيانات التقييمات الشهرية
$sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
               COUNT(*) as reviews_count,
               AVG(rating) as avg_rating
        FROM reviews 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month";

$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();

$monthly_reviews = [];
$monthly_labels = [];
$monthly_counts = [];
$monthly_ratings = [];

while ($row = $result->fetch_assoc()) {
    $monthly_reviews[] = $row;
    $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_counts[] = $row['reviews_count'];
    $monthly_ratings[] = round($row['avg_rating'], 1);
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-chart-line"></i> الإحصائيات</h2>
    
    <!-- نظرة عامة -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <h3><?php echo formatPoints($user->getPoints()); ?></h3>
                    <p>النقاط الحالية</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #0f8c66);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['completed_tasks']; ?></h3>
                    <p>مهام مكتملة</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #0c7bb3);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_reviews']; ?></h3>
                    <p>تقييمات</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPoints($stats['points_used']); ?></h3>
                    <p>نقاط مستخدمة</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- الرسوم البيانية -->
        <div class="col-lg-8">
            <!-- مخطط التقييمات الشهرية -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">نشاط التقييمات خلال 6 أشهر</h5>
                    <div class="chart-container">
                        <canvas id="reviewsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- النقاط المكتسبة والمستخدمة -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">النقاط المكتسبة والمستخدمة</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="points-earned">
                                <h4><?php echo formatPoints($user->getPoints() + $stats['points_used']); ?></h4>
                                <p>إجمالي النقاط المكتسبة</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="points-used">
                                <h4><?php echo formatPoints($stats['points_used']); ?></h4>
                                <p>إجمالي النقاط المستخدمة</p>
                            </div>
                        </div>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <?php
                        $total = $user->getPoints() + $stats['points_used'];
                        $used_percentage = $total > 0 ? round(($stats['points_used'] / $total) * 100) : 0;
                        $current_percentage = $total > 0 ? round(($user->getPoints() / $total) * 100) : 0;
                        ?>
                        <div class="progress-bar bg-success" style="width: <?php echo $used_percentage; ?>%" 
                             data-bs-toggle="tooltip" title="نقاط مستخدمة: <?php echo formatPoints($stats['points_used']); ?>">
                            <?php echo $used_percentage; ?>%
                        </div>
                        <div class="progress-bar bg-primary" style="width: <?php echo $current_percentage; ?>%"
                             data-bs-toggle="tooltip" title="نقاط متبقية: <?php echo formatPoints($user->getPoints()); ?>">
                            <?php echo $current_percentage; ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <!-- النشاط الأخير -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">النشاط الأخير</h5>
                    <div class="activity-list">
                        <?php if (count($activity_logs) > 0): ?>
                            <?php foreach ($activity_logs as $log): ?>
                            <div class="activity-item mb-3">
                                <div class="activity-icon">
                                    <?php
                                    $icons = [
                                        'login' => 'fas fa-sign-in-alt',
                                        'register' => 'fas fa-user-plus',
                                        'profile_update' => 'fas fa-user-edit',
                                        'avatar_update' => 'fas fa-image',
                                        'password_change' => 'fas fa-key',
                                        'earn_points' => 'fas fa-plus-circle text-success',
                                        'redeem_points' => 'fas fa-minus-circle text-warning',
                                        'review_added' => 'fas fa-star text-warning',
                                        'task_completed' => 'fas fa-check-circle text-success'
                                    ];
                                    ?>
                                    <i class="<?php echo $icons[$log['action']] ?? 'fas fa-history'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="mb-1"><?php echo $log['description']; ?></p>
                                    <small class="text-muted"><?php echo formatRelativeTime($log['created_at']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                <p>لا يوجد نشاط مؤخراً</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- آخر المعاملات -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">آخر المعاملات</h5>
                    <div class="transactions-list">
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo $transaction['product_name']; ?></h6>
                                        <small class="text-muted">#<?php echo $transaction['order_number']; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo $transaction['status'] == 'completed' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $transaction['status']; ?>
                                        </span>
                                        <div class="points-amount">
                                            <i class="fas fa-coins text-warning"></i>
                                            <span><?php echo $transaction['points_paid']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo formatRelativeTime($transaction['created_at']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-exchange-alt fa-2x text-muted mb-2"></i>
                                <p>لا توجد معاملات</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تهيئة الرسوم البيانية
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    
    // تفعيل tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function initCharts() {
    // مخطط التقييمات الشهرية
    const reviewsCtx = document.getElementById('reviewsChart').getContext('2d');
    new Chart(reviewsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [
                {
                    label: 'عدد التقييمات',
                    data: <?php echo json_encode($monthly_counts); ?>,
                    borderColor: 'rgb(255, 107, 53)',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'متوسط التقييم',
                    data: <?php echo json_encode($monthly_ratings); ?>,
                    borderColor: 'rgb(42, 157, 143)',
                    backgroundColor: 'rgba(42, 157, 143, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            stacked: false,
            plugins: {
                legend: {
                    rtl: true,
                    labels: {
                        font: {
                            family: "'Cairo', 'Tajawal', sans-serif"
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'عدد التقييمات'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'متوسط التقييم'
                    },
                    min: 1,
                    max: 5,
                    ticks: {
                        stepSize: 0.5
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            }
        }
    });
}
</script>

<style>
.stat-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: var(--card-bg);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    height: 100%;
}

.stat-card .stat-icon {
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

.stat-card .stat-info h3 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.stat-card .stat-info p {
    margin-bottom: 0;
    color: var(--gray-color);
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.points-earned, .points-used {
    text-align: center;
    padding: 20px;
    background-color: var(--light-color);
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.points-earned h4 {
    color: var(--primary-color);
    font-weight: bold;
}

.points-used h4 {
    color: var(--success-color);
    font-weight: bold;
}

.points-earned p, .points-used p {
    margin-bottom: 0;
    color: var(--gray-color);
}

.activity-item {
    display: flex;
    align-items: flex-start;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    background-color: var(--light-color);
    color: var(--primary-color);
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.transaction-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.points-amount {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
    margin-top: 5px;
}

.points-amount span {
    font-weight: bold;
    color: var(--warning-color);
}
</style>

<?php
include 'includes/footer.php';
?>