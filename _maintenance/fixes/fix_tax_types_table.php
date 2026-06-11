<?php
/**
 * إصلاح جدول أنواع الضرائب
 * بلدية تكريت - عكار، شمال لبنان
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>إصلاح جدول أنواع الضرائب - بلدية تكريت</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class='bg-gray-50 p-6'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold text-gray-800 mb-6 text-center'>🔧 إصلاح جدول أنواع الضرائب</h1>
            <div class='space-y-4'>";

// 1. فحص البنية الحالية
echo "<h2 class='text-xl font-bold text-gray-800 mb-3'>📊 البنية الحالية للجدول:</h2>";

try {
    $columns = $db->query("DESCRIBE tax_types")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='bg-blue-50 border border-blue-200 rounded p-4 mb-4'>
            <table class='w-full text-sm'>
                <thead>
                    <tr class='border-b'>
                        <th class='text-right p-2'>اسم العمود</th>
                        <th class='text-right p-2'>النوع</th>
                    </tr>
                </thead>
                <tbody>";
    
    $existing_columns = [];
    foreach ($columns as $col) {
        $existing_columns[] = $col['Field'];
        echo "<tr class='border-b'>
                <td class='p-2 font-bold'>{$col['Field']}</td>
                <td class='p-2'>{$col['Type']}</td>
              </tr>";
    }
    
    echo "</tbody></table></div>";
    
    // 2. إضافة الأعمدة المفقودة
    echo "<h2 class='text-xl font-bold text-gray-800 mb-3'>🔧 إضافة الأعمدة المفقودة:</h2>";
    
    $required_columns = [
        'tax_name' => "VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        'tax_description' => "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        'tax_rate' => "DECIMAL(5, 2)"
    ];
    
    foreach ($required_columns as $column => $type) {
        if (!in_array($column, $existing_columns)) {
            try {
                $db->exec("ALTER TABLE tax_types ADD COLUMN {$column} {$type}");
                echo "<div class='bg-green-50 border border-green-200 rounded p-3 mb-2'>
                        <span class='text-green-800'>✅ تمت إضافة العمود: {$column}</span>
                      </div>";
            } catch (PDOException $e) {
                echo "<div class='bg-red-50 border border-red-200 rounded p-3 mb-2'>
                        <span class='text-red-800'>❌ خطأ في إضافة {$column}: {$e->getMessage()}</span>
                      </div>";
            }
        } else {
            echo "<div class='bg-gray-50 border border-gray-200 rounded p-3 mb-2'>
                    <span class='text-gray-600'>ℹ️ العمود موجود بالفعل: {$column}</span>
                  </div>";
        }
    }
    
    // 3. عرض البيانات الحالية
    echo "<h2 class='text-xl font-bold text-gray-800 mt-6 mb-3'>📝 البيانات الحالية:</h2>";
    
    $data = $db->query("SELECT * FROM tax_types LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        echo "<div class='bg-yellow-50 border border-yellow-200 rounded p-4'>
                <span class='text-yellow-800'>⚠️ الجدول فارغ - لا توجد بيانات</span>
              </div>";
    } else {
        echo "<div class='overflow-x-auto'>
                <table class='w-full border-collapse text-sm'>
                    <thead>
                        <tr class='bg-indigo-600 text-white'>";
        
        foreach (array_keys($data[0]) as $key) {
            echo "<th class='p-2 border text-right'>{$key}</th>";
        }
        
        echo "</tr></thead><tbody>";
        
        foreach ($data as $row) {
            echo "<tr class='border-b hover:bg-gray-50'>";
            foreach ($row as $value) {
                $display = is_null($value) ? '<span class="text-gray-400">NULL</span>' : htmlspecialchars($value);
                echo "<td class='p-2 border'>{$display}</td>";
            }
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
    }
    
    // 4. تحديث أسماء الأعمدة في all_tables_manager.php
    echo "<h2 class='text-xl font-bold text-gray-800 mt-6 mb-3'>📋 الأعمدة الصحيحة للاستخدام:</h2>";
    
    $final_columns = $db->query("DESCRIBE tax_types")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='bg-blue-50 border border-blue-200 rounded p-4'>
            <p class='font-bold mb-2'>استخدم هذه الأعمدة في all_tables_manager.php:</p>
            <code class='block bg-white p-3 rounded text-sm'>";
    
    $col_names = [];
    foreach ($final_columns as $col) {
        $col_names[] = "'{$col['Field']}'";
    }
    
    echo "'columns' => [" . implode(', ', $col_names) . "]";
    
    echo "</code></div>";
    
} catch (PDOException $e) {
    echo "<div class='bg-red-50 border border-red-200 rounded p-4'>
            <span class='text-red-800'>❌ خطأ: {$e->getMessage()}</span>
          </div>";
}

echo "</div>
        
        <div class='mt-8 text-center'>
            <a href='all_tables_manager.php?table=tax_types' class='bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg inline-block transition duration-200'>
                ✅ الذهاب لصفحة إدارة أنواع الضرائب
            </a>
            <a href='check_table_structure.php' class='bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-lg inline-block transition duration-200 mr-4'>
                🔍 فحص بنية الجداول
            </a>
        </div>
        
        <div class='mt-6 text-center text-sm text-gray-500'>
            <p>🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
        </div>
        
        </div>
    </div>
</body>
</html>";
?>


