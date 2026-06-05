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
  `form_name` varchar(100) NOT NULL,
  `form_title_fa` varchar(200) NOT NULL,
  `form_title_en` varchar(200) DEFAULT NULL,
  `form_description_fa` text DEFAULT NULL,
  `form_description_en` text DEFAULT NULL,
  `form_schema` JSON NOT NULL COMMENT 'JSON schema of form fields',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `display_order` (`display_order`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_forms_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SURVEY RESPONSES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `survey_responses` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` int(11) UNSIGNED NOT NULL,
  `order_id` int(11) UNSIGNED DEFAULT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `response_data` JSON NOT NULL COMMENT 'JSON response data',
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `submitted_at` (`submitted_at`),
  KEY `idx_survey_customer_phone` (`customer_phone`),
  CONSTRAINT `fk_responses_form` FOREIGN KEY (`form_id`) REFERENCES `dynamic_forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_responses_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_responses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE SURVEY FORM
-- ============================================
INSERT IGNORE INTO `dynamic_forms` (`form_name`, `form_title_fa`, `form_title_en`, `form_description_fa`, `form_schema`, `is_active`) VALUES
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
}', 1);


-- CRM, content, prediction, security, employee, and analytics schema

CREATE TABLE IF NOT EXISTS `crm_customers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `first_purchase_date` date DEFAULT NULL,
  `total_orders` int(11) NOT NULL DEFAULT 0,
  `total_purchase_volume` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reminder_date` date DEFAULT NULL,
  `acquisition_source` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `surveys_completed_count` int(11) NOT NULL DEFAULT 0,
  `last_visit_date` date DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `attended_match_event` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crm_mobile` (`mobile`),
  KEY `idx_crm_mobile` (`mobile`),
  KEY `idx_crm_birth_date` (`birth_date`),
  KEY `idx_crm_reminder_date` (`reminder_date`),
  KEY `idx_crm_acquisition_source` (`acquisition_source`),
  KEY `idx_crm_last_visit_date` (`last_visit_date`),
  KEY `idx_crm_created_at` (`created_at`),
  KEY `idx_crm_attended` (`attended_match_event`),
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
  `title` varchar(200) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `mobile_image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hero_active_order` (`active_status`, `display_order`),
  KEY `idx_hero_start_end` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matches` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_a` varchar(120) NOT NULL,
  `team_b` varchar(120) NOT NULL,
  `match_date` date NOT NULL,
  `kickoff_time` time NOT NULL,
  `broadcast_time` time DEFAULT NULL,
  `final_score_team_a` int(11) DEFAULT NULL,
  `final_score_team_b` int(11) DEFAULT NULL,
  `match_finished` tinyint(1) NOT NULL DEFAULT 0,
  `prediction_open_at` datetime NOT NULL,
  `prediction_close_at` datetime NOT NULL,
  `status` enum('scheduled','live','finished','cancelled') NOT NULL DEFAULT 'scheduled',
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
  KEY `idx_matches_finished` (`match_finished`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `predictions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `match_id` int(11) UNSIGNED NOT NULL,
  `predicted_score_team_a` tinyint UNSIGNED NOT NULL,
  `predicted_score_team_b` tinyint UNSIGNED NOT NULL,
  `crm_matched` tinyint(1) NOT NULL DEFAULT 0,
  `customer_exists` tinyint(1) NOT NULL DEFAULT 0,
  `attended_match_time` tinyint(1) NOT NULL DEFAULT 0,
  `is_correct_prediction` tinyint(1) NOT NULL DEFAULT 0,
  `crm_match` tinyint(1) NOT NULL DEFAULT 0,
  `attended_match` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prediction_mobile_match` (`mobile`, `match_id`),
  KEY `idx_predictions_mobile` (`mobile`),
  KEY `idx_predictions_match` (`match_id`),
  KEY `idx_predictions_created_at` (`created_at`),
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
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `reward` varchar(255) DEFAULT NULL,
  `penalty` varchar(255) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `evaluated_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employee_period` (`admin_id`, `period_month`),
  KEY `idx_employee_performance_month_score` (`period_month`, `score`),
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
  `url` varchar(500) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_social_active_order` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_evaluations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `evaluator_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `period_month` char(7) NOT NULL,
  `category_group` varchar(50) NOT NULL DEFAULT 'common',
  `scores` JSON NOT NULL,
  `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_eval_once` (`evaluator_id`, `employee_id`, `period_month`),
  KEY `idx_eval_employee_month` (`employee_id`, `period_month`),
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
  `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `department_kpi_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_score_employee_month` (`employee_id`, `period_month`),
  KEY `idx_score_month_final` (`period_month`, `final_score`),
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
    `acquisition_source` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `status` ENUM('new', 'contacted', 'converted', 'rejected') NOT NULL DEFAULT 'new',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pool_mobile` (`mobile`),
    KEY `idx_pool_source` (`acquisition_source`),
    KEY `idx_pool_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traffic_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `country` VARCHAR(100) NULL,
    `city` VARCHAR(100) NULL,
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
    `source_type` ENUM('direct', 'organic', 'social', 'referral', 'campaign') NOT NULL,
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
    `ip_address` VARCHAR(45) NOT NULL,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_session` (`session_id`),
    KEY `idx_session_active` (`is_active`, `last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_locations` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `country` VARCHAR(100) NOT NULL,
    `city` VARCHAR(100) NULL,
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
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_migrations_name` (`migration_name`),
  KEY `idx_schema_migrations_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analytics_visitors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_uuid` varchar(64) NOT NULL,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
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
('seo_title_fa', 'KEY رستوران و کافه', 'text', 'seo', 1),
('seo_description_fa', 'رستوران و کافه KEY', 'text', 'seo', 1),
('default_language', 'fa', 'text', 'general', 1),
('jalali_calendar_enabled', '1', 'boolean', 'general', 0),
('balad_map_url', 'https://balad.ir/location?latitude=35.6892&longitude=51.3890', 'url', 'contact', 1),
('lotus_logo_image', '', 'url', 'lotus', 1),
('lotus_title_fa', 'KEY رستوران و کافه', 'text', 'lotus', 1),
('lotus_subtitle_fa', 'تجربه‌ای بی‌نظیر از غذا و نوشیدنی', 'text', 'lotus', 1),
('lotus_description_fa', '', 'text', 'lotus', 1),
('lotus_cta_text_fa', '', 'text', 'lotus', 1),
('lotus_cta_link', '#menu', 'url', 'lotus', 1),
('lotus_active', '1', 'boolean', 'lotus', 1);

