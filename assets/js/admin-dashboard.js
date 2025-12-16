// JavaScript للوحة تحكم الأدمن
class AdminDashboardManager {
    constructor() {
        this.charts = {};
        this.period = '7days';
    }

    init() {
        this.bindEvents();
        this.loadDashboardStats();
        this.loadRecentActivity();
        this.loadNewUsers();
        this.loadNewRestaurants();
        this.initCharts();
    }

    bindEvents() {
        // تغيير فترة المخططات
        document.getElementById('statsPeriod')?.addEventListener('change', (e) => {
            this.period = e.target.value;
            this.updateCharts();
        });

        // تحديث الإحصائيات
        document.getElementById('refreshStats')?.addEventListener('click', () => {
            this.refreshAllData();
        });

        // تصفية النشاط
        document.getElementById('activityFilter')?.addEventListener('change', (e) => {
            this.filterActivity(e.target.value);
        });

        // الإجراءات السريعة
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="quick-action"]')) {
                const action = e.target.closest('[data-action="quick-action"]').dataset.actionType;
                this.performQuickAction(action);
            }
        });
    }

    async loadDashboardStats() {
        try {
            const response = await fetch('/api/admin?action=dashboard-stats');
            const data = await response.json();

            if (data.status === 'success') {
                this.updateStatsDisplay(data.data);
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }

    updateStatsDisplay(stats) {
        // تحديث إحصائيات العرض
        const statElements = {
            'totalUsers': document.getElementById('totalUsers'),
            'totalRestaurants': document.getElementById('totalRestaurants'),
            'totalReviews': document.getElementById('totalReviews'),
            'activeTasks': document.getElementById('activeTasks'),
            'pendingOrders': document.getElementById('pendingOrders'),
            'reportedReviews': document.getElementById('reportedReviews'),
            'totalPoints': document.getElementById('totalPoints'),
            'newUsersToday': document.getElementById('newUsersToday')
        };

        Object.keys(statElements).forEach(key => {
            if (statElements[key] && stats[key] !== undefined) {
                statElements[key].textContent = this.formatNumber(stats[key]);
            }
        });
    }

    async loadRecentActivity() {
        try {
            const response = await fetch('/api/admin?action=recent-activity&limit=10');
            const data = await response.json();

            if (data.status === 'success') {
                this.renderActivity(data.data);
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
        }
    }

    renderActivity(activities) {
        const container = document.getElementById('recentActivity');
        if (!container) return;

        if (activities.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">لا يوجد نشاط مؤخراً</p>';
            return;
        }

        let html = '';
        activities.forEach(activity => {
            const timeAgo = this.formatTimeAgo(activity.created_at);
            const icon = this.getActivityIcon(activity.action);
            
            html += `
                <div class="activity-item ${activity.highlight ? 'highlight' : ''}">
                    <div class="activity-icon">${icon}</div>
                    <div class="activity-content">
                        <div class="activity-header">
                            <strong>${activity.user_name || 'النظام'}</strong>
                            <span class="activity-time">${timeAgo}</span>
                        </div>
                        <p class="activity-text">${activity.description}</p>
                        ${activity.details ? `<small class="activity-details">${activity.details}</small>` : ''}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    async loadNewUsers() {
        try {
            const response = await fetch('/api/admin?action=new-users&limit=5');
            const data = await response.json();

            if (data.status === 'success') {
                this.renderNewUsers(data.data);
            }
        } catch (error) {
            console.error('Error loading new users:', error);
        }
    }

    renderNewUsers(users) {
        const container = document.getElementById('newUsers');
        if (!container) return;

        if (users.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">لا يوجد مستخدمون جدد</p>';
            return;
        }

        let html = '';
        users.forEach(user => {
            const timeAgo = this.formatTimeAgo(user.created_at);
            
            html += `
                <div class="user-item">
                    <div class="user-avatar">${user.name.charAt(0)}</div>
                    <div class="user-info">
                        <strong>${user.name}</strong>
                        <small class="text-muted">${user.email}</small>
                        <div class="user-meta">
                            <span class="badge bg-${user.role === 'admin' ? 'danger' : 'primary'}">${this.getRoleName(user.role)}</span>
                            <small>${timeAgo}</small>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    async loadNewRestaurants() {
        try {
            const response = await fetch('/api/admin?action=new-restaurants&limit=5');
            const data = await response.json();

            if (data.status === 'success') {
                this.renderNewRestaurants(data.data);
            }
        } catch (error) {
            console.error('Error loading new restaurants:', error);
        }
    }

    renderNewRestaurants(restaurants) {
        const container = document.getElementById('newRestaurants');
        if (!container) return;

        if (restaurants.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">لا يوجد مطاعم جديدة</p>';
            return;
        }

        let html = '';
        restaurants.forEach(restaurant => {
            const timeAgo = this.formatTimeAgo(restaurant.created_at);
            
            html += `
                <div class="restaurant-item">
                    <div class="restaurant-avatar">${restaurant.name.charAt(0)}</div>
                    <div class="restaurant-info">
                        <strong>${restaurant.name}</strong>
                        <small class="text-muted">${restaurant.city}</small>
                        <div class="restaurant-meta">
                            <span class="badge bg-${restaurant.status === 'active' ? 'success' : 'warning'}">${this.getStatusName(restaurant.status)}</span>
                            <small>${timeAgo}</small>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    initCharts() {
        // مخطط المستخدمين
        const usersCtx = document.getElementById('usersChart');
        if (usersCtx) {
            this.charts.users = new Chart(usersCtx.getContext('2d'), {
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
                options: this.getChartOptions('المستخدمون الجدد')
            });
        }

        // مخطط التقييمات
        const reviewsCtx = document.getElementById('reviewsChart');
        if (reviewsCtx) {
            this.charts.reviews = new Chart(reviewsCtx.getContext('2d'), {
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
                options: this.getChartOptions('التقييمات الجديدة', 'bar')
            });
        }

        // مخطط المهام
        const tasksCtx = document.getElementById('tasksChart');
        if (tasksCtx) {
            this.charts.tasks = new Chart(tasksCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['مكتملة', 'نشطة', 'معلقة'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: [
                            'rgb(40, 167, 69)',
                            'rgb(255, 193, 7)',
                            'rgb(108, 117, 125)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: this.getChartOptions('حالة المهام', 'doughnut')
            });
        }

        // تحميل البيانات
        this.updateCharts();
    }

    getChartOptions(title, type = 'line') {
        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
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
                        size: 14,
                        family: "'Cairo', 'Tajawal', sans-serif"
                    }
                }
            }
        };

        if (type === 'line' || type === 'bar') {
            baseOptions.scales = {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            };
        }

        return baseOptions;
    }

    async updateCharts() {
        try {
            const response = await fetch(`/api/admin?action=chart-data&period=${this.period}`);
            const data = await response.json();

            if (data.status === 'success') {
                // تحديث مخطط المستخدمين
                if (this.charts.users && data.data.users) {
                    this.charts.users.data.labels = data.data.users.labels;
                    this.charts.users.data.datasets[0].data = data.data.users.data;
                    this.charts.users.update();
                }

                // تحديث مخطط التقييمات
                if (this.charts.reviews && data.data.reviews) {
                    this.charts.reviews.data.labels = data.data.reviews.labels;
                    this.charts.reviews.data.datasets[0].data = data.data.reviews.data;
                    this.charts.reviews.update();
                }

                // تحديث مخطط المهام
                if (this.charts.tasks && data.data.tasks) {
                    this.charts.tasks.data.datasets[0].data = [
                        data.data.tasks.completed || 0,
                        data.data.tasks.active || 0,
                        data.data.tasks.pending || 0
                    ];
                    this.charts.tasks.update();
                }
            }
        } catch (error) {
            console.error('Error updating charts:', error);
        }
    }

    filterActivity(filter) {
        const items = document.querySelectorAll('.activity-item');
        items.forEach(item => {
            if (filter === 'all' || item.dataset.type === filter) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    performQuickAction(action) {
        switch (action) {
            case 'refresh':
                this.refreshAllData();
                break;
            case 'add_restaurant':
                window.location.href = '/admin/restaurants/add';
                break;
            case 'add_category':
                window.location.href = '/admin/categories/add';
                break;
            case 'view_orders':
                window.location.href = '/admin/store/orders';
                break;
            case 'view_reports':
                window.location.href = '/admin/reports';
                break;
            case 'generate_best100':
                this.generateBest100();
                break;
        }
    }

    async generateBest100() {
        if (!confirm('هل تريد تحديث قائمة أفضل 100 مطعم؟')) return;

        try {
            const response = await fetch('/api/admin?action=generate-best100', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showNotification('تم تحديث قائمة أفضل 100 مطعم بنجاح');
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error generating best 100:', error);
            this.showNotification('حدث خطأ أثناء التحديث', 'error');
        }
    }

    refreshAllData() {
        this.loadDashboardStats();
        this.loadRecentActivity();
        this.loadNewUsers();
        this.loadNewRestaurants();
        this.updateCharts();
        
        this.showNotification('تم تحديث البيانات بنجاح');
    }

    // وظائف مساعدة
    formatNumber(num) {
        return new Intl.NumberFormat('ar-SA').format(num);
    }

    formatTimeAgo(dateString) {
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

    getActivityIcon(action) {
        const icons = {
            'login': 'fas fa-sign-in-alt',
            'register': 'fas fa-user-plus',
            'review': 'fas fa-star',
            'task': 'fas fa-tasks',
            'order': 'fas fa-shopping-cart',
            'restaurant': 'fas fa-utensils',
            'user': 'fas fa-user',
            'system': 'fas fa-cog'
        };
        
        const iconClass = icons[action] || 'fas fa-bell';
        return `<i class="${iconClass}"></i>`;
    }

    getRoleName(role) {
        const roles = {
            'admin': 'أدمن',
            'moderator': 'مشرف',
            'restaurant_owner': 'صاحب مطعم',
            'member': 'عضو'
        };
        return roles[role] || role;
    }

    getStatusName(status) {
        const statuses = {
            'active': 'نشط',
            'pending': 'معلق',
            'suspended': 'موقوف'
        };
        return statuses[status] || status;
    }

    showNotification(message, type = 'success') {
        // استخدام نظام الإشعارات الموجود
        if (window.showNotification) {
            window.showNotification('تنبيه', message, type);
        }
    }
}

// تهيئة لوحة التحكم عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.admin-dashboard')) {
        const adminDashboard = new AdminDashboardManager();
        adminDashboard.init();
        
        // جعل المدير متاحاً عالمياً
        window.adminDashboard = adminDashboard;
    }
});