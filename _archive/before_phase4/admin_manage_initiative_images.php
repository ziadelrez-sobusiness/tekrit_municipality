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
$db->exec("SET NAMES utf8mb4");

$initiative_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// التحقق من وجود المبادرة
$stmt = $db->prepare("SELECT * FROM youth_environmental_initiatives WHERE id = ?");
$stmt->execute([$initiative_id]);
$initiative = $stmt->fetch();

if (!$initiative) {
    header("Location: initiatives.php");
    exit();
}

// معالجة رفع الصور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
    $upload_dir = '../public/assets/images/initiatives/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_files = [];
    $errors = [];
    
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
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
                    $errors[] = "نوع الملف غير مدعوم: $file_name";
                    continue;
                }
                
                // التحقق من حجم الملف (5MB max)
                if ($file_size > 5 * 1024 * 1024) {
                    $errors[] = "حجم الملف كبير جداً: $file_name";
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
                    $display_order = $_POST['display_order'][$i] ?? 0;
                    
                    $stmt = $db->prepare("INSERT INTO initiative_images 
                        (initiative_id, image_path, image_name, image_description, image_type, display_order, file_size, uploaded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $relative_path = 'assets/images/initiatives/' . $unique_name;
                    $stmt->execute([
                        $initiative_id,
                        $relative_path,
                        $file_name,
                        $image_description,
                        $image_type,
                        $display_order,
                        $file_size,
                        $_SESSION['admin_id']
                    ]);
                    
                    // تحديث الصورة الرئيسية إذا كانت هذه أول صورة رئيسية
                    if ($image_type === 'رئيسية') {
                        $check_main = $db->prepare("SELECT main_image FROM youth_environmental_initiatives WHERE id = ?");
                        $check_main->execute([$initiative_id]);
                        $current_main = $check_main->fetchColumn();
                        
                        if (empty($current_main)) {
                            $update_main = $db->prepare("UPDATE youth_environmental_initiatives SET main_image = ? WHERE id = ?");
                            $update_main->execute([$relative_path, $initiative_id]);
                        }
                    }
                    
                    $uploaded_files[] = $file_name;
                } else {
                    $errors[] = "فشل في رفع الملف: $file_name";
                }
            }
        }
    }
    
    if (!empty($uploaded_files)) {
        $message = "تم رفع " . count($uploaded_files) . " صورة بنجاح";
    }
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}

