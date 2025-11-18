<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

// التحقق من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");

$user = $auth->getUserInfo();
$message = '';
$error = '';

// معالجة إضافة مورد جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            $supplier_code = trim($_POST['supplier_code']);
            $name = trim($_POST['name']);
            $contact_person = trim($_POST['contact_person']);
            $phone = trim($_POST['phone']);
            $mobile = trim($_POST['mobile']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);
            $service_type = trim($_POST['service_type']);
            $tax_number = trim($_POST['tax_number']);
            $commercial_registration = trim($_POST['commercial_registration']);
            $payment_terms = trim($_POST['payment_terms']);
            $bank_account = trim($_POST['bank_account']);
            $bank_name = trim($_POST['bank_name']);
            $notes = trim($_POST['notes']);
            
            $stmt = $db->prepare("INSERT INTO suppliers (supplier_code, name, contact_person, phone, mobile, email, address, service_type, tax_number, commercial_registration, payment_terms, bank_account, bank_name, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$supplier_code, $name, $contact_person, $phone, $mobile, $email, $address, $service_type, $tax_number, $commercial_registration, $payment_terms, $bank_account, $bank_name, $notes]);
            
            $message = 'تم إضافة المورد بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المورد: ' . $e->getMessage();
        }
    }
}

// معالجة تعديل مورد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            $id = intval($_POST['supplier_id']);
            $supplier_code = trim($_POST['supplier_code']);
            $name = trim($_POST['name']);
            $contact_person = trim($_POST['contact_person']);
            $phone = trim($_POST['phone']);
            $mobile = trim($_POST['mobile']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);
            $service_type = trim($_POST['service_type']);
            $tax_number = trim($_POST['tax_number']);
            $commercial_registration = trim($_POST['commercial_registration']);
            $payment_terms = trim($_POST['payment_terms']);
            $bank_account = trim($_POST['bank_account']);
            $bank_name = trim($_POST['bank_name']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $notes = trim($_POST['notes']);
            
            $stmt = $db->prepare("UPDATE suppliers SET supplier_code=?, name=?, contact_person=?, phone=?, mobile=?, email=?, address=?, service_type=?, tax_number=?, commercial_registration=?, payment_terms=?, bank_account=?, bank_name=?, is_active=?, notes=? WHERE id=?");
            $stmt->execute([$supplier_code, $name, $contact_person, $phone, $mobile, $email, $address, $service_type, $tax_number, $commercial_registration, $payment_terms, $bank_account, $bank_name, $is_active, $notes, $id]);
            
            $message = 'تم تحديث بيانات المورد بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث المورد: ' . $e->getMessage();
        }
    }
}

// معالجة حذف مورد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supplier'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        try {
            $id = intval($_POST['supplier_id']);
            
            // التحقق من وجود فواتير مرتبطة
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM supplier_invoices WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $invoiceCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($invoiceCount > 0) {
                $error = "لا يمكن حذف المورد لوجود $invoiceCount فاتورة مرتبطة به. يمكنك تعطيله بدلاً من الحذف.";
            } else {
                $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'تم حذف المورد بنجاح!';
            }
        } catch (PDOException $e) {
            $error = 'خطأ في حذف المورد: ' . $e->getMessage();
        }
    }
}

// جلب الموردين
$filter_name = $_GET['name'] ?? '';
$filter_service = $_GET['service'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filter_name)) {
    $where_conditions[] = "(s.name LIKE ? OR s.supplier_code LIKE ?)";
    $params[] = "%$filter_name%";
    $params[] = "%$filter_name%";
}

if (!empty($filter_service)) {
    $where_conditions[] = "s.service_type LIKE ?";
    $params[] = "%$filter_service%";
}

