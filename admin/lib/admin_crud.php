<?php
require_once __DIR__ . '/../../core/models/GenericModel.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/admin_schema.php';

if (!function_exists('adminGuard')) {
function adminGuard($requiredRole = 'employee') {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    try {
        $auth = new Auth();
    } catch (Throwable $e) {
        error_log('[admin] Authentication bootstrap failed: ' . $e->getMessage());
        http_response_code(500);
        exit('درخواست قابل پردازش نیست. جزئیات خطا در لاگ سیستم ثبت شد.');
    }
    if (!$auth->isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
    if (!$auth->hasPermission($requiredRole)) {
        http_response_code(403);
        exit('دسترسی کافی نیست.');
    }
    return $auth->getCurrentAdmin();
}
}

if (!function_exists('h')) {
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('redirectTo')) {
function redirectTo($url) { header('Location: ' . $url); exit; }
}

function adminPermissionAllows(?array $admin, string $permission, array $fallbackRoles = ['manager','admin','super_admin']): bool {
    if (!$admin) {
        return false;
    }
    $role = (string)($admin['role'] ?? 'employee');
    if ($role === 'super_admin') {
        return true;
    }
    $raw = $admin['permissions'] ?? null;
    $permissions = [];
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $permissions = $decoded;
        }
    } elseif (is_array($raw)) {
        $permissions = $raw;
    }
    if (array_key_exists($permission, $permissions)) {
        return (bool)$permissions[$permission];
    }
    return in_array($role, $fallbackRoles, true);
}

function fetchAcquisitionSourceOptions(): array {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query('SELECT title FROM acquisition_sources WHERE active = 1 ORDER BY sort_order ASC, title ASC');
        $rows = $stmt->fetchAll();
        if ($rows) {
            $options = [];
            foreach ($rows as $row) {
                $options[$row['title']] = $row['title'];
            }
            return $options;
        }
    } catch (Throwable $e) {
        // Schema may not be installed yet; fall back to accepted defaults.
    }
    return ['Instagram'=>'Instagram','Telegram'=>'Telegram','Google'=>'Google','Balad'=>'Balad','Friend Referral'=>'Friend Referral','Walk-in'=>'Walk-in','Website'=>'Website','Advertisement'=>'Advertisement','Other'=>'Other'];
}

if (!function_exists('safeAdminLog')) {
function safeAdminLog(string $message): void {
    error_log('[admin] ' . $message);
}
}

