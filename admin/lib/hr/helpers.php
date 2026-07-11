<?php

if (!function_exists('hrModuleKey')) {
    function hrModuleKey(string $moduleKey): string {
        $moduleKey = strtolower(trim($moduleKey));
        $moduleKey = preg_replace('/[^a-z0-9_]/', '_', $moduleKey) ?? '';
        return trim($moduleKey, '_') ?: 'hr';
    }
}

if (!function_exists('hrStatus')) {
    function hrStatus(?string $status, string $default = 'active'): string {
        $status = strtolower(trim((string)$status));
        return preg_match('/^[a-z0-9_]+$/', $status) ? $status : $default;
    }
}

if (!function_exists('hrFoundationJsonEncode')) {
    function hrFoundationJsonEncode($value): string {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? 'null' : $encoded;
    }
}

if (!function_exists('hrFoundationJsonDecode')) {
    function hrFoundationJsonDecode($value, $default = []) {
        if (is_array($value)) return $value;
        if ($value === null || trim((string)$value) === '') return $default;
        $decoded = json_decode((string)$value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }
}

if (!function_exists('hrHashRequestValue')) {
    function hrHashRequestValue(?string $value): ?string {
        $value = trim((string)$value);
        if ($value === '') return null;
        return hash('sha256', $value);
    }
}

if (!function_exists('hrToday')) {
    function hrToday(): string {
        return date('Y-m-d');
    }
}
