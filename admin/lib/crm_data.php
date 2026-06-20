<?php

/**
 * CRM-specific normalization, filtering and Excel-compatible import/export.
 * Kept separate from the generic admin CRUD helpers so other modules retain
 * their historical behaviour.
 */

function crmFieldDefinitions(): array {
    return [
        'id' => 'شناسه',
        'user_id' => 'شناسه کاربر',
        'full_name' => 'نام',
        'mobile' => 'موبایل',
        'email' => 'ایمیل',
        'birth_date' => 'تاریخ تولد',
        'first_purchase_date' => 'تاریخ اولین خرید',
        'total_orders' => 'تعداد سفارش‌ها',
        'total_purchase_volume' => 'حجم کل خرید',
        'reminder_date' => 'تاریخ یادآوری',
        'acquisition_source' => 'منبع جذب',
        'notes' => 'یادداشت‌ها',
        'surveys_completed_count' => 'تعداد نظرسنجی‌های تکمیل‌شده',
        'last_visit_date' => 'تاریخ آخرین مراجعه',
        'tags' => 'برچسب‌ها',
        'attended_match_event' => 'حضور در رویداد مسابقه',
        'customer_status' => 'وضعیت مشتری',
        'points_balance' => 'موجودی امتیاز',
        'rewards_notes' => 'یادداشت پاداش‌ها',
        'follow_up_notes' => 'یادداشت پیگیری',
        'created_at' => 'تاریخ ایجاد',
        'updated_at' => 'تاریخ ویرایش',
    ];
}

function crmUtf8($value): string {
    if ($value === null) return '';
    $value = (string)$value;
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $encoding = mb_detect_encoding($value, ['UTF-8', 'Windows-1256', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding) $value = mb_convert_encoding($value, 'UTF-8', $encoding);
    } elseif (!preg_match('//u', $value) && function_exists('iconv')) {
        $converted = @iconv('Windows-1256', 'UTF-8//IGNORE', $value);
        if ($converted !== false) $value = $converted;
    }
    return trim($value);
}

