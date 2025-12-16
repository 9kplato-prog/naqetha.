<?php
$page_title = 'إنشاء حساب جديد';

// إذا كان المستخدم مسجل دخول بالفعل، توجيهه للصفحة المناسبة
if (isset($user) && $user) {
    $role = $user->getRole();
    switch ($role) {
        case 'admin':
            header('Location: /admin/dashboard');
            break;
        case 'restaurant_owner':
            header('Location: /owner/dashboard');
            break;
        default:
            header('Location: /member/dashboard');
    }
    exit;
}

// معالجة تسجيل المستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => sanitizeInput($_POST['firstname'] . ' ' . $_POST['lastname']),
        'email' => sanitizeInput($_POST['email']),
        'phone' => sanitizeInput($_POST['phone']),
        'city' => sanitizeInput($_POST['city']),
        'birthdate' => sanitizeInput($_POST['birthdate']),
        'password' => $_POST['password']
    ];
    
    $confirm_password = $_POST['confirm_password'];
    
    // التحقق من كلمات المرور
    if ($data['password'] !== $confirm_password) {
        $error = 'كلمات المرور غير متطابقة';
    } elseif (!validatePassword($data['password'])) {
        $error = 'كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل، وتشمل حروفاً كبيرة وصغيرة وأرقاماً';
    } elseif (!validateEmail($data['email'])) {
        $error = 'البريد الإلكتروني غير صالح';
    } elseif (!validatePhone($data['phone'])) {
        $error = 'رقم الهاتف غير صالح';
    } elseif (!isset($_POST['terms'])) {
        $error = 'يجب الموافقة على الشروط والأحكام';
    } else {
        $user = new User($db->getConnection());
        try {
            if ($user->register($data)) {
                // تسجيل الدخول تلقائياً
                $_SESSION['user_id'] = $user->getId();
                $_SESSION['user_role'] = $user->getRole();
                $_SESSION['user_name'] = $user->getName();
                
                // توجيه المستخدم للصفحة المناسبة
                switch ($user->getRole()) {
                    case 'admin':
                        header('Location: /admin/dashboard');
                        break;
                    case 'restaurant_owner':
                        header('Location: /owner/dashboard');
                        break;
                    default:
                        header('Location: /member/dashboard?welcome=true');
                }
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="form-container">
                <div class="logo text-center mb-4">
                    <h1 style="color: var(--primary-color);"><?php echo getSetting('logo_text', SITE_NAME); ?></h1>
                    <p style="color: var(--gray-color);">إنشاء حساب جديد</p>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="tabs mb-4">
                    <div class="tab" onclick="window.location.href='/login'">
                        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                    </div>
                    <div class="tab active">
                        <i class="fas fa-user-plus"></i> إنشاء حساب
                    </div>
                </div>
                
                <form method="POST" id="registerForm" class="needs-validation" novalidate>
                    <div class="legal-notice mb-4">
                        <div class="alert alert-info">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>إشعار قانوني:</strong> أنت مسؤول عن جميع التقييمات والمراجعات التي تقدمها.
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="firstname" class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="firstname" name="firstname" 
                                       value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">يرجى إدخال الاسم الأول</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="lastname" class="form-label">الاسم الأخير <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="lastname" name="lastname" 
                                       value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">يرجى إدخال الاسم الأخير</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني (Gmail فقط) <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="example@gmail.com" required>
                        <div class="invalid-feedback">يرجى إدخال بريد Gmail صحيح</div>
                        <small class="form-text text-muted">سيتم إرسال رابط التفعيل إلى هذا البريد</small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="phone" class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                               placeholder="05XXXXXXXX" required>
                        <div class="invalid-feedback">يرجى إدخال رقم جوال صحيح</div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="city" class="form-label">المدينة <span class="text-danger">*</span></label>
                        <select class="form-control" id="city" name="city" required>
                            <option value="">اختر المدينة</option>
                            <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>" <?php echo (isset($_POST['city']) && $_POST['city'] == $city) ? 'selected' : ''; ?>>
                                <?php echo $city; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار المدينة</div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="birthdate" class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="birthdate" name="birthdate" 
                               value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>" 
                               max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                        <div class="invalid-feedback">يجب أن يكون عمرك 18 عاماً أو أكثر</div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="password" class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                        <div class="password-input">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="كلمة مرور قوية (8 أحرف على الأقل)" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل</div>
                        <div class="password-strength mt-2">
                            <div class="strength-bar">
                                <div class="strength-fill" id="passwordStrength"></div>
                            </div>
                            <small id="passwordStrengthText" class="form-text"></small>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                        <div class="password-input">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="أعد إدخال كلمة المرور" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">كلمة المرور غير متطابقة</div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                أوافق على <a href="/terms" target="_blank">الشروط والأحكام</a> و 
                                <a href="/privacy" target="_blank">سياسة الخصوصية</a> والإشعار القانوني أعلاه
                            </label>
                            <div class="invalid-feedback">يجب الموافقة على الشروط والأحكام</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-user-plus"></i> إنشاء حساب
                    </button>
                    
                    <div class="text-center mt-3">
                        <p>هل لديك حساب بالفعل؟ <a href="/login">سجل الدخول</a></p>
                    </div>
                    
                    <div class="divider">أو</div>
                    
                    <div class="social-register">
                        <button type="button" class="btn btn-outline w-100 mb-2" onclick="registerWithGoogle()">
                            <i class="fab fa-google"></i> التسجيل بواسطة Google
                        </button>
                        <!--
                        <button type="button" class="btn btn-outline w-100 mb-2" onclick="registerWithApple()">
                            <i class="fab fa-apple"></i> التسجيل بواسطة Apple
                        </button>
                        -->
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// التحقق من قوة كلمة المرور
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');
    
    let strength = 0;
    let text = '';
    let color = '#dc3545';
    
    // التحقق من طول كلمة المرور
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // التحقق من وجود حروف كبيرة وصغيرة
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    
    // التحقق من وجود أرقام
    if (/\d/.test(password)) strength++;
    
    // التحقق من وجود رموز خاصة
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // تحديث شريط القوة
    const width = strength * 20;
    strengthBar.style.width = width + '%';
    
    // تحديث النص واللون
    switch (strength) {
        case 0:
        case 1:
            text = 'ضعيفة جداً';
            color = '#dc3545';
            break;
        case 2:
            text = 'ضعيفة';
            color = '#fd7e14';
            break;
        case 3:
            text = 'جيدة';
            color = '#ffc107';
            break;
        case 4:
            text = 'قوية';
            color = '#28a745';
            break;
        case 5:
            text = 'قوية جداً';
            color = '#20c997';
            break;
    }
    
    strengthBar.style.backgroundColor = color;
    strengthText.textContent = text;
    strengthText.style.color = color;
});

// التحقق من تطابق كلمات المرور
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const feedback = this.nextElementSibling;
    
    if (confirmPassword && password !== confirmPassword) {
        this.classList.add('is-invalid');
        feedback.textContent = 'كلمة المرور غير متطابقة';
    } else {
        this.classList.remove('is-invalid');
    }
});

