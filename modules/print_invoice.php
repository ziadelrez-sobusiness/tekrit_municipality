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

// جلب معرف الفاتورة
$invoice_id = $_GET['id'] ?? 0;

if (!$invoice_id) {
    header('Location: invoices.php');
    exit();
}

// جلب بيانات الفاتورة
$stmt = $db->prepare("
    SELECT si.*,
           s.name as supplier_name,
           s.contact_person,
           s.phone,
           s.email,
           s.address as supplier_address,
           s.service_type,
           c.currency_code,
           c.currency_symbol,
           c.currency_name,
           bi.name as budget_item_name,
           u.full_name as created_by_name
    FROM supplier_invoices si
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    LEFT JOIN currencies c ON si.currency_id = c.id
    LEFT JOIN budget_items bi ON si.budget_item_id = bi.id
    LEFT JOIN users u ON si.created_by = u.id
    WHERE si.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب اسم المشروع إذا كان مرتبطاً
$project_name = null;
if (!empty($invoice['related_project_id'])) {
    try {
        // محاولة جلب المشروع مع التعامل مع أسماء أعمدة مختلفة
        $pstmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $pstmt->execute([$invoice['related_project_id']]);
        $project = $pstmt->fetch(PDO::FETCH_ASSOC);
        
        if ($project) {
            // البحث عن عمود الاسم
            if (isset($project['name'])) {
                $project_name = $project['name'];
            } elseif (isset($project['project_name'])) {
                $project_name = $project['project_name'];
            } elseif (isset($project['title'])) {
                $project_name = $project['title'];
            } else {
                $project_name = 'مشروع #' . $invoice['related_project_id'];
            }
        }
    } catch (PDOException $e) {
        $project_name = 'مشروع #' . $invoice['related_project_id'];
    }
}
$invoice['project_name'] = $project_name;

if (!$invoice) {
    header('Location: invoices.php');
    exit();
}

// جلب الدفعات
$stmt = $db->prepare("
    SELECT ip.*,
           u.full_name as created_by_name
    FROM invoice_payments ip
    LEFT JOIN users u ON ip.created_by = u.id
    WHERE ip.invoice_id = ?
    ORDER BY ip.payment_date DESC
");
$stmt->execute([$invoice_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة رقم <?= htmlspecialchars($invoice['invoice_number']) ?> - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .page-break {
                page-break-after: always;
            }
        }
        
        @page {
            size: A4;
            margin: 1.5cm;
        }
    </style>
</head>
<body class="bg-white">
    <!-- أزرار التحكم (لا تطبع) -->
    <div class="no-print fixed top-4 left-4 z-50 flex gap-2">
        <button onclick="window.print()" 
                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 shadow-lg flex items-center gap-2">
            🖨️ طباعة
        </button>
        <button onclick="window.close()" 
                class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 shadow-lg flex items-center gap-2">
            ← إغلاق
        </button>
    </div>

    <div class="max-w-4xl mx-auto p-8">
        <!-- رأس الفاتورة -->
        <div class="border-b-4 border-blue-600 pb-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-4xl font-bold text-blue-900 mb-2">🏛️ بلدية تكريت - عكار</h1>
                    <p class="text-gray-600">شمال لبنان - محافظة عكار</p>
                    <p class="text-gray-600">Tel: +961 XX XXX XXX</p>
                </div>
                <div class="text-left">
                    <div class="bg-blue-100 px-6 py-3 rounded-lg">
                        <p class="text-sm text-gray-600">رقم الفاتورة</p>
                        <p class="text-2xl font-bold text-blue-900"><?= htmlspecialchars($invoice['invoice_number']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- معلومات الفاتورة -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <!-- معلومات المورد -->
            <div class="border-2 border-gray-200 rounded-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm">المورد</span>
                </h2>
                <div class="space-y-2">
                    <div>
                        <p class="text-sm text-gray-600">الاسم</p>
                        <p class="font-bold text-lg"><?= htmlspecialchars($invoice['supplier_name']) ?></p>
                    </div>
                    <?php if ($invoice['contact_person']): ?>
                    <div>
                        <p class="text-sm text-gray-600">جهة الاتصال</p>
                        <p class="font-semibold"><?= htmlspecialchars($invoice['contact_person']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['phone']): ?>
                    <div>
                        <p class="text-sm text-gray-600">الهاتف</p>
                        <p class="font-semibold"><?= htmlspecialchars($invoice['phone']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['email']): ?>
                    <div>
                        <p class="text-sm text-gray-600">البريد الإلكتروني</p>
                        <p class="font-semibold text-sm"><?= htmlspecialchars($invoice['email']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['supplier_address']): ?>
                    <div>
                        <p class="text-sm text-gray-600">العنوان</p>
                        <p class="font-semibold text-sm"><?= htmlspecialchars($invoice['supplier_address']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- معلومات الفاتورة -->
            <div class="border-2 border-gray-200 rounded-lg p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">تفاصيل الفاتورة</span>
                </h2>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">تاريخ الفاتورة:</span>
                        <span class="font-bold"><?= date('Y-m-d', strtotime($invoice['invoice_date'])) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">تاريخ الاستحقاق:</span>
                        <span class="font-bold <?= (strtotime($invoice['due_date']) < time() && $invoice['status'] != 'مدفوع بالكامل') ? 'text-red-600' : '' ?>">
                            <?= date('Y-m-d', strtotime($invoice['due_date'])) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">الحالة:</span>
                        <?php
                        $statusColors = [
                            'غير مدفوع' => 'bg-red-100 text-red-800',
                            'مدفوع جزئياً' => 'bg-yellow-100 text-yellow-800',
                            'مدفوع بالكامل' => 'bg-green-100 text-green-800',
                            'متأخر' => 'bg-red-100 text-red-800'
                        ];
                        $statusClass = $statusColors[$invoice['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-3 py-1 rounded font-bold text-sm <?= $statusClass ?>">
                            <?= htmlspecialchars($invoice['status']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">العملة:</span>
                        <span class="font-bold"><?= htmlspecialchars($invoice['currency_name']) ?> (<?= htmlspecialchars($invoice['currency_symbol']) ?>)</span>
                    </div>
                    <?php if ($invoice['project_name']): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">المشروع:</span>
                        <span class="font-bold text-sm"><?= htmlspecialchars($invoice['project_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['budget_item_name']): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">بند الميزانية:</span>
                        <span class="font-bold text-sm"><?= htmlspecialchars($invoice['budget_item_name']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- وصف الفاتورة -->
        <?php if ($invoice['description']): ?>
        <div class="border-2 border-gray-200 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-3">📝 الوصف</h2>
            <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($invoice['description'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- المبالغ -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg p-6 mb-6">
            <div class="grid grid-cols-3 gap-6">
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-2">المبلغ الإجمالي</p>
                    <p class="text-3xl font-bold text-blue-900">
                        <?= number_format($invoice['total_amount'], 2) ?>
                        <span class="text-lg"><?= htmlspecialchars($invoice['currency_symbol']) ?></span>
                    </p>
                </div>
                <div class="text-center border-x-2 border-gray-300">
                    <p class="text-sm text-gray-600 mb-2">المبلغ المدفوع</p>
                    <p class="text-3xl font-bold text-green-600">
                        <?= number_format($invoice['paid_amount'], 2) ?>
                        <span class="text-lg"><?= htmlspecialchars($invoice['currency_symbol']) ?></span>
                    </p>
                </div>
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-2">المبلغ المتبقي</p>
                    <p class="text-3xl font-bold text-red-600">
                        <?= number_format($invoice['remaining_amount'], 2) ?>
                        <span class="text-lg"><?= htmlspecialchars($invoice['currency_symbol']) ?></span>
                    </p>
                </div>
            </div>
        </div>

        <!-- سجل الدفعات -->
        <?php if (!empty($payments)): ?>
        <div class="border-2 border-gray-200 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">💳 سجل الدفعات</h2>
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="text-right p-3 border">التاريخ</th>
                        <th class="text-right p-3 border">المبلغ</th>
                        <th class="text-right p-3 border">طريقة الدفع</th>
                        <th class="text-right p-3 border">رقم المرجع</th>
                        <th class="text-right p-3 border">المسؤول</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="p-3 border"><?= date('Y-m-d', strtotime($payment['payment_date'])) ?></td>
                        <td class="p-3 border font-bold text-green-600">
                            <?= number_format($payment['payment_amount'], 2) ?> <?= htmlspecialchars($invoice['currency_symbol']) ?>
                        </td>
                        <td class="p-3 border"><?= htmlspecialchars($payment['payment_method']) ?></td>
                        <td class="p-3 border font-mono text-xs"><?= htmlspecialchars($payment['reference_number']) ?></td>
                        <td class="p-3 border"><?= htmlspecialchars($payment['created_by_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- الملاحظات -->
        <?php if ($invoice['notes']): ?>
        <div class="border-2 border-yellow-200 bg-yellow-50 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-3">📌 ملاحظات</h2>
            <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- التوقيعات -->
        <div class="mt-12 pt-6 border-t-2 border-gray-300">
            <div class="grid grid-cols-3 gap-8 text-center">
                <div>
                    <p class="text-gray-600 mb-12">المورد</p>
                    <div class="border-t-2 border-gray-400 pt-2">
                        <p class="text-sm text-gray-500">التوقيع</p>
                    </div>
                </div>
                <div>
                    <p class="text-gray-600 mb-12">المسؤول المالي</p>
                    <div class="border-t-2 border-gray-400 pt-2">
                        <p class="text-sm text-gray-500">التوقيع</p>
                    </div>
                </div>
                <div>
                    <p class="text-gray-600 mb-12">رئيس البلدية</p>
                    <div class="border-t-2 border-gray-400 pt-2">
                        <p class="text-sm text-gray-500">التوقيع والختم</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- معلومات إضافية -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>تم إصدار هذه الفاتورة بتاريخ <?= date('Y-m-d H:i:s') ?></p>
            <p>بواسطة: <?= htmlspecialchars($invoice['created_by_name']) ?></p>
        </div>
    </div>

    <script>
        // طباعة تلقائية عند الفتح (اختياري)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>

