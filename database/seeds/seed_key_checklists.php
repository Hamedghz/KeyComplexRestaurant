<?php

/**
 * Idempotent KEY restaurant checklist template and item seed.
 *
 * Creates templates and items only. It does not create checklist assignments,
 * submissions, planner tasks, or user links.
 */

if (!function_exists('keyChecklistTemplateDefinitions')) {
    function keyChecklistTemplateDefinitions(): array {
        return [
            [
                'template_code' => 'internal_manager_daily_visit_checklist',
                'title' => 'چک‌لیست کنترل روزانه مدیر داخلی رستوران',
                'role_code' => 'internal_manager',
                'department' => 'operations',
                'period_type' => 'daily',
                'requires_manager_approval' => 1,
                'requires_inspector_approval' => 1,
                'items' => [
                    ['im_staff_uniform','کنترل لباس فرم کارکنان','start_shift',1,1,5,0,0],
                    ['im_staff_hygiene','کنترل بهداشت فردی، ناخن، مو، دستکش و کلاه','start_shift',1,1,5,0,0],
                    ['im_hall_floor_clean','کنترل نظافت کف سالن','start_shift',1,1,5,0,0],
                    ['im_tables_chairs','کنترل نظافت و چیدمان میزها و صندلی‌ها','start_shift',1,1,5,0,0],
                    ['im_lighting_music_smell','کنترل نور، موسیقی، رایحه و آمادگی محیط سالن','start_shift',1,1,5,0,0],
                    ['im_toilet_cleanliness','کنترل نظافت سرویس‌های بهداشتی، آینه، فلاش‌تانک و شیرآلات','during_shift',1,1,5,0,0],
                    ['im_kitchen_floor','کنترل نظافت کف آشپزخانه','during_shift',1,1,5,0,0],
                    ['im_kebab_machine','کنترل نظافت دستگاه کباب‌زن و اجاق‌ها','during_shift',1,1,5,0,0],
                    ['im_fridge_order','کنترل نظم یخچال‌ها، بسته‌بندی و تاریخ مواد اولیه','during_shift',1,1,5,0,0],
                    ['im_storage_order','کنترل انبار، درب انبار، چیدمان و موجودی اقلام','during_shift',1,1,5,0,0],
                    ['im_cashier_area','کنترل نظم و نظافت حوزه صندوق و پیشخوان','during_shift',1,1,5,0,0],
                    ['im_warmer_device','کنترل دستگاه گرم‌کن بالا و ظروف سرو','during_shift',1,1,5,0,0],
                    ['im_stairs_balcony','کنترل پله‌ها، بالکن، ورودی و پیاده‌رو','during_shift',1,1,5,0,0],
                    ['im_issues_registered','ثبت ایرادات مشاهده‌شده و ارجاع اقدام اصلاحی','end_shift',1,0,null,1,1],
                    ['im_end_shift_security','کنترل پایان شیفت، قفل انبار و ورودی‌ها','end_shift',1,1,5,0,0],
                ],
            ],
            [
                'template_code' => 'cashier_daily_shift_checklist',
                'title' => 'چک‌لیست روزانه صندوقدار',
                'role_code' => 'cashier',
                'department' => 'sales_finance',
                'period_type' => 'shift',
                'requires_manager_approval' => 1,
                'requires_inspector_approval' => 0,
                'items' => [
                    ['cashier_pos_system_health','بررسی سلامت سیستم صندوق، مانیتور و فیش‌پرینتر','start_shift',1,0,null,1,0],
                    ['cashier_paper_rolls','اطمینان از موجود بودن رول کاغذ و حداقل دو رول زاپاس','start_shift',1,0,null,1,0],
                    ['cashier_card_reader_health','بررسی سلامت و اتصال دستگاه کارتخوان','start_shift',1,0,null,1,0],
                    ['cashier_card_transfer_check','اطمینان از واریز شدن مبالغ کارت‌به‌کارت','start_shift',1,0,null,1,0],
                    ['cashier_counter_clean','بررسی تمیزی میز صندوق و نظافت محیط اطراف','start_shift',1,1,5,0,0],
                    ['cashier_menu_unavailable','چک کردن منوی روز و اطلاع از اقلام ناموجود','start_shift',1,0,null,1,0],
                    ['cashier_order_accuracy','ثبت دقیق سفارش‌ها و جلوگیری از اشتباه در فاکتور','during_shift',1,1,5,0,0],
                    ['cashier_payment_accuracy','کنترل صحیح دریافت وجه نقد، کارت و سایر روش‌های پرداخت','during_shift',1,1,5,0,0],
                    ['cashier_expense_record','ثبت هزینه‌های احتمالی یا تنخواه طبق دستور مدیریت','during_shift',0,0,null,1,0],
                    ['cashier_customer_payment_behavior','پاسخگویی مؤدبانه و حرفه‌ای به مشتری هنگام پرداخت','during_shift',1,1,5,0,0],
                    ['cashier_invoice_payment_match','تطبیق فاکتورهای صادرشده با مبالغ دریافتی','end_shift',1,1,5,0,0],
                    ['cashier_technical_issue_report','اعلام فوری خرابی سیستم، پرینتر یا کارتخوان به مدیریت','as_needed',0,0,null,1,1],
                    ['cashier_final_report','تهیه گزارش پایانی صندوق','end_shift',1,0,null,1,0],
                    ['cashier_cash_count','شمارش وجه نقد و تطبیق با گزارش سیستم','end_shift',1,1,5,0,0],
                    ['cashier_handover','تحویل صندوق، گزارش مغایرت و امضای تحویل به مدیریت','end_shift',1,0,null,1,0],
                    ['cashier_equipment_shutdown','خاموش کردن یا ایمن‌سازی تجهیزات طبق دستور پایان شیفت','end_shift',1,0,null,1,0],
                ],
            ],
            [
                'template_code' => 'internal_manager_daily_kpi_checklist',
                'title' => 'چک‌لیست KPI روزانه مدیر داخلی',
                'role_code' => 'internal_manager',
                'department' => 'operations',
                'period_type' => 'daily',
                'requires_manager_approval' => 1,
                'requires_inspector_approval' => 1,
                'items' => [
                    ['im_kpi_checklist_completion','ثبت درصد تکمیل چک‌لیست‌های نظارتی روز','end_shift',1,0,null,1,0],
                    ['im_kpi_staff_discipline','ثبت وضعیت آراستگی و انضباط پرسنل','end_shift',1,1,5,1,0],
                    ['im_kpi_branch_readiness','ثبت امتیاز آمادگی شعبه قبل از سرویس','end_shift',1,1,5,1,0],
                    ['im_kpi_issue_tracking','ثبت ایرادات و وضعیت پیگیری اقدام اصلاحی','end_shift',1,0,null,1,1],
                    ['im_kpi_customer_satisfaction','ثبت وضعیت رضایت مشتری و شکایت‌های روز','end_shift',1,1,5,1,1],
                ],
            ],
            [
                'template_code' => 'hall_captain_daily_checklist',
                'title' => 'چک‌لیست روزانه مسئول سالن',
                'role_code' => 'hall_captain',
                'department' => 'hall',
                'period_type' => 'shift',
                'requires_manager_approval' => 1,
                'requires_inspector_approval' => 1,
                'items' => [
                    ['hall_staff_grooming_uniform','بررسی آراستگی و لباس فرم پرسنل سالن','start_shift',1,1,5,0,0],
                    ['hall_tables_chairs_clean','چک کردن نظافت کامل میزها و صندلی‌ها','start_shift',1,1,5,0,0],
                    ['hall_light_temp_music','بررسی نور، دما و موسیقی محیط','start_shift',1,1,5,0,0],
                    ['hall_menu_health_stock','اطمینان از موجودی و سلامت منوها','start_shift',1,0,null,1,0],
                    ['hall_toilet_schedule','چک کردن نظافت سرویس‌های بهداشتی طبق تایم‌بندی','during_shift',1,1,5,0,0],
                    ['hall_service_items_stock','کنترل موجودی نمک، فلفل، دستمال، سس و اقلام پذیرایی','during_shift',1,0,null,1,0],
                    ['hall_dishes_spoons_clean','بررسی نظافت ظروف، قاشق و چنگال بدون لک','during_shift',1,1,5,0,0],
                    ['hall_drink_fridge','چک کردن یخچال نوشیدنی‌ها و پر کردن آن','during_shift',1,0,null,1,0],
                    ['hall_customer_complaints','مدیریت و ثبت شکایات مشتریان در صورت وجود','during_shift',0,0,null,1,1],
                    ['hall_service_speed','کنترل سرعت سرویس‌دهی و جمع‌آوری میزها','during_shift',1,1,5,0,0],
                    ['hall_final_cleaning','بررسی خروجی سالن و نظافت نهایی قبل از پایان شیفت','end_shift',1,1,5,0,0],
                    ['hall_shift_incident_report','ثبت گزارش اتفاقات خاص شیفت','end_shift',1,0,null,1,0],
                ],
            ],
            [
                'template_code' => 'delivery_rider_operational_checklist',
                'title' => 'چک‌لیست عملیاتی پیک موتوری',
                'role_code' => 'delivery_rider',
                'department' => 'delivery',
                'period_type' => 'shift',
                'requires_manager_approval' => 1,
                'requires_inspector_approval' => 0,
                'items' => [
                    ['delivery_motor_health','بررسی سلامت فنی موتور، ترمز، چراغ‌ها و لاستیک','start_shift',1,0,null,1,0],
                    ['delivery_box_clean','نظافت و ضدعفونی داخل و بیرون باکس حمل غذا','start_shift',1,1,5,0,0],
                    ['delivery_uniform_helmet','آراستگی لباس فرم و کلاه ایمنی','start_shift',1,1,5,0,0],
                    ['delivery_documents','داشتن مدارک کامل مثل گواهینامه و کارت موتور','start_shift',1,0,null,1,0],
                    ['delivery_card_reader_charge','اطمینان از شارژ دستگاه کارتخوان','start_shift',1,0,null,1,0],
                    ['delivery_phone_gps_charge','اطمینان از شارژ گوشی برای GPS و اپلیکیشن','start_shift',1,0,null,1,0],
                    ['delivery_invoice_items_check','چک کردن فاکتور با اقلام داخل باکس','during_shift',1,1,5,0,0],
                    ['delivery_container_seal','اطمینان از بسته بودن ظروف و عدم نشت سس یا نوشیدنی','during_shift',1,1,5,0,0],
                    ['delivery_hot_cold_separation','چیدمان مرتب غذاها و جدا کردن غذای گرم از سرد','during_shift',1,0,null,1,0],
                    ['delivery_polite_customer_behavior','رفتار محترمانه و استفاده از جملات استاندارد هنگام تحویل','during_shift',1,1,5,0,0],
                    ['delivery_payment_confirmation','اطمینان از دریافت وجه یا تایید پرداخت آنلاین','during_shift',1,0,null,1,0],
                    ['delivery_cash_settlement','تسویه حساب مبالغ نقدی دریافتی از مشتریان','end_shift',1,0,null,1,0],
                    ['delivery_equipment_report','گزارش خرابی تجهیزات به مدیریت','end_shift',0,0,null,1,1],
                ],
            ],
            [
                'template_code' => 'kitchen_daily_checklist',
                'title' => 'چک‌لیست روزانه آشپزخانه',
                'role_code' => 'head_chef',
                'department' => 'kitchen',
                'period_type' => 'daily',
                'requires_manager_approval' => 1,
                'requires_inspector_approval' => 1,
                'items' => [
                    ['kitchen_staff_uniform_hygiene','کنترل لباس فرم، پیش‌بند، کلاه، دستکش و بهداشت فردی کارکنان آشپزخانه','start_shift',1,1,5,0,0],
                    ['kitchen_raw_material_date','کنترل تاریخ، بسته‌بندی و سلامت مواد اولیه','start_shift',1,1,5,0,0],
                    ['kitchen_fridge_order','کنترل نظم و نظافت یخچال‌ها','start_shift',1,1,5,0,0],
                    ['kitchen_equipment_clean','کنترل نظافت اجاق‌ها، کباب‌زن، دم‌کن برنج و تجهیزات پخت','during_shift',1,1,5,0,0],
                    ['kitchen_food_quality_before_send','کنترل کیفیت، طعم، سلامت و ظاهر غذا قبل از ارسال','during_shift',1,1,5,0,0],
                    ['kitchen_waste_area','کنترل نظافت سطل‌های زباله و خروج پسماند','during_shift',1,1,5,0,0],
                    ['kitchen_dishes_clean','کنترل شستشوی ظروف، دیگ‌ها، ملاقه‌ها و سیخ‌ها','end_shift',1,1,5,0,0],
                    ['kitchen_issue_report','ثبت نواقص، خرابی تجهیزات یا کمبود مواد اولیه','end_shift',0,0,null,1,1],
                ],
            ],
            [
                'template_code' => 'marketing_sales_daily_weekly_checklist',
                'title' => 'چک‌لیست روزانه/هفتگی بازاریابی و فروش',
                'role_code' => 'marketing_sales_manager',
                'department' => 'marketing_sales',
                'period_type' => 'weekly',
                'requires_manager_approval' => 1,
                'requires_inspector_approval' => 0,
                'items' => [
                    ['marketing_instagram_comments_reviews','چک کردن و پاسخگویی به نظرات مشتریان در اینستاگرام و پلتفرم‌ها','daily',1,0,null,1,0],
                    ['marketing_daily_stories','انتشار حداقل سه استوری روزانه از محیط و غذاها','daily',1,1,5,0,0],
                    ['marketing_content_production','ساخت و انتشار محتوای برنامه‌ریزی‌شده','weekly',1,1,5,0,0],
                    ['marketing_sales_target_review','بررسی آمار فروش روزانه و مقایسه با هدف','daily',1,0,null,1,0],
                    ['marketing_vip_customer_followup','تماس یا پیگیری مشتریان VIP که مدتی سفارش نداده‌اند','weekly',0,0,null,1,1],
                    ['marketing_campaign_report','ثبت گزارش کمپین، لید، نرخ تبدیل، هزینه و اقدام اصلاحی','weekly',1,0,null,1,0],
                ],
            ],
        ];
    }
}

