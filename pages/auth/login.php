<?php
$page_title = 'تسجيل الدخول';

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

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    try {
        $user = new User($db->getConnection());
        
        if ($user->login($email, $password)) {
            // تسجيل بيانات الجلسة
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_role'] = $user->getRole();
            $_SESSION['user_name'] = $user->getName();
            
            // إذا طلب تذكرني، حفظ الكوكيز
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                
                // حفظ التوكن في قاعدة البيانات
                $sql = "INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))";
                $stmt = $db->getConnection()->prepare($sql);
                $stmt->bind_param("is", $user->getId(), $token);
                $stmt->execute();
            }
            
            // توجيه المستخدم للصفحة المناسبة
            switch ($user->getRole()) {
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
            
        } else {
            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="form-container">
                <div class="logo text-center mb-4">
                    <h1 style="color: var(--primary-color);"><?php echo getSetting('logo_text', SITE_NAME); ?></h1>
                    <p style="color: var(--gray-color);">منصة مهام وتقييمات المطاعم</p>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    تم إنشاء حسابك بنجاح! يمكنك تسجيل الدخول الآن.
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['reset'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.
                </div>
                <?php endif; ?>
                
                <div class="tabs">
                    <div class="tab active" onclick="switchAuthTab('login')">تسجيل الدخول</div>
                    <div class="tab" onclick="switchAuthTab('register')">إنشاء حساب جديد</div>
                </div>
                
                <form method="POST" id="login-form" class="needs-validation" novalidate>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="example@gmail.com" required>
                        <div class="invalid-feedback">يرجى إدخال بريد إلكتروني صحيح</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> كلمة المرور</label>
                        <div class="password-input">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="أدخل كلمة المرور" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">يرجى إدخال كلمة المرور</div>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">تذكرني</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="/forgot-password">نسيت كلمة المرور؟</a>
                    </div>
                    
                    <div class="divider">أو</div>
                    
                    <div class="social-login">
                        <button type="button" class="btn btn-outline w-100 mb-2" onclick="loginWithGoogle()">
                            <i class="fab fa-google"></i> تسجيل الدخول بواسطة Google
                        </button>
                    </div>
                </form>
                
                <form method="POST" id="register-form" class="needs-validation d-none" novalidate>
                    <div class="legal-notice mb-3">
                        <div class="alert alert-info">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>إشعار قانوني:</strong> أنت مسؤول عن جميع التقييمات والمراجعات التي تقدمها.
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>الاسم الأول</label>
                                <input type="text" class="form-control" name="firstname" required>
                                <div class="invalid-feedback">يرجى إدخال الاسم الأول</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>الاسم الأخير</label>
                                <input type="text" class="form-control" name="lastname" required>
                                <div class="invalid-feedback">يرجى إدخال الاسم الأخير</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>البريد الإلكتروني (Gmail فقط)</label>
                        <input type="email" class="form-control" name="email" placeholder="example@gmail.com" required>
                        <div class="invalid-feedback">يرجى إدخال بريد Gmail صحيح</div>
                    </div>
                    
                    <div class="form-group">
                        <label>رقم الجوال</label>
                        <input type="tel" class="form-control" name="phone" placeholder="05XXXXXXXX" required>
                        <div class="invalid-feedback">يرجى إدخال رقم جوال صحيح</div>
                    </div>
                    
                    <div class="form-group">
                        <label>المدينة</label>
                        <select class="form-control" name="city" required>
                            <option value="">اختر المدينة</option>
                            <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار المدينة</div>
                    </div>
                    
                    <div class="form-group">
                        <label>تاريخ الميلاد</label>
                        <input type="date" class="form-control" name="birthdate" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                        <div class="invalid-feedback">يجب أن يكون عمرك 18 عاماً أو أكثر</div>
                    </div>
                    
                    <div class="form-group">
                        <label>كلمة المرور</label>
                        <div class="password-input">
                            <input type="password" class="form-control" name="password" 
                                   placeholder="كلمة مرور قوية (8 أحرف على الأقل)" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل</div>
                    </div>
                    
                    <div class="form-group">
                        <label>تأكيد كلمة المرور</label>
                        <div class="password-input">
                            <input type="password" class="form-control" name="confirm_password" 
                                   placeholder="أعد إدخال كلمة المرور" required>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">كلمة المرور غير متطابقة</div>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" name="terms" required>
                        <label class="form-check-label">
                            أوافق على <a href="/terms" target="_blank">الشروط والأحكام</a> و 
                            <a href="/privacy" target="_blank">سياسة الخصوصية</a> والإشعار القانوني أعلاه
                        </label>
                        <div class="invalid-feedback">يجب الموافقة على الشروط والأحكام</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-user-plus"></i> إنشاء حساب
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function switchAuthTab(tab) {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const tabs = document.querySelectorAll('.tab');
    
    tabs.forEach(t => t.classList.remove('active'));
    
    if (tab === 'login') {
        tabs[0].classList.add('active');
        loginForm.classList.remove('d-none');
        registerForm.classList.add('d-none');
    } else {
        tabs[1].classList.add('active');
        loginForm.classList.add('d-none');
        registerForm.classList.remove('d-none');
    }
}

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

function loginWithGoogle() {
    // يمكن إضافة تسجيل الدخول بواسطة Google هنا
    showNotification('قريباً', 'سيتم تفعيل تسجيل الدخول بواسطة Google قريباً', 'info');
}
</script>

<?php
include 'includes/footer.php';
?>