function adminModuleConfigs() {
    return [
        'crm' => [
            'title' => 'CRM مشتریان', 'min_role' => 'manager', 'table' => 'crm_customers', 'unique' => 'mobile', 'date_fields' => ['birth_date','first_purchase_date','reminder_date','last_visit_date'],
            'search' => ['full_name','mobile','tags','acquisition_source'], 'filters' => ['acquisition_source','tags','attended_match_event'],
            'fields' => [
                'full_name'=>['label'=>'نام کامل','type'=>'text','required'=>true], 'mobile'=>['label'=>'موبایل','type'=>'mobile','required'=>true],
                'birth_date'=>['label'=>'تولد','type'=>'date'], 'first_purchase_date'=>['label'=>'اولین خرید','type'=>'date'],
                'total_orders'=>['label'=>'تعداد سفارش','type'=>'number'], 'total_purchase_volume'=>['label'=>'حجم خرید','type'=>'number'],
                'reminder_date'=>['label'=>'یادآوری','type'=>'date'], 'acquisition_source'=>['label'=>'منبع جذب','type'=>'select','options'=>fetchAcquisitionSourceOptions()],
                'notes'=>['label'=>'یادداشت','type'=>'textarea'], 'surveys_completed_count'=>['label'=>'تعداد نظرسنجی','type'=>'number'],
                'last_visit_date'=>['label'=>'آخرین مراجعه','type'=>'date'], 'tags'=>['label'=>'برچسب‌ها','type'=>'text'],
                'attended_match_event'=>['label'=>'حضور در رویداد مسابقه','type'=>'checkbox'],
            ],
            'columns' => ['id','full_name','mobile','birth_date','first_purchase_date','total_orders','total_purchase_volume','acquisition_source','attended_match_event','tags','created_at'],
        ],
        'matches' => [
            'title'=>'مدیریت مسابقات','min_role'=>'manager','table'=>'matches','unique'=>null,'date_fields'=>['match_date','prediction_open_at','prediction_close_at'],
            'search'=>['team_a','team_b','status'],'filters'=>['status','is_active','active_for_prediction','match_finished'],
            'fields'=>[
                'team_a'=>['label'=>'تیم اول','type'=>'text','required'=>true], 'team_b'=>['label'=>'تیم دوم','type'=>'text','required'=>true],
                'match_date'=>['label'=>'تاریخ مسابقه','type'=>'date','required'=>true], 'kickoff_time'=>['label'=>'ساعت شروع','type'=>'time','required'=>true],
                'broadcast_time'=>['label'=>'ساعت پخش','type'=>'time'], 'prediction_open_at'=>['label'=>'شروع پیش‌بینی','type'=>'datetime','required'=>true],
                'prediction_close_at'=>['label'=>'پایان پیش‌بینی','type'=>'datetime','required'=>true], 'status'=>['label'=>'وضعیت','type'=>'select','options'=>['scheduled'=>'برنامه‌ریزی شده','live'=>'زنده','finished'=>'تمام شده','cancelled'=>'لغو شده']],
                'is_active'=>['label'=>'فعال','type'=>'checkbox'], 'active_for_prediction'=>['label'=>'فعال برای پیش‌بینی','type'=>'checkbox'],
                'final_score_team_a'=>['label'=>'نتیجه نهایی تیم اول','type'=>'number'],
                'final_score_team_b'=>['label'=>'نتیجه نهایی تیم دوم','type'=>'number'],
                'match_finished'=>['label'=>'مسابقه تمام شده','type'=>'checkbox'],
            ], 'columns'=>['id','team_a','team_b','match_date','kickoff_time','broadcast_time','status','is_active','active_for_prediction']
        ],
        'predictions' => [
            'title'=>'پیش‌بینی‌ها','min_role'=>'manager','table'=>'predictions','unique'=>'mobile','date_fields'=>[], 'readonly_create'=>true,
            'search'=>['customer_name','mobile'],'filters'=>['crm_matched','customer_exists','attended_match_time','match_id','is_correct_prediction','crm_match','attended_match'],
            'join'=>'SELECT p.*, CONCAT(m.team_a, " - ", m.team_b) AS match_title FROM predictions p LEFT JOIN matches m ON p.match_id = m.id', 'required_tables'=>['matches'],
            'fields'=>[
                'customer_name'=>['label'=>'نام','type'=>'text','required'=>true], 'mobile'=>['label'=>'موبایل','type'=>'mobile','required'=>true],
                'match_id'=>['label'=>'مسابقه','type'=>'match','required'=>true], 'predicted_score_team_a'=>['label'=>'گل تیم اول','type'=>'number','required'=>true],
                'predicted_score_team_b'=>['label'=>'گل تیم دوم','type'=>'number','required'=>true], 'crm_matched'=>['label'=>'CRM Match','type'=>'checkbox'],
                'customer_exists'=>['label'=>'مشتری موجود','type'=>'checkbox'], 'attended_match_time'=>['label'=>'حضور زمان مسابقه','type'=>'checkbox'],
                'is_correct_prediction'=>['label'=>'پیش‌بینی صحیح','type'=>'checkbox'],
                'crm_match'=>['label'=>'CRM Match (جدید)','type'=>'checkbox'],
                'attended_match'=>['label'=>'حضور در مسابقه (جدید)','type'=>'checkbox'],
            ], 'columns'=>['id','customer_name','mobile','match_title','predicted_score_team_a','predicted_score_team_b','crm_matched','customer_exists','attended_match_time','created_at']
        ],
        'banners' => [
            'title'=>'بنرهای اصلی','min_role'=>'manager','table'=>'hero_banners','unique'=>null,'date_fields'=>['start_date','end_date'], 'search'=>['title','subtitle'],'filters'=>['active_status'],
            'fields'=>[
                'title'=>['label'=>'عنوان','type'=>'text','required'=>true], 'subtitle'=>['label'=>'زیرعنوان','type'=>'text'], 'description'=>['label'=>'توضیح','type'=>'textarea'],
                'button_text'=>['label'=>'متن دکمه','type'=>'text'], 'button_link'=>['label'=>'لینک دکمه','type'=>'text'], 'image'=>['label'=>'تصویر','type'=>'image','path'=>'uploads/banners'],
                'mobile_image'=>['label'=>'تصویر موبایل','type'=>'image','path'=>'uploads/banners'], 'display_order'=>['label'=>'ترتیب','type'=>'number'],
                'active_status'=>['label'=>'فعال','type'=>'checkbox'], 'start_date'=>['label'=>'شروع','type'=>'datetime'], 'end_date'=>['label'=>'پایان','type'=>'datetime'],
            ], 'columns'=>['id','title','display_order','active_status','start_date','end_date','created_at']
        ],
        'categories' => [
            'title'=>'فیلترها و دسته‌بندی منو','min_role'=>'manager','table'=>'menu_categories','unique'=>'slug','date_fields'=>[], 'search'=>['name_fa','name_en','slug'],'filters'=>['is_active'],
            'fields'=>[
                'name_fa'=>['label'=>'نام فارسی','type'=>'text','required'=>true], 'name_en'=>['label'=>'نام انگلیسی','type'=>'text'], 'slug'=>['label'=>'اسلاگ','type'=>'text','required'=>true],
                'icon'=>['label'=>'آیکن','type'=>'text'], 'sort_order'=>['label'=>'ترتیب','type'=>'number'], 'is_active'=>['label'=>'فعال','type'=>'checkbox'],
            ], 'columns'=>['id','name_fa','slug','icon','sort_order','is_active']
        ],
        'menu-items' => [
            'title'=>'آیتم‌های منو','min_role'=>'manager','table'=>'menu_items','unique'=>'slug','date_fields'=>[], 'search'=>['name_fa','name_en','slug','description_fa'],'filters'=>['category_id','is_available'],
            'join'=>'SELECT mi.*, mc.name_fa AS category_title FROM menu_items mi LEFT JOIN menu_categories mc ON mi.category_id = mc.id', 'required_tables'=>['menu_categories'],
            'fields'=>[
                'category_id'=>['label'=>'دسته‌بندی','type'=>'category','required'=>true], 'name_fa'=>['label'=>'عنوان فارسی','type'=>'text','required'=>true], 'name_en'=>['label'=>'عنوان انگلیسی','type'=>'text'],
                'slug'=>['label'=>'اسلاگ','type'=>'text','required'=>true], 'description_fa'=>['label'=>'توضیح فارسی','type'=>'textarea'], 'description_en'=>['label'=>'توضیح انگلیسی','type'=>'textarea'],
                'image'=>['label'=>'تصویر','type'=>'image','path'=>'uploads/menu'], 'gallery_images'=>['label'=>'گالری (با کاما)','type'=>'text'], 'price'=>['label'=>'قیمت','type'=>'number','required'=>true],
                'discount_price'=>['label'=>'قیمت تخفیف','type'=>'number'], 'is_available'=>['label'=>'فعال','type'=>'checkbox'], 'is_featured'=>['label'=>'ویژه','type'=>'checkbox'], 'sort_order'=>['label'=>'ترتیب','type'=>'number'],
            ], 'columns'=>['id','category_title','name_fa','price','discount_price','is_available','sort_order']
        ],
        'surveys' => [
            'title'=>'فرم‌های نظرسنجی','min_role'=>'admin','table'=>'dynamic_forms','unique'=>'form_name','date_fields'=>[], 'search'=>['form_name','form_title_fa'],'filters'=>['is_active'],
            'fields'=>[
                'form_name'=>['label'=>'نام سیستمی','type'=>'text','required'=>true], 'form_title_fa'=>['label'=>'عنوان فارسی','type'=>'text','required'=>true], 'form_title_en'=>['label'=>'عنوان انگلیسی','type'=>'text'],
                'form_description_fa'=>['label'=>'توضیح فارسی','type'=>'textarea'], 'form_schema'=>['label'=>'Schema JSON','type'=>'textarea','default'=>'{"fields":[]}'],
                'is_active'=>['label'=>'فعال','type'=>'checkbox'], 'display_order'=>['label'=>'ترتیب','type'=>'number'],
            ], 'columns'=>['id','form_name','form_title_fa','is_active','display_order','created_at']
        ],
        'survey-responses' => [
            'title'=>'پاسخ‌های نظرسنجی','min_role'=>'manager','table'=>'survey_responses','unique'=>'customer_phone','date_fields'=>[], 'date_column'=>'submitted_at', 'search'=>['customer_name','customer_phone','customer_email'],'filters'=>['form_id'],
            'join'=>'SELECT sr.*, df.form_title_fa AS form_title FROM survey_responses sr LEFT JOIN dynamic_forms df ON sr.form_id = df.id', 'required_tables'=>['dynamic_forms'],
            'fields'=>[
                'form_id'=>['label'=>'فرم','type'=>'survey_form','required'=>true], 'customer_name'=>['label'=>'نام','type'=>'text'], 'customer_phone'=>['label'=>'موبایل','type'=>'mobile'],
                'customer_email'=>['label'=>'ایمیل','type'=>'text'], 'response_data'=>['label'=>'داده پاسخ JSON','type'=>'textarea','default'=>'{}'],
            ], 'columns'=>['id','form_title','customer_name','customer_phone','customer_email','submitted_at']
        ],
    ];
}

