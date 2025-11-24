<?php
/**
 * أمثلة على مصادر APIs صحيحة
 * يمكن استخدامها كمرجع عند إضافة مصادر جديدة
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// أمثلة على مصادر APIs (قد تحتاج إلى تعديل)
$examples = [
    [
        'name_ar' => 'OpenStreetMap Nominatim API',
        'name_en' => 'OpenStreetMap Nominatim',
        'description' => 'API للبحث عن أماكن ومرافق',
        'api_url' => 'https://nominatim.openstreetmap.org/search?format=json&q=hospital+lebanon',
        'api_key' => '',
        'source_type' => 'api',
        'category_id' => null,
        'mapping_config' => json_encode([
            'data_path' => '',
            'name_ar' => 'display_name',
            'location_lat' => 'lat',
            'location_lng' => 'lon',
            'address_ar' => 'display_name'
        ]),
        'note' => 'API مجاني، لا يحتاج API key'
    ],
    [
        'name_ar' => 'Lebanon Data API (مثال)',
        'name_en' => 'Lebanon Data API',
        'description' => 'مثال على API محلي - قد يحتاج إلى تعديل',
        'api_url' => 'https://api.example.com/lebanon/facilities',
        'api_key' => 'your-api-key-here',
        'source_type' => 'api',
        'category_id' => null,
        'mapping_config' => null,
        'note' => 'هذا مثال - استبدل بالرابط الفعلي'
    ],
    [
        'name_ar' => 'WHO Health Facilities',
        'name_en' => 'WHO Health Facilities',
        'description' => 'قاعدة بيانات منظمة الصحة العالمية للمرافق الصحية',
        'api_url' => 'https://ghoapi.azureedge.net/api/FACILITY',
        'api_key' => '',
        'source_type' => 'api',
        'category_id' => 2, // مستشفيات
        'mapping_config' => json_encode([
            'data_path' => 'value',
            'name_ar' => 'FacilityName',
            'phone' => 'Phone',
            'address_ar' => 'Address'
        ]),
        'note' => 'API مجاني من منظمة الصحة العالمية'
    ]
];

// إذا طلب المستخدم إضافة مثال
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_example'])) {
    $exampleIndex = intval($_POST['example_index']);
    if (isset($examples[$exampleIndex])) {
        $example = $examples[$exampleIndex];
        
        try {
            $stmt = $db->prepare("
                INSERT INTO important_link_sources 
                (name_ar, name_en, source_type, api_url, api_key, category_id, 
                 update_frequency, auto_import, mapping_config, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 'weekly', 1, ?, 0)
            ");
            $stmt->execute([
                $example['name_ar'],
                $example['name_en'],
                $example['source_type'],
                $example['api_url'],
                $example['api_key'],
                $example['category_id'],
                $example['mapping_config']
            ]);
            
            $success_message = "تم إضافة المثال بنجاح (غير مفعّل - يمكنك تفعيله بعد التعديل)";
        } catch (Exception $e) {
            $error_message = "خطأ: " . $e->getMessage();
        }
    }
}

$success_message = $success_message ?? '';
$error_message = $error_message ?? '';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أمثلة على مصادر APIs - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold">📚 أمثلة على مصادر APIs</h1>
                    <p class="text-gray-600">مصادر APIs جاهزة يمكن استخدامها أو تعديلها</p>
                </div>
                <a href="important_links_sources_management.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    🔙 العودة
                </a>
            </div>
            
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    ✅ <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    ❌ <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <div class="space-y-6">
                <?php foreach ($examples as $index => $example): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($example['name_ar']) ?></h3>
                                <p class="text-gray-600 mb-2"><?= htmlspecialchars($example['description']) ?></p>
                                <?php if ($example['note']): ?>
                                    <p class="text-sm text-blue-600 bg-blue-50 p-2 rounded">💡 <?= htmlspecialchars($example['note']) ?></p>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="inline">
                                <input type="hidden" name="example_index" value="<?= $index ?>">
                                <button type="submit" name="add_example" 
                                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                                    ➕ إضافة
                                </button>
                            </form>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <strong>رابط API:</strong>
                                <code class="block bg-gray-100 p-2 rounded mt-1 text-xs break-all"><?= htmlspecialchars($example['api_url']) ?></code>
                            </div>
                            <div>
                                <strong>API Key:</strong>
                                <code class="block bg-gray-100 p-2 rounded mt-1 text-xs"><?= $example['api_key'] ? htmlspecialchars($example['api_key']) : 'غير مطلوب' ?></code>
                            </div>
                            <?php if ($example['mapping_config']): ?>
                                <div class="md:col-span-2">
                                    <strong>Mapping Config:</strong>
                                    <pre class="bg-gray-100 p-2 rounded mt-1 text-xs overflow-auto"><?= htmlspecialchars($example['mapping_config']) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4">
                            <a href="test_source_fetch.php?url=<?= urlencode($example['api_url']) ?>&api_key=<?= urlencode($example['api_key']) ?>&mapping=<?= urlencode($example['mapping_config'] ?? '') ?>" 
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                🧪 اختبار هذا المصدر
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-8 bg-yellow-50 border border-yellow-400 rounded-lg p-6">
                <h3 class="font-bold text-yellow-800 mb-3">💡 نصائح مهمة:</h3>
                <ul class="list-disc list-inside space-y-2 text-sm text-yellow-700">
                    <li>قبل إضافة مصدر جديد، استخدم صفحة "🧪 اختبار مصدر" للتحقق من صحته</li>
                    <li>تحقق من أن API يعيد بيانات بصيغة JSON</li>
                    <li>إذا كان API يتطلب API key، احصل عليه من الموقع الرسمي</li>
                    <li>استخدم Mapping Config لربط حقول API مع حقول قاعدة البيانات</li>
                    <li>بعض APIs قد تحتاج إلى headers خاصة - يمكن إضافتها لاحقاً</li>
                    <li>إذا كان الرابط يعطي 404، قد يكون الرابط قد تغير أو غير متاح</li>
                </ul>
            </div>
            
            <div class="mt-6 bg-blue-50 border border-blue-400 rounded-lg p-6">
                <h3 class="font-bold text-blue-800 mb-3">🔍 كيفية العثور على APIs:</h3>
                <ul class="list-disc list-inside space-y-2 text-sm text-blue-700">
                    <li>ابحث في مواقع الوزارات الرسمية عن "API" أو "Developer"</li>
                    <li>تحقق من مواقع مثل: RapidAPI, ProgrammableWeb</li>
                    <li>راجع وثائق الموقع الرسمي للمؤسسة</li>
                    <li>اتصل بالدعم الفني للمؤسسة للاستفسار عن APIs متاحة</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>

