<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
$db->exec("SET CHARACTER SET utf8mb4");

$user = $auth->getUserInfo();
$committeeId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$activeTab = $_GET['tab'] ?? 'overview';

if ($committeeId <= 0) {
    header('Location: municipality_management.php?tab=committees');
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT mc.*, d.department_name
        FROM municipal_committees mc
        LEFT JOIN departments d ON mc.department_id = d.id
        WHERE mc.id = ?
    ");
    $stmt->execute([$committeeId]);
    $committee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$committee) {
        throw new Exception('اللجنة غير موجودة');
    }
} catch (Exception $e) {
    header('Location: municipality_management.php?tab=committees&error=' . urlencode($e->getMessage()));
    exit;
}

$message = '';
$error = '';

// جلب البيانات المشتركة
$currencies = $db->query("SELECT id, currency_name, currency_symbol FROM currencies WHERE is_active = 1 ORDER BY is_default DESC, currency_name")->fetchAll(PDO::FETCH_ASSOC);

/**
 * إعادة توجيه سريعة بعد العمليات
 */
function redirectWithTab(int $committeeId, string $tab, array $extra = []): void
{
    $params = array_merge(['id' => $committeeId, 'tab' => $tab], $extra);
    header('Location: committee_dashboard.php?' . http_build_query($params));
    exit;
}

// معالجة إضافة حركة مالية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $targetId = intval($_POST['committee_id'] ?? 0);
    if ($targetId !== $committeeId) {
        redirectWithTab($committeeId, 'finance', ['error' => 'اللجنة المحددة غير صحيحة']);
    }

    try {
        $transactionType = $_POST['transaction_type'];
        $amount = floatval($_POST['amount']);
        $currencyId = !empty($_POST['currency_id']) ? intval($_POST['currency_id']) : null;
        $exchangeRate = !empty($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : 1.0;
        $transactionDate = $_POST['transaction_date'] ?: date('Y-m-d');
        $description = trim($_POST['description'] ?? '');
        $source = trim($_POST['source'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!in_array($transactionType, ['إيراد', 'مصروف'], true)) {
            throw new Exception('نوع الحركة المالية غير صالح');
        }

        if ($amount <= 0) {
            throw new Exception('يجب أن يكون المبلغ أكبر من صفر');
        }

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO committee_finance_transactions
                (committee_id, transaction_date, transaction_type, amount, currency_id, exchange_rate, description, source, notes, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $committeeId,
            $transactionDate,
            $transactionType,
            $amount,
            $currencyId,
            $exchangeRate,
            $description ?: null,
            $source ?: null,
            $notes ?: null,
            $user['id'] ?? null
        ]);

        $incomeDelta = $transactionType === 'إيراد' ? $amount : 0;
        $expenseDelta = $transactionType === 'مصروف' ? $amount : 0;

        $stmt = $db->prepare("
            INSERT INTO committee_finance_summary (committee_id, opening_balance, total_income, total_expense, current_balance)
            VALUES (:committee_id, 0, :income, :expense, :balance)
            ON DUPLICATE KEY UPDATE
                total_income = total_income + VALUES(total_income),
                total_expense = total_expense + VALUES(total_expense),
                current_balance = current_balance + :balance_delta,
                last_updated = CURRENT_TIMESTAMP
        ");
        $balanceDelta = $incomeDelta - $expenseDelta;
        $stmt->execute([
            ':committee_id' => $committeeId,
            ':income' => $incomeDelta,
            ':expense' => $expenseDelta,
            ':balance' => $balanceDelta,
            ':balance_delta' => $balanceDelta
        ]);

        $db->commit();
        redirectWithTab($committeeId, 'finance', ['success' => 'تم تسجيل الحركة المالية بنجاح']);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        redirectWithTab($committeeId, 'finance', ['error' => $e->getMessage()]);
    }
}

// معالجة إضافة محضر جلسة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_session'])) {
    $targetId = intval($_POST['committee_id'] ?? 0);
    if ($targetId !== $committeeId) {
        redirectWithTab($committeeId, 'sessions', ['error' => 'اللجنة المحددة غير صحيحة']);
    }

    try {
        $sessionNumber = trim($_POST['session_number'] ?? '');
        $sessionTitle = trim($_POST['session_title'] ?? '');
        $sessionDate = $_POST['session_date'] ?: date('Y-m-d');
        $sessionTime = $_POST['session_time'] ?: null;
        $location = trim($_POST['location'] ?? '');
        $agenda = trim($_POST['agenda'] ?? '');
        $minutes = trim($_POST['minutes'] ?? '');
        $attachments = trim($_POST['attachments'] ?? '');

        if (empty($sessionTitle)) {
            throw new Exception('عنوان الجلسة مطلوب');
        }

        $stmt = $db->prepare("
            INSERT INTO committee_sessions
                (committee_id, session_number, session_title, session_date, session_time, location, agenda, minutes, attachments, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $committeeId,
            $sessionNumber ?: null,
            $sessionTitle,
            $sessionDate,
            $sessionTime ?: null,
            $location ?: null,
            $agenda ?: null,
            $minutes ?: null,
            $attachments ?: null,
            $user['id'] ?? null
        ]);

        redirectWithTab($committeeId, 'sessions', ['success' => 'تم حفظ محضر الجلسة بنجاح']);
    } catch (Exception $e) {
        redirectWithTab($committeeId, 'sessions', ['error' => $e->getMessage()]);
    }
}

