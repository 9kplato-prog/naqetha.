<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'التقارير والإحصائيات';
$page_scripts = ['admin-reports.js', 'chart.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// تحديد الفترة
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['type'] ?? 'overview';

// الحصول على الإحصائيات العامة
$stats = $admin->getDashboardStats();

// الحصول على تقارير حسب النوع
$reports = $admin->getReports($report_type, $start_date, $end_date);
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</h2>
    
    <!-- فلترة التقارير -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">نوع التقرير</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="overview" <?php echo ($report_type === 'overview') ? 'selected' : ''; ?>>نظرة عامة</option>
                        <option value="users" <?php echo ($report_type === 'users') ? 'selected' : ''; ?>>المستخدمون</option>
                        <option value="restaurants" <?php echo ($report_type === 'restaurants') ? 'selected' : ''; ?>>المطاعم</option>
                        <option value="reviews" <?php echo ($report_type === 'reviews') ? 'selected' : ''; ?>>التقييمات</option>
                        <option value="tasks" <?php echo ($report_type === 'tasks') ? 'selected' : ''; ?>>المهام</option>
                        <option value="transactions" <?php echo ($report_type === 'transactions') ? 'selected' : ''; ?>>المعاملات</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">تصدير</label>
                    <div class="btn-group w-100">
                        <button type="button" class="btn btn-primary" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-info" onclick="printReport()">
                            <i class="fas fa-print"></i> طباعة
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- التقارير حسب النوع -->
    <?php if ($report_type === 'overview'): ?>
    <!-- نظرة عامة -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>إجمالي المستخدمين</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success-color), #0f8c66);">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_restaurants']); ?></h3>
                    <p>إجمالي المطاعم</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info-color), #0c7bb3);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_reviews']); ?></h3>
                    <p>إجمالي التقييمات</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning-color), #d97706);">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_points_earned']); ?></h3>
                    <p>إجمالي النقاط</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- المخططات -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">نمو المستخدمين</h5>
                    <canvas id="usersChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">التقييمات</h5>
                    <canvas id="reviewsChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">المهام</h5>
                    <canvas id="tasksChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">المعاملات</h5>
                    <canvas id="transactionsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($report_type === 'users'): ?>
    <!-- تقرير المستخدمين -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4">تقرير المستخدمين</h5>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المستخدمون الجدد</th>
                            <th>إجمالي المستخدمين</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reports) > 0): ?>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['date']; ?></td>
                                <td><?php echo $report['count']; ?></td>
                                <td><?php echo $report['cumulative'] ?? ''; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                    <p>لا توجد بيانات</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($report_type === 'restaurants'): ?>
    <!-- تقرير المطاعم -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4">تقرير المطاعم</h5>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المطاعم الجديدة</th>
                            <th>إجمالي المطاعم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reports) > 0): ?>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['date']; ?></td>
                                <td><?php echo $report['count']; ?></td>
                                <td><?php echo $report['cumulative'] ?? ''; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <i class="fas fa-utensils fa-2x text-muted mb-3"></i>
                                    <p>لا توجد بيانات</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($report_type === 'reviews'): ?>
    <!-- تقرير التقييمات -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4">تقرير التقييمات</h5>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>عدد التقييمات</th>
                            <th>متوسط التقييم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reports) > 0): ?>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['date']; ?></td>
                                <td><?php echo $report['count']; ?></td>
                                <td><?php echo number_format($report['avg_rating'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <i class="fas fa-star fa-2x text-muted mb-3"></i>
                                    <p>لا توجد بيانات</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($report_type === 'tasks'): ?>
    <!-- تقرير المهام -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4">تقرير المهام</h5>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المهام الجديدة</th>
                            <th>إجمالي النقاط</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reports) > 0): ?>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['date']; ?></td>
                                <td><?php echo $report['count']; ?></td>
                                <td><?php echo $report['total_points'] ?? 0; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <i class="fas fa-tasks fa-2x text-muted mb-3"></i>
                                    <p>لا توجد بيانات</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($report_type === 'transactions'): ?>
    <!-- تقرير المعاملات -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4">تقرير المعاملات</h5>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>نوع المعاملة</th>
                            <th>عدد المعاملات</th>
                            <th>إجمالي المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reports) > 0): ?>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <?php
                                    $type_names = [
                                        'earn' => 'كسب نقاط',
                                        'redeem' => 'استبدال نقاط',
                                        'withdraw' => 'سحب نقاط',
                                        'transfer' => 'تحويل نقاط',
                                        'bonus' => 'مكافآت'
                                    ];
                                    echo $type_names[$report['type']] ?? $report['type'];
                                    ?>
                                </td>
                                <td><?php echo $report['count']; ?></td>
                                <td><?php echo number_format($report['total_amount'] ?? 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <i class="fas fa-exchange-alt fa-2x text-muted mb-3"></i>
                                    <p>لا توجد بيانات</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script>
// تحميل البيانات عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('usersChart')) {
        loadCharts();
    }
});

