<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'لوحة تحكم الأدمن';
$page_scripts = ['admin-dashboard.js'];

include 'includes/header.php';

// إنشاء كائن الأدمن
$admin = new Admin($db->getConnection(), $user->getId());

// الحصول على إحصائيات الأدمن
$stats = $admin->getDashboardStats();
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-home"></i> لوحة التحكم</h2>
    
    <!-- إحصائيات سريعة -->
    <div class="admin-stats row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="admin-card">
                <h3><?php echo number_format($stats['total_users']); ?></h3>
                <p>إجمالي المستخدمين</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="admin-card">
                <h3><?php echo number_format($stats['total_restaurants']); ?></h3>
                <p>إجمالي المطاعم</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="admin-card">
                <h3><?php echo number_format($stats['total_reviews']); ?></h3>
                <p>إجمالي التقييمات</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="admin-card">
                <h3><?php echo number_format($stats['active_tasks']); ?></h3>
                <p>المهام النشطة</p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- الشريط الجانبي -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">الإجراءات السريعة</h5>
                    <div class="list-group">
                        <a href="/admin/restaurants" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus-circle me-2"></i> إضافة مطعم جديد
                        </a>
                        <a href="/admin/categories" class="list-group-item list-group-item-action">
                            <i class="fas fa-tags me-2"></i> إدارة التصنيفات
                        </a>
                        <a href="/admin/store/products" class="list-group-item list-group-item-action">
                            <i class="fas fa-store me-2"></i> إضافة منتج للمتجر
                        </a>
                        <a href="/admin/store/orders" class="list-group-item list-group-item-action">
                            <i class="fas fa-shopping-cart me-2"></i> طلبات المتجر المعلقة
                            <?php if ($stats['pending_store_orders'] > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $stats['pending_store_orders']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/admin/comments" class="list-group-item list-group-item-action">
                            <i class="fas fa-comment-slash me-2"></i> التعليقات المبلغ عنها
                            <?php if ($stats['reported_reviews'] > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $stats['reported_reviews']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/admin/best100" class="list-group-item list-group-item-action">
                            <i class="fas fa-trophy me-2"></i> تحديث أفضل 100 مطعم
                        </a>
                        <a href="/admin/design" class="list-group-item list-group-item-action">
                            <i class="fas fa-palette me-2"></i> تنسيق الموقع
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- النشاط الأخير -->
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">النشاط الأخير</h5>
                    <div id="recentActivity">
                        <!-- سيتم تحميل النشاط هنا ديناميكياً -->
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="col-lg-9">
            <!-- المخططات -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">الإحصائيات</h5>
                        <div>
                            <select id="statsPeriod" class="form-select form-select-sm" onchange="updateCharts()" style="width: auto;">
                                <option value="7days">آخر 7 أيام</option>
                                <option value="30days">آخر 30 يوم</option>
                                <option value="90days">آخر 90 يوم</option>
                                <option value="year">السنة الحالية</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="usersChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="reviewsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- المستخدمون الجدد -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">المستخدمون الجدد اليوم</h5>
                        <a href="/admin/users" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    
                    <div id="newUsersToday">
                        <!-- سيتم تحميل المستخدمين هنا ديناميكياً -->
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- المطاعم الجديدة -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">المطاعم الجديدة اليوم</h5>
                        <a href="/admin/restaurants" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    
                    <div id="newRestaurantsToday">
                        <!-- سيتم تحميل المطاعم هنا ديناميكياً -->
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- التقارير السريعة -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">تقارير سريعة</h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="quick-report">
                                <div class="report-icon bg-primary">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div class="report-info">
                                    <h6><?php echo number_format($stats['total_points_earned']); ?></h6>
                                    <small>إجمالي النقاط الموزعة</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="quick-report">
                                <div class="report-icon bg-success">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="report-info">
                                    <h6><?php echo number_format($stats['total_points_redeemed']); ?></h6>
                                    <small>إجمالي النقاط المستخدمة</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="quick-report">
                                <div class="report-icon bg-info">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="report-info">
                                    <h6><?php echo number_format($stats['new_users_today']); ?></h6>
                                    <small>مستخدم جديد اليوم</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل البيانات عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadRecentActivity();
    loadNewUsersToday();
    loadNewRestaurantsToday();
    initCharts();
});

