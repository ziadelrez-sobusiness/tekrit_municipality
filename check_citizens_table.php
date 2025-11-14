<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص جدول citizens_accounts</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">فحص جدول citizens_accounts</h1>
        
        <?php
        try {
            $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4'>أعمدة الجدول</h2>";
            echo "<table class='w-full'>";
            echo "<tr class='bg-gray-100'><th class='p-2 text-right'>اسم العمود</th><th class='p-2 text-right'>النوع</th></tr>";
            
            $stmt = $db->query('SHOW COLUMNS FROM citizens_accounts');
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $highlight = ($row['Field'] == 'permanent_access_code') ? 'bg-green-100' : '';
                echo "<tr class='$highlight'><td class='p-2 border'>" . $row['Field'] . "</td><td class='p-2 border'>" . $row['Type'] . "</td></tr>";
            }
            echo "</table>";
            echo "</div>";
            
            echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
            echo "<h2 class='text-2xl font-bold mb-4'>عدد الحسابات</h2>";
            $stmt = $db->query('SELECT COUNT(*) as count FROM citizens_accounts');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p class='text-xl'>عدد الحسابات: <strong>" . $result['count'] . "</strong></p>";
            echo "</div>";
            
            echo "<div class='bg-white rounded-lg shadow p-6'>";
            echo "<h2 class='text-2xl font-bold mb-4'>آخر 5 حسابات</h2>";
            echo "<table class='w-full text-sm'>";
            echo "<tr class='bg-gray-100'><th class='p-2'>ID</th><th class='p-2'>الاسم</th><th class='p-2'>الهاتف</th><th class='p-2'>رمز الدخول</th><th class='p-2'>تاريخ الإنشاء</th></tr>";
            
            $stmt = $db->query('SELECT id, name, phone, permanent_access_code, created_at FROM citizens_accounts ORDER BY id DESC LIMIT 5');
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $code = $row['permanent_access_code'] ?? '<span class="text-red-600 font-bold">NULL</span>';
                echo "<tr>";
                echo "<td class='p-2 border'>" . $row['id'] . "</td>";
                echo "<td class='p-2 border'>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td class='p-2 border'>" . htmlspecialchars($row['phone']) . "</td>";
                echo "<td class='p-2 border'>" . $code . "</td>";
                echo "<td class='p-2 border'>" . $row['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
            
        } catch(Exception $e) {
            echo "<div class='bg-red-100 border-2 border-red-500 rounded-lg p-6'>";
            echo "<h2 class='text-2xl font-bold text-red-800 mb-2'>خطأ!</h2>";
            echo "<p class='text-red-700'>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
        ?>
        
        <div class="mt-6 text-center">
            <a href="migrate_to_telegram.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition inline-block">
                تشغيل سكريبت الترحيل
            </a>
        </div>
    </div>
</body>
</html>
