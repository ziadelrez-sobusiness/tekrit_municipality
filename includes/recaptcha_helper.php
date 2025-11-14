<?php
/**
 * reCAPTCHA Helper Class
 * يوفر وظائف للتحقق من reCAPTCHA في النماذج
 */

class RecaptchaHelper {
    
    // مفاتيح reCAPTCHA v3 الحقيقية من Google
    private static $site_key = '6LdTlGcrAAAAAI6EscR5MLEXcYAQrOT2LCBAzax7'; // مفتاح الموقع v3
    private static $secret_key = '6LdTlGcrAAAAAGL9zw5yE6mnq1dFta5Z9n0y6RwC'; // المفتاح السري v3
    
    /**
     * الحصول على مفتاح الموقع
     */
    public static function getSiteKey() {
        return self::$site_key;
    }
    
    /**
     * إدراج كود JavaScript لـ reCAPTCHA v3 في رأس الصفحة
     */
    public static function renderScript() {
        $siteKey = self::getSiteKey();
        return "<script src=\"https://www.google.com/recaptcha/api.js?render={$siteKey}\"></script>";
    }
    
    /**
     * إدراج عنصر reCAPTCHA v3 في النموذج (مخفي)
     */
    public static function renderWidget($action = 'submit', $class = '') {
        $siteKey = self::getSiteKey();
        $uniqueId = 'recaptcha-token-' . $action . '-' . uniqid();
        return "
        <input type=\"hidden\" id=\"{$uniqueId}\" name=\"recaptcha-token\" />
        <script>
        grecaptcha.ready(function() {
            grecaptcha.execute('{$siteKey}', {action: '{$action}'}).then(function(token) {
                document.getElementById('{$uniqueId}').value = token;
            });
        });
        </script>";
    }
    
    /**
     * التحقق من صحة reCAPTCHA
     * @param string $response قيمة g-recaptcha-response من النموذج
     * @param string $remote_ip عنوان IP للمستخدم (اختياري)
     * @return array نتيجة التحقق مع معلومات إضافية
     */
    public static function verify($response, $remote_ip = null, $min_score = 0.5) {
        // إذا لم يتم إرسال response
        if (empty($response)) {
            return [
                'success' => false,
                'error' => 'فشل في التحقق الأمني، يرجى المحاولة مرة أخرى'
            ];
        }
        
        // إعداد البيانات للإرسال
        $data = [
            'secret' => self::$secret_key,
            'response' => $response
        ];
        
        if ($remote_ip) {
            $data['remoteip'] = $remote_ip;
        }
        
        // إرسال طلب التحقق لـ Google
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        
        if ($result === false) {
            return [
                'success' => false,
                'error' => 'فشل في الاتصال بخدمة التحقق'
            ];
        }
        
        $response_data = json_decode($result, true);
        
        if ($response_data['success']) {
            // في reCAPTCHA v3، نتحقق من النقاط (0.0 إلى 1.0)
            $score = $response_data['score'] ?? 0;
            if ($score >= $min_score) {
                return [
                    'success' => true,
                    'score' => $score,
                    'action' => $response_data['action'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'تم اكتشاف نشاط مشبوه، يرجى المحاولة مرة أخرى',
                    'score' => $score
                ];
            }
        } else {
            $error_message = 'فشل التحقق من أنك لست روبوت';
            
            // رسائل خطأ مخصصة بناءً على نوع الخطأ
            if (isset($response_data['error-codes'])) {
                $error_codes = $response_data['error-codes'];
                if (in_array('timeout-or-duplicate', $error_codes)) {
                    $error_message = 'انتهت صلاحية التحقق، يرجى المحاولة مرة أخرى';
                } elseif (in_array('invalid-input-response', $error_codes)) {
                    $error_message = 'فشل التحقق، يرجى المحاولة مرة أخرى';
                }
            }
            
            return [
                'success' => false,
                'error' => $error_message,
                'error_codes' => $response_data['error-codes'] ?? []
            ];
        }
    }
    
    /**
     * التحقق السريع من reCAPTCHA مع إرجاع true/false فقط
     */
    public static function isValid($response, $remote_ip = null) {
        $result = self::verify($response, $remote_ip);
        return $result['success'];
    }
    
    /**
     * إعداد المفاتيح المخصصة (للاستخدام في الإنتاج)
     */
    public static function setKeys($site_key, $secret_key) {
        self::$site_key = $site_key;
        self::$secret_key = $secret_key;
    }
    
    /**
     * CSS مخصص لتحسين مظهر reCAPTCHA
     */
    public static function renderCSS() {
        return '
        <style>
        .recaptcha-container {
            margin: 15px 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .g-recaptcha {
            transform: scale(0.9);
            transform-origin: 0 0;
        }
        @media (max-width: 768px) {
            .g-recaptcha {
                transform: scale(0.8);
            }
        }
        .recaptcha-required {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        </style>';
    }
}

/**
 * دالة مساعدة سريعة للتحقق من reCAPTCHA v3 في النماذج
 */
function verify_recaptcha($post_data, $remote_ip = null, $min_score = 0.5) {
    // في v3، التوكن يأتي من حقل مخفي
    $response = $post_data['recaptcha-token'] ?? '';
    return RecaptchaHelper::verify($response, $remote_ip, $min_score);
}
?> 