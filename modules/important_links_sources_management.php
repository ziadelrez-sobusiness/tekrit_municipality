<?php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/ImportantLinksFetcher.php';

// التحقق من الصلاحيات
$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$success_message = '';
$error_message = '';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_protect(false)) {
        $error_message = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // إضافة مصدر جديد
        if ($action == 'add_source') {
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en'] ?? '');
            $source_type = $_POST['source_type'] ?? 'api';
            $api_url = trim($_POST['api_url'] ?? '');
            $api_key = trim($_POST['api_key'] ?? '');
            $scraping_url = trim($_POST['scraping_url'] ?? '');
            $scraping_selector = trim($_POST['scraping_selector'] ?? '');
            $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $update_frequency = $_POST['update_frequency'] ?? 'daily';
            $auto_import = isset($_POST['auto_import']) ? 1 : 0;
            $mapping_config = !empty($_POST['mapping_config']) ? $_POST['mapping_config'] : null;
            
            if (!empty($name_ar)) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO important_link_sources 
                        (name_ar, name_en, source_type, api_url, api_key, scraping_url, scraping_selector, 
                         category_id, update_frequency, auto_import, mapping_config, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $name_ar, $name_en, $source_type, $api_url, $api_key, 
                        $scraping_url, $scraping_selector, $category_id, 
                        $update_frequency, $auto_import, $mapping_config
                    ]);
                    $success_message = "تم إضافة المصدر بنجاح";
                } catch (Exception $e) {
                    $error_message = "خطأ في إضافة المصدر: " . $e->getMessage();
                }
            } else {
                $error_message = "يرجى إدخال اسم المصدر";
            }
        }
        
        // تعديل مصدر
        elseif ($action == 'edit_source') {
            $source_id = intval($_POST['source_id']);
            $name_ar = trim($_POST['name_ar']);
            $name_en = trim($_POST['name_en'] ?? '');
            $source_type = $_POST['source_type'] ?? 'api';
            $api_url = trim($_POST['api_url'] ?? '');
            $api_key = trim($_POST['api_key'] ?? '');
            $scraping_url = trim($_POST['scraping_url'] ?? '');
            $scraping_selector = trim($_POST['scraping_selector'] ?? '');
            $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $update_frequency = $_POST['update_frequency'] ?? 'daily';
            $auto_import = isset($_POST['auto_import']) ? 1 : 0;
            $mapping_config = !empty($_POST['mapping_config']) ? $_POST['mapping_config'] : null;
            
            try {
                $stmt = $db->prepare("
                    UPDATE important_link_sources SET 
                    name_ar = ?, name_en = ?, source_type = ?, api_url = ?, api_key = ?, 
                    scraping_url = ?, scraping_selector = ?, category_id = ?, 
                    update_frequency = ?, auto_import = ?, mapping_config = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name_ar, $name_en, $source_type, $api_url, $api_key, 
                    $scraping_url, $scraping_selector, $category_id, 
                    $update_frequency, $auto_import, $mapping_config, $source_id
                ]);
                $success_message = "تم تحديث المصدر بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث المصدر: " . $e->getMessage();
            }
        }
        
        // جلب البيانات يدوياً
        elseif ($action == 'fetch_now') {
            $source_id = intval($_POST['source_id']);
            try {
                $fetcher = new ImportantLinksFetcher($db);
                $result = $fetcher->fetchFromSource($source_id);
                
                if ($result['success']) {
                    $success_message = "تم جلب " . $result['items_fetched'] . " عنصر، استيراد " . $result['items_imported'] . "، تحديث " . $result['items_updated'];
                } else {
                    $error_message = "فشل الجلب: " . ($result['error'] ?? 'خطأ غير معروف');
                }
            } catch (Exception $e) {
                $error_message = "خطأ في جلب البيانات: " . $e->getMessage();
            }
        }
        
        // حذف مصدر
        elseif ($action == 'delete_source') {
            $source_id = intval($_POST['source_id']);
            try {
                $stmt = $db->prepare("DELETE FROM important_link_sources WHERE id = ?");
                $stmt->execute([$source_id]);
                $success_message = "تم حذف المصدر بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في حذف المصدر: " . $e->getMessage();
            }
        }
        
        // تغيير حالة النشاط
        elseif ($action == 'toggle_active') {
            $source_id = intval($_POST['source_id']);
            try {
                $stmt = $db->prepare("UPDATE important_link_sources SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$source_id]);
                $success_message = "تم تحديث الحالة بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث الحالة: " . $e->getMessage();
            }
        }
    }
}

