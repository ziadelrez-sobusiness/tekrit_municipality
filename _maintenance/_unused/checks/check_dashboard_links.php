<?php
/**
 * سكريبت فحص روابط لوحة التحكم
 * بلدية تكريت - عكار، شمال لبنان
 */

// قائمة جميع الروابط المذكورة في comprehensive_dashboard.php
$dashboard_links = [
    // الإدارة الأساسية
    'إدارة البلدية' => 'modules/municipality_management.php',
    'إدارة أعضاء المجلس البلدي' => 'modules/council_management.php',
    'إدارة الموقع العام' => 'modules/public_content_management.php',
    'إدارة صفحة اتصل بنا' => 'modules/contact_management.php',
    
    // الموارد البشرية والمالية
    'الموارد البشرية' => 'modules/hr.php',
    'الإدارة المالية' => 'modules/finance.php',
    'إدارة الجباية' => 'modules/tax_collection.php',
    
    // المخزون والآليات
    'إدارة الآليات' => 'modules/vehicles.php',
    
    // المشاريع والخدمات
    'إدارة المشاريع' => 'modules/projects.php',
    'إدارة النفايات' => 'modules/waste.php',
    'إدارة الشكاوى' => 'modules/complaints.php',
    
    // الرخص والتبرعات
    'رخص البناء والنماذج' => 'modules/building_permit.php',
    'إدارة التبرعات' => 'modules/donations.php',
    'إدارة المواطنين' => 'modules/citizens.php',
    
    // الأرشيف والإعدادات
    'الأرشيف الإلكتروني' => 'modules/archive.php',
    'إعدادات النظام' => 'modules/system_settings.php',
    'إدارة الصلاحيات' => 'modules/permissions.php',
    
    // الجداول المرجعية
    'الجداول المرجعية' => 'all_tables_manager.php',
    
    // الإعدادات الإضافية
    'إدارة العملات' => 'modules/currencies.php',
    'أنواع الضرائب' => 'modules/tax_types.php',
    'إدارة السائقين' => 'modules/drivers_section.php',
    'المنظمات المانحة' => 'modules/donor_organizations.php',
    
    // الخرائط والمرافق
    'خريطة المرافق والخدمات' => 'modules/facilities_management.php',
    'إدارة فئات المرافق' => 'modules/facilities_categories.php',
    'إعدادات الخريطة' => 'modules/map_settings.php',
    
    // صفحات الموظفين
    'تعديل موظف' => 'modules/edit_employee.php',
    'عرض موظف' => 'modules/get_employee.php',
    'حذف موظف' => 'modules/delete_employee.php',
    
    // صفحات الأقسام
    'إدارة الأقسام' => 'modules/departments.php',
];

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>فحص روابط لوحة التحكم - بلدية تكريت</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .status-ok { background-color: #d1fae5; color: #065f46; }
        .status-missing { background-color: #fee2e2; color: #991b1b; }
        .status-warning { background-color: #fef3c7; color: #92400e; }
    </style>
</head>
<body class='bg-gray-50 p-6'>
    <div class='max-w-7xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <div class='text-center mb-8'>
                <h1 class='text-3xl font-bold text-gray-800 mb-2'>🔍 تقرير فحص روابط لوحة التحكم</h1>
                <p class='text-gray-600'>بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
                <p class='text-sm text-gray-500 mt-2'>تاريخ الفحص: " . date('Y-m-d H:i:s') . "</p>
            </div>";

$total = count($dashboard_links);
$found = 0;
$missing = 0;
$found_files = [];
$missing_files = [];

echo "<div class='mb-6'>
        <h2 class='text-2xl font-bold text-gray-800 mb-4'>📊 ملخص الفحص</h2>
      </div>";

echo "<div class='overflow-x-auto'>
        <table class='w-full border-collapse'>
            <thead>
                <tr class='bg-indigo-600 text-white'>
                    <th class='p-3 text-right border'>#</th>
                    <th class='p-3 text-right border'>اسم الصفحة</th>
                    <th class='p-3 text-right border'>المسار</th>
                    <th class='p-3 text-center border'>الحالة</th>
                    <th class='p-3 text-center border'>حجم الملف</th>
                </tr>
            </thead>
            <tbody>";

$counter = 1;
foreach ($dashboard_links as $name => $path) {
    $full_path = __DIR__ . '/' . $path;
    $exists = file_exists($full_path);
    $size = $exists ? filesize($full_path) : 0;
    $size_formatted = $exists ? number_format($size / 1024, 2) . ' KB' : '-';
    
    if ($exists) {
        $found++;
        $found_files[] = ['name' => $name, 'path' => $path, 'size' => $size];
        $status_class = 'status-ok';
        $status_icon = '✅';
        $status_text = 'موجود';
    } else {
        $missing++;
        $missing_files[] = ['name' => $name, 'path' => $path];
        $status_class = 'status-missing';
        $status_icon = '❌';
        $status_text = 'مفقود';
    }
    
    echo "<tr class='border-b hover:bg-gray-50'>
            <td class='p-3 border text-center'>{$counter}</td>
            <td class='p-3 border font-semibold'>{$name}</td>
            <td class='p-3 border'><code class='text-sm bg-gray-100 px-2 py-1 rounded'>{$path}</code></td>
            <td class='p-3 border text-center'>
                <span class='px-3 py-1 rounded-full text-sm font-semibold {$status_class}'>
                    {$status_icon} {$status_text}
                </span>
            </td>
            <td class='p-3 border text-center'>{$size_formatted}</td>
          </tr>";
    
    $counter++;
}

echo "</tbody>
      </table>
      </div>";

// إحصائيات
$percentage_found = round(($found / $total) * 100, 2);
$percentage_missing = round(($missing / $total) * 100, 2);

echo "<div class='grid grid-cols-1 md:grid-cols-3 gap-6 mt-8'>
        <div class='bg-blue-50 border border-blue-200 rounded-lg p-6'>
            <div class='text-center'>
                <div class='text-4xl font-bold text-blue-600'>{$total}</div>
                <div class='text-sm text-blue-800 mt-2'>إجمالي الصفحات</div>
            </div>
        </div>
        
        <div class='bg-green-50 border border-green-200 rounded-lg p-6'>
            <div class='text-center'>
                <div class='text-4xl font-bold text-green-600'>{$found}</div>
                <div class='text-sm text-green-800 mt-2'>صفحات موجودة ({$percentage_found}%)</div>
            </div>
        </div>
        
        <div class='bg-red-50 border border-red-200 rounded-lg p-6'>
            <div class='text-center'>
                <div class='text-4xl font-bold text-red-600'>{$missing}</div>
                <div class='text-sm text-red-800 mt-2'>صفحات مفقودة ({$percentage_missing}%)</div>
            </div>
        </div>
      </div>";

// قائمة الصفحات المفقودة
if (!empty($missing_files)) {
    echo "<div class='mt-8 bg-red-50 border border-red-200 rounded-lg p-6'>
            <h3 class='text-xl font-bold text-red-800 mb-4'>⚠️ الصفحات المفقودة التي تحتاج للإنشاء:</h3>
            <ul class='space-y-2'>";
    
    foreach ($missing_files as $file) {
        echo "<li class='flex items-start'>
                <span class='text-red-600 mr-2'>❌</span>
                <div>
                    <strong>{$file['name']}</strong>
                    <br>
                    <code class='text-sm bg-red-100 px-2 py-1 rounded'>{$file['path']}</code>
                </div>
              </li>";
    }
    
    echo "</ul>
          </div>";
}

// قائمة الصفحات الموجودة
if (!empty($found_files)) {
    echo "<div class='mt-8 bg-green-50 border border-green-200 rounded-lg p-6'>
            <h3 class='text-xl font-bold text-green-800 mb-4'>✅ الصفحات الموجودة والعاملة:</h3>
            <div class='grid grid-cols-1 md:grid-cols-2 gap-4'>";
    
    foreach ($found_files as $file) {
        echo "<div class='bg-white p-3 rounded border border-green-200'>
                <div class='flex justify-between items-start'>
                    <div>
                        <strong class='text-green-800'>{$file['name']}</strong>
                        <br>
                        <code class='text-xs bg-gray-100 px-2 py-1 rounded'>{$file['path']}</code>
                    </div>
                    <span class='text-xs text-gray-500'>" . number_format($file['size'] / 1024, 1) . " KB</span>
                </div>
              </div>";
    }
    
    echo "</div>
          </div>";
}

// توصيات
echo "<div class='mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6'>
        <h3 class='text-xl font-bold text-blue-800 mb-4'>💡 التوصيات:</h3>
        <ul class='space-y-2 text-blue-900'>";

if ($missing > 0) {
    echo "<li class='flex items-start'>
            <span class='mr-2'>🔧</span>
            <span>يجب إنشاء <strong>{$missing}</strong> صفحة مفقودة لإكمال النظام</span>
          </li>";
}

if ($percentage_found >= 80) {
    echo "<li class='flex items-start'>
            <span class='mr-2'>✨</span>
            <span>النظام في حالة جيدة! <strong>{$percentage_found}%</strong> من الصفحات موجودة</span>
          </li>";
} elseif ($percentage_found >= 50) {
    echo "<li class='flex items-start'>
            <span class='mr-2'>⚠️</span>
            <span>النظام يحتاج لبعض التحسينات. <strong>{$percentage_missing}%</strong> من الصفحات مفقودة</span>
          </li>";
} else {
    echo "<li class='flex items-start'>
            <span class='mr-2'>🚨</span>
            <span>النظام يحتاج لعمل كبير. أكثر من نصف الصفحات مفقودة</span>
          </li>";
}

echo "<li class='flex items-start'>
        <span class='mr-2'>📝</span>
        <span>يُنصح بإنشاء الصفحات المفقودة حسب الأولوية (الصفحات الأساسية أولاً)</span>
      </li>
      <li class='flex items-start'>
        <span class='mr-2'>🔒</span>
        <span>تأكد من إضافة نظام الحماية والصلاحيات لجميع الصفحات</span>
      </li>
      </ul>
      </div>";

echo "<div class='mt-8 text-center'>
        <a href='comprehensive_dashboard.php' class='bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg inline-block transition duration-200'>
            🏠 العودة للوحة التحكم
        </a>
        <button onclick='window.print()' class='bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-6 rounded-lg inline-block transition duration-200 mr-4'>
            🖨️ طباعة التقرير
        </button>
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