INSERT IGNORE INTO `acquisition_sources` (`title`, `sort_order`, `active`) VALUES
('Instagram',10,1),('Telegram',20,1),('Google',30,1),('Balad',40,1),('Friend Referral',50,1),('Walk-in',60,1),('Website',70,1),('Advertisement',80,1),('Other',90,1);

INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'Instagram','📷','https://instagram.com/keyrestaurant',10,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='Instagram');
INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'Telegram','✈️','https://t.me/keyrestaurant',20,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='Telegram');
INSERT INTO `social_links` (`title`, `icon`, `url`, `sort_order`, `active`)
SELECT 'WhatsApp','💬','https://wa.me/989121234567',30,1 WHERE NOT EXISTS (SELECT 1 FROM `social_links` WHERE `title`='WhatsApp');

INSERT IGNORE INTO `key_story_settings` (`title`, `subtitle`, `description`, `active`) 
VALUES (
    'داستان KEY',
    'سفری در دل طعم و معنا',
    'KEY رستوران و کافه، جایی که هر لحظه، خاطره‌ای تازه می‌سازیم. از بهترین مواد اولیه تا خدمات بی‌نظیر، همه چیز اینجا برای شما است.',
    1
);

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
('balad_map_url','https://balad.ir/location?latitude=35.6892&longitude=51.3890','url','contact',1);

