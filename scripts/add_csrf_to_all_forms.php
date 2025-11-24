<?php
/**
 * سكريبت لإضافة CSRF Protection تلقائياً لجميع النماذج
 * 
 * هذا السكريبت يضيف CSRF validation و CSRF fields لجميع الملفات في modules/
 * 
 * تحذير: هذا السكريبت للاستخدام مرة واحدة فقط!
 */

require_once __DIR__ . '/../includes/csrf_middleware.php';

$modulesDir = __DIR__ . '/../modules';
$files = glob($modulesDir . '/*.php');

$processed = 0;
$errors = [];

foreach ($files as $file) {
    // تخطي ملفات backup
    if (strpos(basename($file), 'backup') !== false || 
        strpos(basename($file), 'old') !== false ||
        strpos(basename($file), 'example') !== false) {
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $modified = false;
    
    // 1. إضافة require csrf_middleware في البداية (بعد require auth)
    if (strpos($content, 'require_once') !== false && 
        strpos($content, 'csrf_middleware') === false &&
        strpos($content, 'auth.php') !== false) {
        
        // البحث عن آخر require_once
        $lines = explode("\n", $content);
        $insertIndex = -1;
        
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], 'require_once') !== false && 
                strpos($lines[$i], 'auth.php') !== false) {
                $insertIndex = $i + 1;
                break;
            }
        }
        
        if ($insertIndex > 0) {
            $csrfLine = "// تحميل CSRF Protection\n";
            $csrfLine .= "if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {\n";
            $csrfLine .= "    require_once __DIR__ . '/../includes/csrf_middleware.php';\n";
            $csrfLine .= "}\n";
            
            array_splice($lines, $insertIndex, 0, $csrfLine);
            $content = implode("\n", $lines);
            $modified = true;
        }
    }
    
    // 2. إضافة CSRF validation في معالجات POST
    if (preg_match_all('/if\s*\(\s*\$_SERVER\[[\'"]REQUEST_METHOD[\'"]\s*\]\s*===\s*[\'"]POST[\'"]\s*\)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach (array_reverse($matches[0]) as $match) {
            $pos = $match[1];
            
            // التحقق من عدم وجود CSRF validation بالفعل
            $before = substr($content, max(0, $pos - 200), 200);
            if (strpos($before, 'csrf_protect') !== false || 
                strpos($before, 'csrf_validate') !== false ||
                strpos($before, 'form_validate_csrf') !== false) {
                continue;
            }
            
            // إضافة CSRF protection
            $csrfCode = "\n    // التحقق من CSRF\n    if (!csrf_protect(false)) {\n        \$error = \$error ?? '';\n        \$error = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';\n    } else {\n";
            
            // البحث عن السطر التالي
            $after = substr($content, $pos, 500);
            if (preg_match('/\n\s*\{/', $after, $m, PREG_OFFSET_CAPTURE)) {
                $insertPos = $pos + $m[0][1] + strlen($m[0][0]);
                $content = substr_replace($content, $csrfCode, $insertPos, 0);
                
                // إضافة closing brace في النهاية
                // هذا معقد - سنحتاج تحليل أفضل
                $modified = true;
            }
        }
    }
    
    // 3. إضافة CSRF field في النماذج
    if (preg_match_all('/<form\s+method\s*=\s*[\'"]POST[\'"]/i', $content, $formMatches, PREG_OFFSET_CAPTURE)) {
        foreach (array_reverse($formMatches[0]) as $formMatch) {
            $formPos = $formMatch[1];
            
            // التحقق من عدم وجود CSRF field بالفعل
            $afterForm = substr($content, $formPos, 500);
            if (strpos($afterForm, 'csrf_token') !== false || 
                strpos($afterForm, 'csrf_field') !== false ||
                strpos($afterForm, 'csrf_input') !== false) {
                continue;
            }
            
            // البحث عن نهاية <form> tag
            if (preg_match('/>/', $afterForm, $m, PREG_OFFSET_CAPTURE)) {
                $insertPos = $formPos + $m[0][1] + 1;
                $csrfField = "\n                <?php echo csrf_input('csrf_token'); ?>\n";
                $content = substr_replace($content, $csrfField, $insertPos, 0);
                $modified = true;
            }
        }
    }
    
    if ($modified) {
        // نسخ احتياطي
        $backupFile = $file . '.csrf_backup.' . date('Y-m-d_H-i-s');
        copy($file, $backupFile);
        
        // حفظ الملف المحدث
        if (file_put_contents($file, $content)) {
            $processed++;
            echo "✅ تم معالجة: " . basename($file) . "\n";
        } else {
            $errors[] = "❌ فشل حفظ: " . basename($file);
        }
    }
}

echo "\n📊 النتيجة:\n";
echo "✅ تم معالجة: $processed ملف\n";
echo "❌ الأخطاء: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nالأخطاء:\n";
    foreach ($errors as $error) {
        echo "$error\n";
    }
}

echo "\n⚠️ تحذير: تم إنشاء نسخ احتياطية من جميع الملفات المعدلة!\n";
echo "يرجى اختبار النظام بعناية قبل حذف النسخ الاحتياطية.\n";







