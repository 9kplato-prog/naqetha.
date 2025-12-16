<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'إدارة المطاعم';
$page_scripts = ['admin-restaurants.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// الحصول على التصنيفات
$categories = $admin->getCategories(true);

// معالجة الطلبات
$action = $_GET['action'] ?? '';
$restaurant_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'city' => $_POST['city'],
                'address' => $_POST['address'],
                'phone' => $_POST['phone'],
                'email' => $_POST['email'],
                'category_id' => $_POST['category_id'],
                'owner_id' => $_POST['owner_id'] ?? null,
                'status' => $_POST['status'] ?? 'pending'
            ];
            
            $restaurant = new Restaurant($db->getConnection());
            try {
                if ($restaurant->create($data)) {
                    $success = 'تم إنشاء المطعم بنجاح';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'edit':
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'city' => $_POST['city'],
                'address' => $_POST['address'],
                'phone' => $_POST['phone'],
                'email' => $_POST['email'],
                'category_id' => $_POST['category_id'],
                'status' => $_POST['status']
            ];
            
            $restaurant = new Restaurant($db->getConnection());
            if ($restaurant->getById($restaurant_id)) {
                if ($restaurant->update($data)) {
                    $success = 'تم تحديث المطعم بنجاح';
                } else {
                    $error = 'حدث خطأ أثناء تحديث المطعم';
                }
            } else {
                $error = 'المطعم غير موجود';
            }
            break;
            
        case 'delete':
            if ($admin->manageRestaurant($restaurant_id, 'delete')) {
                $success = 'تم حذف المطعم بنجاح';
            } else {
                $error = 'حدث خطأ أثناء حذف المطعم';
            }
            break;
    }
}

