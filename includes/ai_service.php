<?php
/**
 * AI Service - خدمة الذكاء الاصطناعي
 * للتعامل مع مختلف مزودي خدمات AI
 */

require_once __DIR__ . '/ai_helper.php';

class AIService {
    private $ai_helper;
    private $settings;

    public function __construct() {
        $this->ai_helper = new AIHelper();
        $this->settings = $this->ai_helper->getAISettings();
    }

    /**
     * إرسال طلب نصي للذكاء الاصطناعي
     */
    public function sendTextRequest($prompt, $system_message = null, $options = []) {
        if (!$this->ai_helper->isAIEnabled()) {
            return ['success' => false, 'error' => 'الذكاء الاصطناعي غير مفعل'];
        }

        if (empty($this->settings['api_key'])) {
            return ['success' => false, 'error' => 'لم يتم تكوين مفتاح API'];
        }

        try {
            switch ($this->settings['provider']) {
                case 'openai':
                    return $this->sendOpenAIRequest($prompt, $system_message, $options);
                case 'gemini':
                    return $this->sendGeminiRequest($prompt, $system_message, $options);
                case 'claude':
                    return $this->sendClaudeRequest($prompt, $system_message, $options);
                default:
                    return ['success' => false, 'error' => 'مزود الخدمة غير مدعوم'];
            }
        } catch (Exception $e) {
            error_log("AI Service Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'حدث خطأ أثناء الاتصال بخدمة الذكاء الاصطناعي'];
        }
    }

    /**
     * إنشاء صورة باستخدام AI
     */
    public function generateImage($prompt, $size = '1024x1024', $quality = 'standard') {
        if (!$this->ai_helper->isAIEnabled()) {
            return ['success' => false, 'error' => 'الذكاء الاصطناعي غير مفعل'];
        }

        if (empty($this->settings['api_key'])) {
            return ['success' => false, 'error' => 'لم يتم تكوين مفتاح API'];
        }

        try {
            // تحديد خدمة الصور تلقائياً بناءً على المزود
            $image_provider = $this->settings['image_provider'];
            if ($image_provider === 'auto') {
                $image_provider = $this->getDefaultImageProvider();
            }

            switch ($image_provider) {
                case 'dall-e':
                case 'dall-e-3':
                    return $this->generateDALLEImage($prompt, $size, $quality);
                case 'stable-diffusion':
                    return $this->generateStableDiffusionImage($prompt, $size);
                default:
                    return ['success' => false, 'error' => 'خدمة إنشاء الصور غير مدعومة'];
            }
        } catch (Exception $e) {
            error_log("AI Image Generation Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'حدث خطأ أثناء إنشاء الصورة'];
        }
    }

    /**
     * OpenAI API Request
     */
    private function sendOpenAIRequest($prompt, $system_message, $options) {
        $messages = [];

        if ($system_message) {
            $messages[] = [
                'role' => 'system',
                'content' => $system_message
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        $data = [
            'model' => $this->settings['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->settings['temperature'],
            'max_tokens' => $options['max_tokens'] ?? $this->settings['max_tokens']
        ];

        $response = $this->curlRequest(
            'https://api.openai.com/v1/chat/completions',
            $data,
            ['Authorization: Bearer ' . $this->settings['api_key']]
        );

        if (!$response['success']) {
            return $response;
        }

        $result = json_decode($response['data'], true);

        if (isset($result['choices'][0]['message']['content'])) {
            return [
                'success' => true,
                'content' => $result['choices'][0]['message']['content'],
                'usage' => $result['usage'] ?? null
            ];
        }

        return ['success' => false, 'error' => 'استجابة غير صحيحة من API'];
    }

    /**
     * Google Gemini API Request
     */
    private function sendGeminiRequest($prompt, $system_message, $options) {
        // Map user-friendly model names to actual API model names
        $model = $this->settings['model'];
        $model_mapping = [
            // Old model names (for backward compatibility)
            'gemini-pro' => 'gemini-pro-latest',
            'gemini-1.5-pro' => 'gemini-2.5-pro',
            'gemini-1.5-flash' => 'gemini-2.5-flash',
            'gemini-1.5-pro-latest' => 'gemini-2.5-pro',
            // New model names (use as-is)
            'gemini-2.5-flash' => 'gemini-2.5-flash',
            'gemini-2.5-pro' => 'gemini-2.5-pro',
            'gemini-2.0-flash' => 'gemini-2.0-flash',
            'gemini-pro-latest' => 'gemini-pro-latest',
            'gemini-2.5-pro-preview-06-05' => 'gemini-2.5-pro-preview-06-05',
            'gemini-3-pro-preview' => 'gemini-3-pro-preview'
        ];
        
        // Default to gemini-2.5-flash (fastest and most stable)
        $api_model = $model_mapping[$model] ?? $model;
        
        // If model not in mapping and not a known new model, try to use it as-is
        if (!isset($model_mapping[$model]) && strpos($model, 'gemini-') === 0) {
            $api_model = $model;
        } elseif (!isset($model_mapping[$model])) {
            // Fallback to latest stable model
            $api_model = 'gemini-2.5-flash';
        }
        
        // Combine system message with prompt (Gemini doesn't support systemInstruction in all versions)
        $full_prompt = $prompt;
        if ($system_message) {
            $full_prompt = $system_message . "\n\n" . $prompt;
        }
        
        // Build the request - simplified structure
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $full_prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? $this->settings['temperature'],
                'maxOutputTokens' => $options['max_tokens'] ?? $this->settings['max_tokens']
            ]
        ];

        // Try different API versions and models in order of compatibility
        // v1beta has more models available, so try it first
        $api_versions = ['v1beta', 'v1'];
        $models_to_try = [$api_model];
        
        // Only add fallback if the model is not one of the stable new models
        if (!in_array($api_model, ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash', 'gemini-pro-latest'])) {
            // Try gemini-2.5-flash as fallback (most stable)
            $models_to_try[] = 'gemini-2.5-flash';
        }

        $last_error = null;
        
        foreach ($api_versions as $version) {
            foreach ($models_to_try as $try_model) {
                $url = "https://generativelanguage.googleapis.com/{$version}/models/{$try_model}:generateContent?key=" . $this->settings['api_key'];
                
                $response = $this->curlRequest($url, $data, ['Content-Type: application/json']);

                if ($response['success']) {
                    $result = json_decode($response['data'], true);

                    // Check for API errors in response
                    if (isset($result['error'])) {
                        $last_error = $result['error']['message'] ?? 'خطأ غير معروف من Gemini API';
                        continue; // Try next model/version
                    }

                    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                        return [
                            'success' => true,
                            'content' => $result['candidates'][0]['content']['parts'][0]['text'],
                            'usage' => $result['usageMetadata'] ?? null
                        ];
                    }
                } else {
                    // Parse error message
                    $error_data = json_decode($response['data'] ?? '{}', true);
                    if (isset($error_data['error']['message'])) {
                        $last_error = $error_data['error']['message'];
                    } else {
                        $last_error = $response['error'] ?? 'خطأ في الاتصال بـ Gemini API';
                    }
                }
            }
        }

        // All attempts failed
        $error_msg = $last_error ?? 'فشل في الاتصال بـ Gemini API';
        
        // Provide helpful error message
        if (strpos($error_msg, 'not found') !== false || strpos($error_msg, 'not supported') !== false) {
            $error_msg = "النموذج المحدد غير متاح.\n\n";
            $error_msg .= "💡 الحلول المقترحة:\n";
            $error_msg .= "1. استخدم 'gemini-pro' (الأكثر استقراراً)\n";
            $error_msg .= "2. تحقق من أن مفتاح API صحيح وله صلاحيات كافية\n";
            $error_msg .= "3. تأكد من أن حسابك يدعم النماذج المطلوبة";
        }
        
        return ['success' => false, 'error' => $error_msg];
    }

    /**
     * Anthropic Claude API Request
     */
    private function sendClaudeRequest($prompt, $system_message, $options) {
        $data = [
            'model' => $this->settings['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $options['max_tokens'] ?? $this->settings['max_tokens'],
            'temperature' => $options['temperature'] ?? $this->settings['temperature']
        ];

        if ($system_message) {
            $data['system'] = $system_message;
        }

        $response = $this->curlRequest(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . $this->settings['api_key'],
                'anthropic-version: 2023-06-01'
            ]
        );

        if (!$response['success']) {
            return $response;
        }

        $result = json_decode($response['data'], true);

        if (isset($result['content'][0]['text'])) {
            return [
                'success' => true,
                'content' => $result['content'][0]['text'],
                'usage' => $result['usage'] ?? null
            ];
        }

        return ['success' => false, 'error' => 'استجابة غير صحيحة من API'];
    }

    /**
     * DALL-E Image Generation
     */
    private function generateDALLEImage($prompt, $size, $quality) {
        $data = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $quality
        ];

        $response = $this->curlRequest(
            'https://api.openai.com/v1/images/generations',
            $data,
            ['Authorization: Bearer ' . $this->settings['api_key']]
        );

        if (!$response['success']) {
            return $response;
        }

        $result = json_decode($response['data'], true);

        if (isset($result['data'][0]['url'])) {
            return [
                'success' => true,
                'image_url' => $result['data'][0]['url'],
                'revised_prompt' => $result['data'][0]['revised_prompt'] ?? null
            ];
        }

        return ['success' => false, 'error' => 'فشل إنشاء الصورة'];
    }

    /**
     * Stable Diffusion Image Generation (placeholder)
     */
    private function generateStableDiffusionImage($prompt, $size) {
        // يمكن إضافة التكامل مع Stable Diffusion API هنا
        return ['success' => false, 'error' => 'Stable Diffusion غير مدعوم حالياً'];
    }

    /**
     * الحصول على مزود الصور الافتراضي
     */
    private function getDefaultImageProvider() {
        switch ($this->settings['provider']) {
            case 'openai':
                return 'dall-e-3';
            case 'gemini':
                return 'imagen';
            default:
                return 'dall-e-3';
        }
    }

    /**
     * تنفيذ طلب CURL
     */
    private function curlRequest($url, $data, $headers = []) {
        $ch = curl_init($url);

        $default_headers = [
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($default_headers, $headers));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'خطأ في الاتصال: ' . $curl_error, 'data' => ''];
        }

        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = $error_data['error']['message'] ?? 'خطأ غير معروف';
            return ['success' => false, 'error' => 'خطأ من API: ' . $error_message, 'data' => $response];
        }

        return ['success' => true, 'data' => $response];
    }
    
    /**
     * الحصول على قائمة النماذج المتاحة من Gemini API
     */
    public function getAvailableGeminiModels() {
        if (empty($this->settings['api_key'])) {
            return ['success' => false, 'error' => 'مفتاح API غير محدد'];
        }
        
        $api_key = $this->settings['api_key'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key={$api_key}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['models'])) {
                $models = [];
                foreach ($data['models'] as $model) {
                    if (isset($model['name']) && strpos($model['name'], 'gemini') !== false) {
                        $model_name = str_replace('models/', '', $model['name']);
                        $models[] = $model_name;
                    }
                }
                return ['success' => true, 'models' => $models];
            }
        }
        
        return ['success' => false, 'error' => 'فشل في جلب النماذج المتاحة'];
    }
}

// Instance عالمي
$ai_service = new AIService();

// دوال مساعدة
function sendAIRequest($prompt, $system_message = null, $options = []) {
    global $ai_service;
    return $ai_service->sendTextRequest($prompt, $system_message, $options);
}

function generateAIImage($prompt, $size = '1024x1024', $quality = 'standard') {
    global $ai_service;
    return $ai_service->generateImage($prompt, $size, $quality);
}
?>
