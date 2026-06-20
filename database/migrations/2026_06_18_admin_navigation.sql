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
('dashboard',10,'dashboard','dashboard.php','📊','employee',10,NULL),('dashboard',10,'employee_dashboard','employee-dashboard.php','🧑‍💼','employee',20,NULL),
('customers_crm',20,'crm','crm.php','👤','manager',10,NULL),('customers_crm',20,'crm_reports','crm-reports.php','📣','manager',20,NULL),('customers_crm',20,'acquisition_sources','acquisition-sources.php','🧲','manager',30,NULL),('customers_crm',20,'orders','orders.php','📋','employee',40,NULL),
('matches_predictions',30,'matches','matches.php','⚽','manager',10,NULL),('matches_predictions',30,'predictions','predictions.php','🏆','manager',20,NULL),
('site_content',40,'banners','banners.php','🖼️','manager',10,NULL),('site_content',40,'social_links','social-links.php','🔗','admin',20,NULL),('site_content',40,'key_story','key-story.php','📖','manager',30,NULL),
('menu_products',50,'categories','categories.php','📁','manager',10,NULL),('menu_products',50,'menu_items','menu-items.php','🍽️','manager',20,NULL),
('surveys_evaluation',60,'surveys','surveys.php','📝','admin',10,NULL),('surveys_evaluation',60,'survey_responses','survey-responses.php','📨','manager',20,NULL),('surveys_evaluation',60,'feedback','feedback.php','⭐','manager',30,NULL),('surveys_evaluation',60,'evaluation_builder','evaluation-builder.php','🧭','admin',40,'["evaluation-builder.php","employee-evaluation-settings.php"]'),('surveys_evaluation',60,'employee_evaluations','employee-evaluations.php','📝','employee',50,NULL),('surveys_evaluation',60,'employee_tests','employee-tests.php','🧠','employee',60,NULL),('surveys_evaluation',60,'employee_performance','employee-performance.php','📈','manager',70,NULL),('surveys_evaluation',60,'employee_assessments','employee-assessments.php','🧪','manager',80,NULL),
('pool_leads_group',70,'pool_leads','pool-leads.php','🏊','manager',10,NULL),
('analytics_reports',80,'analytics','analytics.php','📈','manager',10,NULL),('analytics_reports',80,'analytics_traffic_sources','analytics-traffic-sources.php','🧭','manager',20,NULL),('analytics_reports',80,'visitor_analytics','visitor-analytics.php','🧩','manager',30,NULL),('analytics_reports',80,'analytics_live','analytics-live.php','🟢','manager',40,NULL),('analytics_reports',80,'analytics_geographic','analytics-geographic.php','🌍','manager',50,NULL),('analytics_reports',80,'analytics_device','analytics-device.php','📱','manager',60,NULL),('analytics_reports',80,'analytics_export','analytics-export.php','📤','manager',70,NULL),
('files_documents',90,'media','media.php','🗂️','manager',10,NULL),('users_access',100,'users','users.php','👥','admin',10,NULL),('settings_system',110,'settings','settings.php','⚙️','admin',10,NULL),('settings_system',110,'system_update','system-update.php','⬆️','super_admin',20,NULL);
