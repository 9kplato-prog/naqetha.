<?php
// التحقق من صلاحيات العضو
if (!isset($user) || !in_array($user->getRole(), ['member', 'moderator'])) {
    header('Location: /login');
    exit;
}

$page_title = 'الملف الشخصي';
$page_scripts = ['profile.js'];

include 'includes/header.php';

// معالجة تحديث الملف الشخصي
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $data = [
            'name' => $_POST['name'],
            'phone' => $_POST['phone'],
            'city' => $_POST['city'],
            'birthdate' => $_POST['birthdate'],
            'bank_name' => $_POST['bank_name'] ?? '',
            'bank_account_name' => $_POST['bank_account_name'] ?? '',
            'iban' => $_POST['iban'] ?? ''
        ];
        
        if ($user->updateProfile($data)) {
            $success = 'تم تحديث الملف الشخصي بنجاح';
        } else {
            $error = 'حدث خطأ أثناء تحديث الملف الشخصي';
        }
    } elseif ($action === 'update_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = 'كلمة المرور الجديدة غير متطابقة';
        } else {
            try {
                if ($user->updatePassword($current_password, $new_password)) {
                    $success = 'تم تحديث كلمة المرور بنجاح';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
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

// الحصول على إحصائيات العضو
$stats = $user->getStatistics();
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-user"></i> الملف الشخصي</h2>
    
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
        <!-- معلومات الملف الشخصي -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <!-- صورة الملف الشخصي -->
                    <div class="profile-avatar mb-3">
                        <div class="avatar-container">
                            <div class="avatar-img">
                                <?php if ($user->getAvatar() && $user->getAvatar() !== 'default.png'): ?>
                                <img src="/uploads/images/<?php echo $user->getAvatar(); ?>" 
                                     alt="<?php echo $user->getName(); ?>"
                                     class="rounded-circle">
                                <?php else: ?>
                                <div class="avatar-initials">
                                    <?php echo mb_substr($user->getName(), 0, 1, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-primary avatar-edit" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                    
                    <h4><?php echo $user->getName(); ?></h4>
                    <p class="text-muted mb-4">
                        <i class="fas fa-envelope"></i> <?php echo $user->getEmail(); ?><br>
                        <i class="fas fa-map-marker-alt"></i> <?php echo $user->getCity(); ?>
                    </p>
                    
                    <!-- نقاط العضو -->
                    <div class="profile-points mb-4">
                        <div class="points-display">
                            <i class="fas fa-coins fa-2x text-warning"></i>
                            <h3><?php echo formatPoints($user->getPoints()); ?></h3>
                            <p>نقطة</p>
                        </div>
                    </div>
                    
                    <!-- الإحصائيات -->
                    <div class="profile-stats">
                        <div class="row">
                            <div class="col-6">
                                <div class="stat-item">
                                    <h5><?php echo $stats['completed_tasks']; ?></h5>
                                    <p>مهام مكتملة</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item">
                                    <h5><?php echo $stats['total_reviews']; ?></h5>
                                    <p>تقييمات</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- معلومات الحساب -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">معلومات الحساب</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>رقم العضو:</span>
                            <strong>#<?php echo str_pad($user->getId(), 6, '0', STR_PAD_LEFT); ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>تاريخ الانضمام:</span>
                            <span><?php echo date('Y-m-d', strtotime($user->getCreatedAt())); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>آخر تسجيل دخول:</span>
                            <span><?php echo $user->getLastLogin() ? date('Y-m-d H:i', strtotime($user->getLastLogin())) : 'لم يسجل دخول بعد'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>حالة الحساب:</span>
                            <span class="badge bg-success">نشط</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- محتوى الملف الشخصي -->
        <div class="col-lg-8">
            <!-- التبويبات -->
            <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button">
                        <i class="fas fa-user-edit"></i> المعلومات الشخصية
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button">
                        <i class="fas fa-key"></i> كلمة المرور
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank" type="button">
                        <i class="fas fa-university"></i> المعلومات البنكية
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                        <i class="fas fa-star"></i> تقييماتي
                    </button>
                </li>
            </ul>
            
            <!-- محتويات التبويبات -->
            <div class="tab-content" id="profileTabContent">
                <!-- تبويب المعلومات الشخصية -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">تعديل المعلومات الشخصية</h5>
                            
                            <form method="POST" id="personalForm">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user->getName()); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">البريد الإلكتروني</label>
                                        <input type="email" class="form-control" id="email" 
                                               value="<?php echo $user->getEmail(); ?>" disabled>
                                        <small class="text-muted">لا يمكن تغيير البريد الإلكتروني</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user->getPhone()); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">المدينة <span class="text-danger">*</span></label>
                                        <select class="form-control" id="city" name="city" required>
                                            <option value="">اختر المدينة</option>
                                            <?php foreach ($cities as $city): ?>
                                            <option value="<?php echo $city; ?>" <?php echo $user->getCity() == $city ? 'selected' : ''; ?>>
                                                <?php echo $city; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="birthdate" class="form-label">تاريخ الميلاد</label>
                                        <input type="date" class="form-control" id="birthdate" name="birthdate" 
                                               value="<?php echo $user->getBirthdate(); ?>">
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <small>سيتم استخدام هذه المعلومات لتحسين تجربتك في المنصة</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> حفظ التغييرات
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب كلمة المرور -->
                <div class="tab-pane fade" id="password" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">تغيير كلمة المرور</h5>
                            
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="update_password">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">كلمة المرور الحالية <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" 
                                                data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">كلمة المرور الجديدة <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" 
                                                data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength mt-2">
                                        <div class="strength-bar"></div>
                                        <small class="text-muted">يجب أن تحتوي على 8 أحرف على الأقل</small>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" 
                                                data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <small>تأكد من حفظ كلمة المرور الجديدة في مكان آمن</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> تغيير كلمة المرور
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب المعلومات البنكية -->
                <div class="tab-pane fade" id="bank" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">المعلومات البنكية للسحب</h5>
                            <p class="text-muted mb-4">تستخدم هذه المعلومات لتحويل النقاط عند استبدالها بمبالغ مالية</p>
                            
                            <form method="POST" id="bankForm">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label for="bank_name" class="form-label">اسم البنك</label>
                                    <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                           value="<?php echo htmlspecialchars($user->getBankName()); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bank_account_name" class="form-label">اسم صاحب الحساب</label>
                                    <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" 
                                           value="<?php echo htmlspecialchars($user->getBankAccountName()); ?>">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="iban" class="form-label">رقم الآيبان (IBAN)</label>
                                    <input type="text" class="form-control" id="iban" name="iban" 
                                           value="<?php echo htmlspecialchars($user->getIban()); ?>">
                                    <small class="text-muted">يجب أن يبدأ بـ SA ويحتوي على 24 رقم</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <small>يتم استخدام هذه المعلومات فقط عند طلب سحب نقاط إلى حسابك البنكي</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> حفظ المعلومات البنكية
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب التقييمات -->
                <div class="tab-pane fade" id="reviews" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="card-title mb-0">تقييماتي</h5>
                                <span class="badge bg-primary"><?php echo $stats['total_reviews']; ?> تقييم</span>
                            </div>
                            
                            <div id="reviewsList">
                                <!-- سيتم تحميل التقييمات هنا ديناميكياً -->
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">جاري التحميل...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3" id="loadMoreContainer" style="display: none;">
                                <button id="loadMoreBtn" class="btn btn-outline-primary" onclick="loadMoreReviews()">
                                    تحميل المزيد
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال تحديث صورة الملف الشخصي -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تغيير صورة الملف الشخصي</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="action" value="update_avatar">
                    
                    <div class="text-center mb-4">
                        <div class="avatar-preview mb-3">
                            <div id="avatarPreview" class="avatar-preview-img">
                                <?php if ($user->getAvatar() && $user->getAvatar() !== 'default.png'): ?>
                                <img src="/uploads/images/<?php echo $user->getAvatar(); ?>" 
                                     alt="Preview" class="rounded-circle">
                                <?php else: ?>
                                <div class="avatar-preview-initials">
                                    <?php echo mb_substr($user->getName(), 0, 1, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                            <div class="form-text">الصيغ المسموحة: JPG, PNG, GIF (الحد الأقصى: 2MB)</div>
                        </div>
                        
                        <div class="avatar-options">
                            <button type="button" class="btn btn-outline-secondary" onclick="removeAvatar()">
                                <i class="fas fa-trash"></i> إزالة الصورة
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" form="avatarForm" class="btn btn-primary">حفظ الصورة</button>
            </div>
        </div>
    </div>
</div>

<script>
// تحميل التقييمات عند فتح التبويب
document.getElementById('reviews-tab').addEventListener('click', function() {
    loadReviews();
});

let currentPage = 1;
const reviewsPerPage = 5;
let hasMoreReviews = true;

// تحميل التقييمات
async function loadReviews() {
    try {
        const response = await fetch(`/api/member?action=get-reviews&page=${currentPage}&limit=${reviewsPerPage}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const reviews = data.data.reviews;
            const container = document.getElementById('reviewsList');
            
            if (currentPage === 1) {
                container.innerHTML = '';
            }
            
            if (reviews.length === 0 && currentPage === 1) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-star fa-2x text-muted mb-3"></i>
                        <p>لا توجد تقييمات بعد</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            
            reviews.forEach(review => {
                const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
                const date = new Date(review.created_at).toLocaleDateString('ar-SA');
                
                html += `
                    <div class="review-item mb-3 p-3 border rounded">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1">${review.restaurant_name}</h6>
                                <div class="rating-stars" style="color: #ffc107;">${stars}</div>
                            </div>
                            <small class="text-muted">${date}</small>
                        </div>
                        
                        ${review.comment ? `
                        <p class="mb-2">${review.comment}</p>
                        ` : ''}
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">${review.is_verified ? '<i class="fas fa-check-circle text-success"></i> تم التحقق' : 'قيد المراجعة'}</small>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteReview(${review.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            if (currentPage === 1) {
                container.innerHTML = html;
            } else {
                container.insertAdjacentHTML('beforeend', html);
            }
            
            // التحقق من وجود المزيد من التقييمات
            hasMoreReviews = data.data.has_more;
            if (hasMoreReviews) {
                document.getElementById('loadMoreContainer').style.display = 'block';
            } else {
                document.getElementById('loadMoreContainer').style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
        showNotification('خطأ', 'حدث خطأ في تحميل التقييمات', 'error');
    }
}

// تحميل المزيد من التقييمات
function loadMoreReviews() {
    currentPage++;
    loadReviews();
}

// حذف تقييم
async function deleteReview(reviewId) {
    if (confirm('هل أنت متأكد من حذف هذا التقييم؟')) {
        try {
            const response = await fetch('/api/member?action=delete-review', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `review_id=${reviewId}`
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                showNotification('نجاح', 'تم حذف التقييم بنجاح');
                currentPage = 1;
                loadReviews();
            } else {
                showNotification('خطأ', data.message, 'error');
            }
        } catch (error) {
            console.error('Error deleting review:', error);
            showNotification('خطأ', 'حدث خطأ أثناء حذف التقييم', 'error');
        }
    }
}

// معاينة صورة الملف الشخصي
document.getElementById('avatar').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="rounded-circle">`;
        };
        reader.readAsDataURL(file);
    }
});

// إزالة صورة الملف الشخصي
function removeAvatar() {
    if (confirm('هل تريد إزالة صورة الملف الشخصي؟')) {
        document.getElementById('avatar').value = '';
        const preview = document.getElementById('avatarPreview');
        preview.innerHTML = `
            <div class="avatar-preview-initials">
                <?php echo mb_substr($user->getName(), 0, 1, 'UTF-8'); ?>
            </div>
        `;
    }
}

// تفعيل/تعطيل رؤية كلمة المرور
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    });
});

// قوة كلمة المرور
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.querySelector('.strength-bar');
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    strengthBar.style.width = (strength * 20) + '%';
    
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997'];
    strengthBar.style.backgroundColor = colors[strength - 1] || '#dc3545';
});
</script>

<style>
.profile-avatar {
    position: relative;
}

.avatar-container {
    position: relative;
    display: inline-block;
}

.avatar-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--primary-color);
}

.avatar-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    font-size: 3rem;
    font-weight: bold;
}

.avatar-edit {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.points-display {
    text-align: center;
    padding: 20px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: var(--border-radius);
}

.points-display h3 {
    margin: 10px 0 5px;
    font-size: 2.5rem;
}

.profile-stats {
    margin-top: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background-color: var(--light-color);
    border-radius: var(--border-radius);
}

.stat-item h5 {
    color: var(--primary-color);
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-item p {
    color: var(--gray-color);
    margin-bottom: 0;
    font-size: 0.9rem;
}

.nav-tabs .nav-link {
    color: var(--dark-color);
    border: none;
    padding: 12px 20px;
    margin: 0 5px;
}

.nav-tabs .nav-link.active {
    background-color: var(--primary-color);
    color: white;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.nav-tabs .nav-link:hover:not(.active) {
    border-bottom: 3px solid var(--primary-color);
}

.avatar-preview {
    position: relative;
    display: inline-block;
}

.avatar-preview-img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--primary-color);
    margin: 0 auto;
}

.avatar-preview-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-preview-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    font-size: 4rem;
    font-weight: bold;
}

.avatar-options {
    margin-top: 20px;
}

.password-strength {
    margin-top: 10px;
}

.strength-bar {
    height: 5px;
    background-color: #dc3545;
    border-radius: 2.5px;
    width: 0%;
    transition: width 0.3s;
}

.review-item:hover {
    background-color: var(--light-color);
}

.review-item .rating-stars {
    font-size: 1.2rem;
}
</style>

<?php
include 'includes/footer.php';
?>