// معالجة حذف الصور
if (isset($_POST['delete_image'])) {
    $image_id = (int)$_POST['image_id'];
    
    // الحصول على معلومات الصورة
    $stmt = $db->prepare("SELECT * FROM initiative_images WHERE id = ? AND initiative_id = ?");
    $stmt->execute([$image_id, $initiative_id]);
    $image = $stmt->fetch();
    
    if ($image) {
        // حذف الملف من الخادم
        $file_path = '../public/' . $image['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // حذف السجل من قاعدة البيانات
        $stmt = $db->prepare("DELETE FROM initiative_images WHERE id = ?");
        $stmt->execute([$image_id]);
        
        // إذا كانت الصورة الرئيسية، تحديث المبادرة
        if ($image['image_type'] === 'رئيسية') {
            // البحث عن صورة أخرى لتكون رئيسية
            $stmt = $db->prepare("SELECT image_path FROM initiative_images WHERE initiative_id = ? ORDER BY display_order LIMIT 1");
            $stmt->execute([$initiative_id]);
            $new_main = $stmt->fetchColumn();
            
            $update_main = $db->prepare("UPDATE youth_environmental_initiatives SET main_image = ? WHERE id = ?");
            $update_main->execute([$new_main ?: null, $initiative_id]);
        }
        
        $message = "تم حذف الصورة بنجاح";
    }
}

// تحديث ترتيب الصور
if (isset($_POST['update_order'])) {
    foreach ($_POST['image_order'] as $image_id => $order) {
        $stmt = $db->prepare("UPDATE initiative_images SET display_order = ? WHERE id = ? AND initiative_id = ?");
        $stmt->execute([$order, $image_id, $initiative_id]);
    }
    $message = "تم تحديث ترتيب الصور";
}

// جلب صور المبادرة
$stmt = $db->prepare("SELECT * FROM initiative_images WHERE initiative_id = ? ORDER BY display_order, created_at");
$stmt->execute([$initiative_id]);
$images = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة صور المبادرة - <?php echo htmlspecialchars($initiative['initiative_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .image-card { transition: transform 0.2s; }
        .image-card:hover { transform: translateY(-5px); }
        .image-preview { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; }
        .upload-area { border: 2px dashed #dee2e6; border-radius: 8px; padding: 40px; text-align: center; }
        .upload-area.dragover { border-color: #0d6efd; background-color: #f8f9fa; }
        .sortable { cursor: move; }
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
                    <h1 class="h2">إدارة صور المبادرة</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="initiatives.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-right"></i> العودة للمبادرات
                        </a>
                    </div>
                </div>

                <!-- معلومات المبادرة -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($initiative['initiative_name']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($initiative['initiative_description']); ?></p>
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

                <!-- رفع الصور -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-upload"></i> رفع صور جديدة</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-area mb-3" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <p>اسحب الصور هنا أو انقر للاختيار</p>
                                <input type="file" name="images[]" id="imageInput" multiple accept="image/*" class="d-none">
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('imageInput').click()">
                                    اختيار الصور
                                </button>
                            </div>
                            
                            <div id="selectedImages"></div>
                            
                            <button type="submit" name="upload_images" class="btn btn-success">
                                <i class="fas fa-upload"></i> رفع الصور
                            </button>
                        </form>
                    </div>
                </div>

                <!-- الصور الموجودة -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-images"></i> الصور الموجودة (<?php echo count($images); ?>)</h5>
                        <?php if (count($images) > 1): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="enableSorting()">
                                <i class="fas fa-sort"></i> ترتيب الصور
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($images)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                <p class="text-muted">لا توجد صور مرفوعة لهذه المبادرة</p>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="orderForm">
                                <div class="row" id="imagesContainer">
                                    <?php foreach ($images as $image): ?>
                                        <div class="col-md-4 col-lg-3 mb-4 image-card sortable" data-id="<?php echo $image['id']; ?>">
                                            <div class="card">
                                                <img src="../public/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                     class="image-preview" alt="<?php echo htmlspecialchars($image['image_name']); ?>">
                                                <div class="card-body p-2">
                                                    <h6 class="card-title small"><?php echo htmlspecialchars($image['image_name']); ?></h6>
                                                    <p class="card-text small text-muted">
                                                        النوع: <?php echo $image['image_type']; ?><br>
                                                        الحجم: <?php echo round($image['file_size']/1024, 1); ?> KB
                                                    </p>
                                                    <input type="hidden" name="image_order[<?php echo $image['id']; ?>]" 
                                                           value="<?php echo $image['display_order']; ?>" class="order-input">
                                                    <div class="btn-group w-100">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewImage('<?php echo htmlspecialchars($image['image_path']); ?>')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteImage(<?php echo $image['id']; ?>, '<?php echo htmlspecialchars($image['image_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" name="update_order" class="btn btn-primary d-none" id="saveOrderBtn">
                                    <i class="fas fa-save"></i> حفظ الترتيب
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal لعرض الصورة -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">عرض الصورة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="">
                </div>
            </div>
        </div>
    </div>

    <!-- Form لحذف الصورة -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="delete_image" value="1">
        <input type="hidden" name="image_id" id="deleteImageId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // معالجة رفع الصور
        const imageInput = document.getElementById('imageInput');
        const selectedImages = document.getElementById('selectedImages');
        const uploadArea = document.getElementById('uploadArea');

        imageInput.addEventListener('change', handleFileSelect);
        uploadArea.addEventListener('click', () => imageInput.click());
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('drop', handleDrop);

        function handleFileSelect(e) {
            displaySelectedImages(e.target.files);
        }

        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            displaySelectedImages(e.dataTransfer.files);
        }

        function displaySelectedImages(files) {
            selectedImages.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imageDiv = document.createElement('div');
                    imageDiv.className = 'row mb-3 p-3 border rounded';
                    imageDiv.innerHTML = `
                        <div class="col-md-3">
                            <img src="${e.target.result}" class="img-thumbnail" style="height: 100px; object-fit: cover;">
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">نوع الصورة</label>
                                    <select name="image_type[]" class="form-select">
                                        <option value="معرض">معرض</option>
                                        <option value="رئيسية">رئيسية</option>
                                        <option value="نشاط">نشاط</option>
                                        <option value="نتائج">نتائج</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ترتيب العرض</label>
                                    <input type="number" name="display_order[]" class="form-control" value="${i}">
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">وصف الصورة</label>
                                <input type="text" name="image_description[]" class="form-control" placeholder="وصف اختياري للصورة">
                            </div>
                        </div>
                    `;
                    selectedImages.appendChild(imageDiv);
                };
                
                reader.readAsDataURL(file);
            }
        }

        // عرض الصورة في modal
        function viewImage(imagePath) {
            document.getElementById('modalImage').src = '../public/' + imagePath;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        // حذف الصورة
        function deleteImage(imageId, imageName) {
            if (confirm('هل أنت متأكد من حذف الصورة: ' + imageName + '؟')) {
                document.getElementById('deleteImageId').value = imageId;
                document.getElementById('deleteForm').submit();
            }
        }

        // تفعيل ترتيب الصور
        function enableSorting() {
            const container = document.getElementById('imagesContainer');
            const saveBtn = document.getElementById('saveOrderBtn');
            
            Sortable.create(container, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function(evt) {
                    updateOrder();
                    saveBtn.classList.remove('d-none');
                }
            });
            
            document.querySelector('[onclick="enableSorting()"]').style.display = 'none';
        }

        function updateOrder() {
            const items = document.querySelectorAll('.sortable');
            items.forEach((item, index) => {
                const input = item.querySelector('.order-input');
                input.value = index;
            });
        }
    </script>
</body>
</html> 