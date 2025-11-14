<?php
require_once 'config/database.php';

echo "<h1>🧪 اختبار نموذج إضافة المبادرة الشاملة</h1>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<h2>✅ تم استلام البيانات التالية:</h2>";
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
    print_r($_POST);
    echo "</pre>";
    
    $name = trim($_POST['initiative_name']);
    $description = trim($_POST['initiative_description']);
    $type = $_POST['initiative_type'];
    
    if (!empty($name) && !empty($description)) {
        try {
            $stmt = $db->prepare("INSERT INTO youth_environmental_initiatives (
                initiative_name, initiative_description, initiative_type, initiative_goals, 
                requirements, benefits, target_audience, required_volunteers, max_volunteers, 
                registered_volunteers, start_date, end_date, registration_deadline, 
                initiative_status, coordinator_name, coordinator_phone, coordinator_email, 
                location, budget, success_story, impact_description, is_featured, 
                is_active, auto_approval, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $name, 
                $description, 
                $type, 
                $_POST['initiative_goals'] ?: null,
                $_POST['requirements'] ?: null,
                $_POST['benefits'] ?: null,
                $_POST['target_audience'] ?: null,
                $_POST['required_volunteers'] ?: null,
                $_POST['max_volunteers'] ?: 50,
                $_POST['start_date'] ?: null,
                $_POST['end_date'] ?: null,
                $_POST['registration_deadline'] ?: null,
                $_POST['initiative_status'] ?: 'مفتوحة للتسجيل',
                $_POST['coordinator_name'] ?: null,
                $_POST['coordinator_phone'] ?: null,
                $_POST['coordinator_email'] ?: null,
                $_POST['location'] ?: null,
                $_POST['budget'] ?: 0,
                $_POST['success_story'] ?: null,
                $_POST['impact_description'] ?: null,
                isset($_POST['is_featured']) ? 1 : 0,
                isset($_POST['is_active']) ? 1 : 0,
                isset($_POST['auto_approval']) ? 1 : 0,
                $_POST['status'] ?: 'مخطط'
            ]);
            
            if ($result) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>
                        ✅ تم إضافة المبادرة بنجاح! ID: " . $db->lastInsertId() . "
                      </div>";
            }
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>
                    ❌ خطأ في إضافة المبادرة: " . $e->getMessage() . "
                  </div>";
        }
    }
}

