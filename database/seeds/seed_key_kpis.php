<?php

/**
 * Idempotent KEY restaurant KPI definition seed.
 *
 * Creates KPI definitions only. It does not create entries, scores, assignments,
 * or user links.
 */

if (!function_exists('keyKpiDefinitions')) {
    function keyKpiDefinitions(): array {
        return [
            ['internal_manager_daily_checklist_completion','تکمیل چک‌لیست نظارتی روزانه','operations','internal_manager','امتیاز 1 تا 5',5,20,'positive','manual_score',90,70,'بررسی کامل تمام بخش‌ها طبق چک‌لیست روزانه.'],
            ['internal_manager_staff_discipline','آراستگی و انضباط پرسنل','operations','internal_manager','امتیاز 1 تا 5',5,20,'positive','manual_score',null,null,'لباس فرم کامل، نظافت فردی، حضور به‌موقع و رعایت نظم.'],
            ['internal_manager_branch_readiness','آمادگی شعبه قبل از سرویس','operations','internal_manager','امتیاز 1 تا 5',5,20,'positive','manual_score',null,null,'آماده بودن سالن، سرویس‌ها، پیشخوان، تجهیزات و پرسنل قبل از شروع سرویس.'],
            ['internal_manager_issue_tracking','ثبت و پیگیری ایرادات','operations','internal_manager','امتیاز 1 تا 5',5,20,'positive','manual_score',null,null,'ثبت دقیق نواقص، ارجاع برای اصلاح و پیگیری تا بسته‌شدن اقدام.'],
            ['internal_manager_customer_satisfaction_hall','رضایت مشتری و وضعیت سالن','operations','internal_manager','امتیاز 1 تا 5',5,20,'positive','manual_score',null,null,'نبود شکایت مؤثر، کنترل مناسب سرویس و حفظ تجربه مطلوب مشتری.'],

            ['cashier_cash_difference','مغایرت صندوق','sales_finance','cashier','مورد',0,35,'negative','simple_percent',null,null,'میزان مغایرت صندوق در پایان شیفت. هدف: صفر.'],
            ['cashier_invoice_registration_speed','سرعت ثبت فاکتور','sales_finance','cashier','دقیقه',1,20,'negative','simple_percent',null,null,'میانگین زمان ثبت هر فاکتور. هدف: زیر ۱ دقیقه.'],
            ['cashier_customer_complaints','شکایت مشتری از برخورد صندوق‌دار','sales_finance','cashier','مورد در ماه',2,20,'negative','simple_percent',null,null,'تعداد شکایات ثبت‌شده از رفتار یا پاسخگویی صندوق‌دار. هدف: حداکثر ۲ مورد در ماه.'],
            ['cashier_order_accuracy','دقت ثبت سفارش','sales_finance','cashier','درصد',100,25,'positive','simple_percent',null,null,'درصد سفارش‌های بدون خطا نسبت به کل سفارش‌های ثبت‌شده.'],

            ['hall_captain_table_turnover','بهره‌وری میز','hall','hall_captain','دقیقه',3,25,'negative','simple_percent',null,null,'زمان آماده‌سازی مجدد میز پس از خروج مشتری. هدف: زیر ۳ دقیقه.'],
            ['hall_captain_detected_issues','کیفیت نظارت سالن','hall','hall_captain','مورد',5,25,'positive','simple_percent',null,null,'تعداد ایرادات کشف‌شده توسط مسئول سالن یا مدیر، قبل از شکایت مشتری. هدف: حداقل ۵ مورد مؤثر.'],
            ['hall_captain_customer_experience_score','تجربه مشتری در سالن','hall','hall_captain','امتیاز از 5',4.5,30,'positive','simple_percent',null,null,'میانگین نمره نظرسنجی سالن. هدف: بالای ۴.۵ از ۵.'],
            ['hall_captain_cleanliness_readiness','نظافت و آمادگی سالن','hall','hall_captain','درصد',95,20,'positive','simple_percent',null,null,'درصد تکمیل آیتم‌های نظافت، آراستگی، چیدمان و آمادگی سالن.'],

            ['waiter_service_accuracy','دقت سرو','hall','waiter','مورد',0,30,'negative','simple_percent',null,null,'تعداد اشتباه در تحویل غذا به میز. هدف: صفر.'],
            ['waiter_upsell_ratio','فروش جانبی','hall','waiter','درصد',10,30,'positive','simple_percent',null,null,'نسبت فروش پیش‌غذا یا نوشیدنی به تعداد فاکتور. هدف: رشد حداقل ۱۰ درصدی.'],
            ['waiter_drink_service_speed','سرعت سرویس نوشیدنی','hall','waiter','دقیقه',2,20,'negative','simple_percent',null,null,'زمان فاصله بین ثبت سفارش تا رسیدن نوشیدنی. هدف: زیر ۲ دقیقه.'],
            ['waiter_customer_greeting_score','کیفیت استقبال و برخورد','hall','waiter','امتیاز 1 تا 5',5,20,'positive','manual_score',null,null,'کیفیت استقبال، ادب، راهنمایی مشتری و رفتار حرفه‌ای.'],

            ['delivery_average_time','زمان تحویل سفارش','delivery','delivery_rider','دقیقه',20,35,'negative','simple_percent',null,null,'میانگین زمان ارسال از لحظه خروج تا تحویل. هدف: زیر ۲۰ دقیقه.'],
            ['delivery_food_condition_complaints','کیفیت تحویل غذا','delivery','delivery_rider','مورد در ماه',3,30,'negative','simple_percent',null,null,'تعداد گزارش ریختگی، سردی غذا یا آسیب سفارش. هدف: حداکثر ۳ در ماه.'],
            ['delivery_traffic_complaints','انضباط ترافیکی و رفتاری پیک','delivery','delivery_rider','مورد',0,20,'negative','simple_percent',null,null,'تعداد جریمه یا شکایات ترافیکی و رفتاری گزارش‌شده. هدف: صفر.'],
            ['delivery_settlement_accuracy','دقت تسویه پیک','delivery','delivery_rider','درصد',100,15,'positive','simple_percent',null,null,'درصد تسویه‌های بدون مغایرت.'],

            ['marketing_instagram_leads','تعداد سرنخ از اینستاگرام','marketing_sales','marketing_sales_manager','عدد',0,15,'positive','simple_percent',null,null,'دایرکت سفارش یا رزرو، تماس‌های ناشی از پیج و کلیک روی لینک رزرو.'],
            ['marketing_lead_conversion_rate','نرخ تبدیل سرنخ به سفارش یا رزرو','marketing_sales','marketing_sales_manager','درصد',0,20,'positive','simple_percent',null,null,'تعداد سفارش یا رزرو تقسیم بر تعداد سرنخ ضربدر ۱۰۰.'],
            ['marketing_attributed_sales','فروش قابل انتساب به دیجیتال','marketing_sales','marketing_sales_manager','تومان',0,20,'positive','simple_percent',null,null,'فروش ثبت‌شده با کد، برچسب یا پیام «از اینستا».'],
            ['marketing_roas','ROAS تبلیغات','marketing_sales','marketing_sales_manager','نسبت',0,15,'positive','simple_percent',null,null,'درآمد قابل انتساب تقسیم بر هزینه تبلیغ.'],
            ['marketing_cac','CAC تقریبی','marketing_sales','marketing_sales_manager','تومان',0,10,'negative','simple_percent',null,null,'هزینه تبلیغ و تولید محتوا تقسیم بر تعداد مشتری جدید از دیجیتال.'],
            ['marketing_content_calendar_execution','اجرای تقویم محتوا','marketing_sales','page_admin','درصد',100,20,'positive','simple_percent',null,null,'درصد پست‌ها و استوری‌های منتشرشده طبق برنامه.'],
            ['marketing_direct_response_rate','نرخ پاسخگویی دایرکت','marketing_sales','page_admin','درصد',95,20,'positive','simple_percent',null,null,'پیام‌های پاسخ‌داده‌شده تقسیم بر کل پیام‌ها ضربدر ۱۰۰.'],
            ['marketing_average_response_time','زمان متوسط پاسخگویی','marketing_sales','page_admin','دقیقه',15,15,'negative','simple_percent',null,null,'میانگین زمان پاسخ در ساعات کاری.'],
            ['marketing_engagement_rate','نرخ تعامل پست‌ها','marketing_sales','page_admin','درصد',5,20,'positive','simple_percent',null,null,'لایک، کامنت، سیو و شیر تقسیم بر Reach ضربدر ۱۰۰.'],
            ['marketing_content_quality','کیفیت انتشار محتوا','marketing_sales','page_admin','درصد',100,15,'positive','simple_percent',null,null,'درصد پست‌ها با کپشن، هشتگ، لوکیشن و CTA صحیح.'],
            ['video_retention_rate','نرخ نگهداشت ویدئو','marketing_sales','content_creator','درصد',0,20,'positive','simple_percent',null,null,'میانگین درصد تماشای ویدئو تا انتها.'],
            ['video_output_count','تعداد خروجی ویدئو','marketing_sales','content_creator','عدد',0,20,'positive','simple_percent',null,null,'تعداد ریلز یا ویدئو آماده و تحویل‌شده.'],
            ['video_technical_quality','کیفیت فنی ویدئو','marketing_sales','content_creator','درصد',95,20,'positive','simple_percent',null,null,'درصد خروجی بدون ایراد نور، صدا، کادر و لرزش.'],

            ['bsf_lead_count','تعداد لید','marketing_sales','marketing_sales_manager','عدد',0,20,'positive','bsf_component',null,null,'جزء اول فرمول BSF: Lead Count.'],
            ['bsf_conversion_rate','نرخ تبدیل','marketing_sales','marketing_sales_manager','درصد',0,25,'positive','bsf_component',null,null,'جزء دوم فرمول BSF: Conversion Rate.'],
            ['bsf_purchase_count','تعداد خرید','sales_finance',null,'عدد',0,15,'positive','bsf_component',null,null,'جزء سوم فرمول BSF: Purchase Count.'],
            ['bsf_average_purchase','متوسط خرید','sales_finance',null,'تومان',0,20,'positive','bsf_component',null,null,'جزء چهارم فرمول BSF: Average Purchase.'],
            ['bsf_profit_margin','حاشیه سود','finance','accountant','درصد',0,20,'positive','bsf_component',null,null,'جزء پنجم فرمول BSF: Profit Margin.'],

            ['script_compliance_score','امتیاز رعایت اسکریپت فروش','marketing_sales','marketing_sales_manager','درصد',90,15,'positive','manual_score',90,70,'رعایت مسیر گفت‌وگو، سوال‌محوری، زبان مشتری و پایان با CTA.'],
            ['open_question_usage','استفاده از سوال باز','marketing_sales','marketing_sales_manager','درصد',80,10,'positive','manual_score',90,70,'شروع گفت‌وگو با سوال باز به جای ارائه مستقیم محصول.'],
            ['listening_score','امتیاز گوش دادن حرفه‌ای','marketing_sales','marketing_sales_manager','درصد',90,15,'positive','manual_score',90,70,'سوال، سکوت، یادداشت‌برداری، بازتاب صحبت مشتری و قطع نکردن مکالمه.'],
            ['fab_usage_score','امتیاز استفاده از FAB','marketing_sales','marketing_sales_manager','درصد',90,15,'positive','manual_score',90,70,'بیان منفعت قبل از ویژگی و مزیت در گفت‌وگو با مشتری.'],
            ['clear_cta_usage','استفاده از CTA روشن','marketing_sales','marketing_sales_manager','درصد',90,10,'positive','manual_score',90,70,'پایان مکالمه با اقدام بعدی واضح برای مشتری.'],
            ['objection_handling_score','امتیاز مدیریت اعتراض مشتری','marketing_sales','marketing_sales_manager','درصد',90,15,'positive','manual_score',90,70,'مدیریت اعتراض قیمت، زمان، اعتماد، تجربه بد یا مقایسه رقبا بدون واکنش دفاعی.'],

            ['customer_complaint_count','تعداد شکایات مشتری','operations','internal_manager','مورد',0,20,'negative','simple_percent',null,null,'تعداد شکایات ثبت‌شده در دوره.'],
            ['complaint_resolution_time','زمان حل شکایت','operations','internal_manager','ساعت',24,25,'negative','simple_percent',null,null,'میانگین زمان از ثبت شکایت تا حل کامل.'],
            ['complaint_resolution_rate','نرخ حل شکایت','operations','internal_manager','درصد',100,25,'positive','simple_percent',null,null,'درصد شکایات حل‌شده نسبت به کل شکایات.'],
            ['repeat_purchase_after_complaint','خرید مجدد پس از حل شکایت','operations','marketing_sales_manager','درصد',0,15,'positive','simple_percent',null,null,'درصد مشتریان ناراضی که پس از حل مشکل دوباره خرید کرده‌اند.'],
            ['customer_referral_count','تعداد معرفی مشتری','marketing_sales','marketing_sales_manager','عدد',0,15,'positive','simple_percent',null,null,'تعداد مشتریان معرفی‌شده توسط مشتریان قبلی.'],
        ];
    }
}

