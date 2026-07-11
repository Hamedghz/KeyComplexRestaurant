<?php

/**
 * Idempotent KEY restaurant personnel role seed.
 *
 * This seed creates role definitions only. It does not create users and does
 * not assign existing admins/users to any role.
 */

if (!function_exists('keyRestaurantRoleDefinitions')) {
    function keyRestaurantRoleDefinitions(): array {
        return [
            ['restaurant_owner','مالک / مدیریت ارشد','Owner / Executive Management','executive',null,0,1,'مالک یا مدیریت ارشد مجموعه که مسئول تصمیم‌گیری نهایی، سیاست‌گذاری، تایید اهداف ماهانه، نظارت کلان و بررسی گزارش‌های مدیریتی است.'],
            ['restaurant_manager','مدیر رستوران','Restaurant Manager','management','restaurant_owner',1,1,'مدیر اصلی رستوران که مسئول نظارت کلان بر عملیات، منابع انسانی، فروش، کیفیت خدمات، اهداف ماهانه و هماهنگی واحدها است.'],
            ['tmo_owner','مسئول TMO / مالک پیگیری اهداف تیمی','Team Management Objective Owner','management','restaurant_manager',2,1,'شخص مسئول پیگیری اهداف ماهانه، KRها، اقدامات، تسک‌ها، KPIهای مرتبط و ثبت بازبینی مدیریتی. TMO یک هدف نیست؛ یک شخص یا نقش مدیریتی است.'],
            ['internal_manager','مدیر داخلی','Internal Operations Manager','operations','restaurant_manager',2,1,'مسئول نظارت روزانه بر عملیات رستوران، نظم، بهداشت، کیفیت سرویس، اجرای چک‌لیست‌ها، پیگیری ایرادات و هماهنگی بین سالن، آشپزخانه، صندوق و خدمات.'],
            ['hr_manager','منابع انسانی','Human Resources','hr','restaurant_manager',2,1,'مسئول امور منابع انسانی، نقش‌ها، ارزیابی کارکنان، آزمون‌ها، آموزش، پیگیری عملکرد و همکاری با مدیریت برای توسعه نیروی انسانی.'],
            ['accountant','حسابدار','Accountant','finance','restaurant_manager',2,0,'مسئول امور مالی، کنترل گزارش‌های مالی، صندوق، هزینه‌ها، درآمد، مغایرت‌ها، طلب‌ها، بدهی‌ها و گزارش‌های مالی دوره‌ای.'],
            ['cashier','صندوق‌دار','Cashier','sales_finance','internal_manager',3,0,'مسئول ثبت دقیق سفارش‌ها، دریافت وجه، مدیریت صندوق، پاسخگویی تلفنی و حضوری، گزارش پایان شیفت و تحویل صندوق.'],
            ['hall_captain','مسئول سالن','Hall Captain','hall','internal_manager',3,1,'مسئول نظارت بر سالن، تجربه مشتری، آراستگی پرسنل سالن، نظم، نظافت، سرویس‌دهی، شکایات مشتریان و آماده‌سازی سالن.'],
            ['waiter','سالن‌دار / گارسون','Waiter','hall','hall_captain',4,0,'مسئول استقبال، راهنمایی مشتری، تسلط به منو، سرو غذا و نوشیدنی، جمع‌آوری میزها، رعایت ادب، آراستگی و استانداردهای سالن.'],
            ['hall_service','نیروی خدمات سالن','Hall Service Staff','hall','hall_captain',4,0,'مسئول نظافت سالن، میزها، صندلی‌ها، شیشه‌ها، سرویس‌ها، سماور، ظروف، زباله‌ها، آراستگی محیط و همکاری با سالن و آشپزخانه.'],
            ['head_chef','سرآشپز','Head Chef','kitchen','internal_manager',3,1,'مسئول نهایی کیفیت محصولات تولیدی، نظارت بر کارکنان آشپزخانه، آماده‌سازی غذاها، کنترل مواد اولیه، پخت، بسته‌بندی و نظافت آشپزخانه.'],
            ['assistant_chef','کمک‌آشپز','Assistant Chef','kitchen','head_chef',4,0,'مسئول کمک به سرآشپز، آماده‌سازی مواد اولیه، پخت، کنترل کیفیت غذا قبل از ارسال، نظافت تجهیزات، یخچال‌ها و رعایت بهداشت فردی.'],
            ['kitchen_service','نیروی خدمات آشپزخانه / ظرفشویی','Kitchen Service / Dishwasher','kitchen','head_chef',4,0,'مسئول نظافت آشپزخانه، ظرفشویی، دستگاه‌ها، اجاق‌ها، کباب‌زن، سطل زباله، یخچال‌ها، آماده‌سازی اولیه و همکاری با آشپزخانه.'],
            ['delivery_rider','پیک موتوری','Delivery Rider','delivery','internal_manager',4,0,'مسئول تحویل سریع، سالم و محترمانه غذا، کنترل اقلام سفارش، حمل ایمن، رعایت قوانین رانندگی، دریافت وجه و تسویه.'],
            ['marketing_sales_manager','مسئول بازاریابی و فروش','Marketing and Sales Manager','marketing_sales','restaurant_manager',2,1,'مسئول جذب مشتری جدید، حفظ مشتریان فعلی، مدیریت کمپین‌ها، شبکه‌های اجتماعی، تحلیل فروش، قراردادهای سازمانی و رشد درآمد.'],
            ['page_admin','مسئول پیج / ادمین شبکه اجتماعی','Social Media Admin','marketing_sales','marketing_sales_manager',3,0,'مسئول تولید و انتشار محتوا، استوری، پاسخ به دایرکت، مدیریت کامنت، رعایت CTA، گزارش تعاملات و پشتیبانی از کمپین‌های دیجیتال.'],
            ['content_creator','تولیدکننده محتوا / گرافیست / تدوینگر','Content Creator / Designer / Editor','marketing_sales','marketing_sales_manager',3,0,'مسئول تولید محتوای تصویری، ویدئویی، ریلز، کاور، کپشن، رعایت استاندارد برند و تحویل خروجی‌های محتوایی.'],
            ['purchasing_manager','مسئول خرید','Purchasing Manager','procurement','restaurant_manager',2,0,'مسئول خرید اقلام مورد نیاز، پیگیری مواد اولیه، هماهنگی با مدیر داخلی، آشپزخانه و انبار، کنترل تامین و ثبت نیازها.'],
            ['flyer_distributor','توزیع تراکت / نیروی تبلیغات میدانی','Flyer Distributor','marketing_sales','marketing_sales_manager',4,0,'مسئول اجرای تبلیغات میدانی، توزیع تراکت، جذب لید حضوری، گزارش مسیرها، بازخورد مشتریان و همکاری با تیم فروش.'],
            ['legal_advisor','مشاور حقوقی','Legal Advisor','advisory','restaurant_owner',2,0,'مشاور حقوقی مجموعه برای قراردادها، مسائل حقوقی، مجوزها، تعهدات و ریسک‌های قانونی.'],
            ['financial_advisor','مشاور مالی','Financial Advisor','advisory','restaurant_owner',2,0,'مشاور مالی مجموعه برای تحلیل هزینه، درآمد، سود، جریان نقدی، بودجه و ساختار مالی.'],
        ];
    }
}

