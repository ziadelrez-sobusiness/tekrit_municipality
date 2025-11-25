<?php
/**
 * اختبار مباشر لـ Gemini API
 * استخدم هذا الملف لتشخيص مشاكل الاتصال
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار Gemini API مباشر</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #45a049;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 12px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
        }
    </style>
    <script>
        // Load available models when API key is entered
        document.addEventListener('DOMContentLoaded', function() {
            const apiKeyInput = document.querySelector('input[name="api_key"]');
            const modelSelect = document.getElementById('model_select');
            
            // Load models when API key changes
            apiKeyInput.addEventListener('blur', function() {
                const apiKey = this.value.trim();
                if (apiKey.length > 10) {
                    loadAvailableModels(apiKey);
                }
            });
            
            // Also load if API key is pre-filled
            if (apiKeyInput.value.trim().length > 10) {
                loadAvailableModels(apiKeyInput.value.trim());
            }
        });
        
        function loadAvailableModels(apiKey) {
            const modelSelect = document.getElementById('model_select');
            modelSelect.innerHTML = '<option value="">جاري التحميل...</option>';
            
            fetch(`api/get_gemini_models.php?api_key=${encodeURIComponent(apiKey)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.models.length > 0) {
                        modelSelect.innerHTML = '';
                        
                        // Add recommended models first
                        const recommended = ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash', 'gemini-pro-latest'];
                        recommended.forEach(model => {
                            if (data.models.includes(model)) {
                                const option = document.createElement('option');
                                option.value = model;
                                option.textContent = model + ' ⭐ (موصى به)';
                                option.selected = model === 'gemini-2.5-flash'; // Default selection
                                modelSelect.appendChild(option);
                            }
                        });
                        
                        // Add separator if there are other models
                        if (data.models.length > recommended.length) {
                            const separator = document.createElement('option');
                            separator.disabled = true;
                            separator.textContent = '──────────';
                            modelSelect.appendChild(separator);
                        }
                        
                        // Add other models
                        data.models.forEach(model => {
                            if (!recommended.includes(model)) {
                                const option = document.createElement('option');
                                option.value = model;
                                option.textContent = model;
                                modelSelect.appendChild(option);
                            }
                        });
                    } else {
                        modelSelect.innerHTML = '<option value="">فشل تحميل النماذج</option>';
                    }
                })
                .catch(error => {
                    modelSelect.innerHTML = '<option value="">خطأ في التحميل</option>';
                    console.error('Error loading models:', error);
                });
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>🧪 اختبار Gemini API مباشر</h1>
        
        <form method="POST">
            <label>مفتاح API:</label>
            <input type="text" name="api_key" placeholder="أدخل مفتاح API" required 
                   value="<?= htmlspecialchars($_POST['api_key'] ?? '') ?>">
            
            <label>النموذج:</label>
            <select name="model" id="model_select">
                <option value="">-- جاري التحميل... --</option>
            </select>
            <small style="color: #666;">سيتم تحميل النماذج المتاحة تلقائياً بعد إدخال مفتاح API</small>
            
            <label>إصدار API (سيتم تجربة كلا الإصدارين تلقائياً):</label>
            <select name="version" disabled>
                <option value="v1beta" selected>v1beta (موصى به)</option>
                <option value="v1">v1</option>
            </select>
            <small style="color: #666;">سيتم تجربة v1beta أولاً ثم v1 تلقائياً</small>
            
            <button type="submit">🚀 اختبار الاتصال</button>
            <button type="submit" name="list_models" value="1">📋 عرض النماذج المتاحة</button>
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $api_key = $_POST['api_key'] ?? '';
            
            // List available models
            if (isset($_POST['list_models'])) {
                echo '<div class="result info">';
                echo "📋 جاري جلب النماذج المتاحة...\n\n";
                echo "</div>";
                
                $versions = ['v1beta', 'v1'];
                $all_models = [];
                
                foreach ($versions as $ver) {
                    $list_url = "https://generativelanguage.googleapis.com/{$ver}/models?key=" . urlencode($api_key);
                    
                    $ch = curl_init($list_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($http_code === 200) {
                        $data = json_decode($response, true);
                        if (isset($data['models'])) {
                            echo '<div class="result success">';
                            echo "✅ النماذج المتاحة في {$ver}:\n";
                            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                            
                            $gemini_models = [];
                            foreach ($data['models'] as $m) {
                                if (strpos($m['name'], 'gemini') !== false) {
                                    $model_name = str_replace('models/', '', $m['name']);
                                    $gemini_models[] = $model_name;
                                    $supported = $m['supportedGenerationMethods'] ?? [];
                                    echo "📌 {$model_name}\n";
                                    if (!empty($supported)) {
                                        echo "   الطرق المدعومة: " . implode(', ', $supported) . "\n";
                                    }
                                    echo "\n";
                                }
                            }
                            
                            $all_models = array_merge($all_models, $gemini_models);
                            echo "</div>";
                        }
                    } else {
                        echo '<div class="result error">';
                        echo "❌ فشل جلب النماذج من {$ver}\n";
                        echo "رمز HTTP: {$http_code}\n";
                        echo "</div>";
                    }
                }
                
                if (!empty($all_models)) {
                    echo '<div class="result info">';
                    echo "💡 النماذج المتاحة للاستخدام:\n";
                    echo implode(', ', array_unique($all_models));
                    echo "</div>";
                }
                
                exit();
            }
            
            $model = $_POST['model'] ?? 'gemini-pro';
            $version = $_POST['version'] ?? 'v1beta'; // Default to v1beta
            
            echo '<div class="result info">';
            echo "🔍 جاري الاختبار...\n";
            echo "النموذج: {$model}\n";
            echo "الإصدار: {$version}\n";
            echo "المفتاح: " . substr($api_key, 0, 10) . "...\n";
            echo "</div>";
            
            // Try v1beta first (more models available)
            $versions_to_try = ['v1beta', 'v1'];
            $success = false;
            
            foreach ($versions_to_try as $try_version) {
                $url = "https://generativelanguage.googleapis.com/{$try_version}/models/{$model}:generateContent?key=" . urlencode($api_key);
            
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Say hello in Arabic']
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 50
                ]
            ];
            
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_VERBOSE, false);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                $curl_info = curl_getinfo($ch);
                curl_close($ch);
                
                echo '<div class="result ' . ($http_code === 200 ? 'success' : 'error') . '">';
                echo "📊 النتائج ({$try_version}):\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                echo "رمز HTTP: {$http_code}\n";
                echo "URL: {$url}\n\n";
            
                if ($curl_error) {
                    echo "❌ خطأ CURL: {$curl_error}\n\n";
                }
                
                if ($http_code === 200) {
                    $result = json_decode($response, true);
                    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                        $text = $result['candidates'][0]['content']['parts'][0]['text'];
                        echo "✅ نجح الاتصال!\n\n";
                        echo "📝 الاستجابة:\n";
                        echo "{$text}\n\n";
                        echo "📊 معلومات الاستخدام:\n";
                        if (isset($result['usageMetadata'])) {
                            print_r($result['usageMetadata']);
                        }
                        $success = true;
                        echo "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        echo "📋 معلومات CURL:\n";
                        echo "  الوقت الإجمالي: " . ($curl_info['total_time'] ?? 'N/A') . " ثانية\n";
                        echo "  حجم الاستجابة: " . ($curl_info['size_download'] ?? 'N/A') . " بايت\n";
                        echo "</div>";
                        break; // Success, exit loop
                    } else {
                        echo "⚠️ استجابة غير متوقعة:\n";
                        echo htmlspecialchars(substr($response, 0, 500));
                        echo "</div>";
                    }
                } else {
                    echo "❌ فشل الاتصال\n\n";
                    $error_data = json_decode($response, true);
                    if (isset($error_data['error'])) {
                        echo "الخطأ:\n";
                        echo "  الكود: " . ($error_data['error']['code'] ?? 'غير محدد') . "\n";
                        echo "  الرسالة: " . ($error_data['error']['message'] ?? 'غير محدد') . "\n";
                        echo "  الحالة: " . ($error_data['error']['status'] ?? 'غير محدد') . "\n";
                    } else {
                        echo "الاستجابة الكاملة:\n";
                        echo htmlspecialchars(substr($response, 0, 1000));
                    }
                    echo "</div>";
                    
                    // If this is the last version, show final error
                    if ($try_version === end($versions_to_try)) {
                        echo '<div class="result error">';
                        echo "\n\n❌ فشل جميع المحاولات!\n";
                        echo "💡 الحلول المقترحة:\n";
                        echo "1. اضغط '📋 عرض النماذج المتاحة' لمعرفة النماذج المدعومة\n";
                        echo "2. جرب استخدام v1beta بدلاً من v1\n";
                        echo "3. تأكد من صحة مفتاح API\n";
                        echo "</div>";
                    }
                }
            }
        }
        ?>
    </div>
</body>
</html>