// تحميل النشاط الأخير
async function loadRecentActivity() {
    try {
        const response = await fetch('/api/admin?action=recent-activity&limit=5');
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            data.data.forEach(activity => {
                const timeAgo = formatRelativeTime(activity.created_at);
                
                html += `
                    <div class="activity-item mb-2 pb-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${activity.action}</strong>
                                <p class="mb-0 small text-muted">${activity.description}</p>
                            </div>
                            <small class="text-muted">${timeAgo}</small>
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('recentActivity').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading activity:', error);
    }
}

// تحميل المستخدمين الجدد اليوم
async function loadNewUsersToday() {
    try {
        const response = await fetch('/api/admin?action=new-users-today');
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            if (data.data.length === 0) {
                html = '<p class="text-center text-muted">لا يوجد مستخدمون جدد اليوم</p>';
            } else {
                data.data.forEach(user => {
                    const timeAgo = formatRelativeTime(user.created_at);
                    
                    html += `
                        <div class="user-item mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-2">
                                    ${user.name.charAt(0)}
                                </div>
                                <div style="flex: 1;">
                                    <strong>${user.name}</strong>
                                    <p class="mb-0 small text-muted">${user.email}</p>
                                </div>
                                <small class="text-muted">${timeAgo}</small>
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('newUsersToday').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading new users:', error);
    }
}

// تحميل المطاعم الجديدة اليوم
async function loadNewRestaurantsToday() {
    try {
        const response = await fetch('/api/admin?action=new-restaurants-today');
        const data = await response.json();
        
        if (data.status === 'success') {
            let html = '';
            
            if (data.data.length === 0) {
                html = '<p class="text-center text-muted">لا يوجد مطاعم جديدة اليوم</p>';
            } else {
                data.data.forEach(restaurant => {
                    const timeAgo = formatRelativeTime(restaurant.created_at);
                    
                    html += `
                        <div class="restaurant-item mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="restaurant-avatar me-2">
                                    ${restaurant.name.charAt(0)}
                                </div>
                                <div style="flex: 1;">
                                    <strong>${restaurant.name}</strong>
                                    <p class="mb-0 small text-muted">${restaurant.city}</p>
                                </div>
                                <small class="text-muted">${timeAgo}</small>
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('newRestaurantsToday').innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading new restaurants:', error);
    }
}

// تهيئة المخططات
function initCharts() {
    // مخطط المستخدمين
    const usersCtx = document.getElementById('usersChart').getContext('2d');
    window.usersChart = new Chart(usersCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'المستخدمون الجدد',
                data: [],
                borderColor: 'rgb(255, 107, 53)',
                backgroundColor: 'rgba(255, 107, 53, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
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
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // مخطط التقييمات
    const reviewsCtx = document.getElementById('reviewsChart').getContext('2d');
    window.reviewsChart = new Chart(reviewsCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'التقييمات الجديدة',
                data: [],
                backgroundColor: 'rgba(42, 157, 143, 0.7)',
                borderColor: 'rgb(42, 157, 143)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
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
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // تحميل البيانات
    updateCharts();
}

// تحديث المخططات
async function updateCharts() {
    const period = document.getElementById('statsPeriod').value;
    
    try {
        const response = await fetch(`/api/admin?action=stats&period=${period}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            // تحديث مخطط المستخدمين
            window.usersChart.data.labels = data.data.users.labels;
            window.usersChart.data.datasets[0].data = data.data.users.data;
            window.usersChart.update();
            
            // تحديث مخطط التقييمات
            window.reviewsChart.data.labels = data.data.reviews.labels;
            window.reviewsChart.data.datasets[0].data = data.data.reviews.data;
            window.reviewsChart.update();
        }
    } catch (error) {
        console.error('Error updating charts:', error);
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
</script>

<style>
.admin-card {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: var(--border-radius);
    padding: 20px;
    text-align: center;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.admin-card h3 {
    font-size: 2rem;
    margin-bottom: 5px;
}

.admin-card p {
    opacity: 0.9;
    margin-bottom: 0;
}

.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
}

.quick-report {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: var(--card-bg);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}

.report-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    font-size: 1.2rem;
}

.report-info h6 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.report-info small {
    color: var(--gray-color);
}

.user-avatar, .restaurant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.activity-item:last-child,
.user-item:last-child,
.restaurant-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
</style>

<?php
include 'includes/footer.php';
?>