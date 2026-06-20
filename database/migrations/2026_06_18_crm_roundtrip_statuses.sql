-- CRM Excel roundtrip and dynamic customer statuses.
ALTER TABLE `crm_customers`
  MODIFY `mobile` varchar(20) DEFAULT NULL,
  MODIFY `tags` text DEFAULT NULL,
  MODIFY `customer_status` varchar(100) NOT NULL DEFAULT 'new_customer',
  ADD KEY `idx_crm_customer_status` (`customer_status`);

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

INSERT IGNORE INTO `crm_customer_statuses` (`title_fa`, `title_en`, `color`, `sort_order`, `is_active`) VALUES
('مشتری جدید','new_customer','#0d6efd',10,1),
('وفادار','loyal_customer','#198754',20,1),
('VIP','vip','#6f42c1',30,1),
('ناراضی','dissatisfied_customer','#dc3545',40,1),
('ریسک ریزش','churn_risk','#fd7e14',50,1);
