-- Homepage extension schema for existing KEY Restaurant & Coffeehouse installs
-- Adds dynamic homepage settings and newsletter subscriber storage.

START TRANSACTION;

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

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `is_public`) VALUES
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
('footer_quick_links', '[{"label":"منو","url":"#menu"},{"label":"درباره ما","url":"#about"},{"label":"موقعیت","url":"#location"},{"label":"باشگاه مشتریان","url":"#newsletter"}]', 'json', 'footer', 1)
ON DUPLICATE KEY UPDATE setting_key = setting_key;

COMMIT;