// جلب المصادر
$sources = $db->query("
    SELECT s.*, c.name_ar as category_name_ar 
    FROM important_link_sources s 
    LEFT JOIN important_link_categories c ON s.category_id = c.id 
    ORDER BY s.created_at DESC
")->fetchAll();

// جلب الفئات
$categories = $db->query("SELECT * FROM important_link_categories WHERE is_active = 1 ORDER BY name_ar")->fetchAll();

// جلب آخر 10 عمليات جلب
$recentLogs = $db->query("
    SELECT l.*, s.name_ar as source_name 
    FROM important_link_fetch_logs l 
    INNER JOIN important_link_sources s ON l.source_id = s.id 
    ORDER BY l.created_at DESC 
    LIMIT 10
")->fetchAll();

// إحصائيات
$stats = [
    'total_sources' => $db->query("SELECT COUNT(*) FROM important_link_sources")->fetchColumn(),
    'active_sources' => $db->query("SELECT COUNT(*) FROM important_link_sources WHERE is_active = 1")->fetchColumn(),
    'total_fetches' => $db->query("SELECT COUNT(*) FROM important_link_fetch_logs")->fetchColumn(),
    'successful_fetches' => $db->query("SELECT COUNT(*) FROM important_link_fetch_logs WHERE status = 'success'")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة مصادر روابط مهمة - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center h-auto md:h-16 py-4 md:py-0 gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">🔗 إدارة مصادر روابط مهمة</h1>
                    <p class="text-sm text-gray-500">إدارة المصادر التلقائية لجلب وتحديث المرافق العامة</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="showAddSourceModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm md:text-base">
                        ➕ إضافة مصدر جديد
                    </button>
                    <button onclick="fetchAllSources()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm md:text-base">
                        ⚡ جلب جميع المصادر
                    </button>
                    <a href="test_source_fetch.php" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm md:text-base">
                        🧪 اختبار مصدر
                    </a>
                    <a href="important_links_sources_examples.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm md:text-base">
                        📚 أمثلة APIs
                    </a>
                    <a href="important_links_management.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm md:text-base">
                        🔙 العودة
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                ✅ <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-blue-500 ml-2 md:ml-3">📡</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">إجمالي المصادر</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['total_sources'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-green-500 ml-2 md:ml-3">✅</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">مصادر نشطة</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['active_sources'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-purple-500 ml-2 md:ml-3">🔄</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">عمليات الجلب</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['total_fetches'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="text-2xl md:text-3xl text-green-500 ml-2 md:ml-3">✓</div>
                    <div>
                        <p class="text-xs md:text-sm text-gray-600">نجاح</p>
                        <p class="text-xl md:text-2xl font-bold"><?= $stats['successful_fetches'] ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sources Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-4 md:p-6 border-b">
                <h2 class="text-xl font-bold">المصادر</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">الاسم</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">النوع</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">التكرار</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">آخر تحديث</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($sources)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    لا توجد مصادر
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sources as $source): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="text-sm md:text-base font-medium text-gray-900">
                                            <?= htmlspecialchars($source['name_ar']) ?>
                                        </div>
                                        <?php if ($source['category_name_ar']): ?>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($source['category_name_ar']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm md:text-base">
                                        <?php
                                        $typeLabels = [
                                            'api' => '🌐 API',
                                            'scraping' => '🕷️ Scraping',
                                            'csv_import' => '📄 CSV',
                                            'manual' => '✋ يدوي'
                                        ];
                                        echo $typeLabels[$source['source_type']] ?? $source['source_type'];
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm md:text-base">
                                        <?php
                                        $frequencyLabels = [
                                            'hourly' => 'كل ساعة',
                                            'daily' => 'يومي',
                                            'weekly' => 'أسبوعي',
                                            'monthly' => 'شهري',
                                            'manual' => 'يدوي'
                                        ];
                                        echo $frequencyLabels[$source['update_frequency']] ?? $source['update_frequency'];
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm md:text-base text-gray-600">
                                        <?= $source['last_update'] ? date('Y-m-d H:i', strtotime($source['last_update'])) : 'لم يتم' ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded text-xs md:text-sm <?= $source['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                            <?= $source['is_active'] ? 'نشط' : 'غير نشط' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button onclick="fetchSource(<?= $source['id'] ?>)" 
                                                    class="bg-green-600 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm hover:bg-green-700">
                                                ⚡ جلب
                                            </button>
                                            <button onclick="editSource(<?= htmlspecialchars(json_encode($source)) ?>)" 
                                                    class="bg-blue-600 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm hover:bg-blue-700">
                                                ✏️
                                            </button>
                                            <button onclick="toggleSourceActive(<?= $source['id'] ?>, <?= $source['is_active'] ? 'false' : 'true' ?>)" 
                                                    class="bg-yellow-600 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm hover:bg-yellow-700">
                                                <?= $source['is_active'] ? '👁️' : '👁️' ?>
                                            </button>
                                            <button onclick="deleteSource(<?= $source['id'] ?>, '<?= htmlspecialchars($source['name_ar']) ?>')" 
                                                    class="bg-red-600 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm hover:bg-red-700">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Logs -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 md:p-6 border-b">
                <h2 class="text-xl font-bold">سجل العمليات الأخيرة</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">المصدر</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">النوع</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">النتائج</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">الخطأ</th>
                            <th class="px-4 py-3 text-right text-xs md:text-sm font-medium text-gray-700">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentLogs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm md:text-base"><?= htmlspecialchars($log['source_name']) ?></td>
                                <td class="px-4 py-3 text-sm md:text-base"><?= $log['fetch_type'] == 'auto' ? 'تلقائي' : 'يدوي' ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs md:text-sm <?= $log['status'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $log['status'] == 'success' ? 'نجح' : 'فشل' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm md:text-base">
                                    جلب: <?= $log['items_fetched'] ?> | 
                                    استيراد: <?= $log['items_imported'] ?> | 
                                    تحديث: <?= $log['items_updated'] ?>
                                    <?php if ($log['execution_time']): ?>
                                        <br><span class="text-xs text-gray-500">⏱️ <?= $log['execution_time'] ?> ثانية</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm md:text-base">
                                    <?php if ($log['error_message']): ?>
                                        <span class="text-red-600 text-xs" title="<?= htmlspecialchars($log['error_message']) ?>">
                                            <?= htmlspecialchars(mb_substr($log['error_message'], 0, 50)) ?><?= mb_strlen($log['error_message']) > 50 ? '...' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm md:text-base text-gray-600">
                                    <?= date('Y-m-d H:i', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Add/Edit Source -->
    <div id="sourceModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 md:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl md:text-2xl font-bold" id="modalTitle">إضافة مصدر جديد</h2>
                    <button onclick="closeSourceModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <form method="POST" id="sourceForm">
                    <?php echo csrf_input('csrf_token'); ?>
                    <input type="hidden" name="action" id="sourceAction" value="add_source">
                    <input type="hidden" name="source_id" id="sourceId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربي *</label>
                            <input type="text" name="name_ar" id="sourceNameAr" required 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزي</label>
                            <input type="text" name="name_en" id="sourceNameEn" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">نوع المصدر *</label>
                            <select name="source_type" id="sourceType" required 
                                    onchange="toggleSourceFields()"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                                <option value="api">🌐 API</option>
                                <option value="scraping">🕷️ Web Scraping</option>
                                <option value="csv_import">📄 CSV Import</option>
                                <option value="manual">✋ يدوي</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الفئة الافتراضية</label>
                            <select name="category_id" id="sourceCategoryId" 
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                                <option value="">اختر الفئة</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icon'] . ' ' . $cat['name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- API Fields -->
                    <div id="apiFields" class="mb-4">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">رابط API *</label>
                            <input type="url" name="api_url" id="sourceApiUrl" 
                                   placeholder="https://example.com/api/facilities"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">مفتاح API (اختياري)</label>
                            <input type="text" name="api_key" id="sourceApiKey" 
                                   placeholder="your-api-key-here"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                    </div>
                    
                    <!-- Scraping Fields -->
                    <div id="scrapingFields" class="mb-4 hidden">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">رابط الصفحة *</label>
                            <input type="url" name="scraping_url" id="sourceScrapingUrl" 
                                   placeholder="https://example.com/directory"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">CSS Selectors (JSON)</label>
                            <textarea name="scraping_selector" id="sourceScrapingSelector" rows="4" 
                                      placeholder='{"item_selector": "//div[@class=\"item\"]", "fields": {"name_ar": ".//h3", "phone": ".//span[@class=\"phone\"]"}}'
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base font-mono"></textarea>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تكرار التحديث</label>
                            <select name="update_frequency" id="sourceFrequency" 
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base">
                                <option value="hourly">كل ساعة</option>
                                <option value="daily" selected>يومي</option>
                                <option value="weekly">أسبوعي</option>
                                <option value="monthly">شهري</option>
                                <option value="manual">يدوي</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center">
                            <label class="flex items-center">
                                <input type="checkbox" name="auto_import" id="sourceAutoImport" value="1" checked 
                                       class="ml-2">
                                <span class="text-sm md:text-base">استيراد تلقائي</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">إعدادات Mapping (JSON - اختياري)</label>
                        <textarea name="mapping_config" id="sourceMappingConfig" rows="4" 
                                  placeholder='{"data_path": "data.items", "name_ar": "name", "phone": "telephone"}'
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm md:text-base font-mono"></textarea>
                        <p class="text-xs text-gray-500 mt-1">استخدم هذا لتحديد كيفية ربط حقول API مع حقول قاعدة البيانات</p>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeSourceModal()" 
                                class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 text-sm md:text-base">
                            إلغاء
                        </button>
                        <button type="submit" 
                                class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 text-sm md:text-base">
                            💾 حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAddSourceModal() {
            document.getElementById('sourceModal').classList.add('active');
            document.getElementById('modalTitle').textContent = 'إضافة مصدر جديد';
            document.getElementById('sourceForm').reset();
            document.getElementById('sourceAction').value = 'add_source';
            document.getElementById('sourceId').value = '';
            toggleSourceFields();
        }
        
        function editSource(source) {
            document.getElementById('sourceModal').classList.add('active');
            document.getElementById('modalTitle').textContent = 'تعديل مصدر';
            document.getElementById('sourceAction').value = 'edit_source';
            document.getElementById('sourceId').value = source.id;
            document.getElementById('sourceNameAr').value = source.name_ar || '';
            document.getElementById('sourceNameEn').value = source.name_en || '';
            document.getElementById('sourceType').value = source.source_type || 'api';
            document.getElementById('sourceApiUrl').value = source.api_url || '';
            document.getElementById('sourceApiKey').value = source.api_key || '';
            document.getElementById('sourceScrapingUrl').value = source.scraping_url || '';
            document.getElementById('sourceScrapingSelector').value = source.scraping_selector || '';
            document.getElementById('sourceCategoryId').value = source.category_id || '';
            document.getElementById('sourceFrequency').value = source.update_frequency || 'daily';
            document.getElementById('sourceAutoImport').checked = source.auto_import == 1;
            document.getElementById('sourceMappingConfig').value = source.mapping_config || '';
            toggleSourceFields();
        }
        
        function closeSourceModal() {
            document.getElementById('sourceModal').classList.remove('active');
        }
        
        function toggleSourceFields() {
            const sourceType = document.getElementById('sourceType').value;
            const apiFields = document.getElementById('apiFields');
            const scrapingFields = document.getElementById('scrapingFields');
            
            if (sourceType === 'api') {
                apiFields.classList.remove('hidden');
                scrapingFields.classList.add('hidden');
            } else if (sourceType === 'scraping') {
                apiFields.classList.add('hidden');
                scrapingFields.classList.remove('hidden');
            } else {
                apiFields.classList.add('hidden');
                scrapingFields.classList.add('hidden');
            }
        }
        
        function fetchSource(sourceId) {
            if (confirm('هل تريد جلب البيانات من هذا المصدر الآن؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php echo csrf_input('csrf_token'); ?>
                    <input type="hidden" name="action" value="fetch_now">
                    <input type="hidden" name="source_id" value="${sourceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function fetchAllSources() {
            if (confirm('هل تريد جلب البيانات من جميع المصادر النشطة؟ قد تستغرق العملية بعض الوقت.')) {
                // يمكن إضافة AJAX call هنا
                alert('سيتم جلب البيانات من جميع المصادر...');
            }
        }
        
        function toggleSourceActive(id, currentState) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="source_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteSource(id, name) {
            if (confirm('هل أنت متأكد من حذف المصدر: ' + name + '؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php echo csrf_input('csrf_token'); ?>
                    <input type="hidden" name="action" value="delete_source">
                    <input type="hidden" name="source_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('sourceModal');
            if (event.target == modal) {
                closeSourceModal();
            }
        }
    </script>
</body>
</html>

