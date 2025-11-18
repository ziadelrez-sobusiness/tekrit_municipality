<?php
// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة تبرع جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_donation'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $donor_name = trim($_POST['donor_name']);
        $donor_type = $_POST['donor_type'];
        $donor_phone = trim($_POST['donor_phone']);
        $donor_email = trim($_POST['donor_email']);
        $donation_type = $_POST['donation_type'];
        $amount = floatval($_POST['amount']);
        $currency_id = intval($_POST['currency_id']);
        $items_description = trim($_POST['items_description']);
        $estimated_value = floatval($_POST['estimated_value']);
        $purpose = trim($_POST['purpose']);
        $allocated_to_project_id = !empty($_POST['allocated_to_project_id']) ? intval($_POST['allocated_to_project_id']) : null;
        
        if (!empty($donor_name)) {
            try {
                // توليد رقم التبرع
                $donation_number = 'DON' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $query = "INSERT INTO donations (donation_number, donor_name, donor_type, donor_phone, donor_email, 
                         donation_type, amount, currency_id, items_description, estimated_value, estimated_value_currency_id, 
                         purpose, allocated_to_project_id, received_by_user_id, received_date, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'مستلم')";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $donation_number, $donor_name, $donor_type, $donor_phone, $donor_email,
                    $donation_type, $amount, $currency_id, $items_description, $estimated_value, $currency_id,
                    $purpose, $allocated_to_project_id, $user['id']
                ]);
                
                $message = 'تم إضافة التبرع بنجاح! رقم التبرع: ' . $donation_number;
            } catch (PDOException $e) {
                $error = 'خطأ في إضافة التبرع: ' . $e->getMessage();
            }
        } else {
            $error = 'يرجى تعبئة الحقول المطلوبة';
        }
    }
}

// معالجة تحديث حالة التبرع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!csrf_protect(false)) {
        $error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $donation_id = intval($_POST['donation_id']);
    $status = $_POST['status'];
    
        try {
            $query = "UPDATE donations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$status, $donation_id]);
            $message = 'تم تحديث حالة التبرع بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث التبرع: ' . $e->getMessage();
        }
    }
}

