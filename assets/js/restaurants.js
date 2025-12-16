// JavaScript للمطاعم
class RestaurantsManager {
    constructor() {
        this.currentPage = 1;
        this.isLoading = false;
        this.hasMore = true;
        this.filters = {
            city: 'all',
            category: 'all',
            sort: 'rating_desc',
            search: ''
        };
    }

    init() {
        this.bindEvents();
        this.loadRestaurants();
        this.loadCategories();
    }

    bindEvents() {
        // فلترة المطاعم
        document.getElementById('citySelect')?.addEventListener('change', (e) => {
            this.filters.city = e.target.value;
            this.resetAndLoad();
        });

        document.getElementById('categorySelect')?.addEventListener('change', (e) => {
            this.filters.category = e.target.value;
            this.resetAndLoad();
        });

        document.getElementById('sortSelect')?.addEventListener('change', (e) => {
            this.filters.sort = e.target.value;
            this.resetAndLoad();
        });

        document.getElementById('searchInput')?.addEventListener('input', (e) => {
            this.filters.search = e.target.value;
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.resetAndLoad();
            }, 500);
        });

        // زر تحميل المزيد
        document.getElementById('loadMoreBtn')?.addEventListener('click', () => {
            this.loadMore();
        });

        // فتح مودال تقييمات المطعم
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="view-reviews"]')) {
                const restaurantId = e.target.closest('[data-restaurant-id]').dataset.restaurantId;
                this.viewRestaurantReviews(restaurantId);
            }
        });
    }

    async loadRestaurants(reset = false) {
        if (this.isLoading) return;

        if (reset) {
            this.currentPage = 1;
            this.hasMore = true;
            this.showLoading();
        }

        this.isLoading = true;

        try {
            const params = new URLSearchParams({
                action: 'get-all',
                city: this.filters.city !== 'all' ? this.filters.city : '',
                category_id: this.filters.category !== 'all' ? this.filters.category : '',
                search: this.filters.search,
                limit: 12,
                offset: (this.currentPage - 1) * 12
            });

            const response = await fetch(`/api/restaurants?${params}`);
            const data = await response.json();

            if (data.status === 'success') {
                if (reset) {
                    this.clearResults();
                }

                if (data.data.length === 0) {
                    if (reset) {
                        this.showNoResults();
                    }
                    this.hasMore = false;
                } else {
                    this.hideNoResults();
                    this.renderRestaurants(data.data);
                    
                    if (data.data.length === 12) {
                        this.showLoadMore();
                        this.currentPage++;
                    } else {
                        this.hideLoadMore();
                        this.hasMore = false;
                    }
                }
            }
        } catch (error) {
            console.error('Error loading restaurants:', error);
            this.showError('حدث خطأ في تحميل المطاعم');
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }

    async loadCategories() {
        try {
            const response = await fetch('/api/restaurants?action=get-categories');
            const data = await response.json();

            if (data.status === 'success') {
                this.renderCategories(data.data);
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    renderRestaurants(restaurants) {
        const container = document.getElementById('restaurantsList');
        
        restaurants.forEach(restaurant => {
            const ratingStars = this.getRatingStars(restaurant.rating);
            const restaurantCard = this.createRestaurantCard(restaurant, ratingStars);
            container.insertAdjacentHTML('beforeend', restaurantCard);
        });
    }

    createRestaurantCard(restaurant, ratingStars) {
        return `
            <div class="col-md-4 col-sm-6 mb-4" data-restaurant-id="${restaurant.id}">
                <div class="restaurant-card">
                    <div class="restaurant-header">
                        ${restaurant.logo ? `
                            <img src="/uploads/images/${restaurant.logo}" class="restaurant-logo" alt="${restaurant.name}">
                        ` : `
                            <div class="restaurant-avatar">${restaurant.name.charAt(0)}</div>
                        `}
                        <div class="restaurant-info">
                            <h3>${restaurant.name}</h3>
                            <div class="restaurant-rating">
                                ${ratingStars}
                                <span>${restaurant.rating}</span>
                                <span>(${restaurant.total_reviews} تقييم)</span>
                            </div>
                            <span class="restaurant-category" style="background-color: ${restaurant.category_color || '#ff6b35'}">
                                ${restaurant.category_name || 'غير مصنف'}
                            </span>
                        </div>
                    </div>
                    
                    <p><i class="fas fa-map-marker-alt"></i> ${restaurant.city}</p>
                    
                    ${restaurant.description ? `
                        <p class="restaurant-description">${restaurant.description.substring(0, 120)}...</p>
                    ` : ''}
                    
                    <div class="mt-3">
                        <button class="btn btn-outline btn-sm w-100" data-action="view-reviews">
                            <i class="fas fa-comment"></i> عرض آخر 20 تقييم
                        </button>
                    </div>
                    
                    <div class="task-actions mt-3">
                        <button class="btn btn-primary btn-sm" onclick="window.location.href='/restaurant/${restaurant.slug}'">
                            <i class="fas fa-info-circle"></i> التفاصيل
                        </button>
                        <button class="btn btn-outline btn-sm" onclick="openGoogleMaps('${restaurant.name}', '${restaurant.city}')">
                            <i class="fas fa-map-marker-alt"></i> الموقع
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderCategories(categories) {
        const select = document.getElementById('categorySelect');
        if (!select) return;

        select.innerHTML = '<option value="all">جميع التصنيفات</option>';
        
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            select.appendChild(option);
        });
    }

    async viewRestaurantReviews(restaurantId) {
        try {
            const response = await fetch(`/api/restaurants?action=get-reviews&restaurant_id=${restaurantId}&limit=20`);
            const data = await response.json();

            if (data.status === 'success') {
                this.showReviewsModal(data.data);
            }
        } catch (error) {
            console.error('Error loading reviews:', error);
            this.showError('حدث خطأ في تحميل التقييمات');
        }
    }

    showReviewsModal(reviews) {
        let html = `
            <div class="modal fade" id="reviewsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">آخر 20 تقييم للمطعم</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
        `;

        if (reviews.length === 0) {
            html += `<p class="text-center">لا توجد تقييمات بعد</p>`;
        } else {
            reviews.forEach(review => {
                const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
                const date = new Date(review.created_at).toLocaleDateString('ar-SA');
                
                html += `
                    <div class="review-card mb-3">
                        <div class="review-header">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-2">
                                    ${review.user_name?.charAt(0) || '?'}
                                </div>
                                <div>
                                    <strong>${review.user_name || 'مستخدم'}</strong>
                                    <div class="review-rating" style="color: #ffc107;">${stars}</div>
                                </div>
                            </div>
                            <small class="text-muted">${date}</small>
                        </div>
                        <div class="review-body">
                            <p>${review.comment || 'لا يوجد تعليق'}</p>
                        </div>
                    </div>
                `;
            });
        }

        html += `
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('reviewsModal'));
        modal.show();

        // تنظيف المودال بعد إغلاقه
        document.getElementById('reviewsModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    loadMore() {
        if (this.hasMore && !this.isLoading) {
            this.loadRestaurants(false);
        }
    }

    resetAndLoad() {
        this.loadRestaurants(true);
    }

    getRatingStars(rating) {
        const fullStars = Math.floor(rating);
        const hasHalfStar = (rating - fullStars) >= 0.5;
        const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
        
        let stars = '';
        
        for (let i = 0; i < fullStars; i++) {
            stars += '<i class="fas fa-star text-warning"></i>';
        }
        
        if (hasHalfStar) {
            stars += '<i class="fas fa-star-half-alt text-warning"></i>';
        }
        
        for (let i = 0; i < emptyStars; i++) {
            stars += '<i class="far fa-star text-warning"></i>';
        }
        
        return stars;
    }

    openGoogleMaps(restaurantName, city) {
        const query = encodeURIComponent(`${restaurantName} ${city}`);
        window.open(`https://www.google.com/maps/search/?api=1&query=${query}`, '_blank');
    }

    // وظائف مساعدة للواجهة
    showLoading() {
        const container = document.getElementById('restaurantsList');
        if (container) {
            container.innerHTML = `
                <div class="col-12 text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                </div>
            `;
        }
    }

    hideLoading() {
        // إخفاء أي مؤشر تحميل
    }

    clearResults() {
        const container = document.getElementById('restaurantsList');
        if (container) {
            container.innerHTML = '';
        }
    }

    showNoResults() {
        const container = document.getElementById('noResults');
        if (container) {
            container.classList.remove('d-none');
        }
        this.hideLoadMore();
    }

    hideNoResults() {
        const container = document.getElementById('noResults');
        if (container) {
            container.classList.add('d-none');
        }
    }

    showLoadMore() {
        const container = document.getElementById('loadMoreContainer');
        if (container) {
            container.classList.remove('d-none');
        }
    }

    hideLoadMore() {
        const container = document.getElementById('loadMoreContainer');
        if (container) {
            container.classList.add('d-none');
        }
    }

    showError(message) {
        const container = document.getElementById('restaurantsList');
        if (container) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> ${message}
                    </div>
                </div>
            `;
        }
    }
}

// تهيئة المدير عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('restaurantsList')) {
        const restaurantsManager = new RestaurantsManager();
        restaurantsManager.init();
        
        // جعل المدير متاحاً عالمياً
        window.restaurantsManager = restaurantsManager;
    }
});