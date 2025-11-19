<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<html dir='rtl'><head><meta charset='UTF-8'><title>فحص قاعدة البيانات</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5}";
echo ".box{background:white;padding:15px;margin:10px 0;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1)}";
echo ".success{color:green;font-weight:bold}.error{color:red;font-weight:bold}</style></head><body>";

echo "<h1>🔍 فحص قاعدة بيانات الصلاحيات</h1>";

// 1. فحص وجود حقل category
echo "<div class='box'>";
echo "<h2>1️⃣ فحص بنية جدول permissions</h2>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM permissions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;width:100%'>";
    echo "<tr><th>اسم الحقل</th><th>النوع</th></tr>";

    $has_category = false;
    $has_updated_at = false;

    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
        if ($col['Field'] == 'category') $has_category = true;
        if ($col['Field'] == 'updated_at') $has_updated_at = true;
    }
    echo "</table>";

    echo "<p>";
    echo $has_category ? "✅ <span class='success'>حقل category موجود</span>" : "❌ <span class='error'>حقل category غير موجود - يجب تشغيل السكريبت!</span>";
    echo "<br>";
    echo $has_updated_at ? "✅ <span class='success'>حقل updated_at موجود</span>" : "⚠️ <span style='color:orange'>حقل updated_at غير موجود</span>";
    echo "</p>";

} catch (Exception $e) {
    echo "<p class='error'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 2. عدد الصلاحيات
echo "<div class='box'>";
echo "<h2>2️⃣ إحصائيات الصلاحيات</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM permissions");
    $total = $stmt->fetch()['total'];

    echo "<p><strong>إجمالي الصلاحيات:</strong> ";
    if ($total > 0) {
        echo "<span class='success'>{$total} صلاحية ✅</span>";
    } else {
        echo "<span class='error'>0 - الجدول فارغ! ❌</span>";
    }
    echo "</p>";

    // تفصيل حسب الفئة إذا كان category موجود
    if ($has_category && $total > 0) {
        echo "<h3>تفصيل حسب الفئة:</h3>";
        $stmt = $db->query("
            SELECT
                category,
                COUNT(*) as count
            FROM permissions
            WHERE category IS NOT NULL
            GROUP BY category
            ORDER BY
                CASE category
                    WHEN 'general_admin' THEN 1
                    WHEN 'finance' THEN 2
                    WHEN 'projects' THEN 3
                    WHEN 'citizens' THEN 4
                    WHEN 'services' THEN 5
                    WHEN 'maps' THEN 6
                    WHEN 'website' THEN 7
                    WHEN 'reports' THEN 8
                    WHEN 'settings' THEN 9
                    ELSE 10
                END
        ");

        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>الفئة</th><th>عدد الصلاحيات</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['category']}</td><td>{$row['count']}</td></tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p class='error'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 3. عدد صلاحيات المستخدمين
echo "<div class='box'>";
echo "<h2>3️⃣ صلاحيات المستخدمين</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_permissions");
    $user_perms = $stmt->fetch()['total'];

    echo "<p><strong>إجمالي الصلاحيات الممنوحة للمستخدمين:</strong> ";
    if ($user_perms > 0) {
        echo "<span class='success'>{$user_perms} صلاحية ممنوحة ✅</span>";
    } else {
        echo "<span class='error'>0 - لم يتم منح صلاحيات لأي مستخدم! ❌</span>";
    }
    echo "</p>";

    if ($user_perms > 0) {
        echo "<h3>تفصيل حسب المستخدم:</h3>";
        $stmt = $db->query("
            SELECT
                u.username,
                u.full_name,
                COUNT(up.id) as perm_count
            FROM users u
            LEFT JOIN user_permissions up ON u.id = up.user_id AND up.is_active = 1
            GROUP BY u.id
            ORDER BY perm_count DESC
            LIMIT 10
        ");

        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>اسم المستخدم</th><th>الاسم الكامل</th><th>عدد الصلاحيات</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['username']}</td><td>{$row['full_name']}</td><td>{$row['perm_count']}</td></tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p class='error'>خطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 4. اختبار استعلام permissions.php
echo "<div class='box'>";
echo "<h2>4️⃣ اختبار استعلام صفحة الصلاحيات</h2>";
try {
    // جلب أول مستخدم
    $stmt = $db->query("SELECT id, username FROM users LIMIT 1");
    $test_user = $stmt->fetch();

    if ($test_user) {
        echo "<p>اختبار مع المستخدم: <strong>{$test_user['username']}</strong> (ID: {$test_user['id']})</p>";

        // تشغيل نفس الاستعلام من permissions.php
        $stmt = $db->prepare("
            SELECT p.*,
                   CASE WHEN up.id IS NOT NULL THEN 1 ELSE 0 END as granted
            FROM permissions p
            LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.user_id = ? AND up.is_active = 1
            WHERE p.is_active = 1
            ORDER BY
                CASE p.category
                    WHEN 'general_admin' THEN 1
                    WHEN 'finance' THEN 2
                    WHEN 'projects' THEN 3
                    WHEN 'citizens' THEN 4
                    WHEN 'services' THEN 5
                    WHEN 'maps' THEN 6
                    WHEN 'website' THEN 7
                    WHEN 'reports' THEN 8
                    WHEN 'settings' THEN 9
                    ELSE 10
                END,
                p.sort_order,
                p.display_name
        ");
        $stmt->execute([$test_user['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<p class='success'>✅ الاستعلام نجح! تم جلب " . count($permissions) . " صلاحية</p>";

        if (count($permissions) > 0) {
            $granted_count = count(array_filter($permissions, function($p) { return $p['granted'] == 1; }));
            echo "<p>الصلاحيات الممنوحة لهذا المستخدم: <strong>{$granted_count}</strong></p>";

            // عرض أول 5 صلاحيات كمثال
            echo "<h4>عينة من الصلاحيات (أول 5):</h4>";
            echo "<table border='1' cellpadding='5' style='border-collapse:collapse;font-size:12px'>";
            echo "<tr><th>الصلاحية</th><th>الاسم</th><th>الفئة</th><th>ممنوحة؟</th></tr>";
            for ($i = 0; $i < min(5, count($permissions)); $i++) {
                $p = $permissions[$i];
                $granted_badge = $p['granted'] == 1 ? "✅" : "❌";
                echo "<tr><td>{$p['permission_name']}</td><td>{$p['display_name']}</td><td>{$p['category']}</td><td>{$granted_badge}</td></tr>";
            }
            echo "</table>";
        }

    } else {
        echo "<p class='error'>لا يوجد مستخدمون في النظام!</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ الاستعلام فشل: " . $e->getMessage() . "</p>";
    echo "<p>هذا يعني أن حقل category غير موجود في جدول permissions!</p>";
}
echo "</div>";

// التوصيات
echo "<div class='box' style='background:#fffbeb;border-right:4px solid #f59e0b'>";
echo "<h2>📋 التوصيات</h2>";

if (!$has_category) {
    echo "<p class='error'><strong>⚠️ يجب تشغيل السكريبت:</strong></p>";
    echo "<pre>mysql -u root -p tekrit_municipality < database/add_category_to_permissions.sql</pre>";
    echo "<p>أو من phpMyAdmin: استيراد الملف <code>database/add_category_to_permissions.sql</code></p>";
} elseif ($total == 0) {
    echo "<p class='error'><strong>⚠️ جدول permissions فارغ!</strong> يجب تشغيل جزء INSERT من السكريبت</p>";
} elseif ($user_perms == 0) {
    echo "<p style='color:orange'><strong>⚠️ لم يتم منح صلاحيات لأي مستخدم</strong></p>";
    echo "<p>يجب الذهاب إلى صفحة الصلاحيات وتعيين صلاحيات للمستخدمين</p>";
} else {
    echo "<p class='success'><strong>✅ كل شيء يعمل بشكل صحيح!</strong></p>";
}

echo "</div>";

echo "</body></html>";
?>
