<?php
/**
 * إعدادات API Security
 * 
 * يمكن تخصيص هذه الإعدادات حسب الحاجة
 */

return [
    'cors' => [
        'enabled' => true,
        // قائمة النطاقات المسموحة (استخدم ['*'] للسماح للجميع)
        'allowed_origins' => ['*'], // مثال: ['https://example.com', 'https://app.example.com']
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-API-Key'],
        'max_age' => 3600 // ثانية واحدة
    ],
    
    'api_keys' => [
        // false = API Keys اختياري (يمكن الوصول بدون key)
        // true = API Keys مطلوب (يجب توفير key)
        'enabled' => false,
        'header_name' => 'X-API-Key',
        'param_name' => 'api_key'
        // API Keys الفعلية في ملف api_keys.php منفصل
    ],
    
    'rate_limiting' => [
        'enabled' => true,
        'max_requests' => 100, // عدد الطلبات المسموحة
        'window' => 3600, // النافذة الزمنية (بالثواني) - ساعة واحدة
        'by_api_key' => true // إذا true، كل API key له حد منفصل
    ],
    
    'error_handling' => [
        'hide_details' => false, // true في بيئة الإنتاج
        'log_errors' => true
    ]
];

