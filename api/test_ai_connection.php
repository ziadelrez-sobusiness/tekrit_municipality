<?php
/**
 * API endpoint to test AI connection
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_service.php';

// Authentication check
$auth->requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$provider = $input['provider'] ?? '';
$model = $input['model'] ?? '';
$api_key = $input['api_key'] ?? '';

if (empty($provider) || empty($model) || empty($api_key)) {
    echo json_encode([
        'success' => false,
        'error' => 'يرجى إدخال جميع البيانات المطلوبة'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Create temporary AI service with provided credentials
    $temp_settings = [
        'provider' => $provider,
        'api_key' => $api_key,
        'model' => $model,
        'enabled' => true,
        'temperature' => 0.7,
        'max_tokens' => 100
    ];
    
    // Test connection based on provider
    if ($provider === 'gemini') {
        // Map old model names to new ones
        $model_mapping = [
            'gemini-pro' => 'gemini-pro-latest',
            'gemini-1.5-pro' => 'gemini-2.5-pro',
            'gemini-1.5-flash' => 'gemini-2.5-flash'
        ];
        
        // Use mapped model if available, otherwise use the model as-is
        $actual_model = $model_mapping[$model] ?? $model;
        $models_to_try = [$actual_model];
        
        // Try v1beta first (more models available), then v1
        $api_versions = ['v1beta', 'v1'];
        
        // Only add fallback if the model is not one of the stable new models
        if (!in_array($actual_model, ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash', 'gemini-pro-latest'])) {
            // Try gemini-2.5-flash as fallback (most stable)
            $models_to_try[] = 'gemini-2.5-flash';
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hello']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 10
            ]
        ];
        
        $last_error = null;
        $last_http_code = null;
        $last_response = null;
        
        foreach ($api_versions as $version) {
            foreach ($models_to_try as $try_model) {
                $url = "https://generativelanguage.googleapis.com/{$version}/models/{$try_model}:generateContent?key=" . urlencode($api_key);
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                if ($curl_error) {
                    $last_error = "خطأ في الاتصال: {$curl_error}";
                    continue;
                }
                
                if ($http_code === 200) {
                    $result = json_decode($response, true);
                    
                    // Check for API errors in response (even with HTTP 200)
                    if (isset($result['error'])) {
                        $last_error = $result['error']['message'] ?? 'خطأ من API';
                        $last_http_code = $http_code;
                        $last_response = $response;
                        continue;
                    }
                    
                    // Check if we have valid response with text
                    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                        echo json_encode([
                            'success' => true,
                            'message' => "✅ النموذج '{$try_model}' يعمل بشكل صحيح مع API {$version}"
                        ], JSON_UNESCAPED_UNICODE);
                        exit();
                    } elseif (isset($result['candidates']) && is_array($result['candidates']) && count($result['candidates']) > 0) {
                        // Response has candidates but no text - might be blocked or filtered
                        $last_error = 'استجابة من API لكن بدون نص (قد يكون المحتوى محظوراً)';
                        $last_http_code = $http_code;
                        $last_response = $response;
                    } else {
                        // No candidates at all
                        $last_error = 'استجابة غير صحيحة من API - لا توجد نتائج';
                        $last_http_code = $http_code;
                        $last_response = $response;
                        
                        // Log the actual response for debugging
                        error_log("Gemini API test - Unexpected response: " . substr($response, 0, 500));
                    }
                } else {
                    $error_data = json_decode($response, true);
                    if (isset($error_data['error']['message'])) {
                        $last_error = $error_data['error']['message'];
                    } else {
                        $last_error = "خطأ HTTP {$http_code}";
                    }
                    $last_http_code = $http_code;
                    $last_response = $response;
                }
            }
        }
        
        // All attempts failed
        $error_msg = $last_error ?? 'فشل في الاتصال بـ Gemini API';
        
        // Provide detailed error message
        $detailed_error = "❌ فشل الاتصال:\n\n";
        $detailed_error .= "النموذج المحدد: {$model}\n";
        $detailed_error .= "رمز HTTP: {$last_http_code}\n";
        $detailed_error .= "الخطأ: {$error_msg}\n\n";
        
        // If we got HTTP 200 but invalid response, provide more details
        if ($last_http_code === 200 && (strpos($error_msg, 'استجابة غير صحيحة') !== false || strpos($error_msg, 'لا توجد نتائج') !== false)) {
            $response_preview = substr($last_response ?? '', 0, 300);
            $detailed_error .= "📋 معاينة الاستجابة:\n";
            $detailed_error .= htmlspecialchars($response_preview) . "\n\n";
            $detailed_error .= "💡 الحلول المقترحة:\n";
            $detailed_error .= "1. تحقق من أن مفتاح API صحيح وله صلاحيات كافية\n";
            $detailed_error .= "2. جرب نموذج آخر مثل 'gemini-2.5-pro'\n";
            $detailed_error .= "3. تحقق من أن حسابك يدعم النموذج المحدد\n";
            $detailed_error .= "4. قد يكون هناك قيود على استخدام API في حسابك";
        } elseif (strpos($error_msg, 'API key') !== false || strpos($error_msg, 'invalid') !== false) {
            $detailed_error .= "💡 تحقق من:\n";
            $detailed_error .= "1. صحة مفتاح API\n";
            $detailed_error .= "2. أن المفتاح غير منتهي الصلاحية\n";
            $detailed_error .= "3. أن المفتاح له صلاحيات الوصول إلى Gemini API";
        } elseif (strpos($error_msg, 'not found') !== false || strpos($error_msg, 'not supported') !== false) {
            $detailed_error .= "💡 الحلول المقترحة:\n";
            $detailed_error .= "1. استخدم 'gemini-2.5-flash' (الأسرع والأكثر استقراراً)\n";
            $detailed_error .= "2. أو 'gemini-2.5-pro' (للأداء الأقوى)\n";
            $detailed_error .= "3. تأكد من أن النموذج المحدد متاح في حسابك";
        } else {
            $detailed_error .= "💡 تحقق من:\n";
            $detailed_error .= "1. الاتصال بالإنترنت\n";
            $detailed_error .= "2. صحة مفتاح API\n";
            $detailed_error .= "3. أن حسابك يدعم Gemini API";
        }
        
        echo json_encode([
            'success' => false,
            'error' => $detailed_error,
            'debug' => [
                'http_code' => $last_http_code,
                'response_preview' => substr($last_response ?? '', 0, 200)
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else if ($provider === 'openai') {
        // Test OpenAI connection
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ],
            'max_tokens' => 10
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            echo json_encode([
                'success' => true,
                'message' => "النموذج '{$model}' يعمل بشكل صحيح"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $error_data = json_decode($response, true);
            $error_msg = $error_data['error']['message'] ?? 'خطأ غير معروف';
            echo json_encode([
                'success' => false,
                'error' => $error_msg
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'مزود الخدمة غير مدعوم للاختبار'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'خطأ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