// معالجة إضافة قرار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_decision'])) {
    $targetId = intval($_POST['committee_id'] ?? 0);
    if ($targetId !== $committeeId) {
        redirectWithTab($committeeId, 'decisions', ['error' => 'اللجنة المحددة غير صحيحة']);
    }

    try {
        $sessionId = !empty($_POST['session_id']) ? intval($_POST['session_id']) : null;
        $decisionNumber = trim($_POST['decision_number'] ?? '');
        $decisionTitle = trim($_POST['decision_title'] ?? '');
        $decisionText = trim($_POST['decision_text'] ?? '');
        $dueDate = $_POST['due_date'] ?: null;
        $status = $_POST['status'] ?? 'قيد المتابعة';
        $notes = trim($_POST['notes'] ?? '');

        if (empty($decisionTitle) || empty($decisionText)) {
            throw new Exception('عنوان القرار ونص القرار مطلوبان');
        }

        $stmt = $db->prepare("
            INSERT INTO committee_decisions
                (committee_id, session_id, decision_number, decision_title, decision_text, status, due_date, notes, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $committeeId,
            $sessionId ?: null,
            $decisionNumber ?: null,
            $decisionTitle,
            $decisionText,
            $status,
            $dueDate ?: null,
            $notes ?: null,
            $user['id'] ?? null
        ]);

        redirectWithTab($committeeId, 'decisions', ['success' => 'تم إضافة القرار بنجاح']);
    } catch (Exception $e) {
        redirectWithTab($committeeId, 'decisions', ['error' => $e->getMessage()]);
    }
}

// رسائل من عمليات سابقة
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// جلب الملخص المالي
$stmt = $db->prepare("
    SELECT opening_balance, total_income, total_expense, current_balance, last_updated
    FROM committee_finance_summary
    WHERE committee_id = ?
");
$stmt->execute([$committeeId]);
$financeSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'opening_balance' => 0,
    'total_income' => 0,
    'total_expense' => 0,
    'current_balance' => 0,
    'last_updated' => null
];

