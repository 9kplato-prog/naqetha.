<?php
$page_title = 'جميع المطاعم';
$page_scripts = ['restaurants.js'];

include 'includes/header.php';

// الحصول على جميع التصنيفات
$sql = "SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order";
$categories_result = $db->query($sql);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-utensils"></i> جميع المطاعم</h2>
    
    <?php if (getSetting('show_best100', 1)): ?>
    <div class="best-restaurants">
        <h3><i class="fas fa-trophy"></i> أفضل 100 مطعم هذا الأسبوع</h3>
        <p>اكتشف قائمة أفضل المطاعم التي حصلت على أعلى التقييمات هذا الأسبوع</p>
        <button class="btn btn-outline" onclick="viewBest100()" style="color: white; border-color: white; margin-top: 10px;">
            <i class="fas fa-trophy"></i> استعرض القائمة
        </button>
    </div>
    <?php endif; ?>
    
    <!-- فلترة المطاعم -->
    <div class="city-filter mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="citySelect" class="form-label">المدينة:</label>
                <select id="citySelect" class="form-select" onchange="filterRestaurants()">
                    <option value="all">جميع المدن</option>
                    <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="categorySelect" class="form-label">التصنيف:</label>
                <select id="categorySelect" class="form-select" onchange="filterRestaurants()">
                    <option value="all">جميع التصنيفات</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="sortSelect" class="form-label">الترتيب:</label>
                <select id="sortSelect" class="form-select" onchange="filterRestaurants()">
                    <option value="rating_desc">الأعلى تقييماً</option>
                    <option value="reviews_desc">الأكثر تقييماً</option>
                    <option value="name_asc">حسب الاسم (أ-ي)</option>
                    <option value="name_desc">حسب الاسم (ي-أ)</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="searchInput" class="form-label">البحث:</label>
                <div class="input-group">
                    <input type="text" id="searchInput" class="form-control" placeholder="ابحث عن مطعم..." onkeyup="filterRestaurants()">
                    <button class="btn btn-primary" onclick="filterRestaurants()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- قائمة المطاعم -->
    <div id="restaurantsList" class="row">
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
        </div>
    </div>
    
    <!-- تذييل التحميل -->
    <div id="loadMoreContainer" class="text-center mt-4 d-none">
        <button id="loadMoreBtn" class="btn btn-primary" onclick="loadMoreRestaurants()">
            <i class="fas fa-spinner fa-spin d-none"></i>
            تحميل المزيد
        </button>
    </div>
    
    <!-- حالة عدم وجود مطاعم -->
    <div id="noResults" class="d-none text-center mt-5">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            لا توجد مطاعم مطابقة لبحثك
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let isLoading = false;
let hasMore = true;

// تحميل المطاعم عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadRestaurants();
});

// تحميل المطاعم
async function loadRestaurants(reset = false) {
    if (isLoading) return;
    
    if (reset) {
        currentPage = 1;
        hasMore = true;
        document.getElementById('restaurantsList').innerHTML = `
            <div class="col-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
            </div>
        `;
    }
    
    isLoading = true;
    
    // إظهار مؤشر التحميل
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (loadMoreBtn) {
        loadMoreBtn.disabled = true;
        loadMoreBtn.querySelector('.fa-spinner').classList.remove('d-none');
    }
    
    try {
        const city = document.getElementById('citySelect').value;
        const category = document.getElementById('categorySelect').value;
        const sort = document.getElementById('sortSelect').value;
        const search = document.getElementById('searchInput').value;
        
        const response = await fetch(`/api/restaurants?action=get-all&city=${city}&category_id=${category}&search=${search}&limit=12&offset=${(currentPage - 1) * 12}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const restaurants = data.data;
            
            if (reset) {
                document.getElementById('restaurantsList').innerHTML = '';
            }
            
            if (restaurants.length === 0) {
                if (currentPage === 1) {
                    document.getElementById('restaurantsList').innerHTML = '';
                    document.getElementById('noResults').classList.remove('d-none');
                    document.getElementById('loadMoreContainer').classList.add('d-none');
                }
                hasMore = false;
            } else {
                document.getElementById('noResults').classList.add('d-none');
                
                let html = '';
                
                restaurants.forEach(restaurant => {
                    const ratingStars = getRatingStars(restaurant.rating);
                    
                    html += `
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="restaurant-card">
                                <div class="restaurant-header">
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
                                    <div class="user-avatar">${restaurant.name.charAt(0)}</div>
                                </div>
                                
                                <p><i class="fas fa-map-marker-alt"></i> ${restaurant.city}</p>
                                
                                ${restaurant.description ? `
                                <p class="restaurant-description">${restaurant.description.substring(0, 120)}...</p>
                                ` : ''}
                                
                                <!-- آخر التقييمات (20 تقييم) -->
                                <div class="mt-3">
                                    <button class="btn btn-outline btn-sm w-100" onclick="viewRestaurantReviews(${restaurant.id})">
                                        <i class="fas fa-comment"></i> عرض آخر 20 تقييم
                                    </button>
                                </div>
                                
                                <div class="task-actions mt-3">
                                    <button class="btn btn-primary btn-sm" onclick="viewRestaurantDetails(${restaurant.id})">
                                        <i class="fas fa-info-circle"></i> التفاصيل والإحصائيات
                                    </button>
                                    <button class="btn btn-outline btn-sm" onclick="openGoogleMaps('${restaurant.name}', '${restaurant.city}')">
                                        <i class="fas fa-map-marker-alt"></i> الموقع
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                if (reset) {
                    document.getElementById('restaurantsList').innerHTML = html;
                } else {
                    document.getElementById('restaurantsList').insertAdjacentHTML('beforeend', html);
                }
                
                // إظهار زر تحميل المزيد إذا كان هناك المزيد من المطاعم
                if (restaurants.length === 12) {
                    document.getElementById('loadMoreContainer').classList.remove('d-none');
                    currentPage++;
                } else {
                    document.getElementById('loadMoreContainer').classList.add('d-none');
                    hasMore = false;
                }
            }
        }
    } catch (error) {
        console.error('Error loading restaurants:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل المطاعم', 'error');
    } finally {
        isLoading = false;
        
        if (loadMoreBtn) {
            loadMoreBtn.disabled = false;
            loadMoreBtn.querySelector('.fa-spinner').classList.add('d-none');
        }
    }
}

// تصفية المطاعم
function filterRestaurants() {
    loadRestaurants(true);
}

// تحميل المزيد من المطاعم
function loadMoreRestaurants() {
    if (hasMore && !isLoading) {
        loadRestaurants(false);
    }
}

// عرض أفضل 100 مطعم
function viewBest100() {
    window.location.href = '/best-100';
}

// عرض تفاصيل المطعم
function viewRestaurantDetails(restaurantId) {
    window.location.href = `/restaurant/${restaurantId}`;
}

// عرض تقييمات المطعم
async function viewRestaurantReviews(restaurantId) {
    try {
        const response = await fetch(`/api/restaurants?action=get-reviews&restaurant_id=${restaurantId}&limit=20`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const reviews = data.data;
            
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
                                <div>
                                    <strong>${review.user_name}</strong>
                                    <div class="review-rating" style="color: #ffc107;">${stars}</div>
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
            
            // إضافة المودال إلى الصفحة وعرضه
            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById('reviewsModal'));
            modal.show();
            
            // تنظيف المودال بعد إغلاقه
            document.getElementById('reviewsModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل التقييمات', 'error');
    }
}

// دالة المساعدة لعرض النجوم
function getRatingStars(rating) {
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
</script>

<?php
include 'includes/footer.php';
?>