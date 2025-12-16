// دالة التبديل بين الوضع الليلي والنهاري
function toggleTheme() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    // تحديث الأيقونة
    const themeToggle = document.querySelector('.theme-toggle');
    if (themeToggle) {
        const icon = themeToggle.querySelector('i');
        const text = themeToggle.querySelector('span');
        if (icon && text) {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            text.textContent = isDark ? 'الوضع النهاري' : 'الوضع الليلي';
        }
    }
    
    // إظهار الإشعار
    showNotification('تم تغيير الوضع', `تم التبديل إلى الوضع ${isDark ? 'الليلي' : 'النهاري'}`);
}

// تحميل الوضع المحفوظ
function loadTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
}

// دالة إظهار الإشعارات
function showNotification(title, message, type = 'success') {
    // إنشاء عنصر الإشعار إذا لم يكن موجوداً
    let notification = document.getElementById('global-notification');
    
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'global-notification';
        notification.className = 'notification';
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong class="notification-title"></strong>
                    <p class="notification-message"></p>
                </div>
            </div>
        `;
        document.body.appendChild(notification);
    }
    
    // تحديد الأيقونة واللون حسب النوع
    const icon = notification.querySelector('i');
    const titleEl = notification.querySelector('.notification-title');
    const messageEl = notification.querySelector('.notification-message');
    
    if (type === 'error') {
        icon.className = 'fas fa-exclamation-circle';
        icon.style.color = 'var(--danger-color)';
    } else if (type === 'warning') {
        icon.className = 'fas fa-exclamation-triangle';
        icon.style.color = 'var(--warning-color)';
    } else if (type === 'info') {
        icon.className = 'fas fa-info-circle';
        icon.style.color = 'var(--info-color)';
    } else {
        icon.className = 'fas fa-check-circle';
        icon.style.color = 'var(--success-color)';
    }
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    // إظهار الإشعار
    notification.classList.add('show');
    
    // إخفاء الإشعار تلقائياً بعد 3 ثوانٍ
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// دالة التحميل الديناميكي للمحتوى
async function loadPage(url, containerId) {
    try {
        const response = await fetch(url);
        const html = await response.text();
        document.getElementById(containerId).innerHTML = html;
        
        // تفعيل السكريبتات الجديدة
        initPageScripts();
        
    } catch (error) {
        console.error('Error loading page:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل الصفحة', 'error');
    }
}

// تهيئة السكريبتات الخاصة بالصفحة
function initPageScripts() {
    // إضافة المستمعين للأحداث
    initEventListeners();
    
    // تهيئة الرسوم البيانية
    initCharts();
    
    // تهيئة الحقول
    initFormValidations();
}

// تحميل الرسوم البيانية
function initCharts() {
    const chartElements = document.querySelectorAll('[data-chart]');
    
    chartElements.forEach(element => {
        const chartType = element.getAttribute('data-chart-type') || 'line';
        const data = JSON.parse(element.getAttribute('data-chart-data'));
        const options = JSON.parse(element.getAttribute('data-chart-options') || '{}');
        
        new Chart(element.getContext('2d'), {
            type: chartType,
            data: data,
            options: options
        });
    });
}

// مصادقة النماذج
function initFormValidations() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

// إضافة المستمعين للأحداث
function initEventListeners() {
    // الأزرار
    document.querySelectorAll('[data-action]').forEach(button => {
        button.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const data = this.getAttribute('data-action-data');
            
            if (window[action]) {
                window[action](data);
            }
        });
    });
    
    // التبويبات
    document.querySelectorAll('[data-toggle="tab"]').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('href');
            
            // إخفاء جميع محتويات التبويبات
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active', 'show');
            });
            
            // إزالة النشاط من جميع التبويبات
            document.querySelectorAll('[data-toggle="tab"]').forEach(t => {
                t.classList.remove('active');
            });
            
            // إظهار التبويب المحدد
            this.classList.add('active');
            document.querySelector(target).classList.add('active', 'show');
        });
    });
    
    // الفتح والإغلاق للشريط الجانبي
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (sidebarToggle && sidebar && overlay) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }
}

// دالة البحث الديناميكي
function initSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let timeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        
        timeout = setTimeout(() => {
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            // إجراء البحث (يمكن استبداله بطلب AJAX)
            performSearch(query);
        }, 300);
    });
    
    // إخفاء نتائج البحث عند النقر خارجها
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
}

// تنفيذ البحث
async function performSearch(query) {
    try {
        const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`);
        const results = await response.json();
        
        displaySearchResults(results);
        
    } catch (error) {
        console.error('Search error:', error);
    }
}

// عرض نتائج البحث
function displaySearchResults(results) {
    const container = document.getElementById('searchResults');
    
    if (!results || results.length === 0) {
        container.innerHTML = '<div class="search-result-item">لا توجد نتائج</div>';
        container.style.display = 'block';
        return;
    }
    
    let html = '';
    
    results.forEach(result => {
        html += `
            <a href="${result.url}" class="search-result-item">
                <i class="${result.icon}"></i>
                <div>
                    <strong>${result.title}</strong>
                    <p>${result.description}</p>
                </div>
            </a>
        `;
    });
    
    container.innerHTML = html;
    container.style.display = 'block';
}

// دالة تصفية العناصر
function initFilters() {
    const filterInputs = document.querySelectorAll('[data-filter]');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            const filterType = this.getAttribute('data-filter');
            const filterValue = this.value;
            
            filterItems(filterType, filterValue);
        });
    });
}

function filterItems(filterType, filterValue) {
    const items = document.querySelectorAll('.filter-item');
    
    items.forEach(item => {
        const itemValue = item.getAttribute(`data-${filterType}`);
        
        if (filterValue === 'all' || itemValue === filterValue) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// تهيئة التطبيق عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // تحميل الوضع المحفوظ
    loadTheme();
    
    // تهيئة جميع المكونات
    initPageScripts();
    initSearch();
    initFilters();
    
    // تحديث الوقت الحي
    updateLiveTime();
    
    // تحميل الإشعارات
    loadNotifications();
});

// تحديث الوقت الحي
function updateLiveTime() {
    const timeElement = document.getElementById('live-time');
    
    if (timeElement) {
        setInterval(() => {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ar-SA');
            timeElement.textContent = timeString;
        }, 1000);
    }
}

// تحميل الإشعارات
async function loadNotifications() {
    try {
        const response = await fetch('/api/notifications');
        const notifications = await response.json();
        
        updateNotificationBadge(notifications.length);
        displayNotifications(notifications);
        
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

function displayNotifications(notifications) {
    const container = document.getElementById('notifications-container');
    
    if (!container) return;
    
    let html = '';
    
    notifications.slice(0, 10).forEach(notification => {
        const iconClass = {
            'info': 'fas fa-info-circle text-info',
            'success': 'fas fa-check-circle text-success',
            'warning': 'fas fa-exclamation-triangle text-warning',
            'error': 'fas fa-exclamation-circle text-danger'
        }[notification.type] || 'fas fa-bell';
        
        html += `
            <a href="${notification.link || '#'}" class="notification-item ${!notification.is_read ? 'unread' : ''}">
                <div class="notification-icon">
                    <i class="${iconClass}"></i>
                </div>
                <div class="notification-content">
                    <h6>${notification.title}</h6>
                    <p>${notification.message}</p>
                    <small>${formatRelativeTime(notification.created_at)}</small>
                </div>
            </a>
        `;
    });
    
    container.innerHTML = html || '<div class="notification-item">لا توجد إشعارات جديدة</div>';
}

// دالة المساعدة لصياغة الوقت النسبي
function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
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