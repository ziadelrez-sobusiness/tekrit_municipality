<?php
/**
 * صفحة إدارة حسابات المواطنين
 * عرض وإدارة حسابات المواطنين ورموز الدخول الخاصة بهم
 */

session_start();
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// الاتصال بقاعدة البيانات
try {
    $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
}

// معالجة البحث
$search = $_GET['search'] ?? '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition = "WHERE ca.phone LIKE ? OR ca.permanent_access_code LIKE ? OR cr.citizen_name LIKE ?";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

// جلب حسابات المواطنين مع عدد الطلبات
$sql = "
    SELECT 
        ca.id,
        ca.phone,
        ca.permanent_access_code,
        ca.telegram_chat_id,
        ca.created_at,
        COUNT(DISTINCT cr.id) as total_requests,
        MAX(cr.created_at) as last_request_date,
        (SELECT citizen_name FROM citizen_requests WHERE citizen_phone = ca.phone ORDER BY created_at DESC LIMIT 1) as citizen_name
    FROM citizens_accounts ca
    LEFT JOIN citizen_requests cr ON ca.phone = cr.citizen_phone
    $searchCondition
    GROUP BY ca.id
    ORDER BY ca.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($searchParams);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$stats = [
    'total_accounts' => count($accounts),
    'with_telegram' => count(array_filter($accounts, fn($a) => !empty($a['telegram_chat_id']))),
    'with_requests' => count(array_filter($accounts, fn($a) => $a['total_requests'] > 0)),
    'total_requests' => array_sum(array_column($accounts, 'total_requests'))
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة حسابات المواطنين - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- الهيدر -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6 shadow-lg">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black mb-2">👥 إدارة حسابات المواطنين</h1>
                    <p class="text-blue-100">عرض وإدارة حسابات المواطنين ورموز الدخول</p>
                </div>
                <a href="../comprehensive_dashboard.php" 
                   class="bg-white text-blue-600 px-6 py-3 rounded-lg font-bold hover:bg-blue-50 transition">
                    🏠 العودة للوحة التحكم
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-6">
        
        <!-- الإحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">إجمالي الحسابات</p>
                        <p class="text-3xl font-black text-blue-600"><?= $stats['total_accounts'] ?></p>
                    </div>
                    <div class="text-5xl">👥</div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">لديهم طلبات</p>
                        <p class="text-3xl font-black text-green-600"><?= $stats['with_requests'] ?></p>
                    </div>
                    <div class="text-5xl">📋</div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">مربوطة بـ Telegram</p>
                        <p class="text-3xl font-black text-purple-600"><?= $stats['with_telegram'] ?></p>
                    </div>
                    <div class="text-5xl">📱</div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">إجمالي الطلبات</p>
                        <p class="text-3xl font-black text-orange-600"><?= $stats['total_requests'] ?></p>
                    </div>
                    <div class="text-5xl">📋</div>
                </div>
            </div>
        </div>

        <!-- البحث -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <form method="GET" class="flex gap-4">
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="🔍 البحث برقم الهاتف، رمز الدخول، أو اسم المواطن..."
                       class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit" 
                        class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                    🔍 بحث
                </button>
                <?php if (!empty($search)): ?>
                <a href="citizens_accounts.php" 
                   class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg font-bold hover:bg-gray-300 transition">
                    ✖️ إلغاء
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- الجدول -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
                            <th class="px-6 py-4 text-right font-bold">ID</th>
                            <th class="px-6 py-4 text-right font-bold">اسم المواطن</th>
                            <th class="px-6 py-4 text-right font-bold">رقم الهاتف</th>
                            <th class="px-6 py-4 text-right font-bold">رمز الدخول</th>
                            <th class="px-6 py-4 text-right font-bold">Telegram</th>
                            <th class="px-6 py-4 text-right font-bold">عدد الطلبات</th>
                            <th class="px-6 py-4 text-right font-bold">تاريخ الإنشاء</th>
                            <th class="px-6 py-4 text-right font-bold">آخر طلب</th>
                            <th class="px-6 py-4 text-right font-bold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($accounts) > 0): ?>
                            <?php foreach ($accounts as $account): ?>
                            <tr class="border-b hover:bg-blue-50 transition">
                                <td class="px-6 py-4 font-bold"><?= $account['id'] ?></td>
                                <td class="px-6 py-4">
                                    <?= htmlspecialchars($account['citizen_name'] ?? 'غير محدد') ?>
                                </td>
                                <td class="px-6 py-4 font-mono"><?= htmlspecialchars($account['phone']) ?></td>
                                <td class="px-6 py-4">
                                    <code class="bg-blue-100 text-blue-800 px-3 py-1 rounded font-bold">
                                        <?= htmlspecialchars($account['permanent_access_code']) ?>
                                    </code>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($account['telegram_chat_id'])): ?>
                                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-bold">
                                            ✅ مربوط
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm">
                                            ❌ غير مربوط
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full font-bold">
                                        <?= $account['total_requests'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= date('Y-m-d H:i', strtotime($account['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= $account['last_request_date'] ? date('Y-m-d H:i', strtotime($account['last_request_date'])) : 'لا يوجد' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <a href="../public/citizen-dashboard.php?code=<?= urlencode($account['permanent_access_code']) ?>" 
                                           target="_blank"
                                           class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 transition"
                                           title="عرض لوحة المواطن">
                                            👁️
                                        </a>
                                        <button onclick="copyCode('<?= htmlspecialchars($account['permanent_access_code']) ?>')"
                                                class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600 transition"
                                                title="نسخ رمز الدخول">
                                            📋
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                    <div class="text-6xl mb-4">😔</div>
                                    <p class="text-xl font-bold">لا توجد حسابات</p>
                                    <?php if (!empty($search)): ?>
                                        <p class="mt-2">لم يتم العثور على نتائج للبحث: "<?= htmlspecialchars($search) ?>"</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- معلومات إضافية -->
        <div class="mt-8 bg-blue-50 border-2 border-blue-400 rounded-xl p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">💡 معلومات مهمة</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-white rounded-lg p-4">
                    <p class="font-bold text-blue-900 mb-2">🔑 رمز الدخول الثابت:</p>
                    <p class="text-blue-700">
                        يتم إنشاؤه تلقائياً عند أول طلب للمواطن ويبقى ثابتاً لجميع الطلبات اللاحقة.
                    </p>
                </div>
                <div class="bg-white rounded-lg p-4">
                    <p class="font-bold text-blue-900 mb-2">📱 ربط Telegram:</p>
                    <p class="text-blue-700">
                        يتم الربط عندما يرسل المواطن رمز الدخول للبوت @TekritAkkarBot
                    </p>
                </div>
                <div class="bg-white rounded-lg p-4">
                    <p class="font-bold text-blue-900 mb-2">✅ التوثيق:</p>
                    <p class="text-blue-700">
                        يتم التوثيق تلقائياً بعد ربط الحساب بـ Telegram أو عند أول دخول.
                    </p>
                </div>
                <div class="bg-white rounded-lg p-4">
                    <p class="font-bold text-blue-900 mb-2">👁️ عرض لوحة المواطن:</p>
                    <p class="text-blue-700">
                        اضغط على أيقونة العين لعرض لوحة التحكم الخاصة بالمواطن.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert('✅ تم نسخ رمز الدخول: ' + code);
            }).catch(err => {
                alert('❌ فشل النسخ: ' + err);
            });
        }
    </script>
</body>
</html>