if (!function_exists('keyChecklistTableHasColumn')) {
    function keyChecklistTableHasColumn(PDO $db, string $table, string $column): bool {
        $stmt = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('keyChecklistsEnsureSchema')) {
    function keyChecklistsEnsureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_templates` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $templateColumns = [
            'template_code' => "`template_code` varchar(120) DEFAULT NULL AFTER `template_key`",
            'department' => "`department` varchar(100) DEFAULT NULL AFTER `role_code`",
            'period_type' => "`period_type` enum('daily','shift','weekly','monthly','custom') NOT NULL DEFAULT 'daily' AFTER `department`",
            'requires_manager_approval' => "`requires_manager_approval` tinyint(1) NOT NULL DEFAULT 0 AFTER `period_type`",
            'requires_inspector_approval' => "`requires_inspector_approval` tinyint(1) NOT NULL DEFAULT 0 AFTER `requires_manager_approval`",
        ];
        foreach ($templateColumns as $column => $definition) {
            if (!keyChecklistTableHasColumn($db, 'hr_checklist_templates', $column)) {
                $db->exec('ALTER TABLE `hr_checklist_templates` ADD COLUMN ' . $definition);
            }
        }
        try { $db->exec('CREATE UNIQUE INDEX `uniq_hr_checklist_template_code` ON `hr_checklist_templates` (`template_code`)'); } catch (Throwable $e) {}
        try { $db->exec('CREATE INDEX `idx_hr_checklist_template_period` ON `hr_checklist_templates` (`period_type`, `department`, `status`)'); } catch (Throwable $e) {}

        $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_items` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('keyChecklistRowHash')) {
    function keyChecklistRowHash(array $row): string {
        return sha1(implode('|', array_map(static fn($value) => (string)$value, $row)));
    }
}

if (!function_exists('seedKeyChecklists')) {
    function seedKeyChecklists(PDO $db, int $actorId = 0): array {
        keyChecklistsEnsureSchema($db);

        $selectTemplate = $db->prepare('SELECT id,template_key,template_code,title,role_code,department,period_type,requires_manager_approval,requires_inspector_approval,items_json,status FROM hr_checklist_templates WHERE template_key = ? OR template_code = ? LIMIT 1');
        $insertTemplate = $db->prepare('INSERT INTO hr_checklist_templates (template_key,template_code,title,role_code,department,period_type,requires_manager_approval,requires_inspector_approval,items_json,status) VALUES (?,?,?,?,?,?,?,?,?,"active")');
        $updateTemplate = $db->prepare('UPDATE hr_checklist_templates SET template_key=?, template_code=?, title=?, role_code=?, department=?, period_type=?, requires_manager_approval=?, requires_inspector_approval=?, items_json=?, status="active" WHERE id=?');
        $selectItem = $db->prepare('SELECT template_code,item_code,title,phase,is_required,has_quality_score,max_quality_score,has_note,can_create_planner_task,sort_order,status FROM hr_checklist_items WHERE template_code = ? AND item_code = ? LIMIT 1');
        $insertItem = $db->prepare('INSERT INTO hr_checklist_items (template_id,template_code,item_code,title,phase,is_required,has_quality_score,max_quality_score,has_note,can_create_planner_task,sort_order,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,"active")');
        $updateItem = $db->prepare('UPDATE hr_checklist_items SET template_id=?, title=?, phase=?, is_required=?, has_quality_score=?, max_quality_score=?, has_note=?, can_create_planner_task=?, sort_order=?, status="active" WHERE template_code=? AND item_code=?');

        $templateInserted = 0;
        $templateUpdated = 0;
        $templateSkipped = 0;
        $itemInserted = 0;
        $itemUpdated = 0;
        $itemSkipped = 0;

        foreach (keyChecklistTemplateDefinitions() as $template) {
            $templateCode = (string)$template['template_code'];
            $itemsJson = json_encode($template['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $targetTemplate = [
                'template_key' => $templateCode,
                'template_code' => $templateCode,
                'title' => $template['title'],
                'role_code' => $template['role_code'],
                'department' => $template['department'],
                'period_type' => $template['period_type'],
                'requires_manager_approval' => (int)$template['requires_manager_approval'],
                'requires_inspector_approval' => (int)$template['requires_inspector_approval'],
                'items_json' => $itemsJson,
                'status' => 'active',
            ];

            $selectTemplate->execute([$templateCode, $templateCode]);
            $existingTemplate = $selectTemplate->fetch(PDO::FETCH_ASSOC);
            if (!$existingTemplate) {
                $insertTemplate->execute([$templateCode, $templateCode, $template['title'], $template['role_code'], $template['department'], $template['period_type'], (int)$template['requires_manager_approval'], (int)$template['requires_inspector_approval'], $itemsJson]);
                $templateId = (int)$db->lastInsertId();
                $templateInserted++;
            } else {
                $templateId = (int)$existingTemplate['id'];
                $existingComparable = $existingTemplate;
                unset($existingComparable['id']);
                if (keyChecklistRowHash($existingComparable) === keyChecklistRowHash($targetTemplate)) {
                    $templateSkipped++;
                } else {
                    $updateTemplate->execute([$templateCode, $templateCode, $template['title'], $template['role_code'], $template['department'], $template['period_type'], (int)$template['requires_manager_approval'], (int)$template['requires_inspector_approval'], $itemsJson, $templateId]);
                    $templateUpdated++;
                }
            }

            $sortOrder = 0;
            foreach ($template['items'] as $item) {
                $sortOrder += 10;
                [$itemCode, $title, $phase, $isRequired, $hasQualityScore, $maxQualityScore, $hasNote, $canCreatePlannerTask] = $item;
                $targetItem = [
                    'template_code' => $templateCode,
                    'item_code' => $itemCode,
                    'title' => $title,
                    'phase' => $phase,
                    'is_required' => (int)$isRequired,
                    'has_quality_score' => (int)$hasQualityScore,
                    'max_quality_score' => $maxQualityScore,
                    'has_note' => (int)$hasNote,
                    'can_create_planner_task' => (int)$canCreatePlannerTask,
                    'sort_order' => $sortOrder,
                    'status' => 'active',
                ];
                $selectItem->execute([$templateCode, $itemCode]);
                $existingItem = $selectItem->fetch(PDO::FETCH_ASSOC);
                if (!$existingItem) {
                    $insertItem->execute([$templateId, $templateCode, $itemCode, $title, $phase, (int)$isRequired, (int)$hasQualityScore, $maxQualityScore, (int)$hasNote, (int)$canCreatePlannerTask, $sortOrder]);
                    $itemInserted++;
                    continue;
                }
                if (keyChecklistRowHash($existingItem) === keyChecklistRowHash($targetItem)) {
                    $itemSkipped++;
                    continue;
                }
                $updateItem->execute([$templateId, $title, $phase, (int)$isRequired, (int)$hasQualityScore, $maxQualityScore, (int)$hasNote, (int)$canCreatePlannerTask, $sortOrder, $templateCode, $itemCode]);
                $itemUpdated++;
            }
        }

        if (function_exists('hrAuditLog')) {
            hrAuditLog('hr_checklists', 'seed', null, 'seed_key_checklists', $actorId ?: null, null, ['templates' => count(keyChecklistTemplateDefinitions()), 'items' => $itemInserted + $itemUpdated + $itemSkipped]);
        }

        return [
            'templates' => count(keyChecklistTemplateDefinitions()),
            'items' => $itemInserted + $itemUpdated + $itemSkipped,
            'templates_inserted' => $templateInserted,
            'templates_updated' => $templateUpdated,
            'templates_skipped' => $templateSkipped,
            'items_inserted' => $itemInserted,
            'items_updated' => $itemUpdated,
            'items_skipped' => $itemSkipped,
        ];
    }
}
