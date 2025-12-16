<?php
// هذا الملف يمكن تضمينه في نهاية جميع الصفحات
?>
            </div>
        </main>
    </div>
    
    <!-- الفوتر -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h2><?php echo getSetting('logo_text', SITE_NAME); ?></h2>
                    <p>منصة مهام وتقييمات ذكية تربط بين الأعضاء وأصحاب المطاعم.</p>
                </div>
                
                <div class="footer-links">
                    <h3>روابط سريعة</h3>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>">الرئيسية</a></li>
                        <li><a href="<?php echo BASE_URL; ?>restaurants">المطاعم</a></li>
                        <li><a href="<?php echo BASE_URL; ?>login">تسجيل الدخول</a></li>
                        <li><a href="<?php echo BASE_URL; ?>register">إنشاء حساب</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3>تواصل معنا</h3>
                    <ul>
                        <li><i class="fas fa-envelope"></i> <?php echo SITE_EMAIL; ?></li>
                        <li><i class="fas fa-phone"></i> 966500000000</li>
                        <li><i class="fas fa-map-marker-alt"></i> المملكة العربية السعودية</li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3>تابعنا</h3>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-snapchat"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> جميع الحقوق محفوظة - <?php echo SITE_NAME; ?>
            </div>
        </div>
    </footer>
    
    <!-- السكريبتات -->
    <script src="<?php echo ASSETS_PATH; ?>js/main.js"></script>
    
    <!-- سكريبتات إضافية حسب الصفحة -->
    <?php if (isset($page_scripts)): ?>
    <?php foreach ($page_scripts as $script): ?>
    <script src="<?php echo ASSETS_PATH; ?>js/<?php echo $script; ?>"></script>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- الإشعارات -->
    <div id="global-notification" class="notification">
        <div class="notification-content">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong class="notification-title"></strong>
                <p class="notification-message"></p>
            </div>
        </div>
    </div>
    
    <!-- تحميل إضافي للوضع الليلي -->
    <script>
        // تحميل الوضع المحفوظ
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                const themeToggle = document.querySelector('.theme-toggle-sidebar');
                if (themeToggle) {
                    const icon = themeToggle.querySelector('i');
                    const text = themeToggle.querySelector('span');
                    if (icon && text) {
                        icon.className = 'fas fa-sun';
                        text.textContent = 'الوضع النهاري';
                    }
                }
            }
            
            // تحديث وقت الخادم
            updateServerTime();
        });
        
        function updateServerTime() {
            const timeElement = document.getElementById('server-time');
            if (timeElement) {
                const now = new Date();
                timeElement.textContent = now.toLocaleTimeString('ar-SA');
                setTimeout(updateServerTime, 1000);
            }
        }
    </script>
</body>
</html>