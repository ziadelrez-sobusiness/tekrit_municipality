<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة تقديم طلب رخصة البناء
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_permit'])) {
    $applicant_name = trim($_POST['applicant_name']);
    $applicant_phone = trim($_POST['applicant_phone']);
    $applicant_address = trim($_POST['applicant_address']);
    $building_address = trim($_POST['building_address']);
    $building_type = $_POST['building_type'];
    $land_area = floatval($_POST['land_area']);
    $building_area = floatval($_POST['building_area']);
    $floors_count = intval($_POST['floors_count']);
    $construction_purpose = $_POST['construction_purpose'];
    $estimated_cost = floatval($_POST['estimated_cost']);
    $notes = trim($_POST['notes']);
    
    // التحقق من البيانات المطلوبة
    if (empty($applicant_name) || empty($applicant_phone) || empty($building_address) || 
        empty($building_type) || $land_area <= 0 || $building_area <= 0) {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة بشكل صحيح';
    } else {
        try {
            // إعداد بيانات النموذج كـ JSON
            $application_data = json_encode([
                'building_address' => $building_address,
                'building_type' => $building_type,
                'land_area' => $land_area,
                'building_area' => $building_area,
                'floors_count' => $floors_count,
                'construction_purpose' => $construction_purpose,
                'estimated_cost' => $estimated_cost,
                'notes' => $notes
            ], JSON_UNESCAPED_UNICODE);
            
            $query = "INSERT INTO municipal_forms (form_type, applicant_name, applicant_phone, applicant_address, application_data, submission_date) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute(['رخصة بناء', $applicant_name, $applicant_phone, $applicant_address, $application_data, date('Y-m-d')]);
            
            $message = 'تم تقديم طلب رخصة البناء بنجاح! رقم الطلب: ' . $db->lastInsertId();
            
            // إعادة تعيين النموذج
            $_POST = [];
            
        } catch (PDOException $e) {
            $error = 'خطأ في تقديم الطلب: ' . $e->getMessage();
        }
    }
}