function moduleConfig($key) { $configs = adminModuleConfigs(); return $configs[$key] ?? null; }
function labelFor($config, $column) { return $config['fields'][$column]['label'] ?? ['id'=>'شناسه','created_at'=>'ایجاد','updated_at'=>'ویرایش','match_title'=>'مسابقه','category_title'=>'دسته‌بندی','submitted_at'=>'ثبت'][$column] ?? $column; }

function fetchOptions($type) {
    $queries = [
        'category' => ['table' => 'menu_categories', 'sql' => 'SELECT id, name_fa AS title FROM menu_categories ORDER BY sort_order, name_fa'],
        'match' => ['table' => 'matches', 'sql' => "SELECT id, CONCAT(team_a, ' - ', team_b, ' (', match_date, ')') AS title FROM matches ORDER BY match_date DESC"],
        'survey_form' => ['table' => 'dynamic_forms', 'sql' => 'SELECT id, form_title_fa AS title FROM dynamic_forms ORDER BY display_order, id DESC'],
    ];
    if (!isset($queries[$type]) || !adminTableExists($queries[$type]['table'])) return [];
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare($queries[$type]['sql']);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        safeAdminLog('Option lookup failed for ' . $type . ': ' . $e->getMessage());
        return [];
    }
}


function uploadedImageValue($field, $current = '', $path = 'uploads/menu') {
    if (empty($_FILES[$field]['name'])) return $current;
    $validation = validateUploadedFile($_FILES[$field], ALLOWED_IMAGE_EXTENSIONS, ALLOWED_IMAGE_TYPES);
    if (!$validation['valid']) {
        throw new RuntimeException($validation['message']);
    }
    $destDir = ROOT_PATH . '/' . trim($path, '/');
    return optimizeUploadedImage($_FILES[$field]['tmp_name'], $destDir, uniqid($field . '_', true));
}

