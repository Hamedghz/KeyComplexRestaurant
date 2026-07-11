<?php

/**
 * Idempotent KEY business coaching standards seed.
 *
 * These rows are reference data only. They are consumed by HR tests,
 * checklists, KPI, planner, and OKR/TMO modules without creating a visible
 * Business Standards admin module.
 */

function keyBusinessCoachingStandardsDefinitions(): array {
    return [
        'customer_types' => [
            'title' => 'انواع مشتری',
            'description' => 'دسته‌بندی مشتری برای تست‌ها، KPI، پلنر و OKR.',
            'items' => [
                ['lead', 'مشتری بالقوه', 'Lead / potential customer'],
                ['new_customer', 'مشتری جدید', 'New customer'],
                ['repeat_customer', 'مشتری تکرار خرید', 'Repeat purchase customer'],
                ['loyal_customer', 'مشتری وفادار', 'Loyal customer'],
                ['price_sensitive', 'مشتری قیمت‌محور', 'Price-sensitive customer'],
                ['value_oriented', 'مشتری ارزش‌محور', 'Value-oriented customer'],
                ['bulk_organizational', 'مشتری حجمی یا سازمانی', 'Bulk or organizational customer'],
                ['unsatisfied', 'مشتری ناراضی', 'Unsatisfied customer'],
                ['hesitant', 'مشتری مردد', 'Hesitant customer'],
                ['indifferent', 'مشتری بی‌تفاوت', 'Indifferent customer'],
                ['referral_customer', 'مشتری معرفی‌شده', 'Referral customer'],
                ['campaign_customer', 'مشتری تبلیغاتی', 'Campaign or advertising customer'],
                ['walk_in_customer', 'مشتری عبوری', 'Walk-in customer'],
            ],
        ],
        'customer_journey' => [
            'title' => 'سفر مشتری',
            'description' => 'مرحله و کانال تعامل مشتری برای KPI، پلنر و OKR.',
            'items' => [
                ['stage_before_purchase', 'قبل از خرید', 'Customer journey stage: before purchase'],
                ['stage_during_purchase', 'حین خرید', 'Customer journey stage: during purchase'],
                ['stage_after_purchase', 'بعد از خرید', 'Customer journey stage: after purchase'],
                ['channel_instagram', 'Instagram', 'Customer journey channel'],
                ['channel_website', 'Website', 'Customer journey channel'],
                ['channel_phone', 'Phone', 'Customer journey channel'],
                ['channel_whatsapp', 'WhatsApp', 'Customer journey channel'],
                ['channel_in_person_restaurant_visit', 'In-person restaurant visit', 'Customer journey channel'],
                ['channel_delivery', 'Delivery', 'Customer journey channel'],
                ['channel_b2b_organizational', 'B2B / organizational', 'Customer journey channel'],
                ['channel_referral', 'Referral', 'Customer journey channel'],
            ],
        ],
        'sales_script' => [
            'title' => 'اسکریپت فروش',
            'description' => 'اسکریپت فروش به عنوان نقشه گفتگو، نه متن حفظی.',
            'items' => [
                ['conversation_roadmap', 'نقشه گفتگو', 'Script is a conversation roadmap.'],
                ['not_memorized_text', 'متن حفظی ثابت نیست', 'Script is not fixed memorized text.'],
                ['start_with_questions', 'شروع با سوال', 'Start with questions.'],
                ['listen_before_presenting', 'شنیدن قبل از ارائه', 'Listen before presenting.'],
                ['customer_language', 'زبان مشتری', 'Speak the customer language.'],
                ['avoid_technical_terms', 'پرهیز از اصطلاحات غیرضروری', 'Avoid unnecessary technical terms.'],
                ['clear_cta', 'پایان با اقدام روشن', 'End with a clear CTA.'],
                ['continuous_improvement', 'بهبود مستمر اسکریپت', 'Improve script continuously.'],
            ],
        ],
        'fab' => [
            'title' => 'FAB',
            'description' => 'Feature, Advantage, Benefit با اولویت Benefit در مکالمه مشتری.',
            'items' => [
                ['feature', 'Feature', 'What the product or service has.'],
                ['advantage', 'Advantage', 'What makes it different.'],
                ['benefit', 'Benefit', 'What value it creates for the customer.'],
                ['benefit_first_rule', 'اول Benefit در مکالمه مشتری', 'Use Benefit first in customer conversation.'],
            ],
        ],
        'listening' => [
            'title' => 'شنیدن حرفه‌ای',
            'description' => 'رفتارهای کلیدی شنیدن حرفه‌ای در فروش و رسیدگی به شکایت.',
            'items' => [
                ['ask_open_question', 'پرسش باز', 'Ask open questions.'],
                ['ask_then_silence', 'سوال و سپس سکوت', 'Ask, then stay silent.'],
                ['take_notes', 'یادداشت‌برداری', 'Take notes.'],
                ['reflect_back', 'بازتاب گفته مشتری', 'Reflect back what the customer said.'],
                ['do_not_interrupt', 'قطع نکردن صحبت مشتری', 'Do not interrupt.'],
            ],
        ],
        'objections' => [
            'title' => 'مدیریت اعتراض',
            'description' => 'بانک اعتراض‌های رایج برای چک‌لیست، تست، پلنر و KPI.',
            'items' => [
                ['price', 'قیمت', 'Price objection'],
                ['time', 'زمان', 'Time objection'],
                ['warranty', 'گارانتی', 'Warranty objection'],
                ['trust', 'اعتماد', 'Trust objection'],
                ['bad_experience', 'تجربه بد قبلی', 'Bad previous experience'],
                ['competitor_comparison', 'مقایسه با رقبا', 'Competitor comparison'],
                ['hesitation', 'تردید', 'Hesitation'],
                ['unclear_need', 'نیاز نامشخص', 'Unclear need'],
            ],
        ],
        'voip_call_review_ready' => [
            'title' => 'آمادگی بازبینی تماس VoIP',
            'description' => 'فقط آمادگی داده‌ای و نقاط اتصال آینده؛ بدون ساخت VoIP.',
            'items' => [
                ['phone_number_display', 'نمایش شماره تماس', 'Phone number display'],
                ['call_recording', 'ضبط تماس', 'Call recording'],
                ['call_report_by_employee', 'گزارش تماس بر اساس کارمند', 'Call report by employee'],
                ['crm_link', 'اتصال CRM', 'CRM link'],
                ['follow_up_scheduling', 'زمان‌بندی پیگیری', 'Follow-up scheduling'],
                ['multi_user_queue', 'صف چندکاربره', 'Multi-user queue'],
                ['auto_response', 'پاسخ خودکار', 'Auto response'],
                ['call_quality_score', 'امتیاز کیفیت تماس', 'Call quality score'],
                ['script_compliance_score', 'امتیاز رعایت اسکریپت', 'Script compliance score'],
            ],
        ],
        'after_sales_support' => [
            'title' => 'پشتیبانی پس از فروش',
            'description' => 'ردیابی شکایت، حل مسئله و رفتار مشتری بعد از حل.',
            'items' => [
                ['complaint_registration', 'ثبت شکایت', 'Complaint registration'],
                ['issue_category', 'دسته‌بندی مسئله', 'Issue category'],
                ['resolution_time', 'زمان حل', 'Resolution time'],
                ['resolved_unresolved', 'حل‌شده / حل‌نشده', 'Resolved/unresolved status'],
                ['follow_up_after_resolution', 'پیگیری پس از حل', 'Follow-up after resolution'],
                ['satisfaction_after_resolution', 'رضایت پس از حل', 'Customer satisfaction after resolution'],
                ['repeat_purchase_after_complaint', 'خرید مجدد پس از شکایت', 'Repeat purchase after complaint'],
            ],
        ],
        'referral' => [
            'title' => 'ارجاع مشتری',
            'description' => 'ردیابی معرف، مشتری معرفی‌شده و تبدیل ارجاع.',
            'items' => [
                ['referred_customer', 'مشتری معرفی‌شده', 'Referred customer'],
                ['referrer_customer', 'مشتری معرف', 'Referrer customer'],
                ['referral_channel', 'کانال ارجاع', 'Referral channel'],
                ['referral_follow_up', 'پیگیری ارجاع', 'Referral follow-up'],
                ['referral_conversion', 'تبدیل ارجاع', 'Referral conversion'],
            ],
        ],
        'financial_reporting' => [
            'title' => 'گزارش مالی',
            'description' => 'قالب‌های KPI و گزارش؛ جایگزین حسابداری نیست.',
            'items' => [
                ['monthly_revenue', 'درآمد ماهانه', 'Monthly revenue'],
                ['cost_by_category', 'هزینه بر اساس دسته', 'Cost by category'],
                ['cash_flow', 'جریان نقد', 'Cash flow'],
                ['bank_balance', 'موجودی بانک', 'Bank balance'],
                ['cashbox_balance', 'موجودی صندوق', 'Cashbox balance'],
                ['receivables', 'دریافتنی‌ها', 'Receivables'],
                ['payables', 'پرداختنی‌ها', 'Payables'],
                ['profit_margin', 'حاشیه سود', 'Profit margin'],
                ['campaign_cost', 'هزینه کمپین', 'Campaign cost'],
                ['roas', 'ROAS', 'Return on ad spend'],
                ['cac', 'CAC', 'Customer acquisition cost'],
            ],
        ],
        'bsf' => [
            'title' => 'فرمول BSF',
            'description' => 'Lead × Conversion Rate × Purchase Count × Average Purchase × Profit Margin',
            'items' => [
                ['formula', 'فرمول BSF', 'Lead × Conversion Rate × Purchase Count × Average Purchase × Profit Margin'],
                ['lead_count', 'تعداد لید', 'Lead count'],
                ['conversion_rate', 'نرخ تبدیل', 'Conversion rate'],
                ['purchase_count', 'تعداد خرید', 'Purchase count'],
                ['average_purchase_value', 'میانگین خرید', 'Average purchase value'],
                ['profit_margin', 'حاشیه سود', 'Profit margin'],
            ],
        ],
        'sop_5s' => [
            'title' => 'SOP / 5S',
            'description' => 'استانداردهای عملیاتی، نظم، بهداشت و اقدام اصلاحی.',
            'items' => [
                ['sop_execution', 'اجرای SOP', 'SOP execution'],
                ['cleanliness', 'نظافت', 'Cleanliness'],
                ['order', 'نظم', 'Order'],
                ['discipline', 'انضباط', 'Discipline'],
                ['hygiene', 'بهداشت', 'Hygiene'],
                ['branch_readiness', 'آمادگی شعبه', 'Branch readiness'],
                ['issue_reporting', 'ثبت مسئله', 'Issue reporting'],
                ['corrective_action', 'اقدام اصلاحی', 'Corrective action'],
                ['five_s_audit', 'ممیزی 5S', '5S audit'],
            ],
        ],
        'behavior' => [
            'title' => 'رفتار سازمانی',
            'description' => 'ابعاد رفتاری برای آزمون، شرح وظایف و KPI.',
            'items' => [
                ['responsibility', 'مسئولیت‌پذیری', 'Responsibility'],
                ['ownership', 'مالکیت / عامل بودن', 'Ownership'],
                ['discipline', 'انضباط', 'Discipline'],
                ['teamwork', 'کار تیمی', 'Teamwork'],
                ['customer_orientation', 'مشتری‌مداری', 'Customer orientation'],
                ['calm_communication', 'ارتباط آرام', 'Calm communication'],
                ['professional_complaint_handling', 'رسیدگی حرفه‌ای به شکایت', 'Professional complaint handling'],
            ],
        ],
    ];
}

