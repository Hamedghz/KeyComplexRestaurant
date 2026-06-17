<?php
require_once __DIR__ . '/core/bootstrap.php';

if (!function_exists('surveyPageH')) {
    function surveyPageH($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function surveyPageDb(): PDO {
    return Database::getInstance()->getConnection();
}

function surveyPageTableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function surveyPageColumnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function surveyPageEnsureColumn(PDO $db, string $table, string $column, string $definition): void {
    if (!surveyPageTableExists($db, $table) || surveyPageColumnExists($db, $table, $column)) return;
    try {
        $db->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $definition);
    } catch (Throwable $e) {
        error_log('[survey] schema repair failed for ' . $table . '.' . $column . ': ' . $e->getMessage());
    }
}

function surveyPageEnsureSchema(PDO $db): void {
    surveyPageEnsureColumn($db, 'dynamic_forms', 'related_page', "varchar(100) DEFAULT 'survey'");
    surveyPageEnsureColumn($db, 'dynamic_forms', 'start_date', 'datetime DEFAULT NULL');
    surveyPageEnsureColumn($db, 'dynamic_forms', 'end_date', 'datetime DEFAULT NULL');
    surveyPageEnsureColumn($db, 'dynamic_forms', 'display_order', 'int(11) NOT NULL DEFAULT 0');
    surveyPageEnsureColumn($db, 'dynamic_forms', 'branch_id', 'int(11) UNSIGNED DEFAULT NULL');
    surveyPageEnsureColumn($db, 'survey_responses', 'customer_mobile', 'varchar(20) DEFAULT NULL');
    surveyPageEnsureColumn($db, 'survey_responses', 'customer_email', 'varchar(150) DEFAULT NULL');
    surveyPageEnsureColumn($db, 'survey_responses', 'satisfaction_score', 'tinyint DEFAULT NULL');
    surveyPageEnsureColumn($db, 'survey_responses', 'is_dissatisfied', 'tinyint(1) NOT NULL DEFAULT 0');
    surveyPageEnsureColumn($db, 'survey_responses', 'crm_follow_up', 'tinyint(1) NOT NULL DEFAULT 0');
    surveyPageEnsureColumn($db, 'survey_responses', 'branch_id', 'int(11) UNSIGNED DEFAULT NULL');
    surveyPageEnsureColumn($db, 'survey_responses', 'submitted_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');
    surveyPageEnsureColumn($db, 'survey_responses', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');
    if (surveyPageColumnExists($db, 'dynamic_forms', 'related_page')) {
        try {
            $db->exec("UPDATE `dynamic_forms` SET `related_page` = 'survey' WHERE `related_page` IS NULL OR `related_page` = ''");
        } catch (Throwable $e) {
            error_log('[survey] related_page normalization failed: ' . $e->getMessage());
        }
    }
}

function surveyPageActiveSurvey(PDO $db): ?array {
    $stmt = $db->prepare("
        SELECT *
        FROM dynamic_forms
        WHERE is_active = 1
          AND related_page = 'survey'
          AND (start_date IS NULL OR start_date <= NOW())
          AND (end_date IS NULL OR end_date >= NOW())
        ORDER BY display_order ASC, id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $survey = $stmt->fetch();
    return $survey ?: null;
}

function surveyPageDecodeSchema($schema): array {
    $decoded = json_decode((string)$schema, true);
    if (!is_array($decoded) || !isset($decoded['fields']) || !is_array($decoded['fields'])) {
        return ['fields' => []];
    }
    return $decoded;
}

function surveyPageNormalizeFields(array $schema): array {
    $fields = [];
    foreach (($schema['fields'] ?? []) as $index => $field) {
        if (!is_array($field)) continue;
        $type = (string)($field['type'] ?? 'text');
        if ($type === 'stars') $type = 'rating';
        if ($type === 'multiple_choice') $type = 'radio';
        if (!in_array($type, ['rating', 'radio', 'text', 'mobile', 'date', 'textarea'], true)) continue;

        $options = [];
        foreach (($field['options'] ?? []) as $option) {
            if (is_array($option)) {
                $optionValue = (string)($option['value'] ?? $option['label_fa'] ?? $option['label'] ?? '');
                $optionLabel = (string)($option['label_fa'] ?? $option['label'] ?? $optionValue);
            } else {
                $optionValue = (string)$option;
                $optionLabel = (string)$option;
            }
            if (trim($optionValue) !== '') {
                $options[] = ['value' => $optionValue, 'label' => $optionLabel];
            }
        }

        $fields[] = [
            'key' => (string)($field['key'] ?? $field['id'] ?? 'field_' . ($index + 1)),
            'label' => (string)($field['label'] ?? $field['label_fa'] ?? ''),
            'type' => $type,
            'required' => !empty($field['required']),
            'max' => (int)($field['max'] ?? $field['max_stars'] ?? 5),
            'options' => $options,
            'sort_order' => (int)($field['sort_order'] ?? ($index + 1)),
        ];
    }
    usort($fields, static fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    return $fields;
}

function surveyPageClientIp(): ?string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (empty($_SERVER[$key])) continue;
        $value = trim(explode(',', (string)$_SERVER[$key])[0]);
        if ($value !== '') return mb_substr($value, 0, 45);
    }
    return null;
}

function surveyPageFindAnswer(array $answers, array $fields, array $types, array $fallbackKeys = []): ?string {
    foreach ($fields as $field) {
        if (!in_array($field['type'], $types, true)) continue;
        $key = $field['key'];
        if (isset($answers[$key]) && trim((string)$answers[$key]) !== '') return (string)$answers[$key];
    }
    foreach ($fallbackKeys as $key) {
        if (isset($answers[$key]) && trim((string)$answers[$key]) !== '') return (string)$answers[$key];
    }
    return null;
}

$db = surveyPageDb();
surveyPageEnsureSchema($db);
$survey = surveyPageActiveSurvey($db);
$fields = $survey ? surveyPageNormalizeFields(surveyPageDecodeSchema($survey['form_schema'] ?? '')) : [];
$errors = [];
$success = '';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!empty($_SESSION['survey_flash_success'])) {
    $success = (string)$_SESSION['survey_flash_success'];
    unset($_SESSION['survey_flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.';
    }

    $survey = surveyPageActiveSurvey($db);
    $fields = $survey ? surveyPageNormalizeFields(surveyPageDecodeSchema($survey['form_schema'] ?? '')) : [];
    if (!$survey || !$fields) {
        $errors[] = 'در حال حاضر نظرسنجی فعالی وجود ندارد.';
    }

    $answers = [];
    if (!$errors) {
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = trim((string)($_POST['answers'][$key] ?? ''));
            if ($field['required'] && $value === '') {
                $errors[] = 'فیلد «' . $field['label'] . '» الزامی است.';
                continue;
            }
            if ($value === '') {
                $answers[$key] = '';
                continue;
            }

            if ($field['type'] === 'rating') {
                if (!ctype_digit($value) || (int)$value < 1 || (int)$value > max(1, $field['max'])) {
                    $errors[] = 'مقدار «' . $field['label'] . '» معتبر نیست.';
                    continue;
                }
                $answers[$key] = (int)$value;
            } elseif ($field['type'] === 'radio') {
                $validOptions = array_column($field['options'], 'value');
                if (!in_array($value, $validOptions, true)) {
                    $errors[] = 'گزینه انتخابی «' . $field['label'] . '» معتبر نیست.';
                    continue;
                }
                $answers[$key] = $value;
            } elseif ($field['type'] === 'mobile') {
                if (!preg_match('/^09[0-9]{9}$/', $value)) {
                    $errors[] = 'شماره موبایل باید با فرمت 09xxxxxxxxx وارد شود.';
                    continue;
                }
                $answers[$key] = $value;
            } elseif ($field['type'] === 'date') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $errors[] = 'تاریخ وارد شده معتبر نیست.';
                    continue;
                }
                $answers[$key] = $value;
            } else {
                $answers[$key] = mb_substr($value, 0, $field['type'] === 'textarea' ? 2000 : 255);
            }
        }
    }

    if (!$errors) {
        $customerName = surveyPageFindAnswer($answers, $fields, ['text'], ['customer_name']);
        $customerMobile = surveyPageFindAnswer($answers, $fields, ['mobile'], ['customer_mobile']);
        $rating = surveyPageFindAnswer($answers, $fields, ['rating']);
        $satisfactionScore = $rating !== null ? (int)$rating : null;
        $isDissatisfied = $satisfactionScore !== null && $satisfactionScore <= 2 ? 1 : 0;

        $stmt = $db->prepare("
            INSERT INTO survey_responses
                (form_id, response_data, customer_name, customer_mobile, customer_email, satisfaction_score, is_dissatisfied, crm_follow_up, ip_address, user_agent, branch_id, submitted_at)
            VALUES
                (:form_id, :response_data, :customer_name, :customer_mobile, :customer_email, :satisfaction_score, :is_dissatisfied, 0, :ip_address, :user_agent, :branch_id, NOW())
        ");
        $stmt->execute([
            'form_id' => (int)$survey['id'],
            'response_data' => json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'customer_name' => $customerName,
            'customer_mobile' => $customerMobile,
            'customer_email' => null,
            'satisfaction_score' => $satisfactionScore,
            'is_dissatisfied' => $isDissatisfied,
            'ip_address' => surveyPageClientIp(),
            'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'branch_id' => $survey['branch_id'] ?? null,
        ]);

        $_SESSION['survey_flash_success'] = 'پاسخ شما با موفقیت ثبت شد. از همراهی شما سپاسگزاریم.';
        header('Location: survey.php?submitted=1');
        exit;
    }
}