// جلب التبرعات
try {
    $filter_status = $_GET['status'] ?? '';
    $filter_type = $_GET['type'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_status)) {
        $where_conditions[] = "d.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_type)) {
        $where_conditions[] = "d.donation_type = ?";
        $params[] = $filter_type;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT d.*, 
               c.currency_symbol, c.currency_code,
               p.project_name,
               u.full_name as received_by_name
        FROM donations d 
        LEFT JOIN currencies c ON d.currency_id = c.id
        LEFT JOIN projects p ON d.allocated_to_project_id = p.id
        LEFT JOIN users u ON d.received_by_user_id = u.id
        $where_clause
        ORDER BY d.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute($params);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إحصائيات التبرعات
    $stmt = $db->query("
        SELECT 
            d.status,
            COUNT(*) as count,
            SUM(CASE WHEN d.donation_type = 'نقدي' THEN d.amount * c.exchange_rate_to_iqd ELSE 0 END) as total_cash_lbp,
            SUM(CASE WHEN d.donation_type = 'عيني' THEN d.estimated_value * c.exchange_rate_to_iqd ELSE 0 END) as total_items_lbp
        FROM donations d
        LEFT JOIN currencies c ON d.currency_id = c.id
        GROUP BY d.status
    ");
    $donation_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_donations,
            SUM(CASE WHEN d.donation_type = 'نقدي' THEN d.amount * c.exchange_rate_to_iqd ELSE 0 END) as total_cash_lbp,
            SUM(CASE WHEN d.donation_type = 'عيني' THEN d.estimated_value * c.exchange_rate_to_iqd ELSE 0 END) as total_items_lbp,
            COUNT(DISTINCT d.donor_name) as unique_donors
        FROM donations d
        LEFT JOIN currencies c ON d.currency_id = c.id
    ");
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب العملات
    $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المشاريع
    $stmt = $db->query("SELECT id, project_name FROM projects WHERE status IN ('مخطط', 'قيد التنفيذ') ORDER BY project_name");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $donations = [];
    $donation_stats = [];
    $general_stats = ['total_donations' => 0, 'total_cash_lbp' => 0, 'total_items_lbp' => 0, 'unique_donors' => 0];
    $currencies = [];
    $projects = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التبرعات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة التبرعات</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addDonationModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ تبرع جديد
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة وتتبع التبرعات النقدية والعينية للبلدية</p>
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

        <!-- إحصائيات التبرعات -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي التبرعات</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $general_stats['total_donations'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">🎁</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">التبرعات النقدية</p>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($general_stats['total_cash_lbp']) ?> ل.ل</p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">💰</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">التبرعات العينية</p>
                        <p class="text-2xl font-bold text-purple-600"><?= number_format($general_stats['total_items_lbp']) ?> ل.ل</p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">📦</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">المتبرعون</p>
                        <p class="text-2xl font-bold text-orange-600"><?= $general_stats['unique_donors'] ?></p>
                    </div>
                    <div class="bg-orange-100 text-orange-600 p-3 rounded-full">👥</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
            <div class="flex flex-wrap gap-4">
                <select onchange="filterDonations()" id="statusFilter" class="px-3 py-2 border rounded-lg">
                    <option value="">جميع الحالات</option>
                    <option value="مستلم" <?= $filter_status === 'مستلم' ? 'selected' : '' ?>>مستلم</option>
                    <option value="قيد المراجعة" <?= $filter_status === 'قيد المراجعة' ? 'selected' : '' ?>>قيد المراجعة</option>
                    <option value="موافق عليه" <?= $filter_status === 'موافق عليه' ? 'selected' : '' ?>>موافق عليه</option>
                    <option value="قيد التوزيع" <?= $filter_status === 'قيد التوزيع' ? 'selected' : '' ?>>قيد التوزيع</option>
                    <option value="موزع" <?= $filter_status === 'موزع' ? 'selected' : '' ?>>موزع</option>
                </select>
                
                <select onchange="filterDonations()" id="typeFilter" class="px-3 py-2 border rounded-lg">
                    <option value="">جميع الأنواع</option>
                    <option value="نقدي" <?= $filter_type === 'نقدي' ? 'selected' : '' ?>>نقدي</option>
                    <option value="عيني" <?= $filter_type === 'عيني' ? 'selected' : '' ?>>عيني</option>
                    <option value="خدمي" <?= $filter_type === 'خدمي' ? 'selected' : '' ?>>خدمي</option>
                </select>
            </div>
        </div>

        <!-- جدول التبرعات -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">قائمة التبرعات</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-right p-4 font-semibold">رقم التبرع</th>
                            <th class="text-right p-4 font-semibold">المتبرع</th>
                            <th class="text-right p-4 font-semibold">النوع</th>
                            <th class="text-right p-4 font-semibold">المبلغ/القيمة</th>
                            <th class="text-right p-4 font-semibold">الغرض</th>
                            <th class="text-right p-4 font-semibold">الحالة</th>
                            <th class="text-right p-4 font-semibold">التاريخ</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as $donation): ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-4 font-medium"><?= htmlspecialchars($donation['donation_number']) ?></td>
                            <td class="p-4">
                                <div class="font-medium"><?= htmlspecialchars($donation['donor_name']) ?></div>
                                <div class="text-sm text-slate-500"><?= htmlspecialchars($donation['donor_type']) ?></div>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm <?= $donation['donation_type'] === 'نقدي' ? 'bg-green-100 text-green-800' : ($donation['donation_type'] === 'عيني' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800') ?>">
                                    <?= htmlspecialchars($donation['donation_type']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <?php if ($donation['donation_type'] === 'نقدي'): ?>
                                    <span class="font-medium"><?= number_format($donation['amount']) ?> <?= htmlspecialchars($donation['currency_symbol']) ?></span>
                                <?php elseif ($donation['donation_type'] === 'عيني'): ?>
                                    <span class="text-sm"><?= number_format($donation['estimated_value']) ?> <?= htmlspecialchars($donation['currency_symbol']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-500">خدمة</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-sm"><?= htmlspecialchars(substr($donation['purpose'], 0, 50)) ?><?= strlen($donation['purpose']) > 50 ? '...' : '' ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?= $donation['status'] === 'مستلم' ? 'bg-blue-100 text-blue-800' : 
                                        ($donation['status'] === 'موافق عليه' ? 'bg-green-100 text-green-800' : 
                                        ($donation['status'] === 'موزع' ? 'bg-purple-100 text-purple-800' : 'bg-yellow-100 text-yellow-800')) ?>">
                                    <?= htmlspecialchars($donation['status']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-sm"><?= date('Y-m-d', strtotime($donation['received_date'])) ?></td>
                            <td class="p-4">
                                <button onclick="updateDonationStatus(<?= $donation['id'] ?>, '<?= htmlspecialchars($donation['status']) ?>')" 
                                        class="text-blue-600 hover:text-blue-800">
                                    تحديث الحالة
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة تبرع جديد -->
    <div id="addDonationModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">إضافة تبرع جديد</h3>
                <button onclick="closeModal('addDonationModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-4">
                <?php echo csrf_input('csrf_token'); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">اسم المتبرع *</label>
                        <input type="text" name="donor_name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">نوع المتبرع *</label>
                        <select name="donor_type" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">اختر نوع المتبرع</option>
                            <option value="فرد">فرد</option>
                            <option value="شركة">شركة</option>
                            <option value="منظمة">منظمة</option>
                            <option value="جهة حكومية">جهة حكومية</option>
                            <option value="منظمة دولية">منظمة دولية</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">رقم الهاتف</label>
                        <input type="text" name="donor_phone" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                        <input type="email" name="donor_email" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">نوع التبرع *</label>
                        <select name="donation_type" id="donation_type" required onchange="toggleDonationFields()" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">اختر نوع التبرع</option>
                            <option value="نقدي">نقدي</option>
                            <option value="عيني">عيني</option>
                            <option value="خدمي">خدمي</option>
                        </select>
                    </div>
                    
                    <div id="cash_fields" style="display: none;">
                        <label class="block text-sm font-medium mb-2">المبلغ</label>
                        <div class="flex gap-2">
                            <input type="number" name="amount" step="0.01" class="flex-1 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <select name="currency_id" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= $currency['currency_code'] === 'IQD' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_symbol']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="items_fields" class="md:col-span-2" style="display: none;">
                        <label class="block text-sm font-medium mb-2">وصف المواد</label>
                        <textarea name="items_description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        
                        <label class="block text-sm font-medium mb-2 mt-4">القيمة التقديرية</label>
                        <input type="number" name="estimated_value" step="0.01" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">الغرض من التبرع</label>
                        <textarea name="purpose" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">تخصيص للمشروع (اختياري)</label>
                        <select name="allocated_to_project_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">بدون تخصيص محدد</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('addDonationModal')" class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        إلغاء
                    </button>
                    <button type="submit" name="add_donation" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        إضافة التبرع
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function filterDonations() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            const url = new URL(window.location.href);
            
            if (status) url.searchParams.set('status', status);
            else url.searchParams.delete('status');
            
            if (type) url.searchParams.set('type', type);
            else url.searchParams.delete('type');
            
            window.location.href = url.toString();
        }

        function toggleDonationFields() {
            const donationType = document.getElementById('donation_type').value;
            const cashFields = document.getElementById('cash_fields');
            const itemsFields = document.getElementById('items_fields');
            
            if (donationType === 'نقدي') {
                cashFields.style.display = 'block';
                itemsFields.style.display = 'none';
            } else if (donationType === 'عيني') {
                cashFields.style.display = 'none';
                itemsFields.style.display = 'block';
            } else {
                cashFields.style.display = 'none';
                itemsFields.style.display = 'none';
            }
        }

        function updateDonationStatus(donationId, currentStatus) {
            const newStatus = prompt('أدخل الحالة الجديدة للتبرع:', currentStatus);
            if (newStatus && newStatus !== currentStatus) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="donation_id" value="${donationId}">
                    <input type="hidden" name="status" value="${newStatus}">
                    <input type="hidden" name="update_status" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 