function seedKeyBusinessCoachingStandards(PDO $db, int $actorId = 0): array {
    $definitions = keyBusinessCoachingStandardsDefinitions();
    $standardStmt = $db->prepare('INSERT INTO business_standards (standard_key,standard_group,title,description,source_label,status) VALUES (?,?,?,?,?,"active") ON DUPLICATE KEY UPDATE standard_group=VALUES(standard_group),title=VALUES(title),description=VALUES(description),source_label=VALUES(source_label),status="active",updated_at=CURRENT_TIMESTAMP');
    $idStmt = $db->prepare('SELECT id FROM business_standards WHERE standard_key = ? LIMIT 1');
    $itemStmt = $db->prepare('INSERT INTO business_standard_items (standard_id,item_key,title,description,sort_order,status) VALUES (?,?,?,?,?,"active") ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),sort_order=VALUES(sort_order),status="active"');

    $groupCount = 0;
    $itemCount = 0;
    foreach ($definitions as $standardKey => $definition) {
        $standardStmt->execute([
            $standardKey,
            $standardKey,
            $definition['title'],
            $definition['description'],
            'Business coaching standards',
        ]);
        $idStmt->execute([$standardKey]);
        $standardId = (int)$idStmt->fetchColumn();
        if ($standardId <= 0) {
            continue;
        }
        $groupCount++;
        foreach ($definition['items'] as $index => $item) {
            $itemStmt->execute([$standardId, $item[0], $item[1], $item[2], ($index + 1) * 10]);
            $itemCount++;
        }
    }

    if (function_exists('hrAuditLog')) {
        hrAuditLog('business_standards', 'seed', null, 'seed_business_coaching_standards', $actorId ?: null, null, ['groups' => $groupCount, 'items' => $itemCount]);
    }

    return [
        'groups' => $groupCount,
        'items' => $itemCount,
        'inserted' => $groupCount + $itemCount,
        'updated' => 0,
        'skipped' => 0,
        'messages' => ['Business coaching standards are reference data only and are upserted by stable keys.'],
    ];
}