// جلب الحركات المالية
$stmt = $db->prepare("
    SELECT cft.*, cur.currency_symbol
    FROM committee_finance_transactions cft
    LEFT JOIN currencies cur ON cft.currency_id = cur.id
    WHERE cft.committee_id = ?
    ORDER BY cft.transaction_date DESC, cft.id DESC
    LIMIT 100
");
$stmt->execute([$committeeId]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الميزانيات الخاصة باللجنة
$stmt = $db->prepare("
    SELECT b.*,
           (SELECT COALESCE(SUM(allocated_amount),0) FROM budget_items WHERE budget_id = b.id) AS total_allocated,
           (SELECT COALESCE(SUM(spent_amount),0) FROM budget_items WHERE budget_id = b.id) AS total_spent
    FROM budgets b
    WHERE b.committee_id = ?
    ORDER BY b.fiscal_year DESC, b.start_date DESC
");
$stmt->execute([$committeeId]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الفواتير الخاصة باللجنة
$stmt = $db->prepare("
    SELECT si.*, s.name AS supplier_name, s.supplier_code,
           c.currency_symbol, c.currency_code
    FROM supplier_invoices si
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    LEFT JOIN currencies c ON si.currency_id = c.id
    WHERE si.committee_id = ?
    ORDER BY si.invoice_date DESC, si.id DESC
    LIMIT 100
");
$stmt->execute([$committeeId]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب طلبات المواطنين المرتبطة باللجنة
$stmt = $db->prepare("
    SELECT cr.id,
           cr.tracking_number,
           cr.citizen_name,
           cr.citizen_phone,
           cr.status,
           cr.priority_level,
           cr.created_at,
           rt.type_name
    FROM citizen_requests cr
    LEFT JOIN request_types rt ON cr.request_type_id = rt.id
    WHERE cr.assigned_to_committee_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 100
");
$stmt->execute([$committeeId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الجلسات الخاصة باللجنة
$stmt = $db->prepare("
    SELECT cs.*
    FROM committee_sessions cs
    WHERE cs.committee_id = ?
    ORDER BY cs.session_date DESC, cs.id DESC
");
$stmt->execute([$committeeId]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب القرارات الخاصة باللجنة
$stmt = $db->prepare("
    SELECT cd.*, cs.session_title, cs.session_date
    FROM committee_decisions cd
    LEFT JOIN committee_sessions cs ON cd.session_id = cs.id
    WHERE cd.committee_id = ?
    ORDER BY cd.created_at DESC
");
$stmt->execute([$committeeId]);
$decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// بيانات التقارير (حركة مالية شهرية)
$stmt = $db->prepare("
    SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS period,
           SUM(CASE WHEN transaction_type = 'إيراد' THEN amount ELSE 0 END) AS total_income,
           SUM(CASE WHEN transaction_type = 'مصروف' THEN amount ELSE 0 END) AS total_expense
    FROM committee_finance_transactions
    WHERE committee_id = ?
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
    ORDER BY period ASC
");
$stmt->execute([$committeeId]);
$financeSeries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reportLabels = array_map(fn($row) => $row['period'], $financeSeries);
$reportIncome = array_map(fn($row) => floatval($row['total_income']), $financeSeries);
$reportExpense = array_map(fn($row) => floatval($row['total_expense']), $financeSeries);

// طلبات حسب الحالة
$stmt = $db->prepare("
    SELECT status, COUNT(*) AS total
    FROM citizen_requests
    WHERE assigned_to_committee_id = ?
    GROUP BY status
");
$stmt->execute([$committeeId]);
$requestStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة لجنة <?= htmlspecialchars($committee['committee_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #e0f2ff 0%, #e5ecff 50%, #f7f9ff 100%);
        }
        .tab-button.active {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
        }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .glass-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }
        .stat-card {
            border-radius: 14px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
        }
        .search-input {
            border-radius: 9999px;
            padding-inline: 1.5rem;
            background: rgba(243, 244, 246, 0.8);
        }
    </style>
</head>
<body>
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
        <div class="glass-card p-6">
            <div class="flex items-center justify-between gap-6 flex-wrap">
            <div>
                <p class="text-sm text-gray-500 mb-2">
                    <a href="municipality_management.php?tab=committees" class="text-blue-600 hover:text-blue-800">العودة إلى إدارة اللجان</a>
                </p>
                <h1 class="text-3xl font-extrabold text-gray-800">
                    🏛️ لجنة <?= htmlspecialchars($committee['committee_name']) ?>
                </h1>
                <p class="text-gray-500 mt-1 flex flex-wrap gap-4">
                    <span>نوع اللجنة: <strong><?= htmlspecialchars($committee['committee_type'] ?? 'غير محدد') ?></strong></span>
                    <?php if (!empty($committee['department_name'])): ?>
                        <span>القسم: <strong><?= htmlspecialchars($committee['department_name']) ?></strong></span>
                    <?php endif; ?>
                    <?php if (!empty($committee['meeting_frequency'])): ?>
                        <span>تواتر الاجتماعات: <?= htmlspecialchars($committee['meeting_frequency']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="text-right">
                <div class="stat-card">
                    <div class="text-xs text-gray-500">الرصيد الحالي</div>
                    <div class="text-2xl font-bold text-blue-700 mt-1"><?= number_format($financeSummary['current_balance'], 2) ?> ل.ل</div>
                </div>
            </div>
        </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="glass-card border border-green-200 bg-green-50/80 text-green-800 px-5 py-4 rounded-lg"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="glass-card border border-red-200 bg-red-50/80 text-red-800 px-5 py-4 rounded-lg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="glass-card p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="stat-card">
                <div class="text-sm text-gray-500">الرصيد الافتتاحي</div>
                <div class="text-2xl font-bold text-gray-800 mt-2"><?= number_format($financeSummary['opening_balance'], 2) ?> ل.ل</div>
            </div>
            <div class="stat-card">
                <div class="text-sm text-gray-500">إجمالي الإيرادات</div>
                <div class="text-2xl font-bold text-green-600 mt-2"><?= number_format($financeSummary['total_income'], 2) ?> ل.ل</div>
            </div>
            <div class="stat-card">
                <div class="text-sm text-gray-500">إجمالي المصروفات</div>
                <div class="text-2xl font-bold text-red-600 mt-2"><?= number_format($financeSummary['total_expense'], 2) ?> ل.ل</div>
            </div>
            <div class="stat-card">
                <div class="text-sm text-gray-500">آخر تحديث</div>
                <div class="text-xl font-semibold text-blue-700 mt-2">
                    <?= $financeSummary['last_updated'] ? date('Y-m-d H:i', strtotime($financeSummary['last_updated'])) : 'لم يتم التحديث بعد' ?>
                </div>
            </div>
        </div>
        </div>

        <!-- التبويبات -->
        <div class="glass-card p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'overview' ? 'active' : '' ?>" data-target="overview">نظرة عامة</button>
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'finance' ? 'active' : '' ?>" data-target="finance">الصندوق المالي</button>
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'budgets' ? 'active' : '' ?>" data-target="budgets">الميزانية</button>
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'invoices' ? 'active' : '' ?>" data-target="invoices">الفواتير</button>
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'requests' ? 'active' : '' ?>" data-target="requests">الطلبات</button>
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'sessions' ? 'active' : '' ?>" data-target="sessions">محاضر الاجتماعات</button>
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'decisions' ? 'active' : '' ?>" data-target="decisions">القرارات</button>
                <button class="tab-button px-4 py-2 rounded-lg font-semibold <?= $activeTab === 'reports' ? 'active' : '' ?>" data-target="reports">التقارير</button>
            </div>
        </div>

        <!-- نظرة عامة -->
        <div id="overview" class="tab-pane <?= $activeTab === 'overview' ? 'active' : '' ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <div class="glass-card p-6 space-y-2">
                    <h3 class="text-lg font-bold text-gray-700">ملخص الطلبات</h3>
                    <?php if (empty($requests)): ?>
                        <p class="text-sm text-gray-500">لا توجد طلبات مرتبطة حالياً.</p>
                    <?php else: ?>
                        <ul class="space-y-1">
                            <?php foreach ($requestStatus as $status): ?>
                                <li class="flex justify-between text-sm">
                                    <span><?= htmlspecialchars($status['status']) ?></span>
                                    <span class="font-semibold"><?= intval($status['total']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="#requests" class="inline-flex items-center mt-3 text-blue-600 hover:text-blue-800">عرض تفاصيل الطلبات →</a>
                    <?php endif; ?>
                </div>

                <div class="glass-card p-6 space-y-2">
                    <h3 class="text-lg font-bold text-gray-700">آخر المحاضر</h3>
                    <?php if (empty($sessions)): ?>
                        <p class="text-sm text-gray-500">لم يتم تسجيل محاضر بعد.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach (array_slice($sessions, 0, 3) as $session): ?>
                                <li class="border-b pb-2">
                                    <div class="font-semibold text-gray-800"><?= htmlspecialchars($session['session_title']) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('Y-m-d', strtotime($session['session_date'])) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="#sessions" class="inline-flex items-center mt-3 text-blue-600 hover:text-blue-800">عرض جميع المحاضر →</a>
                    <?php endif; ?>
                </div>

                <div class="glass-card p-6 space-y-2">
                    <h3 class="text-lg font-bold text-gray-700">أحدث القرارات</h3>
                    <?php if (empty($decisions)): ?>
                        <p class="text-sm text-gray-500">لا توجد قرارات مسجلة بعد.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach (array_slice($decisions, 0, 3) as $decision): ?>
                                <li class="border-b pb-2">
                                    <div class="font-semibold text-gray-800"><?= htmlspecialchars($decision['decision_title']) ?></div>
                                    <div class="text-xs text-gray-500 flex justify-between">
                                        <span>الحالة: <?= htmlspecialchars($decision['status']) ?></span>
                                        <?php if (!empty($decision['due_date'])): ?>
                                            <span>الاستحقاق: <?= htmlspecialchars($decision['due_date']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="#decisions" class="inline-flex items-center mt-3 text-blue-600 hover:text-blue-800">إدارة القرارات →</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- الصندوق المالي -->
        <div id="finance" class="tab-pane <?= $activeTab === 'finance' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-6">
                <div class="flex flex-wrap justify-between items-center gap-4">
                    <h2 class="text-xl font-bold text-gray-800">💰 الحركات المالية</h2>
                    <form method="POST" class="flex flex-wrap gap-2 items-end">
                        <input type="hidden" name="committee_id" value="<?= $committeeId ?>">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">نوع الحركة</label>
                            <select name="transaction_type" class="border rounded-lg px-3 py-2">
                                <option value="إيراد">إيراد</option>
                                <option value="مصروف">مصروف</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">المبلغ</label>
                            <input type="number" step="0.01" name="amount" class="border rounded-lg px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">العملة</label>
                            <select name="currency_id" class="border rounded-lg px-3 py-2">
                                <option value="">ل.ل</option>
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= $currency['id'] ?>"><?= htmlspecialchars($currency['currency_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">سعر الصرف</label>
                            <input type="number" step="0.0001" name="exchange_rate" class="border rounded-lg px-3 py-2" value="1">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">التاريخ</label>
                            <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2">
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs text-gray-500 mb-1">الوصف</label>
                            <input type="text" name="description" class="border rounded-lg px-3 py-2 w-full" placeholder="وصف مختصر">
                        </div>
                        <div>
                            <input type="hidden" name="add_transaction" value="1">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                ➕ إضافة الحركة
                            </button>
                        </div>
                    </form>
                </div>

                <div class="relative">
                    <input type="search" placeholder="🔍 ابحث داخل الحركات المالية..." class="search-input w-full py-2 border" data-search="finance-table">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="finance-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">التاريخ</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">النوع</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الوصف</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المصدر</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المبلغ</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الملاحظات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($transaction['transaction_date']) ?></td>
                                    <td class="px-4 py-2 text-sm <?= $transaction['transaction_type'] === 'إيراد' ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= htmlspecialchars($transaction['transaction_type']) ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($transaction['description'] ?? '—') ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($transaction['source'] ?? '—') ?></td>
                                    <td class="px-4 py-2 text-sm font-semibold">
                                        <?= number_format($transaction['amount'], 2) ?>
                                        <?= htmlspecialchars($transaction['currency_symbol'] ?? 'ل.ل') ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-400"><?= nl2br(htmlspecialchars($transaction['notes'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                        لا توجد حركات مالية مسجلة بعد.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- الميزانية -->
        <div id="budgets" class="tab-pane <?= $activeTab === 'budgets' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">📊 خطط الميزانية</h2>
                    <a href="budgets.php?committee_id=<?= $committeeId ?>&committee_name=<?= urlencode($committee['committee_name']) ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        فتح إدارة الميزانية
                    </a>
                </div>

                <div class="relative">
                    <input type="search" placeholder="🔍 ابحث داخل الميزانيات..." class="search-input w-full py-2 border" data-search="budget-table">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="budget-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الخطة</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">السنة المالية</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المبلغ الإجمالي</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المصروف</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المتبقي</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($budgets as $budget): ?>
                                <?php
                                    $remaining = $budget['total_amount'] - ($budget['total_spent'] ?? 0);
                                    $statusClass = $budget['status'] === 'معتمد' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($budget['name']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($budget['budget_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($budget['fiscal_year']) ?></td>
                                    <td class="px-4 py-2 text-sm font-semibold text-blue-700"><?= number_format($budget['total_amount'], 2) ?></td>
                                    <td class="px-4 py-2 text-sm text-red-600 font-semibold"><?= number_format($budget['total_spent'], 2) ?></td>
                                    <td class="px-4 py-2 text-sm text-green-600 font-semibold"><?= number_format($remaining, 2) ?></td>
                                    <td class="px-4 py-2 text-xs">
                                        <span class="px-3 py-1 rounded-full <?= $statusClass ?>"><?= htmlspecialchars($budget['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($budgets)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                        لا توجد خطط ميزانية مسجلة لهذه اللجنة.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- الفواتير -->
        <div id="invoices" class="tab-pane <?= $activeTab === 'invoices' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">📑 فواتير اللجنة</h2>
                    <a href="invoices.php?committee_id=<?= $committeeId ?>" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        إدارة الفواتير
                    </a>
                </div>

                <div class="relative">
                    <input type="search" placeholder="🔍 ابحث داخل الفواتير..." class="search-input w-full py-2 border" data-search="invoice-table">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="invoice-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">رقم الفاتورة</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المورد</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">التاريخ</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المبلغ</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المدفوع</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المتبقي</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice): ?>
                                <?php
                                    $statusColors = [
                                        'غير مدفوع' => 'bg-red-100 text-red-800',
                                        'مدفوع جزئياً' => 'bg-yellow-100 text-yellow-800',
                                        'مدفوع بالكامل' => 'bg-green-100 text-green-800',
                                        'متأخر' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $statusClass = $statusColors[$invoice['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <tr>
                                    <td class="px-4 py-2 font-semibold text-blue-700"><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                    <td class="px-4 py-2">
                                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($invoice['supplier_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($invoice['supplier_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($invoice['invoice_date']) ?></td>
                                    <td class="px-4 py-2 text-sm font-semibold"><?= number_format($invoice['total_amount'], 2) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?></td>
                                    <td class="px-4 py-2 text-sm text-green-600 font-semibold"><?= number_format($invoice['paid_amount'], 2) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?></td>
                                    <td class="px-4 py-2 text-sm text-red-600 font-semibold"><?= number_format($invoice['remaining_amount'], 2) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?></td>
                                    <td class="px-4 py-2 text-xs">
                                        <span class="px-3 py-1 rounded-full <?= $statusClass ?>"><?= htmlspecialchars($invoice['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                        لا توجد فواتير مرتبطة بهذه اللجنة حتى الآن.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- الطلبات -->
        <div id="requests" class="tab-pane <?= $activeTab === 'requests' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-4">
                <h2 class="text-xl font-bold text-gray-800">📝 طلبات المواطنين المرتبطة باللجنة</h2>

                <div class="relative">
                    <input type="search" placeholder="🔍 ابحث داخل الطلبات..." class="search-input w-full py-2 border" data-search="requests-table">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="requests-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">رقم التتبع</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">المواطن</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">نوع الطلب</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الحالة</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">التاريخ</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">التكلفة التقديرية</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($requests as $request): ?>
                                <?php
                                    $requestName = $request['citizen_name'] ?? ($request['full_name'] ?? '—');
                                    $requestPhone = $request['citizen_phone'] ?? ($request['phone'] ?? '—');
                                    $requestDate = $request['created_at'] ?? ($request['submission_date'] ?? null);
                                    $requestPriority = $request['priority_level'] ?? ($request['priority'] ?? null);
                                ?>
                                <tr>
                                    <td class="px-4 py-2 font-semibold text-blue-700"><?= htmlspecialchars($request['tracking_number']) ?></td>
                                    <td class="px-4 py-2 text-sm">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($requestName) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($requestPhone) ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($request['type_name'] ?? 'غير محدد') ?></td>
                                    <td class="px-4 py-2 text-xs">
                                        <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-800"><?= htmlspecialchars($request['status']) ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?= $requestDate ? htmlspecialchars(date('Y-m-d', strtotime($requestDate))) : '—' ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm font-semibold">
                                        <?php if (isset($request['cost_estimate']) && $request['cost_estimate'] !== null): ?>
                                            <?= number_format((float)$request['cost_estimate'], 2) ?> ل.ل
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                        لا توجد طلبات مرتبطة بهذه اللجنة حالياً.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- الجلسات -->
        <div id="sessions" class="tab-pane <?= $activeTab === 'sessions' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-6">
                <div class="flex flex-wrap justify-between items-center gap-4">
                    <h2 class="text-xl font-bold text-gray-800">📅 محاضر اجتماعات اللجنة</h2>
                    <form method="POST" class="w-full md:w-auto bg-gray-50 border rounded-lg p-4 space-y-3">
                        <input type="hidden" name="committee_id" value="<?= $committeeId ?>">
                        <input type="hidden" name="add_session" value="1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">رقم الجلسة</label>
                                <input type="text" name="session_number" class="border rounded-lg px-3 py-2 w-full">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">عنوان الجلسة *</label>
                                <input type="text" name="session_title" class="border rounded-lg px-3 py-2 w-full" required>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">تاريخ الجلسة *</label>
                                <input type="date" name="session_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 w-full" required>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">الوقت</label>
                                <input type="time" name="session_time" class="border rounded-lg px-3 py-2 w-full">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">الموقع</label>
                                <input type="text" name="location" class="border rounded-lg px-3 py-2 w-full" placeholder="مبنى البلدية - القاعة الرئيسية">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">جدول الأعمال</label>
                                <textarea name="agenda" rows="2" class="border rounded-lg px-3 py-2 w-full"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">محضر الجلسة</label>
                                <textarea name="minutes" rows="3" class="border rounded-lg px-3 py-2 w-full"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">مرفقات (روابط)</label>
                                <input type="text" name="attachments" class="border rounded-lg px-3 py-2 w-full" placeholder="رابط Google Drive أو مستند">
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                💾 حفظ المحضر
                            </button>
                        </div>
                    </form>
                </div>

                <div class="relative">
                    <input type="search" placeholder="🔍 ابحث داخل محاضر الجلسات..." class="search-input w-full py-2 border" data-search="sessions-table">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="sessions-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الجلسة</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">التاريخ</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الموقع</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">جدول الأعمال</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($session['session_title']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($session['session_number'] ?? '—') ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        <?= htmlspecialchars($session['session_date']) ?>
                                        <?php if (!empty($session['session_time'])): ?>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($session['session_time']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($session['location'] ?? '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        <?= nl2br(htmlspecialchars($session['agenda'] ?? '—')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sessions)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                        لم يتم تسجيل أي جلسة لهذه اللجنة بعد.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- القرارات -->
        <div id="decisions" class="tab-pane <?= $activeTab === 'decisions' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-6">
                <div class="flex flex-wrap justify-between items-center gap-4">
                    <h2 class="text-xl font-bold text-gray-800">🧾 قرارات اللجنة</h2>
                    <form method="POST" class="w-full md:w-auto bg-gray-50 border rounded-lg p-4 space-y-3">
                        <input type="hidden" name="committee_id" value="<?= $committeeId ?>">
                        <input type="hidden" name="add_decision" value="1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">الجلسة المرتبطة</label>
                                <select name="session_id" class="border rounded-lg px-3 py-2 w-full">
                                    <option value="">— بدون —</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['id'] ?>">
                                            <?= htmlspecialchars($session['session_title']) ?> (<?= htmlspecialchars($session['session_date']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">رقم القرار</label>
                                <input type="text" name="decision_number" class="border rounded-lg px-3 py-2 w-full">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">عنوان القرار *</label>
                                <input type="text" name="decision_title" class="border rounded-lg px-3 py-2 w-full" required>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">نص القرار *</label>
                                <textarea name="decision_text" rows="3" class="border rounded-lg px-3 py-2 w-full" required></textarea>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">تاريخ الاستحقاق</label>
                                <input type="date" name="due_date" class="border rounded-lg px-3 py-2 w-full">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">الحالة</label>
                                <select name="status" class="border rounded-lg px-3 py-2 w-full">
                                    <option value="قيد المتابعة">قيد المتابعة</option>
                                    <option value="منفذ">منفذ</option>
                                    <option value="مرفوض">مرفوض</option>
                                    <option value="معلق">معلق</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">ملاحظات</label>
                                <textarea name="notes" rows="2" class="border rounded-lg px-3 py-2 w-full"></textarea>
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                💾 حفظ القرار
                            </button>
                        </div>
                    </form>
                </div>

                <div class="relative">
                    <input type="search" placeholder="🔍 ابحث داخل القرارات..." class="search-input w-full py-2 border" data-search="decisions-table">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="decisions-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">القرار</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الجلسة</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">الحالة</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">تاريخ الاستحقاق</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($decisions as $decision): ?>
                                <?php
                                    $statusClass = match ($decision['status']) {
                                        'منفذ' => 'bg-green-100 text-green-800',
                                        'مرفوض' => 'bg-red-100 text-red-800',
                                        'معلق' => 'bg-yellow-100 text-yellow-800',
                                        default => 'bg-blue-100 text-blue-800'
                                    };
                                ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($decision['decision_title']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($decision['decision_number'] ?? '—') ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        <?= htmlspecialchars($decision['session_title'] ?? '—') ?>
                                        <?php if (!empty($decision['session_date'])): ?>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($decision['session_date']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs">
                                        <span class="px-3 py-1 rounded-full <?= $statusClass ?>"><?= htmlspecialchars($decision['status']) ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($decision['due_date'] ?? '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-gray-500"><?= nl2br(htmlspecialchars($decision['notes'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($decisions)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                        لا توجد قرارات مسجلة لهذه اللجنة بعد.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- التقارير -->
        <div id="reports" class="tab-pane <?= $activeTab === 'reports' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-6">
                <h2 class="text-xl font-bold text-gray-800">📈 تقارير الأداء المالي والتشغيلي</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white border rounded-lg p-4 shadow">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">التدفقات المالية الشهرية</h3>
                        <canvas id="financeChart" height="200"></canvas>
                    </div>
                    <div class="bg-white border rounded-lg p-4 shadow">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">توزيع الطلبات حسب الحالة</h3>
                        <canvas id="requestChart" height="200"></canvas>
                    </div>
                </div>

                <div class="bg-white border rounded-lg p-6 shadow space-y-3">
                    <h3 class="text-lg font-semibold text-gray-700">تقرير موجز</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>عدد الطلبات النشطة: <?= count($requests) ?></li>
                        <li>عدد المحاضر المسجلة: <?= count($sessions) ?></li>
                        <li>عدد القرارات الصادرة: <?= count($decisions) ?></li>
                        <li>إجمالي الإيرادات: <?= number_format($financeSummary['total_income'], 2) ?> ل.ل</li>
                        <li>إجمالي المصروفات: <?= number_format($financeSummary['total_expense'], 2) ?> ل.ل</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        const tabs = document.querySelectorAll('.tab-button');
        const panes = document.querySelectorAll('.tab-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.target;
                tabs.forEach(t => t.classList.remove('active'));
                panes.forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(target).classList.add('active');
                const params = new URLSearchParams(window.location.search);
                params.set('tab', target);
                history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`);
            });
        });

        // البحث داخل الجداول
        document.querySelectorAll('[data-search]').forEach(input => {
            input.addEventListener('input', () => {
                const tableId = input.getAttribute('data-search');
                const term = input.value.toLowerCase();
                document.querySelectorAll(`#${tableId} tbody tr`).forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        });

        // الرسوم البيانية
        const financeCtx = document.getElementById('financeChart');
        if (financeCtx) {
            new Chart(financeCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($reportLabels, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [
                        {
                            label: 'الإيرادات',
                            data: <?= json_encode($reportIncome, JSON_UNESCAPED_UNICODE) ?>,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.2)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'المصروفات',
                            data: <?= json_encode($reportExpense, JSON_UNESCAPED_UNICODE) ?>,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.2)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        const requestCtx = document.getElementById('requestChart');
        if (requestCtx) {
            new Chart(requestCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($requestStatus, 'status'), JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        data: <?= json_encode(array_map('intval', array_column($requestStatus, 'total')), JSON_UNESCAPED_UNICODE) ?>,
                        backgroundColor: ['#2563eb', '#16a34a', '#f97316', '#dc2626', '#7c3aed']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>