function crmNormalizeMobile($value): ?string {
    $mobile = crmUtf8($value);
    if ($mobile === '') return null;
    $mobile = strtr($mobile, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    $mobile = preg_replace('/[^0-9+]/', '', $mobile) ?? $mobile;
    if (str_starts_with($mobile, '0098')) $mobile = '0' . substr($mobile, 4);
    elseif (str_starts_with($mobile, '+98')) $mobile = '0' . substr($mobile, 3);
    elseif (str_starts_with($mobile, '98') && strlen($mobile) === 12) $mobile = '0' . substr($mobile, 2);
    elseif (str_starts_with($mobile, '9') && strlen($mobile) === 10) $mobile = '0' . $mobile;
    return $mobile !== '' ? $mobile : null;
}

function crmNormalizeTags($value): string {
    if (is_array($value)) $items = $value;
    else {
        $raw = crmUtf8($value);
        if ($raw === '') return '[]';
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $items = $decoded;
        else {
            $raw = trim($raw, "[](){} \t\n\r\0\x0B");
            $items = preg_split('/\s*[,،;|]\s*/u', $raw) ?: [];
        }
    }
    $clean = [];
    foreach ($items as $item) {
        $item = trim(crmUtf8($item), " \t\n\r\0\x0B\"'");
        if ($item !== '' && !in_array($item, $clean, true)) $clean[] = $item;
    }
    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function crmTagsForExport($value): string {
    $decoded = is_array($value) ? $value : json_decode((string)$value, true);
    return is_array($decoded) ? implode(', ', array_map('strval', $decoded)) : crmUtf8($value);
}

function crmNormalizeDate($value, bool $dateTime = false): ?string {
    $value = crmUtf8($value);
    if ($value === '') return null;
    if (is_numeric($value)) {
        $serial = (float)$value;
        if ($serial > 0 && $serial < 100000) {
            $seconds = (int)round(($serial - 25569) * 86400);
            return gmdate($dateTime ? 'Y-m-d H:i:s' : 'Y-m-d', $seconds);
        }
    }
    $value = str_replace('/', '-', $value);
    $timestamp = strtotime($value);
    if ($timestamp === false) return null;
    return date($dateTime ? 'Y-m-d H:i:s' : 'Y-m-d', $timestamp);
}

function crmCustomerStatuses(bool $activeOnly = true): array {
    static $cache = [];
    $key = $activeOnly ? 'active' : 'all';
    if (isset($cache[$key])) return $cache[$key];
    $sql = 'SELECT id, title_fa, title_en, color, sort_order, is_active FROM crm_customer_statuses';
    if ($activeOnly) $sql .= ' WHERE is_active = 1';
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    try {
        $rows = adminDb()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        safeAdminLog('CRM status lookup failed: ' . $e->getMessage());
        return [];
    }
    if (!$rows) return [];
    return $cache[$key] = $rows;
}

function crmCustomerStatusOptions(bool $activeOnly = true): array {
    $options = [];
    foreach (crmCustomerStatuses($activeOnly) as $status) {
        $options[(string)$status['title_en']] = (string)$status['title_fa'];
    }
    return $options;
}

function crmFilterSql(array $input, array &$params): string {
    $where = ['1=1'];
    $q = trim(crmUtf8($input['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(full_name LIKE :q OR mobile LIKE :q OR email LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    foreach (['customer_status', 'acquisition_source'] as $field) {
        if (isset($input[$field]) && $input[$field] !== '') {
            $where[] = '`' . $field . '` = :' . $field;
            $params[$field] = crmUtf8($input[$field]);
        }
    }
    $filterDate = static function ($value): ?string {
        $value = crmUtf8($value);
        if ($value === '') return null;
        if (preg_match('/^(13|14)\d{2}[\/-]/', $value) && function_exists('parsePersianDate')) {
            $parsed = parsePersianDate($value, false);
            return is_string($parsed) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $parsed) ? $parsed : null;
        }
        return crmNormalizeDate($value);
    };
    $from = $filterDate($input['date_from'] ?? '');
    $to = $filterDate($input['date_to'] ?? '');
    if ($from) { $where[] = 'last_visit_date >= :date_from'; $params['date_from'] = $from; }
    if ($to) { $where[] = 'last_visit_date <= :date_to'; $params['date_to'] = $to; }
    return implode(' AND ', $where);
}

function crmExportCsv(array $input): void {
    $fields = crmFieldDefinitions();
    $params = [];
    $stmt = adminDb()->prepare('SELECT `' . implode('`,`', array_keys($fields)) . '` FROM crm_customers WHERE ' . crmFilterSql($input, $params) . ' ORDER BY id DESC');
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="crm-customers-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_values($fields));
    if (!empty($input['debug_headers'])) fputcsv($out, array_keys($fields));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['tags'] = crmTagsForExport($row['tags'] ?? '');
        fputcsv($out, array_map(static fn($key) => $row[$key] ?? '', array_keys($fields)));
    }
    fclose($out);
    exit;
}

function crmHeaderMap(): array {
    $map = [];
    foreach (crmFieldDefinitions() as $key => $fa) {
        $map[strtolower($key)] = $key;
        $map[crmUtf8($fa)] = $key;
    }
    $aliases = [
        'نام کامل'=>'full_name', 'نام و نام خانوادگی'=>'full_name', 'phone'=>'mobile', 'شماره موبایل'=>'mobile',
        'status'=>'customer_status', 'وضعیت'=>'customer_status', 'source'=>'acquisition_source',
        'تگ‌ها'=>'tags', 'تگ ها'=>'tags', 'last visit'=>'last_visit_date', 'total purchase'=>'total_purchase_volume',
    ];
    foreach ($aliases as $label => $key) $map[strtolower(crmUtf8($label))] = $key;
    return $map;
}

function crmReadCsv(string $path): array {
    $content = file_get_contents($path);
    if ($content === false) throw new RuntimeException('فایل CSV قابل خواندن نیست.');
    if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF") || substr_count(substr($content, 0, 200), "\0") > 10) {
        $source = str_starts_with($content, "\xFE\xFF") ? 'UTF-16BE' : 'UTF-16LE';
        $converted = function_exists('mb_convert_encoding') ? mb_convert_encoding($content, 'UTF-8', $source) : (function_exists('iconv') ? @iconv($source, 'UTF-8//IGNORE', $content) : false);
        if ($converted === false) throw new RuntimeException('CSV با کدگذاری UTF-16 قابل تبدیل به UTF-8 نیست.');
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $converted) ?? $converted;
    }
    $sample = substr($content, 0, 4096);
    $counts = [','=>substr_count($sample, ','), ';'=>substr_count($sample, ';'), "\t"=>substr_count($sample, "\t")];
    arsort($counts); $delimiter = (string)array_key_first($counts);
    if (preg_match('/^sep=([,;\t])\r?\n/i', $content, $separator)) {
        $delimiter = $separator[1];
        $content = substr($content, strlen($separator[0]));
    }
    $handle = fopen('php://temp', 'w+b');
    fwrite($handle, $content); rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = array_map('crmUtf8', $row);
    fclose($handle);
    return $rows;
}

