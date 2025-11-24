<?php
/**
 * سكريبت اختبار شامل لجميع مصادر روابط مهمة
 * يختبر كل مصدر ويعطي تقرير مفصل
 */

require_once '../config/database.php';
require_once '../includes/ImportantLinksFetcher.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار شامل للمصادر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .test-running { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold mb-4">🧪 اختبار شامل لمصادر البيانات</h1>
            <p class="text-gray-600 mb-4">هذا السكريبت يختبر جميع المصادر ويعطيك تقرير مفصل عن كل مصدر</p>
        </div>

        <?php
        try {
            $database = new Database();
            $db = $database->getConnection();
            $db->exec("SET NAMES utf8mb4");

            // جلب جميع المصادر
            $stmt = $db->query("SELECT * FROM important_link_sources ORDER BY id");
            $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($sources)) {
                echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">';
                echo '⚠️ لا توجد مصادر في قاعدة البيانات. يرجى إضافة مصادر أولاً.';
                echo '</div>';
                exit;
            }

            echo '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">';
            echo '📊 عدد المصادر الموجودة: <strong>' . count($sources) . '</strong>';
            echo '</div>';

            $fetcher = new ImportantLinksFetcher($db);
            $successCount = 0;
            $failedCount = 0;
            $results = [];

            foreach ($sources as $source) {
                echo '<div class="bg-white rounded-lg shadow-md p-6 mb-6 border-2 border-gray-200">';
                echo '<div class="flex justify-between items-start mb-4">';
                echo '<div>';
                echo '<h2 class="text-2xl font-bold">' . htmlspecialchars($source['name_ar']) . '</h2>';
                echo '<p class="text-sm text-gray-600">ID: ' . $source['id'] . '</p>';
                echo '</div>';
                
                $statusBadge = $source['is_active'] ? 
                    '<span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">نشط</span>' : 
                    '<span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm">غير نشط</span>';
                echo $statusBadge;
                echo '</div>';

                // معلومات المصدر
                echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 text-sm">';
                echo '<div><strong>النوع:</strong> ' . htmlspecialchars($source['source_type']) . '</div>';
                echo '<div><strong>طريقة الجلب:</strong> ' . htmlspecialchars($source['fetch_method'] ?? 'N/A') . '</div>';
                echo '<div><strong>التكرار:</strong> ' . htmlspecialchars($source['update_frequency']) . '</div>';
                echo '<div><strong>آخر تحديث:</strong> ' . ($source['last_update'] ?: 'لم يتم') . '</div>';
                echo '</div>';

                // عرض الروابط
                if (!empty($source['api_url'])) {
                    echo '<div class="mb-2 text-sm">';
                    echo '<strong>API URL:</strong> <code class="bg-gray-100 px-2 py-1 rounded text-xs break-all">' . htmlspecialchars($source['api_url']) . '</code>';
                    echo '</div>';
                }
                if (!empty($source['scraping_url'])) {
                    echo '<div class="mb-2 text-sm">';
                    echo '<strong>Scraping URL:</strong> <code class="bg-gray-100 px-2 py-1 rounded text-xs break-all">' . htmlspecialchars($source['scraping_url']) . '</code>';
                    echo '</div>';
                }

                // اختبار المصدر
                echo '<div class="mt-4 p-4 bg-gray-50 rounded">';
                echo '<div class="test-running mb-2">🔄 جاري اختبار المصدر...</div>';
                
                flush();
                ob_flush();

                $startTime = microtime(true);
                
                try {
                    $result = $fetcher->fetchFromSource($source['id']);
                    $executionTime = round(microtime(true) - $startTime, 2);

                    if ($result['success']) {
                        $successCount++;
                        echo '<div class="bg-green-50 border-2 border-green-400 text-green-800 px-4 py-3 rounded mt-2">';
                        echo '<h3 class="font-bold text-lg mb-2">✅ نجح الاختبار!</h3>';
                        echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">';
                        echo '<div><strong>تم الجلب:</strong> ' . $result['items_fetched'] . '</div>';
                        echo '<div><strong>تم الاستيراد:</strong> ' . $result['items_imported'] . '</div>';
                        echo '<div><strong>تم التحديث:</strong> ' . $result['items_updated'] . '</div>';
                        echo '<div><strong>الوقت:</strong> ' . $executionTime . 's</div>';
                        echo '</div>';
                        
                        // عرض السجلات
                        $logs = $fetcher->getLogs();
                        if (!empty($logs)) {
                            echo '<details class="mt-3">';
                            echo '<summary class="cursor-pointer font-bold">📋 عرض السجل التفصيلي</summary>';
                            echo '<div class="bg-white p-3 mt-2 rounded text-xs font-mono max-h-64 overflow-y-auto">';
                            foreach ($logs as $log) {
                                echo htmlspecialchars($log) . '<br>';
                            }
                            echo '</div>';
                            echo '</details>';
                        }
                        
                        echo '</div>';
                    } else {
                        $failedCount++;
                        echo '<div class="bg-red-50 border-2 border-red-400 text-red-800 px-4 py-3 rounded mt-2">';
                        echo '<h3 class="font-bold text-lg mb-2">❌ فشل الاختبار</h3>';
                        echo '<p class="text-sm"><strong>الخطأ:</strong> ' . htmlspecialchars($result['error']) . '</p>';
                        
                        // عرض السجلات حتى لو فشل
                        $logs = $fetcher->getLogs();
                        if (!empty($logs)) {
                            echo '<details class="mt-3">';
                            echo '<summary class="cursor-pointer font-bold">📋 عرض السجل التفصيلي</summary>';
                            echo '<div class="bg-white p-3 mt-2 rounded text-xs font-mono max-h-64 overflow-y-auto">';
                            foreach ($logs as $log) {
                                echo htmlspecialchars($log) . '<br>';
                            }
                            echo '</div>';
                            echo '</details>';
                        }
                        
                        echo '</div>';
                        
                        // اقتراحات الإصلاح
                        echo '<div class="bg-yellow-50 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mt-2 text-sm">';
                        echo '<h4 class="font-bold mb-2">💡 اقتراحات الإصلاح:</h4>';
                        echo '<ul class="list-disc list-inside space-y-1">';
                        
                        if (strpos($result['error'], '404') !== false) {
                            echo '<li>الرابط غير موجود (404) - قد يكون الرابط قد تغير أو غير صحيح</li>';
                            echo '<li>تحقق من الرابط على المتصفح مباشرة</li>';
                        }
                        if (strpos($result['error'], 'JSON') !== false) {
                            echo '<li>الاستجابة ليست JSON - قد تكون صفحة HTML</li>';
                            echo '<li>جرب استخدام Scraping بدلاً من API</li>';
                        }
                        if (strpos($result['error'], 'فارغة') !== false) {
                            echo '<li>الاستجابة فارغة - تحقق من صحة الرابط</li>';
                        }
                        if (strpos($result['error'], 'data_path') !== false) {
                            echo '<li>مشكلة في mapping_config - تحقق من مسار البيانات</li>';
                        }
                        
                        echo '</ul>';
                        echo '</div>';
                    }

                } catch (Exception $e) {
                    $failedCount++;
                    echo '<div class="bg-red-50 border-2 border-red-400 text-red-800 px-4 py-3 rounded mt-2">';
                    echo '<h3 class="font-bold text-lg mb-2">❌ خطأ في الاختبار</h3>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }

                echo '</div>'; // end test container
                echo '</div>'; // end source card
                
                flush();
                ob_flush();
            }

            // ملخص النتائج
            echo '<div class="bg-white rounded-lg shadow-lg p-6 mt-8">';
            echo '<h2 class="text-2xl font-bold mb-4">📊 ملخص النتائج</h2>';
            echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
            
            echo '<div class="bg-blue-50 p-4 rounded">';
            echo '<div class="text-3xl font-bold text-blue-600">' . count($sources) . '</div>';
            echo '<div class="text-sm text-gray-600">إجمالي المصادر</div>';
            echo '</div>';
            
            echo '<div class="bg-green-50 p-4 rounded">';
            echo '<div class="text-3xl font-bold text-green-600">' . $successCount . '</div>';
            echo '<div class="text-sm text-gray-600">مصادر تعمل بنجاح</div>';
            echo '</div>';
            
            echo '<div class="bg-red-50 p-4 rounded">';
            echo '<div class="text-3xl font-bold text-red-600">' . $failedCount . '</div>';
            echo '<div class="text-sm text-gray-600">مصادر فاشلة</div>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">';
            echo '<strong>خطأ:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>

        <div class="mt-6 text-center">
            <a href="important_links_sources_management.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                🔙 العودة لإدارة المصادر
            </a>
        </div>
    </div>
</body>
</html>
