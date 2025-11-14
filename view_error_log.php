<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 عرض سجل الأخطاء</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">📋 سجل أخطاء PHP (Error Log)</h1>
        
        <?php
        // مسارات محتملة لـ error log
        $possiblePaths = [
            'C:/xampp/apache/logs/error.log',
            'C:/xampp/php/logs/php_error_log',
            '../logs/error.log',
            'logs/error.log',
            ini_get('error_log')
        ];
        
        $logContent = null;
        $logPath = null;
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $logPath = $path;
                $logContent = file_get_contents($path);
                break;
            }
        }
        
        if ($logContent) {
            echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">📂 الملف: <code class="text-sm">' . htmlspecialchars($logPath) . '</code></h2>';
            
            // فلترة السطور التي تحتوي على TELEGRAM DEBUG
            $lines = explode("\n", $logContent);
            $telegramLines = [];
            
            foreach ($lines as $line) {
                if (stripos($line, 'TELEGRAM') !== false || stripos($line, 'telegram') !== false) {
                    $telegramLines[] = $line;
                }
            }
            
            if (!empty($telegramLines)) {
                echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">';
                echo '<h3 class="text-xl font-bold text-blue-900 mb-3">🔍 سجلات Telegram فقط (' . count($telegramLines) . ' سطر)</h3>';
                echo '<pre class="text-xs overflow-x-auto bg-gray-900 text-green-400 p-4 rounded">';
                echo htmlspecialchars(implode("\n", array_slice(array_reverse($telegramLines), 0, 50)));
                echo '</pre>';
                echo '</div>';
            } else {
                echo '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-4">';
                echo '<p class="text-yellow-900 font-bold">⚠️ لا توجد سجلات Telegram</p>';
                echo '</div>';
            }
            
            // عرض آخر 100 سطر من السجل الكامل
            echo '<div class="bg-gray-50 border border-gray-300 rounded p-4">';
            echo '<h3 class="text-xl font-bold text-gray-800 mb-3">📜 آخر 100 سطر من السجل الكامل</h3>';
            echo '<pre class="text-xs overflow-x-auto bg-gray-900 text-gray-300 p-4 rounded max-h-96">';
            $lastLines = array_slice($lines, -100);
            echo htmlspecialchars(implode("\n", $lastLines));
            echo '</pre>';
            echo '</div>';
            
            echo '</div>';
            
            // زر لمسح السجل
            if (isset($_POST['clear_log'])) {
                file_put_contents($logPath, '');
                echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">';
                echo '<p class="text-green-900 font-bold">✅ تم مسح السجل بنجاح!</p>';
                echo '</div>';
                echo '<script>setTimeout(function(){ location.reload(); }, 2000);</script>';
            }
            
            echo '<form method="POST" class="text-center">';
            echo '<button type="submit" name="clear_log" class="bg-red-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-red-700 transition">';
            echo '🗑️ مسح السجل';
            echo '</button>';
            echo '</form>';
            
        } else {
            echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">';
            echo '<p class="font-bold text-red-900">❌ لم يتم العثور على ملف السجل</p>';
            echo '<p class="text-red-800 text-sm mt-2">المسارات المحاولة:</p>';
            echo '<ul class="text-red-700 text-xs mt-2 mr-5">';
            foreach ($possiblePaths as $path) {
                echo '<li>' . htmlspecialchars($path) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        ?>
        
        <div class="mt-6 bg-blue-50 border border-blue-300 rounded p-4">
            <h3 class="text-lg font-bold text-blue-900 mb-2">📝 كيفية الاستخدام:</h3>
            <ol class="text-blue-800 text-sm space-y-2 mr-5">
                <li><strong>1️⃣</strong> قدم طلب جديد من صفحة <code>citizen-requests.php</code></li>
                <li><strong>2️⃣</strong> ارجع لهذه الصفحة واضغط F5 لتحديث</li>
                <li><strong>3️⃣</strong> ابحث عن "TELEGRAM DEBUG" في السجل</li>
                <li><strong>4️⃣</strong> شاهد البيانات المرسلة والنتيجة</li>
            </ol>
        </div>
        
        <div class="mt-6 text-center">
            <a href="public/citizen-requests.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                📝 تقديم طلب جديد
            </a>
            <button onclick="location.reload()" class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                🔄 تحديث الصفحة
            </button>
        </div>
    </div>
</body>
</html>

