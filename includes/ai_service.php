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
        $full_prompt = $system_message ? "$system_message\n\n$prompt" : $prompt;

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

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->settings['model'] . ':generateContent?key=' . $this->settings['api_key'];

        $response = $this->curlRequest($url, $data, ['Content-Type: application/json']);

        if (!$response['success']) {
            return $response;
        }

        $result = json_decode($response['data'], true);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'success' => true,
                'content' => $result['candidates'][0]['content']['parts'][0]['text'],
                'usage' => $result['usageMetadata'] ?? null
            ];
        }

        return ['success' => false, 'error' => 'استجابة غير صحيحة من API'];
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

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'خطأ في الاتصال: ' . $curl_error];
        }

        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = $error_data['error']['message'] ?? 'خطأ غير معروف';
            return ['success' => false, 'error' => 'خطأ من API: ' . $error_message];
        }

        return ['success' => true, 'data' => $response];
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
