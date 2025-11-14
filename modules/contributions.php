<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");
header('Content-Type: text/html; charset=utf-8');

$user = $auth->getUserInfo();
$message = '';
$error = '';

// معالجة إضافة مساهمة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contribution'])) {
    try {
        $db->beginTransaction();
        
        $project_id = intval($_POST['project_id']);
        $contributor_name = trim($_POST['contributor_name']);
        $contributor_phone = trim($_POST['contributor_phone']);
        $contributor_email = trim($_POST['contributor_email']);
        $contributor_address = trim($_POST['contributor_address']);
        
        $contribution_amount = floatval($_POST['contribution_amount']);
        $currency_id = intval($_POST['currency_id']);
        $contribution_date = $_POST['contribution_date'];
        
        $payment_method = $_POST['payment_method'];
        $bank_name = trim($_POST['bank_name']);
        $check_number = trim($_POST['check_number']);
        $reference_number = trim($_POST['reference_number']);
        $receipt_number = trim($_POST['receipt_number']);
        
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        $notes = trim($_POST['notes']);
        
        // التحقق من أن المشروع يقبل مساهمات
        $stmt = $db->prepare("SELECT allow_public_contributions FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project || !$project['allow_public_contributions']) {
            throw new Exception('هذا المشروع لا يقبل مساهمات شعبية');
        }
        
        // إضافة المساهمة
        $stmt = $db->prepare("INSERT INTO project_contributions 
            (project_id, contributor_name, contributor_phone, contributor_email, contributor_address,
             contribution_amount, currency_id, contribution_date, payment_method,
             bank_name, check_number, reference_number, receipt_number,
             is_anonymous, notes, is_verified, verified_by, verified_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?)");
        
        $stmt->execute([$project_id, $contributor_name, $contributor_phone, $contributor_email, $contributor_address,
                       $contribution_amount, $currency_id, $contribution_date, $payment_method,
                       $bank_name, $check_number, $reference_number, $receipt_number,
                       $is_anonymous, $notes, $user['id'], $user['id']]);
        
        $contribution_id = $db->lastInsertId();
        
        // تحديث contributions_collected في المشروع
        $stmt = $db->prepare("UPDATE projects 
                              SET contributions_collected = contributions_collected + ? 
                              WHERE id = ?");
        $stmt->execute([$contribution_amount, $project_id]);
        
        // إنشاء معاملة مالية تلقائياً
        $stmt = $db->prepare("INSERT INTO financial_transactions 
            (transaction_date, type, category, description, amount, currency_id,
             payment_method, bank_name, check_number, reference_number,
             related_project_id, created_by, status)
            VALUES (?, 'إيراد', 'مساهمات شعبية', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'معتمد')");
        
        $description = 'مساهمة من: ' . $contributor_name . ' في مشروع رقم ' . $project_id;
        
        $stmt->execute([$contribution_date, $description, $contribution_amount, $currency_id,
                       $payment_method, $bank_name, $check_number, $reference_number,
                       $project_id, $user['id']]);
        
        $transaction_id = $db->lastInsertId();
        
        // ربط المساهمة بالمعاملة المالية
        $stmt = $db->prepare("UPDATE project_contributions 
                              SET financial_transaction_id = ? 
                              WHERE id = ?");
        $stmt->execute([$transaction_id, $contribution_id]);
        
        $db->commit();
        
        $message = 'تم إضافة المساهمة بنجاح وربطها بالمعاملات المالية!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'خطأ في إضافة المساهمة: ' . $e->getMessage();
    }
}

// الفلاتر
$filter_project = $_GET['project_id'] ?? '';
$filter_verified = $_GET['verified'] ?? '';
$filter_currency = $_GET['currency_id'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filter_project)) {
    $where_conditions[] = "pc.project_id = ?";
    $params[] = $filter_project;
}

if ($filter_verified !== '') {
    $where_conditions[] = "pc.is_verified = ?";
    $params[] = $filter_verified;
}

if (!empty($filter_currency)) {
    $where_conditions[] = "pc.currency_id = ?";
    $params[] = $filter_currency;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// جلب المساهمات
$stmt = $db->prepare("
    SELECT pc.*,
           p.project_name,
           c.currency_symbol,
           c.currency_code,
           u.full_name as verified_by_name
    FROM project_contributions pc
    INNER JOIN projects p ON pc.project_id = p.id
    INNER JOIN currencies c ON pc.currency_id = c.id
    LEFT JOIN users u ON pc.verified_by = u.id
    $where_clause
    ORDER BY pc.contribution_date DESC, pc.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// الإحصائيات
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_contributions,
        COUNT(DISTINCT project_id) as projects_count,
        COUNT(DISTINCT contributor_name) as contributors_count,
        SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_count
    FROM project_contributions
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// إحصائيات حسب العملة
$stmt = $db->query("
    SELECT 
        c.currency_code,
        c.currency_symbol,
        COUNT(pc.id) as count,
        SUM(pc.contribution_amount) as total
    FROM project_contributions pc
    INNER JOIN currencies c ON pc.currency_id = c.id
    WHERE pc.is_verified = 1
    GROUP BY c.id, c.currency_code, c.currency_symbol
    ORDER BY total DESC
");
$stats_by_currency = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب المشاريع التي تقبل مساهمات
// أولاً: التحقق من أسماء الأعمدة الموجودة
$columns_query = $db->query("SHOW COLUMNS FROM projects");
$existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);

// بناء استعلام ديناميكي بناءً على الأعمدة الموجودة
$name_field = 'CONCAT("مشروع #", p.id)';
if (in_array('name', $existing_columns)) {
    $name_field = 'p.name';
} elseif (in_array('project_name', $existing_columns)) {
    $name_field = 'p.project_name';
} elseif (in_array('title', $existing_columns)) {
    $name_field = 'p.title';
} elseif (in_array('project_title', $existing_columns)) {
    $name_field = 'p.project_title';
}

$target_field = '0';
if (in_array('target_amount', $existing_columns)) {
    $target_field = 'IFNULL(p.target_amount, 0)';
} elseif (in_array('contributions_target', $existing_columns)) {
    $target_field = 'IFNULL(p.contributions_target, 0)';
}

$collected_field = '0';
if (in_array('contributions_collected', $existing_columns)) {
    $collected_field = 'IFNULL(p.contributions_collected, 0)';
} elseif (in_array('collected_amount', $existing_columns)) {
    $collected_field = 'IFNULL(p.collected_amount, 0)';
}

$currency_field = '(SELECT id FROM currencies WHERE is_default = 1 LIMIT 1)';
if (in_array('currency_id', $existing_columns)) {
    $currency_field = 'IFNULL(p.currency_id, (SELECT id FROM currencies WHERE is_default = 1 LIMIT 1))';
} elseif (in_array('contributions_currency_id', $existing_columns)) {
    $currency_field = 'IFNULL(p.contributions_currency_id, (SELECT id FROM currencies WHERE is_default = 1 LIMIT 1))';
}

$stmt = $db->query("
    SELECT 
        p.id, 
        $name_field as project_name,
        $target_field as contributions_target,
        $collected_field as contributions_collected,
        $currency_field as contributions_currency_id,
        c.currency_symbol,
        c.currency_code
    FROM projects p
    LEFT JOIN currencies c ON $currency_field = c.id
    WHERE p.allow_public_contributions = 1
    ORDER BY project_name
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب العملات
$stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المساهمات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none !important; }
        .modal.active { display: flex !important; }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">💰 إدارة المساهمات الشعبية</h1>
                    <p class="text-gray-600 mt-2">إدارة وتتبع المساهمات الشعبية في المشاريع</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="openModal('addContributionModal')" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition shadow-lg">
                        ➕ إضافة مساهمة
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition shadow-lg">
                        ← العودة
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
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-blue-500">
                <p class="text-sm text-gray-500">إجمالي المساهمات</p>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($stats['total_contributions']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-green-500">
                <p class="text-sm text-gray-500">عدد المشاريع</p>
                <p class="text-3xl font-bold text-green-600"><?= number_format($stats['projects_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-purple-500">
                <p class="text-sm text-gray-500">عدد المساهمين</p>
                <p class="text-3xl font-bold text-purple-600"><?= number_format($stats['contributors_count']) ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border-r-4 border-yellow-500">
                <p class="text-sm text-gray-500">محققة</p>
                <p class="text-3xl font-bold text-yellow-600"><?= number_format($stats['verified_count']) ?></p>
            </div>
        </div>

        <!-- إحصائيات حسب العملة -->
        <?php if (!empty($stats_by_currency)): ?>
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4 text-lg">📊 إحصائيات المساهمات حسب العملة</h3>
            <div class="grid grid-cols-1 md:grid-cols-<?= count($stats_by_currency) > 3 ? '4' : count($stats_by_currency) ?> gap-4">
                <?php foreach ($stats_by_currency as $stat): ?>
                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($stat['currency_code']) ?></p>
                    <p class="text-2xl font-bold text-indigo-600">
                        <?= number_format($stat['total'], 0) ?> <?= htmlspecialchars($stat['currency_symbol']) ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?= number_format($stat['count']) ?> مساهمة</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- فلاتر -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4 text-lg">🔍 البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">المشروع</label>
                    <select name="project_id" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع المشاريع</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= ($filter_project == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">العملة</label>
                    <select name="currency_id" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">جميع العملات</option>
                        <?php foreach ($currencies as $curr): ?>
                            <option value="<?= $curr['id'] ?>" <?= ($filter_currency == $curr['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curr['currency_name']) ?> (<?= htmlspecialchars($curr['currency_symbol']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">الحالة</label>
                    <select name="verified" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">الكل</option>
                        <option value="1" <?= ($filter_verified === '1') ? 'selected' : '' ?>>محققة</option>
                        <option value="0" <?= ($filter_verified === '0') ? 'selected' : '' ?>>غير محققة</option>
                    </select>
                </div>
                
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                        بحث
                    </button>
                    <a href="contributions.php" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">
                        إعادة
                    </a>
                    <a href="projects_unified.php" class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700">
                        🏗️ المشاريع
                    </a>
                </div>
            </form>
        </div>

        <!-- جدول المساهمات -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-right p-4 font-semibold">#</th>
                            <th class="text-right p-4 font-semibold">المشروع</th>
                            <th class="text-right p-4 font-semibold">المساهم</th>
                            <th class="text-right p-4 font-semibold">المبلغ</th>
                            <th class="text-right p-4 font-semibold">التاريخ</th>
                            <th class="text-right p-4 font-semibold">طريقة الدفع</th>
                            <th class="text-right p-4 font-semibold">الحالة</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (empty($contributions)): ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-500">
                                📭 لا توجد مساهمات
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($contributions as $index => $cont): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-4 font-semibold text-gray-600"><?= $index + 1 ?></td>
                                <td class="p-4">
                                    <div class="font-semibold text-blue-600"><?= htmlspecialchars($cont['project_name']) ?></div>
                                    <?php if (!empty($cont['receipt_number'])): ?>
                                    <div class="text-xs text-gray-500">إيصال: <?= htmlspecialchars($cont['receipt_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <?php if ($cont['is_anonymous']): ?>
                                        <span class="text-gray-500 italic">مساهم مجهول</span>
                                    <?php else: ?>
                                        <div class="font-semibold"><?= htmlspecialchars($cont['contributor_name']) ?></div>
                                        <?php if (!empty($cont['contributor_phone'])): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($cont['contributor_phone']) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-green-600">
                                        <?= number_format($cont['contribution_amount'], 0) ?> <?= htmlspecialchars($cont['currency_symbol']) ?>
                                    </div>
                                </td>
                                <td class="p-4 text-sm"><?= date('Y-m-d', strtotime($cont['contribution_date'])) ?></td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded text-xs bg-gray-100">
                                        <?= htmlspecialchars($cont['payment_method']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <?php if ($cont['is_verified']): ?>
                                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">✓ محققة</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">⏳ معلقة</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2">
                                        <button onclick="viewContribution(<?= $cont['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm" title="عرض">
                                            👁️
                                        </button>
                                        <button onclick="printReceipt(<?= $cont['id'] ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="طباعة إيصال">
                                            🖨️
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

    <!-- Modal إضافة مساهمة -->
    <div id="addContributionModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg sticky top-0">
                <h3 class="text-xl font-semibold">➕ إضافة مساهمة جديدة</h3>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <!-- المشروع -->
                <div>
                    <label class="block text-sm font-medium mb-2">المشروع *</label>
                    <select name="project_id" required class="w-full px-4 py-2 border rounded-lg" onchange="showProjectInfo(this.value)">
                        <option value="">اختر المشروع</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" 
                                    data-target="<?= $proj['contributions_target'] ?? 0 ?>"
                                    data-collected="<?= $proj['contributions_collected'] ?? 0 ?>"
                                    data-currency="<?= $proj['contributions_currency_id'] ?? '' ?>"
                                    data-currency-symbol="<?= htmlspecialchars($proj['currency_symbol'] ?? '$') ?>">
                                <?= htmlspecialchars($proj['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="projectInfo" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded hidden"></div>
                </div>
                
                <!-- معلومات المساهم -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">👤 معلومات المساهم</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم المساهم *</label>
                            <input type="text" name="contributor_name" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الهاتف</label>
                            <input type="text" name="contributor_phone" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                            <input type="email" name="contributor_email" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">العنوان</label>
                            <input type="text" name="contributor_address" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_anonymous" class="w-4 h-4">
                                <span class="text-sm">مساهمة مجهولة (لن يتم عرض اسم المساهم علناً)</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات المساهمة -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">💰 معلومات المساهمة</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">المبلغ *</label>
                            <input type="number" name="contribution_amount" required step="0.01" min="1" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">العملة *</label>
                            <select name="currency_id" id="currency_id" required class="w-full px-4 py-2 border rounded-lg">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= $currency['id'] ?>" <?= ($currency['is_default']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ المساهمة *</label>
                            <input type="date" name="contribution_date" required value="<?= date('Y-m-d') ?>"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                </div>
                
                <!-- معلومات الدفع -->
                <div class="border-b pb-4">
                    <h4 class="font-semibold mb-4 text-gray-700">💳 معلومات الدفع</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">طريقة الدفع *</label>
                            <select name="payment_method" required class="w-full px-4 py-2 border rounded-lg">
                                <option value="نقد">نقد</option>
                                <option value="شيك">شيك</option>
                                <option value="تحويل مصرفي">تحويل مصرفي</option>
                                <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الإيصال</label>
                            <input type="text" name="receipt_number" 
                                   class="w-full px-4 py-2 border rounded-lg"
                                   placeholder="سيتم إنشاؤه تلقائياً إذا ترك فارغاً">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم البنك</label>
                            <input type="text" name="bank_name" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الشيك</label>
                            <input type="text" name="check_number" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الرقم المرجعي</label>
                            <input type="text" name="reference_number" 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                </div>
                
                <!-- ملاحظات -->
                <div>
                    <label class="block text-sm font-medium mb-2">ملاحظات</label>
                    <textarea name="notes" rows="3" 
                              class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal('addContributionModal')" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 border rounded-lg">
                        إلغاء
                    </button>
                    <button type="submit" name="add_contribution" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                        ✅ إضافة المساهمة
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

        function showProjectInfo(projectId) {
            const select = document.querySelector('select[name="project_id"]');
            const option = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('projectInfo');
            
            console.log('=== showProjectInfo Debug ===');
            console.log('projectId:', projectId);
            console.log('option:', option);
            console.log('option.value:', option ? option.value : 'N/A');
            console.log('infoDiv:', infoDiv);
            
            // تبسيط الشرط - يكفي أن يكون projectId موجود
            if (projectId && option) {
                const target = parseFloat(option.dataset.target) || 0;
                const collected = parseFloat(option.dataset.collected) || 0;
                const currencySymbol = option.dataset.currencySymbol || '$';
                const currencyId = option.dataset.currency;
                const remaining = target - collected;
                const percentage = target > 0 ? (collected / target * 100).toFixed(1) : 0;
                
                console.log('Data from option:', {
                    target: option.dataset.target,
                    collected: option.dataset.collected,
                    currencySymbol: option.dataset.currencySymbol,
                    currencyId: option.dataset.currency
                });
                console.log('Parsed values:', { target, collected, remaining, percentage });
                
                // تحديث العملة تلقائياً إذا كانت محددة للمشروع
                if (currencyId) {
                    const currencySelect = document.getElementById('currency_id');
                    if (currencySelect) {
                        currencySelect.value = currencyId;
                    }
                }
                
                // تحديد الحالة
                let statusText = '';
                let statusColor = '';
                if (target === 0) {
                    statusText = '⚠️ لم يتم تحديد هدف المساهمات';
                    statusColor = 'text-gray-600';
                } else if (collected >= target) {
                    statusText = '🎉 تم الوصول للهدف!';
                    statusColor = 'text-green-600';
                } else if (collected >= target * 0.75) {
                    statusText = '🔥 قارب على الهدف';
                    statusColor = 'text-orange-600';
                } else {
                    statusText = '📊 جاري جمع المساهمات';
                    statusColor = 'text-blue-600';
                }
                
                infoDiv.innerHTML = `
                    <div class="text-sm space-y-2">
                        <p class="font-semibold text-blue-800 mb-2">📊 معلومات المشروع:</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="p-2 bg-white rounded">
                                <p class="text-xs text-gray-600">الهدف</p>
                                <p class="font-bold text-blue-600">${target.toLocaleString('en-US', {minimumFractionDigits: 0})} ${currencySymbol}</p>
                            </div>
                            <div class="p-2 bg-white rounded">
                                <p class="text-xs text-gray-600">المُجمّع</p>
                                <p class="font-bold text-green-600">${collected.toLocaleString('en-US', {minimumFractionDigits: 0})} ${currencySymbol}</p>
                            </div>
                            <div class="p-2 bg-white rounded">
                                <p class="text-xs text-gray-600">المتبقي</p>
                                <p class="font-bold ${remaining > 0 ? 'text-orange-600' : 'text-green-600'}">${remaining.toLocaleString('en-US', {minimumFractionDigits: 0})} ${currencySymbol}</p>
                            </div>
                            <div class="p-2 bg-white rounded">
                                <p class="text-xs text-gray-600">نسبة الإنجاز</p>
                                <p class="font-bold text-purple-600">${percentage}%</p>
                            </div>
                        </div>
                        ${target > 0 ? `
                        <div class="mt-2">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: ${Math.min(percentage, 100)}%"></div>
                            </div>
                        </div>` : ''}
                        <p class="text-center ${statusColor} font-semibold mt-2">${statusText}</p>
                    </div>
                `;
                infoDiv.classList.remove('hidden');
                console.log('✅ Info displayed successfully!');
                console.log('infoDiv classes:', infoDiv.className);
            } else {
                console.log('❌ Condition failed - hiding info');
                infoDiv.classList.add('hidden');
            }
            console.log('=== End Debug ===');
        }

        function viewContribution(id) {
            // يمكن تطوير modal للعرض لاحقاً
            alert('عرض تفاصيل المساهمة رقم: ' + id);
        }

        function printReceipt(id) {
            window.open('print_contribution_receipt.php?id=' + id, '_blank');
        }

        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }

        // تفعيل عرض معلومات المشروع تلقائياً عند تحميل الصفحة (بدون فتح modal)
        document.addEventListener('DOMContentLoaded', function() {
            // لا نفتح modal تلقائياً
            // فقط إذا كان هناك مشروع محدد في modal المفتوح
        });
    </script>
</body>
</html>

