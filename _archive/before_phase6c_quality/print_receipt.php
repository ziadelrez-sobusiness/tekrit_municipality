<?php
// modules/print_receipt.php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

if (!isset($_GET['id'])) {
    die("معرف المعاملة غير متوفر");
}

$id = intval($_GET['id']);
$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");
header('Content-Type: text/html; charset=utf-8');

$stmt = $db->prepare("
    SELECT ft.*, 
           r.receipt_number, r.payer_name, r.receipt_date, r.amount as r_amount,
           c.currency_symbol, c.currency_name,
           cb.name as cashbox_name
    FROM financial_transactions ft
    JOIN accounting_receipts r ON ft.receipt_id = r.id
    LEFT JOIN currencies c ON ft.currency_id = c.id
    LEFT JOIN accounting_cashboxes cb ON ft.cashbox_id = cb.id
    WHERE ft.id = ? AND ft.type = 'إيراد'
");
$stmt->execute([$id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die("المعاملة غير موجودة أو ليست مدخولاً.");
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة إيصال استلام</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #fff; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
            .print-container { border: 2px solid #000; border-radius: 8px; padding: 30px; }
        }
    </style>
</head>
<body class="p-8">
    <div class="max-w-3xl mx-auto print-container shadow-lg p-10 border border-gray-200 rounded">
        <div class="text-center mb-8 border-b-2 border-black pb-4">
            <h1 class="text-3xl font-bold">بلدية تكريت</h1>
            <h2 class="text-2xl mt-2">إيصال استلام نقدية</h2>
        </div>

        <div class="flex justify-between mb-8">
            <div class="text-lg">
                <p class="mb-2"><strong>رقم الإيصال:</strong> <span dir="ltr"><?= htmlspecialchars($transaction['receipt_number']) ?></span></p>
                <p><strong>التاريخ:</strong> <?= date('Y-m-d', strtotime($transaction['receipt_date'])) ?></p>
            </div>
            <div class="text-lg">
                <p class="mb-2 border border-black p-2 font-bold bg-gray-100">
                    <strong>المبلغ:</strong> <?= number_format($transaction['r_amount'], 2) ?> <?= htmlspecialchars($transaction['currency_symbol']) ?>
                </p>
            </div>
        </div>

        <div class="mb-8 leading-loose text-lg">
            <p><strong>استلمنا من السيد/ة (الجهة):</strong> <?= htmlspecialchars($transaction['payer_name']) ?></p>
            <p><strong>مبلغاً وقدره:</strong> <?= number_format($transaction['r_amount'], 2) ?> <?= htmlspecialchars($transaction['currency_name']) ?></p>
            <p><strong>وذلك لقاء (البيان):</strong> <?= htmlspecialchars($transaction['category']) ?> <?= $transaction['description'] ? ' - ' . htmlspecialchars($transaction['description']) : '' ?></p>
            <p><strong>طريقة الدفع:</strong> <?= htmlspecialchars($transaction['payment_method']) ?></p>
            <p><strong>الصندوق المودع فيه:</strong> <?= htmlspecialchars($transaction['cashbox_name']) ?></p>
        </div>

        <div class="flex justify-between mt-16 pt-8 border-t border-gray-300">
            <div class="text-center w-1/3">
                <p class="font-bold">توقيع المستلم</p>
                <br><br>
                <p>.............................</p>
            </div>
            <div class="text-center w-1/3">
                <p class="font-bold">توقيع الدافع</p>
                <br><br>
                <p>.............................</p>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto mt-8 text-center no-print">
        <button onclick="window.print()" class="bg-indigo-600 text-white px-8 py-3 rounded text-lg font-bold hover:bg-indigo-700">🖨️ طباعة الإيصال</button>
        <a href="accounting_treasury.php" class="bg-gray-500 text-white px-8 py-3 rounded text-lg font-bold hover:bg-gray-600 mr-4">العودة</a>
    </div>
</body>
</html>
