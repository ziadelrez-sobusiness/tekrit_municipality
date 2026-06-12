<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة أو تحديث عملة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_currency'])) {
        $currency_code = strtoupper(trim($_POST['currency_code']));
        $currency_name = trim($_POST['currency_name']);
        $currency_symbol = trim($_POST['currency_symbol']);
        $exchange_rate = floatval($_POST['exchange_rate']);
        
        if (!empty($currency_code) && !empty($currency_name) && !empty($currency_symbol) && $exchange_rate > 0) {
            try {
                $stmt = $db->prepare("INSERT INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_iqd, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$currency_code, $currency_name, $currency_symbol, $exchange_rate]);
                $message = 'تم إضافة العملة بنجاح!';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'رمز العملة موجود مسبقاً!';
                } else {
                    $error = 'خطأ في إضافة العملة: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة';
        }
    }
    
    if (isset($_POST['update_currency'])) {
        $currency_id = intval($_POST['currency_id']);
        $currency_name = trim($_POST['currency_name']);
        $currency_symbol = trim($_POST['currency_symbol']);
        $exchange_rate = floatval($_POST['exchange_rate']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($currency_id > 0 && !empty($currency_name) && !empty($currency_symbol) && $exchange_rate > 0) {
            try {
                $stmt = $db->prepare("UPDATE currencies SET currency_name = ?, currency_symbol = ?, exchange_rate_to_iqd = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$currency_name, $currency_symbol, $exchange_rate, $is_active, $currency_id]);
                $message = 'تم تحديث العملة بنجاح!';
            } catch (PDOException $e) {
                $error = 'خطأ في تحديث العملة: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة';
        }
    }
    
    if (isset($_POST['delete_currency'])) {
        $currency_id = intval($_POST['currency_id']);
        
        if ($currency_id > 1) { // لا يمكن حذف الليرة اللبنانية
            try {
                $stmt = $db->prepare("DELETE FROM currencies WHERE id = ?");
                $stmt->execute([$currency_id]);
                $message = 'تم حذف العملة بنجاح!';
            } catch (PDOException $e) {
                $error = 'لا يمكن حذف هذه العملة لوجود معاملات مرتبطة بها';
            }
        } else {
            $error = 'لا يمكن حذف العملة الأساسية (الليرة اللبنانية)';
        }
    }
}

// جلب جميع العملات
try {
    $stmt = $db->query("SELECT * FROM currencies ORDER BY id");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات العملات
    $stmt = $db->query("SELECT COUNT(*) as total_currencies, SUM(is_active) as active_currencies FROM currencies");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // استخدام العملات في المعاملات المالية
    $stmt = $db->query("
        SELECT c.currency_code, c.currency_name, c.currency_symbol, COUNT(ft.id) as usage_count, SUM(ft.amount_in_lbp) as total_amount_lbp
        FROM currencies c 
        LEFT JOIN financial_transactions ft ON c.id = ft.currency_id 
        GROUP BY c.id, c.currency_code, c.currency_name, c.currency_symbol
        ORDER BY usage_count DESC
    ");
    $usage_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $currencies = [];
    $stats = ['total_currencies' => 0, 'active_currencies' => 0];
    $usage_stats = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العملات - بلدية تكريت عكار</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة العملات</h1>
                <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    ← العودة للوحة التحكم
                </a>
            </div>
            <p class="text-slate-600 mt-2">إدارة العملات وأسعار الصرف - العملة الأساسية: الليرة اللبنانية</p>
        </div>

        <!-- Exchange Rate Volatility Safeguard Warning -->
        <div class="bg-amber-50 border-r-4 border-amber-500 text-amber-900 p-4 rounded-lg shadow-sm mb-6 flex items-start gap-3">
            <span class="text-2xl mt-0.5">⚠️</span>
            <div>
                <h4 class="font-bold text-amber-800 mb-1">تحذير أمان: حماية السجلات المالية التاريخية</h4>
                <p class="text-xs leading-relaxed text-amber-700">إن تعديل أسعار الصرف يؤثر فقط على <strong>المعاملات المالية المستقبلية والجديدة</strong>. كافة المعاملات والقيود القديمة المسجلة تحتفظ بأسعار الصرف التاريخية التي نُفذت بها وقت المعاملة لحماية سلامة الدفاتر وتجنب حدوث أي فروقات محاسبية في الأرصدة التراكمية للصناديق.</p>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي العملات</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $stats['total_currencies'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">💱</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">العملات النشطة</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['active_currencies'] ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">العملة الأساسية</p>
                        <p class="text-lg font-bold text-purple-600">الليرة اللبنانية (LBP)</p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">🏦</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Add Currency Form -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h2 class="text-xl font-semibold mb-4">إضافة عملة جديدة</h2>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رمز العملة (3 أحرف)</label>
                        <input type="text" name="currency_code" required maxlength="3" 
                               placeholder="مثال: USD, EUR, SAR"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 uppercase">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم العملة</label>
                        <input type="text" name="currency_name" required 
                               placeholder="مثال: الدولار الأمريكي"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رمز العملة</label>
                        <input type="text" name="currency_symbol" required maxlength="10"
                               placeholder="مثال: $, €, ر.س"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">سعر الصرف مقابل الليرة اللبنانية</label>
                        <input type="number" step="0.0001" name="exchange_rate" required min="0.0001"
                               placeholder="مثال: 90000.0000 (للدولار)"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-xs text-gray-500 mt-1">كم ليرة لبنانية تساوي وحدة واحدة من هذه العملة</p>
                    </div>
                    
                    <button type="submit" name="add_currency" 
                            class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-200">
                        إضافة العملة
                    </button>
                </form>
            </div>

            <!-- Exchange Rate Calculator -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h2 class="text-xl font-semibold mb-4">حاسبة أسعار الصرف</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ</label>
                        <input type="number" id="amount" step="0.01" placeholder="أدخل المبلغ"
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">من العملة</label>
                        <select id="from_currency" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <?php foreach ($currencies as $currency): ?>
                                <?php if ($currency['is_active']): ?>
                                    <option value="<?= $currency['exchange_rate_to_iqd'] ?>" <?= $currency['id'] == 1 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($currency['currency_code']) ?> - <?= htmlspecialchars($currency['currency_name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">إلى العملة</label>
                        <select id="to_currency" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <?php foreach ($currencies as $currency): ?>
                                <?php if ($currency['is_active']): ?>
                                    <option value="<?= $currency['exchange_rate_to_iqd'] ?>" <?= $currency['currency_code'] == 'USD' ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($currency['currency_code']) ?> - <?= htmlspecialchars($currency['currency_name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button onclick="calculateExchange()" 
                            class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200">
                        تحويل
                    </button>
                    
                    <div id="result" class="text-center text-lg font-semibold text-blue-600"></div>
                </div>
            </div>
        </div>

        <!-- Currencies Table -->
        <div class="bg-white rounded-lg shadow-sm mt-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">العملات المتاحة</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">رمز العملة</th>
                            <th class="px-6 py-3">اسم العملة</th>
                            <th class="px-6 py-3">الرمز</th>
                            <th class="px-6 py-3">سعر الصرف (ل.ل)</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">الاستخدام</th>
                            <th class="px-6 py-3">العمليات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currencies as $currency): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-semibold">
                                    <?= htmlspecialchars($currency['currency_code']) ?>
                                    <?php if ($currency['id'] == 1): ?>
                                        <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded-full mr-2">أساسية</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($currency['currency_name']) ?></td>
                                <td class="px-6 py-4 font-mono"><?= htmlspecialchars($currency['currency_symbol']) ?></td>
                                <td class="px-6 py-4 font-mono">
                                    <?php if ($currency['id'] == 1): ?>
                                        1.0000 (عملة أساسية)
                                    <?php else: ?>
                                        <?= number_format($currency['exchange_rate_to_iqd'], 4) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded <?= $currency['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $currency['is_active'] ? 'نشطة' : 'غير نشطة' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $usage = array_filter($usage_stats, function($u) use ($currency) { 
                                        return $u['currency_code'] == $currency['currency_code']; 
                                    });
                                    $usage_count = $usage ? array_values($usage)[0]['usage_count'] : 0;
                                    ?>
                                    <span class="text-blue-600"><?= $usage_count ?> معاملة</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <?php if ($currency['id'] != 1): ?>
                                            <button onclick="editCurrency(<?= htmlspecialchars(json_encode($currency)) ?>)" 
                                                    class="text-blue-600 hover:text-blue-800">تعديل</button>
                                            <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه العملة؟')">
                                                <input type="hidden" name="currency_id" value="<?= $currency['id'] ?>">
                                                <button type="submit" name="delete_currency" 
                                                        class="text-red-600 hover:text-red-800">حذف</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-400">العملة الأساسية</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($currencies)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    لا توجد عملات مضافة بعد
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Usage Statistics -->
        <div class="bg-white rounded-lg shadow-sm mt-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">إحصائيات استخدام العملات</h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($usage_stats as $stat): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-semibold"><?= htmlspecialchars($stat['currency_code']) ?></span>
                                <span class="text-lg"><?= htmlspecialchars($stat['currency_symbol']) ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($stat['currency_name']) ?></p>
                            <p class="text-lg font-bold text-blue-600"><?= $stat['usage_count'] ?> معاملة</p>
                            <?php if ($stat['total_amount_lbp']): ?>
                                <p class="text-sm text-green-600">
                                    إجمالي: <?= number_format($stat['total_amount_lbp']) ?> ل.ل
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Currency Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">تعديل العملة</h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="currency_id" id="edit_currency_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم العملة</label>
                        <input type="text" name="currency_name" id="edit_currency_name" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رمز العملة</label>
                        <input type="text" name="currency_symbol" id="edit_currency_symbol" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">سعر الصرف</label>
                        <input type="number" step="0.0001" name="exchange_rate" id="edit_exchange_rate" required 
                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="edit_is_active" 
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="edit_is_active" class="mr-2 block text-sm text-gray-900">نشطة</label>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            إلغاء
                        </button>
                        <button type="submit" name="update_currency" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            تحديث
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editCurrency(currency) {
            document.getElementById('edit_currency_id').value = currency.id;
            document.getElementById('edit_currency_name').value = currency.currency_name;
            document.getElementById('edit_currency_symbol').value = currency.currency_symbol;
            document.getElementById('edit_exchange_rate').value = currency.exchange_rate_to_iqd;
            document.getElementById('edit_is_active').checked = currency.is_active == 1;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        function calculateExchange() {
            const amount = parseFloat(document.getElementById('amount').value);
            const fromRate = parseFloat(document.getElementById('from_currency').value);
            const toRate = parseFloat(document.getElementById('to_currency').value);
            
            if (amount && fromRate && toRate) {
                // تحويل إلى الليرة اللبنانية أولاً ثم إلى العملة المطلوبة
                const lbpAmount = amount * fromRate;
                const result = lbpAmount / toRate;
                
                document.getElementById('result').innerHTML = 
                    `النتيجة: ${result.toFixed(2)} <br>
                     <small class="text-gray-500">(${amount.toFixed(2)} → ${lbpAmount.toFixed(0)} ل.ل → ${result.toFixed(2)})</small>`;
            } else {
                document.getElementById('result').innerHTML = 'يرجى إدخال جميع القيم';
            }
        }
        
        // تحديث الحاسبة عند تغيير القيم
        document.getElementById('amount').addEventListener('input', calculateExchange);
        document.getElementById('from_currency').addEventListener('change', calculateExchange);
        document.getElementById('to_currency').addEventListener('change', calculateExchange);
    </script>
</body>
</html> 