function collectData($config, $current = []) {
    $data = [];
    foreach ($config['fields'] as $name => $meta) {
        $type = $meta['type'];
        if ($type === 'image') {
            $data[$name] = uploadedImageValue($name, $current[$name] ?? '', $meta['path'] ?? 'uploads/menu');
            continue;
        }
        if ($type === 'checkbox') {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }
        $value = $_POST[$name] ?? ($meta['default'] ?? null);
        if ($type === 'mobile') $value = normalizeMobile($value);
        if ($type === 'date') $value = parsePersianDate($value, false);
        if ($type === 'datetime') $value = parsePersianDate($value, true);
        $data[$name] = $value === '' ? null : $value;
    }
    return $data;
}

function recalculatePredictionsForMatch(int $matchId): void {
    if ($matchId <= 0 || !adminTableExists('matches') || !adminTableExists('predictions')) {
        return;
    }
    if (!adminColumnExists('matches', 'match_finished') || !adminColumnExists('matches', 'final_score_team_a') || !adminColumnExists('matches', 'final_score_team_b') || !adminColumnExists('predictions', 'is_correct_prediction')) {
        return;
    }
    $db = Database::getInstance()->getConnection();
    try {
        $stmt = $db->prepare('UPDATE predictions p JOIN matches m ON m.id = p.match_id SET p.is_correct_prediction = CASE WHEN m.match_finished = 1 AND m.final_score_team_a IS NOT NULL AND m.final_score_team_b IS NOT NULL AND p.predicted_score_team_a = m.final_score_team_a AND p.predicted_score_team_b = m.final_score_team_b THEN 1 ELSE 0 END WHERE p.match_id = :match_id');
        $stmt->execute(['match_id' => $matchId]);
    } catch (Throwable $e) {
        safeAdminLog('Prediction recalculation failed for match ' . $matchId . ': ' . $e->getMessage());
    }
}

function existingColumns(string $table): array {
    if (!adminTableExists($table)) {
        return [];
    }
    try {
        $columns = schemaColumns($table);
        return array_map(static fn($row) => $row['Field'], $columns);
    } catch (Throwable $e) {
        safeAdminLog("Cannot read columns for {$table}: " . $e->getMessage());
        return [];
    }
}

function adminRenderSafeError(string $title, string $logMessage, int $statusCode = 500): void {
    safeAdminLog($logMessage);
    http_response_code($statusCode);
    $pageTitle = $title;
    include __DIR__ . '/../includes/header.php';
    echo '<div class="card"><div class="card-body"><div class="alert" style="background:#f8d7da;color:#721c24">درخواست قابل پردازش نیست. جزئیات خطا در لاگ سیستم ثبت شد.</div></div></div>';
    include __DIR__ . '/../includes/footer.php';
}

function adminRequiredTables(array $config): array {
    $tables = [$config['table']];
    foreach (($config['required_tables'] ?? []) as $table) {
        $tables[] = $table;
    }
    return array_values(array_unique($tables));
}

function adminSqlPrefix(array $config): string {
    if (!empty($config['alias'])) {
        return $config['alias'] . '.';
    }
    if ($config['table'] === 'predictions') return 'p.';
    if ($config['table'] === 'menu_items') return 'mi.';
    if ($config['table'] === 'survey_responses') return 'sr.';
    return '';
}

function adminDateColumn(array $config): ?string {
    $column = $config['date_column'] ?? 'created_at';
    return in_array($column, existingColumns($config['table']), true) ? $column : null;
}

