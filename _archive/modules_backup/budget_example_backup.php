<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة مصروف جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $currency_id = intval($_POST['currency_id']);
    $category = $_POST['category'];
    $expense_date = $_POST['expense_date'];
    
    if (!empty($description) && $amount > 0) {
        try {
            $query = "INSERT INTO expenses (description, amount, currency_id, expense_category, expense_date, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$description, $amount, $currency_id, $category, $expense_date, $user['id']]);
            $message = 'تم إضافة المصروف بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المصروف: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة';
    }
}

// جلب المصروفات مع معلومات العملة
try {
    $stmt = $db->query("
        SELECT e.*, c.currency_symbol, c.currency_name, u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN currencies c ON e.currency_id = c.id
        LEFT JOIN users u ON e.created_by_user_id = u.id
        ORDER BY e.expense_date DESC
        LIMIT 20
    ");
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expenses = [];
}

$categories = [
    'office_supplies' => 'لوازم مكتبية',
    'utilities' => 'فواتير وخدمات',
    'maintenance' => 'صيانة',
    'transportation' => 'مواصلات',
    'communication' => 'اتصالات',
    'other' => 'أخرى'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المصروفات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .currency-amount {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen p-6">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl font-bold text-gray-800">إدارة المصروفات مع العملات</h1>
                    <a href="../dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
                <p class="text-gray-600 mt-2">مثال على استخدام نظام العملات في الصفحات المالية</p>
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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- نموذج إضافة مصروف -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <span class="text-2xl ml-2">💰</span> إضافة مصروف جديد
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">وصف المصروف</label>
                            <input type="text" name="description" required 
                                   class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ</label>
                                <input type="number" step="0.01" min="0" name="amount" required 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">العملة</label>
                                <?= getCurrencySelect('currency_id', null, 'w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500', true) ?>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                                <select name="category" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">اختر الفئة</option>
                                    <?php foreach ($categories as $key => $value): ?>
                                        <option value="<?= $key ?>"><?= $value ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ المصروف</label>
                                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <button type="submit" name="add_expense" 
                                class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                            💾 إضافة المصروف
                        </button>
                    </form>
                </div>

                <!-- معلومات نظام العملات -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <span class="text-2xl ml-2">🌍</span> معلومات العملات
                    </h2>
                    
                    <div class="space-y-3">
                        <?php 
                        $active_currencies = getActiveCurrencies();
                        foreach ($active_currencies as $currency): 
                        ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <span class="font-medium"><?= $currency['currency_name'] ?></span>
                                    <span class="text-sm text-gray-500">(<?= $currency['currency_code'] ?>)</span>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-lg"><?= $currency['currency_symbol'] ?></div>
                                    <?php if (!$currency['is_default']): ?>
                                        <div class="text-xs text-gray-500">
                                            سعر: <?= number_format($currency['exchange_rate'], 6) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- أمثلة على التنسيق -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                        <h3 class="font-semibold text-blue-800 mb-2">أمثلة على التنسيق:</h3>
                        <div class="space-y-1 text-sm">
                            <div class="currency-amount">1,000,000 <?= formatCurrency(1000000, 1) ?></div>
                            <div class="currency-amount">500 <?= formatCurrency(500, 2) ?></div>
                            <div class="currency-amount">750 <?= formatCurrency(750, 3) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- جدول المصروفات -->
            <div class="bg-white rounded-lg shadow-md mt-6">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold">المصروفات الأخيرة</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">الوصف</th>
                                <th class="px-6 py-3">المبلغ</th>
                                <th class="px-6 py-3">الفئة</th>
                                <th class="px-6 py-3">التاريخ</th>
                                <th class="px-6 py-3">المُنشئ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium"><?= htmlspecialchars($expense['description']) ?></td>
                                    <td class="px-6 py-4 currency-amount text-green-600 font-bold">
                                        <?= formatCurrency($expense['amount'], $expense['currency_id']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                            <?= $categories[$expense['expense_category']] ?? $expense['expense_category'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><?= date('Y-m-d', strtotime($expense['expense_date'])) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($expense['created_by_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        لا توجد مصروفات مسجلة
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 
