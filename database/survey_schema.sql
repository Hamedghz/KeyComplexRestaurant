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
  CONSTRAINT `fk_responses_form` FOREIGN KEY (`form_id`) REFERENCES `dynamic_forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_responses_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_responses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE SURVEY FORM
-- ============================================
INSERT INTO `dynamic_forms` (`form_name`, `form_title_fa`, `form_title_en`, `form_description_fa`, `form_schema`, `is_active`) VALUES
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

COMMIT;
