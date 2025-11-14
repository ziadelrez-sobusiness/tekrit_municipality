<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings_helper.php';

class CurrencyHelper {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * الحصول على جميع العملات النشطة
     */
    public function getActiveCurrencies() {
        $stmt = $this->db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على العملة الافتراضية
     */
    public function getDefaultCurrency() {
        // الحصول على معرف العملة الافتراضية من الإعدادات
        $default_currency_id = getDefaultCurrencyId();
        $stmt = $this->db->prepare("SELECT * FROM currencies WHERE id = ? LIMIT 1");
        $stmt->execute([$default_currency_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // إذا لم توجد العملة الافتراضية، استخدم الدينار العراقي
        if (!$result) {
            $stmt = $this->db->query("SELECT * FROM currencies WHERE currency_code = 'IQD' LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $result;
    }
    
    /**
     * الحصول على معلومات عملة معينة
     */
    public function getCurrency($currency_id) {
        $stmt = $this->db->prepare("SELECT * FROM currencies WHERE id = ? AND is_active = 1");
        $stmt->execute([$currency_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * تنسيق المبلغ مع رمز العملة
     */
    public function formatAmount($amount, $currency_id = null, $show_symbol = true) {
        if ($currency_id === null) {
            $currency = $this->getDefaultCurrency();
        } else {
            $currency = $this->getCurrency($currency_id);
        }
        
        if (!$currency) {
            return number_format($amount, 2);
        }
        
        $formatted_amount = number_format($amount, 2);
        
        if ($show_symbol) {
            return $formatted_amount . ' ' . $currency['currency_symbol'];
        }
        
        return $formatted_amount;
    }
    
    /**
     * تحويل مبلغ من عملة إلى أخرى
     */
    public function convertCurrency($amount, $from_currency_id, $to_currency_id) {
        $from_currency = $this->getCurrency($from_currency_id);
        $to_currency = $this->getCurrency($to_currency_id);
        
        if (!$from_currency || !$to_currency) {
            return $amount;
        }
        
        // إذا كان التحويل للعملة نفسها
        if ($from_currency_id == $to_currency_id) {
            return $amount;
        }
        
        // تحويل إلى الدينار العراقي أولاً
        $amount_in_iqd = $amount * ($from_currency['exchange_rate_to_iqd'] ?? 1);
        
        // ثم تحويل إلى العملة المطلوبة
        return $amount_in_iqd / ($to_currency['exchange_rate_to_iqd'] ?? 1);
    }
    
    /**
     * إنشاء قائمة منسدلة للعملات
     */
    public function createCurrencySelect($name, $selected_id = null, $class = '', $required = false) {
        $currencies = $this->getActiveCurrencies();
        $default_currency = $this->getDefaultCurrency();
        
        if ($selected_id === null && $default_currency) {
            $selected_id = $default_currency['id'];
        }
        
        $required_attr = $required ? 'required' : '';
        $html = "<select name='{$name}' class='{$class}' {$required_attr}>";
        
        foreach ($currencies as $currency) {
            $selected = ($currency['id'] == $selected_id) ? 'selected' : '';
            $html .= "<option value='{$currency['id']}' {$selected}>";
            $html .= "{$currency['currency_name']} ({$currency['currency_symbol']})";
            $html .= "</option>";
        }
        
        $html .= "</select>";
        return $html;
    }
    
    /**
     * إنشاء عنصر عرض العملة مع المبلغ
     */
    public function createAmountDisplay($amount, $currency_id = null) {
        if ($currency_id === null) {
            $currency = $this->getDefaultCurrency();
        } else {
            $currency = $this->getCurrency($currency_id);
        }
        
        if (!$currency) {
            return number_format($amount, 2);
        }
        
        return "
            <div class='amount-display'>
                <span class='amount'>" . number_format($amount, 2) . "</span>
                <span class='currency'>{$currency['currency_symbol']}</span>
                <small class='currency-name'>({$currency['currency_name']})</small>
            </div>
        ";
    }
    
    /**
     * الحصول على إجمالي المبلغ بعملة موحدة
     */
    public function getTotalInCurrency($amounts_with_currencies, $target_currency_id = null) {
        if ($target_currency_id === null) {
            $target_currency = $this->getDefaultCurrency();
            $target_currency_id = $target_currency['id'];
        }
        
        $total = 0;
        foreach ($amounts_with_currencies as $item) {
            $converted_amount = $this->convertCurrency(
                $item['amount'], 
                $item['currency_id'], 
                $target_currency_id
            );
            $total += $converted_amount;
        }
        
        return $total;
    }
    
    /**
     * تحديث أسعار الصرف
     */
    public function updateExchangeRate($currency_id, $new_rate) {
        $stmt = $this->db->prepare("UPDATE currencies SET exchange_rate_to_iqd = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$new_rate, $currency_id]);
    }
    
    /**
     * الحصول على عملة من الرمز
     */
    public function getCurrencyByCode($currency_code) {
        $stmt = $this->db->prepare("SELECT * FROM currencies WHERE currency_code = ? AND is_active = 1");
        $stmt->execute([$currency_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// إنشاء instance عالمي
$currency_helper = new CurrencyHelper();

// Functions مساعدة للاستخدام المباشر
function getCurrencySelect($name, $selected_id = null, $class = 'form-control', $required = false) {
    global $currency_helper;
    return $currency_helper->createCurrencySelect($name, $selected_id, $class, $required);
}

function formatCurrency($amount, $currency_id = null, $show_symbol = true) {
    global $currency_helper;
    return $currency_helper->formatAmount($amount, $currency_id, $show_symbol);
}

function getActiveCurrencies() {
    global $currency_helper;
    return $currency_helper->getActiveCurrencies();
}

function getDefaultCurrency() {
    global $currency_helper;
    return $currency_helper->getDefaultCurrency();
}
?> 
