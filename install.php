RewriteEngine On

# إعادة توجيه جميع الطلبات إلى index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]

# إعدادات الأمان
Options -Indexes
<Files ~ "^\.ht">
    Order allow,deny
    Deny from all
</Files>