// إظهار/إخفاء كلمة المرور
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.parentNode.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// التسجيل بواسطة Google
function registerWithGoogle() {
    // في الإنتاج، سيتم استخدام Google OAuth
    showNotification('قريباً', 'سيتم تفعيل التسجيل بواسطة Google قريباً', 'info');
}

// التسجيل بواسطة Apple
function registerWithApple() {
    // في الإنتاج، سيتم استخدام Apple Sign In
    showNotification('قريباً', 'سيتم تفعيل التسجيل بواسطة Apple قريباً', 'info');
}

// التحقق من صحة النموذج
document.getElementById('registerForm').addEventListener('submit', function(event) {
    if (!this.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    this.classList.add('was-validated');
    
    // التحقق من تطابق كلمات المرور
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        document.getElementById('confirm_password').classList.add('is-invalid');
        event.preventDefault();
    }
});

// التحقق من البريد الإلكتروني عند الخروج
document.getElementById('email').addEventListener('blur', function() {
    const email = this.value;
    const emailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
    
    if (email && !emailRegex.test(email)) {
        this.classList.add('is-invalid');
        this.nextElementSibling.textContent = 'يجب استخدام بريد Gmail فقط';
    }
});
</script>

<style>
.form-container {
    background-color: var(--card-bg);
    border-radius: var(--border-radius);
    padding: 30px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color);
}

.tabs {
    display: flex;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 20px;
}

.tab {
    flex: 1;
    text-align: center;
    padding: 10px;
    cursor: pointer;
    color: var(--gray-color);
    transition: var(--transition);
    border-bottom: 3px solid transparent;
}

.tab:hover {
    color: var(--primary-color);
}

.tab.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    font-weight: 600;
}

.password-input {
    position: relative;
}

.toggle-password {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-color);
    cursor: pointer;
}

.toggle-password:hover {
    color: var(--primary-color);
}

.password-strength {
    margin-top: 10px;
}

.strength-bar {
    height: 4px;
    background-color: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.strength-fill {
    height: 100%;
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 2px;
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 20px 0;
    color: var(--gray-color);
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid var(--border-color);
}

.divider::before {
    margin-right: 10px;
}

.divider::after {
    margin-left: 10px;
}

.social-register .btn {
    border-color: var(--border-color);
    color: var(--dark-color);
}

.social-register .btn:hover {
    background-color: var(--light-color);
}
</style>

<?php
include 'includes/footer.php';
?>