<?php
/**
 * سكريبت إضافة البيانات الأولية للجداول المرجعية
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
    <title>إضافة البيانات الأولية - بلدية تكريت</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class='bg-gray-50 p-6'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold text-gray-800 mb-6 text-center'>📝 إضافة البيانات الأولية</h1>
            <div class='space-y-4'>";

// 1. إضافة الأقسام الإدارية
echo "<h2 class='text-xl font-bold text-gray-800 mt-6 mb-3'>🏢 الأقسام الإدارية</h2>";

try {
    $check = $db->query("SELECT COUNT(*) as count FROM departments")->fetch();
    
    if ($check['count'] == 0) {
        $departments = [
            ['قسم الهندسة', 'قسم الهندسة والتخطيط العمراني'],
            ['قسم النظافة', 'قسم النظافة وإدارة النفايات'],
            ['القسم المالي', 'القسم المالي والمحاسبة'],
            ['قسم الموارد البشرية', 'قسم الموارد البشرية والتوظيف'],
            ['القسم الإداري', 'القسم الإداري العام'],
            ['قسم التراخيص', 'قسم التراخيص والرخص البلدية'],
            ['قسم الجباية', 'قسم الجباية وتحصيل الرسوم']
        ];
        
        $stmt = $db->prepare("INSERT INTO departments (department_name, department_description, is_active) VALUES (?, ?, 1)");
        $count = 0;
        foreach ($departments as $dept) {
            try {
                $stmt->execute($dept);
                $count++;
            } catch (PDOException $e) {
                // تخطي إذا كان القسم موجود
            }
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة {$count} قسم إداري</span>
              </div>";
    } else {
        echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4'>
                <span class='text-blue-800'>ℹ️ الأقسام موجودة بالفعل ({$check['count']} قسم)</span>
              </div>";
        
        // عرض الأقسام الموجودة
        $depts = $db->query("SELECT id, department_name, is_active FROM departments ORDER BY id")->fetchAll();
        echo "<div class='mt-2 bg-white border border-gray-200 rounded p-3'>
                <ul class='space-y-1 text-sm'>";
        foreach ($depts as $d) {
            $status = $d['is_active'] ? '<span class="text-green-600">نشط</span>' : '<span class="text-red-600">غير نشط</span>';
            echo "<li>• {$d['department_name']} - {$status}</li>";
        }
        echo "</ul></div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-red-50 border border-red-200 rounded-lg p-4'>
            <span class='text-red-800'>❌ خطأ: {$e->getMessage()}</span>
          </div>";
}

// 2. إضافة العملات
echo "<h2 class='text-xl font-bold text-gray-800 mt-6 mb-3'>💱 العملات</h2>";

try {
    $check = $db->query("SELECT COUNT(*) as count FROM currencies")->fetch();
    
    if ($check['count'] == 0) {
        // إضافة العملات
        $currencies = [
            ['ليرة لبنانية', 'LBP', 'ل.ل', 1.0000, 1, 0],
            ['دولار أمريكي', 'USD', '$', 89500.0000, 1, 1]
        ];
        
        $stmt = $db->prepare("INSERT INTO currencies (currency_name, currency_code, currency_symbol, exchange_rate_to_lbp, is_active, is_default) VALUES (?, ?, ?, ?, ?, ?)");
        $count = 0;
        foreach ($currencies as $currency) {
            try {
                $stmt->execute($currency);
                $count++;
            } catch (PDOException $e) {
                // تخطي إذا كانت العملة موجودة
            }
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة {$count} عملة</span>
              </div>";
    } else {
        echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4'>
                <span class='text-blue-800'>ℹ️ العملات موجودة بالفعل ({$check['count']} عملة)</span>
              </div>";
        
        // عرض العملات الموجودة
        $currencies = $db->query("SELECT id, currency_name, currency_code, currency_symbol, exchange_rate_to_lbp, is_active FROM currencies ORDER BY id")->fetchAll();
        echo "<div class='mt-2 bg-white border border-gray-200 rounded p-3'>
                <ul class='space-y-1 text-sm'>";
        foreach ($currencies as $c) {
            $status = $c['is_active'] ? '<span class="text-green-600">نشط</span>' : '<span class="text-red-600">غير نشط</span>';
            echo "<li>• {$c['currency_name']} ({$c['currency_code']}) - {$c['currency_symbol']} - سعر الصرف: " . number_format($c['exchange_rate_to_lbp'], 2) . " - {$status}</li>";
        }
        echo "</ul></div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-red-50 border border-red-200 rounded-lg p-4'>
            <span class='text-red-800'>❌ خطأ: {$e->getMessage()}</span>
          </div>";
}

// 3. إضافة أنواع الضرائب
echo "<h2 class='text-xl font-bold text-gray-800 mt-6 mb-3'>📋 أنواع الضرائب</h2>";

try {
    $check = $db->query("SELECT COUNT(*) as count FROM tax_types")->fetch();
    
    if ($check['count'] == 0) {
        $tax_types = [
            ['ضريبة الأملاك', 'ضريبة على الأملاك العقارية', 1.50],
            ['رسوم النظافة', 'رسوم خدمات النظافة وجمع النفايات', 0.50],
            ['رسوم الإنارة', 'رسوم إنارة الشوارع والأماكن العامة', 0.30],
            ['رسوم البناء', 'رسوم تراخيص البناء والتشييد', 2.00],
            ['رسوم المهن', 'رسوم تراخيص المهن والأعمال التجارية', 1.00],
            ['رسوم الإشغال', 'رسوم إشغال الأملاك العامة', 1.50],
            ['رسوم الدفن', 'رسوم خدمات المقابر والدفن', 0.00]
        ];
        
        $stmt = $db->prepare("INSERT INTO tax_types (tax_name, tax_description, tax_rate, is_active) VALUES (?, ?, ?, 1)");
        $count = 0;
        foreach ($tax_types as $tax) {
            try {
                $stmt->execute($tax);
                $count++;
            } catch (PDOException $e) {
                // تخطي إذا كان النوع موجود
            }
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة {$count} نوع ضريبة</span>
              </div>";
    } else {
        echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4'>
                <span class='text-blue-800'>ℹ️ أنواع الضرائب موجودة بالفعل ({$check['count']} نوع)</span>
              </div>";
        
        // عرض أنواع الضرائب الموجودة
        $taxes = $db->query("SELECT id, tax_name, tax_rate, is_active FROM tax_types ORDER BY id")->fetchAll();
        echo "<div class='mt-2 bg-white border border-gray-200 rounded p-3'>
                <ul class='space-y-1 text-sm'>";
        foreach ($taxes as $t) {
            $status = $t['is_active'] ? '<span class="text-green-600">نشط</span>' : '<span class="text-red-600">غير نشط</span>';
            echo "<li>• {$t['tax_name']} - النسبة: {$t['tax_rate']}% - {$status}</li>";
        }
        echo "</ul></div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-red-50 border border-red-200 rounded-lg p-4'>
            <span class='text-red-800'>❌ خطأ: {$e->getMessage()}</span>
          </div>";
}

// 4. إضافة الأدوار
echo "<h2 class='text-xl font-bold text-gray-800 mt-6 mb-3'>👤 الأدوار</h2>";

try {
    $check = $db->query("SELECT COUNT(*) as count FROM roles")->fetch();
    
    if ($check['count'] == 0) {
        $roles = [
            ['admin', 'مدير النظام - صلاحيات كاملة'],
            ['mayor', 'رئيس البلدية'],
            ['department_manager', 'مدير قسم'],
            ['employee', 'موظف'],
            ['accountant', 'محاسب'],
            ['citizen', 'مواطن']
        ];
        
        $stmt = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $count = 0;
        foreach ($roles as $role) {
            try {
                $stmt->execute($role);
                $count++;
            } catch (PDOException $e) {
                // تخطي إذا كان الدور موجود
            }
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة {$count} دور</span>
              </div>";
    } else {
        echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4'>
                <span class='text-blue-800'>ℹ️ الأدوار موجودة بالفعل ({$check['count']} دور)</span>
              </div>";
        
        // عرض الأدوار الموجودة
        $roles = $db->query("SELECT id, name, description FROM roles ORDER BY id")->fetchAll();
        echo "<div class='mt-2 bg-white border border-gray-200 rounded p-3'>
                <ul class='space-y-1 text-sm'>";
        foreach ($roles as $r) {
            echo "<li>• {$r['name']} - {$r['description']}</li>";
        }
        echo "</ul></div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-red-50 border border-red-200 rounded-lg p-4'>
            <span class='text-red-800'>❌ خطأ: {$e->getMessage()}</span>
          </div>";
}

// 5. إضافة البيانات المرجعية
echo "<h2 class='text-xl font-bold text-gray-800 mt-6 mb-3'>📊 البيانات المرجعية</h2>";

try {
    $check = $db->query("SELECT COUNT(*) as count FROM reference_data")->fetch();
    
    if ($check['count'] < 5) {
        $reference_data = [
            // فئات الشكاوى
            ['complaint_category', 'مشاكل النظافة', 'شكاوى متعلقة بالنظافة وجمع النفايات'],
            ['complaint_category', 'مشاكل الإنارة', 'شكاوى متعلقة بإنارة الشوارع'],
            ['complaint_category', 'مشاكل الطرق', 'شكاوى متعلقة بحالة الطرق والبنية التحتية'],
            ['complaint_category', 'مشاكل المياه', 'شكاوى متعلقة بالمياه والصرف الصحي'],
            ['complaint_category', 'مشاكل البيئة', 'شكاوى بيئية عامة'],
            
            // فئات المصروفات
            ['expense_category', 'رواتب', 'رواتب الموظفين والأجور'],
            ['expense_category', 'صيانة', 'صيانة المرافق والمعدات'],
            ['expense_category', 'وقود', 'وقود الآليات والمركبات'],
            ['expense_category', 'مشتريات', 'مشتريات عامة ومستلزمات'],
            ['expense_category', 'خدمات', 'خدمات متنوعة'],
            
            // أنواع المخالفات
            ['violation_type', 'مخالفة بناء', 'مخالفات البناء غير المرخص'],
            ['violation_type', 'مخالفة نظافة', 'مخالفات النظافة العامة'],
            ['violation_type', 'مخالفة إشغال', 'مخالفات إشغال الأملاك العامة'],
            ['violation_type', 'مخالفة بيئية', 'مخالفات بيئية'],
            
            // أنواع الإجازات
            ['leave_type', 'إجازة سنوية', 'إجازة سنوية اعتيادية'],
            ['leave_type', 'إجازة مرضية', 'إجازة مرضية بموجب تقرير طبي'],
            ['leave_type', 'إجازة طارئة', 'إجازة طارئة لظروف استثنائية'],
            ['leave_type', 'إجازة أمومة', 'إجازة أمومة'],
            
            // حالات المشاريع
            ['project_status', 'قيد التخطيط', 'المشروع في مرحلة التخطيط'],
            ['project_status', 'قيد التنفيذ', 'المشروع قيد التنفيذ'],
            ['project_status', 'مكتمل', 'المشروع مكتمل'],
            ['project_status', 'معلق', 'المشروع معلق مؤقتاً'],
            ['project_status', 'ملغى', 'المشروع ملغى']
        ];
        
        $stmt = $db->prepare("INSERT INTO reference_data (type, value, description, is_active) VALUES (?, ?, ?, 1)");
        $count = 0;
        foreach ($reference_data as $data) {
            try {
                $stmt->execute($data);
                $count++;
            } catch (PDOException $e) {
                // تخطي إذا كان السجل موجود
            }
        }
        
        echo "<div class='bg-green-50 border border-green-200 rounded-lg p-4'>
                <span class='text-green-800'>✅ تم إضافة {$count} سجل مرجعي</span>
              </div>";
    } else {
        echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4'>
                <span class='text-blue-800'>ℹ️ البيانات المرجعية موجودة بالفعل ({$check['count']} سجل)</span>
              </div>";
        
        // عرض إحصائيات البيانات المرجعية
        $types = $db->query("SELECT type, COUNT(*) as count FROM reference_data GROUP BY type ORDER BY type")->fetchAll();
        echo "<div class='mt-2 bg-white border border-gray-200 rounded p-3'>
                <ul class='space-y-1 text-sm'>";
        foreach ($types as $t) {
            echo "<li>• {$t['type']}: {$t['count']} سجل</li>";
        }
        echo "</ul></div>";
    }
} catch (PDOException $e) {
    echo "<div class='bg-red-50 border border-red-200 rounded-lg p-4'>
            <span class='text-red-800'>❌ خطأ: {$e->getMessage()}</span>
          </div>";
}

echo "</div>
        
        <div class='mt-8 text-center'>
            <a href='all_tables_manager.php' class='bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg inline-block transition duration-200'>
                ✅ الذهاب لصفحة إدارة الجداول المرجعية
            </a>
            <a href='comprehensive_dashboard.php' class='bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-lg inline-block transition duration-200 mr-4'>
                🏠 العودة للوحة التحكم
            </a>
        </div>
        
        <div class='mt-6 text-center text-sm text-gray-500'>
            <p>🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
            <p class='mt-1'>نظام إدارة البلدية الإلكتروني</p>
        </div>
        
        </div>
    </div>
</body>
</html>";
?>

