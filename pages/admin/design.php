<?php
// التحقق من صلاحيات الأدمن
if (!isset($user) || $user->getRole() !== 'admin') {
    header('Location: /login');
    exit;
}

$page_title = 'تنسيق الصفحات';
$page_scripts = ['admin-design.js'];

include 'includes/header.php';

$admin = new Admin($db->getConnection(), $user->getId());

// الحصول على إعدادات التصميم
$settings = $admin->getDesignSettings();

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_colors') {
        $color_settings = [
            'primary_color' => $_POST['primary_color'],
            'secondary_color' => $_POST['secondary_color'],
            'dark_color' => $_POST['dark_color'],
            'light_color' => $_POST['light_color']
        ];
        
        if ($admin->updateMultipleDesignSettings($color_settings)) {
            $success = 'تم حفظ الألوان بنجاح';
        } else {
            $error = 'حدث خطأ أثناء حفظ الألوان';
        }
    } elseif ($action === 'save_design') {
        $design_settings = [
            'show_best100' => isset($_POST['show_best100']) ? '1' : '0',
            'show_categories' => isset($_POST['show_categories']) ? '1' : '0',
            'logo_type' => $_POST['logo_type'],
            'logo_text' => $_POST['logo_text']
        ];
        
        if ($admin->updateMultipleDesignSettings($design_settings)) {
            $success = 'تم حفظ إعدادات التصميم بنجاح';
        } else {
            $error = 'حدث خطأ أثناء حفظ إعدادات التصميم';
        }
    } elseif ($action === 'upload_logo') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            try {
                $filename = uploadFile($_FILES['logo'], 'image');
                $admin->updateDesignSetting('logo_image', $filename, 'image');
                $success = 'تم رفع الشعار بنجاح';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// ألوان افتراضية
$default_colors = [
    '#ff6b35', '#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444'
];
?>

<div class="container">
    <h2 class="section-title"><i class="fas fa-palette"></i> تنسيق الصفحات والتحكم في التصميم</h2>
    
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
        <!-- الألوان -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">التحكم في الألوان</h5>
                    
                    <form method="POST" id="colorsForm">
                        <input type="hidden" name="action" value="save_colors">
                        
                        <div class="mb-4">
                            <h6>ألوان جاهزة:</h6>
                            <div class="color-picker mb-3">
                                <?php foreach ($default_colors as $color): ?>
                                <div class="color-item <?php echo ($settings['primary_color']['setting_value'] ?? '#ff6b35') === $color ? 'active' : ''; ?>" 
                                     style="background-color: <?php echo $color; ?>"
                                     onclick="selectColor('<?php echo $color; ?>')">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customColor" class="form-label">أو اختر لون مخصص:</label>
                                <input type="color" class="form-control form-control-color w-100" id="customColor" 
                                       value="<?php echo $settings['primary_color']['setting_value'] ?? '#ff6b35'; ?>"
                                       onchange="updateColorInputs(this.value)">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="primary_color" class="form-label">اللون الرئيسي</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-square" style="color: <?php echo $settings['primary_color']['setting_value'] ?? '#ff6b35'; ?>"></i>
                                    </span>
                                    <input type="text" class="form-control" id="primary_color" name="primary_color" 
                                           value="<?php echo $settings['primary_color']['setting_value'] ?? '#ff6b35'; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="secondary_color" class="form-label">اللون الثانوي</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-square" style="color: <?php echo $settings['secondary_color']['setting_value'] ?? '#2a9d8f'; ?>"></i>
                                    </span>
                                    <input type="text" class="form-control" id="secondary_color" name="secondary_color" 
                                           value="<?php echo $settings['secondary_color']['setting_value'] ?? '#2a9d8f'; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="dark_color" class="form-label">لون النص الداكن</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-square" style="color: <?php echo $settings['dark_color']['setting_value'] ?? '#264653'; ?>"></i>
                                    </span>
                                    <input type="text" class="form-control" id="dark_color" name="dark_color" 
                                           value="<?php echo $settings['dark_color']['setting_value'] ?? '#264653'; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="light_color" class="form-label">لون الخلفية</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-square" style="color: <?php echo $settings['light_color']['setting_value'] ?? '#f8f9fa'; ?>"></i>
                                    </span>
                                    <input type="text" class="form-control" id="light_color" name="light_color" 
                                           value="<?php echo $settings['light_color']['setting_value'] ?? '#f8f9fa'; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6>معاينة:</h6>
                            <div class="preview-box">
                                <div class="preview-header" id="previewHeader">
                                    <button class="btn btn-primary" id="previewBtn">زر تجريبي</button>
                                    <button class="btn btn-secondary" id="previewBtn2">زر ثانوي</button>
                                </div>
                                <div class="preview-content">
                                    <h5 id="previewTitle">عنوان تجريبي</h5>
                                    <p id="previewText">نص تجريبي يظهر فيه تأثير الألوان الجديدة</p>
                                    <div class="preview-card" id="previewCard">
                                        <h6>بطاقة تجريبية</h6>
                                        <p>محتوى البطاقة</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> حفظ الألوان
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- التصميم العام -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">التصميم العام</h5>
                    
                    <form method="POST" id="designForm">
                        <input type="hidden" name="action" value="save_design">
                        
                        <div class="mb-4">
                            <h6>الشعار:</h6>
                            <div class="mb-3">
                                <label class="form-label">نوع الشعار</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="logo_type" id="logo_text" 
                                                   value="text" <?php echo ($settings['logo_type']['setting_value'] ?? 'text') === 'text' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="logo_text">
                                                نص
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="logo_type" id="logo_image" 
                                                   value="image" <?php echo ($settings['logo_type']['setting_value'] ?? 'text') === 'image' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="logo_image">
                                                صورة
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="logoTextSection">
                                <label for="logo_text_input" class="form-label">نص الشعار</label>
                                <input type="text" class="form-control" id="logo_text_input" name="logo_text" 
                                       value="<?php echo $settings['logo_text']['setting_value'] ?? 'نقطها'; ?>">
                            </div>
                            
                            <div class="mb-3" id="logoImageSection" style="display: none;">
                                <label class="form-label">صورة الشعار</label>
                                <?php if (isset($settings['logo_image']['setting_value']) && $settings['logo_image']['setting_value']): ?>
                                <div class="current-logo mb-3">
                                    <img src="/uploads/images/<?php echo $settings['logo_image']['setting_value']; ?>" 
                                         class="img-thumbnail" style="max-height: 100px;">
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST" enctype="multipart/form-data" id="logoUploadForm">
                                    <input type="hidden" name="action" value="upload_logo">
                                    <div class="input-group">
                                        <input type="file" class="form-control" name="logo" accept="image/*">
                                        <button type="submit" class="btn btn-primary">رفع</button>
                                    </div>
                                    <small class="text-muted">الحجم الأمثل: 200×60 بكسل</small>
                                </form>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6>الصفحة الرئيسية:</h6>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="show_best100" id="show_best100" 
                                           <?php echo ($settings['show_best100']['setting_value'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_best100">
                                        إظهار قائمة أفضل 100 مطعم
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="show_categories" id="show_categories" 
                                           <?php echo ($settings['show_categories']['setting_value'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_categories">
                                        إظهار تصنيفات المطاعم
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6>الوضع الليلي:</h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="dark_mode_switch" 
                                       onclick="toggleDarkMode(this.checked)" 
                                       <?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dark_mode_switch">
                                    تفعيل الوضع الليلي
                                </label>
                            </div>
                            <small class="text-muted">يمكن للمستخدمين تغيير هذا الإعداد من حسابهم</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> حفظ إعدادات التصميم
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- CSS مخصص -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">CSS مخصص</h5>
                    
                    <div class="mb-3">
                        <label for="custom_css" class="form-label">أضف CSS مخصص</label>
                        <textarea class="form-control" id="custom_css" rows="6" placeholder="/* أضف CSS مخصص هنا */
.btn-custom {
    background-color: var(--primary-color);
    border-radius: 20px;
}"></textarea>
                        <small class="text-muted">سيتم تطبيق هذا CSS على جميع صفحات الموقع</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-secondary" onclick="previewCustomCSS()">
                            <i class="fas fa-eye"></i> معاينة
                        </button>
                        <button class="btn btn-success" onclick="saveCustomCSS()">
                            <i class="fas fa-save"></i> حفظ CSS المخصص
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تحديث أقسام الشعار
document.querySelectorAll('input[name="logo_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'text') {
            document.getElementById('logoTextSection').style.display = 'block';
            document.getElementById('logoImageSection').style.display = 'none';
        } else {
            document.getElementById('logoTextSection').style.display = 'none';
            document.getElementById('logoImageSection').style.display = 'block';
        }
    });
});

// تفعيل/تعطيل الوضع الليلي
function toggleDarkMode(enabled) {
    if (enabled) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
    } else {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
    }
    
    // إرسال طلب لحفظ التفضيل
    fetch('/api/admin?action=toggle-dark-mode', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `dark_mode=${enabled ? 1 : 0}`
    });
}

