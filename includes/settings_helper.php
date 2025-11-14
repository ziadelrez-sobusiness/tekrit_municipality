<?php
require_once __DIR__ . '/../config/database.php';

class SettingsHelper {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * الحصول على قيمة إعداد
     */
    public function getSetting($key, $default = null) {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (PDOException $e) {
            return $default;
        }
    }
    
    /**
     * تحديث قيمة إعداد
     */
    public function setSetting($key, $value, $description = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                setting_description = VALUES(setting_description),
                updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$key, $value, $description]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * الحصول على جميع الإعدادات
     */
    public function getAllSettings() {
        try {
            $stmt = $this->db->query("SELECT * FROM system_settings ORDER BY setting_key");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * الحصول على معرف العملة الافتراضية
     */
    public function getDefaultCurrencyId() {
        return intval($this->getSetting('default_currency_id', 1));
    }
    
    /**
     * تحديث العملة الافتراضية
     */
    public function setDefaultCurrency($currency_id) {
        return $this->setSetting('default_currency_id', $currency_id, 'معرف العملة الافتراضية للنظام');
    }
}

// إنشاء instance عالمي
$settings_helper = new SettingsHelper();

// Functions مساعدة للاستخدام المباشر
function getSetting($key, $default = null) {
    global $settings_helper;
    return $settings_helper->getSetting($key, $default);
}

function setSetting($key, $value, $description = '') {
    global $settings_helper;
    return $settings_helper->setSetting($key, $value, $description);
}

function getDefaultCurrencyId() {
    global $settings_helper;
    return $settings_helper->getDefaultCurrencyId();
}
?> 
