<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث رموز الدخول</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">🔄 تحديث رموز الدخول القديمة</h1>
        
        <?php
        require_once 'config/database.php';
        require_once 'includes/CitizenAccountHelper.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // الهواتف المطلوب تحديثها
            $phones = ['03670065', '03495685'];
            
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📱 الأرقام المطلوب تحديثها</h2>';
            
            foreach ($phones as $phone) {
                echo '<div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-6 mb-6">';
                
                // جلب البيانات الحالية
                $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE phone = ?");
                $stmt->execute([$phone]);
                $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($citizen) {
                    $oldCode = $citizen['permanent_access_code'];
                    
                    // توليد رمز جديد
                    $accountHelper = new CitizenAccountHelper($db);
                    $reflection = new ReflectionClass($accountHelper);
                    $method = $reflection->getMethod('generateAccessCode');
                    $method->setAccessible(true);
                    $newCode = $method->invoke($accountHelper);
                    
                    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';
                    
                    // المعلومات الأساسية
                    echo '<div>';
                    echo '<p class="font-bold text-gray-800 text-lg mb-2">👤 ' . htmlspecialchars($citizen['name']) . '</p>';
                    echo '<p class="text-gray-600 text-sm">📞 ' . htmlspecialchars($phone) . '</p>';
                    if ($citizen['telegram_username']) {
                        echo '<p class="text-gray-600 text-sm">✈️ @' . htmlspecialchars($citizen['telegram_username']) . '</p>';
                    }
                    echo '</div>';
                    
                    // الرموز
                    echo '<div>';
                    echo '<div class="bg-red-50 border-2 border-red-300 rounded p-3 mb-2">';
                    echo '<p class="text-xs text-red-600 font-bold mb-1">الرمز القديم:</p>';
                    echo '<p class="text-xl font-bold text-red-800" dir="ltr">' . htmlspecialchars($oldCode) . '</p>';
                    echo '</div>';
                    
                    echo '<div class="bg-green-50 border-2 border-green-400 rounded p-3">';
                    echo '<p class="text-xs text-green-600 font-bold mb-1">الرمز الجديد:</p>';
                    echo '<p class="text-2xl font-bold text-green-800" dir="ltr">' . htmlspecialchars($newCode) . '</p>';
                    echo '<p class="text-xs text-gray-600 mt-1">يدخل المواطن فقط الأرقام الخمسة: <code class="bg-green-100 px-2 py-1 rounded font-bold">' . substr($newCode, 4) . '</code></p>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '</div>';
                    
                    // تحديث قاعدة البيانات
                    if (isset($_POST['update_codes'])) {
                        $updateStmt = $db->prepare("UPDATE citizens_accounts SET permanent_access_code = ? WHERE id = ?");
                        $updateStmt->execute([$newCode, $citizen['id']]);
                        
                        echo '<div class="bg-green-100 border-2 border-green-500 rounded p-3 mt-4">';
                        echo '<p class="text-green-900 font-bold text-center">✅ تم التحديث بنجاح!</p>';
                        echo '</div>';
                    }
                    
                } else {
                    echo '<div class="bg-red-50 border-2 border-red-300 rounded p-4">';
                    echo '<p class="text-red-800 font-bold">❌ الرقم ' . htmlspecialchars($phone) . ' غير موجود في قاعدة البيانات</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            echo '</div>';
            
            // زر التحديث
            if (!isset($_POST['update_codes'])) {
                echo '<form method="POST" class="text-center">';
                echo '<button type="submit" name="update_codes" class="bg-blue-600 text-white px-8 py-4 rounded-xl font-bold hover:bg-blue-700 transition text-xl shadow-lg">';
                echo '🔄 تحديث الرموز الآن';
                echo '</button>';
                echo '<p class="text-gray-600 text-sm mt-3">هذا سيقوم بتحديث الرموز في قاعدة البيانات</p>';
                echo '</form>';
            } else {
                echo '<div class="bg-gradient-to-r from-green-500 to-green-700 rounded-xl p-8 text-center text-white shadow-xl">';
                echo '<p class="text-3xl font-bold mb-4">🎉 تم التحديث بنجاح!</p>';
                echo '<p class="text-xl mb-6">يمكنك الآن اختبار الرموز الجديدة</p>';
                
                echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">';
                
                // استرجاع الرموز الجديدة
                foreach ($phones as $phone) {
                    $stmt = $db->prepare("SELECT name, permanent_access_code FROM citizens_accounts WHERE phone = ?");
                    $stmt->execute([$phone]);
                    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($updated) {
                        echo '<div class="bg-white text-gray-800 rounded-lg p-4">';
                        echo '<p class="font-bold mb-2">' . htmlspecialchars($updated['name']) . '</p>';
                        echo '<p class="text-xs text-gray-600 mb-1">الرمز الكامل:</p>';
                        echo '<p class="text-xl font-bold text-green-700 mb-2" dir="ltr">' . htmlspecialchars($updated['permanent_access_code']) . '</p>';
                        echo '<p class="text-xs text-gray-600 mb-1">يدخل المواطن الأرقام الخمسة:</p>';
                        echo '<code class="bg-green-100 px-3 py-2 rounded font-bold text-lg">' . substr($updated['permanent_access_code'], 4) . '</code>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
                
                echo '<div class="mt-6 space-y-3">';
                echo '<a href="public/citizen-requests.php" class="inline-block bg-white text-green-700 px-6 py-3 rounded-lg font-bold hover:bg-green-50 transition mx-2">';
                echo '📝 اختبر في صفحة الطلبات';
                echo '</a>';
                echo '<a href="public/login.php" class="inline-block bg-white text-green-700 px-6 py-3 rounded-lg font-bold hover:bg-green-50 transition mx-2">';
                echo '🔐 اختبر في صفحة الدخول';
                echo '</a>';
                echo '</div>';
                
                echo '</div>';
            }
            
            // معلومات إضافية
            echo '<div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-6 mt-6">';
            echo '<h3 class="font-bold text-yellow-900 mb-3 text-lg">💡 ملاحظات مهمة:</h3>';
            echo '<ul class="text-yellow-800 space-y-2 text-sm">';
            echo '<li><strong>✅ الرموز الجديدة:</strong> أقصر وأسهل (5 أرقام فقط)</li>';
            echo '<li><strong>✅ الإدخال:</strong> المواطن يدخل فقط الأرقام الخمسة بدون -TKT</li>';
            echo '<li><strong>✅ فريد:</strong> النظام يضمن عدم تكرار الرموز</li>';
            echo '<li><strong>✅ الحسابات المربوطة بـ Telegram:</strong> ستبقى مربوطة</li>';
            echo '</ul>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ خطأ:</p>';
            echo '<p class="text-red-700">' . $e->getMessage() . '</p>';
            echo '<pre class="text-xs mt-2 overflow-x-auto">' . $e->getTraceAsString() . '</pre>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>

