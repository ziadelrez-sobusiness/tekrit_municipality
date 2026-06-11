<?php
/**
 * دوال تنسيق العملات
 */

/**
 * جلب إعداد من قاعدة البيانات
 */
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '', $db = null) {
        if (!$db) {
            global $db;
        }
        
        try {
            // البحث في جدول website_settings أولاً
            $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result) {
                return $result['setting_value'];
            }
            
            // إذا لم توجد في website_settings، ابحث في system_settings
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result) {
                return $result['setting_value'];
            }
            
            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

/**
 * الحصول على العملة الافتراضية من قاعدة البيانات
 */
function getDefaultCurrencyFromDB($db = null) {
    if (!$db) {
        global $db;
    }
    
    try {
        // محاولة الحصول على العملة الافتراضية من الإعدادات
        $default_currency_id = getSetting('default_currency_id', null, $db);
        
        if ($default_currency_id) {
            $stmt = $db->prepare("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE id = ? AND is_active = 1");
            $stmt->execute([$default_currency_id]);
            $currency = $stmt->fetch();
            
            if ($currency) {
                return $currency;
            }
        }
        
        // إذا لم تُحدد عملة افتراضية، ابحث عن عملة مميزة كافتراضية
        $stmt = $db->query("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE is_default = 1 AND is_active = 1 LIMIT 1");
        $currency = $stmt->fetch();
        
        if ($currency) {
            return $currency;
        }
        
        // إذا لم توجد عملة مميزة، استخدم الدينار العراقي
        $stmt = $db->query("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE currency_code = 'IQD' AND is_active = 1 LIMIT 1");
        $currency = $stmt->fetch();
        
        if ($currency) {
            return $currency;
        }
        
        // كحل أخير، استخدم أول عملة نشطة
        $stmt = $db->query("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE is_active = 1 ORDER BY id LIMIT 1");
        return $stmt->fetch();
        
    } catch (Exception $e) {
        // في حالة الخطأ، إرجاع بيانات افتراضية
        return [
            'id' => 1,
            'currency_code' => 'IQD', 
            'currency_name' => 'الدينار العراقي',
            'currency_symbol' => 'د.ع'
        ];
    }
}

/**
 * تنسيق المبلغ بالعملة المناسبة
 */
function formatCurrency($amount, $currency_id = null, $db = null) {
    if (!$db) {
        global $db;
    }
    
    if (!$currency_id) {
        // استخدام العملة الافتراضية من قاعدة البيانات
        $default_currency = getDefaultCurrencyFromDB($db);
        if ($default_currency) {
            $currency_id = $default_currency['id'];
        } else {
            // كحل أخير إذا فشل كل شيء
            return number_format($amount) . ' د.ع';
        }
    }
    
    // جلب معلومات العملة
    $stmt = $db->prepare("SELECT currency_code, currency_name, currency_symbol FROM currencies WHERE id = ? AND is_active = 1");
    $stmt->execute([$currency_id]);
    $currency = $stmt->fetch();
    
    if (!$currency) {
        // إذا لم توجد العملة، استخدم العملة الافتراضية
        $default_currency = getDefaultCurrencyFromDB($db);
        if ($default_currency) {
            $symbol = $default_currency['currency_symbol'] ?: $default_currency['currency_name'];
            return number_format($amount) . ' ' . $symbol;
        } else {
            // كحل أخير
            return number_format($amount) . ' د.ع';
        }
    }
    
    $symbol = $currency['currency_symbol'] ?: $currency['currency_name'];
    return number_format($amount) . ' ' . $symbol;
}

/**
 * جلب العملة الافتراضية
 */
function getDefaultCurrency($db = null) {
    if (!$db) {
        global $db;
    }
    
    return getDefaultCurrencyFromDB($db);
}

/**
 * تنسيق المبلغ مع معلومات المشروع
 * يعرض العملة الفعلية للمشروع وليس العملة الافتراضية
 */
function formatProjectCost($project, $db = null) {
    if (!$db) {
        global $db;
    }
    
    $amount = $project['project_cost'] ?? 0;
    $currency_id = $project['currency_id'] ?? null;
    
    // إذا لم تُحدد عملة للمشروع، استخدم الافتراضية
    if (!$currency_id) {
        $currency_id = getSetting('default_currency_id', 1, $db);
    }
    
    // عرض العملة الفعلية للمشروع
    return formatCurrency($amount, $currency_id, $db);
}

/**
 * حساب إجمالي الميزانية مع تحويل العملات
 * يحول جميع العملات إلى الليرة اللبنانية للمقارنة
 */
function calculateTotalBudget($db = null) {
    if (!$db) {
        global $db;
    }
    
    // جلب العملة الافتراضية للعرض (الليرة اللبنانية)
    $display_currency_id = getSetting('default_currency_id', 1, $db);
    
    // جلب جميع أسعار الصرف إلى الليرة اللبنانية
    $currencies = $db->query("SELECT id, exchange_rate_to_iqd FROM currencies")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // جلب جميع المشاريع مع تكلفتها وعملتها
    $projects = $db->query("SELECT project_cost, currency_id FROM development_projects WHERE project_cost > 0")->fetchAll();
    
    $total_in_lbp = 0;
    
    foreach ($projects as $project) {
        $cost = $project['project_cost'];
        $currency_id = $project['currency_id'] ?? $display_currency_id;
        
        // تحويل إلى الليرة اللبنانية للمقارنة
        if ($currency_id != $display_currency_id && isset($currencies[$currency_id])) {
            // تحويل من العملة الحالية إلى الليرة اللبنانية
            // نظراً لأن exchange_rate_to_iqd يمثل معدل التحويل، نحتاج للتحويل بشكل صحيح
            if ($currency_id == 1) {
                // ليرة لبنانية - لا تحويل
                $cost_in_lbp = $cost;
            } else {
                // تحويل من عملة أخرى إلى ليرة لبنانية
                // إذا كان الدولار exchange_rate_to_iqd = 0.000067، فهذا يعني 1 دولار = 1/0.000067 ليرة
                $cost_in_lbp = $cost / $currencies[$currency_id];
            }
            $total_in_lbp += $cost_in_lbp;
        } else {
            // العملة هي ليرة لبنانية أصلاً
            $total_in_lbp += $cost;
        }
    }
    
    // عرض الإجمالي بالليرة اللبنانية
    return formatCurrency($total_in_lbp, $display_currency_id, $db);
}

/**
 * تحديث عملة مشروع معين
 */
function updateProjectCurrency($project_id, $new_currency_id, $db = null) {
    if (!$db) {
        global $db;
    }
    
    $stmt = $db->prepare("UPDATE development_projects SET currency_id = ? WHERE id = ?");
    return $stmt->execute([$new_currency_id, $project_id]);
}
?> 