if (!function_exists('keyKpisTableHasColumn')) {
    function keyKpisTableHasColumn(PDO $db, string $column): bool {
        $stmt = $db->prepare('SHOW COLUMNS FROM hr_kpi_definitions LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('keyKpisEnsureSchema')) {
    function keyKpisEnsureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS `hr_kpi_definitions` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `kpi_key` varchar(120) NOT NULL,
            `kpi_code` varchar(120) DEFAULT NULL,
            `title` varchar(190) NOT NULL,
            `department` varchar(100) DEFAULT NULL,
            `role_code` varchar(100) DEFAULT NULL,
            `category` varchar(100) NOT NULL DEFAULT 'performance',
            `formula_key` varchar(120) DEFAULT NULL,
            `unit` varchar(60) DEFAULT NULL,
            `unit_label` varchar(80) DEFAULT NULL,
            `target_value` decimal(14,4) DEFAULT NULL,
            `weight` decimal(6,2) NOT NULL DEFAULT 0.00,
            `direction` enum('positive','negative') NOT NULL DEFAULT 'positive',
            `calculation_type` varchar(80) NOT NULL DEFAULT 'simple_percent',
            `rag_green_threshold` decimal(8,2) DEFAULT NULL,
            `rag_yellow_threshold` decimal(8,2) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `standard_key` varchar(120) DEFAULT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_hr_kpi_definition_key` (`kpi_key`),
            UNIQUE KEY `uniq_hr_kpi_definition_code` (`kpi_code`),
            KEY `idx_hr_kpi_definition_status` (`status`, `category`),
            KEY `idx_hr_kpi_definition_scope` (`department`, `role_code`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = [
            'kpi_code' => "`kpi_code` varchar(120) DEFAULT NULL AFTER `kpi_key`",
            'department' => "`department` varchar(100) DEFAULT NULL AFTER `title`",
            'role_code' => "`role_code` varchar(100) DEFAULT NULL AFTER `department`",
            'unit_label' => "`unit_label` varchar(80) DEFAULT NULL AFTER `unit`",
            'weight' => "`weight` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `target_value`",
            'direction' => "`direction` enum('positive','negative') NOT NULL DEFAULT 'positive' AFTER `weight`",
            'calculation_type' => "`calculation_type` varchar(80) NOT NULL DEFAULT 'simple_percent' AFTER `direction`",
            'rag_green_threshold' => "`rag_green_threshold` decimal(8,2) DEFAULT NULL AFTER `calculation_type`",
            'rag_yellow_threshold' => "`rag_yellow_threshold` decimal(8,2) DEFAULT NULL AFTER `rag_green_threshold`",
            'description' => "`description` text DEFAULT NULL AFTER `rag_yellow_threshold`",
        ];
        foreach ($columns as $column => $definition) {
            if (!keyKpisTableHasColumn($db, $column)) {
                $db->exec('ALTER TABLE `hr_kpi_definitions` ADD COLUMN ' . $definition);
            }
        }
        try { $db->exec('CREATE UNIQUE INDEX `uniq_hr_kpi_definition_code` ON `hr_kpi_definitions` (`kpi_code`)'); } catch (Throwable $e) {}
        try { $db->exec('CREATE INDEX `idx_hr_kpi_definition_scope` ON `hr_kpi_definitions` (`department`, `role_code`, `status`)'); } catch (Throwable $e) {}
    }
}

if (!function_exists('keyKpiRowHash')) {
    function keyKpiRowHash(array $row): string {
        return sha1(implode('|', array_map(static fn($value) => (string)$value, $row)));
    }
}

if (!function_exists('keyKpiCategory')) {
    function keyKpiCategory(string $department, string $calculationType): string {
        if ($calculationType === 'bsf_component') return 'bsf';
        if ($department === 'marketing_sales') return 'marketing_sales';
        if ($department === 'sales_finance' || $department === 'finance') return 'finance_sales';
        if ($department === 'hall') return 'customer_experience';
        if ($department === 'delivery') return 'delivery';
        return 'operations';
    }
}

if (!function_exists('seedKeyKpis')) {
    function seedKeyKpis(PDO $db, int $actorId = 0): array {
        keyKpisEnsureSchema($db);
        $select = $db->prepare('SELECT kpi_key,kpi_code,title,department,role_code,category,formula_key,unit,unit_label,target_value,weight,direction,calculation_type,rag_green_threshold,rag_yellow_threshold,description,status FROM hr_kpi_definitions WHERE kpi_key = ? OR kpi_code = ? LIMIT 1');
        $insert = $db->prepare('INSERT INTO hr_kpi_definitions (kpi_key,kpi_code,title,department,role_code,category,formula_key,unit,unit_label,target_value,weight,direction,calculation_type,rag_green_threshold,rag_yellow_threshold,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, "active")');
        $update = $db->prepare('UPDATE hr_kpi_definitions SET kpi_key=?, kpi_code=?, title=?, department=?, role_code=?, category=?, formula_key=?, unit=?, unit_label=?, target_value=?, weight=?, direction=?, calculation_type=?, rag_green_threshold=?, rag_yellow_threshold=?, description=?, status="active" WHERE kpi_key=? OR kpi_code=?');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        foreach (keyKpiDefinitions() as $kpi) {
            [$kpiCode, $title, $department, $roleCode, $unitLabel, $targetValue, $weight, $direction, $calculationType, $green, $yellow, $description] = $kpi;
            $category = keyKpiCategory((string)$department, (string)$calculationType);
            $formulaKey = $calculationType === 'bsf_component' ? 'bsf' : null;
            $target = [
                'kpi_key' => $kpiCode,
                'kpi_code' => $kpiCode,
                'title' => $title,
                'department' => $department,
                'role_code' => $roleCode,
                'category' => $category,
                'formula_key' => $formulaKey,
                'unit' => $unitLabel,
                'unit_label' => $unitLabel,
                'target_value' => $targetValue,
                'weight' => $weight,
                'direction' => $direction,
                'calculation_type' => $calculationType,
                'rag_green_threshold' => $green,
                'rag_yellow_threshold' => $yellow,
                'description' => $description,
                'status' => 'active',
            ];
            $select->execute([$kpiCode, $kpiCode]);
            $existing = $select->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                $insert->execute([$kpiCode, $kpiCode, $title, $department, $roleCode, $category, $formulaKey, $unitLabel, $unitLabel, $targetValue, $weight, $direction, $calculationType, $green, $yellow, $description]);
                $inserted++;
                continue;
            }
            if (keyKpiRowHash($existing) === keyKpiRowHash($target)) {
                $skipped++;
                continue;
            }
            $update->execute([$kpiCode, $kpiCode, $title, $department, $roleCode, $category, $formulaKey, $unitLabel, $unitLabel, $targetValue, $weight, $direction, $calculationType, $green, $yellow, $description, $kpiCode, $kpiCode]);
            $updated++;
        }
        if (function_exists('hrAuditLog')) {
            hrAuditLog('hr_kpi_definitions', 'seed', null, 'seed_key_kpis', $actorId ?: null, null, ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped]);
        }
        return ['kpis' => count(keyKpiDefinitions()), 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'messages' => ['KPI seed creates definitions only; no entries, scores, or user assignments are created.']];
    }
}
