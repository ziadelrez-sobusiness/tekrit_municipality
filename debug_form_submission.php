<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 فحص تقديم الطلب</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔍 فحص مشكلة تقديم الطلب</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📬 البيانات المستلمة</h2>';
            
            echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">';
            echo '<p class="font-bold text-blue-900 mb-2">POST Data:</p>';
            echo '<pre class="text-xs overflow-x-auto">' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
            echo '</div>';
            
            echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">';
            echo '<p class="font-bold text-green-900 mb-2">FILES Data:</p>';
            echo '<pre class="text-xs overflow-x-auto">' . htmlspecialchars(print_r($_FILES, true)) . '</pre>';
            echo '</div>';
            
            // فحص الحقول المطلوبة
            $required_fields = [
                'citizen_name' => 'الاسم',
                'citizen_phone' => 'الهاتف',
                'request_type_id' => 'نوع الطلب',
                'request_title' => 'عنوان الطلب'
            ];
            
            echo '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">';
            echo '<p class="font-bold text-yellow-900 mb-2">✅ فحص الحقول المطلوبة:</p>';
            echo '<ul class="space-y-1">';
            foreach ($required_fields as $field => $label) {
                $value = $_POST[$field] ?? '';
                $status = !empty($value) ? '✅' : '❌';
                $color = !empty($value) ? 'text-green-800' : 'text-red-800';
                echo "<li class='$color'>$status $label: " . htmlspecialchars($value) . "</li>";
            }
            echo '</ul>';
            echo '</div>';
            
            echo '</div>';
        }
        ?>
        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">🧪 نموذج اختبار مبسط</h2>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block font-bold text-gray-700 mb-2">الاسم:</label>
                    <input type="text" name="citizen_name" value="وسيم الحسن" 
                           class="w-full border border-gray-300 rounded px-4 py-2" required>
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">الهاتف:</label>
                    <input type="text" name="citizen_phone" value="03670065" 
                           class="w-full border border-gray-300 rounded px-4 py-2" required>
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">البريد الإلكتروني:</label>
                    <input type="email" name="citizen_email" value="test@test.com" 
                           class="w-full border border-gray-300 rounded px-4 py-2">
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">العنوان:</label>
                    <input type="text" name="citizen_address" value="تكريت - عكار" 
                           class="w-full border border-gray-300 rounded px-4 py-2">
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">الرقم الوطني:</label>
                    <input type="text" name="national_id" value="" 
                           class="w-full border border-gray-300 rounded px-4 py-2">
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">نوع الطلب:</label>
                    <select name="request_type_id" class="w-full border border-gray-300 rounded px-4 py-2" required>
                        <option value="">اختر نوع الطلب</option>
                        <?php
                        require_once 'config/database.php';
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        $stmt = $db->query("SELECT id, type_name FROM request_types WHERE is_active = 1 ORDER BY type_name");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['type_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">عنوان الطلب:</label>
                    <input type="text" name="request_title" value="طلب اختبار" 
                           class="w-full border border-gray-300 rounded px-4 py-2" required>
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">وصف الطلب:</label>
                    <textarea name="request_description" class="w-full border border-gray-300 rounded px-4 py-2" rows="3">هذا طلب اختبار</textarea>
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">الأولوية:</label>
                    <select name="priority_level" class="w-full border border-gray-300 rounded px-4 py-2">
                        <option value="عادي">عادي</option>
                        <option value="مهم" selected>مهم</option>
                        <option value="عاجل">عاجل</option>
                    </select>
                </div>
                
                <div>
                    <label class="block font-bold text-gray-700 mb-2">ملف مرفق (اختياري):</label>
                    <input type="file" name="documents[]" class="w-full border border-gray-300 rounded px-4 py-2">
                </div>
                
                <div class="flex gap-4">
                    <button type="submit" name="submit_request" 
                            class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                        🧪 اختبار التقديم (Debug)
                    </button>
                    
                    <button type="submit" name="test_only" 
                            class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                        📬 عرض البيانات فقط
                    </button>
                </div>
            </form>
        </div>
        
        <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-yellow-900 mb-4">💡 الأسباب المحتملة</h2>
            
            <div class="space-y-3">
                <div class="bg-white rounded p-3">
                    <p class="font-bold text-red-900 mb-1">1️⃣ JavaScript يمنع الإرسال</p>
                    <p class="text-sm text-red-800">افتح Console في المتصفح (F12) وابحث عن أخطاء</p>
                </div>
                
                <div class="bg-white rounded p-3">
                    <p class="font-bold text-orange-900 mb-1">2️⃣ Validation فاشل</p>
                    <p class="text-sm text-orange-800">تحقق من أن جميع الحقول المطلوبة مملوءة</p>
                </div>
                
                <div class="bg-white rounded p-3">
                    <p class="font-bold text-blue-900 mb-1">3️⃣ حجم الملف كبير</p>
                    <p class="text-sm text-blue-800">تحقق من حجم الملف المرفق (الحد الأقصى عادة 2MB)</p>
                </div>
                
                <div class="bg-white rounded p-3">
                    <p class="font-bold text-purple-900 mb-1">4️⃣ خطأ في قاعدة البيانات</p>
                    <p class="text-sm text-purple-800">تحقق من أن جدول citizen_requests موجود</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">🔧 خطوات الإصلاح</h2>
            
            <ol class="space-y-3 mr-4">
                <li class="flex items-start gap-2">
                    <span class="font-bold">1️⃣</span>
                    <span>افتح الصفحة الأصلية: <a href="public/citizen-requests.php" class="text-blue-600 underline">citizen-requests.php</a></span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">2️⃣</span>
                    <span>اضغط F12 لفتح Developer Tools</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">3️⃣</span>
                    <span>اذهب لتبويب "Console"</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">4️⃣</span>
                    <span>املأ النموذج واضغط "تقديم الطلب"</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">5️⃣</span>
                    <span>ابحث عن أي أخطاء حمراء في Console</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-bold">6️⃣</span>
                    <span>أرسل لي الأخطاء التي تظهر</span>
                </li>
            </ol>
        </div>
        
        <div class="mt-6 text-center">
            <a href="public/citizen-requests.php" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                ← العودة للصفحة الأصلية
            </a>
        </div>
    </div>
</body>
</html>

