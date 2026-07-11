<?php
/**
 * Idempotent professional restaurant HR test seed.
 * This file never deletes data. Existing records are updated by stable codes.
 *
 * Legacy status:
 * FINAL-3 disables this old question bank. The canonical organizational tests
 * seed is database/seeds/seed_key_restaurant_organizational_tests.php.
 */

if (!defined('LEGACY_HR_TESTS_SEED_DISABLED')) {
    define('LEGACY_HR_TESTS_SEED_DISABLED', true);
}

function hrRestaurantTestDefinitions(): array {
    return [
        ['DISC','DISC سازمانی','behavioral','positive',24,['D'=>'تصمیم‌گیری و قاطعیت','I'=>'ارتباط و اثرگذاری','S'=>'ثبات و همکاری','C'=>'دقت و نظم']],
        ['MBTI_ORG','MBTI سازمانی غیررسمی','behavioral','positive',32,['E'=>'ارتباط‌محور','I'=>'تمرکزگرا','S'=>'جزئیات‌گرا','N'=>'ایده‌گرا','T'=>'تصمیم منطقی','F'=>'تصمیم انسانی','J'=>'ساختارگرا','P'=>'انعطاف‌گرا']],
        ['ORG_EQ','هوش هیجانی سازمانی','behavioral','positive',30,['self_awareness'=>'خودآگاهی','self_control'=>'خودکنترلی','empathy'=>'همدلی','communication'=>'ارتباط مؤثر','conflict'=>'مدیریت تعارض']],
        ['JOB_SATISFACTION','رضایت شغلی','organizational','positive',25,['environment'=>'محیط کار','management'=>'مدیریت مستقیم','compensation'=>'حقوق و مزایا','growth'=>'رشد و یادگیری','team'=>'همکاری تیمی']],
        ['ORG_COMMITMENT','تعهد سازمانی','organizational','positive',18,['affective'=>'تعهد عاطفی','continuance'=>'تعهد مستمر','normative'=>'تعهد هنجاری']],
        ['BURNOUT_RISK','ریسک فرسودگی کاری سازمانی','organizational','risk',18,['exhaustion'=>'خستگی کاری','motivation'=>'افت انگیزه','relational_pressure'=>'فشار ارتباطی']],
        ['ROLE_FIT','تناسب نقش با شخصیت کاری','behavioral','positive',30,['accuracy'=>'دقت و نظم','speed'=>'سرعت عمل','customer'=>'ارتباط با مشتری','pressure'=>'تحمل فشار','teamwork'=>'کار تیمی','responsibility'=>'مسئولیت‌پذیری']],
        ['TEAM_READINESS','آمادگی کار تیمی','skills','positive',20,['cooperation'=>'همکاری','responsibility'=>'مسئولیت‌پذیری','conflict'=>'حل تعارض','support'=>'کمک به همکار','shift_coordination'=>'هماهنگی در شیفت']],
        ['CUSTOMER_READINESS','آمادگی برخورد با مشتری','skills','positive',25,['courtesy'=>'خوش‌برخوردی','complaint'=>'کنترل شکایت مشتری','response'=>'سرعت پاسخگویی','respect'=>'رعایت احترام','rush'=>'مدیریت شرایط شلوغ']],
        ['RUSH_STRESS','مدیریت استرس در محیط شلوغ','skills','positive',20,['emotion'=>'کنترل هیجان','priority'=>'اولویت‌بندی کارها','calm'=>'آرامش در پیک کاری','decision'=>'تصمیم‌گیری تحت فشار']],
        ['MENU_KNOWLEDGE','شناخت منوی رستوران','knowledge','positive',30,['items'=>'شناخت آیتم‌های منو','ingredients'=>'مواد اولیه','allergens'=>'آلرژن‌ها','pairing'=>'پیشنهاد ترکیبی','pricing'=>'قیمت و دسته‌بندی محصولات']],
        ['SERVICE_STANDARDS','استانداردهای سرویس‌دهی','knowledge','positive',25,['welcome'=>'خوشامدگویی','order'=>'ثبت سفارش','delivery'=>'تحویل سفارش','followup'=>'پیگیری رضایت مشتری','professionalism'=>'رفتار حرفه‌ای']],
        ['HYGIENE_SAFETY','بهداشت و ایمنی','knowledge','positive',30,['personal'=>'بهداشت فردی','environment'=>'بهداشت محیط','storage'=>'نگهداری مواد غذایی','equipment'=>'ایمنی تجهیزات','contamination'=>'کنترل آلودگی']],
        ['UPSELL','فروش پیشنهادی و Upsell','skills','positive',20,['opportunity'=>'شناخت فرصت فروش','respectful'=>'پیشنهاد محترمانه','combination'=>'ترکیب محصولات','basket'=>'افزایش ارزش فاکتور','no_pressure'=>'عدم ایجاد فشار روی مشتری']],
    ];
}

