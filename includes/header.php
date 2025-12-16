<?php
// هذا الملف يمكن تضمينه في جميع الصفحات
// وسيحتوي على هيكل الهيدر المشترك
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . (isset($page_title) ? " | $page_title" : ''); ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- الأنماط الرئيسية -->
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/main.css">
    
    <!-- الأنماط الديناميكية (للألوان المخصصة) -->
    <style>
        :root {
            --primary-color: <?php echo getSetting('primary_color', '#ff6b35'); ?>;
            --secondary-color: <?php echo getSetting('secondary_color', '#2a9d8f'); ?>;
            --dark-color: <?php echo getSetting('dark_color', '#264653'); ?>;
            --light-color: <?php echo getSetting('light_color', '#f8f9fa'); ?>;
        }
        
        <?php if (isset($_SESSION['custom_css'])): ?>
        <?php echo $_SESSION['custom_css']; ?>
        <?php endif; ?>
    </style>
    
    <script>
        // تحميل الثوابت في JavaScript
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const USER_ID = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>;
        const USER_ROLE = '<?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest'; ?>';
    </script>
</head>
<body <?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'class="dark-mode"' : ''; ?>>
    
    <?php if (!isset($hide_header) || !$hide_header): ?>
    
    <!-- شريط التنقل العلوي -->
    <div class="top-bar" id="topBar" <?php echo isset($user) ? 'style="display: none;"' : ''; ?>>
        <div class="container">
            <div class="top-bar-content">
                <div class="logo">
                    <h1><a href="<?php echo BASE_URL; ?>"><?php echo getSetting('logo_text', SITE_NAME); ?></a></h1>
                </div>
                <div class="auth-buttons">
                    <button class="btn btn-outline" onclick="window.location.href='<?php echo BASE_URL; ?>login'" style="color: white; border-color: white;">
                        <i class="fas fa-sign-in-alt"></i> تسجيل دخول
                    </button>
                    <button class="btn btn-primary" onclick="window.location.href='<?php echo BASE_URL; ?>register'">
                        <i class="fas fa-user-plus"></i> إنشاء حساب
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- الهيدر الرئيسي -->
    <header id="mainHeader" <?php echo !isset($user) ? 'style="display: none;"' : ''; ?>>
        <div class="container header-content">
            <div class="logo">
                <button class="btn btn-primary btn-sm" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <?php if (isset($user)): ?>
                <div class="user-avatar" style="margin-right: 10px;" onclick="window.location.href='<?php echo BASE_URL . $user->getRole(); ?>/profile'">
                    <?php echo mb_substr($user->getName(), 0, 1, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                <h1><a href="<?php echo BASE_URL; ?>"><?php echo getSetting('logo_text', SITE_NAME); ?></a></h1>
            </div>
            
            <?php if (isset($user)): ?>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar" onclick="window.location.href='<?php echo BASE_URL . $user->getRole(); ?>/profile'">
                        <?php echo mb_substr($user->getName(), 0, 1, 'UTF-8'); ?>
                    </div>
                    <div>
                        <div id="currentUserName"><?php echo $user->getName(); ?></div>
                        <small id="currentUserPoints"><?php echo formatPoints($user->getPoints()); ?> نقطة</small>
                    </div>
                </div>
                
                <!-- زر الإشعارات -->
                <div class="notification-dropdown">
                    <button class="btn btn-outline btn-sm" id="notificationButton">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" style="display: none;">0</span>
                    </button>
                    <div class="notification-dropdown-content" id="notifications-container">
                        <!-- سيتم تحميل الإشعارات هنا ديناميكياً -->
                        <div class="notification-item">جاري تحميل الإشعارات...</div>
                    </div>
                </div>
                
                <!-- بحث سريع -->
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="بحث...">
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- الشريط الجانبي -->
    <div class="overlay" id="overlay"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>لوحة التحكم</h3>
            <?php if (isset($user)): ?>
            <p id="sidebarUserName">مرحباً <?php echo $user->getName(); ?></p>
            <small id="sidebarUserRole">
                <?php
                $role_names = [
                    'admin' => 'أدمن النظام',
                    'moderator' => 'مشرف',
                    'restaurant_owner' => 'صاحب مطعم',
                    'member' => 'عضو'
                ];
                echo $role_names[$user->getRole()] ?? 'عضو';
                ?>
            </small>
            <?php endif; ?>
        </div>
        
        <ul class="sidebar-menu">
            <?php if (isset($user)): ?>
            
            <?php if ($user->getRole() === 'restaurant_owner'): ?>
            <!-- قائمة أصحاب المطاعم -->
            <div id="restaurantOwnerMenu">
                <p class="menu-title">إدارة المطعم</p>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/dashboard"><i class="fas fa-home"></i> الصفحة الرئيسية</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/ratings"><i class="fas fa-star"></i> التقييمات</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/reviews"><i class="fas fa-comment"></i> آخر التقييمات</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/messages"><i class="fas fa-comments"></i> مراسلة الإدارة</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/profile"><i class="fas fa-user"></i> حسابي</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/reports"><i class="fas fa-chart-bar"></i> التقارير</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/subscription"><i class="fas fa-crown"></i> الاشتراك</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>owner/support"><i class="fas fa-headset"></i> الدعم</a>
            </div>
            
            <?php elseif (in_array($user->getRole(), ['member', 'moderator'])): ?>
            <!-- قائمة الأعضاء -->
            <div id="memberMenu">
                <p class="menu-title">القوائم</p>
                <a class="menu-item" href="<?php echo BASE_URL; ?>member/dashboard"><i class="fas fa-home"></i> الصفحة الرئيسية</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>member/tasks"><i class="fas fa-tasks"></i> المهام</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>member/store"><i class="fas fa-gift"></i> متجر النقاط</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>member/leaderboard"><i class="fas fa-trophy"></i> المتصدرون</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>member/profile"><i class="fas fa-user"></i> حسابي</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>member/statistics"><i class="fas fa-chart-line"></i> الإحصائيات</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>member/support"><i class="fas fa-headset"></i> الدعم</a>
            </div>
            
            <?php elseif ($user->getRole() === 'admin'): ?>
            <!-- قائمة الأدمن -->
            <div id="adminMenu">
                <p class="menu-title">الإدارة</p>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/dashboard"><i class="fas fa-home"></i> الصفحة الرئيسية</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/restaurants"><i class="fas fa-plus-circle"></i> إضافة مطاعم</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/best100"><i class="fas fa-trophy"></i> أفضل 100 مطعم</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/comments"><i class="fas fa-comment-slash"></i> إدارة التعليقات</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/tasks"><i class="fas fa-tasks"></i> إدارة المهام</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/users"><i class="fas fa-users-cog"></i> صلاحيات المشرفين</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/store"><i class="fas fa-store"></i> إدارة المتجر</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/restaurant-permissions"><i class="fas fa-user-cog"></i> صلاحيات المطاعم</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/reports"><i class="fas fa-chart-bar"></i> التقارير</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/profile"><i class="fas fa-user"></i> حسابي</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/messages"><i class="fas fa-comments"></i> المراسلات</a>
                <a class="menu-item" href="<?php echo BASE_URL; ?>admin/design"><i class="fas fa-palette"></i> تنسيق الصفحات</a>
            </div>
            <?php endif; ?>
            
            <p class="menu-title">عام</p>
            <a class="menu-item" href="<?php echo BASE_URL; ?>"><i class="fas fa-home"></i> الرئيسية</a>
            <a class="menu-item theme-toggle" onclick="toggleTheme()">
                <i class="fas fa-moon"></i>
                <span>الوضع الليلي</span>
            </a>
            <a class="menu-item" href="<?php echo BASE_URL; ?>logout"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
            
            <?php else: ?>
            <!-- القائمة للزوار -->
            <p class="menu-title">القوائم</p>
            <a class="menu-item" href="<?php echo BASE_URL; ?>"><i class="fas fa-home"></i> الرئيسية</a>
            <a class="menu-item" href="<?php echo BASE_URL; ?>restaurants"><i class="fas fa-utensils"></i> جميع المطاعم</a>
            <a class="menu-item" href="<?php echo BASE_URL; ?>login"><i class="fas fa-sign-in-alt"></i> تسجيل دخول</a>
            <a class="menu-item" href="<?php echo BASE_URL; ?>register"><i class="fas fa-user-plus"></i> إنشاء حساب</a>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <div class="theme-toggle-sidebar" onclick="toggleTheme()">
                <div>
                    <i class="fas fa-moon"></i>
                    <span>الوضع الليلي</span>
                </div>
                <div class="toggle-switch">
                    <div class="toggle-knob"></div>
                </div>
            </div>
            
            <?php if (isset($user)): ?>
            <div class="user-quick-info">
                <small>نقاطك: <strong><?php echo formatPoints($user->getPoints()); ?></strong></small>
                <small>مدينتك: <strong><?php echo $user->getCity(); ?></strong></small>
            </div>
            <?php endif; ?>
        </div>
    </aside>
    
    <?php endif; ?>
    
    <!-- المحتوى الرئيسي -->
    <div class="app-shell">
        <main class="main">
            <div class="content" id="main-content">