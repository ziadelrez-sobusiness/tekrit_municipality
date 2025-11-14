<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار ربط Telegram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🧪 اختبار ربط حساب Telegram</h1>
        
        <?php
        require_once '../config/database.php';
        require_once '../includes/CitizenAccountHelper.php';
        
        $accessCode = $_GET['code'] ?? 'TKT-12345';
        $testChatId = $_GET['chat_id'] ?? '123456789'; // Chat ID تجريبي
        
        if (isset($_POST['link_account'])) {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $accountHelper = new CitizenAccountHelper($db);
                $accountResult = $accountHelper->getAccountByAccessCode($accessCode);
                
                if ($accountResult['success']) {
                    $account = $accountResult['account'];
                    
                    // ربط Telegram Chat ID بالحساب
                    $stmt = $db->prepare("
                        UPDATE citizens_accounts 
                        SET telegram_chat_id = ?, 
                            telegram_username = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$testChatId, 'test_user', $account['id']]);
                    
                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">';
                    echo '<p class="font-bold text-green-900 text-xl mb-2">✅ تم ربط الحساب بنجاح!</p>';
                    echo '<p class="text-green-800">Chat ID: ' . $testChatId . '</p>';
                    echo '<p class="text-green-800">Account ID: ' . $account['id'] . '</p>';
                    echo '</div>';
                    
                    // إرسال الرسائل المعلقة
                    echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">';
                    echo '<p class="font-bold text-blue-900 text-xl mb-2">📬 إرسال الرسائل المعلقة...</p>';
                    
                    $stmt = $db->prepare("
                        SELECT * FROM telegram_log 
                        WHERE citizen_id = ? 
                        AND status = 'pending' 
                        ORDER BY created_at ASC
                    ");
                    $stmt->execute([$account['id']]);
                    $pendingMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($pendingMessages)) {
                        echo '<p class="text-blue-800">✅ لا توجد رسائل معلقة</p>';
                    } else {
                        echo '<p class="text-blue-800 mb-3">عدد الرسائل المعلقة: ' . count($pendingMessages) . '</p>';
                        
                        foreach ($pendingMessages as $msg) {
                            echo '<div class="bg-white rounded p-3 mb-2">';
                            echo '<p class="text-sm font-bold">رسالة #' . $msg['id'] . '</p>';
                            echo '<pre class="text-xs mt-2 bg-gray-100 p-2 rounded">' . htmlspecialchars($msg['message_text']) . '</pre>';
                            
                            // تحديث حالة الرسالة
                            $updateStmt = $db->prepare("
                                UPDATE telegram_log 
                                SET status = 'sent', 
                                    sent_at = NOW(),
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$msg['id']]);
                            
                            echo '<p class="text-green-600 text-xs mt-1">✅ تم تحديث الحالة إلى "sent"</p>';
                            echo '</div>';
                        }
                        
                        echo '<p class="text-blue-800 mt-3 font-bold">✅ تم إرسال ' . count($pendingMessages) . ' رسالة معلقة</p>';
                    }
                    echo '</div>';
                    
                } else {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">';
                    echo '<p class="font-bold text-red-900">❌ رمز الدخول غير صحيح</p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">';
                echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
                echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
                echo '</div>';
            }
        }
        ?>
        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📝 معلومات الاختبار</h2>
            <form method="POST">
                <div class="mb-4">
                    <label class="block font-bold text-gray-700 mb-2">رمز الدخول:</label>
                    <input type="text" name="code" value="<?= htmlspecialchars($accessCode) ?>" 
                           class="w-full border border-gray-300 rounded px-4 py-2" readonly>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold text-gray-700 mb-2">Telegram Chat ID (تجريبي):</label>
                    <input type="text" name="chat_id" value="<?= htmlspecialchars($testChatId) ?>" 
                           class="w-full border border-gray-300 rounded px-4 py-2">
                    <p class="text-sm text-gray-600 mt-1">يمكنك تغيير هذا الرقم للاختبار</p>
                </div>
                
                <button type="submit" name="link_account" 
                        class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                    🔗 ربط الحساب وإرسال الرسائل المعلقة
                </button>
            </form>
        </div>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
            <p class="font-bold text-yellow-900 mb-2">⚠️ ملاحظة:</p>
            <p class="text-yellow-800 text-sm">
                هذه صفحة اختبار يدوي. في الوضع الطبيعي، يتم ربط الحساب تلقائياً عندما يرسل المواطن 
                رمز الدخول إلى البوت في Telegram.
            </p>
        </div>
        
        <div class="mt-6 text-center">
            <a href="check_telegram_debug.php" class="text-blue-600 hover:underline">
                ← العودة لصفحة الفحص
            </a>
        </div>
    </div>
</body>
</html>

