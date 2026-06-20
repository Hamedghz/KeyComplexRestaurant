<?php

/**
 * Single lightweight i18n core for public and admin presentation layers.
 * Language selection is session-only; database values are never modified.
 */

function supported_langs(): array {
    return ['fa', 'en'];
}

function current_lang(): string {
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) session_start();
    $lang = session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['lang'] ?? 'fa') : 'fa';
    return in_array($lang, supported_langs(), true) ? $lang : 'fa';
}

function set_lang(string $lang): bool {
    $lang = strtolower(trim($lang));
    if (!in_array($lang, supported_langs(), true)) return false;
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) session_start();
    if (session_status() !== PHP_SESSION_ACTIVE) return false;
    $_SESSION['lang'] = $lang;
    return true;
}

function is_rtl(): bool {
    return current_lang() === 'fa';
}

function humanize_key(string $key): string {
    $value = trim(preg_replace('/[_\-\.]+/', ' ', $key) ?? $key);
    if ($value === '') return '';
    $value = ucwords(strtolower($value));
    return str_ireplace(['Crm','Seo','Qr','Api','Ui','Key','Csv'], ['CRM','SEO','QR','API','UI','KEY','CSV'], $value);
}

// Backward-compatible alias; humanize_key() remains the canonical helper.
function humanizeKey(string $key): string {
    return humanize_key($key);
}

function i18n_catalog(string $lang): array {
    $lang = in_array($lang, supported_langs(), true) ? $lang : 'fa';
    if (isset($GLOBALS['__i18n_catalog_cache'][$lang])) return $GLOBALS['__i18n_catalog_cache'][$lang];

    $root = dirname(__DIR__);
    $source = $root . '/lang/' . $lang . '.php';
    $cache = $root . '/storage/cache/lang_' . $lang . '.json';
    $translations = [];

    if (is_readable($cache) && is_readable($source) && filemtime($cache) >= filemtime($source)) {
        $decoded = json_decode((string)file_get_contents($cache), true);
        if (is_array($decoded)) $translations = $decoded;
    }
    if (!$translations && is_readable($source)) {
        $loaded = require $source;
        if (is_array($loaded)) $translations = $loaded;
        $cacheDir = dirname($cache);
        if ((is_dir($cacheDir) || @mkdir($cacheDir, 0755, true)) && is_writable($cacheDir)) {
            @file_put_contents($cache, json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }

    return $GLOBALS['__i18n_catalog_cache'][$lang] = $translations;
}

function t($key, $params = []): string {
    $key = trim((string)$key);
    $catalog = i18n_catalog(current_lang());
    $lookup = strtolower($key);
    $value = array_key_exists($lookup, $catalog) ? (string)$catalog[$lookup] : humanize_key($key);
    if (!is_array($params)) $params = [];
    foreach ($params as $name => $replacement) {
        $value = str_replace([':' . $name, '{' . $name . '}'], (string)$replacement, $value);
    }
    return $value;
}

function clear_lang_cache(): void {
    $root = dirname(__DIR__);
    foreach (supported_langs() as $lang) {
        $file = $root . '/storage/cache/lang_' . $lang . '.json';
        if (is_file($file)) @unlink($file);
    }
    $GLOBALS['__i18n_catalog_cache'] = [];
}

function get_content($fa, $en): string {
    $fa = trim((string)$fa);
    $en = trim((string)$en);
    return current_lang() === 'en' && $en !== '' ? $en : $fa;
}
