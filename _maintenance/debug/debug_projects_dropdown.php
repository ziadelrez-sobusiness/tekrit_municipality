<?php
header('Content-Type: text/html; charset=utf-8');

try {
    $db = new PDO("mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4", 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // نفس الاستعلام من contributions.php
    $columns_query = $db->query("SHOW COLUMNS FROM projects");
    $existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);
    
    $name_field = 'CONCAT("مشروع #", p.id)';
    if (in_array('name', $existing_columns)) {
        $name_field = 'p.name';
    } elseif (in_array('project_name', $existing_columns)) {
        $name_field = 'p.project_name';
    } elseif (in_array('title', $existing_columns)) {
        $name_field = 'p.title';
    }
    
    $target_field = '0';
    if (in_array('target_amount', $existing_columns)) {
        $target_field = 'IFNULL(p.target_amount, 0)';
    }
    
    $collected_field = '0';
    if (in_array('contributions_collected', $existing_columns)) {
        $collected_field = 'IFNULL(p.contributions_collected, 0)';
    }
    
    $currency_field = '(SELECT id FROM currencies WHERE is_default = 1 LIMIT 1)';
    if (in_array('currency_id', $existing_columns)) {
        $currency_field = 'IFNULL(p.currency_id, (SELECT id FROM currencies WHERE is_default = 1 LIMIT 1))';
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
    
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>فحص البيانات المُحمّلة</title>
        <style>
            body { font-family: Arial; padding: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
            h1 { color: #667eea; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px; text-align: right; border: 1px solid #ddd; }
            th { background: #667eea; color: white; }
            .zero { background: #fff3cd; color: #856404; }
            .good { background: #d4edda; color: #155724; }
            .info { background: #d1ecf1; padding: 15px; border-right: 4px solid #17a2b8; margin: 20px 0; }
            .select-demo { padding: 20px; background: #f8f9fa; border-radius: 5px; margin: 20px 0; }
            select { width: 100%; padding: 10px; font-size: 16px; border: 2px solid #667eea; border-radius: 5px; }
            #result { margin-top: 20px; padding: 20px; background: white; border: 2px solid #667eea; border-radius: 5px; min-height: 100px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔍 فحص بيانات المشاريع في القائمة المنسدلة</h1>
            
            <div class="info">
                <h3>📊 الأعمدة المستخدمة في الاستعلام:</h3>
                <ul>
                    <li><strong>اسم المشروع:</strong> <?= $name_field ?></li>
                    <li><strong>الهدف:</strong> <?= $target_field ?></li>
                    <li><strong>المجموع:</strong> <?= $collected_field ?></li>
                    <li><strong>العملة:</strong> <?= $currency_field ?></li>
                </ul>
            </div>
            
            <h2>📋 المشاريع المُحمّلة (<?= count($projects) ?>):</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>اسم المشروع</th>
                        <th>الهدف</th>
                        <th>المُجمّع</th>
                        <th>المتبقي</th>
                        <th>العملة</th>
                        <th>رمز العملة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $proj): 
                        $remaining = $proj['contributions_target'] - $proj['contributions_collected'];
                        $class = $proj['contributions_target'] > 0 ? 'good' : 'zero';
                    ?>
                    <tr class="<?= $class ?>">
                        <td><?= $proj['id'] ?></td>
                        <td><?= htmlspecialchars($proj['project_name']) ?></td>
                        <td><strong><?= number_format($proj['contributions_target'], 2) ?></strong></td>
                        <td><?= number_format($proj['contributions_collected'], 2) ?></td>
                        <td><?= number_format($remaining, 2) ?></td>
                        <td><?= $proj['currency_code'] ?></td>
                        <td><?= $proj['currency_symbol'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="select-demo">
                <h2>🧪 اختبار القائمة المنسدلة:</h2>
                <p>اختر مشروعاً لعرض البيانات:</p>
                
                <select id="projectSelect" onchange="showData()">
                    <option value="">-- اختر مشروعاً --</option>
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
                
                <div id="result"></div>
            </div>
            
            <div class="info">
                <h3>💡 التشخيص:</h3>
                <ul>
                    <?php 
                    $has_zero_target = false;
                    foreach ($projects as $proj) {
                        if ($proj['contributions_target'] == 0) {
                            $has_zero_target = true;
                            echo "<li>⚠️ <strong>" . htmlspecialchars($proj['project_name']) . "</strong>: الهدف = 0 (يجب تحديثه)</li>";
                        }
                    }
                    if (!$has_zero_target) {
                        echo "<li>✅ جميع المشاريع لها أهداف محددة</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
        
        <script>
            function showData() {
                const select = document.getElementById('projectSelect');
                const option = select.options[select.selectedIndex];
                const resultDiv = document.getElementById('result');
                
                if (option.value) {
                    const target = parseFloat(option.dataset.target) || 0;
                    const collected = parseFloat(option.dataset.collected) || 0;
                    const currencySymbol = option.dataset.currencySymbol || '$';
                    const remaining = target - collected;
                    const percentage = target > 0 ? (collected / target * 100).toFixed(1) : 0;
                    
                    resultDiv.innerHTML = `
                        <h3>📊 البيانات المُستخرجة:</h3>
                        <table style="width: 100%; margin-top: 10px;">
                            <tr>
                                <th>الهدف</th>
                                <td><strong>${target.toLocaleString()} ${currencySymbol}</strong></td>
                            </tr>
                            <tr>
                                <th>المُجمّع</th>
                                <td>${collected.toLocaleString()} ${currencySymbol}</td>
                            </tr>
                            <tr>
                                <th>المتبقي</th>
                                <td>${remaining.toLocaleString()} ${currencySymbol}</td>
                            </tr>
                            <tr>
                                <th>نسبة الإنجاز</th>
                                <td>${percentage}%</td>
                            </tr>
                        </table>
                        
                        <h4>🔍 البيانات الخام من data attributes:</h4>
                        <pre style="background: #2d2d2d; color: #fff; padding: 10px; border-radius: 5px; overflow-x: auto;">
data-target: "${option.dataset.target}"
data-collected: "${option.dataset.collected}"
data-currency: "${option.dataset.currency}"
data-currency-symbol: "${option.dataset.currencySymbol}"</pre>
                    `;
                } else {
                    resultDiv.innerHTML = '<p style="color: #666;">اختر مشروعاً لعرض البيانات</p>';
                }
            }
        </script>
    </body>
    </html>
    <?php
    
} catch (PDOException $e) {
    echo "<h1>خطأ</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>


