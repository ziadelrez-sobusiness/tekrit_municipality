<?php
/**
 * سكريبت فحص وإنشاء الجداول المرجعية المفقودة
 * بلدية تكريت - عكار، شمال لبنان
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>فحص وإنشاء الجداول - بلدية تكريت</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class='bg-gray-50 p-6'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold text-gray-800 mb-6 text-center'>🔧 فحص وإنشاء الجداول المرجعية</h1>";

// قائمة الجداول المطلوبة
$required_tables = [
    'reference_data' => "
        CREATE TABLE IF NOT EXISTS reference_data (
            id INT PRIMARY KEY AUTO_INCREMENT,
            type VARCHAR(50) NOT NULL,
            value VARCHAR(255) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'roles' => "
        CREATE TABLE IF NOT EXISTS roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'departments' => "
        CREATE TABLE IF NOT EXISTS departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) UNIQUE NOT NULL,
            description TEXT,
            manager_employee_id INT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'currencies' => "
        CREATE TABLE IF NOT EXISTS currencies (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50),
            code VARCHAR(5) UNIQUE NOT NULL,
            symbol VARCHAR(5),
            exchange_rate_to_lbp DECIMAL(10, 4),
            is_active TINYINT(1) DEFAULT 1,
            is_default BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'tax_types' => "
        CREATE TABLE IF NOT EXISTS tax_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            rate DECIMAL(5, 2),
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

echo "<div class='space-y-4'>";

// فحص وإنشاء الجداول
foreach ($required_tables as $table_name => $create_sql) {
    try {
        // فحص وجود الجدول
        $check = $db->query("SHOW TABLES LIKE '{$table_name}'");
        $exists = $check->rowCount() > 0;
        
        if ($exists) {
            echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                    <div class='flex items-center'>
                        <span class='text-2xl mr-3'>✅</span>
                        <div>
                            <h3 class='font-bold text-green-800'>الجدول موجود: {$table_name}</h3>
                            <p class='text-sm text-green-600'>الجدول موجود بالفعل في قاعدة البيانات</p>
                        </div>
                    </div>
                  </div>";
        } else {
            // إنشاء الجدول
            $db->exec($create_sql);
            echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4'>
                    <div class='flex items-center'>
                        <span class='text-2xl mr-3'>🆕</span>
                        <div>
                            <h3 class='font-bold text-blue-800'>تم إنشاء الجدول: {$table_name}</h3>
                            <p class='text-sm text-blue-600'>تم إنشاء الجدول بنجاح</p>
                        </div>
                    </div>
                  </div>";
        }
    } catch (PDOException $e) {
        echo "<div class='bg-red-50 border border-red-200 rounded-lg p-4'>
                <div class='flex items-start'>
                    <span class='text-2xl mr-3'>❌</span>
                    <div>
                        <h3 class='font-bold text-red-800'>خطأ في الجدول: {$table_name}</h3>
                        <p class='text-sm text-red-600'>{$e->getMessage()}</p>
                    </div>
                </div>
              </div>";
    }
}

echo "</div>";

// إضافة بيانات أولية
echo "<div class='mt-8'>
        <h2 class='text-2xl font-bold text-gray-800 mb-4'>📝 إضافة بيانات أولية</h2>
        <div class='space-y-4'>";

// بيانات أولية للأدوار
try {
    $check = $db->query("SELECT COUNT(*) as count FROM roles")->fetch();
    if ($check['count'] == 0) {
        $roles = [
            ['admin', 'مدير النظام'],
            ['mayor', 'رئيس البلدية'],
            ['employee', 'موظف'],
            ['citizen', 'مواطن']
        ];
        
        $stmt = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        foreach ($roles as $role) {
            $stmt->execute($role);
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة الأدوار الأساسية (4 أدوار)</span>
              </div>";
    } else {
        echo "<div class='bg-gray-50 border border-gray-200 rounded-lg p-4'>
                <span class='text-gray-600'>ℹ️ الأدوار موجودة بالفعل ({$check['count']} دور)</span>
              </div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-yellow-50 border border-yellow-200 rounded-lg p-4'>
            <span class='text-yellow-800'>⚠️ تخطي إضافة الأدوار: {$e->getMessage()}</span>
          </div>";
}

// بيانات أولية للعملات
try {
    $check = $db->query("SELECT COUNT(*) as count FROM currencies")->fetch();
    if ($check['count'] == 0) {
        $currencies = [
            ['ليرة لبنانية', 'LBP', 'ل.ل', 1.0000, 1, 0],
            ['دولار أمريكي', 'USD', '$', 89500.0000, 1, 1]
        ];
        
        $stmt = $db->prepare("INSERT INTO currencies (name, code, symbol, exchange_rate_to_lbp, is_active, is_default) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($currencies as $currency) {
            $stmt->execute($currency);
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة العملات الأساسية (ليرة لبنانية ودولار أمريكي)</span>
              </div>";
    } else {
        echo "<div class='bg-gray-50 border border-gray-200 rounded-lg p-4'>
                <span class='text-gray-600'>ℹ️ العملات موجودة بالفعل ({$check['count']} عملة)</span>
              </div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-yellow-50 border border-yellow-200 rounded-lg p-4'>
            <span class='text-yellow-800'>⚠️ تخطي إضافة العملات: {$e->getMessage()}</span>
          </div>";
}

// بيانات أولية للأقسام
try {
    $check = $db->query("SELECT COUNT(*) as count FROM departments")->fetch();
    if ($check['count'] == 0) {
        $departments = [
            ['قسم الهندسة', 'قسم الهندسة والتخطيط العمراني'],
            ['قسم النظافة', 'قسم النظافة وإدارة النفايات'],
            ['القسم المالي', 'القسم المالي والمحاسبة'],
            ['قسم الموارد البشرية', 'قسم الموارد البشرية والتوظيف'],
            ['القسم الإداري', 'القسم الإداري العام']
        ];
        
        $stmt = $db->prepare("INSERT INTO departments (name, description, is_active) VALUES (?, ?, 1)");
        foreach ($departments as $dept) {
            $stmt->execute($dept);
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة الأقسام الأساسية (5 أقسام)</span>
              </div>";
    } else {
        echo "<div class='bg-gray-50 border border-gray-200 rounded-lg p-4'>
                <span class='text-gray-600'>ℹ️ الأقسام موجودة بالفعل ({$check['count']} قسم)</span>
              </div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-yellow-50 border border-yellow-200 rounded-lg p-4'>
            <span class='text-yellow-800'>⚠️ تخطي إضافة الأقسام: {$e->getMessage()}</span>
          </div>";
}

// بيانات أولية للبيانات المرجعية
try {
    $check = $db->query("SELECT COUNT(*) as count FROM reference_data")->fetch();
    if ($check['count'] == 0) {
        $reference_data = [
            // فئات الشكاوى
            ['complaint_category', 'مشاكل النظافة', 'شكاوى متعلقة بالنظافة وجمع النفايات'],
            ['complaint_category', 'مشاكل الإنارة', 'شكاوى متعلقة بإنارة الشوارع'],
            ['complaint_category', 'مشاكل الطرق', 'شكاوى متعلقة بحالة الطرق والبنية التحتية'],
            ['complaint_category', 'مشاكل المياه', 'شكاوى متعلقة بالمياه والصرف الصحي'],
            
            // فئات المصروفات
            ['expense_category', 'رواتب', 'رواتب الموظفين'],
            ['expense_category', 'صيانة', 'صيانة المرافق والمعدات'],
            ['expense_category', 'وقود', 'وقود الآليات والمركبات'],
            ['expense_category', 'مشتريات', 'مشتريات عامة'],
            
            // أنواع المخالفات
            ['violation_type', 'مخالفة بناء', 'مخالفات البناء غير المرخص'],
            ['violation_type', 'مخالفة نظافة', 'مخالفات النظافة العامة'],
            ['violation_type', 'مخالفة إشغال', 'مخالفات إشغال الأملاك العامة'],
            
            // أنواع الإجازات
            ['leave_type', 'إجازة سنوية', 'إجازة سنوية اعتيادية'],
            ['leave_type', 'إجازة مرضية', 'إجازة مرضية'],
            ['leave_type', 'إجازة طارئة', 'إجازة طارئة']
        ];
        
        $stmt = $db->prepare("INSERT INTO reference_data (type, value, description, is_active) VALUES (?, ?, ?, 1)");
        foreach ($reference_data as $data) {
            $stmt->execute($data);
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة البيانات المرجعية الأساسية (" . count($reference_data) . " سجل)</span>
              </div>";
    } else {
        echo "<div class='bg-gray-50 border border-gray-200 rounded-lg p-4'>
                <span class='text-gray-600'>ℹ️ البيانات المرجعية موجودة بالفعل ({$check['count']} سجل)</span>
              </div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-yellow-50 border border-yellow-200 rounded-lg p-4'>
            <span class='text-yellow-800'>⚠️ تخطي إضافة البيانات المرجعية: {$e->getMessage()}</span>
          </div>";
}

echo "</div></div>";

echo "<div class='mt-8 text-center'>
        <a href='all_tables_manager.php' class='bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg inline-block transition duration-200'>
            ✅ الذهاب لصفحة إدارة الجداول المرجعية
        </a>
        <a href='comprehensive_dashboard.php' class='bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-lg inline-block transition duration-200 mr-4'>
            🏠 العودة للوحة التحكم
        </a>
      </div>";

echo "<div class='mt-6 text-center text-sm text-gray-500'>
        <p>🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
        <p class='mt-1'>نظام إدارة البلدية الإلكتروني</p>
      </div>";

echo "</div>
    </div>
</body>
</html>";
?>

