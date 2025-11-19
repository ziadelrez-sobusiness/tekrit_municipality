<?php
/**
 * اختبار نهائي شامل لحماية CSRF
 * يتحقق من أن جميع الملفات محمية بشكل صحيح
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>اختبار نهائي - CSRF Protection</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
        h2 { color: #059669; margin-top: 30px; }
        .success { color: #059669; background: #d1fae5; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .warning { color: #d97706; background: #fef3c7; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .info { color: #2563eb; background: #dbeafe; padding: 10px; border-radius: 5px; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: right; border: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: bold; }
        .stat { display: inline-block; padding: 10px 20px; margin: 10px; border-radius: 5px; font-weight: bold; }
        .stat-success { background: #d1fae5; color: #059669; }
        .stat-error { background: #fee2e2; color: #dc2626; }
        .stat-warning { background: #fef3c7; color: #d97706; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔒 اختبار نهائي شامل - CSRF Protection</h1>
    <p style='color: #6b7280;'>تاريخ الاختبار: " . date('Y-m-d H:i:s') . "</p>";

$results = [
    'total_files' => 0,
    'protected_files' => 0,
    'unprotected_files' => 0,
    'total_forms' => 0,
    'protected_forms' => 0,
    'unprotected_forms' => 0,
    'total_post_handlers' => 0,
    'protected_handlers' => 0,
    'unprotected_handlers' => 0,
    'errors' => [],
    'warnings' => [],
    'files_details' => []
];

// قائمة الملفات للتحقق
$files_to_check = [
    'modules/invoices.php',
    'modules/budgets.php',
    'modules/committee_dashboard.php',
    'modules/departments.php',
    'modules/suppliers.php',
    'modules/municipality_management.php',
    'modules/contributions.php',
    'modules/donations.php',
    'modules/complaints.php',
    'modules/building_permit.php',
    'modules/projects.php',
    'modules/public_content_management.php',
    'modules/citizens.php',
    'modules/waste.php',
    'modules/vehicles.php',
    'modules/tax_types.php',
    'modules/system_settings.php',
    'modules/update_citizen_request.php',
    'modules/hr.php',
    'modules/facilities_management.php',
    'modules/finance.php',
    'modules/tax_collection.php',
    'public/citizen-requests.php',
    'public/contact.php'
];

// التحقق من وجود csrf_middleware.php
echo "<h2>1. التحقق من ملفات الأمان الأساسية</h2>";
$csrf_middleware_exists = file_exists(__DIR__ . '/includes/csrf_middleware.php');
if ($csrf_middleware_exists) {
    echo "<div class='success'>✅ ملف csrf_middleware.php موجود</div>";
} else {
    echo "<div class='error'>❌ ملف csrf_middleware.php غير موجود!</div>";
    $results['errors'][] = 'ملف csrf_middleware.php غير موجود';
}

// التحقق من وجود الدوال المطلوبة
if ($csrf_middleware_exists) {
    require_once __DIR__ . '/includes/csrf_middleware.php';
    $functions_exist = [
        'csrf_protect' => function_exists('csrf_protect'),
        'csrf_input' => function_exists('csrf_input'),
        'csrf_token' => function_exists('csrf_token')
    ];
    
    foreach ($functions_exist as $func => $exists) {
        if ($exists) {
            echo "<div class='success'>✅ الدالة $func() موجودة</div>";
        } else {
            echo "<div class='error'>❌ الدالة $func() غير موجودة!</div>";
            $results['errors'][] = "الدالة $func() غير موجودة";
        }
    }
}

// فحص كل ملف
echo "<h2>2. فحص الملفات</h2>";
echo "<table>";
echo "<tr><th>الملف</th><th>حماية CSRF</th><th>عدد النماذج</th><th>عدد معالجات POST</th><th>الحالة</th></tr>";

foreach ($files_to_check as $file) {
    $file_path = __DIR__ . '/' . $file;
    
    if (!file_exists($file_path)) {
        echo "<tr><td>$file</td><td colspan='4' class='error'>❌ الملف غير موجود</td></tr>";
        $results['errors'][] = "الملف $file غير موجود";
        continue;
    }
    
    $results['total_files']++;
    $content = file_get_contents($file_path);
    
    $file_details = [
        'file' => $file,
        'has_csrf_middleware' => false,
        'has_csrf_protect' => false,
        'forms_count' => 0,
        'protected_forms' => 0,
        'post_handlers_count' => 0,
        'protected_handlers' => 0,
        'status' => 'unprotected'
    ];
    
    // التحقق من تحميل csrf_middleware
    $has_csrf_middleware = strpos($content, 'csrf_middleware.php') !== false;
    $file_details['has_csrf_middleware'] = $has_csrf_middleware;
    
    // عد معالجات POST
    preg_match_all('/if\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*===\s*[\'"]POST[\'"]|if\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*==\s*[\'"]POST[\'"]/i', $content, $post_matches);
    $post_handlers = count($post_matches[0]);
    $file_details['post_handlers_count'] = $post_handlers;
    $results['total_post_handlers'] += $post_handlers;
    
    // عد معالجات POST المحمية
    preg_match_all('/if\s*\(\s*!\s*csrf_protect\s*\(/i', $content, $protected_matches);
    $protected_handlers = count($protected_matches[0]);
    $file_details['protected_handlers'] = $protected_handlers;
    $results['protected_handlers'] += $protected_handlers;
    
    // عد النماذج
    preg_match_all('/<form\s+[^>]*method\s*=\s*[\'"]POST[\'"]/i', $content, $form_matches);
    $forms_count = count($form_matches[0]);
    $file_details['forms_count'] = $forms_count;
    $results['total_forms'] += $forms_count;
    
    // عد النماذج المحمية
    preg_match_all('/csrf_input\s*\(\s*[\'"]csrf_token[\'"]\s*\)/i', $content, $csrf_input_matches);
    $protected_forms = count($csrf_input_matches[0]);
    $file_details['protected_forms'] = $protected_forms;
    $results['protected_forms'] += $protected_forms;
    
    // تحديد الحالة
    $is_protected = $has_csrf_middleware && 
                    ($post_handlers == 0 || $protected_handlers == $post_handlers) &&
                    ($forms_count == 0 || $protected_forms == $forms_count);
    
    if ($is_protected) {
        $file_details['status'] = 'protected';
        $results['protected_files']++;
        $status_icon = '✅';
        $status_class = 'success';
    } else {
        $file_details['status'] = 'unprotected';
        $results['unprotected_files']++;
        $status_icon = '⚠️';
        $status_class = 'warning';
        
        if (!$has_csrf_middleware) {
            $results['warnings'][] = "$file: لا يحتوي على تحميل csrf_middleware.php";
        }
        if ($post_handlers > 0 && $protected_handlers < $post_handlers) {
            $results['warnings'][] = "$file: بعض معالجات POST غير محمية (" . ($post_handlers - $protected_handlers) . " من $post_handlers)";
        }
        if ($forms_count > 0 && $protected_forms < $forms_count) {
            $results['warnings'][] = "$file: بعض النماذج غير محمية (" . ($forms_count - $protected_forms) . " من $forms_count)";
        }
    }
    
    $results['files_details'][] = $file_details;
    
    $status_text = $is_protected ? '✅ محمي' : '⚠️ يحتاج مراجعة';
    echo "<tr>";
    echo "<td><strong>$file</strong></td>";
    echo "<td>" . ($has_csrf_middleware ? '✅' : '❌') . "</td>";
    echo "<td>$forms_count (" . ($protected_forms == $forms_count ? '✅' : '⚠️') . " $protected_forms)</td>";
    echo "<td>$post_handlers (" . ($protected_handlers == $post_handlers ? '✅' : '⚠️') . " $protected_handlers)</td>";
    echo "<td class='$status_class'>$status_text</td>";
    echo "</tr>";
}

echo "</table>";

// الإحصائيات النهائية
echo "<h2>3. الإحصائيات النهائية</h2>";
echo "<div style='display: flex; flex-wrap: wrap;'>";
echo "<div class='stat stat-success'>إجمالي الملفات: " . $results['total_files'] . "</div>";
echo "<div class='stat stat-success'>الملفات المحمية: " . $results['protected_files'] . "</div>";
echo "<div class='stat " . ($results['unprotected_files'] > 0 ? 'stat-warning' : 'stat-success') . "'>الملفات غير المحمية: " . $results['unprotected_files'] . "</div>";
echo "<div class='stat stat-success'>إجمالي النماذج: " . $results['total_forms'] . "</div>";
echo "<div class='stat stat-success'>النماذج المحمية: " . $results['protected_forms'] . "</div>";
echo "<div class='stat " . (($results['total_forms'] - $results['protected_forms']) > 0 ? 'stat-warning' : 'stat-success') . "'>النماذج غير المحمية: " . ($results['total_forms'] - $results['protected_forms']) . "</div>";
echo "<div class='stat stat-success'>إجمالي معالجات POST: " . $results['total_post_handlers'] . "</div>";
echo "<div class='stat stat-success'>معالجات POST المحمية: " . $results['protected_handlers'] . "</div>";
echo "<div class='stat " . (($results['total_post_handlers'] - $results['protected_handlers']) > 0 ? 'stat-warning' : 'stat-success') . "'>معالجات POST غير المحمية: " . ($results['total_post_handlers'] - $results['protected_handlers']) . "</div>";
echo "</div>";

// النسبة المئوية
$protection_percentage = $results['total_files'] > 0 ? round(($results['protected_files'] / $results['total_files']) * 100, 2) : 0;
$forms_protection_percentage = $results['total_forms'] > 0 ? round(($results['protected_forms'] / $results['total_forms']) * 100, 2) : 0;
$handlers_protection_percentage = $results['total_post_handlers'] > 0 ? round(($results['protected_handlers'] / $results['total_post_handlers']) * 100, 2) : 0;

echo "<div style='margin-top: 20px;'>";
echo "<div class='info'><strong>نسبة الحماية:</strong></div>";
echo "<div class='info'>الملفات: $protection_percentage%</div>";
echo "<div class='info'>النماذج: $forms_protection_percentage%</div>";
echo "<div class='info'>معالجات POST: $handlers_protection_percentage%</div>";
echo "</div>";

// التحذيرات والأخطاء
if (count($results['warnings']) > 0) {
    echo "<h2>4. التحذيرات</h2>";
    foreach ($results['warnings'] as $warning) {
        echo "<div class='warning'>⚠️ $warning</div>";
    }
}

if (count($results['errors']) > 0) {
    echo "<h2>5. الأخطاء</h2>";
    foreach ($results['errors'] as $error) {
        echo "<div class='error'>❌ $error</div>";
    }
}

// التقييم النهائي
echo "<h2>6. التقييم النهائي</h2>";
$all_protected = $results['unprotected_files'] == 0 && 
                 ($results['total_forms'] == 0 || $results['protected_forms'] == $results['total_forms']) &&
                 ($results['total_post_handlers'] == 0 || $results['protected_handlers'] == $results['total_post_handlers']) &&
                 count($results['errors']) == 0;

if ($all_protected) {
    echo "<div class='success' style='font-size: 18px; padding: 20px;'>";
    echo "🎉 <strong>ممتاز!</strong> جميع الملفات محمية بشكل صحيح بـ CSRF Protection!";
    echo "</div>";
} else {
    echo "<div class='warning' style='font-size: 18px; padding: 20px;'>";
    echo "⚠️ <strong>يوجد بعض المشاكل:</strong>";
    echo "<ul>";
    if ($results['unprotected_files'] > 0) {
        echo "<li>يوجد " . $results['unprotected_files'] . " ملف غير محمي</li>";
    }
    if (($results['total_forms'] - $results['protected_forms']) > 0) {
        echo "<li>يوجد " . ($results['total_forms'] - $results['protected_forms']) . " نموذج غير محمي</li>";
    }
    if (($results['total_post_handlers'] - $results['protected_handlers']) > 0) {
        echo "<li>يوجد " . ($results['total_post_handlers'] - $results['protected_handlers']) . " معالج POST غير محمي</li>";
    }
    if (count($results['errors']) > 0) {
        echo "<li>يوجد " . count($results['errors']) . " خطأ</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "</div></body></html>";
?>



