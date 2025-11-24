<?php
/**
 * AI Helper Functions
 * يوفر دوال مساعدة للتعامل مع إعدادات الذكاء الاصطناعي
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings_helper.php';

class AIHelper {
    private $db;
    private $encryption_key;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();

        // مفتاح التشفير - يجب أن يكون في ملف config منفصل في بيئة الإنتاج
        $this->encryption_key = hash('sha256', 'tekrit_ai_secret_key_2025');
    }

    /**
     * تشفير API Key
     */
    public function encryptApiKey($api_key) {
        if (empty($api_key)) return '';

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($api_key, 'aes-256-cbc', $this->encryption_key, 0, $iv);

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * فك تشفير API Key
     */
    public function decryptApiKey($encrypted_key) {
        if (empty($encrypted_key)) return '';

        try {
            list($encrypted_data, $iv) = explode('::', base64_decode($encrypted_key), 2);
            return openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->encryption_key, 0, $iv);
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * حفظ إعدادات AI مع تشفير API Key
     */
    public function saveAISettings($provider, $api_key, $model, $enabled, $image_provider = 'auto') {
        try {
            // تشفير API Key
            $encrypted_key = $this->encryptApiKey($api_key);

            // حفظ الإعدادات
            setSetting('ai_provider', $provider, 'نوع مزود خدمة الذكاء الاصطناعي');
            setSetting('ai_api_key', $encrypted_key, 'مفتاح API للذكاء الاصطناعي (مشفر)');
            setSetting('ai_model', $model, 'نموذج الذكاء الاصطناعي المستخدم');
            setSetting('ai_enabled', $enabled ? '1' : '0', 'تفعيل الذكاء الاصطناعي');
            setSetting('ai_image_provider', $image_provider, 'نوع خدمة إنشاء الصور');

            return true;
        } catch (Exception $e) {
            error_log("Error saving AI settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على إعدادات AI
     */
    public function getAISettings() {
        return [
            'provider' => getSetting('ai_provider', 'openai'),
            'api_key' => $this->decryptApiKey(getSetting('ai_api_key', '')),
            'model' => getSetting('ai_model', 'gpt-4'),
            'enabled' => getSetting('ai_enabled', '0') === '1',
            'image_provider' => getSetting('ai_image_provider', 'auto'),
            'temperature' => floatval(getSetting('ai_temperature', '0.7')),
            'max_tokens' => intval(getSetting('ai_max_tokens', '2000'))
        ];
    }

    /**
     * التحقق من تفعيل AI
     */
    public function isAIEnabled() {
        return getSetting('ai_enabled', '0') === '1';
    }

    /**
     * الحصول على قائمة مزودي AI المدعومين
     */
    public function getSupportedProviders() {
        return [
            'openai' => [
                'name' => 'OpenAI (ChatGPT)',
                'models' => ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo'],
                'image_support' => 'dall-e-3'
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['gemini-pro', 'gemini-1.5-pro', 'gemini-1.5-flash'],
                'image_support' => 'imagen'
            ],
            'claude' => [
                'name' => 'Anthropic Claude',
                'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'],
                'image_support' => null
            ],
            'custom' => [
                'name' => 'مخصص (Custom API)',
                'models' => ['custom'],
                'image_support' => 'custom'
            ]
        ];
    }

    /**
     * الحصول على نماذج AI حسب المزود
     */
    public function getModelsForProvider($provider) {
        $providers = $this->getSupportedProviders();
        return $providers[$provider]['models'] ?? [];
    }

    /**
     * التحقق من صحة إعدادات AI
     */
    public function validateAISettings($provider, $api_key, $model) {
        $errors = [];

        if (empty($provider)) {
            $errors[] = 'يرجى اختيار نوع الذكاء الاصطناعي';
        }

        if (empty($api_key)) {
            $errors[] = 'يرجى إدخال مفتاح API';
        }

        if (empty($model)) {
            $errors[] = 'يرجى اختيار النموذج';
        }

        $providers = $this->getSupportedProviders();
        if (!isset($providers[$provider])) {
            $errors[] = 'نوع الذكاء الاصطناعي غير مدعوم';
        } else if (!in_array($model, $providers[$provider]['models'])) {
            $errors[] = 'النموذج غير مدعوم لهذا المزود';
        }

        return $errors;
    }
}

// إنشاء instance عالمي
$ai_helper = new AIHelper();

// Functions مساعدة للاستخدام المباشر
function getAISettings() {
    global $ai_helper;
    return $ai_helper->getAISettings();
}

function isAIEnabled() {
    global $ai_helper;
    return $ai_helper->isAIEnabled();
}

function saveAISettings($provider, $api_key, $model, $enabled, $image_provider = 'auto') {
    global $ai_helper;
    return $ai_helper->saveAISettings($provider, $api_key, $model, $enabled, $image_provider);
}
?>