if ($filter_status !== '') {
    $where_conditions[] = "s.is_active = ?";
    $params[] = $filter_status;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $db->prepare("
    SELECT s.*, 
           COUNT(DISTINCT si.id) as invoice_count,
           COALESCE(SUM(si.total_amount), 0) as total_invoices_amount,
           COALESCE(SUM(si.paid_amount), 0) as total_paid_amount,
           COALESCE(SUM(si.remaining_amount), 0) as total_remaining_amount
    FROM suppliers s
    LEFT JOIN supplier_invoices si ON s.id = si.supplier_id
    $where_clause
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_suppliers,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_suppliers,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_suppliers
    FROM suppliers
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب أنواع الخدمات للفلتر
$stmt = $db->query("SELECT DISTINCT service_type FROM suppliers WHERE service_type IS NOT NULL AND service_type != '' ORDER BY service_type");
$service_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الموردين - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none !important; }
        .modal.active { display: flex !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">🏪 إدارة الموردين</h1>
                    <p class="text-gray-600 mt-2">إدارة شاملة للموردين وكشوفات الحساب</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="openModal('addSupplierModal')" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition shadow-lg">
                        ➕ إضافة مورد جديد
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 shadow">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 shadow">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- إحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">إجمالي الموردين</p>
                        <p class="text-3xl font-bold text-blue-600"><?= number_format($stats['total_suppliers']) ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-4 rounded-full text-3xl">🏪</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">الموردون النشطون</p>
                        <p class="text-3xl font-bold text-green-600"><?= number_format($stats['active_suppliers']) ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-4 rounded-full text-3xl">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">الموردون غير النشطين</p>
                        <p class="text-3xl font-bold text-red-600"><?= number_format($stats['inactive_suppliers']) ?></p>
                    </div>
                    <div class="bg-red-100 text-red-600 p-4 rounded-full text-3xl">❌</div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4 text-lg">🔍 البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المورد / الرمز</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($filter_name) ?>" 
                           placeholder="ابحث بالاسم أو الرمز"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع الخدمة</label>
                    <select name="service" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($service_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= ($filter_service === $type) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">الحالة</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">الكل</option>
                        <option value="1" <?= ($filter_status === '1') ? 'selected' : '' ?>>نشط</option>
                        <option value="0" <?= ($filter_status === '0') ? 'selected' : '' ?>>غير نشط</option>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">
                        بحث
                    </button>
                    <a href="suppliers.php" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition">
                        إعادة تعيين
                    </a>
                </div>
            </form>
        </div>

        <!-- جدول الموردين -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b bg-gray-50">
                <h2 class="text-xl font-semibold">📋 قائمة الموردين (<?= count($suppliers) ?>)</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-right p-4 font-semibold">الرمز</th>
                            <th class="text-right p-4 font-semibold">اسم المورد</th>
                            <th class="text-right p-4 font-semibold">نوع الخدمة</th>
                            <th class="text-right p-4 font-semibold">الاتصال</th>
                            <th class="text-right p-4 font-semibold">عدد الفواتير</th>
                            <th class="text-right p-4 font-semibold">إجمالي المبالغ</th>
                            <th class="text-right p-4 font-semibold">المدفوع</th>
                            <th class="text-right p-4 font-semibold">المتبقي</th>
                            <th class="text-right p-4 font-semibold">الحالة</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-500">
                                    📭 لا توجد موردين مسجلين
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4 font-bold text-blue-600"><?= htmlspecialchars($supplier['supplier_code']) ?></td>
                                <td class="p-4">
                                    <div class="font-semibold"><?= htmlspecialchars($supplier['name']) ?></div>
                                    <?php if (!empty($supplier['contact_person'])): ?>
                                        <div class="text-sm text-gray-500">👤 <?= htmlspecialchars($supplier['contact_person']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">
                                        <?= htmlspecialchars($supplier['service_type']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-sm">
                                    <?php if (!empty($supplier['mobile'])): ?>
                                        <div>📱 <?= htmlspecialchars($supplier['mobile']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($supplier['phone'])): ?>
                                        <div>📞 <?= htmlspecialchars($supplier['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($supplier['email'])): ?>
                                        <div class="text-blue-600">✉️ <?= htmlspecialchars($supplier['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 bg-gray-100 rounded-full font-bold">
                                        <?= $supplier['invoice_count'] ?>
                                    </span>
                                </td>
                                <td class="p-4 font-semibold"><?= number_format($supplier['total_invoices_amount'], 2) ?> ل.ل</td>
                                <td class="p-4 text-green-600 font-semibold"><?= number_format($supplier['total_paid_amount'], 2) ?> ل.ل</td>
                                <td class="p-4 text-red-600 font-semibold"><?= number_format($supplier['total_remaining_amount'], 2) ?> ل.ل</td>
                                <td class="p-4">
                                    <?php if ($supplier['is_active']): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">✅ نشط</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">❌ غير نشط</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewSupplier(<?= $supplier['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm px-3 py-1 rounded bg-blue-100 hover:bg-blue-200">
                                            👁️ عرض
                                        </button>
                                        <button onclick="editSupplier(<?= $supplier['id'] ?>)" 
                                                class="text-yellow-600 hover:text-yellow-800 text-sm px-3 py-1 rounded bg-yellow-100 hover:bg-yellow-200">
                                            ✏️ تعديل
                                        </button>
                                        <a href="invoices.php?supplier_id=<?= $supplier['id'] ?>" 
                                           class="text-purple-600 hover:text-purple-800 text-sm px-3 py-1 rounded bg-purple-100 hover:bg-purple-200">
                                            📄 الفواتير
                                        </a>
                                        <button onclick="deleteSupplier(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name']) ?>')" 
                                                class="text-red-600 hover:text-red-800 text-sm px-3 py-1 rounded bg-red-100 hover:bg-red-200">
                                            🗑️ حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal التعديل -->
    <div id="editSupplierModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">✏️ تعديل بيانات المورد</h3>
                <button onclick="closeModal('editSupplierModal')" class="text-gray-400 hover:text-gray-600 text-2xl">✕</button>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="supplier_id" id="edit_supplier_id">
                
                <!-- معلومات أساسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">📋 المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رمز المورد *</label>
                            <input type="text" name="supplier_code" id="edit_supplier_code" required 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم المورد *</label>
                            <input type="text" name="name" id="edit_name" required 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الشخص المسؤول</label>
                            <input type="text" name="contact_person" id="edit_contact_person" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع الخدمة/المواد *</label>
                            <input type="text" name="service_type" id="edit_service_type" required 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات الاتصال -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">📞 معلومات الاتصال</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الهاتف</label>
                            <input type="text" name="phone" id="edit_phone" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الموبايل</label>
                            <input type="text" name="mobile" id="edit_mobile" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" id="edit_email" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">العنوان</label>
                            <input type="text" name="address" id="edit_address" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات تجارية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">🏢 المعلومات التجارية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">الرقم الضريبي</label>
                            <input type="text" name="tax_number" id="edit_tax_number" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">السجل التجاري</label>
                            <input type="text" name="commercial_registration" id="edit_commercial_registration" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">شروط الدفع</label>
                            <input type="text" name="payment_terms" id="edit_payment_terms" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" id="edit_is_active" class="w-5 h-5 text-green-600">
                                <span class="text-sm font-medium">المورد نشط</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- معلومات بنكية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">🏦 المعلومات البنكية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم البنك</label>
                            <input type="text" name="bank_name" id="edit_bank_name" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الحساب البنكي</label>
                            <input type="text" name="bank_account" id="edit_bank_account" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- ملاحظات -->
                <div>
                    <label class="block text-sm font-medium mb-2">ملاحظات</label>
                    <textarea name="notes" id="edit_notes" rows="3" 
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('editSupplierModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" name="edit_supplier" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 shadow-lg">
                        💾 حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal إضافة مورد -->
    <div id="addSupplierModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">➕ إضافة مورد جديد</h3>
                <button onclick="closeModal('addSupplierModal')" class="text-gray-400 hover:text-gray-600 text-2xl">✕</button>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <?php echo csrf_input('csrf_token'); ?>
                <!-- معلومات أساسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">📋 المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رمز المورد *</label>
                            <input type="text" name="supplier_code" required 
                                   placeholder="SUP001"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم المورد *</label>
                            <input type="text" name="name" required 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الشخص المسؤول</label>
                            <input type="text" name="contact_person" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع الخدمة/المواد *</label>
                            <input type="text" name="service_type" required 
                                   placeholder="مواد بناء، أدوات كهربائية..."
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات الاتصال -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">📞 معلومات الاتصال</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الهاتف</label>
                            <input type="text" name="phone" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الموبايل</label>
                            <input type="text" name="mobile" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">العنوان</label>
                            <input type="text" name="address" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات تجارية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">🏢 المعلومات التجارية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">الرقم الضريبي</label>
                            <input type="text" name="tax_number" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">السجل التجاري</label>
                            <input type="text" name="commercial_registration" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">شروط الدفع</label>
                            <input type="text" name="payment_terms" 
                                   placeholder="30 يوم، نقداً..."
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات بنكية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">🏦 المعلومات البنكية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم البنك</label>
                            <input type="text" name="bank_name" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الحساب البنكي</label>
                            <input type="text" name="bank_account" 
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- ملاحظات -->
                <div>
                    <label class="block text-sm font-medium mb-2">ملاحظات</label>
                    <textarea name="notes" rows="3" 
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('addSupplierModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" name="add_supplier" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 shadow-lg">
                        ✅ إضافة المورد
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const suppliersData = <?= json_encode($suppliers, JSON_UNESCAPED_UNICODE) ?>;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function viewSupplier(id) {
            const supplier = suppliersData.find(s => s.id == id);
            if (!supplier) return;
            
            alert(`معلومات المورد:\n\nالرمز: ${supplier.supplier_code}\nالاسم: ${supplier.name}\nنوع الخدمة: ${supplier.service_type}\nعدد الفواتير: ${supplier.invoice_count}\nإجمالي المبالغ: ${parseFloat(supplier.total_invoices_amount).toLocaleString()} ل.ل\nالمدفوع: ${parseFloat(supplier.total_paid_amount).toLocaleString()} ل.ل\nالمتبقي: ${parseFloat(supplier.total_remaining_amount).toLocaleString()} ل.ل`);
        }

        function editSupplier(id) {
            const supplier = suppliersData.find(s => s.id == id);
            if (!supplier) return;
            
            openModal('editSupplierModal');
            
            // ملء النموذج
            document.getElementById('edit_supplier_id').value = supplier.id;
            document.getElementById('edit_supplier_code').value = supplier.supplier_code || '';
            document.getElementById('edit_name').value = supplier.name || '';
            document.getElementById('edit_contact_person').value = supplier.contact_person || '';
            document.getElementById('edit_service_type').value = supplier.service_type || '';
            document.getElementById('edit_phone').value = supplier.phone || '';
            document.getElementById('edit_mobile').value = supplier.mobile || '';
            document.getElementById('edit_email').value = supplier.email || '';
            document.getElementById('edit_address').value = supplier.address || '';
            document.getElementById('edit_tax_number').value = supplier.tax_number || '';
            document.getElementById('edit_commercial_registration').value = supplier.commercial_registration || '';
            document.getElementById('edit_payment_terms').value = supplier.payment_terms || '';
            document.getElementById('edit_bank_name').value = supplier.bank_name || '';
            document.getElementById('edit_bank_account').value = supplier.bank_account || '';
            document.getElementById('edit_is_active').checked = supplier.is_active == 1;
            document.getElementById('edit_notes').value = supplier.notes || '';
        }

        function deleteSupplier(id, name) {
            if (confirm(`هل أنت متأكد من حذف المورد "${name}"؟\n\nملاحظة: لا يمكن الحذف إذا كان هناك فواتير مرتبطة`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="supplier_id" value="${id}">
                    <input type="hidden" name="delete_supplier" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // إغلاق المودال عند النقر خارجه
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>

