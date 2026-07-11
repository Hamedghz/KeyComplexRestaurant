-- KEY Restaurant & Coffeehouse canonical production schema

-- Single source of truth generated from the legacy schema, survey schema, homepage extensions, runtime analytics migration, admin CRUD tables, and production fixes.

-- MySQL 5.7+/MariaDB compatible.


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

SET time_zone = "+00:00";

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


-- KEY Restaurant & Coffeehouse Database Schema
-- MySQL 8+ / MariaDB compatible
-- Character set: utf8mb4 for full Persian support

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Database creation
-- CREATE DATABASE IF NOT EXISTS `keycir_keykavoos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `keycir_keykavoos`;

-- ============================================
-- ADMINS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin','manager','employee') DEFAULT 'admin',
  `department` varchar(100) DEFAULT NULL,
  `permissions` JSON DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: username=admin, password=admin123 (CHANGE THIS!)


-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `membership_level` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `loyalty_points` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `last_order_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  KEY `membership_level` (`membership_level`),
  KEY `is_active` (`is_active`),
  KEY `loyalty_points` (`loyalty_points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MENU CATEGORIES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `menu_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_fa` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description_fa` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `parent_id` int(11) UNSIGNED DEFAULT NULL,
  `sepid_id` varchar(100) DEFAULT NULL,
  `visible_qr_menu` tinyint(1) NOT NULL DEFAULT 1,
  `visible_website` tinyint(1) NOT NULL DEFAULT 1,
  `visible_kiosk` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `is_active` (`is_active`),
  KEY `sort_order` (`sort_order`),
  KEY `idx_menu_categories_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default categories
INSERT IGNORE INTO `menu_categories` (`name_fa`, `name_en`, `slug`, `icon`, `sort_order`) VALUES
('نوشیدنی‌های گرم', 'Hot Beverages', 'hot-beverages', '☕', 1),
('نوشیدنی‌های سرد', 'Cold Beverages', 'cold-beverages', '🥤', 2),
('غذاهای اصلی', 'Main Dishes', 'main-dishes', '🍽️', 3),
('پیش غذا', 'Appetizers', 'appetizers', '🥗', 4),
('دسر', 'Desserts', 'desserts', '🍰', 5),
('صبحانه', 'Breakfast', 'breakfast', '🍳', 6);

-- ============================================
-- MENU ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int(11) UNSIGNED NOT NULL,
  `name_fa` varchar(150) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description_fa` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `gallery_images` JSON DEFAULT NULL,
  `ingredients_fa` text DEFAULT NULL,
  `ingredients_en` text DEFAULT NULL,
  `calories` int(11) DEFAULT NULL,
  `preparation_time` int(11) DEFAULT NULL COMMENT 'in minutes',
  `availability_status` enum('available','unavailable','limited') NOT NULL DEFAULT 'available',
  `visible_qr_menu` tinyint(1) NOT NULL DEFAULT 1,
  `visible_website` tinyint(1) NOT NULL DEFAULT 1,
  `visible_kiosk` tinyint(1) NOT NULL DEFAULT 1,
  `visible_loyalty` tinyint(1) NOT NULL DEFAULT 1,
  `campaign_price` decimal(10,2) DEFAULT NULL,
  `promo_text` varchar(255) DEFAULT NULL,
  `promo_image` varchar(255) DEFAULT NULL,
  `sepid_id` varchar(100) DEFAULT NULL,
  `sepid_last_sync_at` datetime DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_vegetarian` tinyint(1) DEFAULT 0,
  `is_spicy` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `orders_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `is_available` (`is_available`),
  KEY `is_featured` (`is_featured`),
  KEY `sort_order` (`sort_order`),
  KEY `idx_menu_items_category_active_order` (`category_id`, `is_available`, `sort_order`),
  CONSTRAINT `fk_menu_items_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample menu items
INSERT IGNORE INTO `menu_items` (`category_id`, `name_fa`, `name_en`, `slug`, `description_fa`, `description_en`, `price`, `image`, `is_featured`, `preparation_time`) VALUES
(1, 'قهوه ترک', 'Turkish Coffee', 'turkish-coffee', 'قهوه ترک اصیل با طعم بی‌نظیر', 'Authentic Turkish coffee with unique taste', 45000.00, 'turkish-coffee.jpg', 1, 5),
(1, 'اسپرسو', 'Espresso', 'espresso', 'اسپرسو تک شات', 'Single shot espresso', 35000.00, 'espresso.jpg', 0, 3),
(1, 'کاپوچینو', 'Cappuccino', 'cappuccino', 'کاپوچینو با شیر بخار', 'Cappuccino with steamed milk', 55000.00, 'cappuccino.jpg', 1, 5),
(2, 'آیس لته', 'Iced Latte', 'iced-latte', 'لته سرد با یخ', 'Cold latte with ice', 60000.00, 'iced-latte.jpg', 0, 4),
(3, 'چلو کباب کوبیده', 'Koobideh Kebab', 'koobideh-kebab', 'کباب کوبیده با برنج ایرانی', 'Koobideh kebab with Persian rice', 180000.00, 'koobideh.jpg', 1, 20),
(4, 'میرزا قاسمی', 'Mirza Ghasemi', 'mirza-ghasemi', 'میرزا قاسمی شمالی', 'Northern Iranian appetizer', 85000.00, 'mirza-ghasemi.jpg', 1, 10);

-- ============================================
-- ORDERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `order_number` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `order_type` enum('dine_in','takeaway','delivery') DEFAULT 'takeaway',
  `table_number` varchar(10) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','online') DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `order_status` enum('pending','confirmed','preparing','ready','delivered','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL COMMENT 'in minutes',
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `order_status` (`order_status`),
  KEY `payment_status` (`payment_status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ORDER ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(11) UNSIGNED NOT NULL,
  `menu_item_id` int(11) UNSIGNED NOT NULL,
  `item_name_fa` varchar(150) NOT NULL,
  `item_name_en` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `menu_item_id` (`menu_item_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FEEDBACK TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `order_id` int(11) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `food_rating` tinyint(1) DEFAULT NULL CHECK (`food_rating` >= 1 AND `food_rating` <= 5),
  `service_rating` tinyint(1) DEFAULT NULL CHECK (`service_rating` >= 1 AND `service_rating` <= 5),
  `ambiance_rating` tinyint(1) DEFAULT NULL CHECK (`ambiance_rating` >= 1 AND `ambiance_rating` <= 5),
  `review_text` text DEFAULT NULL,
  `admin_response` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `order_id` (`order_id`),
  KEY `rating` (`rating`),
  KEY `is_approved` (`is_approved`),
  KEY `is_featured` (`is_featured`),
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_feedback_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MEDIA TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `media` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'in bytes',
  `mime_type` varchar(100) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `alt_text_fa` varchar(255) DEFAULT NULL,
  `alt_text_en` varchar(255) DEFAULT NULL,
  `category` enum('logo','hero','menu','texture','model','icon','other') DEFAULT 'other',
  `uploaded_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `file_type` (`file_type`),
  KEY `category` (`category`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_media_admin` FOREIGN KEY (`uploaded_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SETTINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','color','url','email') DEFAULT 'text',
  `category` varchar(50) DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0 COMMENT 'accessible via API',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `category` (`category`),
  KEY `is_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('site_name_fa', 'KEY رستوران و کافه', 'text', 'general', 1),
('site_name_en', 'KEY Restaurant & Coffeehouse', 'text', 'general', 1),
('site_tagline_fa', 'تجربه‌ای لوکس از غذا و نوشیدنی', 'text', 'general', 1),
('site_tagline_en', 'A Luxury Dining Experience', 'text', 'general', 1),
('primary_color', '#004647', 'color', 'theme', 1),
('accent_color', '#D4AF37', 'color', 'theme', 1),
('phone_number', '+98 21 1234 5678', 'text', 'contact', 1),
('email', 'info@keyrestaurant.com', 'email', 'contact', 1),
('address_fa', 'تهران، خیابان ولیعصر، پلاک ۱۲۳', 'text', 'contact', 1),
('address_en', 'Tehran, Valiasr St., No. 123', 'text', 'contact', 1),
('instagram_url', 'https://instagram.com/keyrestaurant', 'url', 'social', 1),
('telegram_url', 'https://t.me/keyrestaurant', 'url', 'social', 1),
('whatsapp_number', '+989121234567', 'text', 'social', 1),
('location_lat', '35.6892', 'text', 'contact', 1),
('location_lng', '51.3890', 'text', 'contact', 1),
('location_title_fa', 'موقعیت و تماس', 'text', 'contact', 1),
('opening_hours', '{"saturday":"09:00-23:00","sunday":"09:00-23:00","monday":"09:00-23:00","tuesday":"09:00-23:00","wednesday":"09:00-23:00","thursday":"09:00-23:00","friday":"10:00-24:00"}', 'json', 'contact', 1),
('opening_hours_title_fa', 'ساعت کاری', 'text', 'contact', 1),
('about_title_fa', 'درباره مجموعه', 'text', 'content', 1),
('about_content_fa', '<p>روایت طعم‌های اصیل، قهوه‌های منتخب و میزبانی گرم در فضایی لوکس و آرام.</p>', 'text', 'content', 1),
('about_image', '', 'url', 'content', 1),
('featured_menu_title_fa', 'منوی ویژه', 'text', 'content', 1),
('newsletter_title_fa', 'باشگاه مشتریان', 'text', 'membership', 1),
('newsletter_text_fa', 'برای دریافت خبرهای تازه، پیشنهادهای ویژه و رویدادهای مجموعه، شماره تماس یا ایمیل خود را ثبت کنید.', 'text', 'membership', 1),
('footer_quick_links_title_fa', 'دسترسی سریع', 'text', 'footer', 1),
('footer_contact_title_fa', 'اطلاعات تماس', 'text', 'footer', 1),
('footer_copyright_fa', 'تمامی حقوق محفوظ است.', 'text', 'footer', 1),
('footer_quick_links', '[{"label":"منو","url":"#menu"},{"label":"درباره ما","url":"#about"},{"label":"موقعیت","url":"#location"},{"label":"باشگاه مشتریان","url":"#newsletter"}]', 'json', 'footer', 1),
('hero_title_fa', 'KEY رستوران و کافه', 'text', 'hero', 1),
('hero_title_en', 'KEY Restaurant & Coffeehouse', 'text', 'hero', 1),
('hero_subtitle_fa', 'تجربه‌ای بی‌نظیر از غذا و نوشیدنی', 'text', 'hero', 1),
('hero_subtitle_en', 'An Unforgettable Dining Experience', 'text', 'hero', 1),
('hero_cta_text_fa', 'سفارش آنلاین', 'text', 'hero', 1),
('hero_cta_text_en', 'Order Online', 'text', 'hero', 1),
('webgl_fog_intensity', '0.5', 'number', 'webgl', 1),
('webgl_bloom_intensity', '0.8', 'number', 'webgl', 1),
('webgl_animation_speed', '1.0', 'number', 'webgl', 1),
('delivery_fee', '15000', 'number', 'orders', 1),
('min_order_amount', '50000', 'number', 'orders', 1),
('tax_rate', '9', 'number', 'orders', 1),
('loyalty_points_rate', '1', 'number', 'membership', 1),
('bronze_threshold', '0', 'number', 'membership', 1),
('silver_threshold', '500000', 'number', 'membership', 1),
('gold_threshold', '2000000', 'number', 'membership', 1),
('platinum_threshold', '5000000', 'number', 'membership', 1);

-- ============================================
-- NEWSLETTER SUBSCRIBERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `email` (`email`),
  KEY `is_active` (`is_active`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MEMBERSHIPS TABLE
-- ============================================ 
CREATE TABLE IF NOT EXISTS `memberships` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `level` enum('bronze','silver','gold','platinum') NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `points_earned` int(11) DEFAULT 0,
  `points_redeemed` int(11) DEFAULT 0,
  `started_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `level` (`level`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_memberships_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN SESSIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) UNSIGNED NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `admin_id` (`admin_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_sessions_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACTIVITY LOG TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `action` (`action`),
  KEY `entity_type` (`entity_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_activity_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Dynamic survey schema
-- Dynamic Survey Engine Schema
-- Add to existing schema or run separately

-- ============================================
-- DYNAMIC FORMS TABLE (Form Builder)
-- ============================================
CREATE TABLE IF NOT EXISTS `dynamic_forms` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_name` varchar(150) NOT NULL,
  `form_title_fa` varchar(255) NOT NULL,
  `form_title_en` varchar(255) DEFAULT NULL,
  `form_description_fa` text DEFAULT NULL,
  `form_description_en` text DEFAULT NULL,
  `form_schema` longtext DEFAULT NULL,
  `related_page` varchar(100) DEFAULT 'survey',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `publishing_channels` varchar(255) DEFAULT NULL,
  `branch_id` int(11) UNSIGNED DEFAULT NULL,
  `survey_version` varchar(50) DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `display_order` (`display_order`),
  KEY `idx_dynamic_forms_active_page` (`is_active`, `related_page`, `display_order`),
  KEY `idx_dynamic_forms_dates` (`start_date`, `end_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_forms_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_navigation_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_key` varchar(100) NOT NULL,
  `group_order` int(11) NOT NULL DEFAULT 0,
  `item_key` varchar(100) NOT NULL,
  `url` varchar(190) NOT NULL,
  `icon` varchar(20) DEFAULT NULL,
  `min_role` enum('employee','manager','admin','super_admin') NOT NULL DEFAULT 'employee',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active_pages` JSON DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_navigation_item_key` (`item_key`),
  KEY `idx_admin_navigation_order` (`is_active`, `group_order`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admin_navigation_items` (`group_key`,`group_order`,`item_key`,`url`,`icon`,`min_role`,`sort_order`,`active_pages`) VALUES
('dashboard',10,'dashboard','dashboard.php','📊','employee',10,NULL),
('dashboard',10,'employee_dashboard','employee-dashboard.php','🧑‍💼','employee',20,NULL),
('customers_crm',20,'crm','crm.php','👤','manager',10,NULL),
('customers_crm',20,'crm_reports','crm-reports.php','📣','manager',20,NULL),
('customers_crm',20,'acquisition_sources','acquisition-sources.php','🧲','manager',30,NULL),
('customers_crm',20,'orders','orders.php','📋','employee',40,NULL),
('matches_predictions',30,'matches','matches.php','⚽','manager',10,NULL),
('matches_predictions',30,'predictions','predictions.php','🏆','manager',20,NULL),
('site_content',40,'banners','banners.php','🖼️','manager',10,NULL),
('site_content',40,'social_links','social-links.php','🔗','admin',20,NULL),
('site_content',40,'key_story','key-story.php','📖','manager',30,NULL),
('menu_products',50,'categories','categories.php','📁','manager',10,NULL),
('menu_products',50,'menu_items','menu-items.php','🍽️','manager',20,NULL),
('surveys_evaluation',60,'surveys','surveys.php','📝','admin',10,NULL),
('surveys_evaluation',60,'survey_responses','survey-responses.php','📨','manager',20,NULL),
('surveys_evaluation',60,'feedback','feedback.php','⭐','manager',30,NULL),
('hr_performance_goals',65,'hr_dashboard','hr-dashboard.php','📌','employee',5,'["hr-dashboard.php"]'),
('hr_performance_goals',65,'hr_tests_bank','hr-tests-bank.php','🧠','admin',10,'["hr-tests-bank.php","hr-test-questions.php","hr-test-assignments.php","hr-my-tests.php","hr-test-results.php","hr-test-personnel-report.php","employee-tests.php","employee-assessments.php","hr-test-report.php","evaluation-builder.php"]'),
('hr_performance_goals',65,'hr_role_duties','hr-role-duties.php','✅','manager',20,'["hr-role-duties.php","hr-checklist-templates.php","hr-checklist-assignments.php","hr-checklist-submissions.php","hr-checklist-approvals.php","hr-checklist-progress.php"]'),
('hr_performance_goals',65,'hr_kpi_definitions','hr-kpi-definitions.php','📈','manager',30,'["hr-kpi-definitions.php","hr-kpi-assignments.php","hr-kpi-entries.php","hr-kpi-scores.php","hr-kpi-reports.php","employee-performance.php"]'),
('hr_performance_goals',65,'hr_planner_mine','planner.php','📅','employee',40,'["planner.php","planner-today.php","planner-assigned.php","planner-report.php","hr-planner-mine.php","hr-planner-today.php","hr-planner-tomorrow.php","hr-planner-overdue.php","hr-planner-referred.php","hr-planner-reports.php"]'),
('hr_performance_goals',65,'hr_okr_objectives','okr-objectives.php','🎯','manager',50,'["okr-objectives.php","okr-key-results.php","okr-actions.php","okr-progress.php","tmo-review.php","tmo-dashboard.php","hr-okr-objectives.php","hr-okr-key-results.php","hr-okr-actions.php","hr-okr-progress.php","hr-tmo-reviews.php"]'),
('hr_performance_goals',65,'hr_performance_summary','hr-performance-summary.php','📊','manager',60,'["hr-performance-summary.php","employee-performance.php"]'),
('pool_leads_group',70,'pool_leads','pool-leads.php','🏊','manager',10,NULL),
('analytics_reports',80,'analytics','analytics.php','📈','manager',10,NULL),
('analytics_reports',80,'analytics_traffic_sources','analytics-traffic-sources.php','🧭','manager',20,NULL),
('analytics_reports',80,'visitor_analytics','visitor-analytics.php','🧩','manager',30,NULL),
('analytics_reports',80,'analytics_live','analytics-live.php','🟢','manager',40,NULL),
('analytics_reports',80,'analytics_geographic','analytics-geographic.php','🌍','manager',50,NULL),
('analytics_reports',80,'analytics_device','analytics-device.php','📱','manager',60,NULL),
('analytics_reports',80,'analytics_export','analytics-export.php','📤','manager',70,NULL),
('files_documents',90,'media','media.php','🗂️','manager',10,NULL),
('users_access',100,'users','users.php','👥','admin',10,NULL),
('settings_system',110,'settings','settings.php','⚙️','admin',10,NULL),
('settings_system',110,'system_update','system-update.php','⬆️','super_admin',20,NULL);

-- ============================================
-- SURVEY RESPONSES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `survey_responses` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) UNSIGNED DEFAULT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `response_data` longtext DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_mobile` varchar(20) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `branch_id` int(11) UNSIGNED DEFAULT NULL,
  `satisfaction_score` tinyint DEFAULT NULL,
  `is_dissatisfied` tinyint(1) NOT NULL DEFAULT 0,
  `crm_follow_up` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `submitted_at` (`submitted_at`),
  KEY `idx_survey_customer_phone` (`customer_phone`),
  KEY `idx_survey_responses_form` (`form_id`),
  KEY `idx_survey_responses_submitted` (`submitted_at`),
  KEY `idx_survey_responses_mobile` (`customer_mobile`),
  CONSTRAINT `fk_responses_form` FOREIGN KEY (`form_id`) REFERENCES `dynamic_forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_responses_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_responses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE SURVEY FORM
-- ============================================
INSERT IGNORE INTO `dynamic_forms` (`form_name`, `form_title_fa`, `form_title_en`, `form_description_fa`, `form_schema`, `related_page`, `is_active`) VALUES
('customer_satisfaction', 'نظرسنجی رضایت مشتری', 'Customer Satisfaction Survey', 'لطفاً نظر خود را درباره تجربه خرید به اشتراک بگذارید',
'{
  "fields": [
    {
      "id": "overall_rating",
      "type": "stars",
      "label_fa": "امتیاز کلی",
      "label_en": "Overall Rating",
      "required": true,
      "max_stars": 5
    },
    {
      "id": "food_quality",
      "type": "stars",
      "label_fa": "کیفیت غذا",
      "label_en": "Food Quality",
      "required": true,
      "max_stars": 5
    },
    {
      "id": "service_quality",
      "type": "stars",
      "label_fa": "کیفیت سرویس",
      "label_en": "Service Quality",
      "required": true,
      "max_stars": 5
    },
    {
      "id": "ambiance",
      "type": "stars",
      "label_fa": "فضای رستوران",
      "label_en": "Ambiance",
      "required": false,
      "max_stars": 5
    },
    {
      "id": "visit_frequency",
      "type": "multiple_choice",
      "label_fa": "چند وقت یکبار از ما خرید می‌کنید؟",
      "label_en": "How often do you visit us?",
      "required": true,
      "options": [
        {"value": "first_time", "label_fa": "اولین بار", "label_en": "First time"},
        {"value": "weekly", "label_fa": "هفتگی", "label_en": "Weekly"},
        {"value": "monthly", "label_fa": "ماهانه", "label_en": "Monthly"},
        {"value": "rarely", "label_fa": "به ندرت", "label_en": "Rarely"}
      ]
    },
    {
      "id": "recommend",
      "type": "multiple_choice",
      "label_fa": "آیا ما را به دیگران توصیه می‌کنید؟",
      "label_en": "Would you recommend us?",
      "required": true,
      "options": [
        {"value": "definitely", "label_fa": "قطعاً بله", "label_en": "Definitely yes"},
        {"value": "probably", "label_fa": "احتماالً بله", "label_en": "Probably yes"},
        {"value": "not_sure", "label_fa": "مطمئن نیستم", "label_en": "Not sure"},
        {"value": "probably_not", "label_fa": "احتمالاً خیر", "label_en": "Probably not"},
        {"value": "definitely_not", "label_fa": "قطعاً خیر", "label_en": "Definitely not"}
      ]
    },
    {
      "id": "comments",
      "type": "textarea",
      "label_fa": "نظرات و پیشنهادات",
      "label_en": "Comments and Suggestions",
      "required": false,
      "placeholder_fa": "نظرات خود را اینجا بنویسید...",
      "placeholder_en": "Write your comments here...",
      "max_length": 500
    },
    {
      "id": "contact_permission",
      "type": "checkbox",
      "label_fa": "موافقم که برای پیگیری با من تماس بگیرید",
      "label_en": "I agree to be contacted for follow-up",
      "required": false
    }
  ]
}', 'survey', 1);


-- CRM, content, prediction, security, employee, and analytics schema

CREATE TABLE IF NOT EXISTS `crm_customers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `first_purchase_date` date DEFAULT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `total_purchase_volume` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reminder_date` date DEFAULT NULL,
  `acquisition_source` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `surveys_completed_count` int(11) NOT NULL DEFAULT 0,
  `last_visit_date` date DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `attended_match_event` tinyint(1) NOT NULL DEFAULT 0,
  `customer_status` varchar(100) NOT NULL DEFAULT 'new_customer',
  `points_balance` int(11) NOT NULL DEFAULT 0,
  `rewards_notes` text DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crm_mobile` (`mobile`),
  KEY `idx_crm_mobile` (`mobile`),
  KEY `idx_crm_email` (`email`),
  KEY `idx_crm_birth_date` (`birth_date`),
  KEY `idx_crm_reminder_date` (`reminder_date`),
  KEY `idx_crm_acquisition_source` (`acquisition_source`),
  KEY `idx_crm_last_visit_date` (`last_visit_date`),
  KEY `idx_crm_created_at` (`created_at`),
  KEY `idx_crm_attended` (`attended_match_event`),
  KEY `idx_crm_customer_status` (`customer_status`),
  CONSTRAINT `fk_crm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_timelines` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) UNSIGNED NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_crm_timeline_customer_date` (`customer_id`, `event_date`),
  CONSTRAINT `fk_crm_timeline_customer` FOREIGN KEY (`customer_id`) REFERENCES `crm_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_banners` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `mobile_image` varchar(255) DEFAULT NULL,
  `display_location` varchar(50) NOT NULL DEFAULT 'homepage',
  `match_id` int(11) UNSIGNED DEFAULT NULL,
  `menu_item_id` int(11) UNSIGNED DEFAULT NULL,
  `category_id` int(11) UNSIGNED DEFAULT NULL,
  `loyalty_campaign` varchar(150) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hero_active_order` (`active_status`, `display_order`),
  KEY `idx_hero_start_end` (`start_date`, `end_date`),
  KEY `idx_hero_display_location` (`display_location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matches` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `participation_conditions` text DEFAULT NULL,
  `team_a` varchar(120) DEFAULT NULL,
  `team_b` varchar(120) DEFAULT NULL,
  `team_one_name` varchar(120) DEFAULT NULL,
  `team_two_name` varchar(120) DEFAULT NULL,
  `team_one_logo` varchar(255) DEFAULT NULL,
  `team_two_logo` varchar(255) DEFAULT NULL,
  `match_date` date DEFAULT NULL,
  `kickoff_time` time DEFAULT NULL,
  `broadcast_time` time DEFAULT NULL,
  `final_score_team_a` int(11) DEFAULT NULL,
  `final_score_team_b` int(11) DEFAULT NULL,
  `final_team_one_score` int(11) DEFAULT NULL,
  `final_team_two_score` int(11) DEFAULT NULL,
  `final_result_status` varchar(50) DEFAULT NULL,
  `match_finished` tinyint(1) NOT NULL DEFAULT 0,
  `prediction_open_at` datetime DEFAULT NULL,
  `prediction_close_at` datetime DEFAULT NULL,
  `prediction_start_at` datetime DEFAULT NULL,
  `prediction_end_at` datetime DEFAULT NULL,
  `match_start_at` datetime DEFAULT NULL,
  `match_end_at` datetime DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `campaign_status` varchar(50) NOT NULL DEFAULT 'active',
  `participant_count` int(11) NOT NULL DEFAULT 0,
  `banner_id` int(11) UNSIGNED DEFAULT NULL,
  `menu_item_id` int(11) UNSIGNED DEFAULT NULL,
  `campaign_target` varchar(150) DEFAULT NULL,
  `reward_title` varchar(200) DEFAULT NULL,
  `points_reward` int(11) NOT NULL DEFAULT 0,
  `reward_points` int(11) NOT NULL DEFAULT 0,
  `reward_description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `active_for_prediction` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_matches_date` (`match_date`),
  KEY `idx_matches_team_a` (`team_a`),
  KEY `idx_matches_team_b` (`team_b`),
  KEY `idx_matches_prediction_window` (`prediction_open_at`, `prediction_close_at`),
  KEY `idx_matches_active_status` (`is_active`, `active_for_prediction`, `status`),
  KEY `idx_matches_status` (`status`),
  KEY `idx_matches_prediction_start` (`prediction_start_at`),
  KEY `idx_matches_prediction_end` (`prediction_end_at`),
  KEY `idx_matches_match_start` (`match_start_at`),
  KEY `idx_matches_finished` (`match_finished`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `predictions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_last_name` varchar(150) DEFAULT NULL,
  `customer_mobile` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `match_id` int(11) UNSIGNED DEFAULT NULL,
  `team_one_name` varchar(120) DEFAULT NULL,
  `team_two_name` varchar(120) DEFAULT NULL,
  `predicted_team_one_score` tinyint UNSIGNED DEFAULT NULL,
  `predicted_team_two_score` tinyint UNSIGNED DEFAULT NULL,
  `predicted_score_team_a` tinyint UNSIGNED DEFAULT NULL,
  `predicted_score_team_b` tinyint UNSIGNED DEFAULT NULL,
  `prediction_content` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `is_winner` tinyint(1) NOT NULL DEFAULT 0,
  `evaluated_at` datetime DEFAULT NULL,
  `points_awarded` int(11) NOT NULL DEFAULT 0,
  `crm_follow_up` tinyint(1) NOT NULL DEFAULT 0,
  `wants_reservation` tinyint(1) NOT NULL DEFAULT 0,
  `reserve_table_interest` tinyint(1) NOT NULL DEFAULT 0,
  `source` varchar(150) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `crm_matched` tinyint(1) NOT NULL DEFAULT 0,
  `customer_exists` tinyint(1) NOT NULL DEFAULT 0,
  `attended_match_time` tinyint(1) NOT NULL DEFAULT 0,
  `is_correct_prediction` tinyint(1) NOT NULL DEFAULT 0,
  `crm_match` tinyint(1) NOT NULL DEFAULT 0,
  `attended_match` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prediction_mobile_match` (`mobile`, `match_id`),
  KEY `idx_predictions_mobile` (`mobile`),
  KEY `idx_predictions_customer_mobile` (`customer_mobile`),
  KEY `idx_predictions_customer_id` (`customer_id`),
  KEY `idx_predictions_match` (`match_id`),
  KEY `idx_predictions_winner` (`is_winner`),
  KEY `idx_predictions_created_at` (`created_at`),
  KEY `idx_predictions_submitted_at` (`submitted_at`),
  CONSTRAINT `fk_predictions_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_versions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `version_name` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_system_versions_name` (`version_name`),
  KEY `idx_system_versions_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_performance` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `score_breakdown` longtext DEFAULT NULL,
  `reward` varchar(255) DEFAULT NULL,
  `penalty` varchar(255) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `evaluated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employee_period` (`admin_id`, `period_month`),
  KEY `idx_employee_performance_month_score` (`period_month`, `score`),
  KEY `idx_employee_performance_period_score` (`period_id`, `score`),
  CONSTRAINT `fk_employee_performance_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_performance_evaluator` FOREIGN KEY (`evaluated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acquisition_sources` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_acquisition_title` (`title`),
  KEY `idx_acquisition_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `social_links` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `icon_image` varchar(255) DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_social_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_customer_statuses` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title_fa` varchar(100) NOT NULL,
  `title_en` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crm_status_title_en` (`title_en`),
  KEY `idx_crm_status_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_roles` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_code` varchar(100) NOT NULL,
  `title_fa` varchar(190) NOT NULL,
  `title_en` varchar(190) DEFAULT NULL,
  `department` varchar(100) NOT NULL,
  `parent_role_code` varchar(100) DEFAULT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_managerial` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `source_label` varchar(190) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_role_code` (`role_code`),
  KEY `idx_hr_roles_parent` (`parent_role_code`),
  KEY `idx_hr_roles_department` (`department`, `status`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_periods` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `period_type` enum('daily','shift','weekly','monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'monthly',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `jalali_label` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_periods_type_status` (`period_type`, `status`, `starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_dynamic_fields` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) UNSIGNED DEFAULT NULL,
  `field_key` varchar(120) NOT NULL,
  `label` varchar(190) NOT NULL,
  `field_type` enum('text','textarea','number','select','multi_select','checkbox','date','json') NOT NULL DEFAULT 'text',
  `options_json` longtext DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `default_value` longtext DEFAULT NULL,
  `weight` decimal(8,2) DEFAULT NULL,
  `visible_to` enum('employee','manager','hr','tmo','admin','all') NOT NULL DEFAULT 'all',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_dynamic_field` (`module_key`, `entity_type`, `entity_id`, `field_key`),
  KEY `idx_hr_dynamic_fields_lookup` (`module_key`, `entity_type`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_dynamic_field_values` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_id` int(11) UNSIGNED NOT NULL,
  `module_key` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) UNSIGNED NOT NULL,
  `subject_user_id` int(11) UNSIGNED DEFAULT NULL,
  `value_json` longtext DEFAULT NULL,
  `submitted_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_dynamic_values_entity` (`module_key`, `entity_type`, `entity_id`),
  KEY `idx_hr_dynamic_values_subject` (`subject_user_id`, `field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `actor_user_id` int(11) UNSIGNED DEFAULT NULL,
  `old_value_json` longtext DEFAULT NULL,
  `new_value_json` longtext DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_audit_entity` (`module_key`, `entity_type`, `entity_id`, `created_at`),
  KEY `idx_hr_audit_actor` (`actor_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_module_settings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` varchar(100) NOT NULL,
  `setting_key` varchar(120) NOT NULL,
  `setting_value_json` longtext DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_module_setting` (`module_key`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_standards` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `standard_key` varchar(120) NOT NULL,
  `standard_group` varchar(120) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `source_label` varchar(190) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_business_standard_key` (`standard_key`),
  KEY `idx_business_standards_group_status` (`standard_group`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_standard_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `standard_id` int(11) UNSIGNED NOT NULL,
  `item_key` varchar(120) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_business_standard_item` (`standard_id`, `item_key`),
  KEY `idx_business_standard_items_status` (`standard_id`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `planner_tasks` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_user_id` int(11) UNSIGNED NOT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role_key` varchar(80) DEFAULT NULL,
  `task_date` date NOT NULL,
  `due_at` datetime DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `shift_code` varchar(60) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','done','cancelled','postponed','overdue') NOT NULL DEFAULT 'pending',
  `progress_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `source_module` varchar(100) DEFAULT NULL,
  `source_entity_type` varchar(100) DEFAULT NULL,
  `source_entity_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_objective_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_kr_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_action_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_kpi_score_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_checklist_item_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_customer_id` int(11) UNSIGNED DEFAULT NULL,
  `linked_followup_id` int(11) UNSIGNED DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `recurrence_rule` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_planner_owner_date` (`owner_user_id`, `task_date`, `status`),
  KEY `idx_planner_assigned` (`assigned_by`, `task_date`),
  KEY `idx_planner_department` (`department`, `role_key`, `status`),
  KEY `idx_planner_due` (`status`, `due_at`),
  KEY `idx_planner_source` (`source_module`, `source_entity_type`, `source_entity_id`),
  KEY `idx_planner_links` (`linked_objective_id`, `linked_kr_id`, `linked_action_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `planner_task_logs` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `old_status` varchar(40) DEFAULT NULL,
  `new_status` varchar(40) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_planner_logs_task` (`task_id`, `created_at`),
  KEY `idx_planner_logs_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `planner_task_comments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_planner_comments_task` (`task_id`, `created_at`),
  KEY `idx_planner_comments_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_role_duties` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `duty_code` varchar(140) DEFAULT NULL,
  `role_code` varchar(100) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `responsibility_type` enum('daily','shift','weekly','monthly','as_needed') NOT NULL DEFAULT 'daily',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `standard_key` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_role_duty_code` (`duty_code`),
  UNIQUE KEY `uniq_hr_role_duty` (`role_code`, `title`),
  KEY `idx_hr_role_duty_status` (`status`, `role_code`),
  KEY `idx_hr_role_duty_priority` (`priority`, `responsibility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_templates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_key` varchar(120) NOT NULL,
  `template_code` varchar(120) DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `period_type` enum('daily','shift','weekly','monthly','custom') NOT NULL DEFAULT 'daily',
  `requires_manager_approval` tinyint(1) NOT NULL DEFAULT 0,
  `requires_inspector_approval` tinyint(1) NOT NULL DEFAULT 0,
  `items_json` longtext DEFAULT NULL,
  `standard_key` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_checklist_template_key` (`template_key`),
  UNIQUE KEY `uniq_hr_checklist_template_code` (`template_code`),
  KEY `idx_hr_checklist_template_status` (`status`, `role_code`),
  KEY `idx_hr_checklist_template_period` (`period_type`, `department`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` int(11) UNSIGNED NOT NULL,
  `template_code` varchar(120) NOT NULL,
  `item_code` varchar(140) NOT NULL,
  `title` varchar(190) NOT NULL,
  `phase` varchar(60) NOT NULL DEFAULT 'during_shift',
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `has_quality_score` tinyint(1) NOT NULL DEFAULT 0,
  `max_quality_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `has_note` tinyint(1) NOT NULL DEFAULT 0,
  `can_create_planner_task` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_checklist_item_code` (`template_code`, `item_code`),
  KEY `idx_hr_checklist_items_template` (`template_id`, `status`, `sort_order`),
  KEY `idx_hr_checklist_items_phase` (`phase`, `is_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_key` varchar(120) NOT NULL,
  `title` varchar(190) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_checklist_category_key` (`category_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` int(11) UNSIGNED NOT NULL,
  `assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role',
  `assigned_scope_id` varchar(120) DEFAULT NULL,
  `assigned_employee_id` int(11) UNSIGNED DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'assigned',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_assignment_scope` (`assigned_scope_type`, `assigned_scope_id`, `status`),
  KEY `idx_hr_checklist_assignment_owner` (`assigned_employee_id`, `status`, `starts_at`),
  KEY `idx_hr_checklist_assignment_template` (`template_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_submissions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `template_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `checklist_date` date NOT NULL,
  `shift_code` varchar(60) DEFAULT NULL,
  `completion_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_quality_score` decimal(8,2) NOT NULL DEFAULT 0.00,
  `answers_json` longtext DEFAULT NULL,
  `manager_id` int(11) UNSIGNED DEFAULT NULL,
  `approval_status` varchar(30) NOT NULL DEFAULT 'pending',
  `approval_notes` text DEFAULT NULL,
  `status` enum('draft','submitted','manager_approved','inspector_approved','rejected') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_submission_assignment` (`assignment_id`, `status`),
  KEY `idx_hr_checklist_submission_employee` (`employee_id`, `checklist_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_submission_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) UNSIGNED NOT NULL,
  `checklist_item_id` int(11) UNSIGNED NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `quality_score` decimal(6,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `issue_flag` tinyint(1) NOT NULL DEFAULT 0,
  `corrective_task_id` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_submission_item` (`submission_id`, `checklist_item_id`),
  KEY `idx_hr_submission_item_issue` (`issue_flag`, `corrective_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_checklist_approvals` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) UNSIGNED NOT NULL,
  `approver_id` int(11) UNSIGNED NOT NULL,
  `approval_type` enum('manager','inspector') NOT NULL,
  `status` enum('approved','rejected') NOT NULL,
  `note` text DEFAULT NULL,
  `approved_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_checklist_approval_submission` (`submission_id`, `approval_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_definitions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_key` varchar(120) DEFAULT NULL,
  `kpi_code` varchar(120) DEFAULT NULL,
  `code` varchar(120) DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role_code` varchar(100) DEFAULT NULL,
  `role_key` varchar(100) DEFAULT NULL,
  `standard_group` varchar(120) DEFAULT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'performance',
  `formula_key` varchar(120) DEFAULT NULL,
  `unit` varchar(60) DEFAULT NULL,
  `unit_label` varchar(80) DEFAULT NULL,
  `target_value` decimal(14,4) DEFAULT NULL,
  `min_value` decimal(14,4) DEFAULT NULL,
  `max_value` decimal(14,4) DEFAULT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 0.00,
  `direction` enum('positive','negative') NOT NULL DEFAULT 'positive',
  `calculation_type` varchar(80) NOT NULL DEFAULT 'simple_percent',
  `rag_green_threshold` decimal(8,2) DEFAULT NULL,
  `rag_yellow_threshold` decimal(8,2) DEFAULT NULL,
  `max_score_percent` decimal(8,2) NOT NULL DEFAULT 100.00,
  `description` text DEFAULT NULL,
  `standard_key` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_kpi_definition_key` (`kpi_key`),
  UNIQUE KEY `uniq_hr_kpi_definition_code` (`kpi_code`),
  UNIQUE KEY `uniq_hr_kpi_definition_code_phase6` (`code`),
  KEY `idx_hr_kpi_definition_status` (`status`, `category`),
  KEY `idx_hr_kpi_definition_scope` (`department`, `role_code`, `status`),
  KEY `idx_hr_kpi_definition_scope_phase6` (`department`, `role_key`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_id` int(11) UNSIGNED NOT NULL,
  `assigned_scope_type` enum('employee','role','department','all') NOT NULL DEFAULT 'role',
  `assigned_scope_id` varchar(120) DEFAULT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_assignment_scope` (`assigned_scope_type`, `assigned_scope_id`, `period_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_entries` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `kpi_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `actual_value` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `manual_score` decimal(8,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `entered_by` int(11) UNSIGNED DEFAULT NULL,
  `entered_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_entry_period` (`period_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_scores` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `kpi_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `actual_value` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `target_value` decimal(14,4) DEFAULT NULL,
  `score_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `weighted_score` decimal(8,2) NOT NULL DEFAULT 0.00,
  `rag_status` enum('green','yellow','red') NOT NULL DEFAULT 'red',
  `calculated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_score_assignment` (`assignment_id`, `period_id`),
  KEY `idx_hr_kpi_score_rag` (`rag_status`, `calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_kpi_corrective_actions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_score_id` int(11) UNSIGNED NOT NULL,
  `planner_task_id` int(11) UNSIGNED DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_user_id` int(11) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `idx_hr_kpi_corrective_score` (`kpi_score_id`, `status`),
  KEY `idx_hr_kpi_corrective_task` (`planner_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_objectives` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `target_month` char(7) DEFAULT NULL,
  `scope_type` enum('company','department','team') NOT NULL DEFAULT 'company',
  `scope_id` varchar(120) DEFAULT NULL,
  `owner_user_id` int(11) UNSIGNED DEFAULT NULL,
  `tmo_user_id` int(11) UNSIGNED DEFAULT NULL,
  `status` enum('draft','active','reviewed','closed','archived') NOT NULL DEFAULT 'draft',
  `manual_progress_percent` decimal(6,2) DEFAULT NULL,
  `calculated_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `final_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_objective_period` (`period_id`, `target_month`, `status`),
  KEY `idx_okr_objective_tmo` (`tmo_user_id`, `status`),
  KEY `idx_okr_objective_scope` (`scope_type`, `scope_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_key_results` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `kr_type` enum('numeric','descriptive') NOT NULL DEFAULT 'numeric',
  `target_value` decimal(14,4) DEFAULT NULL,
  `current_value` decimal(14,4) DEFAULT NULL,
  `unit_label` varchar(80) DEFAULT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 1.00,
  `manual_progress_percent` decimal(6,2) DEFAULT NULL,
  `calculated_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `final_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_kr_objective` (`objective_id`, `status`),
  KEY `idx_okr_kr_progress` (`final_progress_percent`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_actions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `kr_id` int(11) UNSIGNED DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `owner_user_id` int(11) UNSIGNED DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','done','cancelled','overdue') NOT NULL DEFAULT 'pending',
  `planner_task_id` int(11) UNSIGNED DEFAULT NULL,
  `manual_progress_percent` decimal(6,2) DEFAULT NULL,
  `calculated_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `final_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_action_objective` (`objective_id`, `status`),
  KEY `idx_okr_action_kr` (`kr_id`, `status`),
  KEY `idx_okr_action_planner` (`planner_task_id`),
  KEY `idx_okr_action_owner_due` (`owner_user_id`, `due_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_kpi_links` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED DEFAULT NULL,
  `kr_id` int(11) UNSIGNED DEFAULT NULL,
  `kpi_definition_id` int(11) UNSIGNED DEFAULT NULL,
  `kpi_assignment_id` int(11) UNSIGNED DEFAULT NULL,
  `weight` decimal(6,2) NOT NULL DEFAULT 1.00,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_okr_kpi_link_objective` (`objective_id`, `status`),
  KEY `idx_okr_kpi_link_kr` (`kr_id`, `status`),
  KEY `idx_okr_kpi_link_kpi` (`kpi_definition_id`, `kpi_assignment_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `okr_progress_logs` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` enum('objective','kr','action') NOT NULL,
  `entity_id` int(11) UNSIGNED NOT NULL,
  `source` enum('manual','planner','kpi','system') NOT NULL DEFAULT 'manual',
  `old_progress_percent` decimal(6,2) DEFAULT NULL,
  `new_progress_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_okr_progress_entity` (`entity_type`, `entity_id`, `created_at`),
  KEY `idx_okr_progress_source` (`source`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tmo_reviews` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `objective_id` int(11) UNSIGNED NOT NULL,
  `tmo_user_id` int(11) UNSIGNED NOT NULL,
  `review_date` date NOT NULL,
  `result_summary` text DEFAULT NULL,
  `blockers` text DEFAULT NULL,
  `decisions` text DEFAULT NULL,
  `next_actions` text DEFAULT NULL,
  `final_score` decimal(6,2) DEFAULT NULL,
  `status` enum('draft','submitted','approved','closed') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tmo_review_objective` (`objective_id`, `status`),
  KEY `idx_tmo_review_user_date` (`tmo_user_id`, `review_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_evaluation_categories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `title` varchar(160) NOT NULL,
  `form_type` varchar(60) NOT NULL DEFAULT 'employee_performance',
  `allow_self_evaluation` tinyint(1) NOT NULL DEFAULT 0,
  `prevent_duplicate_responses` tinyint(1) NOT NULL DEFAULT 1,
  `manual_result_entry` tinyint(1) NOT NULL DEFAULT 0,
  `external_link` varchar(500) DEFAULT NULL,
  `age_guidance` varchar(120) DEFAULT NULL,
  `question_count` int(11) DEFAULT NULL,
  `intended_use` varchar(180) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `applicable_role` varchar(60) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_eval_category_code` (`code`),
  KEY `idx_hr_eval_category_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_evaluation_criteria` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int(11) UNSIGNED NOT NULL,
  `code` varchar(80) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `input_type` varchar(40) NOT NULL DEFAULT 'numeric',
  `options_json` longtext DEFAULT NULL,
  `weight` decimal(7,2) NOT NULL DEFAULT 0.00,
  `max_score` decimal(7,2) NOT NULL DEFAULT 100.00,
  `include_in_score` tinyint(1) NOT NULL DEFAULT 1,
  `visibility` varchar(40) NOT NULL DEFAULT 'manager',
  `applicable_role` varchar(60) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_eval_criterion_code` (`category_id`, `code`),
  KEY `idx_hr_eval_criterion_category` (`category_id`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_evaluation_periods` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `period_type` varchar(60) NOT NULL DEFAULT 'monthly',
  `period_key` varchar(60) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `visibility` varchar(40) NOT NULL DEFAULT 'manager',
  `description` text DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_eval_period_status` (`status`, `period_type`),
  KEY `idx_hr_eval_period_key` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_assessment_tests` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `test_code` varchar(80) NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT 'other',
  `age_guidance` varchar(120) DEFAULT NULL,
  `question_count` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `external_link` varchar(500) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `source_license` varchar(160) DEFAULT NULL,
  `scoring_method_type` varchar(40) NOT NULL DEFAULT 'manual',
  `import_metadata` longtext DEFAULT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `intended_use` varchar(180) DEFAULT NULL,
  `time_limit_minutes` int(11) DEFAULT NULL,
  `assigned_role` varchar(60) DEFAULT NULL,
  `assigned_department` varchar(100) DEFAULT NULL,
  `allow_retake` tinyint(1) NOT NULL DEFAULT 0,
  `retake_policy` enum('free','manager_approval_required') NOT NULL DEFAULT 'manager_approval_required',
  `show_disclaimer` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_assessment_test_code` (`test_code`),
  KEY `idx_hr_assessment_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_dimensions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` int(11) UNSIGNED NOT NULL,
  `code` varchar(80) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `positive_label` varchar(120) DEFAULT NULL,
  `negative_label` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_dimension` (`test_id`, `code`),
  KEY `idx_hr_test_dimension_test` (`test_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_questions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` int(11) UNSIGNED NOT NULL,
  `dimension_id` int(11) UNSIGNED DEFAULT NULL,
  `code` varchar(80) NOT NULL,
  `question_text` text NOT NULL,
  `answer_type` varchar(40) NOT NULL DEFAULT 'scale_5',
  `question_type` varchar(40) DEFAULT NULL,
  `options_json` longtext DEFAULT NULL,
  `weight` decimal(7,2) NOT NULL DEFAULT 1.00,
  `scoring_direction` varchar(20) NOT NULL DEFAULT 'positive',
  `score_direction` varchar(20) DEFAULT NULL,
  `is_reverse_scored` tinyint(1) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `role_visibility` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_question` (`test_id`, `code`),
  KEY `idx_hr_test_question_test` (`test_id`, `is_active`, `sort_order`),
  KEY `idx_hr_test_question_dimension` (`dimension_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_assignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` int(11) UNSIGNED NOT NULL,
  `target_type` varchar(40) NOT NULL DEFAULT 'employee',
  `target_id` varchar(120) DEFAULT NULL,
  `employee_id` int(11) UNSIGNED DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` varchar(60) DEFAULT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'active',
  `allow_retake` tinyint(1) NOT NULL DEFAULT 0,
  `max_attempts` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `show_result_to_employee` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `assigned_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_test_assignment_test` (`test_id`, `status`),
  KEY `idx_hr_test_assignment_employee` (`employee_id`, `status`),
  KEY `idx_hr_test_assignment_scope` (`department`, `role`, `status`),
  KEY `idx_hr_test_assignment_target` (`target_type`, `target_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_responses` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED DEFAULT NULL,
  `attempt_id` int(11) UNSIGNED DEFAULT NULL,
  `test_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'in_progress',
  `answers_json` longtext DEFAULT NULL,
  `dimension_scores_json` longtext DEFAULT NULL,
  `profile_output` varchar(180) DEFAULT NULL,
  `normalized_score` decimal(6,2) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_test_response_employee` (`employee_id`, `status`),
  KEY `idx_hr_test_response_test` (`test_id`, `submitted_at`),
  KEY `idx_hr_test_response_assignment` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_options` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(500) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `score_value` decimal(8,2) NOT NULL DEFAULT 0.00,
  `dimension_code` varchar(80) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_option` (`question_id`, `slug`),
  KEY `idx_hr_test_option_question` (`question_id`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_scoring_rules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `rule_type` varchar(60) NOT NULL DEFAULT 'positive',
  `rule_config_json` longtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_scoring_rule` (`test_id`, `slug`),
  KEY `idx_hr_test_scoring_test` (`test_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_attempts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `test_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `attempt_no` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'in_progress',
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_attempt_no` (`assignment_id`, `employee_id`, `attempt_no`),
  KEY `idx_hr_test_attempt_employee` (`employee_id`, `status`),
  KEY `idx_hr_test_attempt_test` (`test_id`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_results` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) UNSIGNED DEFAULT NULL,
  `assignment_id` int(11) UNSIGNED DEFAULT NULL,
  `test_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `overall_score` decimal(6,2) DEFAULT NULL,
  `result_level` varchar(120) DEFAULT NULL,
  `profile_code` varchar(120) DEFAULT NULL,
  `dimension_scores_json` longtext DEFAULT NULL,
  `strengths_json` longtext DEFAULT NULL,
  `improvements_json` longtext DEFAULT NULL,
  `recommendations_json` longtext DEFAULT NULL,
  `warnings_json` longtext DEFAULT NULL,
  `analysis_disclaimer` varchar(500) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'final',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_test_result_attempt` (`attempt_id`),
  KEY `idx_hr_test_result_employee` (`employee_id`, `created_at`),
  KEY `idx_hr_test_result_test` (`test_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_retake_requests` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) UNSIGNED NOT NULL,
  `test_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `request_note` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) UNSIGNED DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_test_retake_assignment` (`assignment_id`, `employee_id`, `status`),
  KEY `idx_hr_test_retake_test` (`test_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_test_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` int(11) UNSIGNED DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `context_json` longtext DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_test_audit_entity` (`entity_type`, `entity_id`, `created_at`),
  KEY `idx_hr_test_audit_actor` (`actor_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_assessment_results` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `test_id` int(11) UNSIGNED NOT NULL,
  `completion_date` date NOT NULL,
  `result_summary` text DEFAULT NULL,
  `score_value` varchar(120) DEFAULT NULL,
  `result_type` varchar(120) DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `hr_notes` text DEFAULT NULL,
  `visibility` varchar(40) NOT NULL DEFAULT 'private',
  `recorded_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_assessment_result_employee` (`employee_id`, `completion_date`),
  KEY `idx_hr_assessment_result_test` (`test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_evaluations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `evaluator_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `category_group` varchar(50) NOT NULL DEFAULT 'common',
  `category_id` int(11) UNSIGNED DEFAULT NULL,
  `scores` JSON NOT NULL,
  `answers` longtext DEFAULT NULL,
  `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `category_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `source_type` varchar(40) NOT NULL DEFAULT 'peer',
  `notes` text DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_eval_once_form` (`evaluator_id`, `employee_id`, `period_id`, `category_id`, `period_month`),
  KEY `idx_eval_employee_month` (`employee_id`, `period_month`),
  KEY `idx_eval_period_category` (`period_id`, `category_id`),
  KEY `idx_eval_category_group` (`category_group`),
  CONSTRAINT `fk_eval_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eval_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_monthly_inputs` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `department_kpi_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_monthly_inputs` (`employee_id`, `period_month`),
  CONSTRAINT `fk_monthly_inputs_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monthly_inputs_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_score_history` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `period_id` int(11) UNSIGNED DEFAULT NULL,
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `department_kpi_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `category_breakdown` longtext DEFAULT NULL,
  `source_breakdown` longtext DEFAULT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_score_employee_month` (`employee_id`, `period_month`),
  KEY `idx_score_month_final` (`period_month`, `final_score`),
  KEY `idx_score_period_final` (`period_id`, `final_score`),
  CONSTRAINT `fk_score_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_rewards` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `reward_date` date NOT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rewards_employee_date` (`employee_id`, `reward_date`),
  CONSTRAINT `fk_rewards_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rewards_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_warnings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `warning_date` date NOT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_warnings_employee_date` (`employee_id`, `warning_date`),
  CONSTRAINT `fk_warnings_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_warnings_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `key_story_settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NULL,
    `subtitle` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `image` VARCHAR(500) NULL,
    `gallery` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pool_leads` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `pool_name` VARCHAR(100) DEFAULT NULL,
    `customer_type` VARCHAR(100) DEFAULT NULL,
    `acquisition_source` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `status` ENUM('new', 'contacted', 'converted', 'rejected') NOT NULL DEFAULT 'new',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pool_mobile` (`mobile`),
    KEY `idx_pool_leads_pool_name` (`pool_name`),
    KEY `idx_pool_leads_customer_type` (`customer_type`),
    KEY `idx_pool_source` (`acquisition_source`),
    KEY `idx_pool_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traffic_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(64) NULL,
    `country` VARCHAR(100) DEFAULT 'Unknown',
    `city` VARCHAR(100) DEFAULT 'Unknown',
    `isp` VARCHAR(255) NULL,
    `referrer` VARCHAR(500) NULL,
    `landing_page` VARCHAR(500) NULL,
    `user_agent` TEXT NULL,
    `browser` VARCHAR(100) NULL,
    `os` VARCHAR(100) NULL,
    `device` VARCHAR(50) NULL,
    `language` VARCHAR(10) NULL,
    `visit_duration` INT NULL,
    `pages_viewed` INT NOT NULL DEFAULT 1,
    `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_traffic_session` (`session_id`),
    KEY `idx_traffic_ip` (`ip_address`),
    KEY `idx_traffic_date` (`created_at`),
    KEY `idx_traffic_country` (`country`),
    KEY `idx_traffic_referrer` (`referrer`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traffic_sources` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_name` VARCHAR(100) NOT NULL,
    `source_type` VARCHAR(50) NOT NULL DEFAULT 'unknown',
    `visits_count` INT NOT NULL DEFAULT 0,
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_source_date` (`source_name`, `date`),
    KEY `idx_source_type` (`source_type`),
    KEY `idx_source_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_sessions` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(64) NULL,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `current_page` VARCHAR(500) NULL,
    `source_name` VARCHAR(150) NULL,
    `device_type` VARCHAR(50) NULL,
    `browser` VARCHAR(100) NULL,
    `os` VARCHAR(100) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_session` (`session_id`),
    KEY `idx_session_active` (`is_active`, `last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_locations` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `country` VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `city` VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `visits_count` INT NOT NULL DEFAULT 0,
    `date` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_location_date` (`country`, `city`, `date`),
    KEY `idx_location_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traffic_statistics` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_date` DATE NOT NULL,
    `total_visits` INT NOT NULL DEFAULT 0,
    `unique_visitors` INT NOT NULL DEFAULT 0,
    `total_page_views` INT NOT NULL DEFAULT 0,
    `bounce_rate` DECIMAL(5,2) NULL,
    `avg_duration` INT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- RUNTIME ANALYTICS TABLES
-- ============================================
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) NOT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `batch` int NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'completed',
  `error_message` text DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_migrations_name` (`migration_name`),
  KEY `idx_schema_migrations_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seed_registry` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seed_key` varchar(190) NOT NULL,
  `seed_file` varchar(255) NOT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `batch` int NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `rows_inserted` int NOT NULL DEFAULT 0,
  `rows_updated` int NOT NULL DEFAULT 0,
  `rows_skipped` int NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_seed_registry_key` (`seed_key`),
  KEY `idx_seed_registry_status` (`status`),
  KEY `idx_seed_registry_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `setup_run_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `run_type` varchar(80) NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `summary` text DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setup_run_type` (`run_type`, `created_at`),
  KEY `idx_setup_run_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analytics_visitors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_uuid` varchar(64) NOT NULL,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `masked_ip` varchar(64) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Unknown',
  `city` varchar(100) DEFAULT 'Unknown',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_analytics_visitor_uuid` (`visitor_uuid`),
  KEY `idx_analytics_visitors_uuid` (`visitor_uuid`),
  KEY `idx_analytics_visitors_device` (`device_type`),
  KEY `idx_analytics_visitors_browser` (`browser`),
  KEY `idx_analytics_visitors_os` (`os`),
  KEY `idx_analytics_visitors_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analytics_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_uuid` varchar(64) NOT NULL,
  `visitor_uuid` varchar(64) NOT NULL,
  `started_at` datetime NOT NULL,
  `last_activity_at` datetime NOT NULL,
  `landing_page` varchar(500) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `medium` varchar(100) DEFAULT NULL,
  `campaign` varchar(150) DEFAULT NULL,
  `utm_source` varchar(100) DEFAULT NULL,
  `utm_medium` varchar(100) DEFAULT NULL,
  `utm_campaign` varchar(150) DEFAULT NULL,
  `utm_term` varchar(150) DEFAULT NULL,
  `utm_content` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_analytics_session_uuid` (`session_uuid`),
  KEY `idx_analytics_sessions_uuid` (`session_uuid`),
  KEY `idx_analytics_sessions_visitor` (`visitor_uuid`),
  KEY `idx_analytics_sessions_started` (`started_at`),
  KEY `idx_analytics_sessions_activity` (`last_activity_at`),
  KEY `idx_analytics_sessions_source` (`source`),
  KEY `idx_analytics_sessions_medium` (`medium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analytics_pageviews` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_uuid` varchar(64) NOT NULL,
  `session_uuid` varchar(64) NOT NULL,
  `page_url` varchar(1000) DEFAULT NULL,
  `page_path` varchar(500) DEFAULT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `screen_width` int(11) DEFAULT NULL,
  `screen_height` int(11) DEFAULT NULL,
  `browser_language` varchar(50) DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `viewed_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_analytics_pageviews_visitor` (`visitor_uuid`),
  KEY `idx_analytics_pageviews_session` (`session_uuid`),
  KEY `idx_analytics_pageviews_viewed` (`viewed_at`),
  KEY `idx_analytics_pageviews_path` (`page_path`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_analytics_logs` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `customer_id` int(11) UNSIGNED DEFAULT NULL,
  `source_type` varchar(100) DEFAULT NULL,
  `source_name` varchar(150) DEFAULT NULL,
  `campaign_type` varchar(100) DEFAULT NULL,
  `entry_link` varchar(500) DEFAULT NULL,
  `referrer_url` varchar(500) DEFAULT NULL,
  `utm_source` varchar(100) DEFAULT NULL,
  `utm_medium` varchar(100) DEFAULT NULL,
  `utm_campaign` varchar(150) DEFAULT NULL,
  `landing_page` varchar(500) DEFAULT NULL,
  `current_page` varchar(500) DEFAULT NULL,
  `next_page` varchar(500) DEFAULT NULL,
  `related_module` varchar(100) DEFAULT NULL,
  `related_record_id` int(11) UNSIGNED DEFAULT NULL,
  `event_type` enum('external_entry','page_view','banner_view','banner_click','match_view','prediction_start','prediction_submit','category_view','menu_item_view','survey_view','survey_start','survey_submit','crm_link_entry','exit') NOT NULL DEFAULT 'page_view',
  `target_action` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `branch_id` int(11) UNSIGNED DEFAULT NULL,
  `is_new_visitor` tinyint(1) NOT NULL DEFAULT 0,
  `is_converted` tinyint(1) NOT NULL DEFAULT 0,
  `duration_seconds` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_logs_session` (`session_id`),
  KEY `idx_visitor_logs_source` (`source_type`, `source_name`),
  KEY `idx_visitor_logs_pages` (`landing_page`(191), `current_page`(191), `next_page`(191)),
  KEY `idx_visitor_logs_action` (`target_action`, `is_converted`),
  KEY `idx_visitor_logs_related` (`related_module`, `related_record_id`),
  KEY `idx_visitor_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Consolidated seed data

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('location_lat', '35.6892', 'text', 'contact', 1),
('location_lng', '51.3890', 'text', 'contact', 1),
('location_title_fa', 'موقعیت و تماس', 'text', 'contact', 1),
('opening_hours_title_fa', 'ساعت کاری', 'text', 'contact', 1),
('about_title_fa', 'درباره مجموعه', 'text', 'content', 1),
('about_content_fa', '<p>روایت طعم‌های اصیل، قهوه‌های منتخب و میزبانی گرم در فضایی لوکس و آرام.</p>', 'text', 'content', 1),
('about_image', '', 'url', 'content', 1),
('featured_menu_title_fa', 'منوی ویژه', 'text', 'content', 1),
('newsletter_title_fa', 'باشگاه مشتریان', 'text', 'membership', 1),
('newsletter_text_fa', 'برای دریافت خبرهای تازه، پیشنهادهای ویژه و رویدادهای مجموعه، شماره تماس یا ایمیل خود را ثبت کنید.', 'text', 'membership', 1),
('footer_quick_links_title_fa', 'دسترسی سریع', 'text', 'footer', 1),
('footer_contact_title_fa', 'اطلاعات تماس', 'text', 'footer', 1),
('footer_copyright_fa', 'تمامی حقوق محفوظ است.', 'text', 'footer', 1),
('footer_quick_links', '[{"label":"منو","url":"#menu"},{"label":"درباره ما","url":"#about"},{"label":"موقعیت","url":"#location"},{"label":"باشگاه مشتریان","url":"#newsletter"}]', 'json', 'footer', 1);

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('site_description_fa', 'تجربه‌ای لوکس از غذا و نوشیدنی', 'text', 'general', 1),
('logo_image', '', 'url', 'general', 1),
('seo_title_fa', 'رستوران و کافیشاپ کي', 'text', 'seo', 1),
('seo_description_fa', 'رستوران و کافیشاپ کي', 'text', 'seo', 1),
('default_language', 'fa', 'text', 'general', 1),
('jalali_calendar_enabled', '1', 'boolean', 'general', 0),
('balad_map_url', 'https://balad.ir/location?latitude=35.6892&longitude=51.3890', 'url', 'contact', 1),
('lotus_logo_image', '', 'url', 'lotus', 1),
('lotus_title_fa', ' رستوران و کافیشاپ کي', 'text', 'lotus', 1),
('lotus_subtitle_fa', 'تجربه‌ای بی‌نظیر از غذا و نوشیدنی', 'text', 'lotus', 1),
('lotus_description_fa', '', 'text', 'lotus', 1),
('lotus_cta_text_fa', '', 'text', 'lotus', 1),
('lotus_cta_link', '#menu', 'url', 'lotus', 1),
('lotus_active', '1', 'boolean', 'lotus', 1);

INSERT IGNORE INTO `acquisition_sources` (`title`, `sort_order`, `active`) VALUES
('Instagram',10,1),('Telegram',20,1),('Google',30,1),('Balad',40,1),('Friend Referral',50,1),('Walk-in',60,1),('Website',70,1),('Advertisement',80,1),('Other',90,1);

INSERT IGNORE INTO `crm_customer_statuses` (`title_fa`, `title_en`, `color`, `sort_order`, `is_active`) VALUES
('مشتری جدید','new_customer','#0d6efd',10,1),('وفادار','loyal_customer','#198754',20,1),('VIP','vip','#6f42c1',30,1),('ناراضی','dissatisfied_customer','#dc3545',40,1),('ریسک ریزش','churn_risk','#fd7e14',50,1);

INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'Instagram','📷','https://instagram.com/keyrestaurant',10,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='Instagram');
INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'Telegram','✈️','https://t.me/keyrestaurant',20,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='Telegram');
INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'WhatsApp','💬','https://wa.me/989121234567',30,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='WhatsApp');

INSERT IGNORE INTO `key_story_settings` (`title`, `subtitle`, `description`, `active`) 
VALUES (
    'داستان کي',
    'سفری در دل طعم و معنا',
    'رستوران و کافیشاپ کي، جایی که هر لحظه، خاطره‌ای تازه می‌سازیم. از بهترین مواد اولیه تا خدمات بی‌نظیر، همه چیز اینجا برای شما است.',
    1
);

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('balad_map_url','https://balad.ir/location?latitude=35.6892&longitude=51.3890','url','contact',1);
