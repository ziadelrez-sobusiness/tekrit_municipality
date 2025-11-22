<?php
/**
 * صفحة تشخيصية للتحقق من شكاوى المواطن
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("خطأ في الاتصال بقاعدة البيانات");
}

$code = $_GET['code'] ?? '';

if (empty($code)) {
    die("يرجى إدخال رمز الدخول في URL: ?code=TKT-XXXXX");
}

// جلب حساب المواطن
require_once '../includes/CitizenAccountHelper.php';
$accountHelper = new CitizenAccountHelper($db);
$accountResult = $accountHelper->getAccountByAccessCode($code);

if (!$accountResult['success']) {
    die("رمز الدخول غير صحيح: " . ($accountResult['error'] ?? ''));
}

$citizen = $accountResult['account'];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تشخيص شكاوى المواطن</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">🔍 تشخيص شكاوى المواطن</h1>
        
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-bold mb-4">معلومات المواطن</h2>
            <pre class="bg-gray-100 p-4 rounded"><?= print_r($citizen, true) ?></pre>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-bold mb-4">فحص الأعمدة في جدول complaints</h2>
            <?php
            $columnsStmt = $db->query("SHOW COLUMNS FROM complaints");
            $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<pre class='bg-gray-100 p-4 rounded'>";
            print_r($columns);
            echo "</pre>";
            ?>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-bold mb-4">البحث عن الشكاوى</h2>
            <?php
            $originalPhone = $citizen['phone'];
            $cleanPhone = preg_replace('/\s+/', '', $originalPhone);
            $cleanPhone = ltrim($cleanPhone, '0');
            
            echo "<p><strong>Citizen ID:</strong> " . $citizen['id'] . "</p>";
            echo "<p><strong>Phone (original):</strong> " . $originalPhone . "</p>";
            echo "<p><strong>Phone (cleaned):</strong> " . $cleanPhone . "</p>";
            
            // البحث بطرق مختلفة
            $queries = [
                [
                    'name' => 'البحث بـ citizen_id',
                    'sql' => "SELECT * FROM complaints WHERE citizen_id = " . intval($citizen['id'])
                ],
                [
                    'name' => 'البحث بـ citizen_phone (مطابقة تامة)',
                    'sql' => "SELECT * FROM complaints WHERE citizen_phone = ?"
                ],
                [
                    'name' => 'البحث بـ citizen_phone (بدون أصفار ومسافات)',
                    'sql' => "SELECT * FROM complaints WHERE REPLACE(TRIM(LEADING '0' FROM citizen_phone), ' ', '') = ?"
                ]
            ];
            
            // إضافة استعلامات complainant_phone فقط إذا كان العمود موجوداً
            if (in_array('complainant_phone', $columns)) {
                $queries[] = [
                    'name' => 'البحث بـ complainant_phone (مطابقة تامة)',
                    'sql' => "SELECT * FROM complaints WHERE complainant_phone = ?"
                ];
                $queries[] = [
                    'name' => 'البحث بـ complainant_phone (بدون أصفار ومسافات)',
                    'sql' => "SELECT * FROM complaints WHERE REPLACE(TRIM(LEADING '0' FROM complainant_phone), ' ', '') = ?"
                ];
            }
            
            foreach ($queries as $index => $queryInfo) {
                echo "<div class='mt-4 p-4 border rounded'>";
                echo "<h3 class='font-bold mb-2'>" . ($index + 1) . ". " . htmlspecialchars($queryInfo['name']) . "</h3>";
                echo "<code class='text-sm bg-gray-100 p-2 block mb-2'>" . htmlspecialchars($queryInfo['sql']) . "</code>";
                
                try {
                    $stmt = $db->prepare($queryInfo['sql']);
                    
                    // تحديد المعاملات حسب نوع الاستعلام
                    if (strpos($queryInfo['sql'], '?') !== false) {
                        // استعلامات تحتاج معاملات
                        if (strpos($queryInfo['sql'], 'TRIM') !== false) {
                            $stmt->execute([$cleanPhone]);
                        } else {
                            $stmt->execute([$originalPhone]);
                        }
                    } else {
                        // استعلامات بدون معاملات (مثل citizen_id)
                        $stmt->execute();
                    }
                    
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo "<p class='text-green-600 font-bold'>✅ وجد " . count($results) . " شكوى</p>";
                    if (!empty($results)) {
                        echo "<pre class='bg-gray-100 p-2 rounded mt-2 text-xs overflow-auto max-h-64'>";
                        print_r($results);
                        echo "</pre>";
                    }
                } catch (Exception $e) {
                    echo "<p class='text-red-600'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                echo "</div>";
            }
            ?>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-4">جميع الشكاوى في قاعدة البيانات</h2>
            <?php
            try {
                // بناء SELECT clause ديناميكياً حسب الأعمدة الموجودة
                $selectFields = ['id', 'citizen_id', 'citizen_phone', 'subject', 'status', 'created_at'];
                if (in_array('complainant_phone', $columns)) {
                    $selectFields[] = 'complainant_phone';
                }
                if (in_array('complaint_type', $columns)) {
                    $selectFields[] = 'complaint_type';
                }
                if (in_array('category', $columns)) {
                    $selectFields[] = 'category';
                }
                
                $sql = "SELECT " . implode(', ', $selectFields) . " FROM complaints ORDER BY id DESC LIMIT 20";
                $stmt = $db->query($sql);
                $allComplaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<p class='mb-2'><strong>إجمالي الشكاوى (آخر 20):</strong> " . count($allComplaints) . "</p>";
                echo "<pre class='bg-gray-100 p-4 rounded text-xs overflow-auto max-h-96'>";
                print_r($allComplaints);
                echo "</pre>";
            } catch (Exception $e) {
                echo "<p class='text-red-600'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>

