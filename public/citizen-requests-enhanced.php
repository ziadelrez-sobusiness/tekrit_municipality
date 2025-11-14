<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/CitizenRequest.php';
require_once '../includes/RequestType.php';
require_once '../includes/Utils.php';
require_once '../includes/FileUpload.php';
require_once '../includes/recaptcha_helper.php';

$citizenRequest = new CitizenRequest();
$requestType = new RequestType();
$fileUpload = new FileUpload();

$success_message = '';
$error_message = '';
$tracking_number = '';

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    
    // التحقق من رمز CSRF
    if (!Utils::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'رمز الأمان غير صحيح. يرجى إعادة المحاولة.';
    } else {
        // تنظيف البيانات
        $data = Utils::sanitizeInput($_POST);
        
        // التحقق من البيانات المطلوبة
        if (empty($data['citizen_name']) || empty($data['citizen_phone']) || 
            empty($data['request_type_id']) || empty($data['request_title']) || 
            empty($data['request_description'])) {
            $error_message = "جميع الحقول المطلوبة يجب ملؤها";
        } else {
            
            // التحقق من reCAPTCHA
            $min_score = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') ? 0.3 : 0.5;
            $recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null, $min_score);
            
            if (!$recaptcha_result['success'] && !($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
                $error_message = 'فشل التحقق الأمني: ' . $recaptcha_result['error'];
            } else {
                
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
                    'project_id' => !empty($data['project_id']) ? (int)$data['project_id'] : null
                ];
                
                // إضافة بيانات النموذج إذا كانت موجودة
                $formData = [];
                foreach ($data as $key => $value) {
                    if (strpos($key, 'form_') === 0) {
                        $formData[substr($key, 5)] = $value;
                    }
                }
                
                if (!empty($formData)) {
                    $requestData['form_data'] = $formData;
                }
                
                // معالجة رفع الملفات
                $documents = [];
                if (!empty($_FILES['documents']['name'][0])) {
                    $uploadResults = $fileUpload->uploadMultipleFiles($_FILES['documents'], 'requests');
                    foreach ($uploadResults as $result) {
                        if ($result['success']) {
                            $documents[] = [
                                'document_name' => 'مستند مرفق',
                                'original_filename' => $result['file_name'],
                                'file_path' => $result['file_path'],
                                'file_size' => $result['file_size'],
                                'file_type' => $result['file_type']
                            ];
                        }
                    }
                }
                
                if (!empty($documents)) {
                    $requestData['documents'] = $documents;
                }
                
                $result = $citizenRequest->create($requestData);
                
                if ($result['success']) {
                    $success_message = "تم تقديم طلبك بنجاح! رقم التتبع الخاص بك هو: " . $result['tracking_number'];
                    $tracking_number = $result['tracking_number'];
                    // إعادة تعيين النموذج
                    $_POST = array();
                } else {
                    $error_message = "حدث خطأ أثناء تقديم الطلب: " . $result['error'];
                }
            }
        }
    }
}

// جلب أنواع الطلبات النشطة
$requestTypes = $requestType->getAllActiveTypes();

// جلب نوع الطلب المحدد من الرابط
$selectedTypeId = $_GET['type_id'] ?? '';
$selectedType = null;
if ($selectedTypeId) {
    $selectedType = $requestType->getById($selectedTypeId);
}

