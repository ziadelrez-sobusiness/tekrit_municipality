<?php
session_start();
require_once '../config/database.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec('SET NAMES utf8mb4');

$message = '';
$error = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_initiative'])) {
    $name = trim($_POST['initiative_name'] ?? '');
    $description = trim($_POST['initiative_description'] ?? '');
    $type = trim($_POST['initiative_type'] ?? '');
    $max_volunteers = intval($_POST['max_volunteers'] ?? 10);
    $location = trim($_POST['location'] ?? '');
    $registration_deadline = $_POST['registration_deadline'] ?? null;
    $budget = floatval($_POST['budget'] ?? 0);
    $requirements = trim($_POST['requirements'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $auto_approval = isset($_POST['auto_approval']) ? 1 : 0;
    
    if (empty($name) || empty($description) || empty($type)) {
        $error = "يرجى ملء جميع الحقول المطلوبة";
    } else {
        try {
            // إدراج المبادرة
            $stmt = $db->prepare("
                INSERT INTO youth_environmental_initiatives 
                (initiative_name, initiative_description, initiative_type, max_volunteers, location, registration_deadline, budget, requirements, benefits, is_active, auto_approval, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$name, $description, $type, $max_volunteers, $location, $registration_deadline, $budget, $requirements, $benefits, $is_active, $auto_approval, $_SESSION['admin_id']])) {
                $initiative_id = $db->lastInsertId();
                
                // معالجة رفع الصور
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../public/assets/images/initiatives/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $uploaded_files = [];
                    $file_count = count($_FILES['images']['name']);
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['images']['name'][$i];
                            $file_tmp = $_FILES['images']['tmp_name'][$i];
                            $file_size = $_FILES['images']['size'][$i];
                            $file_type = $_FILES['images']['type'][$i];
                            
                            // التحقق من نوع الملف
                            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                            if (!in_array($file_type, $allowed_types)) {
                                continue;
                            }
                            
                            // التحقق من حجم الملف (5MB max)
                            if ($file_size > 5 * 1024 * 1024) {
                                continue;
                            }
                            
                            // إنشاء اسم فريد للملف
                            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                            $unique_name = 'initiative_' . $initiative_id . '_' . time() . '_' . $i . '.' . $file_extension;
                            $file_path = $upload_dir . $unique_name;
                            
                            if (move_uploaded_file($file_tmp, $file_path)) {
                                // حفظ معلومات الصورة في قاعدة البيانات
                                $image_type = $_POST['image_type'][$i] ?? 'معرض';
                                $image_description = $_POST['image_description'][$i] ?? '';
                                $display_order = $_POST['display_order'][$i] ?? $i;
                                
                                $img_stmt = $db->prepare("
                                    INSERT INTO initiative_images 
                                    (initiative_id, image_path, image_name, image_description, image_type, display_order, file_size, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                
                                $relative_path = 'assets/images/initiatives/' . $unique_name;
                                $img_stmt->execute([
                                    $initiative_id, $relative_path, $file_name, $image_description,
                                    $image_type, $display_order, $file_size, $_SESSION['admin_id']
                                ]);
                                
                                // تحديث الصورة الرئيسية إذا كانت هذه أول صورة رئيسية
                                if ($image_type === 'رئيسية' && empty($uploaded_files)) {
                                    $update_main = $db->prepare("UPDATE youth_environmental_initiatives SET main_image = ? WHERE id = ?");
                                    $update_main->execute([$relative_path, $initiative_id]);
                                }
                                
                                $uploaded_files[] = $file_name;
                            }
                        }
                    }
                }
                
                $message = "تم إضافة المبادرة بنجاح" . (count($uploaded_files ?? []) > 0 ? " مع " . count($uploaded_files) . " صورة" : "");
                
                // إعادة توجيه إلى صفحة إدارة الصور
                header("Location: manage_initiative_images.php?id=" . $initiative_id);
                exit();
            } else {
                $error = "حدث خطأ في إضافة المبادرة";
            }
        } catch (Exception $e) {
            $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

// جلب أنواع المبادرات الموجودة
$types = $db->query("SELECT DISTINCT initiative_type FROM youth_environmental_initiatives ORDER BY initiative_type")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مبادرة جديدة - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .upload-area { 
            border: 2px dashed #dee2e6; 
            border-radius: 8px; 
            padding: 40px; 
            text-align: center; 
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area.dragover { 
            border-color: #0d6efd; 
            background-color: #f8f9fa; 
        }
        .selected-image {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="initiatives.php">
                                <i class="fas fa-lightbulb"></i> المبادرات
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">إضافة مبادرة جديدة</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="initiatives.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-right"></i> العودة للمبادرات
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="initiativeForm">
                    <div class="row">
                        <!-- معلومات المبادرة -->
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-info-circle"></i> معلومات المبادرة</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">اسم المبادرة *</label>
                                        <input type="text" name="initiative_name" class="form-control" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">وصف المبادرة *</label>
                                        <textarea name="initiative_description" class="form-control" rows="5" required></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">نوع المبادرة *</label>
                                                <select name="initiative_type" class="form-select" required>
                                                    <option value="">اختر النوع</option>
                                                    <option value="بيئية">بيئية</option>
                                                    <option value="شبابية">شبابية</option>
                                                    <option value="مجتمعية">مجتمعية</option>
                                                    <option value="ثقافية">ثقافية</option>
                                                    <option value="تعليمية">تعليمية</option>
                                                    <?php foreach ($types as $type): ?>
                                                        <option value="<?= htmlspecialchars($type['initiative_type']) ?>">
                                                            <?= htmlspecialchars($type['initiative_type']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">عدد المتطوعين المطلوب</label>
                                                <input type="number" name="max_volunteers" class="form-control" min="1" value="10">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">الموقع</label>
                                                <input type="text" name="location" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">آخر موعد للتسجيل</label>
                                                <input type="date" name="registration_deadline" class="form-control">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">الميزانية (دينار)</label>
                                        <input type="number" name="budget" class="form-control" step="0.01" min="0">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">المتطلبات</label>
                                        <textarea name="requirements" class="form-control" rows="3" placeholder="اذكر المتطلبات والشروط للانضمام"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">المزايا والفوائد</label>
                                        <textarea name="benefits" class="form-control" rows="3" placeholder="اذكر الفوائد التي سيحصل عليها المتطوعون"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- رفع الصور -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-images"></i> صور المبادرة</h5>
                                </div>
                                <div class="card-body">
                                    <div class="upload-area mb-3" id="uploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <p>اسحب الصور هنا أو انقر للاختيار</p>
                                        <input type="file" name="images[]" id="imageInput" multiple accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="d-none">
                                        <button type="button" class="btn btn-primary" onclick="openFileSelector()">
                                            <i class="fas fa-images"></i> اختيار الصور
                                        </button>
                                        <p class="small text-muted mt-2">
                                            الحد الأقصى: 5 ميجابايت لكل صورة<br>
                                            الأنواع المدعومة: JPG, PNG, GIF, WebP
                                        </p>
                                    </div>
                                    
                                    <div id="selectedImages"></div>
                                </div>
                            </div>
                        </div>

                        <!-- الإعدادات -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-cog"></i> إعدادات المبادرة</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                            <label class="form-check-label" for="is_active">
                                                تفعيل المبادرة
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="auto_approval" id="auto_approval">
                                            <label class="form-check-label" for="auto_approval">
                                                الموافقة التلقائية على التسجيل
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" name="add_initiative" class="btn btn-success">
                                            <i class="fas fa-save"></i> حفظ المبادرة
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedFiles = [];
        const imageInput = document.getElementById('imageInput');
        const selectedImages = document.getElementById('selectedImages');
        const uploadArea = document.getElementById('uploadArea');

        function openFileSelector() {
            imageInput.click();
        }

        // منع النقر المباشر على منطقة الرفع
        uploadArea.addEventListener('click', (e) => {
            if (e.target.closest('button') || e.target.tagName === 'BUTTON' || e.target.tagName === 'I') {
                return;
            }
            imageInput.click();
        });

        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);
        imageInput.addEventListener('change', handleFileSelect);

        function handleFileSelect(e) {
            const files = Array.from(e.target.files);
            addFiles(files);
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
            const files = Array.from(e.dataTransfer.files);
            addFiles(files);
        }

        function addFiles(files) {
            // فلترة ملفات الصور فقط
            const imageFiles = files.filter(file => {
                return file.type.startsWith('image/') && 
                       ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'].includes(file.type);
            });
            
            if (imageFiles.length === 0) {
                alert('يرجى اختيار ملفات صور صالحة (JPG, PNG, GIF, WebP)');
                return;
            }
            
            if (imageFiles.length !== files.length) {
                alert('تم تجاهل بعض الملفات - يتم دعم ملفات الصور فقط');
            }
            
            // إضافة الملفات الجديدة
            selectedFiles = imageFiles;
            displaySelectedImages();
            updateFileInput();
        }

        function displaySelectedImages() {
            selectedImages.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imageDiv = document.createElement('div');
                    imageDiv.className = 'selected-image';
                    imageDiv.setAttribute('data-index', index);
                    
                    // التحقق من حجم الملف
                    const sizeWarning = file.size > 5 * 1024 * 1024 ? 
                        '<div class="text-danger mt-1"><i class="fas fa-exclamation-triangle"></i> الملف كبير جداً (أكثر من 5 MB)</div>' : '';
                    
                    imageDiv.innerHTML = `
                        <div class="row">
                            <div class="col-md-3">
                                <img src="${e.target.result}" class="img-thumbnail" style="height: 120px; object-fit: cover; width: 100%;">
                                <div class="mt-2">
                                    <small class="text-muted d-block">${file.name}</small>
                                    <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                                    ${sizeWarning}
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">نوع الصورة</label>
                                        <select name="image_type[]" class="form-select">
                                            <option value="رئيسية" ${index === 0 ? 'selected' : ''}>رئيسية</option>
                                            <option value="معرض" ${index > 0 ? 'selected' : ''}>معرض</option>
                                            <option value="نشاط">نشاط</option>
                                            <option value="نتائج">نتائج</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ترتيب العرض</label>
                                        <input type="number" name="display_order[]" class="form-control" value="${index}" min="0">
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">وصف الصورة</label>
                                    <input type="text" name="image_description[]" class="form-control" placeholder="وصف اختياري للصورة">
                                </div>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeImage(${index})" title="حذف الصورة">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    selectedImages.appendChild(imageDiv);
                };
                
                reader.readAsDataURL(file);
            });
        }

        function updateFileInput() {
            // إنشاء DataTransfer جديد
            const dataTransfer = new DataTransfer();
            
            // إضافة جميع الملفات
            selectedFiles.forEach(file => {
                dataTransfer.items.add(file);
            });
            
            // تحديث input
            imageInput.files = dataTransfer.files;
        }

        function removeImage(index) {
            selectedFiles.splice(index, 1);
            displaySelectedImages();
            updateFileInput();
        }

        // التحقق من النموذج قبل الإرسال
        document.getElementById('initiativeForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const nameInput = this.querySelector('input[name="initiative_name"]');
            const descInput = this.querySelector('textarea[name="initiative_description"]');
            const typeInput = this.querySelector('select[name="initiative_type"]');
            
            if (!nameInput.value.trim()) {
                alert('يرجى إدخال اسم المبادرة');
                nameInput.focus();
                e.preventDefault();
                return false;
            }
            
            if (!descInput.value.trim()) {
                alert('يرجى إدخال وصف المبادرة');
                descInput.focus();
                e.preventDefault();
                return false;
            }
            
            if (!typeInput.value) {
                alert('يرجى اختيار نوع المبادرة');
                typeInput.focus();
                e.preventDefault();
                return false;
            }
            
            // عرض حالة التحميل
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html> 