function crmXmlCellValue(SimpleXMLElement $cell, array $shared): string {
    $type = (string)$cell['t'];
    if ($type === 'inlineStr') return crmUtf8((string)$cell->is->t);
    $value = (string)$cell->v;
    if ($type === 's') return crmUtf8($shared[(int)$value] ?? '');
    if ($type === 'b') return $value === '1' ? '1' : '0';
    return crmUtf8($value);
}

function crmReadXlsx(string $path): array {
    if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
        throw new RuntimeException('برای خواندن XLSX افزونه‌های ZipArchive و SimpleXML باید روی PHP فعال باشند.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('فایل XLSX معتبر نیست.');
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml) foreach ($xml->si as $item) {
            $text = (string)$item->t;
            if ($text === '') foreach ($item->r as $run) $text .= (string)$run->t;
            $shared[] = crmUtf8($text);
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) { $zip->close(); throw new RuntimeException('اولین شیت XLSX پیدا نشد.'); }
    $sheet = simplexml_load_string($sheetXml);
    $rows = [];
    if ($sheet) foreach ($sheet->sheetData->row as $xmlRow) {
        $row = [];
        foreach ($xmlRow->c as $cell) {
            preg_match('/^[A-Z]+/', (string)$cell['r'], $match);
            $letters = $match[0] ?? 'A';
            $index = 0;
            for ($i = 0; $i < strlen($letters); $i++) $index = $index * 26 + ord($letters[$i]) - 64;
            $row[$index - 1] = crmXmlCellValue($cell, $shared);
        }
        if ($row) { ksort($row); $max = max(array_keys($row)); $rows[] = array_replace(array_fill(0, $max + 1, ''), $row); }
    }
    $zip->close();
    return $rows;
}

function crmNormalizeImportRow(array $row): array {
    $dates = ['birth_date','first_purchase_date','reminder_date','last_visit_date'];
    foreach ($dates as $field) if (array_key_exists($field, $row)) {
        $raw = $row[$field]; $row[$field] = crmNormalizeDate($raw);
        if (crmUtf8($raw) !== '' && $row[$field] === null) throw new RuntimeException('تاریخ نامعتبر در ستون ' . $field);
    }
    foreach (['created_at','updated_at'] as $field) if (array_key_exists($field, $row)) {
        $raw = $row[$field]; $row[$field] = crmNormalizeDate($raw, true);
        if (crmUtf8($raw) !== '' && $row[$field] === null) unset($row[$field]);
    }
    if (array_key_exists('mobile', $row)) $row['mobile'] = crmNormalizeMobile($row['mobile']);
    if (array_key_exists('email', $row)) $row['email'] = strtolower(crmUtf8($row['email'])) ?: null;
    if (array_key_exists('tags', $row)) $row['tags'] = crmNormalizeTags($row['tags']);
    foreach (['total_orders','points_balance','surveys_completed_count','user_id'] as $field) if (array_key_exists($field, $row)) $row[$field] = (int)preg_replace('/[^0-9\-]/', '', crmUtf8($row[$field]));
    if (array_key_exists('total_purchase_volume', $row)) {
        $number = str_replace([',',' '], '', crmUtf8($row['total_purchase_volume']));
        $row['total_purchase_volume'] = is_numeric($number) ? round((float)$number, 2) : 0;
    }
    if (array_key_exists('attended_match_event', $row)) $row['attended_match_event'] = in_array(strtolower(crmUtf8($row['attended_match_event'])), ['1','yes','true','بله'], true) ? 1 : 0;
    foreach ($row as $key => $value) if (!in_array($key, ['mobile','email','tags'], true) && is_string($value)) $row[$key] = crmUtf8($value) ?: null;
    $row['full_name'] = crmUtf8($row['full_name'] ?? '');
    $row['mobile'] = crmNormalizeMobile($row['mobile'] ?? null);
    if ($row['full_name'] === '' && !$row['mobile']) throw new RuntimeException('حداقل نام یا موبایل الزامی است.');
    return $row;
}

