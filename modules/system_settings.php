<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';
require_once __DIR__ . '/../includes/settings_helper.php';

// التأكد من تسجيل الدخول وصلاحية الإدارة
$auth->requireLogin();
if (!$auth->checkPermission('admin')) {
    header('Location: ../comprehensive_dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة تحديث أسعار الصرف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exchange_rates'])) {
    try {
        $db->beginTransaction();
        
        foreach ($_POST['currencies'] as $currency_id => $data) {
            $rate = floatval($data['exchange_rate']);
            $is_active = isset($data['is_active']) ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE currencies SET exchange_rate_to_iqd = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$rate, $is_active, $currency_id]);
        }
        
        $db->commit();
        $message = 'تم تحديث أسعار الصرف بنجاح!';
    } catch (PDOException $e) {
        $db->rollback();
        $error = 'خطأ في تحديث أسعار الصرف: ' . $e->getMessage();
    }
}

// معالجة إضافة عملة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_currency'])) {
    $currency_code = strtoupper(trim($_POST['currency_code']));
    $currency_name = trim($_POST['currency_name']);
    $currency_symbol = trim($_POST['currency_symbol']);
    $exchange_rate = floatval($_POST['exchange_rate']);
    
    if (!empty($currency_code) && !empty($currency_name) && !empty($currency_symbol)) {
        try {
            $stmt = $db->prepare("INSERT INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_iqd, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$currency_code, $currency_name, $currency_symbol, $exchange_rate]);
            $message = 'تم إضافة العملة الجديدة بنجاح!';
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'رمز العملة موجود مسبقاً';
            } else {
                $error = 'خطأ في إضافة العملة: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'يرجى تعبئة جميع الحقول المطلوبة';
    }
}

// معالجة إعدادات النظام العامة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_system_settings'])) {
    $default_currency = intval($_POST['default_currency']);
    $system_name = trim($_POST['system_name']);
    $admin_email = trim($_POST['admin_email']);
    
    try {
        // تحديث الإعدادات في جدول system_settings
        setSetting('default_currency_id', $default_currency, 'معرف العملة الافتراضية للنظام');
        setSetting('system_name', $system_name, 'اسم النظام');
        setSetting('admin_email', $admin_email, 'بريد المدير الإلكتروني');
        
        $message = 'تم تحديث إعدادات النظام بنجاح! العملة الافتراضية الآن: ' . $default_currency;
    } catch (Exception $e) {
        $error = 'خطأ في تحديث الإعدادات: ' . $e->getMessage();
    }
}

// جلب جميع العملات
try {
    $stmt = $db->query("SELECT * FROM currencies ORDER BY is_active DESC, currency_name ASC");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $currencies = [];
}

