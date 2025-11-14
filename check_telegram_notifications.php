<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 فحص إشعارات Telegram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔍 فحص إشعارات Telegram</h1>
        
        <?php
        require_once 'config/database.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // 1. آخر الطلبات المقدمة
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📋 آخر 5 طلبات مقدمة</h2>';
            
            $stmt = $db->query("
                SELECT 
                    id,
                    tracking_number,
                    citizen_name,
                    citizen_phone,
                    request_title,
                    created_at
                FROM citizen_requests 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($requests)) {
                echo '<div class="overflow-x-auto">';
                echo '<table class="w-full text-sm">';
                echo '<thead class="bg-gray-100">';
                echo '<tr>';
                echo '<th class="px-4 py-2 text-right">رقم التتبع</th>';
                echo '<th class="px-4 py-2 text-right">الاسم</th>';
                echo '<th class="px-4 py-2 text-right">الهاتف</th>';
                echo '<th class="px-4 py-2 text-right">العنوان</th>';
                echo '<th class="px-4 py-2 text-right">التاريخ</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                foreach ($requests as $req) {
                    echo '<tr class="border-b">';
                    echo '<td class="px-4 py-2">' . htmlspecialchars($req['tracking_number']) . '</td>';
                    echo '<td class="px-4 py-2">' . htmlspecialchars($req['citizen_name']) . '</td>';
                    echo '<td class="px-4 py-2">' . htmlspecialchars($req['citizen_phone']) . '</td>';
                    echo '<td class="px-4 py-2">' . htmlspecialchars($req['request_title']) . '</td>';
                    echo '<td class="px-4 py-2">' . date('Y-m-d H:i', strtotime($req['created_at'])) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            } else {
                echo '<p class="text-gray-600">لا توجد طلبات</p>';
            }
            
            echo '</div>';
            
            // 2. حسابات المواطنين
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">👥 حسابات المواطنين</h2>';
            
            $stmt = $db->query("
                SELECT 
                    id,
                    phone,
                    permanent_access_code,
                    telegram_chat_id,
                    telegram_username,
                    created_at
                FROM citizens_accounts 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($accounts)) {
                echo '<div class="overflow-x-auto">';
                echo '<table class="w-full text-sm">';
                echo '<thead class="bg-gray-100">';
                echo '<tr>';
                echo '<th class="px-4 py-2 text-right">الهاتف</th>';
                echo '<th class="px-4 py-2 text-right">رمز الدخول</th>';
                echo '<th class="px-4 py-2 text-right">Chat ID</th>';
                echo '<th class="px-4 py-2 text-right">الحالة</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                foreach ($accounts as $acc) {
                    $isLinked = !empty($acc['telegram_chat_id']);
                    $statusColor = $isLinked ? 'text-green-600' : 'text-red-600';
                    $statusText = $isLinked ? '✅ مربوط' : '❌ غير مربوط';
                    
                    echo '<tr class="border-b">';
                    echo '<td class="px-4 py-2">' . htmlspecialchars($acc['phone']) . '</td>';
                    echo '<td class="px-4 py-2"><code class="bg-gray-100 px-2 py-1 rounded">' . htmlspecialchars($acc['permanent_access_code']) . '</code></td>';
                    echo '<td class="px-4 py-2">' . ($acc['telegram_chat_id'] ?: '-') . '</td>';
                    echo '<td class="px-4 py-2 ' . $statusColor . ' font-bold">' . $statusText . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            } else {
                echo '<p class="text-gray-600">لا توجد حسابات</p>';
            }
            
            echo '</div>';
            
            // 3. رسائل Telegram
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📬 رسائل Telegram</h2>';
            
            $stmt = $db->query("
                SELECT 
                    tl.*,
                    ca.phone as citizen_phone,
                    ca.permanent_access_code
                FROM telegram_log tl
                LEFT JOIN citizens_accounts ca ON tl.citizen_id = ca.id
                ORDER BY tl.created_at DESC 
                LIMIT 10
            ");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($messages)) {
                echo '<div class="space-y-3">';
                foreach ($messages as $msg) {
                    $statusColor = [
                        'pending' => 'bg-yellow-50 border-yellow-500',
                        'sent' => 'bg-green-50 border-green-500',
                        'failed' => 'bg-red-50 border-red-500'
                    ][$msg['status']] ?? 'bg-gray-50 border-gray-500';
                    
                    $statusIcon = [
                        'pending' => '⏳',
                        'sent' => '✅',
                        'failed' => '❌'
                    ][$msg['status']] ?? '❓';
                    
                    echo '<div class="' . $statusColor . ' border-l-4 rounded p-4">';
                    echo '<div class="flex justify-between items-start mb-2">';
                    echo '<p class="font-bold">' . $statusIcon . ' ' . ucfirst($msg['status']) . '</p>';
                    echo '<p class="text-xs text-gray-600">' . date('Y-m-d H:i', strtotime($msg['created_at'])) . '</p>';
                    echo '</div>';
                    echo '<p class="text-sm mb-1"><strong>هاتف:</strong> ' . ($msg['citizen_phone'] ?? 'غير محدد') . '</p>';
                    echo '<p class="text-sm mb-1"><strong>نوع:</strong> ' . htmlspecialchars($msg['message_type']) . '</p>';
                    echo '<p class="text-xs text-gray-700 mt-2">' . htmlspecialchars(substr($msg['message_text'], 0, 100)) . '...</p>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<p class="text-gray-600">لا توجد رسائل</p>';
            }
            
            echo '</div>';
            
            // 4. التحليل
            echo '<div class="bg-blue-50 border-2 border-blue-400 rounded-lg p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-blue-900 mb-4">📊 التحليل</h2>';
            
            // عدد الرسائل المعلقة
            $stmt = $db->query("SELECT COUNT(*) FROM telegram_log WHERE status = 'pending'");
            $pendingCount = $stmt->fetchColumn();
            
            // عدد الحسابات المربوطة
            $stmt = $db->query("SELECT COUNT(*) FROM citizens_accounts WHERE telegram_chat_id IS NOT NULL AND telegram_chat_id != ''");
            $linkedCount = $stmt->fetchColumn();
            
            // عدد الحسابات الكلي
            $stmt = $db->query("SELECT COUNT(*) FROM citizens_accounts");
            $totalAccounts = $stmt->fetchColumn();
            
            echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
            
            echo '<div class="bg-white rounded p-4">';
            echo '<p class="text-3xl font-bold text-yellow-600">' . $pendingCount . '</p>';
            echo '<p class="text-sm text-gray-700">رسائل معلقة</p>';
            echo '</div>';
            
            echo '<div class="bg-white rounded p-4">';
            echo '<p class="text-3xl font-bold text-green-600">' . $linkedCount . '</p>';
            echo '<p class="text-sm text-gray-700">حسابات مربوطة</p>';
            echo '</div>';
            
            echo '<div class="bg-white rounded p-4">';
            echo '<p class="text-3xl font-bold text-blue-600">' . $totalAccounts . '</p>';
            echo '<p class="text-sm text-gray-700">إجمالي الحسابات</p>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
            
            // 5. الشرح
            echo '<div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6">';
            echo '<h2 class="text-2xl font-bold text-yellow-900 mb-4">💡 لماذا لا تصل الإشعارات؟</h2>';
            
            echo '<div class="space-y-4">';
            
            echo '<div class="bg-white rounded p-4">';
            echo '<p class="font-bold text-red-900 mb-2">❌ المشكلة:</p>';
            echo '<p class="text-sm text-red-800">الإشعارات تُسجل كـ "pending" (معلقة) ولا تُرسل تلقائياً</p>';
            echo '</div>';
            
            echo '<div class="bg-white rounded p-4">';
            echo '<p class="font-bold text-blue-900 mb-2">🔍 السبب:</p>';
            echo '<p class="text-sm text-blue-800 mb-2">المواطن لم يربط حسابه بالبوت بعد (telegram_chat_id = NULL)</p>';
            echo '<p class="text-xs text-blue-700">Telegram لا يسمح بإرسال رسائل للمستخدمين إلا إذا بدأوا المحادثة مع البوت أولاً</p>';
            echo '</div>';
            
            echo '<div class="bg-white rounded p-4">';
            echo '<p class="font-bold text-green-900 mb-2">✅ الحل:</p>';
            echo '<ol class="text-sm text-green-800 space-y-1 mr-4">';
            echo '<li>1. المواطن يفتح Telegram</li>';
            echo '<li>2. يبحث عن @TekritAkkarBot</li>';
            echo '<li>3. يضغط Start</li>';
            echo '<li>4. يرسل رمز الدخول (مثلاً: TKT-ABC123)</li>';
            echo '<li>5. <strong>فوراً</strong> سيتم ربط الحساب وإرسال جميع الرسائل المعلقة!</li>';
            echo '</ol>';
            echo '</div>';
            
            echo '<div class="bg-white rounded p-4">';
            echo '<p class="font-bold text-purple-900 mb-2">🔮 بعد الربط:</p>';
            echo '<p class="text-sm text-purple-800">جميع الإشعارات المستقبلية ستُرسل <strong>تلقائياً وفوراً</strong></p>';
            echo '</div>';
            
            echo '</div>';
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
            <a href="modules/telegram_messages.php" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                📬 رسائل Telegram
            </a>
        </div>
    </div>
</body>
</html>