function renderField($name, $meta, $value = null) {
    $type = $meta['type'];
    $label = h($meta['label']);
    $required = !empty($meta['required']) ? 'required' : '';
    if (in_array($type, ['date','datetime'], true) && $value) $value = formatJalaliDateTime($value, $type === 'datetime');
    echo '<div class="form-group"><label>' . $label . '</label>';
    if ($type === 'textarea') {
        echo '<textarea class="form-control jalali-input" name="' . h($name) . '" ' . $required . '>' . h($value ?? $meta['default'] ?? '') . '</textarea>';
    } elseif ($type === 'select') {
        echo '<select class="form-control" name="' . h($name) . '">';
        foreach ($meta['options'] as $k => $v) echo '<option value="' . h($k) . '" ' . ((string)$value === (string)$k ? 'selected' : '') . '>' . h($v) . '</option>';
        echo '</select>';
    } elseif (in_array($type, ['category','match','survey_form'], true)) {
        echo '<select class="form-control" name="' . h($name) . '" ' . $required . '><option value="">انتخاب کنید</option>';
        foreach (fetchOptions($type) as $opt) echo '<option value="' . h($opt['id']) . '" ' . ((string)$value === (string)$opt['id'] ? 'selected' : '') . '>' . h($opt['title']) . '</option>';
        echo '</select>';
    } elseif ($type === 'checkbox') {
        echo '<label><input type="checkbox" name="' . h($name) . '" value="1" ' . ($value ? 'checked' : '') . '> فعال</label>';
    } elseif ($type === 'image') {
        if ($value) echo '<p><img src="../' . h($meta['path'] ?? 'uploads/menu') . '/' . h($value) . '" style="max-width:120px;border-radius:8px"></p>';
        echo '<input class="form-control" type="file" name="' . h($name) . '" accept="image/*">';
    } else {
        $htmlType = $type === 'number' ? 'number' : ($type === 'time' ? 'time' : 'text');
        echo '<input class="form-control jalali-input" type="' . $htmlType . '" name="' . h($name) . '" value="' . h($value ?? $meta['default'] ?? '') . '" ' . $required . '>';
    }
    if (in_array($type, ['date','datetime'], true)) echo '<small class="text-muted">فرمت شمسی: 1405/03/10' . ($type === 'datetime' ? ' 18:30' : '') . '</small>';
    echo '</div>';
}

function parseDelimited($file) {
    $rows = [];
    $fh = fopen($file, 'r');
    if (!$fh) return $rows;
    $header = fgetcsv($fh);
    if (!$header) return [];
    while (($row = fgetcsv($fh)) !== false) $rows[] = array_combine($header, array_pad($row, count($header), null));
    fclose($fh);
    return $rows;
}

function parseXlsx($file) {
    if (!class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) return [];
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml);
        foreach ($sx->si as $si) $shared[] = (string)($si->t ?? '');
    }
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$xml) return [];
    $sx = simplexml_load_string($xml);
    $matrix = [];
    foreach ($sx->sheetData->row as $row) {
        $line = [];
        foreach ($row->c as $c) {
            $v = (string)$c->v;
            $line[] = ((string)$c['t'] === 's') ? ($shared[(int)$v] ?? '') : $v;
        }
        $matrix[] = $line;
    }
    $header = array_shift($matrix) ?: [];
    return array_map(fn($r) => array_combine($header, array_pad($r, count($header), null)), $matrix);
}

