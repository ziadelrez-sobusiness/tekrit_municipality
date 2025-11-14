<?php
/**
 * سكريبت الترحيل من WhatsApp إلى Telegram
 * بلدية تكريت - عكار، شمال لبنان
 */

header('Content-Type: text/html; charset=utf-8');

// الاتصال بقاعدة البيانات
$db_host = 'localhost';
$db_name = 'tekrit_municipality';
$db_user = 'root';
$db_pass = '';

try {
    $db = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    die("❌ خطأ في الاتصال: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الترحيل إلى Telegram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <h1 class="text-4xl font-bold text-center text-blue-600 mb-4">
                🔄 الترحيل من WhatsApp إلى Telegram
            </h1>
            <p class="text-center text-gray-600 text-lg">
                بلدية تكريت - عكار، شمال لبنان
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 سجل الترحيل</h2>

            <?php
            $log = [];
            $errors = [];
            
            try {
                // قراءة ملف SQL
                $log[] = ['step' => 'قراءة ملف SQL', 'status' => 'progress'];
                
                // محاولة استخدام ملف الإنشاء الجديد أولاً
                $sqlFile = 'database/create_telegram_system.sql';
                
                if (!file_exists($sqlFile)) {
                    // إذا لم يكن موجود، استخدم ملف الترحيل
                    $sqlFile = 'database/migrate_whatsapp_to_telegram.sql';
                }
                
                if (!file_exists($sqlFile)) {
                    throw new Exception("ملف SQL غير موجود: $sqlFile");
                }
                
                $sql = file_get_contents($sqlFile);
                $log[] = ['step' => 'قراءة ملف SQL', 'status' => 'success', 'details' => strlen($sql) . ' حرف (' . basename($sqlFile) . ')'];
                
                // تقسيم الأوامر
                $log[] = ['step' => 'تحليل الأوامر', 'status' => 'progress'];
                
                // إزالة التعليقات
                $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
                
                // تقسيم حسب DELIMITER
                $parts = preg_split('/DELIMITER\s+(\S+)/i', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
                
                $commands = [];
                $currentDelimiter = ';';
                
                for ($i = 0; $i < count($parts); $i++) {
                    if ($i % 2 == 0) {
                        // هذا جزء من الكود
                        $cmds = array_filter(
                            explode($currentDelimiter, $parts[$i]),
                            function($cmd) {
                                return trim($cmd) !== '';
                            }
                        );
                        $commands = array_merge($commands, $cmds);
                    } else {
                        // هذا delimiter جديد
                        $currentDelimiter = trim($parts[$i]);
                    }
                }
                
                $log[] = ['step' => 'تحليل الأوامر', 'status' => 'success', 'details' => count($commands) . ' أمر'];
                
                // تنفيذ الأوامر
                $log[] = ['step' => 'تنفيذ الأوامر', 'status' => 'progress'];
                
                $successCount = 0;
                $skipCount = 0;
                
                foreach ($commands as $index => $command) {
                    $command = trim($command);
                    if (empty($command)) continue;
                    
                    try {
                        $stmt = $db->prepare($command);
                        $stmt->execute();
                        $stmt->closeCursor(); // إغلاق المؤشر بعد كل استعلام
                        $successCount++;
                        
                        // عرض بعض الأوامر المهمة
                        if (stripos($command, 'RENAME TABLE') !== false ||
                            stripos($command, 'CREATE VIEW') !== false ||
                            stripos($command, 'CREATE PROCEDURE') !== false ||
                            stripos($command, 'ALTER TABLE') !== false) {
                            $shortCmd = substr($command, 0, 100) . '...';
                            $log[] = ['step' => 'تنفيذ', 'status' => 'success', 'details' => $shortCmd];
                        }
                    } catch (PDOException $e) {
                        // تجاهل بعض الأخطاء المتوقعة
                        if (stripos($e->getMessage(), "doesn't exist") !== false ||
                            stripos($e->getMessage(), "already exists") !== false ||
                            stripos($e->getMessage(), "Duplicate") !== false ||
                            stripos($e->getMessage(), "Can't DROP") !== false) {
                            $skipCount++;
                        } else {
                            // محاولة استخدام exec للأوامر البسيطة
                            try {
                                $db->exec($command);
                                $successCount++;
                            } catch (PDOException $e2) {
                                $errors[] = [
                                    'command' => substr($command, 0, 200),
                                    'error' => $e2->getMessage()
                                ];
                            }
                        }
                    }
                }
                
                $log[] = ['step' => 'تنفيذ الأوامر', 'status' => 'success', 
                         'details' => "نجح: $successCount | تم تجاوزه: $skipCount"];
                
                // التحقق من الجداول
                $log[] = ['step' => 'التحقق من الجداول', 'status' => 'progress'];
                
                $tables = ['telegram_log', 'citizens_accounts'];
                foreach ($tables as $table) {
                    $stmt = $db->query("SHOW TABLES LIKE '$table'");
                    $exists = $stmt->fetch();
                    $stmt->closeCursor();
                    
                    if ($exists) {
                        $countStmt = $db->query("SELECT COUNT(*) as count FROM $table");
                        $countData = $countStmt->fetch(PDO::FETCH_ASSOC);
                        $countStmt->closeCursor();
                        $count = $countData['count'];
                        $log[] = ['step' => "جدول $table", 'status' => 'success', 'details' => "$count سجل"];
                    } else {
                        $log[] = ['step' => "جدول $table", 'status' => 'error', 'details' => 'غير موجود'];
                    }
                }
                
                // التحقق من الإعدادات
                $log[] = ['step' => 'التحقق من الإعدادات', 'status' => 'progress'];
                
                $stmt = $db->query("SELECT COUNT(*) as count FROM website_settings WHERE setting_key LIKE 'telegram%'");
                $settingsData = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                $telegramSettings = $settingsData['count'];
                
                if ($telegramSettings > 0) {
                    $log[] = ['step' => 'إعدادات Telegram', 'status' => 'success', 'details' => "$telegramSettings إعداد"];
                } else {
                    $log[] = ['step' => 'إعدادات Telegram', 'status' => 'warning', 'details' => 'لم يتم العثور على إعدادات'];
                }
                
            } catch (Exception $e) {
                $log[] = ['step' => 'خطأ عام', 'status' => 'error', 'details' => $e->getMessage()];
            }
            
            // عرض السجل
            foreach ($log as $entry) {
                $icon = '⏳';
                $color = 'blue';
                
                switch ($entry['status']) {
                    case 'success':
                        $icon = '✅';
                        $color = 'green';
                        break;
                    case 'error':
                        $icon = '❌';
                        $color = 'red';
                        break;
                    case 'warning':
                        $icon = '⚠️';
                        $color = 'yellow';
                        break;
                }
                
                echo "<div class='mb-3 p-4 bg-{$color}-50 border border-{$color}-200 rounded-lg'>";
                echo "<div class='flex items-start gap-3'>";
                echo "<span class='text-2xl'>$icon</span>";
                echo "<div class='flex-1'>";
                echo "<p class='font-bold text-{$color}-900'>{$entry['step']}</p>";
                if (isset($entry['details'])) {
                    echo "<p class='text-sm text-{$color}-700 mt-1'>{$entry['details']}</p>";
                }
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }
            
            // عرض الأخطاء
            if (!empty($errors)) {
                echo "<div class='mt-6 bg-red-50 border-2 border-red-300 rounded-lg p-6'>";
                echo "<h3 class='text-xl font-bold text-red-800 mb-4'>⚠️ أخطاء حدثت:</h3>";
                foreach ($errors as $error) {
                    echo "<div class='mb-3 p-3 bg-white rounded'>";
                    echo "<p class='text-sm text-red-900 font-bold mb-1'>الأمر:</p>";
                    echo "<code class='text-xs text-red-700'>{$error['command']}</code>";
                    echo "<p class='text-sm text-red-900 font-bold mt-2 mb-1'>الخطأ:</p>";
                    echo "<p class='text-xs text-red-700'>{$error['error']}</p>";
                    echo "</div>";
                }
                echo "</div>";
            }
            
            // النتيجة النهائية
            $hasErrors = !empty($errors) || in_array('error', array_column($log, 'status'));
            
            if (!$hasErrors) {
                echo "<div class='mt-8 bg-green-50 border-2 border-green-400 rounded-xl p-8 text-center'>";
                echo "<div class='text-6xl mb-4'>🎉</div>";
                echo "<h2 class='text-3xl font-bold text-green-800 mb-3'>تم الترحيل بنجاح!</h2>";
                echo "<p class='text-green-700 mb-6'>تم استبدال WhatsApp بـ Telegram بنجاح</p>";
                echo "<div class='space-y-3'>";
                echo "<a href='modules/telegram_settings.php' class='inline-block bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition'>⚙️ إعدادات Telegram</a>";
                echo "<a href='modules/telegram_pending_messages.php' class='inline-block bg-green-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-green-700 transition mr-3'>📱 الرسائل المعلقة</a>";
                echo "</div>";
                echo "</div>";
            } else {
                echo "<div class='mt-8 bg-yellow-50 border-2 border-yellow-400 rounded-xl p-8 text-center'>";
                echo "<div class='text-6xl mb-4'>⚠️</div>";
                echo "<h2 class='text-3xl font-bold text-yellow-800 mb-3'>الترحيل غير مكتمل</h2>";
                echo "<p class='text-yellow-700'>يرجى مراجعة الأخطاء أعلاه</p>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
</body>
</html>

