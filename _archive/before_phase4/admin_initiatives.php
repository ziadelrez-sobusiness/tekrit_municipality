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

$message = '';
$error = '';

// معالجة حذف المبادرة
if (isset($_POST['delete_initiative'])) {
    $initiative_id = (int)$_POST['initiative_id'];
    
    try {
        // حذف الصور المرتبطة
        $stmt = $db->prepare("SELECT image_path FROM initiative_images WHERE initiative_id = ?");
        $stmt->execute([$initiative_id]);
        $images = $stmt->fetchAll();
        
        foreach ($images as $image) {
            $file_path = '../public/' . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // حذف المبادرة (سيتم حذف الصور تلقائياً بسبب CASCADE)
        $stmt = $db->prepare("DELETE FROM youth_environmental_initiatives WHERE id = ?");
        $stmt->execute([$initiative_id]);
        
        $message = "تم حذف المبادرة بنجاح";
    } catch (Exception $e) {
        $error = "خطأ في حذف المبادرة: " . $e->getMessage();
    }
}

// معالجة تغيير حالة المبادرة
if (isset($_POST['toggle_status'])) {
    $initiative_id = (int)$_POST['initiative_id'];
    $new_status = (int)$_POST['new_status'];
    
    $stmt = $db->prepare("UPDATE youth_environmental_initiatives SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_status, $initiative_id]);
    
    $message = $new_status ? "تم تفعيل المبادرة" : "تم إلغاء تفعيل المبادرة";
}

// جلب المبادرات مع عدد الصور
$stmt = $db->query("
    SELECT i.*, 
           COUNT(img.id) as image_count,
           (SELECT COUNT(*) FROM initiative_volunteers WHERE initiative_id = i.id AND status = 'مقبول') as registered_volunteers
    FROM youth_environmental_initiatives i
    LEFT JOIN initiative_images img ON i.id = img.initiative_id
    GROUP BY i.id
    ORDER BY i.created_at DESC
");
$initiatives = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المبادرات - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .initiative-card { transition: transform 0.2s; }
        .initiative-card:hover { transform: translateY(-2px); }
        .status-badge { font-size: 0.8em; }
        .image-count { background: #e9ecef; border-radius: 15px; padding: 2px 8px; font-size: 0.8em; }
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
                        <li class="nav-item">
                            <a class="nav-link" href="projects.php">
                                <i class="fas fa-project-diagram"></i> المشاريع
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> المستخدمين
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">إدارة المبادرات</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_initiative.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> إضافة مبادرة جديدة
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

                <!-- إحصائيات سريعة -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?php echo count($initiatives); ?></h5>
                                <p class="card-text">إجمالي المبادرات</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success">
                                    <?php echo count(array_filter($initiatives, function($i) { return $i['is_active']; })); ?>
                                </h5>
                                <p class="card-text">المبادرات النشطة</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-info">
                                    <?php echo array_sum(array_column($initiatives, 'registered_volunteers')); ?>
                                </h5>
                                <p class="card-text">إجمالي المتطوعين</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-warning">
                                    <?php echo array_sum(array_column($initiatives, 'image_count')); ?>
                                </h5>
                                <p class="card-text">إجمالي الصور</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- قائمة المبادرات -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> قائمة المبادرات</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($initiatives)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-lightbulb fa-3x text-muted mb-3"></i>
                                <p class="text-muted">لا توجد مبادرات مضافة حتى الآن</p>
                                <a href="add_initiative.php" class="btn btn-primary">إضافة أول مبادرة</a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($initiatives as $initiative): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card initiative-card h-100">
                                            <?php if ($initiative['main_image']): ?>
                                                <img src="../public/<?php echo htmlspecialchars($initiative['main_image']); ?>" 
                                                     class="card-img-top" style="height: 200px; object-fit: cover;" 
                                                     alt="<?php echo htmlspecialchars($initiative['initiative_name']); ?>">
                                            <?php else: ?>
                                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                                     style="height: 200px;">
                                                    <i class="fas fa-image fa-3x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($initiative['initiative_name']); ?></h6>
                                                    <span class="badge <?php echo $initiative['is_active'] ? 'bg-success' : 'bg-secondary'; ?> status-badge">
                                                        <?php echo $initiative['is_active'] ? 'نشطة' : 'غير نشطة'; ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="card-text small text-muted flex-grow-1">
                                                    <?php echo mb_substr(htmlspecialchars($initiative['initiative_description']), 0, 100) . '...'; ?>
                                                </p>
                                                
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                                        <span>المتطوعين: <?php echo $initiative['registered_volunteers']; ?>/<?php echo $initiative['max_volunteers']; ?></span>
                                                        <span class="image-count">
                                                            <i class="fas fa-images"></i> <?php echo $initiative['image_count']; ?>
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height: 5px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?php echo ($initiative['registered_volunteers'] / max($initiative['max_volunteers'], 1)) * 100; ?>%">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="btn-group w-100">
                                                    <a href="manage_initiative_images.php?id=<?php echo $initiative['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-images"></i> الصور
                                                    </a>
                                                    <a href="edit_initiative.php?id=<?php echo $initiative['id']; ?>" 
                                                       class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-edit"></i> تعديل
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteInitiative(<?php echo $initiative['id']; ?>, '<?php echo htmlspecialchars($initiative['initiative_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm w-100 <?php echo $initiative['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                                            onclick="toggleStatus(<?php echo $initiative['id']; ?>, <?php echo $initiative['is_active'] ? 0 : 1; ?>)">
                                                        <i class="fas fa-<?php echo $initiative['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        <?php echo $initiative['is_active'] ? 'إيقاف' : 'تفعيل'; ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Forms للعمليات -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="delete_initiative" value="1">
        <input type="hidden" name="initiative_id" id="deleteInitiativeId">
    </form>

    <form method="POST" id="statusForm" style="display: none;">
        <input type="hidden" name="toggle_status" value="1">
        <input type="hidden" name="initiative_id" id="statusInitiativeId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteInitiative(id, name) {
            if (confirm('هل أنت متأكد من حذف المبادرة: ' + name + '؟\nسيتم حذف جميع الصور والبيانات المرتبطة بها.')) {
                document.getElementById('deleteInitiativeId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function toggleStatus(id, newStatus) {
            const action = newStatus ? 'تفعيل' : 'إيقاف';
            if (confirm('هل أنت متأكد من ' + action + ' هذه المبادرة؟')) {
                document.getElementById('statusInitiativeId').value = id;
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }
    </script>
</body>
</html> 