function hrSeedRestaurantProfessionalTests(PDO $db, int $actorId = 0): array {
    if (LEGACY_HR_TESTS_SEED_DISABLED) {
        if (function_exists('safeAdminLog')) {
            safeAdminLog('Legacy restaurant HR tests seed was blocked; use key_restaurant_organizational_tests.');
        }
        return [
            'tests' => 0,
            'questions' => 0,
            'skipped' => 1,
            'legacy_disabled' => true,
            'message' => 'بانک آزمون قدیمی غیرفعال شده است. از Seed جدید آزمون‌های سازمانی رستوران KEY استفاده کنید.',
        ];
    }

    $categoryStmt = $db->prepare('INSERT INTO hr_test_categories (title,slug,description,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),status=VALUES(status),sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)');
    foreach ([['رفتاری و سازمانی','behavioral',10],['مهارتی رستوران','skills',20],['دانش شغلی و ایمنی','knowledge',30],['نگرش سازمانی','organizational',40]] as [$title,$slug,$sort]) {
        $categoryStmt->execute([$title,$slug,'ارزیابی سازمانی، آموزشی و مدیریتی؛ غیرکلینیکی.','active',$sort,$actorId ?: null,$actorId ?: null]);
    }
    $categories = [];
    foreach ($db->query('SELECT id,slug FROM hr_test_categories WHERE deleted_at IS NULL')->fetchAll() as $row) $categories[(string)$row['slug']] = (int)$row['id'];

    $testStmt = $db->prepare("INSERT INTO hr_assessment_tests (title,test_code,category,category_id,age_guidance,question_count,description,source_license,scoring_method_type,analysis_type,intended_use,is_active,sort_order,show_disclaimer,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),category=VALUES(category),category_id=VALUES(category_id),question_count=VALUES(question_count),description=VALUES(description),source_license=VALUES(source_license),scoring_method_type=VALUES(scoring_method_type),analysis_type=VALUES(analysis_type),intended_use=VALUES(intended_use),is_active=1,sort_order=VALUES(sort_order),show_disclaimer=1,updated_by=VALUES(updated_by),deleted_at=NULL,deleted_by=NULL");
    $dimensionStmt = $db->prepare('INSERT INTO hr_test_dimensions (test_id,code,title,description,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),status=VALUES(status),sort_order=VALUES(sort_order),updated_by=VALUES(updated_by),deleted_at=NULL,deleted_by=NULL');
    $questionStmt = $db->prepare("INSERT INTO hr_test_questions (test_id,dimension_id,code,question_text,answer_type,question_type,options_json,weight,scoring_direction,score_direction,is_reverse_scored,is_required,is_critical,role_visibility,is_active,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE dimension_id=VALUES(dimension_id),question_text=VALUES(question_text),answer_type=VALUES(answer_type),question_type=VALUES(question_type),options_json=VALUES(options_json),weight=VALUES(weight),scoring_direction=VALUES(scoring_direction),score_direction=VALUES(score_direction),is_reverse_scored=VALUES(is_reverse_scored),is_required=VALUES(is_required),is_critical=VALUES(is_critical),role_visibility=VALUES(role_visibility),is_active=1,status='active',sort_order=VALUES(sort_order),updated_by=VALUES(updated_by),deleted_at=NULL,deleted_by=NULL");
    $ruleStmt = $db->prepare('INSERT INTO hr_test_scoring_rules (test_id,title,slug,description,rule_type,rule_config_json,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),rule_type=VALUES(rule_type),rule_config_json=VALUES(rule_config_json),status=VALUES(status),updated_by=VALUES(updated_by)');
    $recommendationStmt = $db->prepare('INSERT INTO hr_test_recommendations (test_id,title,slug,dimension_code,min_score,max_score,recommendation_text,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),min_score=VALUES(min_score),max_score=VALUES(max_score),recommendation_text=VALUES(recommendation_text),status=VALUES(status),updated_by=VALUES(updated_by)');
    $likert = hrJsonEncode([
        ['label'=>'کاملاً مخالفم','score'=>1],['label'=>'مخالفم','score'=>2],['label'=>'نظری ندارم','score'=>3],['label'=>'موافقم','score'=>4],['label'=>'کاملاً موافقم','score'=>5],
    ]);
    $scenario = hrJsonEncode([
        ['label'=>'بدون بررسی و بر اساس حدس پاسخ می‌دهم','score'=>1],
        ['label'=>'موضوع را به همکار دیگری واگذار می‌کنم','score'=>2],
        ['label'=>'منبع مصوب را بررسی و پاسخ دقیق و محترمانه ارائه می‌کنم','score'=>5],
        ['label'=>'برای سرعت بیشتر بخشی از استاندارد را نادیده می‌گیرم','score'=>1],
    ]);
    $stems = [
        'در شیفت کاری، این رفتار را به‌طور پایدار نشان می‌دهم: %s.',
        'وقتی کار فشرده می‌شود، کیفیت عملکرد من در «%s» حفظ می‌شود.',
        'برای بهبود نتیجه تیم، درباره «%s» بازخورد می‌گیرم و اقدام می‌کنم.',
        'در موقعیت واقعی رستوران، می‌توانم «%s» را مطابق استاندارد اجرا کنم.',
        'پیش از تصمیم‌گیری، پیامدهای مربوط به «%s» را بررسی می‌کنم.',
        'همکاران می‌توانند برای اجرای درست «%s» روی من حساب کنند.',
    ];
    $db->beginTransaction();
    try {
        $testCount = 0; $questionCount = 0; $sort = 10;
        foreach (hrRestaurantTestDefinitions() as [$code,$title,$category,$analysisType,$totalQuestions,$dimensions]) {
            $description = ($code === 'DISC' ? 'DISC-inspired و غیرکپی؛ صرفاً برای توسعه سازمانی. ' : ($code === 'MBTI_ORG' ? 'MBTI-inspired، غیررسمی و غیرکلینیکی. ' : '')) . hrAssessmentDisclaimer();
            $testStmt->execute([$title,$code,$category,$categories[$category] ?? null,'پرسنل بزرگسال',$totalQuestions,$description,'Original organizational content',$analysisType === 'risk' ? 'calculated' : 'calculated',$analysisType,'آموزش، توسعه فردی و تحلیل مدیریتی؛ نه تصمیم قطعی استخدامی',1,$sort,1,$actorId ?: null,$actorId ?: null]);
            $idStmt = $db->prepare('SELECT id FROM hr_assessment_tests WHERE test_code=? LIMIT 1'); $idStmt->execute([$code]); $testId=(int)$idStmt->fetchColumn();
            $perDimension = intdiv($totalQuestions, count($dimensions));
            $remainder = $totalQuestions % count($dimensions);
            $questionSort = 10; $dimensionIndex = 0;
            foreach ($dimensions as $dimensionCode => $dimensionTitle) {
                $dimensionIndex++;
                $dimensionStmt->execute([$testId,$dimensionCode,$dimensionTitle,'بعد «'.$dimensionTitle.'» در محیط کاری رستوران','active',$dimensionIndex*10,$actorId ?: null,$actorId ?: null]);
                $dimensionIdStmt=$db->prepare('SELECT id FROM hr_test_dimensions WHERE test_id=? AND code=? LIMIT 1'); $dimensionIdStmt->execute([$testId,$dimensionCode]); $dimensionId=(int)$dimensionIdStmt->fetchColumn();
                $count = $perDimension + ($dimensionIndex <= $remainder ? 1 : 0);
                for ($i=1; $i<=$count; $i++) {
                    $isKnowledge = $category === 'knowledge' || in_array($code, ['SERVICE_STANDARDS','UPSELL'], true);
                    $text = $isKnowledge
                        ? 'سناریوی '.$i.' درباره «'.$dimensionTitle.'»: کدام اقدام با منبع مصوب، ایمنی و تجربه محترمانه مشتری سازگارتر است؟'
                        : sprintf($stems[($i - 1) % count($stems)], $dimensionTitle);
                    $isCritical = $code === 'HYGIENE_SAFETY' && $i === 1;
                    $questionStmt->execute([$testId,$dimensionId,$code.'_'.$dimensionCode.'_'.$i,$text,$isKnowledge ? 'scenario' : 'likert_5',$isKnowledge ? 'scenario' : 'likert_5',$isKnowledge ? $scenario : $likert,1,'positive','positive',0,1,$isCritical,null,1,'active',$questionSort,$actorId ?: null,$actorId ?: null]);
                    $questionSort += 10; $questionCount++;
                }
                $recommendationStmt->execute([$testId,'پیشنهاد آموزشی '.$dimensionTitle,'training_'.$dimensionCode,$dimensionCode,0,59.99,'برای «'.$dimensionTitle.'» آموزش کوتاه، مشاهده عملی در شیفت و بازخورد سرپرست برنامه‌ریزی شود.','active',$dimensionIndex*10,$actorId ?: null,$actorId ?: null]);
            }
            $ruleConfig = ['analysis_type'=>$analysisType,'positive_levels'=>[40,60,75,90],'risk_levels'=>[40,65,80],'disc_combination_delta'=>10,'critical_threshold'=>50];
            if ($code === 'MBTI_ORG') $ruleConfig['pairs'] = [['E','I'],['S','N'],['T','F'],['J','P']];
            $ruleStmt->execute([$testId,'قاعده امتیازدهی '.$title,'default','امتیاز وزنی ابعاد و سطح‌بندی سازمانی',$analysisType,hrJsonEncode($ruleConfig),'active',10,$actorId ?: null,$actorId ?: null]);
            $sort += 10; $testCount++;
        }
        $fitStmt=$db->prepare("SELECT id FROM hr_assessment_tests WHERE test_code='ROLE_FIT' LIMIT 1"); $fitStmt->execute(); $fitId=(int)$fitStmt->fetchColumn();
        $profileStmt=$db->prepare('INSERT INTO hr_test_role_profiles (test_id,title,slug,role_code,description,dimension_targets_json,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),dimension_targets_json=VALUES(dimension_targets_json),status=VALUES(status),updated_by=VALUES(updated_by)');
        foreach (['hall'=>'سالن‌کار','cashier'=>'صندوقدار','barista'=>'باریستا','kitchen'=>'آشپزخانه','warehouse'=>'انبار','shift_supervisor'=>'سرپرست شیفت','branch_manager'=>'مدیر شعبه','support'=>'پشتیبانی'] as $role=>$roleTitle) {
            $profileStmt->execute([$fitId,$roleTitle,$role,$role,'پروفایل پیشنهادی قابل ویرایش برای مقایسه توسعه‌ای.',hrJsonEncode(['accuracy'=>75,'speed'=>70,'customer'=>70,'pressure'=>70,'teamwork'=>75,'responsibility'=>80]),'active',10,$actorId ?: null,$actorId ?: null]);
        }
        $db->commit();
        hrTestAudit($db,'seed','test_catalog',null,['description'=>'Seed حرفه‌ای آزمون‌های پرسنل اجرا شد.','tests'=>$testCount,'questions'=>$questionCount],$actorId);
        return ['tests'=>$testCount,'questions'=>$questionCount];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