if (!function_exists('keyRolesEnsureSchema')) {
    function keyRolesEnsureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS `hr_roles` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('keyRoleRowHash')) {
    function keyRoleRowHash(array $row): string {
        $values = [
            (string)($row['role_code'] ?? ''),
            (string)($row['title_fa'] ?? ''),
            (string)($row['title_en'] ?? ''),
            (string)($row['department'] ?? ''),
            (string)($row['parent_role_code'] ?? ''),
            (string)($row['level'] ?? ''),
            (string)((int)($row['is_managerial'] ?? 0)),
            (string)($row['description'] ?? ''),
            (string)($row['source_label'] ?? ''),
            (string)($row['status'] ?? 'active'),
        ];
        return sha1(implode('|', $values));
    }
}

if (!function_exists('seedKeyRoles')) {
    function seedKeyRoles(PDO $db, int $actorId = 0): array {
        keyRolesEnsureSchema($db);

        $select = $db->prepare('SELECT role_code,title_fa,title_en,department,parent_role_code,level,is_managerial,description,source_label,status FROM hr_roles WHERE role_code = ? LIMIT 1');
        $insert = $db->prepare('INSERT INTO hr_roles (role_code,title_fa,title_en,department,parent_role_code,level,is_managerial,description,source_label,status) VALUES (?,?,?,?,?,?,?,?,?,"active")');
        $update = $db->prepare('UPDATE hr_roles SET title_fa=?, title_en=?, department=?, parent_role_code=?, level=?, is_managerial=?, description=?, source_label=?, status="active" WHERE role_code=?');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $sourceLabel = 'چارت پرسنلی، شناسنامه شغلی و اسناد شرح وظایف داخلی KEY';

        foreach (keyRestaurantRoleDefinitions() as $role) {
            [$roleCode, $titleFa, $titleEn, $department, $parentRoleCode, $level, $isManagerial, $description] = $role;
            $target = [
                'role_code' => $roleCode,
                'title_fa' => $titleFa,
                'title_en' => $titleEn,
                'department' => $department,
                'parent_role_code' => $parentRoleCode,
                'level' => $level,
                'is_managerial' => $isManagerial,
                'description' => $description,
                'source_label' => $sourceLabel,
                'status' => 'active',
            ];

            $select->execute([$roleCode]);
            $existing = $select->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                $insert->execute([$roleCode, $titleFa, $titleEn, $department, $parentRoleCode, $level, $isManagerial, $description, $sourceLabel]);
                $inserted++;
                continue;
            }

            if (keyRoleRowHash($existing) === keyRoleRowHash($target)) {
                $skipped++;
                continue;
            }

            $update->execute([$titleFa, $titleEn, $department, $parentRoleCode, $level, $isManagerial, $description, $sourceLabel, $roleCode]);
            $updated++;
        }

        if (function_exists('hrAuditLog')) {
            hrAuditLog('hr_roles', 'seed', null, 'seed_key_roles', $actorId ?: null, null, ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped]);
        }

        return [
            'roles' => count(keyRestaurantRoleDefinitions()),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