// جلب المشاريع التي تسمح بالمساهمة
$projects = [];
try {
    $db = Database::getInstance();
    $projects = $db->fetchAll("
        SELECT id, project_name 
        FROM development_projects 
        WHERE allow_contributions = 1 AND project_status != 'منفذ' 
        ORDER BY project_name
    ");
} catch (Exception $e) {
    $projects = [];
}

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    try {
        $db = Database::getInstance();
        $result = $db->fetch("SELECT setting_value FROM website_settings WHERE setting_key = ?", [$key]);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$site_title = getSetting('site_title', 'بلدية تكريت');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?> - تقديم طلب جديد</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <link href="assets/css/citizen-requests.css" rel="stylesheet">
    <?= RecaptchaHelper::renderScript() ?>
    <?= RecaptchaHelper::renderCSS() ?>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .form-field { transition: all 0.3s ease; }
        .form-field:focus { transform: translateY(-2px); }
        .dynamic-field { animation: slideIn 0.3s ease-out; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="tekrit-header sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between py-4">
                <!-- Logo and Title -->
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center ml-4">
                        <span class="text-white text-2xl font-bold">ت</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($site_title) ?></h1>
                        <p class="text-sm text-gray-600">خدمات إلكترونية للمواطنين</p>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="hidden lg:flex space-x-8 space-x-reverse">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium">الرئيسية</a>
                    <a href="#" class="text-blue-600 font-medium">تقديم طلب</a>
                    <a href="track-request.php" class="text-gray-700 hover:text-blue-600 font-medium">تتبع الطلب</a>
                    <a href="../login.php" class="btn-primary">🔐 دخول الموظفين</a>
                </nav>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="text-center mb-12 fade-in">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">📝 تقديم طلب جديد</h1>
            <p class="text-xl text-gray-600">
                قدم طلبك إلكترونياً واحصل على رقم تتبع لمتابعة حالة طلبك
            </p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success fade-in">
                <div class="flex items-center">
                    <span class="text-green-500 text-xl ml-3">✅</span>
                    <div>
                        <p class="font-bold"><?= $success_message ?></p>
                        <p class="text-sm mt-1">احفظ رقم التتبع لمتابعة طلبك لاحقاً</p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="track-request.php?tracking=<?= $tracking_number ?>" 
                       class="btn btn-success">
                        تتبع الطلب الآن
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger fade-in">
                <div class="flex items-center">
                    <span class="text-red-500 text-xl ml-3">❌</span>
                    <p class="font-bold"><?= $error_message ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Request Form -->
        <div class="card fade-in">
            <form method="POST" enctype="multipart/form-data" class="space-y-6" id="requestForm">
                <input type="hidden" name="csrf_token" value="<?= Utils::generateCSRFToken() ?>">
                
                <!-- Personal Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="card-title">المعلومات الشخصية</h3>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">الاسم الكامل *</label>
                                <input type="text" name="citizen_name" value="<?= htmlspecialchars($_POST['citizen_name'] ?? '') ?>" 
                                       class="form-control form-field" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">رقم الهاتف *</label>
                                <input type="tel" name="citizen_phone" value="<?= htmlspecialchars($_POST['citizen_phone'] ?? '') ?>" 
                                       class="form-control form-field" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" name="citizen_email" value="<?= htmlspecialchars($_POST['citizen_email'] ?? '') ?>" 
                                       class="form-control form-field">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">رقم البطاقة الوطنية</label>
                                <input type="text" name="national_id" value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>" 
                                       class="form-control form-field">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">العنوان الكامل</label>
                        <textarea name="citizen_address" rows="3" 
                                  class="form-control form-field"><?= htmlspecialchars($_POST['citizen_address'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Request Information -->
                <div>
                    <h3 class="card-title">تفاصيل الطلب</h3>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">نوع الطلب *</label>
                                <select name="request_type_id" id="request_type_id"
                                        class="form-control form-field" required onchange="loadFormFields()">
                                    <option value="">اختر نوع الطلب</option>
                                    <?php foreach ($requestTypes as $type): ?>
                                        <option value="<?= $type['id'] ?>" 
                                                <?= ($selectedTypeId == $type['id'] || ($_POST['request_type_id'] ?? '') == $type['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['name_ar'] ?? $type['type_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">مستوى الأولوية</label>
                                <select name="priority_level" class="form-control form-field">
                                    <option value="عادي" <?= ($_POST['priority_level'] ?? '') == 'عادي' ? 'selected' : '' ?>>عادي</option>
                                    <option value="مهم" <?= ($_POST['priority_level'] ?? '') == 'مهم' ? 'selected' : '' ?>>مهم</option>
                                    <option value="عاجل" <?= ($_POST['priority_level'] ?? '') == 'عاجل' ? 'selected' : '' ?>>عاجل</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Selection - Only show for contribution requests -->
                    <div id="project_selection" class="form-group" style="display: none;">
                        <label class="form-label">اختر المشروع *</label>
                        <select name="project_id" id="project_id" class="form-control form-field">
                            <option value="">اختر المشروع الذي تريد المساهمة فيه</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" 
                                        <?= ($_POST['project_id'] ?? '') == $project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($projects)): ?>
                            <p class="text-sm text-gray-500 mt-1">لا توجد مشاريع متاحة للمساهمة حالياً</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">عنوان الطلب *</label>
                        <input type="text" name="request_title" value="<?= htmlspecialchars($_POST['request_title'] ?? '') ?>" 
                               class="form-control form-field" placeholder="اكتب عنواناً مختصراً لطلبك" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تفاصيل الطلب *</label>
                        <textarea name="request_description" rows="6" 
                                  class="form-control form-field" placeholder="اشرح طلبك بالتفصيل..." required><?= htmlspecialchars($_POST['request_description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Dynamic Form Fields -->
                <div id="dynamic_fields" class="d-none">
                    <h3 class="card-title">معلومات إضافية</h3>
                    <div id="form_fields_container"></div>
                </div>

                <!-- File Upload -->
                <div class="form-group">
                    <label class="form-label">المستندات المرفقة (اختياري)</label>
                    <input type="file" name="documents[]" multiple 
                           class="form-control form-field" 
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <p class="text-sm text-gray-500 mt-1">
                        يمكنك رفع ملفات PDF, Word, أو صور (الحد الأقصى: 5 ميجابايت لكل ملف)
                    </p>
                </div>

                <!-- reCAPTCHA -->
                <div class="form-group text-center">
                    <?= RecaptchaHelper::renderWidget('citizen_request') ?>
                    <div class="text-sm text-gray-500 mt-2">
                        🛡️ محمي بواسطة reCAPTCHA
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="text-center pt-6">
                    <button type="submit" name="submit_request" 
                            class="btn btn-primary btn-lg">
                        📤 تقديم الطلب
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Section -->
        <div class="card mt-8" style="background: linear-gradient(135deg, #e3f2fd, #f3e5f5);">
            <h3 class="card-title" style="color: #1565c0;">💡 نصائح مهمة</h3>
            <ul class="space-y-2" style="color: #1976d2;">
                <li>• تأكد من صحة رقم الهاتف للتواصل معك</li>
                <li>• اكتب تفاصيل الطلب بوضوح ليتم التعامل معه بسرعة</li>
                <li>• احفظ رقم التتبع الذي ستحصل عليه لمتابعة طلبك</li>
                <li>• يمكنك تتبع حالة طلبك في أي وقت من صفحة "تتبع الطلب"</li>
                <li>• في حالة الطوارئ، يرجى الاتصال بنا مباشرة</li>
            </ul>
        </div>

        <!-- Quick Actions -->
        <div class="text-center mt-8">
            <div class="row">
                <div class="col-6">
                    <a href="track-request.php" class="btn btn-success w-100">
                        🔍 تتبع طلب موجود
                    </a>
                </div>
                <div class="col-6">
                    <a href="contact.php" class="btn btn-outline w-100">
                        📞 اتصل بنا
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-gray-400">© <?= date('Y') ?> جميع الحقوق محفوظة - <?= htmlspecialchars($site_title) ?></p>
            </div>
        </div>
    </footer>

    <script>
        // تحميل حقول النموذج الديناميكية
        function loadFormFields() {
            const typeId = document.getElementById('request_type_id').value;
            const dynamicFields = document.getElementById('dynamic_fields');
            const container = document.getElementById('form_fields_container');
            const projectSelection = document.getElementById('project_selection');
            const projectId = document.getElementById('project_id');
            
            if (!typeId) {
                dynamicFields.classList.add('d-none');
                projectSelection.style.display = 'none';
                return;
            }
            
            // التحقق من نوع الطلب للمساهمة في المشاريع
            const selectedOption = document.querySelector(`#request_type_id option[value="${typeId}"]`);
            const typeName = selectedOption ? selectedOption.textContent : '';
            
            if (typeName.includes('المساهمة') || typeName.includes('مشروع')) {
                projectSelection.style.display = 'block';
                projectId.required = true;
            } else {
                projectSelection.style.display = 'none';
                projectId.required = false;
                projectId.value = '';
            }
            
            // تحميل الحقول الديناميكية
            container.innerHTML = getFormFieldsForType(typeId);
            dynamicFields.classList.remove('d-none');
        }
        
        function getFormFieldsForType(typeId) {
            // حقول أساسية لجميع أنواع الطلبات
            let fields = `
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">تاريخ الحاجة للخدمة</label>
                            <input type="date" name="form_service_date" 
                                   class="form-control form-field dynamic-field">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">ملاحظات إضافية</label>
                            <textarea name="form_notes" rows="3"
                                      class="form-control form-field dynamic-field"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            // إضافة حقول خاصة بناءً على نوع الطلب
            // يمكن توسيع هذا بناءً على أنواع الطلبات المختلفة
            
            return fields;
        }
        
        // تحميل الحقول عند تحميل الصفحة إذا كان هناك نوع محدد
        document.addEventListener('DOMContentLoaded', function() {
            loadFormFields();
        });
    </script>
</body>
</html> 