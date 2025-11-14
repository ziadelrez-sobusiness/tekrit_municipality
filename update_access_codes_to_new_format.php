<?php
/**
 * Script: update_access_codes_to_new_format.php
 * الهدف: تحويل جميع رموز الدخول إلى الصيغة الجديدة (TKT- + 5 أرقام فريدة)
 */

header('Content-Type: text/html; charset=utf-8');

require_once 'config/database.php';
require_once 'includes/CitizenAccountHelper.php';

function hasUniqueDigits($code) {
    if (!preg_match('/^TKT\-(\d{5})$/', $code, $matches)) {
        return false;
    }
    $digits = str_split($matches[1]);
    return count($digits) === count(array_unique($digits));
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $helper = new CitizenAccountHelper($db);
    $reflection = new ReflectionClass($helper);
    $method = $reflection->getMethod('generateAccessCode');
    $method->setAccessible(true);

    $stmt = $db->query("SELECT id, name, phone, permanent_access_code FROM citizens_accounts ORDER BY id ASC");
    $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = [];
    $skipped = [];

    foreach ($citizens as $citizen) {
        $oldCode = $citizen['permanent_access_code'] ?? '';

        if (hasUniqueDigits($oldCode)) {
            $skipped[] = [
                'id' => $citizen['id'],
                'name' => $citizen['name'],
                'phone' => $citizen['phone'],
                'code' => $oldCode
            ];
            continue;
        }

        $newCode = $method->invoke($helper);

        $updateStmt = $db->prepare("UPDATE citizens_accounts SET permanent_access_code = ? WHERE id = ?");
        $updateStmt->execute([$newCode, $citizen['id']]);

        $updated[] = [
            'id' => $citizen['id'],
            'name' => $citizen['name'],
            'phone' => $citizen['phone'],
            'old_code' => $oldCode,
            'new_code' => $newCode
        ];
    }

} catch (Exception $e) {
    echo '<div style="padding:20px;background:#fee2e2;border:1px solid #ef4444;border-radius:12px;font-family:Arial">';
    echo '<h2 style="margin-top:0;color:#b91c1c">❌ حدث خطأ أثناء التحديث</h2>';
    echo '<p style="color:#7f1d1d;">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث رموز الدخول إلى الصيغة الجديدة</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f8fafc; }
        .container { max-width: 960px; margin: 40px auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border: 1px solid #e2e8f0; text-align: center; }
        th { background: #1d4ed8; color: white; }
        .pill { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: 700; }
        .pill-old { background: #fee2e2; color: #b91c1c; }
        .pill-new { background: #dcfce7; color: #065f46; }
        .pill-skip { background: #e2e8f0; color: #475569; }
    </style>
</head>
<body>
    <main class="container">
        <h1>🔄 تحديث رموز الدخول إلى الصيغة الجديدة</h1>
        <p>تم تنفيذ التحديث بنجاح. الصيغة الجديدة: <code>TKT-12345</code> (خمسة أرقام بدون تكرار).</p>

        <section>
            <h2>✅ الرموز التي تم تحديثها (<?= count($updated) ?>)</h2>
            <?php if (count($updated) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>الهاتف</th>
                            <th>الرمز القديم</th>
                            <th>الرمز الجديد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($updated as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td><span class="pill pill-old"><?= htmlspecialchars($row['old_code'] ?: 'غير موجود') ?></span></td>
                                <td><span class="pill pill-new"><?= htmlspecialchars($row['new_code']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>لا توجد رموز بحاجة إلى تحديث.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2>ℹ️ رموز تم الإبقاء عليها (<?= count($skipped) ?>)</h2>
            <p>هذه الرموز مطابقة بالفعل للشروط الجديدة (TKT- + 5 أرقام فريدة).</p>
            <?php if (count($skipped) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>الهاتف</th>
                            <th>الرمز الحالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skipped as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td><span class="pill pill-skip"><?= htmlspecialchars($row['code']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>لا توجد رموز مطابقة للشروط السابقة.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2>📌 ملاحظات مهمة</h2>
            <ul>
                <li>يتم ضمان عدم تكرار الأرقام داخل الرمز الواحد.</li>
                <li>يمكن للمواطنين استخدام الرموز الجديدة فورًا للدخول أو للربط على Telegram.</li>
                <li>احفظ جدول النتائج للاطلاع على الرموز بعد التحديث.</li>
            </ul>
        </section>
    </main>
</body>
</html>

