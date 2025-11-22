<?php
/**
 * صفحة تشخيصية للتحقق من الشكاوى
 * بلدية تكريت - عكار
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("خطأ في الاتصال بقاعدة البيانات");
}

$phone = $_GET['phone'] ?? '';
$citizen_id = $_GET['citizen_id'] ?? '';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تشخيص الشكاوى</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">🔍 تشخيص الشكاوى</h1>
        
        <form method="GET" class="mb-6 bg-white p-4 rounded-lg shadow">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block mb-2">رقم الهاتف:</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block mb-2">Citizen ID:</label>
                    <input type="text" name="citizen_id" value="<?= htmlspecialchars($citizen_id) ?>" class="w-full p-2 border rounded">
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">بحث</button>
        </form>
        
        <?php if ($phone || $citizen_id): ?>
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 class="text-xl font-bold mb-4">📊 نتائج البحث</h2>
                
                <?php
                // البحث عن الشكاوى
                $cleanPhone = preg_replace('/\s+/', '', $phone);
                $cleanPhone = ltrim($cleanPhone, '0');
                
                $sql = "
                    SELECT c.*, 
                           COALESCE(c.category, c.complaint_type, 'غير محدد') as category_display
                    FROM complaints c
                    WHERE 1=1
                ";
                
                $params = [];
                if ($citizen_id) {
                    $sql .= " AND (c.citizen_id = ? OR c.citizen_id IS NULL)";
                    $params[] = $citizen_id;
                }
                if ($phone) {
                    $sql .= " AND (
                        c.citizen_phone = ? 
                        OR c.citizen_phone = ?
                        OR REPLACE(LTRIM(c.citizen_phone, '0'), ' ', '') = ?
                        OR c.complainant_phone = ?
                        OR REPLACE(LTRIM(c.complainant_phone, '0'), ' ', '') = ?
                    )";
                    $params = array_merge($params, [$phone, $cleanPhone, $cleanPhone, $phone, $cleanPhone]);
                }
                
                $sql .= " ORDER BY COALESCE(c.created_at, c.date_submitted) DESC LIMIT 50";
                
                try {
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<p class='mb-4'><strong>عدد الشكاوى: " . count($complaints) . "</strong></p>";
                    
                    if (empty($complaints)) {
                        echo "<div class='bg-yellow-50 border border-yellow-400 rounded p-4'>";
                        echo "<p>⚠️ لم يتم العثور على شكاوى</p>";
                        echo "<p class='mt-2 text-sm'>SQL: <code>" . htmlspecialchars($sql) . "</code></p>";
                        echo "<p class='mt-2 text-sm'>Params: <code>" . htmlspecialchars(json_encode($params, JSON_UNESCAPED_UNICODE)) . "</code></p>";
                        echo "</div>";
                    } else {
                        echo "<table class='w-full border-collapse border border-gray-300'>";
                        echo "<thead class='bg-gray-200'>";
                        echo "<tr>";
                        echo "<th class='border border-gray-300 p-2'>ID</th>";
                        echo "<th class='border border-gray-300 p-2'>رقم الشكوى</th>";
                        echo "<th class='border border-gray-300 p-2'>Citizen ID</th>";
                        echo "<th class='border border-gray-300 p-2'>Citizen Phone</th>";
                        echo "<th class='border border-gray-300 p-2'>Complainant Phone</th>";
                        echo "<th class='border border-gray-300 p-2'>الموضوع</th>";
                        echo "<th class='border border-gray-300 p-2'>الحالة</th>";
                        echo "<th class='border border-gray-300 p-2'>التاريخ</th>";
                        echo "</tr>";
                        echo "</thead>";
                        echo "<tbody>";
                        
                        foreach ($complaints as $complaint) {
                            echo "<tr>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars($complaint['id']) . "</td>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars($complaint['complaint_number'] ?? 'N/A') . "</td>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars($complaint['citizen_id'] ?? 'NULL') . "</td>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars($complaint['citizen_phone'] ?? 'N/A') . "</td>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars($complaint['complainant_phone'] ?? 'N/A') . "</td>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars(substr($complaint['subject'] ?? '', 0, 50)) . "</td>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars($complaint['status'] ?? 'N/A') . "</td>";
                            echo "<td class='border border-gray-300 p-2'>" . htmlspecialchars($complaint['created_at'] ?? $complaint['date_submitted'] ?? 'N/A') . "</td>";
                            echo "</tr>";
                        }
                        
                        echo "</tbody>";
                        echo "</table>";
                    }
                } catch (Exception $e) {
                    echo "<div class='bg-red-50 border border-red-400 rounded p-4'>";
                    echo "<p class='text-red-800'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "</div>";
                }
                ?>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4">🔍 معلومات الحساب</h2>
                <?php
                if ($phone) {
                    $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE phone = ?");
                    $stmt->execute([$phone]);
                    $account = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($account) {
                        echo "<pre class='bg-gray-100 p-4 rounded'>";
                        print_r($account);
                        echo "</pre>";
                    } else {
                        echo "<p class='text-yellow-600'>⚠️ لم يتم العثور على حساب بهذا الرقم</p>";
                    }
                }
                
                if ($citizen_id) {
                    $stmt = $db->prepare("SELECT * FROM citizens_accounts WHERE id = ?");
                    $stmt->execute([$citizen_id]);
                    $account = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($account) {
                        echo "<pre class='bg-gray-100 p-4 rounded mt-4'>";
                        print_r($account);
                        echo "</pre>";
                    } else {
                        echo "<p class='text-yellow-600'>⚠️ لم يتم العثور على حساب بهذا ID</p>";
                    }
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

