<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص البحث برقم الهاتف</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-4xl font-bold mb-8 text-center">🔍 فحص البحث برقم الهاتف</h1>
        
        <?php
        try {
            $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $searchPhone = '03670065';
            
            // 1. البحث المباشر
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-blue-600'>1️⃣ البحث المباشر</h2>";
            echo "<p class='mb-3'>البحث عن: <code class='bg-blue-100 px-2 py-1 rounded font-bold'>$searchPhone</code></p>";
            
            $stmt = $db->prepare("SELECT * FROM citizen_requests WHERE citizen_phone = ?");
            $stmt->execute([$searchPhone]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p class='font-bold mb-3'>النتائج: " . count($results) . " طلب</p>";
            
            if (count($results) > 0) {
                echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-4'>";
                echo "<p class='text-green-800 font-bold mb-2'>✅ تم العثور على طلبات!</p>";
                foreach ($results as $req) {
                    echo "<div class='bg-white rounded p-3 mb-2 text-sm'>";
                    echo "<p><strong>رقم التتبع:</strong> " . htmlspecialchars($req['tracking_number']) . "</p>";
                    echo "<p><strong>العنوان:</strong> " . htmlspecialchars($req['request_title']) . "</p>";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<div class='bg-red-50 border-2 border-red-400 rounded-lg p-4'>";
                echo "<p class='text-red-800 font-bold'>❌ لم يتم العثور على أي طلبات!</p>";
                echo "</div>";
            }
            echo "</div>";
            
            // 2. جميع أرقام الهواتف في الطلبات
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-purple-600'>2️⃣ جميع أرقام الهواتف في الطلبات</h2>";
            
            $stmt = $db->query("SELECT DISTINCT citizen_phone, citizen_name, COUNT(*) as count FROM citizen_requests GROUP BY citizen_phone ORDER BY created_at DESC LIMIT 20");
            $allPhones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<div class='overflow-x-auto'>";
            echo "<table class='w-full text-sm'>";
            echo "<tr class='bg-gray-100'>";
            echo "<th class='p-2 text-right'>رقم الهاتف</th>";
            echo "<th class='p-2 text-right'>الاسم</th>";
            echo "<th class='p-2 text-right'>عدد الطلبات</th>";
            echo "<th class='p-2 text-right'>مطابق؟</th>";
            echo "</tr>";
            
            foreach ($allPhones as $p) {
                $phone = $p['citizen_phone'];
                $isMatch = ($phone === $searchPhone);
                $rowClass = $isMatch ? 'bg-green-100 border-2 border-green-500' : '';
                
                echo "<tr class='$rowClass'>";
                echo "<td class='p-2 border'><code>" . htmlspecialchars($phone) . "</code></td>";
                echo "<td class='p-2 border'>" . htmlspecialchars($p['citizen_name']) . "</td>";
                echo "<td class='p-2 border'>" . $p['count'] . "</td>";
                echo "<td class='p-2 border'>" . ($isMatch ? '✅ نعم' : '❌ لا') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            echo "</div>";
            echo "</div>";
            
            // 3. البحث بدون مسافات وأصفار
            echo "<div class='bg-white rounded-lg shadow-lg p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4 text-orange-600'>3️⃣ البحث الذكي (بدون مسافات/أصفار)</h2>";
            
            $cleanSearch = preg_replace('/\s+/', '', $searchPhone);
            $cleanSearch = ltrim($cleanSearch, '0');
            
            echo "<p class='mb-3'>الرقم المنظف: <code class='bg-orange-100 px-2 py-1 rounded font-bold'>$cleanSearch</code></p>";
            
            $matchingPhones = [];
            foreach ($allPhones as $p) {
                $phone = $p['citizen_phone'];
                $cleanPhone = preg_replace('/\s+/', '', $phone);
                $cleanPhone = ltrim($cleanPhone, '0');
                
                if ($cleanPhone === $cleanSearch) {
                    $matchingPhones[] = [
                        'original' => $phone,
                        'name' => $p['citizen_name'],
                        'count' => $p['count']
                    ];
                }
            }
            
            if (count($matchingPhones) > 0) {
                echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-4'>";
                echo "<p class='text-green-800 font-bold mb-3'>✅ تم العثور على " . count($matchingPhones) . " رقم مطابق!</p>";
                foreach ($matchingPhones as $match) {
                    echo "<div class='bg-white rounded p-3 mb-2'>";
                    echo "<p><strong>الرقم الأصلي:</strong> <code class='bg-green-200 px-2 py-1 rounded'>" . htmlspecialchars($match['original']) . "</code></p>";
                    echo "<p><strong>الاسم:</strong> " . htmlspecialchars($match['name']) . "</p>";
                    echo "<p><strong>عدد الطلبات:</strong> " . $match['count'] . "</p>";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<div class='bg-red-50 border-2 border-red-400 rounded-lg p-4'>";
                echo "<p class='text-red-800 font-bold'>❌ لم يتم العثور على أرقام مطابقة!</p>";
                echo "</div>";
            }
            echo "</div>";
            
            // 4. الحل المقترح
            if (count($matchingPhones) > 0) {
                $firstMatch = $matchingPhones[0]['original'];
                
                echo "<div class='bg-gradient-to-r from-green-500 to-blue-500 rounded-lg p-6 text-white'>";
                echo "<h2 class='text-2xl font-bold mb-4'>✅ الحل المقترح</h2>";
                echo "<p class='text-xl mb-4'>تم العثور على رقم مطابق بصيغة مختلفة!</p>";
                
                echo "<div class='bg-white bg-opacity-20 rounded-lg p-4 mb-4'>";
                echo "<p class='mb-2'><strong>الرقم في الحساب:</strong> <code class='bg-white bg-opacity-30 px-2 py-1 rounded'>$searchPhone</code></p>";
                echo "<p><strong>الرقم في الطلبات:</strong> <code class='bg-white bg-opacity-30 px-2 py-1 rounded'>$firstMatch</code></p>";
                echo "</div>";
                
                echo "<form method='POST' class='space-y-4'>";
                echo "<input type='hidden' name='old_phone' value='" . htmlspecialchars($searchPhone) . "'>";
                echo "<input type='hidden' name='new_phone' value='" . htmlspecialchars($firstMatch) . "'>";
                echo "<p class='text-lg mb-3'>هل تريد تحديث رقم الهاتف في الحساب؟</p>";
                echo "<button type='submit' name='update_phone' class='bg-white text-green-600 px-8 py-4 rounded-lg font-bold hover:bg-gray-100 transition text-lg'>";
                echo "🔧 تحديث رقم الهاتف";
                echo "</button>";
                echo "</form>";
                echo "</div>";
            }
            
            // معالجة التحديث
            if (isset($_POST['update_phone'])) {
                $oldPhone = $_POST['old_phone'];
                $newPhone = $_POST['new_phone'];
                
                $stmt = $db->prepare("UPDATE citizens_accounts SET phone = ? WHERE phone = ?");
                $stmt->execute([$newPhone, $oldPhone]);
                
                echo "<div class='bg-green-50 border-2 border-green-400 rounded-lg p-6 mb-6'>";
                echo "<h2 class='text-2xl font-bold text-green-800 mb-3'>✅ تم التحديث بنجاح!</h2>";
                echo "<p class='text-green-700 mb-4'>تم تحديث رقم الهاتف من <code class='bg-green-200 px-2 py-1 rounded'>$oldPhone</code> إلى <code class='bg-green-200 px-2 py-1 rounded'>$newPhone</code></p>";
                echo "<a href='public/citizen-dashboard.php?code=TKT-121683E2' class='inline-block bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition'>";
                echo "👤 افتح الحساب الشخصي الآن";
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
            <a href="public/citizen-dashboard.php?code=TKT-121683E2" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 transition">
                👤 الحساب الشخصي
            </a>
            <a href="debug_system.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                🔍 فحص شامل
            </a>
        </div>
    </div>
</body>
</html>

