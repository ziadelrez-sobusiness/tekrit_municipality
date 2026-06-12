<?php
echo "<h1>🧪 اختبار وظيفة تعديل المبادرة</h1>";

// اختبار 1: التحقق من وجود الملفات المطلوبة
echo "<h2>📁 فحص الملفات:</h2>";

$files_to_check = [
    'modules/public_content_management.php' => 'ملف إدارة المحتوى العام',
    'modules/get_initiative.php' => 'ملف جلب بيانات المبادرة'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $description موجود</p>";
    } else {
        echo "<p style='color: red;'>❌ $description غير موجود</p>";
    }
}

// اختبار 2: التحقق من وجود الكود المطلوب
echo "<h2>🔍 فحص الكود:</h2>";

$main_file = file_get_contents('modules/public_content_management.php');

$code_checks = [
    'edit_initiative' => 'كود معالجة تعديل المبادرة',
    'editInitiativeModal' => 'نموذج تعديل المبادرة',
    'function editInitiative' => 'دالة JavaScript لتعديل المبادرة',
    'onclick="editInitiative(' => 'زر تعديل المبادرة'
];

foreach ($code_checks as $search => $description) {
    if (strpos($main_file, $search) !== false) {
        echo "<p style='color: green;'>✅ $description موجود</p>";
    } else {
        echo "<p style='color: red;'>❌ $description غير موجود</p>";
    }
}

// اختبار 3: التحقق من قاعدة البيانات
echo "<h2>🗄️ فحص قاعدة البيانات:</h2>";

try {
    require_once 'config/database.php';
    
    // التحقق من وجود جدول المبادرات
    $stmt = $db->query("SHOW TABLES LIKE 'youth_environmental_initiatives'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ جدول المبادرات موجود</p>";
        
        // التحقق من وجود مبادرات للاختبار
        $stmt = $db->query("SELECT COUNT(*) as count FROM youth_environmental_initiatives");
        $result = $stmt->fetch();
        echo "<p>📊 عدد المبادرات: " . $result['count'] . "</p>";
        
        if ($result['count'] > 0) {
            // جلب أول مبادرة للاختبار
            $stmt = $db->query("SELECT id, initiative_name FROM youth_environmental_initiatives LIMIT 1");
            $initiative = $stmt->fetch();
            echo "<p>🎯 مبادرة للاختبار: " . $initiative['initiative_name'] . " (ID: " . $initiative['id'] . ")</p>";
            
            // اختبار ملف get_initiative.php
            echo "<h3>🔗 اختبار ملف جلب البيانات:</h3>";
            $test_url = "modules/get_initiative.php?id=" . $initiative['id'];
            echo "<p><a href='$test_url' target='_blank'>اختبار جلب بيانات المبادرة</a></p>";
        }
    } else {
        echo "<p style='color: red;'>❌ جدول المبادرات غير موجود</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage() . "</p>";
}

echo "<h2>📋 التعليمات:</h2>";
echo "<ol>";
echo "<li>انتقل إلى صفحة إدارة المحتوى العام</li>";
echo "<li>اذهب إلى تبويب المبادرات</li>";
echo "<li>اضغط على زر 'تعديل' بجانب أي مبادرة</li>";
echo "<li>يجب أن يظهر نموذج التعديل مع البيانات محملة</li>";
echo "<li>قم بتعديل البيانات واضغط 'تحديث المبادرة'</li>";
echo "</ol>";

echo "<h2>🚀 رابط الاختبار:</h2>";
echo "<p><a href='modules/public_content_management.php?tab=initiatives' target='_blank' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>اختبار تعديل المبادرة</a></p>";
?> 