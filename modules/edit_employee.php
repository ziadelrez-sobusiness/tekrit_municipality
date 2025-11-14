<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$employee_id = intval($_GET['id'] ?? 0);
$message = '';
$error = '';

if ($employee_id <= 0) {
    die('معرف الموظف غير صحيح');
}

// معالجة تحديث بيانات الموظف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $user_type = $_POST['user_type'];
    $salary = floatval($_POST['salary']);
    $salary_currency_id = intval($_POST['salary_currency_id']);
    $contract_type = $_POST['contract_type'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (!empty($full_name) && !empty($department) && !empty($username)) {
        try {
            // التحقق من وجود اسم المستخدم إذا تم تغييره
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$employee_id]);
            $current_username = $stmt->fetchColumn();
            
            // إذا تم تغيير اسم المستخدم، تحقق من عدم وجوده
            if ($username !== $current_username) {
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $check_stmt->execute([$username, $employee_id]);
                $username_exists = $check_stmt->fetchColumn();
                
                if ($username_exists > 0) {
                    $error = 'اسم المستخدم "' . htmlspecialchars($username) . '" موجود مسبقاً. يرجى اختيار اسم مستخدم آخر.';
                    goto skip_update;
                }
            }
            
            // تحديث البيانات مع أو بدون كلمة مرور
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ?, department = ?, position = ?, user_type = ?, salary = ?, salary_currency_id = ?, contract_type = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $password, $full_name, $email, $phone, $department, $position, $user_type, $salary, $salary_currency_id, $contract_type, $is_active, $employee_id]);
            } else {
                $query = "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, department = ?, position = ?, user_type = ?, salary = ?, salary_currency_id = ?, contract_type = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $full_name, $email, $phone, $department, $position, $user_type, $salary, $salary_currency_id, $contract_type, $is_active, $employee_id]);
            }
            
            $message = 'تم تحديث بيانات الموظف بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث البيانات: ' . $e->getMessage();
        }
        
        skip_update:
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// جلب بيانات الموظف مع معلومات العملة
try {
    $stmt = $db->prepare("
        SELECT u.*, c.currency_symbol, c.currency_name 
        FROM users u
        LEFT JOIN currencies c ON u.salary_currency_id = c.id
        WHERE u.id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        die('الموظف غير موجود');
    }
} catch (PDOException $e) {
    die('خطأ في جلب البيانات');
}

$departments = ['الإدارة العامة', 'المالية', 'الموارد البشرية', 'الهندسة', 'الخدمات', 'الصحة', 'البيئة', 'الأمن'];
$user_types = ['employee' => 'موظف', 'manager' => 'مدير', 'admin' => 'مدير النظام'];
$contract_types = ['monthly' => 'شهرية', 'daily' => 'يومية'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الموظف - بلدية تكريت</title>
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
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">تعديل بيانات الموظف</h1>
                    <button onclick="window.close()" class="text-gray-500 hover:text-gray-700 text-xl">✕</button>
                </div>

                <!-- عرض معلومات الموظف الحالية -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-blue-800 mb-2">المعلومات الحالية:</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-blue-600 font-medium">الاسم:</span>
                            <p class="text-gray-700"><?= htmlspecialchars($employee['full_name']) ?></p>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">اسم المستخدم:</span>
                            <p class="text-gray-700"><?= htmlspecialchars($employee['username']) ?></p>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">نوع العقد:</span>
                            <p class="text-gray-700"><?= $contract_types[$employee['contract_type']] ?? $employee['contract_type'] ?></p>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">الراتب:</span>
                            <p class="text-gray-700 currency-amount"><?= formatCurrency($employee['salary'], $employee['salary_currency_id']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <!-- معلومات الحساب -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                            <span class="text-xl ml-2">🔐</span> معلومات الحساب
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم *</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($employee['username']) ?>" required
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-500 mt-1">يمكن تغيير اسم المستخدم إذا لزم الأمر</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                                <input type="password" name="password" placeholder="اتركها فارغة للاحتفاظ بكلمة المرور الحالية"
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-500 mt-1">اتركها فارغة إذا كنت لا تريد تغيير كلمة المرور</p>
                            </div>
                        </div>
                    </div>

                    <!-- المعلومات الشخصية -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                            <span class="text-xl ml-2">👤</span> المعلومات الشخصية
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($employee['full_name']) ?>" required 
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($employee['email'] ?? '') ?>"
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">القسم *</label>
                                <select name="department" required 
                                        class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept ?>" <?= $employee['department'] === $dept ? 'selected' : '' ?>><?= $dept ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- معلومات الوظيفة -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                            <span class="text-xl ml-2">💼</span> معلومات الوظيفة
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المنصب</label>
                                <input type="text" name="position" value="<?= htmlspecialchars($employee['position'] ?? '') ?>"
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">نوع المستخدم</label>
                                <select name="user_type" class="w-full p-2 border border-gray-300 rounded-md">
                                    <?php foreach ($user_types as $type => $label): ?>
                                        <?php if ($type === 'admin' && $user['user_type'] !== 'admin') continue; ?>
                                        <option value="<?= $type ?>" <?= $employee['user_type'] === $type ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الاتفاقية</label>
                                <select name="contract_type" class="w-full p-2 border border-gray-300 rounded-md">
                                    <?php foreach ($contract_types as $type => $label): ?>
                                        <option value="<?= $type ?>" <?= $employee['contract_type'] === $type ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" <?= $employee['is_active'] ? 'checked' : '' ?>
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_active" class="mr-2 block text-sm text-gray-900">الموظف نشط</label>
                            </div>
                        </div>
                    </div>

                    <!-- معلومات الراتب -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                            <span class="text-xl ml-2">💰</span> معلومات الراتب
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">مبلغ الراتب</label>
                                <input type="number" step="0.01" min="0" name="salary" value="<?= $employee['salary'] ?>"
                                       class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">عملة الراتب</label>
                                <?= getCurrencySelect('salary_currency_id', $employee['salary_currency_id'], 'w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500', true) ?>
                            </div>
                        </div>
                        
                        <!-- عرض الراتب الحالي -->
                        <div class="mt-4 p-3 bg-gray-50 rounded-md">
                            <p class="text-sm text-gray-700">
                                <span class="font-medium">الراتب الحالي:</span>
                                <span class="currency-amount text-green-600 font-bold"><?= formatCurrency($employee['salary'], $employee['salary_currency_id']) ?></span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="update_employee" 
                                class="flex-1 bg-green-600 text-white py-3 px-4 rounded-md hover:bg-green-700 transition font-medium">
                            💾 حفظ التغييرات
                        </button>
                        <button type="button" onclick="window.close()" 
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                            ❌ إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // تأكيد قبل الحفظ
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('هل أنت متأكد من حفظ التغييرات؟')) {
                e.preventDefault();
            }
        });

        // تحديث عرض الراتب عند تغيير العملة أو المبلغ
        function updateSalaryDisplay() {
            const salaryInput = document.querySelector('input[name="salary"]');
            const currencySelect = document.querySelector('select[name="salary_currency_id"]');
            
            if (salaryInput.value && currencySelect.value) {
                // يمكن إضافة AJAX لجلب رمز العملة وعرض المبلغ المحدث
                console.log(`الراتب: ${salaryInput.value} - العملة: ${currencySelect.value}`);
            }
        }

        document.querySelector('input[name="salary"]').addEventListener('input', updateSalaryDisplay);
        document.querySelector('select[name="salary_currency_id"]').addEventListener('change', updateSalaryDisplay);
    </script>
</body>
</html> 
