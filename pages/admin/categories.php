<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'إدارة التصنيفات';
$page_scripts = ['admin-categories.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// معالجة الطلبات
$action = $_GET['action'] ?? '';
$category_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'],
                'icon' => $_POST['icon'],
                'color' => $_POST['color'],
                'sort_order' => $_POST['sort_order'] ?? 0
            ];
            
            try {
                $category_id = $admin->createCategory($data);
                $success = 'تم إنشاء التصنيف بنجاح';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'edit':
            $data = [
                'name' => $_POST['name'],
                'icon' => $_POST['icon'],
                'color' => $_POST['color'],
                'sort_order' => $_POST['sort_order'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            if ($admin->updateCategory($category_id, $data)) {
                $success = 'تم تحديث التصنيف بنجاح';
            } else {
                $error = 'حدث خطأ أثناء تحديث التصنيف';
            }
            break;
            
        case 'delete':
            try {
                if ($admin->deleteCategory($category_id)) {
                    $success = 'تم حذف التصنيف بنجاح';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
    }
}

// الحصول على جميع التصنيفات
$categories = $admin->getCategories(false);
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-tags"></i> إدارة تصنيفات المطاعم</h2>
    
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
    
    <div class="row">
        <!-- قائمة التصنيفات -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">جميع التصنيفات</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus"></i> إضافة تصنيف
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>الأيقونة</th>
                                    <th>اللون</th>
                                    <th>الترتيب</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($categories) > 0): ?>
                                    <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $category['name']; ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $category['slug']; ?></small>
                                        </td>
                                        <td>
                                            <i class="<?php echo $category['icon'] ?: 'fas fa-tag'; ?> fa-lg"></i>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="color-preview me-2" style="background-color: <?php echo $category['color']; ?>;"></div>
                                                <span><?php echo $category['color']; ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $category['sort_order']; ?></td>
                                        <td>
                                            <?php if ($category['is_active']): ?>
                                            <span class="badge bg-success">نشط</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">غير نشط</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-warning" onclick="editCategory(<?php echo $category['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-tags fa-2x text-muted mb-3"></i>
                                            <p>لا توجد تصنيفات</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الإرشادات -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">إرشادات إضافة التصنيفات</h5>
                    <div class="guide-item mb-3">
                        <div class="guide-icon bg-primary">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="guide-content">
                            <h6>الأيقونات</h6>
                            <p>استخدم أيقونات Font Awesome. مثال: <code>fas fa-hamburger</code></p>
                        </div>
                    </div>
                    
                    <div class="guide-item mb-3">
                        <div class="guide-icon bg-success">
                            <i class="fas fa-palette"></i>
                        </div>
                        <div class="guide-content">
                            <h6>الألوان</h6>
                            <p>استخدم الألوان بتنسيق HEX. مثال: <code>#ff6b35</code></p>
                        </div>
                    </div>
                    
                    <div class="guide-item mb-3">
                        <div class="guide-icon bg-warning">
                            <i class="fas fa-sort"></i>
                        </div>
                        <div class="guide-content">
                            <h6>الترتيب</h6>
                            <p>التصنيفات ذات الرقم الأقل تظهر أولاً</p>
                        </div>
                    </div>
                    
                    <div class="guide-item">
                        <div class="guide-icon bg-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="guide-content">
                            <h6>الحذف</h6>
                            <p>لا يمكن حذف التصنيف إذا كان مرتبطاً بمطاعم</p>
                        </div>
                    </div>
                    
                    <!-- أيقونات متاحة -->
                    <div class="mt-4">
                        <h6>أيقونات مقترحة:</h6>
                        <div class="icons-grid">
                            <div class="icon-item" onclick="selectIcon('fas fa-hamburger')">
                                <i class="fas fa-hamburger"></i>
                            </div>
                            <div class="icon-item" onclick="selectIcon('fas fa-utensils')">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="icon-item" onclick="selectIcon('fas fa-pizza-slice')">
                                <i class="fas fa-pizza-slice"></i>
                            </div>
                            <div class="icon-item" onclick="selectIcon('fas fa-coffee')">
                                <i class="fas fa-coffee"></i>
                            </div>
                            <div class="icon-item" onclick="selectIcon('fas fa-fish')">
                                <i class="fas fa-fish"></i>
                            </div>
                            <div class="icon-item" onclick="selectIcon('fas fa-ice-cream')">
                                <i class="fas fa-ice-cream"></i>
                            </div>
                            <div class="icon-item" onclick="selectIcon('fas fa-cookie-bite')">
                                <i class="fas fa-cookie-bite"></i>
                            </div>
                            <div class="icon-item" onclick="selectIcon('fas fa-wine-glass-alt')">
                                <i class="fas fa-wine-glass-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال إضافة تصنيف -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة تصنيف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addCategoryForm">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="category_name" class="form-label">اسم التصنيف <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_icon" class="form-label">الأيقونة <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i id="iconPreview" class="fas fa-tag"></i>
                            </span>
                            <input type="text" class="form-control" id="category_icon" name="icon" value="fas fa-tag" required>
                        </div>
                        <small class="text-muted">استخدم أيقونات Font Awesome</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_color" class="form-label">اللون <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="category_color_picker" value="#ff6b35">
                            <input type="text" class="form-control" id="category_color" name="color" value="#ff6b35" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_order" class="form-label">ترتيب الظهور</label>
                        <input type="number" class="form-control" id="category_order" name="sort_order" value="0" min="0">
                        <small class="text-muted">التصنيفات ذات الرقم الأقل تظهر أولاً</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="addCategoryForm" class="btn btn-primary">إضافة التصنيف</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال تعديل التصنيف -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعديل التصنيف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editCategoryForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_category_id">
                    
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
                <button type="submit" form="editCategoryForm" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </div>
    </div>
</div>

<script>
// تحديث معاينة الأيقونة
document.getElementById('category_icon').addEventListener('input', function() {
    document.getElementById('iconPreview').className = this.value;
});

// تحديث معاينة اللون
document.getElementById('category_color_picker').addEventListener('input', function() {
    document.getElementById('category_color').value = this.value;
});

document.getElementById('category_color').addEventListener('input', function() {
    document.getElementById('category_color_picker').value = this.value;
});

// اختيار أيقونة من القائمة
function selectIcon(iconClass) {
    document.getElementById('category_icon').value = iconClass;
    document.getElementById('iconPreview').className = iconClass;
}

// تعديل تصنيف
async function editCategory(categoryId) {
    try {
        const response = await fetch(`/api/admin?action=get-category&id=${categoryId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const category = data.data;
            
            document.getElementById('edit_category_id').value = category.id;
            
            let html = `
                <div class="mb-3">
                    <label class="form-label">اسم التصنيف <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="${category.name}" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">الأيقونة <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i id="editIconPreview" class="${category.icon}"></i>
                        </span>
                        <input type="text" class="form-control" name="icon" value="${category.icon}" required oninput="document.getElementById('editIconPreview').className = this.value">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">اللون <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color" value="${category.color}" onchange="this.nextElementSibling.value = this.value">
                        <input type="text" class="form-control" name="color" value="${category.color}" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ترتيب الظهور</label>
                    <input type="number" class="form-control" name="sort_order" value="${category.sort_order}" min="0">
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" ${category.is_active ? 'checked' : ''}>
                        <label class="form-check-label" for="edit_is_active">
                            التصنيف نشط
                        </label>
                    </div>
                </div>
            `;
            
            document.querySelector('#editCategoryForm .modal-body').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Error loading category:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل بيانات التصنيف', 'error');
    }
}

// حذف تصنيف
function deleteCategory(categoryId, categoryName) {
    if (confirm(`هل أنت متأكد من حذف التصنيف "${categoryName}"؟`)) {
        window.location.href = `/admin/categories?action=delete&id=${categoryId}`;
    }
}
</script>

<style>
.color-preview {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.guide-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.guide-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    flex-shrink: 0;
}

.guide-content h6 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.guide-content p {
    margin-bottom: 0;
    color: var(--gray-color);
    font-size: 0.9rem;
}

.icons-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-top: 10px;
}

.icon-item {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--light-color);
    color: var(--dark-color);
    cursor: pointer;
    transition: var(--transition);
    border: 1px solid var(--border-color);
}

.icon-item:hover {
    background-color: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}
</style>

<?php
include 'includes/footer.php';
?>