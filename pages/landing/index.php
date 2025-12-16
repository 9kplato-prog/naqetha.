<?php
$page_title = 'الرئيسية';
$hide_header = true;
include 'includes/header.php';
?>

<!-- الهيدر الرئيسي -->
<div class="landing-hero">
    <div class="container">
        <h2>اكتشف أفضل المطاعم في مدينتك واستفد من زياراتك!</h2>
        <p>منصة "<?php echo SITE_NAME; ?>" توفر لك تجربة فريدة لتقييم المطاعم واكتشاف الأماكن المميزة، مع فرصة كسب نقاط حقيقية من خلال زياراتك.</p>
        
        <div class="hero-buttons">
            <button class="btn btn-primary" onclick="window.location.href='<?php echo BASE_URL; ?>restaurants'">
                <i class="fas fa-utensils"></i> استعرض المطاعم
            </button>
            
            <div class="mt-3">
                <button class="btn btn-outline" onclick="window.location.href='<?php echo BASE_URL; ?>login'" 
                        style="color: white; border-color: white; margin-left: 10px;">
                    <i class="fas fa-sign-in-alt"></i> تسجيل دخول
                </button>
                <button class="btn btn-primary" onclick="window.location.href='<?php echo BASE_URL; ?>register'">
                    <i class="fas fa-user-plus"></i> إنشاء حساب
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container mt-5">
    <!-- أفضل المطاعم -->
    <h2 class="section-title">المطاعم الأعلى تقييماً</h2>
    
    <div class="city-filter mb-4">
        <label for="citySelect">اختر مدينتك:</label>
        <select id="citySelect" class="form-select" onchange="filterRestaurantsByCity()">
            <option value="all">جميع المدن</option>
            <?php foreach ($cities as $city): ?>
            <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div id="topRestaurants" class="row">
        <!-- سيتم تحميل المطاعم هنا ديناميكياً -->
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
        </div>
    </div>
    
    <!-- ميزات المنصة -->
    <h2 class="section-title mt-5">مميزات المنصة</h2>
    
    <div class="features row">
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3>تقييم المطاعم</h3>
                <p>شارك تجربتك وقيّم المطاعم التي تزورها لمساعدة الآخرين في اختيار الأفضل.</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3>كسب النقاط</h3>
                <p>احصل على نقاط قابلة للاستبدال مقابل كل مهمة تكملها وتقييم ترفعه.</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <h3>استبدال الهدايا</h3>
                <p>استبدل نقاطك بهدايا حقيقية من متجر النقاط مثل رصيد الجوال والكوبونات.</p>
            </div>
        </div>
    </div>
    
    <!-- إحصائيات المنصة -->
    <div class="stats-cards row mt-5">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3 id="totalUsers">0</h3>
                    <p>مستخدم نشط</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success-color), #0f8c66);">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalRestaurants">0</h3>
                    <p>مطعم مشارك</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info-color), #0c7bb3);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalReviews">0</h3>
                    <p>تقييم مقدم</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning-color), #d97706);">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalPoints">0</h3>
                    <p>نقطة مكتسبة</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل المطاعم عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadTopRestaurants();
    loadStatistics();
});

// تحميل أفضل المطاعم
async function loadTopRestaurants(city = 'all') {
    try {
        const response = await fetch(`/api/restaurants/top?city=${city}&limit=6`);
        const restaurants = await response.json();
        
        const container = document.getElementById('topRestaurants');
        if (!restaurants || restaurants.length === 0) {
            container.innerHTML = '<div class="col-12 text-center"><p>لا توجد مطاعم</p></div>';
            return;
        }
        
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
                        <p>${restaurant.city}</p>
                        
                        ${restaurant.description ? `
                        <p class="restaurant-description">${restaurant.description.substring(0, 100)}...</p>
                        ` : ''}
                        
                        <div class="task-actions">
                            <button class="btn btn-primary btn-sm" onclick="viewRestaurantDetails(${restaurant.id})">
                                <i class="fas fa-info-circle"></i> التفاصيل
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="openGoogleMaps('${restaurant.name}', '${restaurant.city}')">
                                <i class="fas fa-map-marker-alt"></i> الموقع
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading restaurants:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل المطاعم', 'error');
    }
}

// تصفية المطاعم حسب المدينة
function filterRestaurantsByCity() {
    const city = document.getElementById('citySelect').value;
    loadTopRestaurants(city);
}

// تحميل الإحصائيات
async function loadStatistics() {
    try {
        const response = await fetch('/api/statistics');
        const stats = await response.json();
        
        document.getElementById('totalUsers').textContent = stats.total_users.toLocaleString();
        document.getElementById('totalRestaurants').textContent = stats.total_restaurants.toLocaleString();
        document.getElementById('totalReviews').textContent = stats.total_reviews.toLocaleString();
        document.getElementById('totalPoints').textContent = stats.total_points.toLocaleString();
        
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// عرض تفاصيل المطعم
function viewRestaurantDetails(restaurantId) {
    window.location.href = `/restaurant/${restaurantId}`;
}

// فتح خرائط Google
function openGoogleMaps(restaurantName, city) {
    const query = encodeURIComponent(`${restaurantName} ${city}`);
    window.open(`https://www.google.com/maps/search/?api=1&query=${query}`, '_blank');
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