<?php
/**
 * إنشاء جدول اللجان
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء جدول اللجان</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { 
            color: #667eea; 
            text-align: center; 
            margin-bottom: 10px;
            font-size: 2em;
        }
        .subtitle { 
            text-align: center; 
            color: #666; 
            margin-bottom: 30px;
        }
        .status-box { 
            padding: 15px; 
            margin: 15px 0; 
            border-radius: 8px; 
            border-left: 5px solid;
        }
        .success { background: #d4edda; border-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        .btn:hover { background: #5568d3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .actions { text-align: center; margin-top: 30px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏛️ إنشاء جدول اللجان</h1>
        <p class="subtitle">إعداد جدول اللجان البلدية</p>

        <?php
        require_once 'config/database.php';

        try {
            $db = Database::getInstance()->getConnection();
            echo '<div class="status-box info">✅ تم الاتصال بقاعدة البيانات بنجاح</div>';

            // قراءة ملف SQL
            $sql_file = 'create_committees_table.sql';
            if (!file_exists($sql_file)) {
                throw new Exception("ملف SQL غير موجود: $sql_file");
            }

            $sql_content = file_get_contents($sql_file);
            
            // تقسيم الأوامر
            $commands = array_filter(
                array_map('trim', explode(';', $sql_content)),
                function($cmd) {
                    return !empty($cmd) && 
                           stripos($cmd, 'USE ') !== 0 && 
                           stripos($cmd, '--') !== 0 &&
                           stripos($cmd, '/*') !== 0 &&
                           stripos($cmd, '=====') !== 0;
                }
            );

            echo '<div class="status-box info">📊 عدد الأوامر: ' . count($commands) . '</div>';
            
            $success_count = 0;
            $error_count = 0;

            foreach ($commands as $index => $command) {
                $command = trim($command);
                if (empty($command)) continue;

                try {
                    $stmt = $db->prepare($command);
                    $stmt->execute();
                    $success_count++;
                    
                    // عرض نتيجة SELECT
                    if (stripos($command, 'SELECT') === 0) {
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result) {
                            echo '<div class="status-box success">';
                            foreach ($result as $key => $value) {
                                echo "<strong>$key:</strong> $value<br>";
                            }
                            echo '</div>';
                        }
                    }

                } catch (PDOException $e) {
                    $error_msg = $e->getMessage();
                    
                    // تجاهل أخطاء "already exists" أو "Duplicate"
                    if (
                        stripos($error_msg, 'already exists') !== false ||
                        stripos($error_msg, 'Duplicate') !== false
                    ) {
                        continue;
                    }
                    
                    $error_count++;
                    echo '<div class="status-box warning">';
                    echo '⚠️ تحذير: ' . htmlspecialchars($error_msg);
                    echo '</div>';
                }
            }

            echo '<div class="status-box success">';
            echo '✅ أوامر ناجحة: ' . $success_count . '<br>';
            if ($error_count > 0) {
                echo '⚠️ تحذيرات: ' . $error_count;
            }
            echo '</div>';

            // التحقق من الجدول
            $check = $db->query("SHOW TABLES LIKE 'committees'");
            if ($check->rowCount() > 0) {
                echo '<div class="status-box success">✅ جدول committees تم إنشاؤه بنجاح</div>';
                
                // عرض البيانات
                $stmt = $db->query("SELECT * FROM committees ORDER BY id");
                $committees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($committees)) {
                    echo '<h3 style="color: #667eea; margin-top: 30px;">📋 اللجان المضافة (' . count($committees) . ')</h3>';
                    echo '<table>';
                    echo '<tr><th>#</th><th>الرمز</th><th>اسم اللجنة</th><th>النوع</th><th>عدد الأعضاء</th><th>الحالة</th></tr>';
                    foreach ($committees as $committee) {
                        $status = $committee['is_active'] ? '✅ نشط' : '❌ غير نشط';
                        echo '<tr>';
                        echo '<td>' . $committee['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($committee['committee_code']) . '</td>';
                        echo '<td><strong>' . htmlspecialchars($committee['committee_name']) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($committee['committee_type'] ?? '-') . '</td>';
                        echo '<td>' . $committee['members_count'] . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            } else {
                echo '<div class="status-box error">❌ فشل إنشاء جدول committees</div>';
            }

            echo '<div class="status-box success" style="font-size: 1.2em; text-align: center; margin-top: 30px;">';
            echo '🎉 تم إعداد جدول اللجان بنجاح!';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="status-box error">';
            echo '❌ خطأ: ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>

        <div class="actions">
            <a href="execute_budget_update.php" class="btn btn-success">➡️ التالي: تحديث نظام الميزانيات</a>
            <a href="modules/budgets.php" class="btn">📊 الذهاب إلى الميزانيات</a>
            <a href="comprehensive_dashboard.php" class="btn">🏠 العودة للوحة التحكم</a>
        </div>
    </div>
</body>
</html>


