<?php
/**
 * تهيئة أنظمة المرحلة 1
 * 
 * هذا الملف يدمج ErrorHandler, Logger, Validator, Cache في النظام
 * يمكن استدعاؤه في بداية أي ملف يحتاج هذه الأنظمة
 */

// تجنب التحميل المكرر
if (defined('PHASE1_INITIALIZED')) {
    return;
}

define('PHASE1_INITIALIZED', true);

// تحميل الملفات المطلوبة
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Cache.php';

// تحديد بيئة الإنتاج (يمكن تغييرها حسب الحاجة)
$isProduction = false; // TODO: تغيير هذا حسب بيئة الإنتاج
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
    $isProduction = true;
}

// تهيئة ErrorHandler
ErrorHandler::init($isProduction);

// تهيئة Cache
Cache::init();

// إنشاء logger عام للاستخدام السريع
$GLOBALS['logger'] = new Logger();

/**
 * دالة مساعدة للـ Logger
 */
if (!function_exists('log_info')) {
    function log_info($message, $context = []) {
        global $logger;
        if (isset($logger)) {
            $logger->info($message, $context);
        }
    }
}

if (!function_exists('log_error')) {
    function log_error($message, $context = []) {
        global $logger;
        if (isset($logger)) {
            $logger->error($message, $context);
        }
    }
}

if (!function_exists('log_warning')) {
    function log_warning($message, $context = []) {
        global $logger;
        if (isset($logger)) {
            $logger->warning($message, $context);
        }
    }
}

if (!function_exists('log_debug')) {
    function log_debug($message, $context = []) {
        global $logger;
        if (isset($logger)) {
            $logger->debug($message, $context);
        }
    }
}

/**
 * دالة مساعدة للـ Validator
 */
if (!function_exists('validate')) {
    function validate($data, $rules) {
        $validator = new Validator($data);
        foreach ($rules as $field => $fieldRules) {
            $validator->rule($field, $fieldRules);
        }
        return $validator;
    }
}

/**
 * دالة مساعدة للـ Cache
 */
if (!function_exists('cache_get')) {
    function cache_get($key, $default = null) {
        return Cache::get($key, $default);
    }
}

if (!function_exists('cache_set')) {
    function cache_set($key, $value, $ttl = null) {
        return Cache::set($key, $value, $ttl);
    }
}

if (!function_exists('cache_remember')) {
    function cache_remember($key, $callback, $ttl = null) {
        return Cache::remember($key, $callback, $ttl);
    }
}

if (!function_exists('cache_delete')) {
    function cache_delete($key) {
        return Cache::delete($key);
    }
}

if (!function_exists('cache_clear')) {
    function cache_clear() {
        return Cache::clear();
    }
}