// اختيار لون
function selectColor(color) {
    updateColorInputs(color);
    document.getElementById('customColor').value = color;
    
    // تحديث العناصر النشطة
    document.querySelectorAll('.color-item').forEach(item => {
        item.classList.remove('active');
        if (item.style.backgroundColor === color) {
            item.classList.add('active');
        }
    });
}

// تحديث حقول الألوان
function updateColorInputs(color) {
    document.getElementById('primary_color').value = color;
    updatePreview();
}

// تحديث المعاينة
function updatePreview() {
    const primaryColor = document.getElementById('primary_color').value;
    const secondaryColor = document.getElementById('secondary_color').value;
    const darkColor = document.getElementById('dark_color').value;
    const lightColor = document.getElementById('light_color').value;
    
    // تحديث المعاينة
    document.getElementById('previewBtn').style.backgroundColor = primaryColor;
    document.getElementById('previewBtn2').style.backgroundColor = secondaryColor;
    document.getElementById('previewHeader').style.backgroundColor = lightColor;
    document.getElementById('previewTitle').style.color = darkColor;
    document.getElementById('previewCard').style.backgroundColor = lightColor;
    document.getElementById('previewCard').style.borderColor = primaryColor + '20';
    
    // تحديث أيقونات الألوان
    document.querySelectorAll('#colorsForm .input-group-text i').forEach((icon, index) => {
        const colors = [primaryColor, secondaryColor, darkColor, lightColor];
        if (colors[index]) {
            icon.style.color = colors[index];
        }
    });
}

