<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص شامل للنظام</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .code { background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-family: monospace; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-4xl font-bold mb-8 text-center">🔍 فحص شامل للنظام</h1>
        
        <?php
        try {
            $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 1. فحص الجداول
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-blue-600'>1️⃣ الجداول الموجودة</h2>";
            
            $tables = ['citizens_accounts', 'citizen_requests', 'telegram_log'];
            foreach ($tables as $table) {
                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->fetch();
                
                if ($exists) {
                    echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-4 mb-3'>";
                    echo "<p class='text-green-800 font-bold'>✅ $table موجود</p>";
                    
                    // عدد السجلات
                    $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo "<p class='text-green-700 text-sm'>عدد السجلات: " . $result['count'] . "</p>";
                    echo "</div>";
                } else {
                    echo "<div class='bg-red-50 border-2 border-red-400 rounded-lg p-4 mb-3'>";
                    echo "<p class='text-red-800 font-bold'>❌ $table غير موجود!</p>";
                    echo "</div>";
                }
            }
            echo "</div>";
            
            // 2. فحص أعمدة citizens_accounts
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-purple-600'>2️⃣ أعمدة citizens_accounts</h2>";
            
            $stmt = $db->query('SHOW COLUMNS FROM citizens_accounts');
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $requiredColumns = ['permanent_access_code', 'telegram_chat_id', 'telegram_username'];
            
            echo "<div class='grid grid-cols-1 md:grid-cols-3 gap-3'>";
            foreach ($requiredColumns as $col) {
                $found = false;
                foreach ($columns as $column) {
                    if ($column['Field'] == $col) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-3'>";
                    echo "<p class='text-green-800 font-bold text-sm'>✅ $col</p>";
                    echo "</div>";
                } else {
                    echo "<div class='bg-red-50 border-2 border-red-400 rounded-lg p-3'>";
                    echo "<p class='text-red-800 font-bold text-sm'>❌ $col مفقود!</p>";
                    echo "</div>";
                }
            }
            echo "</div>";
            echo "</div>";
            
            // 3. فحص الحساب المحدد
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-green-600'>3️⃣ فحص الحساب: TKT-121683E2</h2>";
            
            $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE permanent_access_code = ?");
            $stmt->execute(['TKT-121683E2']);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-4 mb-4'>";
                echo "<p class='text-green-800 font-bold mb-3'>✅ الحساب موجود</p>";
                echo "<div class='grid grid-cols-2 gap-2 text-sm'>";
                echo "<p class='text-gray-700'><strong>ID:</strong> " . $account['id'] . "</p>";
                echo "<p class='text-gray-700'><strong>الاسم:</strong> " . htmlspecialchars($account['name']) . "</p>";
                echo "<p class='text-gray-700'><strong>الهاتف:</strong> " . htmlspecialchars($account['phone']) . "</p>";
                echo "<p class='text-gray-700'><strong>رمز الدخول:</strong> " . htmlspecialchars($account['permanent_access_code']) . "</p>";
                echo "</div>";
                echo "</div>";
                
                // البحث عن الطلبات
                $phone = $account['phone'];
                $stmt = $db->prepare("SELECT * FROM citizen_requests WHERE citizen_phone = ?");
                $stmt->execute([$phone]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<div class='bg-blue-50 border-2 border-blue-400 rounded-lg p-4'>";
                echo "<p class='text-blue-800 font-bold mb-3'>📋 الطلبات المرتبطة برقم الهاتف: $phone</p>";
                
                if (count($requests) > 0) {
                    echo "<p class='text-blue-700 mb-2'>عدد الطلبات: " . count($requests) . "</p>";
                    echo "<div class='space-y-2'>";
                    foreach ($requests as $req) {
                        echo "<div class='bg-white rounded p-3 text-sm'>";
                        echo "<p><strong>رقم التتبع:</strong> " . htmlspecialchars($req['tracking_number']) . "</p>";
                        echo "<p><strong>العنوان:</strong> " . htmlspecialchars($req['request_title']) . "</p>";
                        echo "<p><strong>الحالة:</strong> " . htmlspecialchars($req['status']) . "</p>";
                        echo "<p><strong>التاريخ:</strong> " . $req['created_at'] . "</p>";
                        echo "</div>";
                    }
                    echo "</div>";
                } else {
                    echo "<p class='text-red-700 font-bold'>❌ لا توجد طلبات مرتبطة بهذا الرقم!</p>";
                }
                echo "</div>";
                
            } else {
                echo "<div class='bg-red-50 border-2 border-red-400 rounded-lg p-4'>";
                echo "<p class='text-red-800 font-bold'>❌ الحساب غير موجود!</p>";
                echo "</div>";
            }
            echo "</div>";
            
            // 4. فحص رسائل Telegram
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-indigo-600'>4️⃣ رسائل Telegram</h2>";
            
            // التحقق من وجود الجدول
            $stmt = $db->query("SHOW TABLES LIKE 'telegram_log'");
            $tableExists = $stmt->fetch();
            
            if ($tableExists) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM telegram_log");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "<div class='bg-blue-50 border-2 border-blue-400 rounded-lg p-4 mb-3'>";
                echo "<p class='text-blue-800 font-bold'>✅ جدول telegram_log موجود</p>";
                echo "<p class='text-blue-700'>عدد الرسائل: " . $result['count'] . "</p>";
                echo "</div>";
                
                if ($result['count'] > 0) {
                    // آخر 5 رسائل
                    $stmt = $db->query("SELECT * FROM telegram_log ORDER BY id DESC LIMIT 5");
                    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<div class='space-y-2'>";
                    foreach ($messages as $msg) {
                        echo "<div class='bg-gray-50 rounded p-3 text-sm'>";
                        echo "<p><strong>ID:</strong> " . $msg['id'] . "</p>";
                        echo "<p><strong>Citizen ID:</strong> " . ($msg['citizen_id'] ?? 'NULL') . "</p>";
                        echo "<p><strong>Chat ID:</strong> " . htmlspecialchars($msg['telegram_chat_id']) . "</p>";
                        echo "<p><strong>الحالة:</strong> <span class='font-bold'>" . $msg['status'] . "</span></p>";
                        echo "<p><strong>التاريخ:</strong> " . $msg['created_at'] . "</p>";
                        echo "</div>";
                    }
                    echo "</div>";
                }
            } else {
                echo "<div class='bg-red-50 border-2 border-red-400 rounded-lg p-4'>";
                echo "<p class='text-red-800 font-bold'>❌ جدول telegram_log غير موجود!</p>";
                echo "<p class='text-red-700 text-sm mt-2'>يجب تشغيل سكريبت الترحيل</p>";
                echo "</div>";
            }
            echo "</div>";
            
            // 5. فحص آخر 10 طلبات
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-orange-600'>5️⃣ آخر 10 طلبات في النظام</h2>";
            
            $stmt = $db->query("SELECT cr.*, ca.permanent_access_code 
                                FROM citizen_requests cr 
                                LEFT JOIN citizens_accounts ca ON cr.citizen_phone = ca.phone 
                                ORDER BY cr.id DESC LIMIT 10");
            $allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($allRequests) > 0) {
                echo "<div class='overflow-x-auto'>";
                echo "<table class='w-full text-sm'>";
                echo "<tr class='bg-gray-100'>";
                echo "<th class='p-2 text-right'>رقم التتبع</th>";
                echo "<th class='p-2 text-right'>اسم المواطن</th>";
                echo "<th class='p-2 text-right'>الهاتف</th>";
                echo "<th class='p-2 text-right'>رمز الدخول</th>";
                echo "<th class='p-2 text-right'>الحالة</th>";
                echo "</tr>";
                
                foreach ($allRequests as $req) {
                    $codeDisplay = $req['permanent_access_code'] ?? '<span class="text-red-600 font-bold">لا يوجد</span>';
                    echo "<tr class='border-b'>";
                    echo "<td class='p-2'>" . htmlspecialchars($req['tracking_number']) . "</td>";
                    echo "<td class='p-2'>" . htmlspecialchars($req['citizen_name']) . "</td>";
                    echo "<td class='p-2'>" . htmlspecialchars($req['citizen_phone']) . "</td>";
                    echo "<td class='p-2'>" . $codeDisplay . "</td>";
                    echo "<td class='p-2'>" . htmlspecialchars($req['status']) . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                echo "</div>";
            }
            echo "</div>";
            
            // 6. التوصيات
            echo "<div class='bg-gradient-to-r from-red-500 to-orange-500 rounded-lg shadow-lg p-6 text-white'>";
            echo "<h2 class='text-2xl font-bold mb-4'>⚠️ التوصيات</h2>";
            
            $recommendations = [];
            
            // التحقق من العمود
            $hasAccessCode = false;
            foreach ($columns as $column) {
                if ($column['Field'] == 'permanent_access_code') {
                    $hasAccessCode = true;
                    break;
                }
            }
            
            if (!$hasAccessCode) {
                $recommendations[] = "❌ عمود permanent_access_code مفقود - يجب تشغيل سكريبت الترحيل";
            }
            
            if (!$tableExists) {
                $recommendations[] = "❌ جدول telegram_log مفقود - يجب تشغيل سكريبت الترحيل";
            }
            
            if (count($recommendations) > 0) {
                echo "<ul class='space-y-2'>";
                foreach ($recommendations as $rec) {
                    echo "<li class='text-lg'>$rec</li>";
                }
                echo "</ul>";
                
                echo "<div class='mt-6'>";
                echo "<a href='migrate_to_telegram.php' class='inline-block bg-white text-red-600 px-8 py-4 rounded-lg font-bold hover:bg-gray-100 transition text-lg'>";
                echo "🚀 تشغيل سكريبت الترحيل الآن";
                echo "</a>";
                echo "</div>";
            } else {
                echo "<p class='text-xl'>✅ جميع الجداول والأعمدة موجودة!</p>";
            }
            
            echo "</div>";
            
        } catch(Exception $e) {
            echo "<div class='bg-red-100 border-2 border-red-500 rounded-lg p-6'>";
            echo "<h2 class='text-2xl font-bold text-red-800 mb-2'>خطأ!</h2>";
            echo "<p class='text-red-700'>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
        ?>
        
        <div class="mt-8 text-center">
            <a href="SOLVE_THREE_PROBLEMS.html" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition inline-block">
                📖 دليل حل المشاكل
            </a>
        </div>
    </div>
</body>
</html>

