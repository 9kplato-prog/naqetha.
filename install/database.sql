-- إنشاء قاعدة البيانات وحذفها إذا كانت موجودة مسبقاً
DROP DATABASE IF EXISTS `nuqtaha`;
CREATE DATABASE `nuqtaha` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nuqtaha`;

-- جدول المستخدمين
CREATE TABLE `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20),
    `city` VARCHAR(50),
    `birthdate` DATE,
    `avatar` VARCHAR(255) DEFAULT 'default.png',
    `points` INT DEFAULT 0,
    `bank_name` VARCHAR(100),
    `bank_account_name` VARCHAR(100),
    `iban` VARCHAR(50),
    `role` ENUM('admin', 'moderator', 'restaurant_owner', 'member') DEFAULT 'member',
    `status` ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    `last_login` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المطاعم
CREATE TABLE `restaurants` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(200) UNIQUE NOT NULL,
    `description` TEXT,
    `city` VARCHAR(50) NOT NULL,
    `address` TEXT,
    `phone` VARCHAR(20),
    `email` VARCHAR(100),
    `logo` VARCHAR(255),
    `cover_image` VARCHAR(255),
    `category_id` INT,
    `owner_id` INT,
    `rating` DECIMAL(3,2) DEFAULT 0.00,
    `total_reviews` INT DEFAULT 0,
    `is_featured` BOOLEAN DEFAULT FALSE,
    `status` ENUM('active', 'pending', 'suspended') DEFAULT 'pending',
    `latitude` DECIMAL(10,8),
    `longitude` DECIMAL(11,8),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_city` (`city`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول تصنيفات المطاعم (يمكن للأدمن إضافة أقسام)
CREATE TABLE `categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) UNIQUE NOT NULL,
    `icon` VARCHAR(50),
    `color` VARCHAR(7) DEFAULT '#ff6b35',
    `sort_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المهام
CREATE TABLE `tasks` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `restaurant_id` INT NOT NULL,
    `discount_percentage` INT DEFAULT 0,
    `points_reward` INT DEFAULT 0,
    `max_participants` INT,
    `current_participants` INT DEFAULT 0,
    `requirements` TEXT,
    `status` ENUM('available', 'active', 'completed', 'cancelled') DEFAULT 'available',
    `code_prefix` VARCHAR(10),
    `start_date` DATETIME,
    `end_date` DATETIME,
    `created_by` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_restaurant` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول مهام الأعضاء
CREATE TABLE `user_tasks` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `task_id` INT NOT NULL,
    `status` ENUM('reserved', 'in_progress', 'completed', 'cancelled') DEFAULT 'reserved',
    `discount_code` VARCHAR(50) UNIQUE,
    `code_expires` DATETIME,
    `review_link` TEXT,
    `rating` INT CHECK (rating >= 1 AND rating <= 5),
    `admin_approved` BOOLEAN DEFAULT FALSE,
    `points_received` BOOLEAN DEFAULT FALSE,
    `completed_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_task` (`user_id`, `task_id`),
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_code` (`discount_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول التقييمات والتعليقات
CREATE TABLE `reviews` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `restaurant_id` INT NOT NULL,
    `user_task_id` INT,
    `rating` INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    `comment` TEXT,
    `images` TEXT,
    `is_verified` BOOLEAN DEFAULT FALSE,
    `helpful_count` INT DEFAULT 0,
    `reply` TEXT,
    `replied_at` DATETIME,
    `reported_count` INT DEFAULT 0,
    `status` ENUM('active', 'hidden', 'reported', 'deleted') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_task_id`) REFERENCES `user_tasks`(`id`) ON DELETE SET NULL,
    INDEX `idx_restaurant` (`restaurant_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_rating` (`rating`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول متجر النقاط
CREATE TABLE `store_products` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `category` ENUM('mobile_balance', 'coupons', 'bank_transfer', 'tickets', 'other') NOT NULL,
    `points_required` INT NOT NULL,
    `image` VARCHAR(255),
    `stock` INT DEFAULT -1, -- -1 يعني غير محدود
    `is_active` BOOLEAN DEFAULT TRUE,
    `sort_order` INT DEFAULT 0,
    `created_by` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول طلبات المتجر
CREATE TABLE `store_orders` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `order_number` VARCHAR(50) UNIQUE NOT NULL,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `points_paid` INT NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    `details` TEXT, -- تفاصيل إضافية حسب نوع المنتج
    `admin_notes` TEXT,
    `code_sent` BOOLEAN DEFAULT FALSE,
    `sent_at` DATETIME,
    `sent_by` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `store_products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_order_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المعاملات (سحب وتحويل النقاط)