// جلب طلبات رخص البناء
try {
    $stmt = $db->query("
        SELECT * FROM municipal_forms 
        WHERE form_type = 'رخصة بناء' 
        ORDER BY submission_date DESC, created_at DESC 
        LIMIT 20
    ");
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $permits = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب رخصة البناء - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">طلب رخصة البناء</h1>
                <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    ← العودة للوحة التحكم
                </a>
            </div>
            <p class="text-slate-600 mt-2">تقديم طلبات رخص البناء الجديدة ومتابعة الطلبات المقدمة</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Building Permit Form -->
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h2 class="text-xl font-semibold mb-6 flex items-center">
                        <span class="bg-blue-100 text-blue-600 p-2 rounded-full mr-3">🏗️</span>
                        نموذج طلب رخصة البناء
                    </h2>
                    
                    <form method="POST" class="space-y-6">
                        <!-- معلومات مقدم الطلب -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-800 mb-4">معلومات مقدم الطلب</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
                                    <input type="text" name="applicant_name" required 
                                           value="<?= htmlspecialchars($_POST['applicant_name'] ?? '') ?>"
                                           class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف *</label>
                                    <input type="tel" name="applicant_phone" required 
                                           value="<?= htmlspecialchars($_POST['applicant_phone'] ?? '') ?>"
                                           placeholder="07xxxxxxxxx"
                                           class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان السكن</label>
                                <textarea name="applicant_address" rows="2" 
                                          class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($_POST['applicant_address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- معلومات البناء -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-800 mb-4">معلومات البناء المراد إنشاؤه</h3>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان البناء (الموقع) *</label>
                                <textarea name="building_address" required rows="2" 
                                          placeholder="العنوان الكامل للموقع المراد البناء عليه"
                                          class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($_POST['building_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع البناء *</label>
                                    <select name="building_type" required 
                                            class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">اختر نوع البناء</option>
                                        <option value="سكني" <?= ($_POST['building_type'] ?? '') === 'سكني' ? 'selected' : '' ?>>سكني</option>
                                        <option value="تجاري" <?= ($_POST['building_type'] ?? '') === 'تجاري' ? 'selected' : '' ?>>تجاري</option>
                                        <option value="صناعي" <?= ($_POST['building_type'] ?? '') === 'صناعي' ? 'selected' : '' ?>>صناعي</option>
                                        <option value="مختلط" <?= ($_POST['building_type'] ?? '') === 'مختلط' ? 'selected' : '' ?>>مختلط (سكني وتجاري)</option>
                                        <option value="مؤسسي" <?= ($_POST['building_type'] ?? '') === 'مؤسسي' ? 'selected' : '' ?>>مؤسسي</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">الغرض من البناء *</label>
                                    <select name="construction_purpose" required 
                                            class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">اختر الغرض</option>
                                        <option value="بناء جديد" <?= ($_POST['construction_purpose'] ?? '') === 'بناء جديد' ? 'selected' : '' ?>>بناء جديد</option>
                                        <option value="توسيع" <?= ($_POST['construction_purpose'] ?? '') === 'توسيع' ? 'selected' : '' ?>>توسيع بناء موجود</option>
                                        <option value="ترميم" <?= ($_POST['construction_purpose'] ?? '') === 'ترميم' ? 'selected' : '' ?>>ترميم وتجديد</option>
                                        <option value="هدم وإعادة بناء" <?= ($_POST['construction_purpose'] ?? '') === 'هدم وإعادة بناء' ? 'selected' : '' ?>>هدم وإعادة بناء</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">مساحة الأرض (م²) *</label>
                                    <input type="number" step="0.01" min="0" name="land_area" required 
                                           value="<?= htmlspecialchars($_POST['land_area'] ?? '') ?>"
                                           class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">مساحة البناء (م²) *</label>
                                    <input type="number" step="0.01" min="0" name="building_area" required 
                                           value="<?= htmlspecialchars($_POST['building_area'] ?? '') ?>"
                                           class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">عدد الطوابق</label>
                                    <input type="number" min="1" max="10" name="floors_count" 
                                           value="<?= htmlspecialchars($_POST['floors_count'] ?? '1') ?>"
                                           class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">التكلفة التقديرية للبناء (ليرة لبنانية)</label>
                                <input type="number" step="1000" min="0" name="estimated_cost" 
                                       value="<?= htmlspecialchars($_POST['estimated_cost'] ?? '') ?>"
                                       placeholder="التكلفة المتوقعة للمشروع"
                                       class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- ملاحظات إضافية -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات أو متطلبات إضافية</label>
                            <textarea name="notes" rows="3" 
                                      placeholder="أي معلومات إضافية تود إضافتها..."
                                      class="w-full p-3 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>

                        <!-- شروط وأحكام -->
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-800 mb-2">إقرار والتزام</h4>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p>• أتعهد بأن جميع المعلومات المقدمة صحيحة ودقيقة</p>
                                <p>• أتعهد بالالتزام بجميع القوانين واللوائح البنائية المحلية</p>
                                <p>• أتعهد بدفع جميع الرسوم المترتبة على هذا الطلب</p>
                                <p>• أوافق على قيام البلدية بالكشف الميداني على الموقع عند الحاجة</p>
                            </div>
                            
                            <div class="mt-3">
                                <label class="flex items-center">
                                    <input type="checkbox" required class="ml-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="text-sm text-blue-800 font-medium">أوافق على جميع الشروط والأحكام المذكورة أعلاه</span>
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex gap-4">
                            <button type="submit" name="submit_permit" 
                                    class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-md hover:bg-blue-700 transition duration-200 font-semibold">
                                تقديم طلب رخصة البناء
                            </button>
                            
                            <button type="reset" 
                                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition duration-200">
                                إعادة تعيين
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Side Panel -->
            <div class="space-y-6">
                <!-- متطلبات الرخصة -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="font-semibold text-gray-800 mb-4">المستندات المطلوبة</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-start">
                            <span class="text-green-500 ml-2">✓</span>
                            نسخة من هوية مقدم الطلب
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 ml-2">✓</span>
                            سند ملكية الأرض أو عقد الإيجار
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 ml-2">✓</span>
                            مخططات معمارية معتمدة
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 ml-2">✓</span>
                            تقرير فحص التربة (للمباني الكبيرة)
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 ml-2">✓</span>
                            موافقة الدفاع المدني (إن وجدت)
                        </li>
                    </ul>
                </div>

                <!-- معلومات الرسوم -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="font-semibold text-gray-800 mb-4">رسوم رخصة البناء</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">رسم الطلب:</span>
                            <span class="font-semibold">50,000 ل.ل</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">رسم المتر المربع:</span>
                            <span class="font-semibold">5,000 ل.ل/م²</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">رسم الكشف الميداني:</span>
                            <span class="font-semibold">25,000 ل.ل</span>
                        </div>
                        <hr class="my-2">
                        <div class="flex justify-between font-semibold text-blue-600">
                            <span>المجموع التقريبي:</span>
                            <span>يُحسب حسب المساحة</span>
                        </div>
                    </div>
                </div>

                <!-- مدة المعالجة -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="font-semibold text-gray-800 mb-4">مدة المعالجة</h3>
                    <div class="space-y-2 text-sm text-gray-600">
                        <p><strong>المراجعة الأولية:</strong> 3-5 أيام عمل</p>
                        <p><strong>الكشف الميداني:</strong> 7-10 أيام عمل</p>
                        <p><strong>الموافقة النهائية:</strong> 15-30 يوم عمل</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- طلبات رخص البناء الأخيرة -->
        <div class="bg-white rounded-lg shadow-sm mt-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">طلبات رخص البناء الأخيرة</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">رقم الطلب</th>
                            <th class="px-6 py-3">اسم مقدم الطلب</th>
                            <th class="px-6 py-3">نوع البناء</th>
                            <th class="px-6 py-3">مساحة البناء</th>
                            <th class="px-6 py-3">تاريخ التقديم</th>
                            <th class="px-6 py-3">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permits as $permit): ?>
                            <?php $data = json_decode($permit['application_data'], true); ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">#<?= $permit['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($permit['applicant_name']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($data['building_type'] ?? '-') ?></td>
                                <td class="px-6 py-4"><?= number_format($data['building_area'] ?? 0) ?> م²</td>
                                <td class="px-6 py-4"><?= date('Y-m-d', strtotime($permit['submission_date'])) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded 
                                        <?= $permit['status'] === 'مقدم' ? 'bg-blue-100 text-blue-800' : 
                                           ($permit['status'] === 'قيد المراجعة' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($permit['status'] === 'موافق عليه' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= htmlspecialchars($permit['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($permits)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-gray-500">
                                    لم يتم تقديم أي طلبات رخص بناء بعد
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 
