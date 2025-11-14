<?php
/**
 * Cache - نظام التخزين المؤقت
 * 
 * يوفر نظام تخزين مؤقت بسيط وفعال لتحسين الأداء
 * يدعم التخزين في الملفات مع إمكانية التوسع لقاعدة البيانات
 */

class Cache {
    private static $cacheDir = null;
    private static $defaultTTL = 3600; // ساعة واحدة
    private static $enabled = true;
    
    /**
     * تهيئة Cache
     */
    public static function init($cacheDir = null, $defaultTTL = 3600, $enabled = true) {
        if ($cacheDir === null) {
            $cacheDir = __DIR__ . '/../cache';
        }
        
        self::$cacheDir = $cacheDir;
        self::$defaultTTL = $defaultTTL;
        self::$enabled = $enabled;
        
        // إنشاء مجلد الـ cache إذا لم يكن موجوداً
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * الحصول على قيمة من الـ cache
     */
    public static function get($key, $default = null) {
        if (!self::$enabled) {
            return $default;
        }
        
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = @file_get_contents($file);
        if ($data === false) {
            return $default;
        }
        
        $cacheData = @unserialize($data);
        if ($cacheData === false) {
            @unlink($file);
            return $default;
        }
        
        // التحقق من انتهاء الصلاحية
        if (isset($cacheData['expires_at']) && $cacheData['expires_at'] < time()) {
            @unlink($file);
            return $default;
        }
        
        return $cacheData['value'] ?? $default;
    }
    
    /**
     * حفظ قيمة في الـ cache
     */
    public static function set($key, $value, $ttl = null) {
        if (!self::$enabled) {
            return false;
        }
        
        if ($ttl === null) {
            $ttl = self::$defaultTTL;
        }
        
        $cacheData = [
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $ttl
        ];
        
        $file = self::getCacheFile($key);
        $data = serialize($cacheData);
        
        return @file_put_contents($file, $data, LOCK_EX) !== false;
    }
    
    /**
     * حذف قيمة من الـ cache
     */
    public static function delete($key) {
        $file = self::getCacheFile($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }
    
    /**
     * حذف جميع قيم الـ cache
     */
    public static function clear() {
        if (!is_dir(self::$cacheDir)) {
            return true;
        }
        
        $files = glob(self::$cacheDir . '/*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * حذف قيم الـ cache التي تطابق نمط معين
     */
    public static function clearPattern($pattern) {
        if (!is_dir(self::$cacheDir)) {
            return true;
        }
        
        $files = glob(self::$cacheDir . '/' . $pattern . '.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * التحقق من وجود قيمة في الـ cache
     */
    public static function has($key) {
        if (!self::$enabled) {
            return false;
        }
        
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = @file_get_contents($file);
        if ($data === false) {
            return false;
        }
        
        $cacheData = @unserialize($data);
        if ($cacheData === false) {
            return false;
        }
        
        // التحقق من انتهاء الصلاحية
        if (isset($cacheData['expires_at']) && $cacheData['expires_at'] < time()) {
            @unlink($file);
            return false;
        }
        
        return true;
    }
    
    /**
     * الحصول على قيمة أو استدعاء callback إذا لم تكن موجودة
     */
    public static function remember($key, $callback, $ttl = null) {
        if (self::has($key)) {
            return self::get($key);
        }
        
        $value = call_user_func($callback);
        self::set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * زيادة قيمة عددية في الـ cache
     */
    public static function increment($key, $by = 1, $ttl = null) {
        $current = self::get($key, 0);
        $newValue = $current + $by;
        self::set($key, $newValue, $ttl);
        return $newValue;
    }
    
    /**
     * تقليل قيمة عددية في الـ cache
     */
    public static function decrement($key, $by = 1, $ttl = null) {
        $current = self::get($key, 0);
        $newValue = $current - $by;
        self::set($key, $newValue, $ttl);
        return $newValue;
    }
    
    /**
     * الحصول على معلومات الـ cache
     */
    public static function info($key) {
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }
        
        $cacheData = @unserialize($data);
        if ($cacheData === false) {
            return null;
        }
        
        return [
            'key' => $key,
            'created_at' => $cacheData['created_at'] ?? null,
            'expires_at' => $cacheData['expires_at'] ?? null,
            'ttl' => isset($cacheData['expires_at']) ? ($cacheData['expires_at'] - time()) : null,
            'is_expired' => isset($cacheData['expires_at']) && $cacheData['expires_at'] < time(),
            'size' => filesize($file)
        ];
    }
    
    /**
     * تنظيف الـ cache منتهي الصلاحية
     */
    public static function clean() {
        if (!is_dir(self::$cacheDir)) {
            return 0;
        }
        
        $files = glob(self::$cacheDir . '/*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = @file_get_contents($file);
            if ($data === false) {
                continue;
            }
            
            $cacheData = @unserialize($data);
            if ($cacheData === false) {
                @unlink($file);
                $cleaned++;
                continue;
            }
            
            if (isset($cacheData['expires_at']) && $cacheData['expires_at'] < time()) {
                @unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * الحصول على إحصائيات الـ cache
     */
    public static function stats() {
        if (!is_dir(self::$cacheDir)) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'expired_files' => 0,
                'valid_files' => 0
            ];
        }
        
        $files = glob(self::$cacheDir . '/*.cache');
        $totalSize = 0;
        $expiredCount = 0;
        $validCount = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $data = @file_get_contents($file);
            if ($data === false) {
                $expiredCount++;
                continue;
            }
            
            $cacheData = @unserialize($data);
            if ($cacheData === false) {
                $expiredCount++;
                continue;
            }
            
            if (isset($cacheData['expires_at']) && $cacheData['expires_at'] < time()) {
                $expiredCount++;
            } else {
                $validCount++;
            }
        }
        
        return [
            'total_files' => count($files),
            'total_size' => $totalSize,
            'total_size_formatted' => self::formatBytes($totalSize),
            'expired_files' => $expiredCount,
            'valid_files' => $validCount
        ];
    }
    
    /**
     * الحصول على مسار ملف الـ cache
     */
    private static function getCacheFile($key) {
        $safeKey = md5($key);
        return self::$cacheDir . '/' . $safeKey . '.cache';
    }
    
    /**
     * تنسيق الحجم بالبايت
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * تفعيل/تعطيل الـ cache
     */
    public static function enable($enabled = true) {
        self::$enabled = $enabled;
    }
    
    /**
     * التحقق من تفعيل الـ cache
     */
    public static function isEnabled() {
        return self::$enabled;
    }
}

