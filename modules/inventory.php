<?php
// تحميل أنظمة الأمان
if (file_exists(__DIR__ . '/../includes/auto_security.php')) {
    require_once __DIR__ . '/../includes/auto_security.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");

$user = $auth->getUserInfo();
$message = '';
$error = '';

// معالجة إضافة صنف جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    try {
        $item_code = trim($_POST['item_code']);
        $item_name = trim($_POST['item_name']);
        $category = trim($_POST['category']);
        $unit = trim($_POST['unit']);
        $minimum_stock = intval($_POST['minimum_stock']);
        $current_stock = intval($_POST['current_stock']);
        $unit_price = floatval($_POST['unit_price']);
        $currency_id = intval($_POST['currency_id']);
        $location = trim($_POST['location']);
        $notes = trim($_POST['notes']);

        $stmt = $db->prepare("INSERT INTO inventory_items (item_code, item_name, category, unit, minimum_stock, current_stock, unit_price, currency_id, location, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_code, $item_name, $category, $unit, $minimum_stock, $current_stock, $unit_price, $currency_id, $location, $notes, $user['id']]);

        $message = 'تم إضافة الصنف بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في إضافة الصنف: ' . $e->getMessage();
    }
}

// معالجة تحديث المخزون
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    try {
        $item_id = intval($_POST['item_id']);
        $quantity = intval($_POST['quantity']);
        $movement_type = $_POST['movement_type']; // إضافة / سحب
        $notes = trim($_POST['notes']);

        $db->beginTransaction();

        // تحديث الكمية
        if ($movement_type === 'إضافة') {
            $stmt = $db->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?");
        }
        $stmt->execute([$quantity, $item_id]);

        // تسجيل الحركة
        $stmt = $db->prepare("INSERT INTO inventory_movements (item_id, movement_type, quantity, notes, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$item_id, $movement_type, $quantity, $notes, $user['id']]);

        $db->commit();
        $message = 'تم تحديث المخزون بنجاح!';
    } catch (PDOException $e) {
        $db->rollBack();
        $error = 'خطأ في تحديث المخزون: ' . $e->getMessage();
    }
}

