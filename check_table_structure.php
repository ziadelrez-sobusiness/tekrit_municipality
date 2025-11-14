<?php
/**
 * فحص بنية الجداول المرجعية
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>فحص بنية الجداول - بلدية تكريت</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class='bg-gray-50 p-6'>
    <div class='max-w-6xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold text-gray-800 mb-6 text-center'>🔍 فحص بنية الجداول المرجعية</h1>";

$tables = ['departments', 'currencies', 'tax_types', 'roles', 'reference_data', 'request_types'];

foreach ($tables as $table) {
    echo "<div class='mb-6 bg-gray-50 rounded-lg p-4'>";
    echo "<h2 class='text-xl font-bold text-gray-800 mb-3'>📊 جدول: {$table}</h2>";
    
    try {
        // فحص وجود الجدول
        $check = $db->query("SHOW TABLES LIKE '{$table}'")->fetch();
        
        if ($check) {
            // جلب بنية الجدول
            $columns = $db->query("DESCRIBE {$table}")->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<div class='overflow-x-auto'>
                    <table class='w-full border-collapse text-sm'>
                        <thead>
                            <tr class='bg-indigo-600 text-white'>
                                <th class='p-2 border text-right'>اسم العمود</th>
                                <th class='p-2 border text-right'>النوع</th>
                                <th class='p-2 border text-center'>Null</th>
                                <th class='p-2 border text-center'>Key</th>
                                <th class='p-2 border text-right'>Default</th>
                            </tr>
                        </thead>
                        <tbody>";
            
            foreach ($columns as $col) {
                echo "<tr class='border-b hover:bg-gray-100'>
                        <td class='p-2 border font-bold'>{$col['Field']}</td>
                        <td class='p-2 border'>{$col['Type']}</td>
                        <td class='p-2 border text-center'>{$col['Null']}</td>
                        <td class='p-2 border text-center'>{$col['Key']}</td>
                        <td class='p-2 border'>{$col['Default']}</td>
                      </tr>";
            }
            
            echo "</tbody></table></div>";
            
            // عرض عدد السجلات
            $count = $db->query("SELECT COUNT(*) as count FROM {$table}")->fetch();
            echo "<div class='mt-2 text-sm text-gray-600'>
                    📊 عدد السجلات: <strong>{$count['count']}</strong>
                  </div>";
            
            // عرض بعض البيانات
            if ($count['count'] > 0 && $count['count'] <= 10) {
                echo "<div class='mt-3'>
                        <h3 class='font-bold text-gray-700 mb-2'>📝 البيانات الموجودة:</h3>";
                $data = $db->query("SELECT * FROM {$table} LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                echo "<div class='overflow-x-auto'>
                        <table class='w-full border-collapse text-xs'>
                            <thead>
                                <tr class='bg-gray-200'>";
                foreach (array_keys($data[0]) as $key) {
                    echo "<th class='p-1 border text-right'>{$key}</th>";
                }
                echo "</tr></thead><tbody>";
                foreach ($data as $row) {
                    echo "<tr class='border-b'>";
                    foreach ($row as $value) {
                        $display = is_null($value) ? '<span class="text-gray-400">NULL</span>' : htmlspecialchars(substr($value, 0, 50));
                        echo "<td class='p-1 border'>{$display}</td>";
                    }
                    echo "</tr>";
                }
                echo "</tbody></table></div></div>";
            }
            
        } else {
            echo "<div class='bg-red-50 border border-red-200 rounded p-3'>
                    <span class='text-red-800'>❌ الجدول غير موجود</span>
                  </div>";
        }
    } catch (PDOException $e) {
        echo "<div class='bg-red-50 border border-red-200 rounded p-3'>
                <span class='text-red-800'>❌ خطأ: {$e->getMessage()}</span>
              </div>";
    }
    
    echo "</div>";
}

echo "<div class='mt-8 text-center'>
        <a href='all_tables_manager.php' class='bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg inline-block transition duration-200'>
            📊 إدارة الجداول المرجعية
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


