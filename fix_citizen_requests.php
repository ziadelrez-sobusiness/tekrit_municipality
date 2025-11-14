<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>حل مشكلة الطلبات</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-4xl font-bold mb-8 text-center">🔧 حل مشكلة الطلبات</h1>
        
        <?php
        try {
            $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $accessCode = 'TKT-121683E2';
            
            // 1. جلب معلومات الحساب
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-blue-600'>1️⃣ معلومات الحساب</h2>";
            
            $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE permanent_access_code = ?");
            $stmt->execute([$accessCode]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-4 mb-4'>";
                echo "<p class='text-green-800 font-bold mb-3'>✅ الحساب موجود</p>";
                echo "<div class='grid grid-cols-2 gap-3 text-sm'>";
                echo "<p><strong>ID:</strong> " . $account['id'] . "</p>";
                echo "<p><strong>الاسم:</strong> " . htmlspecialchars($account['name']) . "</p>";
                echo "<p><strong>الهاتف:</strong> <span class='text-xl font-bold text-blue-600'>" . htmlspecialchars($account['phone']) . "</span></p>";
                echo "<p><strong>رمز الدخول:</strong> " . htmlspecialchars($account['permanent_access_code']) . "</p>";
                echo "</div>";
                echo "</div>";
                
                $phone = $account['phone'];
                
                // 2. البحث عن الطلبات بهذا الرقم
                echo "<div class='bg-blue-50 border-2 border-blue-400 rounded-lg p-4 mb-4'>";
                echo "<h3 class='text-xl font-bold text-blue-900 mb-3'>2️⃣ البحث عن الطلبات برقم: $phone</h3>";
                
                $stmt = $db->prepare("SELECT * FROM citizen_requests WHERE citizen_phone = ?");
                $stmt->execute([$phone]);
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($requests) > 0) {
                    echo "<p class='text-green-700 font-bold mb-3'>✅ تم العثور على " . count($requests) . " طلب</p>";
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
                    echo "<p class='text-red-700 font-bold'>❌ لا توجد طلبات بهذا الرقم!</p>";
                }
                echo "</div>";
                
                // 3. البحث عن أرقام مشابهة
                echo "<div class='bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4 mb-4'>";
                echo "<h3 class='text-xl font-bold text-yellow-900 mb-3'>3️⃣ البحث عن أرقام مشابهة</h3>";
                
                // إزالة المسافات والأصفار الزائدة
                $phoneClean = preg_replace('/\s+/', '', $phone);
                $phoneClean = ltrim($phoneClean, '0');
                
                $stmt = $db->query("SELECT DISTINCT citizen_phone FROM citizen_requests ORDER BY created_at DESC LIMIT 20");
                $allPhones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<p class='text-yellow-800 mb-3'>أرقام الهواتف في الطلبات:</p>";
                echo "<div class='space-y-1'>";
                foreach ($allPhones as $p) {
                    $phoneInDb = $p['citizen_phone'];
                    $phoneInDbClean = preg_replace('/\s+/', '', $phoneInDb);
                    $phoneInDbClean = ltrim($phoneInDbClean, '0');
                    
                    $match = ($phoneInDbClean == $phoneClean) ? 'bg-green-200 border-2 border-green-500' : 'bg-white';
                    
                    echo "<div class='$match rounded p-2 text-sm'>";
                    echo "<code>" . htmlspecialchars($phoneInDb) . "</code>";
                    
                    if ($phoneInDbClean == $phoneClean) {
                        echo " <span class='text-green-700 font-bold'>← مطابق!</span>";
                        
                        // عد الطلبات
                        $stmt2 = $db->prepare("SELECT COUNT(*) as count FROM citizen_requests WHERE citizen_phone = ?");
                        $stmt2->execute([$phoneInDb]);
                        $count = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];
                        echo " <span class='text-blue-700'>($count طلب)</span>";
                    }
                    echo "</div>";
                }
                echo "</div>";
                echo "</div>";
                
                // 4. الحل
                echo "<div class='bg-gradient-to-r from-green-500 to-blue-500 rounded-lg p-6 text-white'>";
                echo "<h3 class='text-2xl font-bold mb-4'>✅ الحل</h3>";
                
                // البحث عن رقم مطابق
                $matchFound = false;
                $matchingPhone = null;
                
                foreach ($allPhones as $p) {
                    $phoneInDb = $p['citizen_phone'];
                    $phoneInDbClean = preg_replace('/\s+/', '', $phoneInDb);
                    $phoneInDbClean = ltrim($phoneInDbClean, '0');
                    
                    if ($phoneInDbClean == $phoneClean && $phoneInDb != $phone) {
                        $matchFound = true;
                        $matchingPhone = $phoneInDb;
                        break;
                    }
                }
                
                if ($matchFound) {
                    echo "<p class='text-xl mb-4'>تم العثور على رقم مطابق بصيغة مختلفة!</p>";
                    echo "<div class='bg-white bg-opacity-20 rounded-lg p-4 mb-4'>";
                    echo "<p class='mb-2'><strong>الرقم في الحساب:</strong> <code class='bg-white bg-opacity-30 px-2 py-1 rounded'>$phone</code></p>";
                    echo "<p><strong>الرقم في الطلبات:</strong> <code class='bg-white bg-opacity-30 px-2 py-1 rounded'>$matchingPhone</code></p>";
                    echo "</div>";
                    
                    echo "<form method='POST' class='space-y-4'>";
                    echo "<input type='hidden' name='account_id' value='" . $account['id'] . "'>";
                    echo "<input type='hidden' name='new_phone' value='" . htmlspecialchars($matchingPhone) . "'>";
                    echo "<p class='text-lg mb-3'>هل تريد تحديث رقم الهاتف في الحساب ليطابق الطلبات؟</p>";
                    echo "<button type='submit' name='fix_phone' class='bg-white text-green-600 px-8 py-4 rounded-lg font-bold hover:bg-gray-100 transition text-lg'>";
                    echo "🔧 تحديث رقم الهاتف";
                    echo "</button>";
                    echo "</form>";
                } else {
                    echo "<p class='text-xl'>لم يتم العثور على طلبات بهذا الرقم أو برقم مشابه</p>";
                    echo "<p class='mt-3'>قد يكون المواطن لم يقدم أي طلبات بعد، أو الطلبات مسجلة برقم مختلف تماماً</p>";
                }
                
                echo "</div>";
                
            } else {
                echo "<div class='bg-red-50 border-2 border-red-400 rounded-lg p-4'>";
                echo "<p class='text-red-800 font-bold'>❌ الحساب غير موجود!</p>";
                echo "</div>";
            }
            echo "</div>";
            
            // معالجة التحديث
            if (isset($_POST['fix_phone'])) {
                $accountId = $_POST['account_id'];
                $newPhone = $_POST['new_phone'];
                
                $stmt = $db->prepare("UPDATE citizens_accounts SET phone = ? WHERE id = ?");
                $stmt->execute([$newPhone, $accountId]);
                
                echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-6 mb-6'>";
                echo "<h2 class='text-2xl font-bold text-green-800 mb-3'>✅ تم التحديث بنجاح!</h2>";
                echo "<p class='text-green-700 mb-4'>تم تحديث رقم الهاتف في الحساب</p>";
                echo "<a href='public/citizen-dashboard.php?code=$accessCode' class='inline-block bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition'>";
                echo "🔄 تحديث الصفحة وعرض الطلبات";
                echo "</a>";
                echo "</div>";
            }
            
        } catch(Exception $e) {
            echo "<div class='bg-red-100 border-2 border-red-500 rounded-lg p-6'>";
            echo "<h2 class='text-2xl font-bold text-red-800 mb-2'>خطأ!</h2>";
            echo "<p class='text-red-700'>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
        ?>
        
        <div class="mt-8 text-center space-x-4 space-x-reverse">
            <a href="debug_system.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                🔍 فحص شامل
            </a>
            <a href="public/citizen-dashboard.php?code=TKT-121683E2" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                👤 الحساب الشخصي
            </a>
        </div>
    </div>
</body>
</html>