// تحميل المخططات
async function loadCharts() {
    const startDate = '<?php echo $start_date; ?>';
    const endDate = '<?php echo $end_date; ?>';
    
    try {
        const response = await fetch(`/api/admin?action=get-chart-data&start_date=${startDate}&end_date=${endDate}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            // مخطط المستخدمين
            const usersCtx = document.getElementById('usersChart').getContext('2d');
            new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: data.data.users.labels,
                    datasets: [{
                        label: 'المستخدمون الجدد',
                        data: data.data.users.data,
                        borderColor: 'rgb(255, 107, 53)',
                        backgroundColor: 'rgba(255, 107, 53, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: getChartOptions('المستخدمون')
            });
            
            // مخطط التقييمات
            const reviewsCtx = document.getElementById('reviewsChart').getContext('2d');
            new Chart(reviewsCtx, {
                type: 'bar',
                data: {
                    labels: data.data.reviews.labels,
                    datasets: [{
                        label: 'التقييمات الجديدة',
                        data: data.data.reviews.data,
                        backgroundColor: 'rgba(42, 157, 143, 0.7)',
                        borderColor: 'rgb(42, 157, 143)',
                        borderWidth: 1
                    }]
                },
                options: getChartOptions('التقييمات')
            });
            
            // مخطط المهام
            const tasksCtx = document.getElementById('tasksChart').getContext('2d');
            new Chart(tasksCtx, {
                type: 'bar',
                data: {
                    labels: data.data.tasks.labels,
                    datasets: [{
                        label: 'المهام الجديدة',
                        data: data.data.tasks.data,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: 'rgb(37, 99, 235)',
                        borderWidth: 1
                    }]
                },
                options: getChartOptions('المهام')
            });
            
            // مخطط المعاملات
            const transactionsCtx = document.getElementById('transactionsChart').getContext('2d');
            new Chart(transactionsCtx, {
                type: 'pie',
                data: {
                    labels: ['كسب نقاط', 'استبدال نقاط', 'مكافآت'],
                    datasets: [{
                        data: data.data.transactions.data,
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.7)',
                            'rgba(239, 68, 68, 0.7)',
                            'rgba(245, 158, 11, 0.7)'
                        ],
                        borderColor: [
                            'rgb(34, 197, 94)',
                            'rgb(239, 68, 68)',
                            'rgb(245, 158, 11)'
                        ],
                        borderWidth: 1
                    }]
                },
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
        console.error('Error loading charts:', error);
    }
}

// إعدادات المخططات
function getChartOptions(title) {
    return {
        responsive: true,
        plugins: {
            legend: {
                rtl: true,
                labels: {
                    font: {
                        family: "'Cairo', 'Tajawal', sans-serif"
                    }
                }
            },
            title: {
                display: true,
                text: title,
                font: {
                    family: "'Cairo', 'Tajawal', sans-serif",
                    size: 16
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    };
}

// تصدير التقرير
function exportReport(format) {
    const reportType = '<?php echo $report_type; ?>';
    const startDate = '<?php echo $start_date; ?>';
    const endDate = '<?php echo $end_date; ?>';
    
    let url = `/api/admin?action=export-report&type=${reportType}&start_date=${startDate}&end_date=${endDate}&format=${format}`;
    
    if (format === 'pdf') {
        window.open(url, '_blank');
    } else if (format === 'excel') {
        // إنشاء جدول Excel
        const table = document.querySelector('table');
        const html = table.outerHTML;
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `report_${reportType}_${startDate}_${endDate}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
    
    showNotification('جاري التصدير', 'يتم تحضير الملف للتحميل', 'info');
}

// طباعة التقرير
function printReport() {
    window.print();
}
</script>

<style>
.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 20px;
    box-shadow: var(--shadow);
    text-align: center;
    height: 100%;
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 15px;
}

.stat-card .stat-info h3 {
    color: var(--dark-color);
    margin-bottom: 5px;
    font-size: 1.8rem;
}

.stat-card .stat-info p {
    color: var(--gray-color);
    margin-bottom: 0;
}

@media print {
    .card, .stat-card {
        break-inside: avoid;
    }
    
    .btn-group, form {
        display: none !important;
    }
}
</style>

<?php
include 'includes/footer.php';
?>