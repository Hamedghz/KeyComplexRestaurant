<?php

/**
 * Idempotent KEY restaurant role duties seed.
 *
 * Duties are linked to role_code only. This seed does not create users,
 * checklist templates, KPI definitions, or assignments.
 */

if (!function_exists('keyRestaurantDutyDefinitions')) {
    function keyRestaurantDutyDefinitions(): array {
        return [
            ['internal_manager_staff_supervision','internal_manager','نظارت و کنترل عملکرد کلیه کارکنان','نظارت روزانه بر عملکرد کارکنان طبق شرح وظایف، کنترل نظم، آراستگی، بهداشت فردی، لباس فرم، رفتار حرفه‌ای و اجرای استانداردهای رستوران.','daily','critical'],
            ['internal_manager_checklist_completion','internal_manager','تکمیل و کنترل چک‌لیست‌های روزانه','پرکردن چک‌لیست شرح وظایف کارکنان، کنترل بخش‌های سالن، آشپزخانه، انبار، سرویس‌ها، ورودی و ارائه گزارش حداقل دو نوبت در روز به مدیریت.','daily','critical'],
            ['internal_manager_quality_control','internal_manager','نظارت بر کیفیت غذا و سرویس','کنترل کیفیت غذا از نظر ظاهر، دما، نحوه سرو، آماده‌سازی، تحویل سفارشات سالن، بیرون‌بر و پیک با هماهنگی آشپزخانه و سالن.','daily','critical'],
            ['internal_manager_complaint_handling','internal_manager','پاسخگویی و پیگیری شکایات مشتریان','شنیدن شکایت مشتری با صبوری، ثبت نارضایتی، پیگیری تا رفع کامل، حفظ احترام مشتری و گزارش موارد مهم به مدیریت.','as_needed','critical'],
            ['internal_manager_inventory_followup','internal_manager','پیگیری اقلام، مواد اولیه و نیازهای واحدها','دریافت گزارش مواد اولیه و لوازم موردنیاز از کارکنان مرتبط، هماهنگی با خرید و آشپزخانه و پیگیری تامین اقلام ضروری.','daily','high'],
            ['internal_manager_warehouse_control','internal_manager','نظارت بر ورود و خروج کالا از انبار','کنترل ورود و خروج اقلام از انبار، بررسی نظم، سلامت، تاریخ، بسته‌بندی و هماهنگی با آشپزخانه و خرید.','daily','high'],
            ['internal_manager_branch_readiness','internal_manager','کنترل آمادگی شعبه پیش از سرویس','کنترل سالن، سرویس‌ها، پیشخوان، تجهیزات، نور، موسیقی، رایحه، نظافت، یخچال، نوشیدنی‌ها، ظروف و آمادگی پرسنل قبل از شروع کار.','shift','critical'],
            ['internal_manager_issue_tracking','internal_manager','ثبت، ارجاع و پیگیری ایرادات اجرایی','ثبت خرابی‌ها، نواقص، تخلفات، مشکلات فنی و عملیاتی، ارجاع به مسئول مربوطه و پیگیری تا رفع کامل.','daily','high'],
            ['internal_manager_weekly_meeting','internal_manager','برگزاری جلسه هفتگی و اتاق فکر','برگزاری جلسه هفتگی با کارکنان برای بررسی مشکلات، ارائه پیشنهادهای بهبود، افزایش کیفیت محصول، فروش و هماهنگی تیمی.','weekly','medium'],
            ['internal_manager_end_shift_security','internal_manager','کنترل پایان کار و ایمنی شعبه','حضور تا پایان کار، اطمینان از خروج ایمن کارکنان، قفل کردن انبار، ورودی آشپزخانه و درب‌های مرتبط و ثبت موارد مهم پایان شیفت.','shift','high'],

            ['cashier_order_registration','cashier','ثبت دقیق سفارش حضوری و تلفنی','دریافت سفارش مشتریان حضوری و تلفنی، ثبت دقیق در سیستم فروش و ارجاع صحیح سفارش به آشپزخانه یا سالن.','daily','critical'],
            ['cashier_sales_recording','cashier','ثبت کلیه فروش‌ها و فاکتورها','ثبت تمام محصولات فروخته‌شده، کنترل فاکتورها، جلوگیری از خطای مبلغ، تعداد، روش پرداخت و تحویل گزارش فروش.','daily','critical'],
            ['cashier_purchase_recording','cashier','ثبت خریدها طبق فاکتور','ثبت خریدهای مجاز طبق فاکتور ارائه‌شده و هماهنگی با مدیریت یا حسابداری طبق دستورالعمل داخلی.','as_needed','medium'],
            ['cashier_cash_control','cashier','کنترل دریافت وجه نقد، کارت و سایر روش‌های پرداخت','دریافت صحیح وجه، کارتخوان، کارت‌به‌کارت یا پرداخت آنلاین، کنترل مغایرت و ثبت موارد خاص.','daily','critical'],
            ['cashier_cashbox_handover','cashier','تحویل صندوق و گزارش پایان شیفت','تهیه گزارش پایانی صندوق، شمارش وجه نقد، تطبیق با سیستم، ثبت مغایرت، تحویل صندوق به مدیر یا صندوق‌دار بعدی و امضا.','shift','critical'],
            ['cashier_delivery_settlement','cashier','پیگیری سفارشات پیک و تسویه پیک','پیگیری سفارشات پیک، دریافت هزینه‌های دریافتی توسط پیک، تسویه حساب پایان وقت و ثبت موارد مربوط.','daily','high'],
            ['cashier_customer_greeting','cashier','خوش‌آمدگویی و برخورد محترمانه با مشتری','هنگام ورود و خروج مشتری با احترام برخورد کند، خوش‌آمدگویی مناسب داشته باشد و پاسخگویی مؤدبانه هنگام پرداخت انجام دهد.','daily','high'],
            ['cashier_system_readiness','cashier','کنترل آمادگی سیستم صندوق','بررسی سلامت صندوق، مانیتور، فیش‌پرینتر، رول کاغذ، کارتخوان، اتصال اینترنت و آماده‌بودن تجهیزات پیش از شروع شیفت.','shift','high'],

            ['hall_captain_staff_grooming_control','hall_captain','کنترل آراستگی و آمادگی پرسنل سالن','بررسی لباس فرم، اصلاح سر و صورت، ناخن، دست‌ها، آمادگی روحی و رفتار مهماندارها پیش از شروع سرویس.','shift','high'],
            ['hall_captain_table_assignment','hall_captain','چیدمان پرسنل و تقسیم میزها','تقسیم میزها بین گارسون‌ها، کنترل نظم سالن و اطمینان از پوشش مناسب همه بخش‌های سالن.','shift','high'],
            ['hall_captain_service_supervision','hall_captain','نظارت بر سرویس‌دهی سالن','کنترل نحوه سرو، پیگیری سرو به‌موقع غذا، جمع‌آوری سریع میزها و اطمینان از حضور در دسترس برای نیازهای مشتری.','daily','critical'],
            ['hall_captain_complaint_response','hall_captain','پاسخگویی اولیه به اعتراض یا درخواست مهمان','پاسخ مناسب به اعتراض، درخواست یا مشکل مهمان و ارجاع به مدیر داخلی در صورت نیاز تا رفع مسئله.','as_needed','critical'],
            ['hall_captain_hall_cleanliness','hall_captain','نظارت بر نظافت سالن و سرویس‌ها','کنترل نظافت میزها، صندلی‌ها، کف سالن، شیشه‌ها، سرویس‌های بهداشتی، نور، دما، موسیقی و رایحه محیط.','daily','critical'],
            ['hall_captain_station_stock','hall_captain','کنترل موجودی اقلام سالن','اطمینان از کامل بودن نوشیدنی‌ها، منوها، قاشق، چنگال، سس، نمک، فلفل، دستمال و سایر اقلام پذیرایی.','shift','high'],
            ['hall_captain_shift_incident_report','hall_captain','ثبت گزارش اتفاقات خاص شیفت','ثبت اتفاقات مهم شیفت، شکایات، تأخیرها، نواقص، ایرادات سرویس و اقدامات انجام‌شده.','shift','medium'],

            ['waiter_customer_welcome','waiter','استقبال گرم و راهنمایی مشتری','استقبال محترمانه از مشتری، هدایت به میز مناسب و ایجاد حس خوشایند در شروع تجربه مشتری.','daily','high'],
            ['waiter_menu_knowledge','waiter','تسلط کامل بر منو و راهنمایی مشتری','شناخت منو، توضیح غذاها، پیشنهاد مناسب و فروش جانبی پیش‌غذا، نوشیدنی یا آیتم مکمل.','daily','high'],
            ['waiter_standard_service','waiter','سرو غذا و نوشیدنی طبق استاندارد','تحویل صحیح غذا و نوشیدنی به میز، رعایت ترتیب سرو، ادب، سرعت و دقت در تحویل.','daily','critical'],
            ['waiter_table_cleaning','waiter','پاکسازی و جمع‌آوری میزها','جمع‌آوری ظروف اضافه، تمیزکردن میزها، آماده‌سازی مجدد میز پس از خروج مشتری و حفظ نظم سالن.','daily','high'],
            ['waiter_uniform_and_tools','waiter','آراستگی فردی و آماده‌سازی ابزار سرویس','اتوی لباس فرم، تمیزی کفش، شارژ نمک، فلفل، دستمال و کنترل لک نداشتن ظروف و لیوان‌ها.','shift','high'],

            ['head_chef_kitchen_supervision','head_chef','نظارت بر کارکنان آشپزخانه','نظارت و کنترل کار همه کارکنان آشپزخانه، تقسیم وظایف، کنترل نظم، بهداشت، لباس فرم و اجرای استانداردهای آشپزخانه.','daily','critical'],
            ['head_chef_food_preparation','head_chef','آماده‌سازی و پخت غذاهای منو','آماده‌سازی و پخت همه غذاهای موجود در منوی رستوران و ارسال به‌موقع به سالن یا بیرون‌بر.','daily','critical'],
            ['head_chef_raw_material_control','head_chef','کنترل مواد اولیه و اعلام نیاز','کنترل مواد اولیه موردنیاز، موجودی یخچال‌ها، بسته‌بندی، تاریخ، سلامت ظاهری و مکتوب کردن نیازها برای مدیر داخلی.','daily','high'],
            ['head_chef_food_quality_accountability','head_chef','مسئولیت نهایی کیفیت غذا','پاسخگویی نهایی نسبت به کیفیت همه محصولات تولیدی، طعم، سلامت، دما، ظاهر و نحوه آماده‌سازی غذاها.','daily','critical'],
            ['head_chef_kitchen_cleanliness','head_chef','نظارت بر نظافت آشپزخانه و تجهیزات','نظارت بر نظافت کف آشپزخانه، دستگاه کباب‌زن، اجاق‌ها، ظروف، سیخ‌ها، دم‌کن برنج، انبار، راه‌پله، اتاق استراحت و سرویس پایین.','daily','critical'],
            ['head_chef_staff_backup','head_chef','جایگزینی و پشتیبانی از کارکنان در صورت نیاز','به عهده گرفتن وظایف سایر کارکنان آشپزخانه در صورت عدم حضور یا نیاز به کمک.','as_needed','high'],

            ['assistant_chef_backup_head_chef','assistant_chef','انجام وظایف سرآشپز در غیاب او','به عهده گرفتن وظایف اصلی مسئول آشپزخانه در غیاب او با رعایت استانداردهای پخت، بهداشت و کیفیت.','as_needed','high'],
            ['assistant_chef_preparation','assistant_chef','آماده‌سازی مواد اولیه و پخت','آماده‌سازی جوجه، کوبیده و سایر مواد اولیه، همکاری در پخت و پر کردن دستگاه گرم‌نگهدارنده.','daily','critical'],
            ['assistant_chef_quality_check','assistant_chef','کنترل کیفیت غذا قبل از ارسال','کنترل غذاها از نظر کیفیت، طعم، سلامت و ظاهر پیش از ارسال به سالن.','daily','critical'],
            ['assistant_chef_cleaning','assistant_chef','نظافت تجهیزات و ظروف آشپزخانه','نظافت اجاق‌ها، دم‌کن برنج، دیگ‌ها، ملاقه‌ها، سیخ‌ها، یخچال‌ها و اتاق استراحت کارکنان.','daily','high'],
            ['assistant_chef_hygiene','assistant_chef','رعایت بهداشت فردی و کارت‌های سلامت','استفاده از دستکش و کلاه، اصلاح هفتگی ناخن، اصلاح دوره‌ای مو و صورت و تهیه یا تمدید کارت تندرستی و آموزشگاه بهداشت.','weekly','high'],

            ['hall_service_floor_cleaning','hall_service','نظافت مستمر کف سالن و میزها','نظافت لحظه‌ای کف سالن، میزها، صندلی‌ها، اطراف سماور، انتقال ظروف کثیف و حفظ آراستگی محیط.','daily','critical'],
            ['hall_service_glass_fridge_cleaning','hall_service','تمیزکردن شیشه‌ها و یخچال‌ها','تمیز کردن شیشه‌ها و یخچال‌ها حداقل سه بار در هفته و کنترل نظم و چیدمان نوشیدنی‌ها.','weekly','medium'],
            ['hall_service_toilet_cleaning','hall_service','نظافت سرویس بهداشتی و بخش‌های عمومی','نظافت سرویس توالت بالا، بالکن، پله‌ها، قسمت‌های عمومی، جارو و طی‌کشی روزانه.','daily','critical'],
            ['hall_service_customer_pre_service','hall_service','آماده‌سازی پذیرایی اولیه مشتری','ریختن چای، سوپ رایگان یا نوشیدنی قبل از صرف غذا طبق دستورالعمل داخلی.','daily','medium'],
            ['hall_service_waste_management','hall_service','جمع‌آوری و خروج زباله‌ها','جمع‌آوری زباله‌ها، بیرون بردن سطل‌های زباله و قرار دادن در محل مناسب حین یا پایان کار.','daily','high'],

            ['kitchen_service_dishwashing','kitchen_service','شستن ظروف و تجهیزات آشپزخانه','شستن کلیه ظروف، دیگ‌ها، سینی‌ها، سیخ‌ها و ابزار آشپزخانه طبق استاندارد نظافت.','daily','critical'],
            ['kitchen_service_floor_equipment_cleaning','kitchen_service','نظافت کف، ظرفشویی و تجهیزات آشپزخانه','نظافت مداوم کف آشپزخانه، ظرفشویی، دستگاه کباب‌زن، اجاق‌ها، قسمت سطل‌های زباله و مسیرهای مرتبط.','daily','critical'],
            ['kitchen_service_raw_material_preparation','kitchen_service','آماده‌سازی اولیه مواد غذایی','بیرون آوردن مواد اولیه، آماده‌سازی برای جوجه و کوبیده، خیس کردن برنج و حبوبات با هماهنگی مسئول آشپزخانه.','daily','high'],
            ['kitchen_service_storage_order','kitchen_service','مرتب نگه داشتن انبار و یخچال‌ها','نظافت روزانه یخچال‌ها، مرتب‌سازی انبار، کنترل بسته‌بندی مناسب و درج تاریخ روی گوشت‌ها و مواد اولیه.','daily','high'],
            ['kitchen_service_hygiene','kitchen_service','رعایت بهداشت فردی در آشپزخانه','استفاده از دستکش و کلاه، رعایت لباس فرم، اصلاح ناخن و مو، کارت تندرستی و کارت آموزشگاه بهداشت.','weekly','high'],

            ['delivery_rider_vehicle_check','delivery_rider','بررسی سلامت موتور و تجهیزات','بررسی ترمز، چراغ‌ها، لاستیک، بنزین، باکس حمل غذا، شارژ موبایل، GPS و دستگاه کارتخوان قبل از شروع شیفت.','shift','critical'],
            ['delivery_rider_order_check','delivery_rider','کنترل اقلام سفارش قبل از خروج','چک کردن فاکتور، نوشابه، نان، قاشق، سس، بسته بودن ظروف، عدم نشت و چیدمان درست غذا داخل باکس.','daily','critical'],
            ['delivery_rider_safe_delivery','delivery_rider','تحویل ایمن، سریع و محترمانه سفارش','حمل ایمن غذا، حفظ دما، رعایت قوانین رانندگی، پارک ایمن، رفتار محترمانه و رعایت حریم خصوصی مشتری.','daily','critical'],
            ['delivery_rider_payment_settlement','delivery_rider','دریافت وجه و تسویه پایان شیفت','دریافت وجه نقد یا کارتخوان، تایید پرداخت آنلاین، تسویه مبالغ دریافتی و گزارش مغایرت احتمالی.','shift','high'],
            ['delivery_rider_equipment_report','delivery_rider','گزارش خرابی تجهیزات','گزارش هرگونه خرابی موتور، باکس، کارتخوان، گوشی یا تجهیزات مرتبط به مدیر داخلی یا ناظر.','as_needed','high'],

            ['marketing_social_media_management','marketing_sales_manager','مدیریت شبکه‌های اجتماعی','برنامه‌ریزی و نظارت بر تولید محتوا، استوری، پاسخ به دایرکت، کامنت‌ها، CTA، لوکیشن، هشتگ و اجرای کمپین‌ها.','daily','high'],
            ['marketing_lead_generation','marketing_sales_manager','جذب سرنخ و مشتری جدید','جذب مشتری از اینستاگرام، تماس، واتساپ، تبلیغات، کمپین‌ها، معرفی مشتری و بازاریابی حضوری یا تلفنی.','daily','critical'],
            ['marketing_b2b_contracts','marketing_sales_manager','بازاریابی سازمانی و قرارداد با ارگان‌ها','پیگیری فروش سازمانی، قرارداد با شرکت‌ها، ادارات، گروه‌ها و مشتریان حجمی برای افزایش درآمد.','weekly','high'],
            ['marketing_sales_analysis','marketing_sales_manager','تحلیل گزارشات فروش و کمپین','بررسی فروش روزانه و ماهانه، نرخ تبدیل، لید، ROAS، CAC، رشد فروش، رفتار مشتری و طراحی اقدام اصلاحی.','weekly','high'],
            ['marketing_vip_followup','marketing_sales_manager','پیگیری مشتریان VIP و غیرفعال','تماس یا عیادت از مشتریان VIP و مشتریانی که مدتی سفارش نداده‌اند و ثبت نتیجه پیگیری.','weekly','medium'],
            ['marketing_online_panel_management','marketing_sales_manager','مدیریت پنل‌های فروش آنلاین','مدیریت اسنپ‌فود و سایر پلتفرم‌ها، پاسخ به نظرات، بهبود رتبه، بررسی نرخ تبدیل و گزارش عملکرد کانال.','daily','medium'],

            ['page_admin_direct_response','page_admin','پاسخگویی به دایرکت و کامنت‌ها','پاسخ به پیام‌ها و کامنت‌ها در زمان مناسب، با ادبیات محترمانه، CTA واضح و ثبت موارد نیازمند پیگیری.','daily','critical'],
            ['page_admin_story_publish','page_admin','انتشار استوری روزانه','انتشار حداقل سه استوری روزانه از محیط، غذاها، پیشنهادها، پشت‌صحنه، رضایت مشتری و CTA رزرو یا سفارش.','daily','high'],
            ['page_admin_post_quality','page_admin','کنترل کیفیت انتشار پست‌ها','کنترل کپشن، هشتگ، لوکیشن، CTA، کاور، هماهنگی با برند و زمان انتشار.','daily','high'],

            ['purchasing_collect_needs','purchasing_manager','جمع‌آوری نیازهای خرید از واحدها','دریافت نیازهای خرید از مدیر داخلی، آشپزخانه، سالن و انبار و اولویت‌بندی اقلام ضروری.','daily','high'],
            ['purchasing_followup_supply','purchasing_manager','پیگیری تامین اقلام','پیگیری تامین به‌موقع مواد اولیه، تجهیزات، اقلام مصرفی و گزارش تاخیر یا کمبود به مدیریت.','daily','high'],
        ];
    }
}