// الحصول على قائمة المطاعم
$filters = [
    'status' => $_GET['status'] ?? '',
    'city' => $_GET['city'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$restaurants = $admin->getRestaurants($filters, 20, 0);
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-utensils"></i> إدارة المطاعم</h2>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <!-- فلترة المطاعم -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="active" <?php echo ($filters['status'] === 'active') ? 'selected' : ''; ?>>نشط</option>
                        <option value="pending" <?php echo ($filters['status'] === 'pending') ? 'selected' : ''; ?>>معلق</option>
                        <option value="suspended" <?php echo ($filters['status'] === 'suspended') ? 'selected' : ''; ?>>موقوف</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">المدينة</label>
                    <select name="city" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع المدن</option>
                        <?php foreach ($cities as $city): ?>
                        <option value="<?php echo $city; ?>" <?php echo ($filters['city'] === $city) ? 'selected' : ''; ?>>
                            <?php echo $city; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">التصنيف</label>
                    <select name="category_id" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع التصنيفات</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($filters['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo $category['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">بحث</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ابحث..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- زر إضافة مطعم جديد -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>قائمة المطاعم</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRestaurantModal">
            <i class="fas fa-plus"></i> إضافة مطعم جديد
        </button>
    </div>
    
    <!-- جدول المطاعم -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>اسم المطعم</th>
                            <th>المدينة</th>
                            <th>التصنيف</th>
                            <th>المالك</th>
                            <th>التقييم</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($restaurants) > 0): ?>
                            <?php foreach ($restaurants as $restaurant): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($restaurant['logo']): ?>
                                        <img src="/uploads/images/<?php echo $restaurant['logo']; ?>" class="restaurant-logo-sm me-2" alt="<?php echo $restaurant['name']; ?>">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo $restaurant['name']; ?></strong>
                                            <?php if ($restaurant['is_featured']): ?>
                                            <span class="badge bg-warning ms-2">مميز</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $restaurant['city']; ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo $restaurant['category_color'] ?? '#ff6b35'; ?>">
                                        <?php echo $restaurant['category_name'] ?? 'غير مصنف'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($restaurant['owner_name']): ?>
                                    <div><?php echo $restaurant['owner_name']; ?></div>
                                    <small class="text-muted"><?php echo $restaurant['owner_email']; ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">غير محدد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rating-stars me-2">
                                            <?php echo getRatingStars($restaurant['rating']); ?>
                                        </div>
                                        <span><?php echo number_format($restaurant['rating'], 1); ?></span>
                                    </div>
                                    <small class="text-muted">(<?php echo $restaurant['total_reviews']; ?> تقييم)</small>
                                </td>
                                <td>
                                    <?php
                                    $status_badge = [
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'suspended' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_badge[$restaurant['status']] ?? 'secondary'; ?>">
                                        <?php
                                        $status_names = [
                                            'active' => 'نشط',
                                            'pending' => 'معلق',
                                            'suspended' => 'موقوف'
                                        ];
                                        echo $status_names[$restaurant['status']] ?? $restaurant['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/restaurant/<?php echo $restaurant['slug']; ?>" class="btn btn-info" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-warning" onclick="editRestaurant(<?php echo $restaurant['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-primary" onclick="managePermissions(<?php echo $restaurant['id']; ?>)">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteRestaurant(<?php echo $restaurant['id']; ?>, '<?php echo addslashes($restaurant['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-utensils fa-2x text-muted mb-3"></i>
                                    <p>لا توجد مطاعم</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- مودال إضافة مطعم جديد -->
<div class="modal fade" id="addRestaurantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة مطعم جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addRestaurantForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">اسم المطعم <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">التصنيف <span class="text-danger">*</span></label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">اختر التصنيف</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">المدينة <span class="text-danger">*</span></label>
                            <select class="form-control" id="city" name="city" required>
                                <option value="">اختر المدينة</option>
                                <?php foreach ($cities as $city): ?>
                                <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">العنوان</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">وصف المطعم</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">الحالة</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active">نشط</option>
                            <option value="pending" selected>معلق</option>
                            <option value="suspended">موقوف</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="owner_id" class="form-label">ربط بحساب مالك (اختياري)</label>
                        <select class="form-control" id="owner_id" name="owner_id">
                            <option value="">اختر المالك</option>
                            <!-- سيتم تحميل قائمة أصحاب المطاعم هنا ديناميكياً -->
                        </select>
                        <small class="text-muted">اتركه فارغاً إذا لم يكن هناك مالك محدد</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="addRestaurantForm" class="btn btn-primary">إضافة المطعم</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال تعديل المطعم -->
<div class="modal fade" id="editRestaurantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعديل المطعم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editRestaurantForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_restaurant_id">
                    
                    <!-- سيتم تحميل النموذج هنا ديناميكياً -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="editRestaurantForm" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال إدارة الصلاحيات -->
<div class="modal fade" id="permissionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إدارة صلاحيات المطعم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="permissionsForm">
                    <input type="hidden" id="permissions_restaurant_id">
                    
                    <!-- سيتم تحميل النموذج هنا ديناميكياً -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="savePermissions()">حفظ الصلاحيات</button>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل أصحاب المطاعم عند فتح مودال الإضافة
document.getElementById('addRestaurantModal').addEventListener('show.bs.modal', function() {
    loadRestaurantOwners();
});

// تحميل قائمة أصحاب المطاعم
async function loadRestaurantOwners() {
    try {
        const response = await fetch('/api/admin?action=get-restaurant-owners');
        const data = await response.json();
        
        if (data.status === 'success') {
            const select = document.getElementById('owner_id');
            select.innerHTML = '<option value="">اختر المالك</option>';
            
            data.data.forEach(owner => {
                const option = document.createElement('option');
                option.value = owner.id;
                option.textContent = `${owner.name} (${owner.email})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading owners:', error);
    }
}

// تعديل مطعم
async function editRestaurant(restaurantId) {
    try {
        const response = await fetch(`/api/admin?action=get-restaurant&id=${restaurantId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const restaurant = data.data;
            
            document.getElementById('edit_restaurant_id').value = restaurant.id;
            
            let html = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">اسم المطعم <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="${restaurant.name}" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">التصنيف <span class="text-danger">*</span></label>
                        <select class="form-control" name="category_id" required>
                            <option value="">اختر التصنيف</option>
            `;
            
            <?php foreach ($categories as $category): ?>
            html += `<option value="<?php echo $category['id']; ?>" ${restaurant.category_id == <?php echo $category['id']; ?> ? 'selected' : ''}><?php echo $category['name']; ?></option>`;
            <?php endforeach; ?>
            
            html += `
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">المدينة <span class="text-danger">*</span></label>
                        <select class="form-control" name="city" required>
                            <option value="">اختر المدينة</option>
            `;
            
            <?php foreach ($cities as $city): ?>
            html += `<option value="<?php echo $city; ?>" ${restaurant.city == '<?php echo $city; ?>' ? 'selected' : ''}><?php echo $city; ?></option>`;
            <?php endforeach; ?>
            
            html += `
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="tel" class="form-control" name="phone" value="${restaurant.phone || ''}">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">العنوان</label>
                    <textarea class="form-control" name="address" rows="2">${restaurant.address || ''}</textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-control" name="email" value="${restaurant.email || ''}">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">وصف المطعم</label>
                    <textarea class="form-control" name="description" rows="3">${restaurant.description || ''}</textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">الحالة</label>
                    <select class="form-control" name="status">
                        <option value="active" ${restaurant.status == 'active' ? 'selected' : ''}>نشط</option>
                        <option value="pending" ${restaurant.status == 'pending' ? 'selected' : ''}>معلق</option>
                        <option value="suspended" ${restaurant.status == 'suspended' ? 'selected' : ''}>موقوف</option>
                    </select>
                </div>
            `;
            
            document.querySelector('#editRestaurantForm .modal-body').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('editRestaurantModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading restaurant:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل بيانات المطعم', 'error');
    }
}

// إدارة صلاحيات المطعم
async function managePermissions(restaurantId) {
    try {
        const response = await fetch(`/api/admin?action=get-restaurant-permissions&restaurant_id=${restaurantId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const permissions = data.data;
            
            document.getElementById('permissions_restaurant_id').value = restaurantId;
            
            let html = `
                <div class="mb-3">
                    <label class="form-label">الحد الأقصى للخصم (%)</label>
                    <input type="number" class="form-control" id="max_discount" min="10" max="50" value="${permissions.max_discount || 30}">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">الحد الأقصى للمهام النشطة</label>
                    <input type="number" class="form-control" id="max_tasks" min="1" max="50" value="${permissions.max_tasks || 10}">
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="can_feature" ${permissions.can_feature ? 'checked' : ''}>
                        <label class="form-check-label" for="can_feature">
                            يمكن تمييز المطعم في الصفحة الرئيسية
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="can_priority" ${permissions.can_priority ? 'checked' : ''}>
                        <label class="form-check-label" for="can_priority">
                            أولوية في ظهور المطعم في النتائج
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">إعدادات مخصصة (JSON)</label>
                    <textarea class="form-control" id="custom_settings" rows="3" placeholder='{"setting": "value"}'>${permissions.custom_settings || ''}</textarea>
                </div>
            `;
            
            document.querySelector('#permissionsForm .modal-body').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('permissionsModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading permissions:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل الصلاحيات', 'error');
    }
}

// حفظ الصلاحيات
async function savePermissions() {
    const restaurantId = document.getElementById('permissions_restaurant_id').value;
    
    const data = {
        max_discount: document.getElementById('max_discount').value,
        max_tasks: document.getElementById('max_tasks').value,
        can_feature: document.getElementById('can_feature').checked,
        can_priority: document.getElementById('can_priority').checked,
        custom_settings: document.getElementById('custom_settings').value
    };
    
    try {
        const response = await fetch('/api/admin?action=update-restaurant-permissions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                restaurant_id: restaurantId,
                ...data
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification('نجاح', 'تم حفظ الصلاحيات بنجاح');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('permissionsModal'));
            modal.hide();
            
            // تحديث الصفحة
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification('خطأ', result.message, 'error');
        }
    } catch (error) {
        console.error('Error saving permissions:', error);
        showNotification('خطأ', 'حدث خطأ أثناء حفظ الصلاحيات', 'error');
    }
}

// حذف مطعم
function deleteRestaurant(restaurantId, restaurantName) {
    if (confirm(`هل أنت متأكد من حذف المطعم "${restaurantName}"؟`)) {
        window.location.href = `/admin/restaurants?action=delete&id=${restaurantId}`;
    }
}
</script>

<style>
.restaurant-logo-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.table th {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--dark-color);
    font-weight: 600;
}

.table-hover tbody tr:hover {
    background-color: rgba(var(--primary-color-rgb), 0.05);
}

.btn-group .btn {
    border-radius: var(--border-radius) !important;
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>

<?php
include 'includes/footer.php';
?>