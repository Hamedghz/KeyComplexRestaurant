-- Phase 9 final HR integration and legacy cleanup.
-- Additive only. Old route files are preserved as wrappers/redirects.

INSERT INTO `hr_module_settings` (`module_key`,`setting_key`,`setting_value_json`,`updated_by`) VALUES
('performance_summary','score_weights','{"kpi":40,"checklist":25,"planner":20,"tests":15}',NULL)
ON DUPLICATE KEY UPDATE setting_value_json=COALESCE(setting_value_json, VALUES(setting_value_json)), updated_at=NOW();

INSERT IGNORE INTO `admin_navigation_items` (`group_key`,`group_order`,`item_key`,`url`,`icon`,`min_role`,`sort_order`,`active_pages`) VALUES
('hr_performance_goals',65,'hr_performance_summary','hr-performance-summary.php','📊','manager',60,'["hr-performance-summary.php","employee-performance.php"]')
ON DUPLICATE KEY UPDATE group_key=VALUES(group_key), group_order=VALUES(group_order), url=VALUES(url), icon=VALUES(icon), min_role=VALUES(min_role), sort_order=VALUES(sort_order), active_pages=VALUES(active_pages), is_active=1;

UPDATE `admin_navigation_items`
SET `is_active` = 0
WHERE `item_key` IN ('evaluation_builder','employee_evaluations','employee_performance','employee_assessments','hr_test_report');