if (!function_exists('keyDutiesTableHasColumn')) {
    function keyDutiesTableHasColumn(PDO $db, string $column): bool {
        $stmt = $db->prepare('SHOW COLUMNS FROM hr_role_duties LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('keyDutiesEnsureSchema')) {
    function keyDutiesEnsureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS `hr_role_duties` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `duty_code` varchar(140) DEFAULT NULL,
            `role_code` varchar(100) NOT NULL,
            `title` varchar(190) NOT NULL,
            `description` text DEFAULT NULL,
            `responsibility_type` enum('daily','shift','weekly','monthly','as_needed') NOT NULL DEFAULT 'daily',
            `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            `standard_key` varchar(120) DEFAULT NULL,
            `status` varchar(30) NOT NULL DEFAULT 'active',
            `created_by` int(11) UNSIGNED DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_hr_role_duty_code` (`duty_code`),
            UNIQUE KEY `uniq_hr_role_duty` (`role_code`, `title`),
            KEY `idx_hr_role_duty_status` (`status`, `role_code`),
            KEY `idx_hr_role_duty_priority` (`priority`, `responsibility_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = [
            'duty_code' => "`duty_code` varchar(140) DEFAULT NULL AFTER `id`",
            'responsibility_type' => "`responsibility_type` enum('daily','shift','weekly','monthly','as_needed') NOT NULL DEFAULT 'daily' AFTER `description`",
            'priority' => "`priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium' AFTER `responsibility_type`",
        ];
        foreach ($columns as $column => $definition) {
            if (!keyDutiesTableHasColumn($db, $column)) {
                $db->exec('ALTER TABLE `hr_role_duties` ADD COLUMN ' . $definition);
            }
        }

        try {
            $db->exec('CREATE UNIQUE INDEX `uniq_hr_role_duty_code` ON `hr_role_duties` (`duty_code`)');
        } catch (Throwable $e) {
            // The index may already exist; existing duplicate legacy nulls are allowed by MySQL.
        }
        try {
            $db->exec('CREATE INDEX `idx_hr_role_duty_priority` ON `hr_role_duties` (`priority`, `responsibility_type`)');
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('keyDutyRowHash')) {
    function keyDutyRowHash(array $row): string {
        return sha1(implode('|', [
            (string)($row['duty_code'] ?? ''),
            (string)($row['role_code'] ?? ''),
            (string)($row['title'] ?? ''),
            (string)($row['description'] ?? ''),
            (string)($row['responsibility_type'] ?? ''),
            (string)($row['priority'] ?? ''),
            (string)($row['status'] ?? 'active'),
        ]));
    }
}

if (!function_exists('seedKeyDuties')) {
    function seedKeyDuties(PDO $db, int $actorId = 0): array {
        keyDutiesEnsureSchema($db);

        $select = $db->prepare('SELECT duty_code,role_code,title,description,responsibility_type,priority,status FROM hr_role_duties WHERE duty_code = ? LIMIT 1');
        $insert = $db->prepare('INSERT INTO hr_role_duties (duty_code,role_code,title,description,responsibility_type,priority,status,created_by) VALUES (?,?,?,?,?,?,"active",?)');
        $update = $db->prepare('UPDATE hr_role_duties SET role_code=?, title=?, description=?, responsibility_type=?, priority=?, status="active" WHERE duty_code=?');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        foreach (keyRestaurantDutyDefinitions() as $duty) {
            [$dutyCode, $roleCode, $title, $description, $responsibilityType, $priority] = $duty;
            $target = [
                'duty_code' => $dutyCode,
                'role_code' => $roleCode,
                'title' => $title,
                'description' => $description,
                'responsibility_type' => $responsibilityType,
                'priority' => $priority,
                'status' => 'active',
            ];
            $select->execute([$dutyCode]);
            $existing = $select->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                $insert->execute([$dutyCode, $roleCode, $title, $description, $responsibilityType, $priority, $actorId ?: null]);
                $inserted++;
                continue;
            }
            if (keyDutyRowHash($existing) === keyDutyRowHash($target)) {
                $skipped++;
                continue;
            }
            $update->execute([$roleCode, $title, $description, $responsibilityType, $priority, $dutyCode]);
            $updated++;
        }

        if (function_exists('hrAuditLog')) {
            hrAuditLog('hr_role_duties', 'seed', null, 'seed_key_duties', $actorId ?: null, null, ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped]);
        }

        return [
            'duties' => count(keyRestaurantDutyDefinitions()),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
