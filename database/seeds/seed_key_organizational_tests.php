<?php

require_once dirname(__DIR__, 2) . '/admin/lib/hr_evaluation_service.php';

function seedKeyOrganizationalTests(PDO $db, ?int $actorId = null): array {
    hrEnsureEvaluationSchema($db);
    if (function_exists('hrOrgTestsEnsureSchema')) {
        hrOrgTestsEnsureSchema($db);
    }

    $tests = keyOrganizationalTestDefinitions();
    $insertedTests = 0;
    $insertedDimensions = 0;
    $insertedQuestions = 0;
    $insertedOptions = 0;

    foreach ($tests as $test) {
        $stmt = $db->prepare('INSERT INTO hr_assessment_tests (title,test_code,category,question_count,description,scoring_method_type,intended_use,time_limit_minutes,allow_retake,retake_policy,show_disclaimer,is_active,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,"calculated",?,NULL,1,?,1,1,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),category=VALUES(category),question_count=VALUES(question_count),description=VALUES(description),scoring_method_type=VALUES(scoring_method_type),intended_use=VALUES(intended_use),time_limit_minutes=NULL,allow_retake=1,retake_policy=VALUES(retake_policy),show_disclaimer=1,is_active=1,sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)');
        $stmt->execute([
            $test['title'],
            $test['code'],
            $test['category'],
            count($test['questions']),
            $test['description'],
            'ابزار آموزشی، مدیریتی و سازمانی ویژه پرسنل رستوران KEY',
            $test['retake_policy'],
            $test['sort_order'],
            $actorId,
            $actorId,
        ]);
        $testIdStmt = $db->prepare('SELECT id FROM hr_assessment_tests WHERE test_code=? LIMIT 1');
        $testIdStmt->execute([$test['code']]);
        $testId = (int)$testIdStmt->fetchColumn();
        if ($testId <= 0) {
            continue;
        }
        $insertedTests++;

        $dimensionIds = [];
        foreach ($test['dimensions'] as $index => $dimension) {
            $db->prepare('INSERT INTO hr_test_dimensions (test_id,code,title,description,positive_label,negative_label,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,"نیازمند آموزش","active",?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),positive_label=VALUES(positive_label),negative_label=VALUES(negative_label),status="active",sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
                ->execute([
                    $testId,
                    $dimension['code'],
                    $dimension['title'],
                    $dimension['description'],
                    'آماده و مسلط',
                    ($index + 1) * 10,
                    $actorId,
                    $actorId,
                ]);
            $dimensionStmt = $db->prepare('SELECT id FROM hr_test_dimensions WHERE test_id=? AND code=? LIMIT 1');
            $dimensionStmt->execute([$testId, $dimension['code']]);
            $dimensionIds[$dimension['code']] = (int)$dimensionStmt->fetchColumn();
            $insertedDimensions++;
        }

        foreach ($test['questions'] as $index => $question) {
            $options = keyOrganizationalQuestionOptions($question['type'] ?? 'knowledge');
            $db->prepare('INSERT INTO hr_test_questions (test_id,dimension_id,code,question_text,answer_type,question_type,options_json,weight,scoring_direction,score_direction,is_reverse_scored,is_required,is_critical,role_visibility,is_active,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,0,1,0,NULL,1,"active",?,?,?) ON DUPLICATE KEY UPDATE dimension_id=VALUES(dimension_id),question_text=VALUES(question_text),answer_type=VALUES(answer_type),question_type=VALUES(question_type),options_json=VALUES(options_json),weight=VALUES(weight),scoring_direction=VALUES(scoring_direction),score_direction=VALUES(score_direction),is_active=1,status="active",sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
                ->execute([
                    $testId,
                    $dimensionIds[$question['dimension']] ?? null,
                    $question['code'],
                    $question['text'],
                    'single_choice',
                    'organizational',
                    hrJsonEncode($options),
                    (float)($question['weight'] ?? 1),
                    'positive',
                    'positive',
                    ($index + 1) * 10,
                    $actorId,
                    $actorId,
                ]);
            $questionStmt = $db->prepare('SELECT id FROM hr_test_questions WHERE test_id=? AND code=? LIMIT 1');
            $questionStmt->execute([$testId, $question['code']]);
            $questionId = (int)$questionStmt->fetchColumn();
            $insertedQuestions++;
            foreach ($options as $optionIndex => $option) {
                $slug = 'option_' . ($optionIndex + 1);
                $db->prepare('INSERT INTO hr_test_options (question_id,title,slug,score_value,dimension_code,is_correct,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,"active",?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),score_value=VALUES(score_value),dimension_code=VALUES(dimension_code),is_correct=VALUES(is_correct),status="active",sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
                    ->execute([
                        $questionId,
                        $option['label'],
                        $slug,
                        (float)$option['score'],
                        $question['dimension'],
                        ((float)$option['score'] >= 100) ? 1 : 0,
                        ($optionIndex + 1) * 10,
                        $actorId,
                        $actorId,
                    ]);
                $insertedOptions++;
            }
        }

        $db->prepare('INSERT INTO hr_test_scoring_rules (test_id,title,slug,description,rule_type,rule_config_json,status,sort_order,created_by,updated_by) VALUES (?,"محاسبه درصدی استاندارد","simple_percent","میانگین وزنی پاسخ‌ها بر اساس امتیاز گزینه‌ها","simple_percent",?,"active",10,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),rule_type=VALUES(rule_type),rule_config_json=VALUES(rule_config_json),status="active",updated_by=VALUES(updated_by)')
            ->execute([$testId, hrJsonEncode(['rag' => ['green' => 90, 'yellow' => 70], 'disclaimer' => hrAssessmentDisclaimer()]), $actorId, $actorId]);
    }

    return [
        'tests' => $insertedTests,
        'dimensions' => $insertedDimensions,
        'questions' => $insertedQuestions,
        'options' => $insertedOptions,
    ];
}

function keyOrganizationalQuestionOptions(string $type): array {
    if ($type === 'knowledge') {
        return [
            ['label' => 'کاملا نادرست', 'score' => 0],
            ['label' => 'تا حدی درست', 'score' => 50],
            ['label' => 'درست و قابل اجرا', 'score' => 100],
        ];
    }
    return [
        ['label' => 'نیازمند آموزش', 'score' => 25],
        ['label' => 'قابل قبول', 'score' => 70],
        ['label' => 'حرفه‌ای و پایدار', 'score' => 100],
    ];
}

function keyOrganizationalTestDefinitions(): array {
    return [
        [
            'code' => 'KEY_ORG_BEHAVIOR',
            'title' => 'رفتار سازمانی و مسئولیت‌پذیری KEY',
            'category' => 'organizational_behavior',
            'retake_policy' => 'manager_approval_required',
            'sort_order' => 10,
            'description' => 'سنجش آموزشی مسئولیت، نظم، مالکیت، کار تیمی و مشتری‌مداری در محیط رستوران.',
            'dimensions' => [
                ['code' => 'responsibility', 'title' => 'مسئولیت‌پذیری', 'description' => 'پیگیری وظیفه تا نتیجه قابل مشاهده.'],
                ['code' => 'discipline', 'title' => 'نظم و انضباط', 'description' => 'رعایت زمان، ظاهر، روال و استانداردها.'],
                ['code' => 'ownership', 'title' => 'مالکیت کار', 'description' => 'حل مسئله بدون پاس‌کاری.'],
                ['code' => 'teamwork', 'title' => 'کار تیمی', 'description' => 'همکاری موثر در سرویس.'],
                ['code' => 'customer_orientation', 'title' => 'مشتری‌مداری', 'description' => 'تصمیم‌گیری با توجه به تجربه مشتری.'],
            ],
            'questions' => [
                ['code' => 'RESP_01', 'dimension' => 'responsibility', 'text' => 'اگر وظیفه‌ای ناقص بماند، بهترین اقدام سازمانی چیست؟', 'type' => 'knowledge'],
                ['code' => 'DISC_01', 'dimension' => 'discipline', 'text' => 'رعایت نظم قبل از شروع سرویس چه اثری روی کیفیت شعبه دارد؟', 'type' => 'knowledge'],
                ['code' => 'OWN_01', 'dimension' => 'ownership', 'text' => 'در مواجهه با ایراد عملیاتی، کدام رفتار نشان‌دهنده مالکیت کار است؟', 'type' => 'behavior'],
                ['code' => 'TEAM_01', 'dimension' => 'teamwork', 'text' => 'هنگام شلوغی سالن، همکاری موثر چگونه دیده می‌شود؟', 'type' => 'behavior'],
                ['code' => 'CUST_01', 'dimension' => 'customer_orientation', 'text' => 'در تصمیم‌های سریع سرویس، تجربه مشتری چه جایگاهی دارد؟', 'type' => 'knowledge'],
            ],
        ],
        [
            'code' => 'KEY_RESTAURANT_OPERATIONS',
            'title' => 'دانش عملیاتی رستوران، SOP و 5S',
            'category' => 'restaurant_operations',
            'retake_policy' => 'manager_approval_required',
            'sort_order' => 20,
            'description' => 'سنجش دانش عملیاتی درباره بهداشت، سالن، صندوق، پیک، چک‌لیست مدیر داخلی، SOP و 5S.',
            'dimensions' => [
                ['code' => 'hygiene', 'title' => 'بهداشت', 'description' => 'درک استانداردهای بهداشت و ایمنی غذا.'],
                ['code' => 'service_standards', 'title' => 'استاندارد سرویس', 'description' => 'اجرای رفتار و فرآیند سرویس.'],
                ['code' => 'cashier_standards', 'title' => 'استاندارد صندوق', 'description' => 'دقت مالی و ثبت سفارش.'],
                ['code' => 'delivery_standards', 'title' => 'استاندارد پیک', 'description' => 'تحویل سالم، محترمانه و قابل پیگیری.'],
                ['code' => 'sop_5s', 'title' => 'SOP / 5S', 'description' => 'اجرای روال، نظم، پاکیزگی و اقدام اصلاحی.'],
            ],
            'questions' => [
                ['code' => 'HYG_01', 'dimension' => 'hygiene', 'text' => 'در شروع شیفت، کنترل بهداشت فردی و محیطی چه زمانی باید انجام شود؟', 'type' => 'knowledge'],
                ['code' => 'SERV_01', 'dimension' => 'service_standards', 'text' => 'استاندارد برخورد حرفه‌ای در سالن شامل چه رفتاری است؟', 'type' => 'behavior'],
                ['code' => 'CASH_01', 'dimension' => 'cashier_standards', 'text' => 'برای کاهش مغایرت صندوق، کدام اقدام ضروری است؟', 'type' => 'knowledge'],
                ['code' => 'DEL_01', 'dimension' => 'delivery_standards', 'text' => 'در تحویل سفارش، چه چیزی باید برای پیگیری بعدی ثبت شود؟', 'type' => 'knowledge'],
                ['code' => 'SOP_01', 'dimension' => 'sop_5s', 'text' => 'اگر یک ایراد تکراری در 5S دیده شود، اقدام درست چیست؟', 'type' => 'knowledge'],
            ],
        ],
        [
            'code' => 'KEY_SALES_CUSTOMER_INTERACTION',
            'title' => 'فروش، مسیر مشتری و گفت‌وگوی حرفه‌ای',
            'category' => 'sales_customer_interaction',
            'retake_policy' => 'manager_approval_required',
            'sort_order' => 30,
            'description' => 'سنجش استانداردهای فروش، شناخت نوع مشتری، سوال باز، گوش دادن، FAB، اعتراض، CTA، معرفی و پشتیبانی پس از فروش.',
            'dimensions' => [
                ['code' => 'customer_types', 'title' => 'انواع مشتری', 'description' => 'شناخت رفتار مشتریان مختلف.'],
                ['code' => 'customer_journey', 'title' => 'مسیر مشتری', 'description' => 'قبل، حین و پس از خرید.'],
                ['code' => 'listening', 'title' => 'گوش دادن حرفه‌ای', 'description' => 'سوال باز، سکوت، یادداشت و بازتاب.'],
                ['code' => 'fab_objection', 'title' => 'FAB و اعتراض', 'description' => 'بیان منفعت و مدیریت اعتراض بدون دفاعی شدن.'],
                ['code' => 'cta_referral_support', 'title' => 'CTA، معرفی و پشتیبانی', 'description' => 'پایان روشن گفت‌وگو و پیگیری.'],
            ],
            'questions' => [
                ['code' => 'TYPE_01', 'dimension' => 'customer_types', 'text' => 'مشتری قیمت‌محور را چگونه باید در گفت‌وگو هدایت کرد؟', 'type' => 'knowledge'],
                ['code' => 'JOURNEY_01', 'dimension' => 'customer_journey', 'text' => 'پیگیری بعد از خرید در کدام بخش مسیر مشتری قرار می‌گیرد؟', 'type' => 'knowledge'],
                ['code' => 'LISTEN_01', 'dimension' => 'listening', 'text' => 'بعد از پرسیدن سوال باز، رفتار حرفه‌ای بعدی چیست؟', 'type' => 'behavior'],
                ['code' => 'FAB_01', 'dimension' => 'fab_objection', 'text' => 'در گفت‌وگو با مشتری، کدام بخش FAB باید زودتر برجسته شود؟', 'type' => 'knowledge'],
                ['code' => 'CTA_01', 'dimension' => 'cta_referral_support', 'text' => 'پایان حرفه‌ای مکالمه فروش باید شامل چه چیزی باشد؟', 'type' => 'knowledge'],
            ],
        ],
        [
            'code' => 'KEY_MARKETING_CONTENT',
            'title' => 'بازاریابی، محتوا و پیگیری کمپین',
            'category' => 'marketing_content',
            'retake_policy' => 'manager_approval_required',
            'sort_order' => 40,
            'description' => 'سنجش سواد عملیاتی تولید لید، تبدیل، تقویم محتوا، مسیر آنلاین مشتری، تعامل و پیگیری کمپین.',
            'dimensions' => [
                ['code' => 'lead_generation', 'title' => 'تولید لید', 'description' => 'تشخیص ورودی قابل پیگیری.'],
                ['code' => 'conversion', 'title' => 'تبدیل', 'description' => 'تبدیل ارتباط به اقدام بعدی.'],
                ['code' => 'content_calendar', 'title' => 'تقویم محتوا', 'description' => 'برنامه‌ریزی پیوسته محتوا.'],
                ['code' => 'online_journey', 'title' => 'مسیر آنلاین مشتری', 'description' => 'اینستاگرام، سایت، واتساپ و تماس.'],
                ['code' => 'campaign_followup', 'title' => 'پیگیری کمپین', 'description' => 'ثبت، سنجش و پیگیری سرنخ‌ها.'],
            ],
            'questions' => [
                ['code' => 'LEAD_01', 'dimension' => 'lead_generation', 'text' => 'لید قابل پیگیری چه اطلاعات حداقلی نیاز دارد؟', 'type' => 'knowledge'],
                ['code' => 'CONV_01', 'dimension' => 'conversion', 'text' => 'نرخ تبدیل چه چیزی را نشان می‌دهد؟', 'type' => 'knowledge'],
                ['code' => 'CAL_01', 'dimension' => 'content_calendar', 'text' => 'تقویم محتوا چه کمکی به اجرای فروش می‌کند؟', 'type' => 'knowledge'],
                ['code' => 'ONLINE_01', 'dimension' => 'online_journey', 'text' => 'مشتری آنلاین قبل از خرید معمولا از چه نقاط تماسی عبور می‌کند؟', 'type' => 'knowledge'],
                ['code' => 'CAMP_01', 'dimension' => 'campaign_followup', 'text' => 'پس از کمپین، مهم‌ترین اقدام تیم فروش چیست؟', 'type' => 'behavior'],
            ],
        ],
        [
            'code' => 'KEY_KPI_REPORTING_LITERACY',
            'title' => 'سواد KPI، BSF و گزارش‌خوانی',
            'category' => 'kpi_reporting_literacy',
            'retake_policy' => 'manager_approval_required',
            'sort_order' => 50,
            'description' => 'سنجش درک BSF، لید، نرخ تبدیل، میانگین خرید، حاشیه سود، حل شکایت و گزارش پیگیری.',
            'dimensions' => [
                ['code' => 'bsf', 'title' => 'BSF', 'description' => 'درک فرمول پایه فروش.'],
                ['code' => 'lead_conversion', 'title' => 'لید و تبدیل', 'description' => 'خواندن ورودی و خروجی فروش.'],
                ['code' => 'purchase_margin', 'title' => 'میانگین خرید و سود', 'description' => 'درک ارزش خرید و حاشیه سود.'],
                ['code' => 'complaint_resolution', 'title' => 'حل شکایت', 'description' => 'پیگیری شکایت تا نتیجه.'],
                ['code' => 'followup_report', 'title' => 'گزارش پیگیری', 'description' => 'ثبت قابل استفاده برای مدیر.'],
            ],
            'questions' => [
                ['code' => 'BSF_01', 'dimension' => 'bsf', 'text' => 'فرمول BSF از چه مولفه‌هایی تشکیل می‌شود؟', 'type' => 'knowledge'],
                ['code' => 'LC_01', 'dimension' => 'lead_conversion', 'text' => 'اگر لید زیاد اما فروش کم باشد، کدام شاخص باید بررسی شود؟', 'type' => 'knowledge'],
                ['code' => 'PM_01', 'dimension' => 'purchase_margin', 'text' => 'میانگین خرید چه کمکی به تحلیل فروش می‌کند؟', 'type' => 'knowledge'],
                ['code' => 'CR_01', 'dimension' => 'complaint_resolution', 'text' => 'حل شکایت چه زمانی کامل محسوب می‌شود؟', 'type' => 'behavior'],
                ['code' => 'FR_01', 'dimension' => 'followup_report', 'text' => 'گزارش پیگیری خوب باید چه ویژگی داشته باشد؟', 'type' => 'behavior'],
            ],
        ],
    ];
}