CREATE TABLE `transactions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `type` ENUM('earn', 'redeem', 'withdraw', 'transfer', 'bonus') NOT NULL,
    `amount` INT NOT NULL,
    `balance_after` INT NOT NULL,
    `description` TEXT,
    `reference_id` INT, -- ID المرجعي (مهمة، طلب متجر، إلخ)
    `reference_type` VARCHAR(50),
    `status` ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    `admin_notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_type` (`user_id`, `type`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول أفضل 100 مطعم (تحديث أسبوعي)
CREATE TABLE `top_restaurants` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `restaurant_id` INT NOT NULL,
    `week_number` INT NOT NULL,
    `year` INT NOT NULL,
    `position` INT NOT NULL,
    `rating` DECIMAL(3,2),
    `total_reviews` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_restaurant_week` (`restaurant_id`, `week_number`, `year`),
    INDEX `idx_week_year` (`week_number`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الصلاحيات المتقدمة للأدمن
CREATE TABLE `admin_permissions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `can_manage_restaurants` BOOLEAN DEFAULT TRUE,
    `can_manage_categories` BOOLEAN DEFAULT TRUE,
    `can_manage_tasks` BOOLEAN DEFAULT TRUE,
    `can_manage_reviews` BOOLEAN DEFAULT TRUE,
    `can_manage_users` BOOLEAN DEFAULT TRUE,
    `can_manage_store` BOOLEAN DEFAULT TRUE,
    `can_manage_settings` BOOLEAN DEFAULT TRUE,
    `can_view_reports` BOOLEAN DEFAULT TRUE,
    `custom_permissions` TEXT, -- صلاحيات مخصصة بصيغة JSON
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول صلاحيات المطاعم
CREATE TABLE `restaurant_permissions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `restaurant_id` INT NOT NULL,
    `max_discount` INT DEFAULT 30,
    `max_tasks` INT DEFAULT 10,
    `can_feature` BOOLEAN DEFAULT FALSE,
    `can_priority` BOOLEAN DEFAULT FALSE,
    `custom_settings` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_restaurant` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول إعدادات التصميم
CREATE TABLE `design_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('color', 'boolean', 'text', 'number', 'image') DEFAULT 'text',
    `category` VARCHAR(50),
    `sort_order` INT DEFAULT 0,
    `is_public` BOOLEAN DEFAULT FALSE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المراسلات
CREATE TABLE `messages` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `subject` VARCHAR(200),
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `read_at` DATETIME,
    `sender_deleted` BOOLEAN DEFAULT FALSE,
    `receiver_deleted` BOOLEAN DEFAULT FALSE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_sender` (`sender_id`, `sender_deleted`),
    INDEX `idx_receiver` (`receiver_id`, `receiver_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الإشعارات
CREATE TABLE `notifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    `is_read` BOOLEAN DEFAULT FALSE,
    `link` VARCHAR(255),
    `read_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_read` (`user_id`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول سجلات النشاط
CREATE TABLE `activity_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_action` (`user_id`, `action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الإحصائيات
CREATE TABLE `statistics` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `date` DATE NOT NULL,
    `total_users` INT DEFAULT 0,
    `total_restaurants` INT DEFAULT 0,
    `total_reviews` INT DEFAULT 0,
    `total_tasks` INT DEFAULT 0,
    `completed_tasks` INT DEFAULT 0,
    `total_points_earned` INT DEFAULT 0,
    `total_points_redeemed` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إدراج بيانات أولية
-- تصنيفات المطاعم الأساسية
INSERT INTO `categories` (`name`, `slug`, `icon`, `color`, `sort_order`) VALUES
('برقر', 'burger', 'fas fa-hamburger', '#ff6b35', 1),
('شورما', 'shawarma', 'fas fa-utensils', '#e55a2b', 2),
('مطاعم شعبية', 'traditional', 'fas fa-drumstick-bite', '#2a9d8f', 3),
('مأكولات بحرية', 'seafood', 'fas fa-fish', '#0ea5e9', 4),
('بيتزا', 'pizza', 'fas fa-pizza-slice', '#f59e0b', 5),
('كوفي', 'coffee', 'fas fa-coffee', '#8b5cf6', 6);

-- إعدادات التصميم الافتراضية
INSERT INTO `design_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('primary_color', '#ff6b35', 'color', 'colors', TRUE),
('secondary_color', '#2a9d8f', 'color', 'colors', TRUE),
('dark_color', '#264653', 'color', 'colors', TRUE),
('light_color', '#f8f9fa', 'color', 'colors', TRUE),
('show_best100', '1', 'boolean', 'homepage', TRUE),
('show_categories', '1', 'boolean', 'homepage', TRUE),
('logo_type', 'text', 'text', 'general', TRUE),
('logo_text', 'نقطها', 'text', 'general', TRUE),
('favicon', '', 'image', 'general', TRUE);

-- صلاحيات الأدمن الافتراضية
-- سيتم إنشاؤها تلقائياً عند إنشاء حساب أدمن