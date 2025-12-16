// JavaScript لتفاصيل المطعم
class RestaurantDetailsManager {
    constructor() {
        this.restaurantId = null;
        this.taskModal = null;
        this.reviewModal = null;
    }

    init() {
        this.restaurantId = this.getRestaurantId();
        this.bindEvents();
        this.initMap();
        this.initReviewStars();
    }

    getRestaurantId() {
        // الحصول على معرف المطعم من الرابط أو البيانات
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id') || 
                  document.querySelector('[data-restaurant-id]')?.dataset.restaurantId ||
                  document.querySelector('#restaurant_id')?.value;
        return id;
    }

    bindEvents() {
        // إضافة تقييم
        document.getElementById('submitReview')?.addEventListener('click', () => {
            this.submitReview();
        });

        // حجز مهمة
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="reserve-task"]')) {
                const taskId = e.target.closest('[data-task-id]').dataset.taskId;
                this.reserveTask(taskId);
            }
        });

        // مشاركة المطعم
        document.getElementById('shareFacebook')?.addEventListener('click', () => {
            this.shareOnFacebook();
        });

        document.getElementById('shareTwitter')?.addEventListener('click', () => {
            this.shareOnTwitter();
        });

        document.getElementById('shareWhatsApp')?.addEventListener('click', () => {
            this.shareOnWhatsApp();
        });

        document.getElementById('copyLink')?.addEventListener('click', () => {
            this.copyLink();
        });

        // عرض جميع التقييمات
        document.getElementById('viewAllReviews')?.addEventListener('click', () => {
            this.viewAllReviews();
        });

        // تفعيل النجوم في المودال
        this.initModalStars();
    }

    initMap() {
        const mapElement = document.getElementById('map');
        if (!mapElement) return;

        const lat = parseFloat(mapElement.dataset.latitude);
        const lng = parseFloat(mapElement.dataset.longitude);

        if (!lat || !lng) return;

        // تحميل مكتبة Leaflet
        if (typeof L === 'undefined') {
            this.loadLeaflet().then(() => {
                this.createMap(lat, lng);
            });
        } else {
            this.createMap(lat, lng);
        }
    }

    loadLeaflet() {
        return new Promise((resolve, reject) => {
            // تحميل CSS
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);

            // تحميل JS
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    createMap(lat, lng) {
        const map = L.map('map').setView([lat, lng], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const restaurantName = document.querySelector('.restaurant-name')?.textContent || 'المطعم';
        
        L.marker([lat, lng])
            .addTo(map)
            .bindPopup(`<b>${restaurantName}</b>`)
            .openPopup();
    }

    initReviewStars() {
        const stars = document.querySelectorAll('.star-rating input[type="radio"]');
        stars.forEach(star => {
            star.addEventListener('change', (e) => {
                const rating = e.target.value;
                this.updateStarDisplay(rating);
            });
        });
    }

    initModalStars() {
        const modal = document.getElementById('reviewModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', () => {
                setTimeout(() => {
                    this.initReviewStars();
                }, 100);
            });
        }
    }

    updateStarDisplay(rating) {
        const stars = document.querySelectorAll('.star-rating label');
        stars.forEach((star, index) => {
            const starValue = index + 1;
            if (starValue <= rating) {
                star.querySelector('i').className = 'fas fa-star text-warning';
            } else {
                star.querySelector('i').className = 'far fa-star';
            }
        });
    }

    async submitReview() {
        const rating = document.querySelector('input[name="rating"]:checked');
        const comment = document.getElementById('reviewComment')?.value || '';
        
        if (!rating) {
            this.showNotification('يرجى اختيار تقييم', 'error');
            return;
        }

        try {
            const response = await fetch('/api/restaurants?action=add-review', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `restaurant_id=${this.restaurantId}&rating=${rating.value}&comment=${encodeURIComponent(comment)}`
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showNotification('تم إضافة تقييمك بنجاح!');
                
                // إغلاق المودال
                const modal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
                modal?.hide();
                
                // تحديث الصفحة بعد 2 ثانية
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error submitting review:', error);
            this.showNotification('حدث خطأ أثناء إضافة التقييم', 'error');
        }
    }

    async reserveTask(taskId) {
        try {
            const response = await fetch('/api/tasks?action=get-task-details&task_id=' + taskId);
            const data = await response.json();

            if (data.status === 'success') {
                this.showTaskModal(data.data);
            }
        } catch (error) {
            console.error('Error loading task details:', error);
            this.showNotification('حدث خطأ في تحميل تفاصيل المهمة', 'error');
        }
    }

    showTaskModal(task) {
        let html = `
            <div class="modal fade" id="taskModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${task.title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>${task.description}</p>
                            <div class="task-details">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong><i class="fas fa-coins"></i> النقاط:</strong>
                                        <span class="badge bg-primary">${task.points_reward}</span>
                                    </div>
                                    <div class="col-6">
                                        <strong><i class="fas fa-percentage"></i> الخصم:</strong>
                                        <span class="badge bg-success">${task.discount_percentage}%</span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong><i class="fas fa-utensils"></i> المطعم:</strong>
                                        ${task.restaurant_name}
                                    </div>
                                    <div class="col-6">
                                        <strong><i class="fas fa-map-marker-alt"></i> المدينة:</strong>
                                        ${task.city}
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <strong><i class="fas fa-users"></i> المتاح:</strong>
                                        ${task.max_participants - task.current_participants} من ${task.max_participants}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="legal-agreement mt-3">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>إشعار قانوني:</strong> أنت مسؤول عن جميع التقييمات التي تقدمها.
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
                            <button type="button" class="btn btn-primary" onclick="restaurantDetails.confirmReserveTask(${task.id})">تأكيد الحجز</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
        this.taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
        this.taskModal.show();

        // تنظيف المودال بعد إغلاقه
        document.getElementById('taskModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    async confirmReserveTask(taskId) {
        const agreement = document.getElementById('legalAgreement');
        
        if (!agreement?.checked) {
            this.showNotification('يجب الموافقة على الإشعار القانوني', 'error');
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
                this.showNotification('تم حجز المهمة بنجاح!');
                
                // إغلاق المودال
                this.taskModal?.hide();
                
                // تحديث الصفحة بعد 2 ثانية
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error reserving task:', error);
            this.showNotification('حدث خطأ أثناء حجز المهمة', 'error');
        }
    }

    shareOnFacebook() {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent(`جربت ${document.title} وكانت تجربة رائعة!`);
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${text}`, '_blank');
    }

    shareOnTwitter() {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent(`جربت ${document.title} وكانت تجربة رائعة!`);
        window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank');
    }

    shareOnWhatsApp() {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent(`جربت ${document.title} وكانت تجربة رائعة!`);
        window.open(`https://wa.me/?text=${text}%20${url}`, '_blank');
    }

    copyLink() {
        navigator.clipboard.writeText(window.location.href).then(() => {
            this.showNotification('تم نسخ الرابط إلى الحافظة');
        });
    }

    viewAllReviews() {
        window.location.href = `/restaurant/${this.restaurantId}/reviews`;
    }

    showNotification(message, type = 'success') {
        // استخدام نظام الإشعارات الموجود أو إنشاء واحد جديد
        if (window.showNotification) {
            window.showNotification('تنبيه', message, type);
        } else {
            // إنشاء إشعار بسيط
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : 'success'} notification-alert`;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'} me-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    }
}

// تهيئة المدير عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.restaurant-details-page')) {
        const restaurantDetails = new RestaurantDetailsManager();
        restaurantDetails.init();
        
        // جعل المدير متاحاً عالمياً
        window.restaurantDetails = restaurantDetails;
    }
});

// CSS للإشعارات
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification-alert {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    }
    
    .notification-alert.show {
        opacity: 1;
        transform: translateX(0);
    }
`;
document.head.appendChild(notificationStyles);