// جلب آخر المبادرات
$recent_initiatives = $db->query("SELECT * FROM youth_environmental_initiatives ORDER BY created_at DESC LIMIT 3")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار نموذج المبادرة الشاملة</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007cba; }
        .form-section h3 { color: #333; margin-top: 0; }
        input, textarea, select { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; }
        button { background: #007cba; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a8b; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
        .checkbox-group { display: flex; gap: 20px; align-items: center; }
        .checkbox-group label { width: auto; }
        .recent-initiatives { margin-top: 30px; }
        .initiative-card { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #dee2e6; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-featured { background: #fff3cd; color: #856404; }
        .badge-status { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <form method="POST">
            <div class="form-section">
                <h3>📋 المعلومات الأساسية</h3>
                <div class="grid">
                    <div>
                        <label>اسم المبادرة *</label>
                        <input type="text" name="initiative_name" required placeholder="مثال: مبادرة تنظيف نهر دجلة">
                    </div>
                    <div>
                        <label>نوع المبادرة *</label>
                        <select name="initiative_type" required>
                            <option value="">اختر نوع المبادرة</option>
                            <option value="شبابية">شبابية</option>
                            <option value="بيئية">بيئية</option>
                            <option value="مجتمعية">مجتمعية</option>
                            <option value="تطوعية">تطوعية</option>
                        </select>
                    </div>
                </div>
                <label>وصف المبادرة *</label>
                <textarea name="initiative_description" rows="3" required placeholder="وصف مفصل للمبادرة وأهدافها"></textarea>
                <label>أهداف المبادرة</label>
                <textarea name="initiative_goals" rows="3" placeholder="الأهداف المحددة للمبادرة"></textarea>
            </div>

            <div class="form-section">
                <h3>📝 التفاصيل الإضافية</h3>
                <div class="grid">
                    <div>
                        <label>المتطلبات</label>
                        <textarea name="requirements" rows="3" placeholder="المتطلبات اللازمة للمشاركة"></textarea>
                    </div>
                    <div>
                        <label>الفوائد المتوقعة</label>
                        <textarea name="benefits" rows="3" placeholder="الفوائد التي ستعود على المشاركين والمجتمع"></textarea>
                    </div>
                </div>
                <div class="grid">
                    <div>
                        <label>الفئة المستهدفة</label>
                        <input type="text" name="target_audience" placeholder="مثال: الشباب من 18-30 سنة">
                    </div>
                    <div>
                        <label>الموقع</label>
                        <input type="text" name="location" placeholder="موقع تنفيذ المبادرة">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>👥 إدارة المتطوعين</h3>
                <div class="grid-3">
                    <div>
                        <label>عدد المتطوعين المطلوب</label>
                        <input type="number" name="required_volunteers" min="0" placeholder="20">
                    </div>
                    <div>
                        <label>الحد الأقصى للمتطوعين</label>
                        <input type="number" name="max_volunteers" min="0" value="50">
                    </div>
                    <div>
                        <label>حالة المبادرة</label>
                        <select name="initiative_status">
                            <option value="مفتوحة للتسجيل">مفتوحة للتسجيل</option>
                            <option value="قيد التنفيذ">قيد التنفيذ</option>
                            <option value="مكتملة">مكتملة</option>
                            <option value="مؤجلة">مؤجلة</option>
                            <option value="ملغية">ملغية</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>📅 التواريخ المهمة</h3>
                <div class="grid-3">
                    <div>
                        <label>تاريخ البدء</label>
                        <input type="date" name="start_date">
                    </div>
                    <div>
                        <label>تاريخ الانتهاء</label>
                        <input type="date" name="end_date">
                    </div>
                    <div>
                        <label>آخر موعد للتسجيل</label>
                        <input type="date" name="registration_deadline">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>👤 معلومات المنسق</h3>
                <div class="grid-3">
                    <div>
                        <label>اسم المنسق</label>
                        <input type="text" name="coordinator_name" placeholder="أحمد محمد">
                    </div>
                    <div>
                        <label>رقم هاتف المنسق</label>
                        <input type="text" name="coordinator_phone" placeholder="07901234567">
                    </div>
                    <div>
                        <label>بريد المنسق الإلكتروني</label>
                        <input type="email" name="coordinator_email" placeholder="coordinator@example.com">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>💰 الميزانية والإعدادات</h3>
                <div class="grid">
                    <div>
                        <label>الميزانية المقدرة (دينار عراقي)</label>
                        <input type="number" name="budget" step="0.01" min="0" value="0" placeholder="1000000">
                    </div>
                    <div>
                        <label>حالة النشاط</label>
                        <select name="status">
                            <option value="مخطط">مخطط</option>
                            <option value="نشط">نشط</option>
                            <option value="مكتمل">مكتمل</option>
                            <option value="معلق">معلق</option>
                            <option value="ملغي">ملغي</option>
                        </select>
                    </div>
                </div>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="is_featured"> مبادرة مميزة</label>
                    <label><input type="checkbox" name="is_active" checked> نشطة</label>
                    <label><input type="checkbox" name="auto_approval" checked> موافقة تلقائية للمتطوعين</label>
                </div>
            </div>

            <div class="form-section">
                <h3>🏆 قصة النجاح والتأثير</h3>
                <div class="grid">
                    <div>
                        <label>قصة النجاح</label>
                        <textarea name="success_story" rows="3" placeholder="قصة نجاح المبادرة (إن وجدت)"></textarea>
                    </div>
                    <div>
                        <label>وصف التأثير</label>
                        <textarea name="impact_description" rows="3" placeholder="وصف التأثير المتوقع أو المحقق"></textarea>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <button type="submit">✨ إضافة المبادرة</button>
            </div>
        </form>

        <?php if (!empty($recent_initiatives)): ?>
        <div class="recent-initiatives">
            <h2>📋 آخر المبادرات المضافة</h2>
            <?php foreach ($recent_initiatives as $initiative): ?>
            <div class="initiative-card">
                <h4><?= htmlspecialchars($initiative['initiative_name']) ?>
                    <?php if ($initiative['is_featured']): ?>
                        <span class="badge badge-featured">⭐ مميزة</span>
                    <?php endif; ?>
                    <span class="badge badge-status"><?= $initiative['initiative_status'] ?></span>
                </h4>
                <p><strong>النوع:</strong> <?= $initiative['initiative_type'] ?></p>
                <p><strong>الوصف:</strong> <?= htmlspecialchars(substr($initiative['initiative_description'], 0, 200)) ?>...</p>
                <p><strong>المتطوعين:</strong> <?= $initiative['registered_volunteers'] ?>/<?= $initiative['max_volunteers'] ?: 'غير محدد' ?></p>
                <p><strong>تاريخ الإنشاء:</strong> <?= date('Y/m/d H:i', strtotime($initiative['created_at'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 