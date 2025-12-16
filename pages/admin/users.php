<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'إدارة المستخدمين';
$page_scripts = ['admin-users.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// معالجة الطلبات
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update-role':
            $new_role = $_POST['role'] ?? '';
            
            try {
                if ($admin->updateUserRole($user_id, $new_role)) {
                    $success = 'تم تحديث دور المستخدم بنجاح';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'update-status':
            $new_status = $_POST['status'] ?? '';
            
            try {
                if ($admin->updateUserStatus($user_id, $new_status)) {
                    $success = 'تم تحديث حالة المستخدم بنجاح';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'reset-password':
            $new_password = bin2hex(random_bytes(8)); // كلمة مرور عشوائية
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                // إرسال كلمة المرور الجديدة للمستخدم (في الإنتاج، سيتم إرسالها بالبريد)
                $password_info = "تم تعيين كلمة مرور جديدة: $new_password";
                $success = 'تم إعادة تعيين كلمة المرور بنجاح';
            } else {
                $error = 'حدث خطأ أثناء إعادة تعيين كلمة المرور';
            }
            break;
            
        case 'delete':
            // لا يمكن حذف المستخدم الرئيسي
            if ($user_id == $user->getId()) {
                $error = 'لا يمكن حذف حساب الأدمن الرئيسي';
                break;
            }
            
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = 'تم حذف المستخدم بنجاح';
            } else {
                $error = 'حدث خطأ أثناء حذف المستخدم';
            }
            break;
    }
}

// الحصول على قائمة المستخدمين
$filters = [
    'role' => $_GET['role'] ?? '',
    'status' => $_GET['status'] ?? '',
    'city' => $_GET['city'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$users = $admin->getUsers($filters, 20, 0);

// أدوار المستخدمين
$roles = [
    'admin' => 'أدمن',
    'moderator' => 'مشرف',
    'restaurant_owner' => 'صاحب مطعم',
    'member' => 'عضو'
];

// حالات المستخدمين
$statuses = [
    'active' => 'نشط',
    'suspended' => 'موقوف',
    'pending' => 'معلق'
];
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-users-cog"></i> إدارة المستخدمين</h2>
    
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
    
    <!-- فلترة المستخدمين -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الدور</label>
                    <select name="role" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع الأدوار</option>
                        <?php foreach ($roles as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($filters['role'] === $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($filters['status'] === $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
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
    
    <!-- جدول المستخدمين -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>المستخدم</th>
                            <th>الدور</th>
                            <th>المدينة</th>
                            <th>النقاط</th>
                            <th>الحالة</th>
                            <th>تاريخ التسجيل</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user_item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?php echo mb_substr($user_item['name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo $user_item['name']; ?></strong>
                                            <small class="d-block text-muted"><?php echo $user_item['email']; ?></small>
                                            <small class="d-block text-muted"><?php echo $user_item['phone']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user_item['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                        <?php echo $roles[$user_item['role']] ?? $user_item['role']; ?>
                                    </span>
                                </td>
                                <td><?php echo $user_item['city']; ?></td>
                                <td>
                                    <span class="badge bg-warning">
                                        <?php echo number_format($user_item['points']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_badge = [
                                        'active' => 'success',
                                        'suspended' => 'danger',
                                        'pending' => 'warning'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_badge[$user_item['status']] ?? 'secondary'; ?>">
                                        <?php echo $statuses[$user_item['status']] ?? $user_item['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('Y-m-d', strtotime($user_item['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editRoleModal" 
                                                   onclick="setEditRole(<?php echo $user_item['id']; ?>, '<?php echo $user_item['role']; ?>', '<?php echo $user_item['name']; ?>')">
                                                    <i class="fas fa-user-tag"></i> تغيير الدور
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editStatusModal" 
                                                   onclick="setEditStatus(<?php echo $user_item['id']; ?>, '<?php echo $user_item['status']; ?>', '<?php echo $user_item['name']; ?>')">
                                                    <i class="fas fa-user-check"></i> تغيير الحالة
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-warning" href="#" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" 
                                                   onclick="setResetPassword(<?php echo $user_item['id']; ?>, '<?php echo $user_item['name']; ?>')">
                                                    <i class="fas fa-key"></i> إعادة تعيين كلمة المرور
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-info" href="/admin/user-details?id=<?php echo $user_item['id']; ?>">
                                                    <i class="fas fa-eye"></i> التفاصيل
                                                </a>
                                            </li>
                                            <?php if ($user_item['id'] != $user->getId()): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" 
                                                   onclick="confirmDelete(<?php echo $user_item['id']; ?>, '<?php echo addslashes($user_item['name']); ?>')">
                                                    <i class="fas fa-trash"></i> حذف
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                    <p>لا يوجد مستخدمون</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- الترقيم -->
            <?php if (count($users) > 0): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <small class="text-muted">عرض <?php echo count($users); ?> مستخدم</small>
                </div>
                <nav>
                    <ul class="pagination pagination-sm">
                        <li class="page-item disabled">
                            <a class="page-link" href="#">السابق</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">2</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">التالي</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- مودال تغيير الدور -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تغيير دور المستخدم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editRoleForm">
                    <input type="hidden" name="action" value="update-role">
                    <input type="hidden" id="edit_role_user_id" name="id">
                    
                    <div class="mb-3">
                        <p>المستخدم: <strong id="edit_role_user_name"></strong></p>
                        <label for="edit_role" class="form-label">الدور الجديد</label>
                        <select class="form-control" id="edit_role" name="role" required>
                            <?php foreach ($roles as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <small>سيتم منح الصلاحيات المناسبة للمستخدم بناءً على الدور الجديد</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="editRoleForm" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال تغيير الحالة -->
<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تغيير حالة المستخدم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editStatusForm">
                    <input type="hidden" name="action" value="update-status">
                    <input type="hidden" id="edit_status_user_id" name="id">
                    
                    <div class="mb-3">
                        <p>المستخدم: <strong id="edit_status_user_name"></strong></p>
                        <label for="edit_status" class="form-label">الحالة الجديدة</label>
                        <select class="form-control" id="edit_status" name="status" required>
                            <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <small>تعليق حساب المستخدم سيؤدي إلى منعه من الوصول للنظام</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="editStatusForm" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </div>
    </div>
</div>

<!-- مودال إعادة تعيين كلمة المرور -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إعادة تعيين كلمة المرور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="resetPasswordForm">
                    <input type="hidden" name="action" value="reset-password">
                    <input type="hidden" id="reset_password_user_id" name="id">
                    
                    <div class="mb-3">
                        <p>المستخدم: <strong id="reset_password_user_name"></strong></p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>تنبيه:</strong> سيتم إنشاء كلمة مرور عشوائية جديدة وإرسالها للمستخدم
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="send_email" checked>
                            <label class="form-check-label" for="send_email">
                                إرسال كلمة المرور الجديدة إلى بريد المستخدم
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="resetPasswordForm" class="btn btn-warning">إعادة التعيين</button>
            </div>
        </div>
    </div>
</div>

<script>
function setEditRole(userId, currentRole, userName) {
    document.getElementById('edit_role_user_id').value = userId;
    document.getElementById('edit_role_user_name').textContent = userName;
    document.getElementById('edit_role').value = currentRole;
}

function setEditStatus(userId, currentStatus, userName) {
    document.getElementById('edit_status_user_id').value = userId;
    document.getElementById('edit_status_user_name').textContent = userName;
    document.getElementById('edit_status').value = currentStatus;
}

function setResetPassword(userId, userName) {
    document.getElementById('reset_password_user_id').value = userId;
    document.getElementById('reset_password_user_name').textContent = userName;
}

function confirmDelete(userId, userName) {
    if (confirm(`هل أنت متأكد من حذف المستخدم "${userName}"؟`)) {
        window.location.href = `/admin/users?action=delete&id=${userId}`;
    }
}

// البحث الفوري
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.closest('form').submit();
    }, 500);
});
</script>

<style>
.user-avatar {
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

.table th {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    font-weight: 600;
}

.dropdown-menu {
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-color);
}

.dropdown-item:active {
    background-color: var(--primary-color);
}

.pagination .page-item.active .page-link {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.pagination .page-link {
    color: var(--primary-color);
}

.pagination .page-link:hover {
    background-color: var(--light-color);
}
</style>

<?php
include 'includes/footer.php';
?>