// جلب الأصناف
$items = [];
try {
    $stmt = $db->query("
        SELECT ii.*, c.currency_symbol, c.currency_code,
               (ii.current_stock * ii.unit_price) as total_value,
               CASE
                   WHEN ii.current_stock <= ii.minimum_stock THEN 'تحذير'
                   WHEN ii.current_stock <= (ii.minimum_stock * 1.5) THEN 'منخفض'
                   ELSE 'جيد'
               END as stock_status
        FROM inventory_items ii
        LEFT JOIN currencies c ON ii.currency_id = c.id
        ORDER BY ii.item_name
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'خطأ في جلب البيانات: ' . $e->getMessage();
}

// جلب العملات
$currencies = $db->query("SELECT * FROM currencies WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$stats = [
    'total_items' => count($items),
    'low_stock' => 0,
    'warning_stock' => 0,
    'total_value' => 0
];

foreach ($items as $item) {
    if ($item['stock_status'] === 'تحذير') $stats['warning_stock']++;
    if ($item['stock_status'] === 'منخفض') $stats['low_stock']++;
    $stats['total_value'] += $item['total_value'];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المخزون والمشتريات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">

        <!-- العنوان -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">📦 إدارة المخزون والمشتريات</h1>
                    <p class="text-gray-600">إدارة شاملة لمخزون البلدية والمشتريات</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    ← العودة للوحة التحكم
                </a>
            </div>
        </div>

        <!-- الرسائل -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- الإحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-100 rounded-lg p-6">
                <div class="text-3xl mb-2">📊</div>
                <div class="text-2xl font-bold text-blue-800"><?= $stats['total_items'] ?></div>
                <div class="text-sm text-blue-600">إجمالي الأصناف</div>
            </div>

            <div class="bg-yellow-100 rounded-lg p-6">
                <div class="text-3xl mb-2">⚠️</div>
                <div class="text-2xl font-bold text-yellow-800"><?= $stats['low_stock'] ?></div>
                <div class="text-sm text-yellow-600">مخزون منخفض</div>
            </div>

            <div class="bg-red-100 rounded-lg p-6">
                <div class="text-3xl mb-2">🚨</div>
                <div class="text-2xl font-bold text-red-800"><?= $stats['warning_stock'] ?></div>
                <div class="text-sm text-red-600">تحذير مخزون</div>
            </div>

            <div class="bg-green-100 rounded-lg p-6">
                <div class="text-3xl mb-2">💰</div>
                <div class="text-2xl font-bold text-green-800"><?= number_format($stats['total_value'], 2) ?></div>
                <div class="text-sm text-green-600">القيمة الإجمالية</div>
            </div>
        </div>

        <!-- زر إضافة صنف -->
        <div class="mb-4">
            <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-bold">
                ➕ إضافة صنف جديد
            </button>
        </div>

        <!-- جدول الأصناف -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الرمز</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم الصنف</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الفئة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الكمية</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحد الأدنى</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سعر الوحدة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">القيمة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($item['item_code']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?= htmlspecialchars($item['category']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $item['current_stock'] ?> <?= htmlspecialchars($item['unit']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $item['minimum_stock'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($item['stock_status'] === 'تحذير'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        🚨 تحذير
                                    </span>
                                <?php elseif ($item['stock_status'] === 'منخفض'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        ⚠️ منخفض
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        ✅ جيد
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= number_format($item['unit_price'], 2) ?> <?= htmlspecialchars($item['currency_symbol']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                <?= number_format($item['total_value'], 2) ?> <?= htmlspecialchars($item['currency_symbol']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="openStockModal(<?= $item['id'] ?>, '<?= addslashes($item['item_name']) ?>')"
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                    📝 تحديث
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal إضافة صنف -->
    <div id="addModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-xl font-bold">➕ إضافة صنف جديد</h3>
                <button onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <form method="POST" class="mt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold mb-2">رمز الصنف</label>
                        <input type="text" name="item_code" required
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2">اسم الصنف</label>
                        <input type="text" name="item_name" required
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2">الفئة</label>
                        <select name="category" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="مواد بناء">مواد بناء</option>
                            <option value="قرطاسية">قرطاسية</option>
                            <option value="كهربائيات">كهربائيات</option>
                            <option value="أدوات نظافة">أدوات نظافة</option>
                            <option value="وقود">وقود</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2">الوحدة</label>
                        <select name="unit" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="قطعة">قطعة</option>
                            <option value="كيس">كيس</option>
                            <option value="علبة">علبة</option>
                            <option value="كرتونة">كرتونة</option>
                            <option value="لتر">لتر</option>
                            <option value="متر">متر</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2">الكمية الحالية</label>
                        <input type="number" name="current_stock" value="0" required
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2">الحد الأدنى</label>
                        <input type="number" name="minimum_stock" value="10" required
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2">سعر الوحدة</label>
                        <input type="number" step="0.01" name="unit_price" required
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2">العملة</label>
                        <select name="currency_id" required class="w-full px-3 py-2 border rounded-lg">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>">
                                    <?= htmlspecialchars($currency['currency_code']) ?> - <?= htmlspecialchars($currency['currency_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-2">الموقع/المستودع</label>
                        <input type="text" name="location"
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-2">ملاحظات</label>
                        <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button"
                            onclick="document.getElementById('addModal').classList.add('hidden')"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        إلغاء
                    </button>
                    <button type="submit" name="add_item"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        ➕ إضافة
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal تحديث المخزون -->
    <div id="stockModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-xl font-bold">📝 تحديث المخزون</h3>
                <button onclick="document.getElementById('stockModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <form method="POST" class="mt-4">
                <input type="hidden" name="item_id" id="stock_item_id">

                <div class="mb-4">
                    <label class="block text-sm font-bold mb-2">الصنف</label>
                    <input type="text" id="stock_item_name" readonly
                           class="w-full px-3 py-2 border rounded-lg bg-gray-100">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold mb-2">نوع الحركة</label>
                    <select name="movement_type" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="إضافة">➕ إضافة للمخزون</option>
                        <option value="سحب">➖ سحب من المخزون</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold mb-2">الكمية</label>
                    <input type="number" name="quantity" required min="1"
                           class="w-full px-3 py-2 border rounded-lg">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold mb-2">ملاحظات</label>
                    <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button"
                            onclick="document.getElementById('stockModal').classList.add('hidden')"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        إلغاء
                    </button>
                    <button type="submit" name="update_stock"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        💾 حفظ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStockModal(itemId, itemName) {
            document.getElementById('stock_item_id').value = itemId;
            document.getElementById('stock_item_name').value = itemName;
            document.getElementById('stockModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
