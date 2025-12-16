<?php
// التحقق من صلاحيات صاحب المطعم
if (!isset($user) || $user->getRole() !== 'restaurant_owner') {
    header('Location: /login');
    exit;
}

$page_title = 'حسابي - صاحب المطعم';
$page_scripts = ['owner-profile.js'];

include 'includes/header.php';

// الحصول على مطعم المستخدم
$sql = "SELECT r.*, c.name as category_name, c.color as category_color,
               rp.max_discount, rp.max_tasks, rp.can_feature, rp.can_priority
        FROM restaurants r 
        LEFT JOIN categories c ON r.category_id = c.id 
        LEFT JOIN restaurant_permissions rp ON r.id = rp.restaurant_id 
        WHERE r.owner_id = ?";
$stmt = $db->getConnection()->prepare($sql);
$stmt->bind_param("i", $user->getId());
$stmt->execute();
$result = $stmt->get_result();
$restaurant = $result->fetch_assoc();

// تحديث الملف الشخصي
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $data = [
            'name' => $_POST['name'],
            'phone' => $_POST['phone'],
            'city' => $_POST['city'],
            'birthdate' => $_POST['birthdate'],
            'bank_name' => $_POST['bank_name'],
            'bank_account_name' => $_POST['bank_account_name'],
            'iban' => $_POST['iban']
        ];
        
        if ($user->updateProfile($data)) {
            $success = 'تم تحديث الملف الشخصي بنجاح';
        } else {
            $error = 'حدث خطأ أثناء تحديث الملف الشخصي';
        }
    }
    
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = 'كلمات المرور غير متطابقة';
        } else {
            try {
                if ($user->updatePassword($current_password, $new_password)) {
                    $success = 'تم تحديث كلمة المرور بنجاح';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['update_avatar']) && isset($_FILES['avatar'])) {
        if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            try {
                $filename = uploadFile($_FILES['avatar'], 'image');
                if ($user->updateAvatar($filename)) {
                    $success = 'تم تحديث صورة الملف الشخصي بنجاح';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-user"></i> حسابي</h2>
    
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
        <div class="col-lg-4">
            <!-- بطاقة الملف الشخصي -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <!-- صورة الملف الشخصي -->
                    <div class="avatar-upload mb-3">
                        <div class="avatar-preview">
                            <?php if ($user->getAvatar() && $user->getAvatar() !== 'default.png'): ?>
                            <img src="/uploads/images/<?php echo $user->getAvatar(); ?>" 
                                 class="img-fluid rounded-circle" 
                                 alt="<?php echo $user->getName(); ?>"
                                 id="avatarPreview">
                            <?php else: ?>
                            <div class="avatar-placeholder" id="avatarPreview">
                                <?php echo mb_substr($user->getName(), 0, 1, 'UTF-8'); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="mt-3">
                            <div class="input-group">
                                <input type="file" class="form-control" name="avatar" id="avatarInput" 
                                       accept="image/*" onchange="previewAvatar(event)">
                                <button type="submit" name="update_avatar" class="btn btn-primary">
                                    <i class="fas fa-upload"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- معلومات المستخدم -->
                    <h4><?php echo $user->getName(); ?></h4>
                    <p class="text-muted">صاحب مطعم</p>
                    
                    <div class="user-info mt-4">
                        <div class="info-item mb-3">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo $user->getEmail(); ?></span>
                        </div>
                        
                        <div class="info-item mb-3">
                            <i class="fas fa-phone"></i>
                            <span><?php echo $user->getPhone(); ?></span>
                        </div>
                        
                        <div class="info-item mb-3">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo $user->getCity(); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date('Y-m-d', strtotime($user->getCreatedAt())); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- معلومات المطعم -->
            <?php if ($restaurant): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">معلومات المطعم</h5>
                    
                    <div class="restaurant-info">
                        <h6><?php echo $restaurant['name']; ?></h6>
                        
                        <div class="restaurant-details mt-3">
                            <div class="detail-item mb-2">
                                <strong>التصنيف:</strong>
                                <span class="badge" style="background-color: <?php echo $restaurant['category_color']; ?>">
                                    <?php echo $restaurant['category_name']; ?>
                                </span>
                            </div>
                            
                            <div class="detail-item mb-2">
                                <strong>المدينة:</strong>
                                <span><?php echo $restaurant['city']; ?></span>
                            </div>
                            
                            <div class="detail-item mb-2">
                                <strong>التقييم:</strong>
                                <span>
                                    <?php echo number_format($restaurant['rating'], 1); ?> 
                                    (<?php echo $restaurant['total_reviews']; ?> تقييم)
                                </span>
                            </div>
                            
                            <div class="detail-item mb-2">
                                <strong>الحالة:</strong>
                                <span class="badge bg-<?php 
                                    echo $restaurant['status'] === 'active' ? 'success' : 
                                          ($restaurant['status'] === 'pending' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo $restaurant['status'] === 'active' ? 'نشط' : 
                                           ($restaurant['status'] === 'pending' ? 'معلق' : 'موقوف'); ?>
                                </span>
                            </div>
                            
                            <?php if ($restaurant['is_featured']): ?>
                            <div class="detail-item mb-2">
                                <span class="badge bg-warning">مطعم مميز</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4">
                            <a href="/restaurant/<?php echo $restaurant['slug']; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-eye"></i> عرض صفحة المطعم
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-8">
            <!-- تحديث الملف الشخصي -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">تحديث المعلومات الشخصية</h5>
                    
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user->getName()); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user->getPhone()); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">المدينة <span class="text-danger">*</span></label>
                                <select class="form-control" id="city" name="city" required>
                                    <option value="">اختر المدينة</option>
                                    <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo $city; ?>" <?php echo $user->getCity() === $city ? 'selected' : ''; ?>>
                                        <?php echo $city; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="birthdate" class="form-label">تاريخ الميلاد</label>
                                <input type="date" class="form-control" id="birthdate" name="birthdate" 
                                       value="<?php echo $user->getBirthdate(); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="bank_name" class="form-label">اسم البنك</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                       value="<?php echo htmlspecialchars($user->bank_name ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="bank_account_name" class="form-label">اسم صاحب الحساب</label>
                                <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" 
                                       value="<?php echo htmlspecialchars($user->bank_account_name ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="iban" class="form-label">رقم الآيبان (IBAN)</label>
                            <input type="text" class="form-control" id="iban" name="iban" 
                                   value="<?php echo htmlspecialchars($user->iban ?? ''); ?>">
                            <small class="text-muted">يستخدم لتحويل المكافآت والمستحقات</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- تغيير كلمة المرور -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">تغيير كلمة المرور</h5>
                    
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">كلمة المرور الحالية <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">كلمة المرور الجديدة <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">يجب أن تحتوي على 8 أحرف على الأقل</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> تغيير كلمة المرور
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- صلاحيات المطعم -->
            <?php if ($restaurant && isset($restaurant['max_discount'])): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">صلاحيات مطعمك</h5>
                    
                    <div class="permissions-grid">
                        <div class="permission-item text-center p-3">
                            <div class="permission-icon bg-primary">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <h4><?php echo $restaurant['max_discount']; ?>%</h4>
                            <p>الحد الأقصى للخصم</p>
                        </div>
                        
                        <div class="permission-item text-center p-3">
                            <div class="permission-icon bg-success">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h4><?php echo $restaurant['max_tasks']; ?></h4>
                            <p>الحد الأقصى للمهام</p>
                        </div>
                        
                        <div class="permission-item text-center p-3">
                            <div class="permission-icon bg-warning">
                                <i class="fas fa-crown"></i>
                            </div>
                            <h4>
                                <?php echo $restaurant['can_feature'] ? 'مفعل' : 'غير مفعل'; ?>
                            </h4>
                            <p>تمييز المطعم</p>
                        </div>
                        
                        <div class="permission-item text-center p-3">
                            <div class="permission-icon bg-info">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <h4>
                                <?php echo $restaurant['can_priority'] ? 'مفعل' : 'غير مفعل'; ?>
                            </h4>
                            <p>أولوية الظهور</p>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="/owner/subscription" class="btn btn-outline-primary">
                            <i class="fas fa-crown"></i> ترقية صلاحيات المطعم
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// معاينة الصورة قبل الرفع
function previewAvatar(event) {
    const input = event.target;
    const preview = document.getElementById('avatarPreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (preview.classList.contains('avatar-placeholder')) {
                preview.classList.remove('avatar-placeholder');
                preview.outerHTML = `<img src="${e.target.result}" class="img-fluid rounded-circle" id="avatarPreview" alt="Preview">`;
            } else {
                preview.src = e.target.result;
            }
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// إظهار/إخفاء كلمة المرور
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        button.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        button.className = 'fas fa-eye';
    }
}

// التحقق من قوة كلمة المرور
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strength = checkPasswordStrength(password);
    const strengthBar = document.getElementById('passwordStrength');
    
    if (!strengthBar) {
        const bar = document.createElement('div');
        bar.id = 'passwordStrength';
        bar.className = 'password-strength mt-2';
        this.parentNode.parentNode.appendChild(bar);
    }
    
    updatePasswordStrength(strength);
});

function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    return strength;
}