$title = $survey['form_title_fa'] ?? 'نظرسنجی';
$description = $survey['form_description_fa'] ?? '';
$token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo surveyPageH($title); ?> - KEY</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;font-family:Tahoma,Arial,sans-serif;background:#004647;color:#fff;display:flex;align-items:center;justify-content:center;padding:24px;direction:rtl}
        .survey-shell{width:100%;max-width:680px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.25)}
        .logo{width:64px;height:64px;margin:0 auto 18px;background:#d4af37;color:#004647;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:30px}
        h1{text-align:center;font-size:26px;margin:0 0 10px}
        .description{text-align:center;color:rgba(255,255,255,.82);line-height:1.8;margin:0 0 24px}
        .field{margin-bottom:22px}
        label.field-label{display:block;font-weight:700;margin-bottom:10px}
        .required{color:#ffd66b;margin-right:4px}
        input[type=text],input[type=tel],input[type=date],textarea{width:100%;border:1px solid rgba(255,255,255,.28);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;padding:12px 14px;font:inherit}
        textarea{min-height:110px;resize:vertical}
        .options{display:grid;gap:10px}
        .option{display:flex;align-items:center;gap:8px;border:1px solid rgba(255,255,255,.24);border-radius:10px;padding:11px;background:rgba(255,255,255,.08);cursor:pointer}
        .stars{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:6px}
        .stars input{position:absolute;opacity:0}
        .stars label{font-size:34px;color:rgba(255,255,255,.35);cursor:pointer;line-height:1}
        .stars input:checked ~ label,.stars label:hover,.stars label:hover ~ label{color:#d4af37}
        .alert{border-radius:10px;padding:13px 15px;margin-bottom:18px;line-height:1.8}
        .alert-success{background:rgba(25,135,84,.25);border:1px solid rgba(25,135,84,.55)}
        .alert-error{background:rgba(220,53,69,.22);border:1px solid rgba(220,53,69,.55)}
        button{width:100%;border:0;border-radius:10px;background:#d4af37;color:#003536;font:inherit;font-weight:800;padding:14px;cursor:pointer}
        .empty{text-align:center;line-height:2;font-size:18px}
    </style>
</head>
<body>
<main class="survey-shell">
    <div class="logo">K</div>
    <?php if (!$survey || !$fields): ?>
        <p class="empty">در حال حاضر نظرسنجی فعالی وجود ندارد.</p>
    <?php else: ?>
        <h1><?php echo surveyPageH($title); ?></h1>
        <?php if ($description): ?><p class="description"><?php echo surveyPageH($description); ?></p><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo surveyPageH($success); ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><?php echo surveyPageH(implode(' ', $errors)); ?></div><?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="<?php echo surveyPageH(CSRF_TOKEN_NAME); ?>" value="<?php echo surveyPageH($token); ?>">
            <?php foreach ($fields as $field): $name = 'answers[' . $field['key'] . ']'; $old = $_POST['answers'][$field['key']] ?? ''; ?>
                <div class="field">
                    <label class="field-label">
                        <?php echo surveyPageH($field['label']); ?>
                        <?php if ($field['required']): ?><span class="required">*</span><?php endif; ?>
                    </label>
                    <?php if ($field['type'] === 'rating'): ?>
                        <div class="stars">
                            <?php for ($i = $field['max']; $i >= 1; $i--): ?>
                                <input id="<?php echo surveyPageH($field['key'] . '_' . $i); ?>" type="radio" name="<?php echo surveyPageH($name); ?>" value="<?php echo $i; ?>" <?php echo (string)$old === (string)$i ? 'checked' : ''; ?> <?php echo $field['required'] ? 'required' : ''; ?>>
                                <label for="<?php echo surveyPageH($field['key'] . '_' . $i); ?>">★</label>
                            <?php endfor; ?>
                        </div>
                    <?php elseif ($field['type'] === 'radio'): ?>
                        <div class="options">
                            <?php foreach ($field['options'] as $option): ?>
                                <label class="option">
                                    <input type="radio" name="<?php echo surveyPageH($name); ?>" value="<?php echo surveyPageH($option['value']); ?>" <?php echo (string)$old === (string)$option['value'] ? 'checked' : ''; ?> <?php echo $field['required'] ? 'required' : ''; ?>>
                                    <span><?php echo surveyPageH($option['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <textarea name="<?php echo surveyPageH($name); ?>" <?php echo $field['required'] ? 'required' : ''; ?>><?php echo surveyPageH($old); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo $field['type'] === 'date' ? 'date' : ($field['type'] === 'mobile' ? 'tel' : 'text'); ?>" name="<?php echo surveyPageH($name); ?>" value="<?php echo surveyPageH($old); ?>" <?php echo $field['type'] === 'mobile' ? 'pattern="09[0-9]{9}"' : ''; ?> <?php echo $field['required'] ? 'required' : ''; ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit">ارسال پاسخ</button>
        </form>
    <?php endif; ?>
</main>
<script src="/assets/js/analytics-tracker.js" defer></script>
</body>
</html>
