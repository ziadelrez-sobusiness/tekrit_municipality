<?php
/**
 * تطبيق النظام المتقدم لطلبات المواطنين
 * دعم السيناريوهات المطلوبة مع النماذج الديناميكية ونظام المراجعة
 */

require_once 'config/database.php';
require_once 'includes/AdvancedRequestSystem.php';

try {
    $database = new Database();
    $advancedSystem = new AdvancedRequestSystem($database);
    
    echo "<!DOCTYPE html>";
    echo "<html lang='ar' dir='rtl'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>تطبيق النظام المتقدم - بلدية تكريت</title>";
    echo "<style>";
    echo "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f5f5f5; }";
    echo ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
    echo ".success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }";
    echo ".error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }";
    echo ".info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }";
    echo ".warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }";
    echo "h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }";
    echo "h2 { color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 10px; }";
    echo "h3 { color: #2980b9; margin-top: 25px; }";
    echo ".feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }";
    echo ".feature-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #3498db; }";
    echo ".stats { display: flex; justify-content: space-around; margin: 20px 0; }";
    echo ".stat-item { text-align: center; padding: 15px; background: #ecf0f1; border-radius: 8px; }";
    echo ".stat-number { font-size: 2em; font-weight: bold; color: #2980b9; }";
    echo ".emoji { font-size: 1.5em; margin-left: 10px; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    echo "<div class='container'>";
    
    echo "<h1>🚀 تطبيق النظام المتقدم لطلبات المواطنين</h1>";
    
    echo "<div class='info'>";
    echo "<h3>📋 الهدف من التحديث:</h3>";
    echo "<p>تطوير نظام متقدم يدعم السيناريوهات المطلوبة:</p>";
    echo "<ul>";
    echo "<li><strong>السيناريو 1:</strong> مواطن يقدم طلب ترخيص بناء مع نموذج ديناميكي كامل</li>";
    echo "<li><strong>السيناريو 2:</strong> موظف بلدية يراجع ويعالج الطلب عبر مراحل العمل</li>";
    echo "<li><strong>السيناريو 3:</strong> مواطن يتتبع طلبه مع خط زمني تفصيلي</li>";
    echo "</ul>";
    echo "</div>";
    
    // تطبيق النظام المتقدم
    echo "<h2>⚙️ بدء تطبيق النظام المتقدم...</h2>";
    
    $result = $advancedSystem->setupAdvancedRequestTypes();
    
    if ($result['success']) {
        echo "<div class='success'>";
        echo "<h3>✅ تم تطبيق النظام المتقدم بنجاح!</h3>";
        echo "<p>" . $result['message'] . "</p>";
        echo "</div>";
        
        // عرض الميزات المضافة
        echo "<h2>🎯 الميزات الجديدة المضافة</h2>";
        echo "<div class='feature-list'>";
        
        echo "<div class='feature-card'>";
        echo "<h3>🏗️ طلب ترخيص البناء</h3>";
        echo "<ul>";
        echo "<li>نموذج ديناميكي مع 18 حقل متخصص</li>";
        echo "<li>معلومات مالك الأرض والعقار</li>";
        echo "<li>تفاصيل البناء والمقاول والمهندس</li>";
        echo "<li>المعلومات المالية والزمنية</li>";
        echo "<li>9 مستندات مطلوبة</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='feature-card'>";
        echo "<h3>🏪 طلب ترخيص تجاري</h3>";
        echo "<ul>";
        echo "<li>نموذج ديناميكي للأنشطة التجارية</li>";
        echo "<li>معلومات المحل والنشاط</li>";
        echo "<li>عدد الموظفين وساعات العمل</li>";
        echo "<li>6 مستندات مطلوبة</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='feature-card'>";
        echo "<h3>📋 نظام مراحل العمل</h3>";
        echo "<ul>";
        echo "<li>6 مراحل لمعالجة طلب ترخيص البناء</li>";
        echo "<li>تتبع تقدم الطلب عبر المراحل</li>";
        echo "<li>تحديد الموظف المسؤول عن كل مرحلة</li>";
        echo "<li>مدة زمنية محددة لكل مرحلة</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='feature-card'>";
        echo "<h3>🔔 نظام الإشعارات</h3>";
        echo "<ul>";
        echo "<li>إشعارات تلقائية للمواطنين</li>";
        echo "<li>5 قوالب جاهزة للرسائل</li>";
        echo "<li>دعم SMS, Email, النظام</li>";
        echo "<li>متغيرات ديناميكية في الرسائل</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='feature-card'>";
        echo "<h3>📊 تتبع متقدم</h3>";
        echo "<ul>";
        echo "<li>خط زمني تفصيلي للطلب</li>";
        echo "<li>عرض المرحلة الحالية والتقدم</li>";
        echo "<li>ملاحظات وتعليقات الموظفين</li>";
        echo "<li>إحصائيات شاملة</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='feature-card'>";
        echo "<h3>🔧 تحسينات تقنية</h3>";
        echo "<ul>";
        echo "<li>قاعدة بيانات محسنة</li>";
        echo "<li>فهارس للأداء السريع</li>";
        echo "<li>Views شاملة للتقارير</li>";
        echo "<li>معالجة أخطاء متقدمة</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "</div>";
        
        // اختبار النظام
        echo "<h2>🧪 اختبار النظام</h2>";
        
        $database = new Database();
        $db = $database->getConnection();
        
        // إحصائيات النظام
        $stats = [];
        
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM request_types WHERE is_active = 1");
            $stats['types'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM request_workflow_stages");
            $stats['stages'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM notification_templates WHERE is_active = 1");
            $stats['templates'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM citizen_requests");
            $stats['requests'] = $stmt->fetchColumn();
            
            echo "<div class='stats'>";
            echo "<div class='stat-item'>";
            echo "<div class='stat-number'>{$stats['types']}</div>";
            echo "<div>أنواع الطلبات النشطة</div>";
            echo "</div>";
            
            echo "<div class='stat-item'>";
            echo "<div class='stat-number'>{$stats['stages']}</div>";
            echo "<div>مراحل العمل</div>";
            echo "</div>";
            
            echo "<div class='stat-item'>";
            echo "<div class='stat-number'>{$stats['templates']}</div>";
            echo "<div>قوالب الإشعارات</div>";
            echo "</div>";
            
            echo "<div class='stat-item'>";
            echo "<div class='stat-number'>{$stats['requests']}</div>";
            echo "<div>إجمالي الطلبات</div>";
            echo "</div>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='warning'>تعذر جلب الإحصائيات: " . $e->getMessage() . "</div>";
        }
        
        // عرض أنواع الطلبات الجديدة
        echo "<h3>📋 أنواع الطلبات المتقدمة</h3>";
        
        try {
            $stmt = $db->query("
                SELECT type_name, name_ar, type_description, 
                       JSON_LENGTH(form_fields) as fields_count,
                       JSON_LENGTH(required_documents) as documents_count
                FROM request_types 
                WHERE type_name IN ('طلب ترخيص بالبناء', 'طلب ترخيص تجاري')
                ORDER BY display_order
            ");
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($types)) {
                echo "<div class='success'>";
                echo "<table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>";
                echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>نوع الطلب</td>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>الوصف</td>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>عدد الحقول</td>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>عدد المستندات</td>";
                echo "</tr>";
                
                foreach ($types as $type) {
                    echo "<tr>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd;'><strong>{$type['name_ar']}</strong></td>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd;'>{$type['type_description']}</td>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd; text-align: center;'>{$type['fields_count']}</td>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd; text-align: center;'>{$type['documents_count']}</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='warning'>تعذر جلب أنواع الطلبات: " . $e->getMessage() . "</div>";
        }
        
        // عرض مراحل العمل
        echo "<h3>⚙️ مراحل معالجة طلب ترخيص البناء</h3>";
        
        try {
            $stmt = $db->query("
                SELECT rws.stage_name, rws.stage_description, rws.stage_order, 
                       rws.required_role, rws.max_duration_days
                FROM request_workflow_stages rws
                JOIN request_types rt ON rws.request_type_id = rt.id
                WHERE rt.type_name = 'طلب ترخيص بالبناء'
                ORDER BY rws.stage_order
            ");
            $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($stages)) {
                echo "<div class='success'>";
                echo "<table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>";
                echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>الترتيب</td>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>اسم المرحلة</td>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>الوصف</td>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>الدور المطلوب</td>";
                echo "<td style='padding: 12px; border: 1px solid #ddd;'>المدة القصوى</td>";
                echo "</tr>";
                
                foreach ($stages as $stage) {
                    echo "<tr>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd; text-align: center;'>{$stage['stage_order']}</td>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd;'><strong>{$stage['stage_name']}</strong></td>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd;'>{$stage['stage_description']}</td>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd;'>{$stage['required_role']}</td>";
                    echo "<td style='padding: 12px; border: 1px solid #ddd; text-align: center;'>{$stage['max_duration_days']} يوم</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='warning'>تعذر جلب مراحل العمل: " . $e->getMessage() . "</div>";
        }
        
        // الخطوات التالية
        echo "<div class='warning'>";
        echo "<h3>🚀 الخطوات التالية المطلوبة:</h3>";
        echo "<ol>";
        echo "<li><strong>تحديث واجهة طلبات المواطنين:</strong> إضافة دعم النماذج الديناميكية</li>";
        echo "<li><strong>لوحة تحكم الموظفين:</strong> إنشاء واجهة إدارة مراحل العمل</li>";
        echo "<li><strong>نظام الإشعارات:</strong> تفعيل إرسال SMS و Email</li>";
        echo "<li><strong>تقارير متقدمة:</strong> إحصائيات وتقارير شاملة</li>";
        echo "<li><strong>اختبار السيناريوهات:</strong> اختبار جميع السيناريوهات المطلوبة</li>";
        echo "</ol>";
        echo "</div>";
        
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ فشل في تطبيق النظام المتقدم</h3>";
        echo "<p><strong>الخطأ:</strong> " . $result['error'] . "</p>";
        echo "</div>";
    }
    
    echo "</div>";
    echo "</body>";
    echo "</html>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ خطأ في تطبيق النظام:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><strong>الملف:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>السطر:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?> 