// إحصائيات النظام
try {
    $stats = [];
    
    // عدد المستخدمين
    $stmt = $db->query("SELECT COUNT(*) as total_users, SUM(is_active) as active_users FROM users");
    $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['users'] = $user_stats;
    
    // إجمالي الرواتب بالدينار العراقي
    $stmt = $db->query("
        SELECT 
            SUM(u.salary * c.exchange_rate_to_iqd) as total_salary_iqd,
            COUNT(*) as employees_with_salary
        FROM users u
        LEFT JOIN currencies c ON u.salary_currency_id = c.id
        WHERE u.is_active = 1 AND u.salary > 0
    ");
    $salary_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['salary'] = $salary_stats;
    
    // عدد العملات النشطة
    $stmt = $db->query("SELECT COUNT(*) as active_currencies FROM currencies WHERE is_active = 1");
    $currency_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['currencies'] = $currency_stats;
    
} catch (PDOException $e) {
    $stats = ['users' => [], 'salary' => [], 'currencies' => []];
}

// الحصول على العملة الافتراضية الحالية
$current_default_currency = getDefaultCurrency();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات النظام - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .currency-symbol {
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
                    <h1 class="text-3xl font-bold text-gray-800">⚙️ إعدادات النظام</h1>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
                <p class="text-gray-600 mt-2">إدارة العملات وأسعار الصرف والإعدادات العامة</p>
                
                <!-- العملة الافتراضية الحالية -->
                <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center">
                        <span class="text-xl ml-2">🌟</span>
                        <span class="font-semibold text-blue-800">العملة الافتراضية الحالية:</span>
                        <span class="mr-2 font-bold text-blue-900">
                            <?= $current_default_currency ? $current_default_currency['currency_name'] . ' (' . $current_default_currency['currency_symbol'] . ')' : 'غير محددة' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                    ✅ <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="text-3xl ml-4">👥</div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">المستخدمين</h3>
                            <p class="text-2xl font-bold text-blue-600"><?= $stats['users']['active_users'] ?? 0 ?> / <?= $stats['users']['total_users'] ?? 0 ?></p>
                            <p class="text-sm text-gray-500">نشط / إجمالي</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="text-3xl ml-4">💰</div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">إجمالي الرواتب</h3>
                            <p class="text-2xl font-bold text-green-600 currency-symbol">
                                <?= number_format($stats['salary']['total_salary_iqd'] ?? 0, 0) ?> د.ع
                            </p>
                            <p class="text-sm text-gray-500"><?= $stats['salary']['employees_with_salary'] ?? 0 ?> موظف</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="text-3xl ml-4">🌍</div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">العملات النشطة</h3>
                            <p class="text-2xl font-bold text-purple-600"><?= $stats['currencies']['active_currencies'] ?? 0 ?></p>
                            <p class="text-sm text-gray-500">من <?= count($currencies) ?> إجمالي</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button onclick="showTab('system')" class="tab-button py-4 px-2 border-b-2 border-blue-500 text-blue-600 font-medium">
                            ⚙️ إعدادات عامة
                        </button>
                        <button onclick="showTab('currencies')" class="tab-button py-4 px-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium">
                            💱 إدارة العملات
                        </button>
                        <button onclick="showTab('exchange-rates')" class="tab-button py-4 px-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium">
                            📈 أسعار الصرف
                        </button>
                    </nav>
                </div>

                <!-- Tab: إعدادات عامة -->
                <div id="system-tab" class="tab-content active p-6">
                    <form method="POST" class="space-y-6">
                        <h3 class="text-xl font-semibold">إعدادات النظام العامة</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">العملة الافتراضية</label>
                                <?= getCurrencySelect('default_currency', getDefaultCurrencyId(), 'w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500', true) ?>
                                <p class="text-xs text-gray-500 mt-1">هذه العملة ستُستخدم افتراضياً في جميع العمليات المالية</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">اسم النظام</label>
                                <input type="text" name="system_name" value="<?= htmlspecialchars(getSetting('system_name', 'نظام إدارة بلدية تكريت')) ?>" 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">بريد المدير الإلكتروني</label>
                                <input type="email" name="admin_email" value="<?= htmlspecialchars(getSetting('admin_email', 'admin@tekrit.gov.iq')) ?>" 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <button type="submit" name="update_system_settings" 
                                class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                            💾 حفظ الإعدادات
                        </button>
                    </form>
                </div>

                <!-- Tab: إدارة العملات -->
                <div id="currencies-tab" class="tab-content p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- إضافة عملة جديدة -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-xl font-semibold mb-4">➕ إضافة عملة جديدة</h3>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">رمز العملة (3 أحرف)</label>
                                    <input type="text" name="currency_code" maxlength="3" placeholder="LBP" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 uppercase">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم العملة</label>
                                    <input type="text" name="currency_name" placeholder="الليرة اللبنانية" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">رمز العملة</label>
                                    <input type="text" name="currency_symbol" placeholder="ل.ل" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">سعر الصرف مقابل الدينار العراقي</label>
                                    <input type="number" step="0.0001" min="0" name="exchange_rate" placeholder="0.8750" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <button type="submit" name="add_currency" 
                                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                                    ➕ إضافة العملة
                                </button>
                            </form>
                        </div>

                        <!-- قائمة العملات الحالية -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-xl font-semibold mb-4">📋 العملات الحالية</h3>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php foreach ($currencies as $currency): ?>
                                    <div class="flex items-center justify-between p-3 bg-white rounded-lg border">
                                        <div class="flex items-center">
                                            <div class="text-2xl ml-3"><?= htmlspecialchars($currency['currency_symbol']) ?></div>
                                            <div>
                                                <div class="font-medium"><?= htmlspecialchars($currency['currency_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($currency['currency_code']) ?></div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium currency-symbol"><?= number_format($currency['exchange_rate_to_iqd'], 4) ?></div>
                                            <div class="text-sm <?= $currency['is_active'] ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= $currency['is_active'] ? '✅ نشط' : '❌ غير نشط' ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: أسعار الصرف -->
                <div id="exchange-rates-tab" class="tab-content p-6">
                    <form method="POST" class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold">تحديث أسعار الصرف</h3>
                            <button type="submit" name="update_exchange_rates" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                                💾 حفظ التحديثات
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($currencies as $currency): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center mb-3">
                                        <div class="text-2xl ml-3"><?= htmlspecialchars($currency['currency_symbol']) ?></div>
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($currency['currency_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($currency['currency_code']) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700">سعر الصرف</label>
                                        <input type="number" step="0.0001" min="0" 
                                               name="currencies[<?= $currency['id'] ?>][exchange_rate]" 
                                               value="<?= $currency['exchange_rate_to_iqd'] ?>"
                                               class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   name="currencies[<?= $currency['id'] ?>][is_active]" 
                                                   <?= $currency['is_active'] ? 'checked' : '' ?>
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label class="mr-2 text-sm text-gray-700">نشط</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // إخفاء جميع التبويبات
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إظهار التبويب المحدد
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // تحديث أزرار التبويبات
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            event.target.classList.remove('border-transparent', 'text-gray-500');
            event.target.classList.add('border-blue-500', 'text-blue-600');
        }
    </script>
</body>
</html> 