function updatePasswordStrength(strength) {
    const bar = document.getElementById('passwordStrength');
    const colors = ['danger', 'warning', 'info', 'primary', 'success'];
    const labels = ['ضعيفة جداً', 'ضعيفة', 'متوسطة', 'قوية', 'قوية جداً'];
    
    bar.innerHTML = `
        <div class="progress" style="height: 5px;">
            <div class="progress-bar bg-${colors[strength - 1] || 'danger'}" 
                 style="width: ${strength * 20}%"></div>
        </div>
        <small class="text-${colors[strength - 1] || 'danger'}">${labels[strength - 1] || 'ضعيفة جداً'}</small>
    `;
}
</script>

<style>
.avatar-upload {
    position: relative;
}

.avatar-preview {
    width: 150px;
    height: 150px;
    margin: 0 auto 20px;
}

.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border: 3px solid var(--primary-color);
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    font-weight: bold;
    border: 3px solid var(--primary-color);
}

.info-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-item i {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.restaurant-info h6 {
    color: var(--primary-color);
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
}

.detail-item:last-child {
    border-bottom: none;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.permission-item {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--card-bg);
}

.permission-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    color: white;
    font-size: 1.5rem;
}

.permission-item h4 {
    margin-bottom: 5px;
    color: var(--dark-color);
}

.permission-item p {
    margin: 0;
    color: var(--gray-color);
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
include 'includes/footer.php';
?>