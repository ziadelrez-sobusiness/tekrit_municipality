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

if (!$initiative_id) {
    header("Location: initiatives.php");
    exit();
}

// جلب بيانات المبادرة
$stmt = $db->prepare("SELECT * FROM youth_environmental_initiatives WHERE id = ?");
$stmt->execute([$initiative_id]);
$initiative = $stmt->fetch();

if (!$initiative) {
    header("Location: initiatives.php");
    exit();
}

// معالجة تحديث المبادرة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_initiative'])) {
    $initiative_name = trim($_POST['initiative_name'] ?? '');
    $initiative_description = trim($_POST['initiative_description'] ?? '');
    $initiative_type = trim($_POST['initiative_type'] ?? '');
    $max_volunteers = (int)($_POST['max_volunteers'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $registration_deadline = $_POST['registration_deadline'] ?? null;
    $budget = (float)($_POST['budget'] ?? 0);
    $requirements = trim($_POST['requirements'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $auto_approval = isset($_POST['auto_approval']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($initiative_name) || empty($initiative_description) || empty($initiative_type)) {
        $error = "يرجى ملء جميع الحقول المطلوبة";
    } else {
        try {
            // تحديث المبادرة
            $stmt = $db->prepare("
                UPDATE youth_environmental_initiatives 
                SET initiative_name = ?, initiative_description = ?, initiative_type = ?, 
                    max_volunteers = ?, location = ?, registration_deadline = ?, budget = ?, 
                    requirements = ?, benefits = ?, auto_approval = ?, is_active = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $initiative_name, $initiative_description, $initiative_type, $max_volunteers,
                $location, $registration_deadline, $budget, $requirements, $benefits,
                $auto_approval, $is_active, $initiative_id
            ])) {
                $message = "تم تحديث المبادرة بنجاح";
                
                // إعادة جلب البيانات المحدثة
                $stmt = $db->prepare("SELECT * FROM youth_environmental_initiatives WHERE id = ?");
                $stmt->execute([$initiative_id]);
                $initiative = $stmt->fetch();
            } else {
                $error = "حدث خطأ في تحديث المبادرة";
            }
        } catch (Exception $e) {
            $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

// جلب أنواع المبادرات الموجودة
$types = $db->query("SELECT DISTINCT initiative_type FROM youth_environmental_initiatives ORDER BY initiative_type")->fetchAll();

// جلب إحصائيات المبادرة
$stmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM initiative_volunteers WHERE initiative_id = ? AND status = 'مقبول') as registered_volunteers,
        (SELECT COUNT(*) FROM initiative_images WHERE initiative_id = ? AND is_active = 1) as image_count
");
$stmt->execute([$initiative_id, $initiative_id]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث المبادرة - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
                    <h1 class="h2">تحديث المبادرة</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="initiatives.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-right"></i> العودة للمبادرات
                        </a>
                        <a href="manage_initiative_images.php?id=<?= $initiative_id ?>" class="btn btn-outline-primary">
                            <i class="fas fa-images"></i> إدارة الصور
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

                <form method="POST">
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
                                        <input type="text" name="initiative_name" class="form-control" 
                                               value="<?= htmlspecialchars($initiative['initiative_name']) ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">وصف المبادرة *</label>
                                        <textarea name="initiative_description" class="form-control" rows="5" required><?= htmlspecialchars($initiative['initiative_description']) ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">نوع المبادرة *</label>
                                                <select name="initiative_type" class="form-select" required>
                                                    <option value="">اختر النوع</option>
                                                    <option value="بيئية" <?= $initiative['initiative_type'] === 'بيئية' ? 'selected' : '' ?>>بيئية</option>
                                                    <option value="شبابية" <?= $initiative['initiative_type'] === 'شبابية' ? 'selected' : '' ?>>شبابية</option>
                                                    <option value="مجتمعية" <?= $initiative['initiative_type'] === 'مجتمعية' ? 'selected' : '' ?>>مجتمعية</option>
                                                    <option value="ثقافية" <?= $initiative['initiative_type'] === 'ثقافية' ? 'selected' : '' ?>>ثقافية</option>
                                                    <option value="تعليمية" <?= $initiative['initiative_type'] === 'تعليمية' ? 'selected' : '' ?>>تعليمية</option>
                                                    <?php foreach ($types as $type): ?>
                                                        <option value="<?= htmlspecialchars($type['initiative_type']) ?>" 
                                                                <?= $initiative['initiative_type'] === $type['initiative_type'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($type['initiative_type']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">عدد المتطوعين المطلوب</label>
                                                <input type="number" name="max_volunteers" class="form-control" min="1" 
                                                       value="<?= $initiative['max_volunteers'] ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">الموقع</label>
                                                <input type="text" name="location" class="form-control" 
                                                       value="<?= htmlspecialchars($initiative['location']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">آخر موعد للتسجيل</label>
                                                <input type="date" name="registration_deadline" class="form-control" 
                                                       value="<?= $initiative['registration_deadline'] ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">الميزانية (دينار)</label>
                                        <input type="number" name="budget" class="form-control" step="0.01" min="0" 
                                               value="<?= $initiative['budget'] ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">المتطلبات</label>
                                        <textarea name="requirements" class="form-control" rows="3" 
                                                  placeholder="اذكر المتطلبات والشروط للانضمام"><?= htmlspecialchars($initiative['requirements']) ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">المزايا والفوائد</label>
                                        <textarea name="benefits" class="form-control" rows="3" 
                                                  placeholder="اذكر الفوائد التي سيحصل عليها المتطوعون"><?= htmlspecialchars($initiative['benefits']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الإعدادات والإحصائيات -->
                        <div class="col-lg-4">
                            <!-- إحصائيات -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-bar"></i> إحصائيات المبادرة</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="border-end">
                                                <h4 class="text-primary"><?= $stats['registered_volunteers'] ?></h4>
                                                <small class="text-muted">متطوع مسجل</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <h4 class="text-info"><?= $stats['image_count'] ?></h4>
                                            <small class="text-muted">صورة</small>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= $initiative['max_volunteers'] > 0 ? ($stats['registered_volunteers'] / $initiative['max_volunteers']) * 100 : 0 ?>%">
                                                <?= $initiative['max_volunteers'] > 0 ? round(($stats['registered_volunteers'] / $initiative['max_volunteers']) * 100) : 0 ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">نسبة اكتمال التسجيل</small>
                                    </div>
                                </div>
                            </div>

                            <!-- الإعدادات -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-cog"></i> إعدادات المبادرة</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                                   <?= $initiative['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">
                                                تفعيل المبادرة
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="auto_approval" id="auto_approval" 
                                                   <?= $initiative['auto_approval'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="auto_approval">
                                                الموافقة التلقائية على التسجيل
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_initiative" class="btn btn-primary">
                                            <i class="fas fa-save"></i> حفظ التحديثات
                                        </button>
                                        <a href="manage_initiative_images.php?id=<?= $initiative_id ?>" class="btn btn-outline-success">
                                            <i class="fas fa-images"></i> إدارة الصور
                                        </a>
                                    </div>

                                    <hr>
                                    
                                    <div class="small text-muted">
                                        <div><strong>تاريخ الإنشاء:</strong><br><?= date('Y/m/d H:i', strtotime($initiative['created_at'])) ?></div>
                                        <?php if ($initiative['updated_at']): ?>
                                            <div class="mt-2"><strong>آخر تحديث:</strong><br><?= date('Y/m/d H:i', strtotime($initiative['updated_at'])) ?></div>
                                        <?php endif; ?>
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
</body>
</html> 