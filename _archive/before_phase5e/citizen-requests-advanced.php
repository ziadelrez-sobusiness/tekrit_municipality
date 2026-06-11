<?php
/**
 * صفحة طلبات المواطنين المتقدمة
 * تدعم النماذج الديناميكية والسيناريوهات المطلوبة
 */

require_once '../config/database.php';
require_once '../includes/Utils.php';
require_once '../includes/RequestType.php';
require_once '../includes/CitizenRequest.php';
require_once '../includes/FileUpload.php';
require_once '../includes/recaptcha_helper.php';

$database = new Database();
$db = $database->getConnection();
$requestType = new RequestType($database);

$message = '';
$error = '';
$success = false;
$trackingNumber = '';

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    try {
        // التحقق من reCAPTCHA
        if (!RecaptchaHelper::verifyResponse($_POST['g-recaptcha-response'] ?? '')) {
            throw new Exception('فشل التحقق من reCAPTCHA. يرجى المحاولة مرة أخرى.');
        }
        
        // تنظيف البيانات
        $data = Utils::sanitizeArray($_POST);
        
        // التحقق من البيانات الأساسية
        $requiredFields = ['citizen_name', 'citizen_phone', 'request_type_id', 'request_title', 'request_description'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("حقل {$field} مطلوب");
            }
        }
        
        // التحقق من نوع الطلب والحصول على معلوماته
        $requestTypeInfo = $requestType->getTypeById($data['request_type_id']);
        if (!$requestTypeInfo) {
            throw new Exception('نوع الطلب غير صحيح');
        }
        
        // جمع بيانات النموذج الديناميكية
        $formData = [];
        $formFields = json_decode($requestTypeInfo['form_fields'] ?? '{}', true);
        
        foreach ($formFields as $fieldName => $fieldConfig) {
            if (isset($fieldConfig['type']) && $fieldConfig['type'] !== 'section') {
                $fieldValue = $data[$fieldName] ?? '';
                if (!empty($fieldValue)) {
                    $formData[$fieldName] = $fieldValue;
                }
            }
        }
        
        // التحقق من صحة بيانات النموذج الديناميكية
        if (!empty($formFields)) {
            $validationErrors = $requestType->validateFormData($data['request_type_id'], $formData);
            if (!empty($validationErrors)) {
                throw new Exception('أخطاء في النموذج: ' . implode(', ', $validationErrors));
            }
        }
        
        // إعداد بيانات الطلب
        $requestData = [
            'request_type_id' => $data['request_type_id'],
            'citizen_name' => $data['citizen_name'],
            'citizen_phone' => $data['citizen_phone'],
            'citizen_email' => $data['citizen_email'] ?? null,
            'citizen_address' => $data['citizen_address'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'request_title' => $data['request_title'],
            'request_description' => $data['request_description'],
            'priority_level' => $data['priority_level'] ?? 'عادي',
            'project_id' => !empty($data['project_id']) ? (int)$data['project_id'] : null,
            'form_data' => $formData
        ];
        
        // معالجة رفع الملفات
        $documents = [];
        if (!empty($_FILES['documents']['name'][0])) {
            $fileUpload = new FileUpload();
            for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                    $uploadResult = $fileUpload->uploadFile([
                        'name' => $_FILES['documents']['name'][$i],
                        'type' => $_FILES['documents']['type'][$i],
                        'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                        'error' => $_FILES['documents']['error'][$i],
                        'size' => $_FILES['documents']['size'][$i]
                    ], 'documents');
                    
                    if ($uploadResult['success']) {
                        $documents[] = [
                            'filename' => $uploadResult['filename'],
                            'original_name' => $_FILES['documents']['name'][$i],
                            'type' => 'مستند مرفق'
                        ];
                    }
                }
            }
        }
        
        $requestData['documents'] = $documents;
        
        // إنشاء الطلب
        $citizenRequest = new CitizenRequest($database);
        $result = $citizenRequest->create($requestData);
        
        if ($result['success']) {
            $success = true;
            $trackingNumber = $result['tracking_number'];
            $message = "تم تقديم طلبك بنجاح! رقم التتبع: {$trackingNumber}";
            
            // إنشاء مراحل العمل إذا كانت متوفرة
            $this->createWorkflowStages($result['request_id'], $data['request_type_id']);
            
            // إعادة تعيين النموذج
            $_POST = [];
        } else {
            throw new Exception($result['error']);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// جلب أنواع الطلبات النشطة
$requestTypes = $requestType->getAllActiveTypes();

// جلب المشاريع للمساهمة
$projects = [];
try {
    $stmt = $db->query("SELECT id, project_name FROM development_projects WHERE project_status = 'نشط' ORDER BY project_name");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // تجاهل الخطأ إذا كان الجدول غير موجود
}

// إعدادات الموقع
$site_title = "بلدية تكريت";
$site_description = "خدمات إلكترونية متطورة";

/**
 * إنشاء مراحل العمل للطلب
 */
function createWorkflowStages($requestId, $requestTypeId) {
    global $db;
    
    try {
        // فحص وجود جدول مراحل العمل
        $stmt = $db->query("SHOW TABLES LIKE 'request_workflow_stages'");
        if ($stmt->rowCount() == 0) return;
        
        // جلب مراحل العمل لنوع الطلب
        $stmt = $db->prepare("
            SELECT id FROM request_workflow_stages 
            WHERE request_type_id = ? 
            ORDER BY stage_order
        ");
        $stmt->execute([$requestTypeId]);
        $stages = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($stages)) return;
        
        // فحص وجود جدول تتبع المراحل
        $stmt = $db->query("SHOW TABLES LIKE 'request_stage_tracking'");
        if ($stmt->rowCount() == 0) return;
        
        // إنشاء تتبع لكل مرحلة
        $trackingStmt = $db->prepare("
            INSERT INTO request_stage_tracking (request_id, stage_id, status, created_at) 
            VALUES (?, ?, 'pending', NOW())
        ");
        
        foreach ($stages as $stageId) {
            $trackingStmt->execute([$requestId, $stageId]);
        }
        
        // تفعيل المرحلة الأولى
        if (!empty($stages)) {
            $db->prepare("
                UPDATE request_stage_tracking 
                SET status = 'in_progress', started_at = NOW() 
                WHERE request_id = ? AND stage_id = ?
            ")->execute([$requestId, $stages[0]]);
        }
        
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقديم طلب جديد - <?= htmlspecialchars($site_title) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 20px auto;
            overflow: hidden;
        }
        
        .header-section {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .form-section {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .section-title {
            background: #f8f9fa;
            padding: 15px 20px;
            margin: 25px -30px 20px -30px;
            border-right: 4px solid #3498db;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .dynamic-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin: 20px 0;
        }
        
        .success-card {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            text-align: center;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .tracking-number {
            font-size: 1.5em;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            letter-spacing: 2px;
        }
        
        .required-docs {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .required-docs h6 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .required-docs ul {
            margin-bottom: 0;
            padding-right: 20px;
        }
        
        .required-docs li {
            color: #856404;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .form-section {
                padding: 20px;
            }
            
            .section-title {
                margin-left: -20px;
                margin-right: -20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <!-- Header -->
            <div class="header-section">
                <h1><i class="fas fa-file-alt me-3"></i>تقديم طلب جديد</h1>
                <p class="mb-0">نظام متقدم لخدمات المواطنين - <?= htmlspecialchars($site_title) ?></p>
            </div>
            
            <div class="form-section">
                <?php if ($success): ?>
                    <!-- رسالة النجاح -->
                    <div class="success-card">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <h3>تم تقديم طلبك بنجاح!</h3>
                        <p>شكراً لك على استخدام خدماتنا الإلكترونية</p>
                        <div class="tracking-number">
                            رقم التتبع: <?= htmlspecialchars($trackingNumber) ?>
                        </div>
                        <p class="mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            احتفظ برقم التتبع لمتابعة حالة طلبك
                        </p>
                        <div class="mt-4">
                            <a href="track-request.php" class="btn btn-light btn-lg me-3">
                                <i class="fas fa-search me-2"></i>تتبع الطلب
                            </a>
                            <a href="citizen-requests-advanced.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-plus me-2"></i>طلب جديد
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- النموذج -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="requestForm">
                        <!-- معلومات المواطن -->
                        <div class="section-title">
                            <i class="fas fa-user me-2"></i>معلومات مقدم الطلب
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">الاسم الكامل *</label>
                                    <input type="text" name="citizen_name" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['citizen_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">رقم الهاتف *</label>
                                    <input type="tel" name="citizen_phone" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['citizen_phone'] ?? '') ?>" 
                                           placeholder="07xxxxxxxxx" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">البريد الإلكتروني</label>
                                    <input type="email" name="citizen_email" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['citizen_email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">رقم الهوية الشخصية</label>
                                    <input type="text" name="national_id" class="form-control" 
                                           value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">العنوان</label>
                            <textarea name="citizen_address" rows="2" class="form-control"><?= htmlspecialchars($_POST['citizen_address'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- معلومات الطلب -->
                        <div class="section-title">
                            <i class="fas fa-clipboard-list me-2"></i>تفاصيل الطلب
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">نوع الطلب *</label>
                                    <select name="request_type_id" id="request_type_id" class="form-select" required onchange="loadDynamicForm()">
                                        <option value="">اختر نوع الطلب</option>
                                        <?php foreach ($requestTypes as $type): ?>
                                            <option value="<?= $type['id'] ?>" 
                                                    data-form-fields="<?= htmlspecialchars($type['form_fields'] ?? '{}') ?>"
                                                    data-required-docs="<?= htmlspecialchars($type['required_documents'] ?? '[]') ?>"
                                                    <?= (($_POST['request_type_id'] ?? '') == $type['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['name_ar'] ?? $type['type_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">مستوى الأولوية</label>
                                    <select name="priority_level" class="form-select">
                                        <option value="عادي" <?= (($_POST['priority_level'] ?? 'عادي') == 'عادي') ? 'selected' : '' ?>>عادي</option>
                                        <option value="مهم" <?= (($_POST['priority_level'] ?? '') == 'مهم') ? 'selected' : '' ?>>مهم</option>
                                        <option value="عاجل" <?= (($_POST['priority_level'] ?? '') == 'عاجل') ? 'selected' : '' ?>>عاجل</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">عنوان الطلب *</label>
                            <input type="text" name="request_title" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['request_title'] ?? '') ?>" 
                                   placeholder="عنوان مختصر وواضح للطلب" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">تفاصيل الطلب *</label>
                            <textarea name="request_description" rows="4" class="form-control" 
                                      placeholder="اشرح طلبك بالتفصيل..." required><?= htmlspecialchars($_POST['request_description'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- المشاريع (للمساهمة) -->
                        <?php if (!empty($projects)): ?>
                        <div id="project_selection" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">المشروع المراد المساهمة فيه</label>
                                <select name="project_id" id="project_id" class="form-select">
                                    <option value="">اختر المشروع</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?= $project['id'] ?>" 
                                                <?= (($_POST['project_id'] ?? '') == $project['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($project['project_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- النموذج الديناميكي -->
                        <div id="dynamic_form_container" style="display: none;">
                            <div class="section-title">
                                <i class="fas fa-edit me-2"></i>معلومات إضافية مطلوبة
                            </div>
                            <div id="dynamic_form_fields" class="dynamic-section"></div>
                        </div>
                        
                        <!-- المستندات المطلوبة -->
                        <div id="required_documents_container" style="display: none;">
                            <div class="required-docs">
                                <h6><i class="fas fa-paperclip me-2"></i>المستندات المطلوبة:</h6>
                                <ul id="required_documents_list"></ul>
                            </div>
                        </div>
                        
                        <!-- رفع الملفات -->
                        <div class="form-group">
                            <label class="form-label">المستندات المرفقة</label>
                            <input type="file" name="documents[]" multiple class="form-control" 
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                يمكنك رفع ملفات PDF, Word, أو صور (الحد الأقصى: 5 ميجابايت لكل ملف)
                            </div>
                        </div>
                        
                        <!-- reCAPTCHA -->
                        <div class="form-group text-center">
                            <?= RecaptchaHelper::renderWidget('citizen_request') ?>
                            <div class="form-text mt-2">
                                <i class="fas fa-shield-alt me-1"></i>محمي بواسطة reCAPTCHA
                            </div>
                        </div>
                        
                        <!-- أزرار التحكم -->
                        <div class="text-center mt-4">
                            <button type="submit" name="submit_request" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>تقديم الطلب
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary btn-lg ms-3">
                                <i class="fas fa-arrow-right me-2"></i>العودة للرئيسية
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // تحميل النموذج الديناميكي
        function loadDynamicForm() {
            const typeSelect = document.getElementById('request_type_id');
            const selectedOption = typeSelect.options[typeSelect.selectedIndex];
            const dynamicContainer = document.getElementById('dynamic_form_container');
            const dynamicFields = document.getElementById('dynamic_form_fields');
            const projectSelection = document.getElementById('project_selection');
            const docsContainer = document.getElementById('required_documents_container');
            const docsList = document.getElementById('required_documents_list');
            
            // إخفاء جميع الأقسام أولاً
            dynamicContainer.style.display = 'none';
            projectSelection.style.display = 'none';
            docsContainer.style.display = 'none';
            
            if (!selectedOption.value) return;
            
            // فحص نوع الطلب للمساهمة
            const typeName = selectedOption.textContent;
            if (typeName.includes('المساهمة') || typeName.includes('مشروع')) {
                projectSelection.style.display = 'block';
                document.getElementById('project_id').required = true;
            } else {
                document.getElementById('project_id').required = false;
            }
            
            // تحميل النموذج الديناميكي
            const formFields = selectedOption.getAttribute('data-form-fields');
            if (formFields && formFields !== '{}') {
                try {
                    const fields = JSON.parse(formFields);
                    const html = generateDynamicForm(fields);
                    dynamicFields.innerHTML = html;
                    dynamicContainer.style.display = 'block';
                } catch (e) {
                    console.error('خطأ في تحميل النموذج الديناميكي:', e);
                }
            }
            
            // عرض المستندات المطلوبة
            const requiredDocs = selectedOption.getAttribute('data-required-docs');
            if (requiredDocs && requiredDocs !== '[]') {
                try {
                    const docs = JSON.parse(requiredDocs);
                    if (docs.length > 0) {
                        docsList.innerHTML = docs.map(doc => `<li>${doc}</li>`).join('');
                        docsContainer.style.display = 'block';
                    }
                } catch (e) {
                    console.error('خطأ في تحميل المستندات المطلوبة:', e);
                }
            }
        }
        
        // إنشاء HTML للنموذج الديناميكي
        function generateDynamicForm(fields) {
            let html = '';
            
            for (const [fieldName, config] of Object.entries(fields)) {
                if (config.type === 'section') {
                    html += `<h5 class="mt-4 mb-3 text-primary">${config.label}</h5>`;
                } else {
                    html += generateFieldHTML(fieldName, config);
                }
            }
            
            return html;
        }
        
        // إنشاء HTML لحقل واحد
        function generateFieldHTML(fieldName, config) {
            const required = config.required ? 'required' : '';
            const requiredMark = config.required ? ' <span class="text-danger">*</span>' : '';
            
            let html = `<div class="form-group">`;
            html += `<label class="form-label">${config.label}${requiredMark}</label>`;
            
            switch (config.type) {
                case 'text':
                case 'email':
                case 'tel':
                    html += `<input type="${config.type}" name="${fieldName}" class="form-control" ${required}>`;
                    break;
                    
                case 'number':
                    const min = config.min ? `min="${config.min}"` : '';
                    const max = config.max ? `max="${config.max}"` : '';
                    html += `<input type="number" name="${fieldName}" class="form-control" ${min} ${max} ${required}>`;
                    break;
                    
                case 'date':
                    html += `<input type="date" name="${fieldName}" class="form-control" ${required}>`;
                    break;
                    
                case 'textarea':
                    const rows = config.rows || 3;
                    html += `<textarea name="${fieldName}" rows="${rows}" class="form-control" ${required}></textarea>`;
                    break;
                    
                case 'select':
                    html += `<select name="${fieldName}" class="form-select" ${required}>`;
                    html += `<option value="">اختر...</option>`;
                    if (config.options) {
                        config.options.forEach(option => {
                            html += `<option value="${option}">${option}</option>`;
                        });
                    }
                    html += `</select>`;
                    break;
            }
            
            if (config.help) {
                html += `<div class="form-text">${config.help}</div>`;
            }
            
            html += `</div>`;
            return html;
        }
        
        // تحميل النموذج عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            loadDynamicForm();
        });
        
        // التحقق من النموذج قبل الإرسال
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('يرجى تعبئة جميع الحقول المطلوبة');
            }
        });
    </script>
</body>
</html> 