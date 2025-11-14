<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 فحص ربط Telegram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔍 فحص ربط Telegram للمواطنين</h1>
        
        <?php
        require_once 'config/database.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // جلب جميع المواطنين
            $stmt = $db->query("
                SELECT 
                    id,
                    name,
                    phone,
                    permanent_access_code,
                    telegram_chat_id,
                    telegram_username,
                    created_at,
                    (SELECT COUNT(*) FROM citizen_requests WHERE citizen_phone = citizens_accounts.phone) as total_requests
                FROM citizens_accounts
                ORDER BY created_at DESC
            ");
            $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($citizens)) {
                echo '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">';
                echo '<p class="font-bold text-yellow-900">⚠️ لا يوجد مواطنين مسجلين</p>';
                echo '</div>';
                exit;
            }
            
            // إحصائيات
            $totalCitizens = count($citizens);
            $linkedCitizens = count(array_filter($citizens, function($c) { return !empty($c['telegram_chat_id']); }));
            $unlinkedCitizens = $totalCitizens - $linkedCitizens;
            
            echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">';
            
            echo '<div class="bg-blue-50 border-2 border-blue-400 rounded-lg p-6 text-center">';
            echo '<p class="text-4xl font-bold text-blue-900">' . $totalCitizens . '</p>';
            echo '<p class="text-blue-700 font-bold">إجمالي المواطنين</p>';
            echo '</div>';
            
            echo '<div class="bg-green-50 border-2 border-green-400 rounded-lg p-6 text-center">';
            echo '<p class="text-4xl font-bold text-green-900">' . $linkedCitizens . '</p>';
            echo '<p class="text-green-700 font-bold">✅ مربوطين بـ Telegram</p>';
            echo '</div>';
            
            echo '<div class="bg-red-50 border-2 border-red-400 rounded-lg p-6 text-center">';
            echo '<p class="text-4xl font-bold text-red-900">' . $unlinkedCitizens . '</p>';
            echo '<p class="text-red-700 font-bold">❌ غير مربوطين</p>';
            echo '</div>';
            
            echo '</div>';
            
            // جدول المواطنين
            echo '<div class="bg-white rounded-lg shadow overflow-hidden">';
            echo '<table class="w-full">';
            echo '<thead class="bg-gray-800 text-white">';
            echo '<tr>';
            echo '<th class="px-4 py-3 text-right">الاسم</th>';
            echo '<th class="px-4 py-3 text-right">الهاتف</th>';
            echo '<th class="px-4 py-3 text-center">رمز الدخول</th>';
            echo '<th class="px-4 py-3 text-center">Telegram</th>';
            echo '<th class="px-4 py-3 text-center">Chat ID</th>';
            echo '<th class="px-4 py-3 text-center">الطلبات</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($citizens as $citizen) {
                $isLinked = !empty($citizen['telegram_chat_id']);
                $rowClass = $isLinked ? 'bg-green-50' : 'bg-red-50';
                
                echo '<tr class="' . $rowClass . ' border-b border-gray-200">';
                
                // الاسم
                echo '<td class="px-4 py-3">';
                echo '<p class="font-bold text-gray-900">' . htmlspecialchars($citizen['name']) . '</p>';
                echo '<p class="text-xs text-gray-600">' . $citizen['created_at'] . '</p>';
                echo '</td>';
                
                // الهاتف
                echo '<td class="px-4 py-3">';
                echo '<code class="bg-gray-100 px-2 py-1 rounded text-sm">' . htmlspecialchars($citizen['phone']) . '</code>';
                echo '</td>';
                
                // رمز الدخول
                echo '<td class="px-4 py-3 text-center">';
                echo '<code class="bg-blue-100 px-2 py-1 rounded text-xs font-bold">' . htmlspecialchars($citizen['permanent_access_code']) . '</code>';
                echo '</td>';
                
                // حالة Telegram
                echo '<td class="px-4 py-3 text-center">';
                if ($isLinked) {
                    echo '<span class="inline-block bg-green-600 text-white px-3 py-1 rounded-full text-xs font-bold">✅ مربوط</span>';
                    if ($citizen['telegram_username']) {
                        echo '<p class="text-xs text-gray-600 mt-1">@' . htmlspecialchars($citizen['telegram_username']) . '</p>';
                    }
                } else {
                    echo '<span class="inline-block bg-red-600 text-white px-3 py-1 rounded-full text-xs font-bold">❌ غير مربوط</span>';
                }
                echo '</td>';
                
                // Chat ID
                echo '<td class="px-4 py-3 text-center">';
                if ($isLinked) {
                    echo '<code class="bg-gray-100 px-2 py-1 rounded text-xs">' . htmlspecialchars($citizen['telegram_chat_id']) . '</code>';
                } else {
                    echo '<span class="text-gray-400 text-xs">-</span>';
                }
                echo '</td>';
                
                // عدد الطلبات
                echo '<td class="px-4 py-3 text-center">';
                echo '<span class="inline-block bg-blue-600 text-white px-3 py-1 rounded-full text-xs font-bold">' . $citizen['total_requests'] . '</span>';
                echo '</td>';
                
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            
            // تعليمات
            echo '<div class="mt-6 bg-yellow-50 border border-yellow-300 rounded p-4">';
            echo '<h3 class="text-lg font-bold text-yellow-900 mb-2">💡 كيف تعمل الإشعارات التلقائية؟</h3>';
            echo '<ol class="text-yellow-800 text-sm space-y-2 mr-5">';
            echo '<li><strong>1️⃣</strong> عندما يقدم مواطن طلب جديد، النظام يتحقق من رقم هاتفه</li>';
            echo '<li><strong>2️⃣</strong> إذا كان الرقم <strong>مربوط بـ Telegram</strong> (Chat ID موجود)، يرسل إشعار فوري</li>';
            echo '<li><strong>3️⃣</strong> إذا كان الرقم <strong>غير مربوط</strong>، يسجل الرسالة كـ "pending" ولا يرسلها</li>';
            echo '<li><strong>4️⃣</strong> عندما يربط المواطن حسابه لاحقاً، يستلم جميع الرسائل المعلقة</li>';
            echo '</ol>';
            echo '</div>';
            
            echo '<div class="mt-4 bg-blue-50 border border-blue-300 rounded p-4">';
            echo '<h3 class="text-lg font-bold text-blue-900 mb-2">🧪 لاختبار الإشعارات التلقائية:</h3>';
            echo '<ol class="text-blue-800 text-sm space-y-2 mr-5">';
            echo '<li><strong>1️⃣</strong> استخدم رقم هاتف <strong>مربوط بـ Telegram</strong> (مع ✅)</li>';
            echo '<li><strong>2️⃣</strong> قدم طلب جديد بهذا الرقم</li>';
            echo '<li><strong>3️⃣</strong> يجب أن تصلك رسالة فورية على Telegram!</li>';
            echo '</ol>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
            echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="mt-6 text-center space-x-3 space-x-reverse">
            <a href="public/citizen-requests.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                📝 تقديم طلب جديد
            </a>
            <a href="view_error_log.php" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                📋 عرض السجل
            </a>
            <button onclick="location.reload()" class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                🔄 تحديث
            </button>
        </div>
    </div>
</body>
</html>

