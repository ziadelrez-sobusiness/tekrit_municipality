<?php
// modules/municipal_budget_print.php
if (!isset($budget)) die();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طباعة الموازنة البلدية</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');
    body { font-family: 'Cairo', sans-serif; background: #fff; color: #000; margin: 0; padding: 20px; font-size: 12px; }
    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    .header h1 { margin: 0 0 5px; font-size: 24px; }
    .header h2 { margin: 0 0 5px; font-size: 18px; }
    .meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-weight: bold; }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #000; padding: 6px; text-align: right; }
    th { background-color: #f0f0f0; }
    
    .chapter-header { background-color: #e0e0e0; font-weight: bold; }
    .section-title { font-size: 16px; font-weight: bold; margin-bottom: 10px; margin-top: 20px; background: #333; color: #fff; padding: 5px; text-align: center; }
    .total-row { font-weight: bold; background-color: #f9f9f9; }
    
    .summary-box { border: 1px solid #000; padding: 10px; width: 350px; margin: 20px auto; text-align: center; font-weight: bold; }
    .summary-box div { margin: 5px 0; }
    
    .note { text-align: center; font-weight: bold; margin-bottom: 15px; border: 1px dashed #000; padding: 5px; }

    @media print {
        .no-print { display: none; }
        @page { margin: 1cm; }
        body { font-size: 10px; }
    }
</style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding:10px 20px; font-size:16px; cursor:pointer;">🖨️ طباعة الموازنة</button>
</div>

<div class="header">
    <h1>الجمهورية اللبنانية</h1>
    <h2>بلدية تكريت</h2>
    <h3>الموازنة البلدية للعام <?= $budget['fiscal_year'] ?></h3>
    <p><?= htmlspecialchars($budget['title']) ?></p>
</div>

<div class="note">
    جميع أرقام الموازنة الرسمية بالليرة اللبنانية (ل.ل.)
</div>

<div class="meta">
    <div>تاريخ الطباعة: <?= date('Y-m-d') ?></div>
    <div>حالة الموازنة: <?php
    $print_sl = $budget['status'];
    $print_labels = ['draft'=>'مسودة', 'approved'=>'معتمدة / فعالة', 'closed'=>'مقفلة', 'cancelled'=>'ملغاة'];
    echo $print_labels[$print_sl] ?? $print_sl;
    ?></div>
</div>

<div class="summary-box">
    <div style="font-size:14px; margin-bottom:10px;">الخلاصة العامة (ل.ل.)</div>
    <div>مجموع الواردات المقدرة: <span dir="ltr"><?= number_format($totals['income_est'], 0) ?></span></div>
    <div>مجموع الواردات الفعلية: <span dir="ltr"><?= number_format($totals['income_act'], 0) ?></span></div>
    <div>مجموع النفقات المقدرة: <span dir="ltr"><?= number_format($totals['expense_est'], 0) ?></span></div>
    <div>مجموع النفقات الفعلية: <span dir="ltr"><?= number_format($totals['expense_act'], 0) ?></span></div>
    <div style="border-top:1px solid #000; margin-top:5px; padding-top:5px;">الفارق المقدر: <span dir="ltr"><?= number_format($totals['income_est'] - $totals['expense_est'], 0) ?></span></div>
    <div>الفارق الفعلي: <span dir="ltr"><?= number_format($totals['income_act'] - $totals['expense_act'], 0) ?></span></div>
</div>

<?php foreach (['income' => 'قسم الواردات', 'expense' => 'قسم النفقات'] as $sec_key => $sec_title): ?>
    <div class="section-title"><?= $sec_title ?></div>
    
    <?php if (empty($grouped[$sec_key])): ?>
        <p style="text-align:center;">لا يوجد بنود.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th width="40">الباب</th>
                    <th width="40">الفصل</th>
                    <th><?= $sec_key==='income'?'نوع الواردات':'نوع النفقات' ?></th>
                    <th width="120">تقديرات الموازنة (ل.ل.)</th>
                    <th width="120">الفعلي (ل.ل.)</th>
                    <th>شرح البند</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grouped[$sec_key] as $chap_name => $chap_data): ?>
                    <tr class="chapter-header">
                        <td colspan="6" style="text-align:center;"><?= htmlspecialchars($chap_name) ?></td>
                    </tr>
                    <?php foreach ($chap_data['lines'] as $line): if(!$line['is_active']) continue; ?>
                    <tr>
                        <td style="text-align:center;"><?= htmlspecialchars($line['chapter_number'] ?? '-') ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($line['item_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($line['item_name']) ?></td>
                        <td dir="ltr"><?= number_format($line['current_estimate'], 0) ?></td>
                        <td dir="ltr"><?= number_format($line['actual_amount'] ?? 0, 0) ?></td>
                        <td><?= htmlspecialchars($line['explanation'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3">مجموع <?= htmlspecialchars($chap_name) ?></td>
                        <td dir="ltr"><?= number_format($chap_data['est_sum'], 0) ?></td>
                        <td dir="ltr"><?= number_format($chap_data['act_sum'], 0) ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row" style="background-color:#d0d0d0;">
                    <td colspan="3">مجموع <?= $sec_title ?></td>
                    <td dir="ltr"><?= number_format($totals[$sec_key.'_est'], 0) ?></td>
                    <td dir="ltr"><?= number_format($totals[$sec_key.'_act'], 0) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>
<?php endforeach; ?>

<div style="margin-top: 50px; display:flex; justify-content:space-around;">
    <div style="text-align:center;">
        <b>رئيس البلدية</b><br><br><br>
        .......................
    </div>
    <div style="text-align:center;">
        <b>المحاسب</b><br><br><br>
        .......................
    </div>
</div>

</body>
</html>
