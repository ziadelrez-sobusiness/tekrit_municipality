<?php
/**
 * API endpoint to get available Gemini models
 */

// Prevent any output before headers
if (ob_get_level()) {
    ob_clean();
}

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Allow public access for testing (no authentication required)
// This endpoint is safe because it only uses the provided API key to query Gemini API
$api_key = $_GET['api_key'] ?? '';

if (empty($api_key)) {
    echo json_encode([
        'success' => false,
        'error' => 'مفتاح API مطلوب'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $versions = ['v1beta', 'v1'];
    $all_models = [];
    
    foreach ($versions as $version) {
        $url = "https://generativelanguage.googleapis.com/{$version}/models?key=" . urlencode($api_key);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['models'])) {
                foreach ($data['models'] as $model) {
                    if (strpos($model['name'], 'gemini') !== false) {
                        $model_name = str_replace('models/', '', $model['name']);
                        $supported_methods = $model['supportedGenerationMethods'] ?? [];
                        
                        // Only include models that support generateContent
                        if (in_array('generateContent', $supported_methods)) {
                            if (!isset($all_models[$model_name])) {
                                $all_models[$model_name] = [
                                    'name' => $model_name,
                                    'version' => $version,
                                    'methods' => $supported_methods
                                ];
                            } else {
                                // Prefer v1beta if available in both
                                if ($version === 'v1beta') {
                                    $all_models[$model_name]['version'] = $version;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Sort models: stable first, then previews
    $stable_models = [];
    $preview_models = [];
    $latest_models = [];
    
    foreach ($all_models as $model_name => $model_info) {
        if (strpos($model_name, 'latest') !== false) {
            $latest_models[$model_name] = $model_info;
        } elseif (strpos($model_name, 'preview') !== false || strpos($model_name, 'exp') !== false) {
            $preview_models[$model_name] = $model_info;
        } else {
            $stable_models[$model_name] = $model_info;
        }
    }
    
    // Sort each category
    ksort($stable_models);
    ksort($preview_models);
    ksort($latest_models);
    
    // Combine: stable, latest, then previews
    $sorted_models = array_merge($stable_models, $latest_models, $preview_models);
    
    // Extract just the names for the dropdown
    $model_names = array_keys($sorted_models);
    
    echo json_encode([
        'success' => true,
        'models' => $model_names,
        'models_detail' => $sorted_models
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Make sure we output JSON even on error
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'خطأ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