// معاينة CSS المخصص
function previewCustomCSS() {
    const css = document.getElementById('custom_css').value;
    
    // إضافة CSS مؤقت
    const style = document.createElement('style');
    style.id = 'custom-css-preview';
    style.textContent = css;
    
    // إزالة CSS السابق إذا كان موجوداً
    const oldStyle = document.getElementById('custom-css-preview');
    if (oldStyle) {
        oldStyle.remove();
    }
    
    document.head.appendChild(style);
    
    showNotification('تم التطبيق', 'تم تطبيق CSS المخصص للمعاينة');
}

// حفظ CSS المخصص
function saveCustomCSS() {
    const css = document.getElementById('custom_css').value;
    
    fetch('/api/admin?action=save-custom-css', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `css=${encodeURIComponent(css)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification('نجاح', 'تم حفظ CSS المخصص بنجاح');
        } else {
            showNotification('خطأ', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving CSS:', error);
        showNotification('خطأ', 'حدث خطأ أثناء حفظ CSS', 'error');
    });
}

// تحديث المعاينة عند تغيير الألوان
document.querySelectorAll('#colorsForm input[type="text"]').forEach(input => {
    input.addEventListener('input', updatePreview);
});

// تهيئة المعاينة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
    
    // تهيئة نوع الشعار
    const logoType = document.querySelector('input[name="logo_type"]:checked').value;
    if (logoType === 'image') {
        document.getElementById('logoTextSection').style.display = 'none';
        document.getElementById('logoImageSection').style.display = 'block';
    }
});
</script>

<style>
.color-picker {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.color-item {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: var(--transition);
}

.color-item.active {
    border-color: var(--dark-color);
    transform: scale(1.1);
}

body.dark-mode .color-item.active {
    border-color: white;
}

.preview-box {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.preview-header {
    padding: 20px;
    background-color: var(--light-color);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    gap: 10px;
}

.preview-content {
    padding: 20px;
    background-color: white;
}

body.dark-mode .preview-content {
    background-color: var(--card-bg);
}

.preview-card {
    padding: 15px;
    border-radius: var(--border-radius);
    border: 1px solid rgba(255, 107, 53, 0.2);
    margin-top: 15px;
}
</style>

<?php
include 'includes/footer.php';
?>