function readImportRows($file) {
    $validation = validateUploadedFile($_FILES[$file] ?? null, ALLOWED_IMPORT_EXTENSIONS, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'], MAX_FILE_SIZE);
    if (!$validation['valid']) {
        throw new RuntimeException($validation['message']);
    }
    $ext = strtolower(pathinfo($_FILES[$file]['name'] ?? '', PATHINFO_EXTENSION));
    return $ext === 'xlsx' ? parseXlsx($_FILES[$file]['tmp_name']) : parseDelimited($_FILES[$file]['tmp_name']);
}

function applyImport($config, $rows, $mapping = []) {
    $db = Database::getInstance()->getConnection();
    $model = new GenericModel($config['table']);
    $fields = array_keys($config['fields']);
    $result = ['inserted'=>0,'updated'=>0,'errors'=>[]];
    foreach ($rows as $i => $row) {
        $data = [];
        foreach ($fields as $field) {
            $source = $mapping[$field] ?? $field;
            if (array_key_exists($source, $row)) {
                $value = $row[$source];
                $type = $config['fields'][$field]['type'];
                if ($type === 'mobile') $value = normalizeMobile($value);
                if ($type === 'date') $value = parsePersianDate($value, false);
                if ($type === 'datetime') $value = parsePersianDate($value, true);
                if ($type === 'checkbox') $value = in_array(strtolower((string)$value), ['1','yes','true','active','فعال'], true) ? 1 : 0;
                $data[$field] = $value === '' ? null : $value;
            }
        }
        foreach ($config['fields'] as $field => $meta) {
            if (!empty($meta['required']) && empty($data[$field])) $result['errors'][] = 'ردیف ' . ($i + 2) . ': ' . $meta['label'] . ' الزامی است.';
        }
        if (!empty($result['errors']) && str_starts_with(end($result['errors']), 'ردیف ' . ($i + 2))) continue;
        try {
            $unique = $config['unique'] ?? null;
            if ($unique && !empty($data[$unique])) {
                $stmt = $db->prepare('SELECT id FROM ' . $config['table'] . ' WHERE ' . $unique . ' = :v LIMIT 1');
                $stmt->execute(['v' => $data[$unique]]);
                $existing = $stmt->fetch();
                if ($existing) { $model->update($existing['id'], $data); $result['updated']++; continue; }
            }
            $model->create($data); $result['inserted']++;
        } catch (Throwable $e) { $result['errors'][] = 'ردیف ' . ($i + 2) . ': ' . $e->getMessage(); }
    }
    return $result;
}

function outputExport($config, $format, $rows) {
    $filename = $config['table'] . '-' . date('Ymd-His') . '.' . ($format === 'xlsx' ? 'xlsx' : 'csv');
    header('Content-Type: ' . ($format === 'xlsx' ? 'application/vnd.ms-excel; charset=utf-8' : 'text/csv; charset=utf-8'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $columns = $config['columns'];
    if ($format === 'xlsx') {
        echo "<html><meta charset='utf-8'><body><table><tr>";
        foreach ($columns as $c) echo '<th>' . h(labelFor($config, $c)) . '</th>';
        echo '</tr>';
        foreach ($rows as $row) { echo '<tr>'; foreach ($columns as $c) echo '<td>' . h($row[$c] ?? '') . '</td>'; echo '</tr>'; }
        echo '</table></body></html>'; exit;
    }
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $columns);
    foreach ($rows as $row) fputcsv($out, array_map(fn($c) => $row[$c] ?? '', $columns));
    exit;
}

function getRows($config, $forExport = false) {
    $db = Database::getInstance()->getConnection();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = $forExport ? 10000 : 20;
    $offset = ($page - 1) * $perPage;
    $base = $config['join'] ?? ('SELECT * FROM ' . $config['table']);
    $where = ['1=1']; $params = [];
    $prefix = adminSqlPrefix($config);
    $allowedColumns = existingColumns($config['table']);
    if (!empty($_GET['q'])) {
        $ors = [];
        foreach (($config['search'] ?? []) as $col) {
            if (in_array($col, $allowedColumns, true)) {
                $ors[] = $prefix . $col . ' LIKE :q';
            }
        }
        if ($ors) {
            $where[] = '(' . implode(' OR ', $ors) . ')';
            $params['q'] = '%' . $_GET['q'] . '%';
        }
    }
    foreach (($config['filters'] ?? []) as $filter) {
        if (isset($_GET[$filter]) && $_GET[$filter] !== '' && in_array($filter, $allowedColumns, true)) {
            $where[] = $prefix . $filter . ' = :' . $filter;
            $params[$filter] = $_GET[$filter];
        }
    }
    if (!empty($_GET['ids']) && is_array($_GET['ids'])) {
        $ids = array_values(array_filter(array_map('intval', $_GET['ids'])));
        if ($ids) {
            $placeholders = [];
            foreach ($ids as $idx => $id) { $key = 'id_' . $idx; $placeholders[] = ':' . $key; $params[$key] = $id; }
            $where[] = $prefix . 'id IN (' . implode(',', $placeholders) . ')';
        }
    }
    $dateColumn = adminDateColumn($config);
    if ($dateColumn && !empty($_GET['date_from'])) { $where[] = $prefix . $dateColumn . ' >= :date_from'; $params['date_from'] = parsePersianDate($_GET['date_from'], false) . ' 00:00:00'; }
    if ($dateColumn && !empty($_GET['date_to'])) { $where[] = $prefix . $dateColumn . ' <= :date_to'; $params['date_to'] = parsePersianDate($_GET['date_to'], false) . ' 23:59:59'; }
    $sql = $base . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $prefix . 'id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $db->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $countSql = 'SELECT COUNT(*) AS total FROM (' . $base . ' WHERE ' . implode(' AND ', $where) . ') x';
    $count = $db->prepare($countSql); $count->execute($params);
    return ['rows'=>$rows,'page'=>$page,'total'=>(int)($count->fetch()['total'] ?? 0),'perPage'=>$perPage];
}

function renderAdminModule($module) {
    $config = moduleConfig($module);
    if (!$config) { http_response_code(404); echo 'Module not found'; return; }
    $currentAdmin = adminGuard($config['min_role'] ?? 'employee');
    try {
        ensureAdminSchema();
    } catch (Throwable $e) {
        adminRenderSafeError($config['title'], "Schema bootstrap failed for module {$module}: " . $e->getMessage());
        return;
    }
    foreach (adminRequiredTables($config) as $requiredTable) {
        if (!adminTableExists($requiredTable)) {
            adminRenderSafeError($config['title'], "Missing table for module {$module}: {$requiredTable}");
            return;
        }
    }
    $allowedColumns = existingColumns($config['table']);
    $config['fields'] = array_filter(
        $config['fields'],
        static fn($fieldName) => in_array($fieldName, $allowedColumns, true),
        ARRAY_FILTER_USE_KEY
    );
    $config['columns'] = array_values(array_filter(
        $config['columns'],
        static fn($col) => $col === 'match_title' || $col === 'category_title' || $col === 'submitted_at' || in_array($col, $allowedColumns, true)
    ));
    $safeFilters = [];
    foreach (($config['filters'] ?? []) as $filter) {
        if (in_array($filter, $allowedColumns, true)) {
            $safeFilters[] = $filter;
        }
    }
    $config['filters'] = $safeFilters;
    $model = new GenericModel($config['table']);
    $action = $_GET['action'] ?? 'list';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['crud_action'] ?? '') === 'delete') { requireValidCsrf(); $model->delete((int)($_POST['id'] ?? 0)); redirectTo(basename($_SERVER['PHP_SELF']) . '?deleted=1'); }
    if ($action === 'export') {
        try {
            $data = getRows($config, true); outputExport($config, $_GET['format'] ?? 'csv', $data['rows']);
        } catch (Throwable $e) {
            adminRenderSafeError($config['title'], "Export failed for module {$module}: " . $e->getMessage());
            return;
        }
    }
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['crud_action'] ?? '') === 'save') {
        try {
            requireValidCsrf();
            $id = (int)($_POST['id'] ?? 0); $current = $id ? $model->find($id) : [];
            $data = collectData($config, $current ?: []);
            if ($id) {
                $model->update($id, $data);
            } else {
                $id = (int)$model->create($data);
            }
            if ($module === 'matches') {
                recalculatePredictionsForMatch($id);
            }
            redirectTo(basename($_SERVER['PHP_SELF']) . '?saved=1');
        } catch (Throwable $e) {
            $message = 'ذخیره انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
            safeAdminLog("Save failed for module {$module}: " . $e->getMessage());
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['crud_action'] ?? '') === 'import') {
        try {
            requireValidCsrf();
            $rows = readImportRows('import_file');
            $mapping = json_decode($_POST['mapping'] ?? '[]', true) ?: [];
            $report = applyImport($config, $rows, $mapping);
            $message = 'Import: ' . $report['inserted'] . ' ایجاد، ' . $report['updated'] . ' بروزرسانی. ' . implode(' | ', array_slice($report['errors'], 0, 5));
        } catch (Throwable $e) {
            $message = 'Import انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
            safeAdminLog("Import failed for module {$module}: " . $e->getMessage());
        }
    }
    $pageTitle = $config['title']; include __DIR__ . '/../includes/header.php';
    echo '<div class="card"><div class="card-header"><h2>' . h($config['title']) . '</h2><div><a class="btn btn-primary" href="?action=add">افزودن</a> <a class="btn" href="?action=export&format=csv">CSV</a> <a class="btn" href="?action=export&format=xlsx">Excel</a></div></div><div class="card-body">';
    if ($message) echo '<div class="alert alert-info">' . h($message) . '</div>';
    if (in_array($action, ['add','edit'], true)) {
        $row = $action === 'edit' ? $model->find((int)$_GET['id']) : [];
        echo '<form method="post" enctype="multipart/form-data"><input type="hidden" name="crud_action" value="save"><input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . h(generateCSRFToken()) . '"><input type="hidden" name="id" value="' . h($row['id'] ?? '') . '">';
        foreach ($config['fields'] as $name => $meta) renderField($name, $meta, $row[$name] ?? null);
        echo '<button class="btn btn-success" type="submit">ذخیره</button> <a class="btn" href="' . basename($_SERVER['PHP_SELF']) . '">بازگشت</a></form>';
    } else {
        echo '<form class="admin-filter" method="get"><input class="form-control" name="q" placeholder="جستجو" value="' . h($_GET['q'] ?? '') . '"><input class="form-control" name="date_from" placeholder="از تاریخ شمسی" value="' . h($_GET['date_from'] ?? '') . '"><input class="form-control" name="date_to" placeholder="تا تاریخ شمسی" value="' . h($_GET['date_to'] ?? '') . '">';
        foreach (($config['filters'] ?? []) as $filter) {
            $filterLabel = labelFor($config, $filter);
            $filterValue = $_GET[$filter] ?? '';
            if (isset($config['fields'][$filter]) && ($config['fields'][$filter]['type'] ?? '') === 'match') {
                echo '<select class="form-control" name="' . h($filter) . '"><option value="">' . h($filterLabel) . '</option>';
                foreach (fetchOptions('match') as $opt) {
                    echo '<option value="' . h($opt['id']) . '" ' . ((string)$filterValue === (string)$opt['id'] ? 'selected' : '') . '>' . h($opt['title']) . '</option>';
                }
                echo '</select>';
                continue;
            }
            if (isset($config['fields'][$filter]) && ($config['fields'][$filter]['type'] ?? '') === 'select' && !empty($config['fields'][$filter]['options'])) {
                echo '<select class="form-control" name="' . h($filter) . '"><option value="">' . h($filterLabel) . '</option>';
                foreach ($config['fields'][$filter]['options'] as $optionKey => $optionTitle) {
                    echo '<option value="' . h($optionKey) . '" ' . ((string)$filterValue === (string)$optionKey ? 'selected' : '') . '>' . h($optionTitle) . '</option>';
                }
                echo '</select>';
                continue;
            }
            echo '<select class="form-control" name="' . h($filter) . '"><option value="">' . h($filterLabel) . '</option><option value="1" ' . ((string)$filterValue === '1' ? 'selected' : '') . '>بله</option><option value="0" ' . ((string)$filterValue === '0' ? 'selected' : '') . '>خیر</option></select>';
        }
        echo '<button class="btn btn-primary">فیلتر</button></form>';
        echo '<details class="import-box"><summary>Import CSV/XLSX با mapping خودکار/دستی</summary><form method="post" enctype="multipart/form-data"><input type="hidden" name="crud_action" value="import"><input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . h(generateCSRFToken()) . '"><input class="form-control" type="file" name="import_file" accept=".csv,.xlsx" required><textarea class="form-control" name="mapping" placeholder="اختیاری: {&quot;mobile&quot;:&quot;phone&quot;}"></textarea><button class="btn btn-warning">Preview/Import</button><p class="text-muted">ردیف اول فایل به عنوان header استفاده می‌شود؛ ستون‌های هم‌نام خودکار map می‌شوند، JSON بالا override دستی است، duplicate با unique key مثل mobile بروزرسانی می‌شود.</p></form></details>';
        try {
            $data = getRows($config); $rows = $data['rows'];
        } catch (Throwable $e) {
            safeAdminLog("List query failed for module {$module}: " . $e->getMessage());
            $data = ['rows'=>[],'page'=>1,'total'=>0,'perPage'=>20]; $rows = [];
            echo '<div class="alert" style="background:#f8d7da;color:#721c24">داده‌ها در حال حاضر قابل نمایش نیستند. جزئیات خطا در لاگ سیستم ثبت شد.</div>';
        }
        echo '<form method="get"><input type="hidden" name="action" value="export"><div class="mb-3"><button class="btn" name="format" value="csv">خروجی CSV ردیف‌های انتخابی</button> <button class="btn" name="format" value="xlsx">خروجی Excel ردیف‌های انتخابی</button></div><div class="table-responsive"><table class="table"><thead><tr><th><input type="checkbox"></th>';
        foreach ($config['columns'] as $col) echo '<th>' . h(labelFor($config, $col)) . '</th>';
        echo '<th>عملیات</th></tr></thead><tbody>';
        foreach ($rows as $row) { echo '<tr><td><input type="checkbox" name="ids[]" value="' . h($row['id']) . '"></td>'; foreach ($config['columns'] as $col) { $val=$row[$col]??''; if (str_ends_with($col,'_at') || in_array($col,$config['date_fields']??[],true)) $val=formatJalaliDateTime($val, !($col==='match_date'||str_ends_with($col,'date'))); echo '<td>' . h($val) . '</td>'; } echo '<td>'; if ($module === 'crm') echo '<a class="btn btn-sm" href="crm-profile.php?id=' . h($row['id']) . '">پروفایل</a> '; echo '<a class="btn btn-sm btn-primary" href="?action=edit&id=' . h($row['id']) . '">ویرایش</a> <form method="post" style="display:inline" onsubmit="return confirm(\'حذف شود؟\')"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . h(generateCSRFToken()) . '"><input type="hidden" name="id" value="' . h($row['id']) . '"><button class="btn btn-sm btn-danger" type="submit">حذف</button></form></td></tr>'; }
        echo '</tbody></table></div></form>';
        $pages = max(1, (int)ceil($data['total'] / $data['perPage'])); echo '<p>صفحه ' . $data['page'] . ' از ' . $pages . '</p>'; if ($data['page']>1) echo '<a class="btn" href="?page=' . ($data['page']-1) . '">قبلی</a> '; if ($data['page']<$pages) echo '<a class="btn" href="?page=' . ($data['page']+1) . '">بعدی</a>';
    }
    echo '</div></div><script>document.querySelectorAll("tbody tr").forEach((tr,i)=>tr.draggable=true);</script>';
    include __DIR__ . '/../includes/footer.php';
}
