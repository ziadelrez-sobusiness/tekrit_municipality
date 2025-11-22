<?php
/**
 * SecurityHeaders - إضافة Security Headers للصفحات
 * 
 * يوفر:
 * - Content Security Policy (CSP)
 * - X-Frame-Options
 * - X-Content-Type-Options
 * - Referrer-Policy
 * - Permissions-Policy
 */

class SecurityHeaders {
    private static $cspConfig = null;
    private static $initialized = false;
    
    /**
     * تهيئة Security Headers
     * 
     * @param array $cspConfig إعدادات CSP مخصصة (اختياري)
     */
    public static function init($cspConfig = null) {
        if (self::$initialized || headers_sent()) {
            return;
        }
        
        self::$cspConfig = $cspConfig ?? self::getDefaultCSP();
        self::$initialized = true;
        
        self::setCSP();
        self::setXFrameOptions();
        self::setXContentTypeOptions();
        self::setReferrerPolicy();
        self::setPermissionsPolicy();
    }
    
    /**
     * الحصول على إعدادات CSP الافتراضية
     */
    private static function getDefaultCSP() {
        return [
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'",
                "'unsafe-inline'", // للسماح بـ inline scripts (Tailwind CDN قد يحتاجها)
                "'unsafe-eval'", // مطلوب لـ Alpine.js (يستخدم eval لتقييم expressions)
                'https://cdn.tailwindcss.com',
                'https://unpkg.com',
                'https://cdn.jsdelivr.net',
                'https://www.google.com', // لـ Google reCAPTCHA
                'https://www.gstatic.com' // لـ Google reCAPTCHA
            ],
            'style-src' => [
                "'self'",
                "'unsafe-inline'", // للسماح بـ inline styles
                'https://fonts.googleapis.com',
                'https://cdn.tailwindcss.com',
                'https://unpkg.com' // لـ Leaflet CSS
            ],
            'font-src' => [
                "'self'",
                'https://fonts.gstatic.com',
                'data:'
            ],
            'img-src' => [
                "'self'",
                'data:',
                'https:',
                'http:' // للسماح بالصور من أي مصدر (يمكن تقييده لاحقاً)
            ],
            'connect-src' => [
                "'self'",
                'https://api.telegram.org',
                'https://graph.facebook.com',
                'https://www.google.com', // لـ Google reCAPTCHA verification
                'https://cdn.jsdelivr.net', // لتحميل source maps للـ charts
                'https://unpkg.com', // لـ Leaflet source maps
                'https://*.tile.openstreetmap.org' // لـ OpenStreetMap tiles
            ],
            'frame-src' => [
                "'self'",
                'https://www.google.com' // لـ Google reCAPTCHA iframe
            ],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'upgrade-insecure-requests' => false // true في الإنتاج مع HTTPS
        ];
    }
    
    /**
     * بناء CSP string من الإعدادات
     */
    private static function buildCSPString() {
        $directives = [];
        
        foreach (self::$cspConfig as $directive => $sources) {
            if ($directive === 'upgrade-insecure-requests') {
                if ($sources) {
                    $directives[] = 'upgrade-insecure-requests';
                }
                continue;
            }
            
            if (is_array($sources)) {
                $directives[] = $directive . ' ' . implode(' ', $sources);
            } else {
                $directives[] = $directive . ' ' . $sources;
            }
        }
        
        return implode('; ', $directives);
    }
    
    /**
     * إضافة Content Security Policy
     */
    private static function setCSP() {
        $cspString = self::buildCSPString();
        header("Content-Security-Policy: $cspString");
        
        // إضافة report-uri (اختياري - للتقرير عن انتهاكات CSP)
        // header("Content-Security-Policy-Report-Only: $cspString");
    }
    
    /**
     * إضافة X-Frame-Options
     */
    private static function setXFrameOptions() {
        header('X-Frame-Options: SAMEORIGIN'); // أو DENY لمنع أي framing
    }
    
    /**
     * إضافة X-Content-Type-Options
     */
    private static function setXContentTypeOptions() {
        header('X-Content-Type-Options: nosniff');
    }
    
    /**
     * إضافة Referrer-Policy
     */
    private static function setReferrerPolicy() {
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * إضافة Permissions-Policy
     */
    private static function setPermissionsPolicy() {
        // تنسيق صحيح لـ Permissions-Policy حسب المواصفات
        // الصيغة: feature=(origin) أو feature=() للرفض
        $permissions = [
            'geolocation' => 'self',
            'camera' => '',
            'microphone' => '',
            'payment' => ''
        ];
        
        $policy = [];
        foreach ($permissions as $feature => $origin) {
            if (empty($origin)) {
                $policy[] = $feature . '=()';
            } else {
                $policy[] = $feature . '=(' . $origin . ')';
            }
        }
        
        header('Permissions-Policy: ' . implode(', ', $policy));
    }
    
    /**
     * تعطيل CSP مؤقتاً (للتطوير فقط)
     */
    public static function disable() {
        self::$initialized = false;
    }
    
    /**
     * تحديث إعدادات CSP
     */
    public static function updateCSP($cspConfig) {
        self::$cspConfig = array_merge(self::getDefaultCSP(), $cspConfig);
        if (self::$initialized && !headers_sent()) {
            self::setCSP();
        }
    }
}

