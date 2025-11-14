<?php
/**
 * تثبيت Stored Procedures لنظام الحساب الشخصي للمواطن
 * بلدية تكريت - عكار، شمال لبنان
 */

header('Content-Type: text/html; charset=utf-8');

// إعدادات قاعدة البيانات
$db_host = "localhost";
$db_name = "tekrit_municipality";
$db_user = "root";
$db_pass = "";

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت Stored Procedures</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .step { margin-bottom: 1rem; padding: 1rem; border-radius: 0.5rem; }
        .step.success { background-color: #d1fae5; border: 2px solid #10b981; }
        .step.error { background-color: #fee2e2; border: 2px solid #ef4444; }
        .step.warning { background-color: #fef3c7; border: 2px solid #f59e0b; }
        .step.info { background-color: #dbeafe; border: 2px solid #3b82f6; }
        pre { background: #1f2937; color: #f3f4f6; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; }
    </style>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">
                🔧 تثبيت Stored Procedures
            </h1>
            <p class="text-center text-gray-600 mb-6">بلدية تكريت - عكار، شمال لبنان</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 سجل التثبيت</h2>

<?php

try {
    // الاتصال بقاعدة البيانات
    echo '<div class="step info">';
    echo '<h3 class="font-bold text-lg mb-2">🔌 الخطوة 1: الاتصال بقاعدة البيانات</h3>';
    
    $db = new PDO(
        "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
        $db_user,
        $db_pass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        )
    );
    
    echo '<p class="text-green-600">✅ تم الاتصال بقاعدة البيانات بنجاح</p>';
    echo '</div>';
    
    // قراءة ملف SQL
    echo '<div class="step info">';
    echo '<h3 class="font-bold text-lg mb-2">📄 الخطوة 2: قراءة ملف Stored Procedures</h3>';
    
    $sql_file = __DIR__ . '/database/stored_procedures_simple.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception('ملف SQL غير موجود: ' . $sql_file);
    }
    
    $sql_content = file_get_contents($sql_file);
    echo '<p class="text-green-600">✅ تم قراءة الملف بنجاح (' . number_format(strlen($sql_content)) . ' حرف)</p>';
    echo '</div>';
    
    // تنفيذ الـ Procedures
    echo '<div class="step info">';
    echo '<h3 class="font-bold text-lg mb-2">⚙️ الخطوة 3: تنفيذ Stored Procedures</h3>';
    
    // تقسيم الملف إلى procedures منفصلة
    $procedures = [];
    $current_proc = '';
    $in_procedure = false;
    
    $lines = explode("\n", $sql_content);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // تجاهل التعليقات والأسطر الفارغة
        if (empty($trimmed) || substr($trimmed, 0, 2) === '--') {
            continue;
        }
        
        // بداية procedure جديد
        if (stripos($trimmed, 'CREATE PROCEDURE') !== false) {
            if (!empty($current_proc)) {
                $procedures[] = $current_proc;
            }
            $current_proc = $line . "\n";
            $in_procedure = true;
            continue;
        }
        
        // بداية DROP PROCEDURE
        if (stripos($trimmed, 'DROP PROCEDURE') !== false) {
            if (!empty($current_proc)) {
                $procedures[] = $current_proc;
            }
            $current_proc = $line . "\n";
            $in_procedure = false;
            continue;
        }
        
        // إضافة السطر للـ procedure الحالي
        if ($in_procedure || !empty($current_proc)) {
            $current_proc .= $line . "\n";
            
            // نهاية الـ procedure
            if (trim($line) === 'END;') {
                $procedures[] = $current_proc;
                $current_proc = '';
                $in_procedure = false;
            }
        }
    }
    
    // إضافة آخر procedure
    if (!empty($current_proc)) {
        $procedures[] = $current_proc;
    }
    
    echo '<p class="text-blue-600">📊 تم العثور على ' . count($procedures) . ' أمر SQL</p>';
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($procedures as $index => $procedure) {
        $proc_name = 'غير معروف';
        
        // استخراج اسم الـ procedure
        if (preg_match('/(?:CREATE|DROP)\s+PROCEDURE\s+(?:IF\s+EXISTS\s+)?(\w+)/i', $procedure, $matches)) {
            $proc_name = $matches[1];
        }
        
        try {
            $db->exec($procedure);
            echo '<p class="text-green-600 text-sm">✅ ' . htmlspecialchars($proc_name) . '</p>';
            $success_count++;
        } catch (PDOException $e) {
            echo '<p class="text-red-600 text-sm">❌ ' . htmlspecialchars($proc_name) . ': ' . htmlspecialchars($e->getMessage()) . '</p>';
            $errors[] = [
                'procedure' => $proc_name,
                'error' => $e->getMessage(),
                'sql' => substr($procedure, 0, 200)
            ];
            $error_count++;
        }
    }
    
    echo '<p class="mt-4 font-bold">';
    echo '✅ نجح: ' . $success_count . ' | ';
    echo '❌ فشل: ' . $error_count;
    echo '</p>';
    echo '</div>';
    
    // التحقق من الـ Procedures
    echo '<div class="step info">';
    echo '<h3 class="font-bold text-lg mb-2">🔍 الخطوة 4: التحقق من Stored Procedures</h3>';
    
    $required_procedures = [
        'sp_get_or_create_citizen_account' => 'إنشاء/جلب حساب مواطن',
        'sp_cleanup_expired_links' => 'تنظيف الروابط المنتهية',
        'sp_get_citizen_stats' => 'إحصائيات المواطن',
        'sp_create_magic_link' => 'إنشاء رابط سحري',
        'sp_validate_magic_link' => 'التحقق من الرابط السحري'
    ];
    
    $all_exist = true;
    
    foreach ($required_procedures as $proc => $description) {
        $stmt = $db->query("SHOW PROCEDURE STATUS WHERE Db = '$db_name' AND Name = '$proc'");
        $result = $stmt->fetch();
        $stmt->closeCursor();
        
        if ($result) {
            echo '<p class="text-green-600">✅ ' . htmlspecialchars($description) . ' (<code>' . $proc . '</code>)</p>';
        } else {
            echo '<p class="text-red-600">❌ ' . htmlspecialchars($description) . ' (<code>' . $proc . '</code>) غير موجود</p>';
            $all_exist = false;
        }
    }
    
    echo '</div>';
    
    // النتيجة النهائية
    if ($all_exist && $error_count === 0) {
        echo '<div class="step success">';
        echo '<h3 class="font-bold text-lg mb-2">🎉 تم التثبيت بنجاح!</h3>';
        echo '<p class="text-green-700 mb-4">تم إنشاء جميع الـ Stored Procedures المطلوبة.</p>';
        echo '<div class="bg-white rounded p-4 border border-green-300">';
        echo '<p class="font-bold mb-2">الـ Procedures المتاحة الآن:</p>';
        echo '<ul class="list-disc list-inside space-y-1 text-sm">';
        foreach ($required_procedures as $proc => $description) {
            echo '<li><strong>' . htmlspecialchars($description) . '</strong>: <code class="text-xs bg-gray-100 px-2 py-1 rounded">' . $proc . '</code></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="step warning">';
        echo '<h3 class="font-bold text-lg mb-2">⚠️ التثبيت غير مكتمل</h3>';
        echo '<p class="text-yellow-700">بعض الـ Procedures لم يتم إنشاؤها بنجاح.</p>';
        
        if (!empty($errors)) {
            echo '<div class="mt-4">';
            echo '<p class="font-bold mb-2">تفاصيل الأخطاء:</p>';
            foreach ($errors as $error) {
                echo '<div class="bg-red-50 p-3 rounded mb-2">';
                echo '<p class="font-bold text-red-800">' . htmlspecialchars($error['procedure']) . '</p>';
                echo '<p class="text-sm text-red-600">' . htmlspecialchars($error['error']) . '</p>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    
    // اختبار سريع
    if ($all_exist) {
        echo '<div class="step info">';
        echo '<h3 class="font-bold text-lg mb-2">🧪 الخطوة 5: اختبار سريع</h3>';
        
        try {
            // اختبار sp_cleanup_expired_links
            $stmt = $db->query("CALL sp_cleanup_expired_links()");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            echo '<p class="text-green-600">✅ تم اختبار <code>sp_cleanup_expired_links</code> بنجاح</p>';
            echo '<p class="text-sm text-gray-600 mr-6">تم حذف ' . $result['deleted_magic_links'] . ' رابط منتهي و ' . $result['deleted_sessions'] . ' جلسة منتهية</p>';
        } catch (Exception $e) {
            echo '<p class="text-yellow-600">⚠️ خطأ في الاختبار: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="step error">';
    echo '<h3 class="font-bold text-lg mb-2">❌ خطأ فادح</h3>';
    echo '<p class="text-red-700">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}

?>

            <div class="mt-6 flex gap-4">
                <a href="setup_citizen_accounts_system.php" class="flex-1 bg-blue-600 text-white text-center py-3 rounded-lg hover:bg-blue-700 transition">
                    🔄 إعادة تشغيل التثبيت الكامل
                </a>
                <a href="comprehensive_dashboard.php" class="flex-1 bg-green-600 text-white text-center py-3 rounded-lg hover:bg-green-700 transition">
                    🏠 لوحة التحكم
                </a>
            </div>
        </div>

        <div class="mt-6 text-center text-sm text-gray-600">
            <p>🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
        </div>
    </div>
</body>
</html>

