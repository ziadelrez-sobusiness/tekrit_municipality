<?php
/**
 * صفحة اختبار جلب البيانات من مصدر
 * تستخدم لاختبار المصادر قبل إضافتها
 */

require_once '../config/database.php';
require_once '../includes/ImportantLinksFetcher.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$testResult = null;
$error = null;

// دعم GET parameters للاختبار السريع
$testUrl = $_GET['url'] ?? ($_POST['test_url'] ?? '');
$testApiKey = $_GET['api_key'] ?? ($_POST['api_key'] ?? '');
$testMapping = $_GET['mapping'] ?? ($_POST['mapping_config'] ?? '');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_url']) || !empty($testUrl)) {
    try {
        // إنشاء مصدر تجريبي
        $testSource = [
            'id' => 0,
            'name_ar' => 'اختبار',
            'api_url' => $testUrl,
            'api_key' => $testApiKey,
            'source_type' => 'api',
            'category_id' => null,
            'mapping_config' => !empty($testMapping) ? $testMapping : null
        ];
        
        $fetcher = new ImportantLinksFetcher($db);
        
        // محاولة جلب البيانات
        try {
            $data = $fetcher->fetchFromAPI($testSource);
        } catch (Exception $e) {
            throw $e; // إعادة رمي الخطأ للتعامل معه في الكود الخارجي
        }
        
        $testResult = [
            'success' => true,
            'items_count' => count($data),
            'data' => array_slice($data, 0, 5), // أول 5 عناصر فقط للعرض
            'logs' => $fetcher->getLogs()
        ];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        $testResult = [
            'success' => false,
            'error' => $error
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار مصدر API - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-6">🧪 اختبار مصدر API</h1>
            
            <form method="POST" class="space-y-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">رابط API *</label>
                    <input type="url" name="test_url" required 
                           value="<?= htmlspecialchars($testUrl) ?>"
                           placeholder="https://example.com/api/facilities"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">⚠️ تأكد من أن الرابط صحيح ويعيد بيانات JSON</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">مفتاح API (اختياري)</label>
                    <input type="text" name="api_key" 
                           value="<?= htmlspecialchars($testApiKey) ?>"
                           placeholder="your-api-key-here"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mapping Config (JSON - اختياري)</label>
                    <textarea name="mapping_config" rows="4" 
                              placeholder='{"data_path": "data.items", "name_ar": "name", "phone": "telephone"}'
                              class="w-full border border-gray-300 rounded-md px-3 py-2 font-mono text-sm"><?= htmlspecialchars($testMapping) ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">استخدم هذا إذا كانت البيانات في مسار مختلف (مثل data.items)</p>
                </div>
                
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    🧪 اختبار المصدر
                </button>
            </form>
            
            <?php if ($testResult): ?>
                <?php if ($testResult['success']): ?>
                    <div class="bg-green-50 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <h3 class="font-bold mb-2">✅ نجح الاختبار!</h3>
                        <p>تم جلب <?= $testResult['items_count'] ?> عنصر</p>
                    </div>
                    
                    <?php if (!empty($testResult['data'])): ?>
                        <div class="mb-4">
                            <h3 class="font-bold mb-2">عينة من البيانات (أول 5 عناصر):</h3>
                            <pre class="bg-gray-100 p-4 rounded overflow-auto text-xs"><?= htmlspecialchars(json_encode($testResult['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <h3 class="font-bold mb-2">❌ فشل الاختبار</h3>
                        <p><?= htmlspecialchars($testResult['error']) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($testResult['logs'])): ?>
                    <div class="mt-4">
                        <h3 class="font-bold mb-2">سجل العمليات:</h3>
                        <div class="bg-gray-100 p-4 rounded">
                            <?php foreach ($testResult['logs'] as $log): ?>
                                <div class="text-sm text-gray-700 mb-1"><?= htmlspecialchars($log) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="mt-6 pt-6 border-t">
                <a href="important_links_sources_management.php" class="text-blue-600 hover:text-blue-800">
                    ← العودة لإدارة المصادر
                </a>
            </div>
        </div>
    </div>
</body>
</html>

