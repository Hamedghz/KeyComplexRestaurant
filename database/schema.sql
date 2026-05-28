-- KEY Restaurant & Coffeehouse Database Schema
-- MySQL 8+ / MariaDB compatible
-- Character set: utf8mb4 for full Persian support

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database creation
CREATE DATABASE IF NOT EXISTS `key_restaurant` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `key_restaurant`;

-- ============================================
-- ADMINS TABLE
-- ============================================
CREATE TABLE `admins` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin','manager') DEFAULT 'admin',
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
INSERT INTO `admins` (`username`, `email`, `password`, `full_name`, `role`) VALUES
('admin', 'admin@keyrestaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'super_admin');

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE `users` (
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
CREATE TABLE `menu_categories` (
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
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default categories
INSERT INTO `menu_categories` (`name_fa`, `name_en`, `slug`, `icon`, `sort_order`) VALUES
('نوشیدنی‌های گرم', 'Hot Beverages', 'hot-beverages', '☕', 1),
('نوشیدنی‌های سرد', 'Cold Beverages', 'cold-beverages', '🥤', 2),
('غذاهای اصلی', 'Main Dishes', 'main-dishes', '🍽️', 3),
('پیش غذا', 'Appetizers', 'appetizers', '🥗', 4),
('دسر', 'Desserts', 'desserts', '🍰', 5),
('صبحانه', 'Breakfast', 'breakfast', '🍳', 6);

-- ============================================
-- MENU ITEMS TABLE
-- ============================================
CREATE TABLE `menu_items` (
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
  CONSTRAINT `fk_menu_items_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample menu items
INSERT INTO `menu_items` (`category_id`, `name_fa`, `name_en`, `slug`, `description_fa`, `description_en`, `price`, `image`, `is_featured`, `preparation_time`) VALUES
(1, 'قهوه ترک', 'Turkish Coffee', 'turkish-coffee', 'قهوه ترک اصیل با طعم بی‌نظیر', 'Authentic Turkish coffee with unique taste', 45000.00, 'turkish-coffee.jpg', 1, 5),
(1, 'اسپرسو', 'Espresso', 'espresso', 'اسپرسو تک شات', 'Single shot espresso', 35000.00, 'espresso.jpg', 0, 3),
(1, 'کاپوچینو', 'Cappuccino', 'cappuccino', 'کاپوچینو با شیر بخار', 'Cappuccino with steamed milk', 55000.00, 'cappuccino.jpg', 1, 5),
(2, 'آیس لته', 'Iced Latte', 'iced-latte', 'لته سرد با یخ', 'Cold latte with ice', 60000.00, 'iced-latte.jpg', 0, 4),
(3, 'چلو کباب کوبیده', 'Koobideh Kebab', 'koobideh-kebab', 'کباب کوبیده با برنج ایرانی', 'Koobideh kebab with Persian rice', 180000.00, 'koobideh.jpg', 1, 20),
(4, 'میرزا قاسمی', 'Mirza Ghasemi', 'mirza-ghasemi', 'میرزا قاسمی شمالی', 'Northern Iranian appetizer', 85000.00, 'mirza-ghasemi.jpg', 1, 10);

-- ============================================
-- ORDERS TABLE
-- ============================================
CREATE TABLE `orders` (
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
CREATE TABLE `order_items` (
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
CREATE TABLE `feedback` (
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
CREATE TABLE `media` (
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
CREATE TABLE `settings` (
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
INSERT INTO \`settings\` (\`setting_key\`, \`setting_value\`, \`setting_type\`, \`category\`, \`is_public\`) VALUES
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
('opening_hours', '{"saturday":"09:00-23:00","sunday":"09:00-23:00","monday":"09:00-23:00","tuesday":"09:00-23:00","wednesday":"09:00-23:00","thursday":"09:00-23:00","friday":"10:00-24:00"}', 'json', 'contact', 1),
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
-- MEMBERSHIPS TABLE
-- ============================================
CREATE TABLE \`memberships\` (
  \`id\` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`user_id\` int(11) UNSIGNED NOT NULL,
  \`level\` enum('bronze','silver','gold','platinum') NOT NULL,
  \`discount_percentage\` decimal(5,2) DEFAULT 0.00,
  \`points_earned\` int(11) DEFAULT 0,
  \`points_redeemed\` int(11) DEFAULT 0,
  \`started_at\` datetime NOT NULL,
  \`expires_at\` datetime DEFAULT NULL,
  \`is_active\` tinyint(1) DEFAULT 1,
  \`created_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`updated_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`),
  KEY \`user_id\` (\`user_id\`),
  KEY \`level\` (\`level\`),
  KEY \`is_active\` (\`is_active\`),
  CONSTRAINT \`fk_memberships_user\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN SESSIONS TABLE
-- ============================================
CREATE TABLE \`admin_sessions\` (
  \`id\` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`admin_id\` int(11) UNSIGNED NOT NULL,
  \`session_token\` varchar(255) NOT NULL,
  \`ip_address\` varchar(45) DEFAULT NULL,
  \`user_agent\` varchar(255) DEFAULT NULL,
  \`expires_at\` datetime NOT NULL,
  \`created_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`),
  UNIQUE KEY \`session_token\` (\`session_token\`),
  KEY \`admin_id\` (\`admin_id\`),
  KEY \`expires_at\` (\`expires_at\`),
  CONSTRAINT \`fk_sessions_admin\` FOREIGN KEY (\`admin_id\`) REFERENCES \`admins\` (\`id\`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACTIVITY LOG TABLE
-- ============================================
CREATE TABLE \`activity_log\` (
  \`id\` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`admin_id\` int(11) UNSIGNED DEFAULT NULL,
  \`action\` varchar(100) NOT NULL,
  \`entity_type\` varchar(50) DEFAULT NULL,
  \`entity_id\` int(11) DEFAULT NULL,
  \`description\` text DEFAULT NULL,
  \`ip_address\` varchar(45) DEFAULT NULL,
  \`user_agent\` varchar(255) DEFAULT NULL,
  \`created_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`),
  KEY \`admin_id\` (\`admin_id\`),
  KEY \`action\` (\`action\`),
  KEY \`entity_type\` (\`entity_type\`),
  KEY \`created_at\` (\`created_at\`),
  CONSTRAINT \`fk_activity_admin\` FOREIGN KEY (\`admin_id\`) REFERENCES \`admins\` (\`id\`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