function crmUpsertChunk(array $items, array $columns, array &$report): void {
    if (!$items) return;
    $db = adminDb();
    $mobiles = array_values(array_unique(array_filter(array_column(array_column($items, 'data'), 'mobile'))));
    $existing = [];
    if ($mobiles) {
        $marks = implode(',', array_fill(0, count($mobiles), '?'));
        $stmt = $db->prepare('SELECT mobile FROM crm_customers WHERE mobile IN (' . $marks . ')');
        $stmt->execute($mobiles);
        $existing = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    }
    $quoted = array_map(static fn($c) => '`' . $c . '`', $columns);
    $one = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
    $updates = array_values(array_filter($columns, static fn($c) => !in_array($c, ['mobile','created_at'], true)));
    $updateSql = implode(',', array_map(static fn($c) => '`' . $c . '`=VALUES(`' . $c . '`)', $updates));
    $sql = 'INSERT INTO crm_customers (' . implode(',', $quoted) . ') VALUES ' . implode(',', array_fill(0, count($items), $one));
    if ($updateSql !== '') $sql .= ' ON DUPLICATE KEY UPDATE ' . $updateSql;
    $params = [];
    foreach ($items as $item) foreach ($columns as $column) $params[] = $item['data'][$column] ?? null;
    $db->beginTransaction();
    try { $db->prepare($sql)->execute($params); $db->commit(); }
    catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    foreach ($items as $item) {
        $mobile = $item['data']['mobile'] ?? null;
        if ($mobile && isset($existing[$mobile])) $report['updated']++;
        else { $report['inserted']++; if ($mobile) $existing[$mobile] = true; }
    }
}

function crmProcessImportChunk(array $items, array $columns, array &$report): void {
    if (!$items) return;
    try {
        crmUpsertChunk($items, $columns, $report);
    } catch (Throwable $batchError) {
        safeAdminLog('CRM import batch fallback: ' . $batchError->getMessage());
        foreach ($items as $item) {
            try {
                crmUpsertChunk([$item], $columns, $report);
            } catch (Throwable $rowError) {
                $report['skipped']++;
                $report['errors'][(string)$item['row']] = $rowError->getMessage();
            }
        }
    }
}

function crmImportFile(array $file): array {
    $report = ['inserted'=>0, 'updated'=>0, 'skipped'=>0, 'errors'=>[]];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) throw new RuntimeException('فایل ورودی دریافت نشد.');
    if (($file['size'] ?? 0) > 20 * 1024 * 1024) throw new RuntimeException('حداکثر حجم فایل ۲۰ مگابایت است.');
    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv','xlsx'], true)) throw new RuntimeException('فقط فایل CSV یا XLSX پذیرفته می‌شود.');
    $rows = $extension === 'xlsx' ? crmReadXlsx($file['tmp_name']) : crmReadCsv($file['tmp_name']);
    if (!$rows) throw new RuntimeException('فایل خالی است.');
    $headerMap = crmHeaderMap();
    $headers = array_shift($rows);
    $indexes = []; $unknown = [];
    foreach ($headers as $index => $header) {
        $normalized = strtolower(crmUtf8($header));
        if (isset($headerMap[$normalized]) && $headerMap[$normalized] !== 'id') $indexes[$index] = $headerMap[$normalized];
        elseif ($normalized !== '' && $normalized !== 'id' && $normalized !== 'شناسه') $unknown[] = crmUtf8($header);
    }
    if ($unknown) safeAdminLog('CRM import ignored unknown columns: ' . implode(', ', $unknown));
    if (!$indexes) throw new RuntimeException('هیچ ستون شناخته‌شده‌ای در فایل پیدا نشد.');
    $first = $rows[0] ?? [];
    $internalKeys = array_keys(crmFieldDefinitions());
    $recognizedSecond = 0;
    foreach ($first as $cell) if (in_array(strtolower(crmUtf8($cell)), $internalKeys, true)) $recognizedSecond++;
    if ($recognizedSecond >= 2) array_shift($rows);
    $columns = array_values(array_unique(array_merge(array_values($indexes), ['full_name','mobile'])));
    $chunk = [];
    foreach ($rows as $offset => $cells) {
        $excelRow = $offset + 2 + ($recognizedSecond >= 2 ? 1 : 0);
        if (!array_filter($cells, static fn($v) => crmUtf8($v) !== '')) continue;
        try {
            $data = [];
            foreach ($indexes as $index => $field) $data[$field] = $cells[$index] ?? '';
            $data = crmNormalizeImportRow($data);
            foreach ($columns as $column) if (!array_key_exists($column, $data)) $data[$column] = $column === 'full_name' ? '' : null;
            $chunk[] = ['row'=>$excelRow, 'data'=>$data];
            if (count($chunk) === 500) { $ready = $chunk; $chunk = []; crmProcessImportChunk($ready, $columns, $report); }
        } catch (Throwable $e) {
            $report['skipped']++;
            $report['errors'][(string)$excelRow] = $e->getMessage();
        }
    }
    crmProcessImportChunk($chunk, $columns, $report);
    return $report;
}
