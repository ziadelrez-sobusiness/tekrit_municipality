<?php
/**
 * صفحة تتبع الطلبات المتقدمة
 * تدعم عرض مراحل العمل والخط الزمني التفصيلي
 */

require_once '../config/database.php';
require_once '../includes/Utils.php';

$database = new Database();
$db = $database->getConnection();

$trackingNumber = '';
$request = null;
$stages = [];
$formData = [];
$documents = [];
$updates = [];
$error = '';

// معالجة البحث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_request'])) {
    $trackingNumber = Utils::sanitizeString($_POST['tracking_number'] ?? '');
    
    if (empty($trackingNumber)) {
        $error = 'يرجى إدخال رقم التتبع';
    } else {
        try {
            // جلب بيانات الطلب الأساسية
            $stmt = $db->prepare("
                SELECT cr.*, rt.name_ar as request_type_name, rt.form_fields, rt.required_documents,
                       DATEDIFF(NOW(), cr.created_at) as days_since_created
                FROM citizen_requests cr
                LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                WHERE cr.tracking_number = ?
            ");
            $stmt->execute([$trackingNumber]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                $error = 'رقم التتبع غير صحيح أو غير موجود';
            } else {
                // جلب مراحل العمل إذا كانت متوفرة
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            rws.stage_name,
                            rws.stage_description,
                            rws.stage_order,
                            rws.max_duration_days,
                            rst.status,
                            rst.started_at,
                            rst.completed_at,
                            rst.notes,
                            rst.rejection_reason,
                            u.full_name as assigned_to_name,
                            CASE 
                                WHEN rst.status = 'in_progress' AND rst.started_at IS NOT NULL 
                                THEN DATEDIFF(NOW(), rst.started_at)
                                ELSE NULL 
                            END as days_in_stage
                        FROM request_workflow_stages rws
                        LEFT JOIN request_stage_tracking rst ON rws.id = rst.stage_id AND rst.request_id = ?
                        LEFT JOIN users u ON rst.assigned_to = u.id
                        WHERE rws.request_type_id = ?
                        ORDER BY rws.stage_order
                    ");
                    $stmt->execute([$request['id'], $request['request_type_id']]);
                    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // تجاهل الخطأ إذا كانت الجداول غير موجودة
                }
                
                // جلب بيانات النموذج الديناميكية
                try {
                    $stmt = $db->prepare("
                        SELECT field_name, field_value 
                        FROM request_form_data 
                        WHERE request_id = ?
                    ");
                    $stmt->execute([$request['id']]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $formData[$row['field_name']] = $row['field_value'];
                    }
                } catch (Exception $e) {
                    // تجاهل الخطأ
                }
                
                // جلب المستندات المرفقة
                try {
                    $stmt = $db->prepare("
                        SELECT document_name, original_filename, file_path, file_size, uploaded_at 
                        FROM request_documents 
                        WHERE request_id = ? 
                        ORDER BY uploaded_at DESC
                    ");
                    $stmt->execute([$request['id']]);
                    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // تجاهل الخطأ
                }
                
                // جلب التحديثات
                try {
                    $stmt = $db->prepare("
                        SELECT ru.*, u.full_name as updated_by_name 
                        FROM request_updates ru
                        LEFT JOIN users u ON ru.updated_by = u.id
                        WHERE ru.request_id = ? AND ru.is_visible_to_citizen = 1
                        ORDER BY ru.created_at DESC
                    ");
                    $stmt->execute([$request['id']]);
                    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // تجاهل الخطأ
                }
            }
            
        } catch (Exception $e) {
            $error = 'حدث خطأ في البحث: ' . $e->getMessage();
        }
    }
}

// دالة للحصول على أيقونة الحالة
function getStatusIcon($status) {
    switch ($status) {
        case 'pending': return '<i class="fas fa-clock text-warning"></i>';
        case 'in_progress': return '<i class="fas fa-spinner fa-spin text-primary"></i>';
        case 'completed': return '<i class="fas fa-check-circle text-success"></i>';
        case 'rejected': return '<i class="fas fa-times-circle text-danger"></i>';
        case 'on_hold': return '<i class="fas fa-pause-circle text-warning"></i>';
        default: return '<i class="fas fa-question-circle text-muted"></i>';
    }
}

// دالة للحصول على نص الحالة
function getStatusText($status) {
    switch ($status) {
        case 'pending': return 'في الانتظار';
        case 'in_progress': return 'قيد التنفيذ';
        case 'completed': return 'مكتملة';
        case 'rejected': return 'مرفوضة';
        case 'on_hold': return 'معلقة';
        default: return 'غير محدد';
    }
}

// دالة لحساب نسبة التقدم
function calculateProgress($stages) {
    if (empty($stages)) return 0;
    
    $totalStages = count($stages);
    $completedStages = 0;
    
    foreach ($stages as $stage) {
        if ($stage['status'] === 'completed') {
            $completedStages++;
        }
    }
    
    return round(($completedStages / $totalStages) * 100);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تتبع الطلب - بلدية تكريت</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
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
        
        .search-section {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .content-section {
            padding: 30px;
        }
        
        .tracking-form {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1.1em;
            text-align: center;
            letter-spacing: 1px;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .request-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .status-badge {
            font-size: 1.1em;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .progress-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .progress {
            height: 20px;
            border-radius: 10px;
        }
        
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-item {
            position: relative;
            padding: 20px 0 20px 60px;
            border-right: 3px solid #e9ecef;
        }
        
        .timeline-item:last-child {
            border-right: none;
        }
        
        .timeline-icon {
            position: absolute;
            right: -15px;
            top: 25px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #e9ecef;
        }
        
        .timeline-item.completed .timeline-icon {
            border-color: #28a745;
            background: #28a745;
            color: white;
        }
        
        .timeline-item.in-progress .timeline-icon {
            border-color: #007bff;
            background: #007bff;
            color: white;
        }
        
        .timeline-item.pending .timeline-icon {
            border-color: #ffc107;
            background: #ffc107;
            color: white;
        }
        
        .timeline-item.rejected .timeline-icon {
            border-color: #dc3545;
            background: #dc3545;
            color: white;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            border-right: 4px solid #e9ecef;
        }
        
        .timeline-item.completed .timeline-content {
            border-right-color: #28a745;
        }
        
        .timeline-item.in-progress .timeline-content {
            border-right-color: #007bff;
        }
        
        .timeline-item.pending .timeline-content {
            border-right-color: #ffc107;
        }
        
        .timeline-item.rejected .timeline-content {
            border-right-color: #dc3545;
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-right: 4px solid #3498db;
        }
        
        .documents-list {
            list-style: none;
            padding: 0;
        }
        
        .documents-list li {
            background: #f8f9fa;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            border-right: 3px solid #3498db;
        }
        
        .update-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border-right: 3px solid #17a2b8;
        }
        
        @media (max-width: 768px) {
            .content-section, .search-section {
                padding: 20px;
            }
            
            .timeline-item {
                padding-right: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <!-- Header -->
            <div class="header-section">
                <h1><i class="fas fa-search me-3"></i>تتبع الطلب</h1>
                <p class="mb-0">متابعة حالة طلبك خطوة بخطوة</p>
            </div>
            
            <!-- Search Section -->
            <div class="search-section">
                <form method="POST" class="tracking-form">
                    <div class="form-group mb-3">
                        <label class="form-label text-center d-block mb-3">
                            <i class="fas fa-barcode me-2"></i>أدخل رقم التتبع
                        </label>
                        <input type="text" name="tracking_number" class="form-control" 
                               value="<?= htmlspecialchars($trackingNumber) ?>" 
                               placeholder="REQ2025-XXXX" required>
                    </div>
                    <div class="text-center">
                        <button type="submit" name="track_request" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>تتبع الطلب
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Content Section -->
            <div class="content-section">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php elseif ($request): ?>
                    <!-- معلومات الطلب الأساسية -->
                    <div class="request-info">
                        <div class="row">
                            <div class="col-md-8">
                                <h3 class="mb-3">
                                    <i class="fas fa-file-alt me-2 text-primary"></i>
                                    <?= htmlspecialchars($request['request_title']) ?>
                                </h3>
                                <p class="mb-2"><strong>نوع الطلب:</strong> <?= htmlspecialchars($request['request_type_name']) ?></p>
                                <p class="mb-2"><strong>مقدم الطلب:</strong> <?= htmlspecialchars($request['citizen_name']) ?></p>
                                <p class="mb-2"><strong>تاريخ التقديم:</strong> <?= date('Y-m-d H:i', strtotime($request['created_at'])) ?></p>
                                <p class="mb-0"><strong>منذ:</strong> <?= $request['days_since_created'] ?> يوم</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <?php
                                    $statusClass = '';
                                    switch ($request['status']) {
                                        case 'جديد': $statusClass = 'bg-info text-white'; break;
                                        case 'قيد المراجعة': $statusClass = 'bg-warning text-dark'; break;
                                        case 'قيد التنفيذ': $statusClass = 'bg-primary text-white'; break;
                                        case 'مكتمل': $statusClass = 'bg-success text-white'; break;
                                        case 'مرفوض': $statusClass = 'bg-danger text-white'; break;
                                        case 'معلق': $statusClass = 'bg-secondary text-white'; break;
                                        default: $statusClass = 'bg-light text-dark';
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($request['status']) ?>
                                    </span>
                                </div>
                                <div>
                                    <strong>رقم التتبع:</strong><br>
                                    <code style="font-size: 1.1em;"><?= htmlspecialchars($request['tracking_number']) ?></code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- مراحل العمل -->
                    <?php if (!empty($stages)): ?>
                        <div class="progress-section">
                            <h4 class="mb-3">
                                <i class="fas fa-tasks me-2 text-primary"></i>مراحل معالجة الطلب
                            </h4>
                            
                            <?php $progress = calculateProgress($stages); ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>نسبة الإنجاز</span>
                                    <span><strong><?= $progress ?>%</strong></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="timeline">
                                <?php foreach ($stages as $stage): ?>
                                    <?php
                                    $stageClass = 'pending';
                                    if ($stage['status'] === 'completed') $stageClass = 'completed';
                                    elseif ($stage['status'] === 'in_progress') $stageClass = 'in-progress';
                                    elseif ($stage['status'] === 'rejected') $stageClass = 'rejected';
                                    ?>
                                    <div class="timeline-item <?= $stageClass ?>">
                                        <div class="timeline-icon">
                                            <?php if ($stage['status'] === 'completed'): ?>
                                                <i class="fas fa-check"></i>
                                            <?php elseif ($stage['status'] === 'in_progress'): ?>
                                                <i class="fas fa-cog fa-spin"></i>
                                            <?php elseif ($stage['status'] === 'rejected'): ?>
                                                <i class="fas fa-times"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-2"><?= htmlspecialchars($stage['stage_name']) ?></h6>
                                            <p class="mb-2 text-muted"><?= htmlspecialchars($stage['stage_description']) ?></p>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">
                                                        <strong>الحالة:</strong> <?= getStatusText($stage['status']) ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-6">
                                                    <?php if ($stage['max_duration_days']): ?>
                                                        <small class="text-muted">
                                                            <strong>المدة المتوقعة:</strong> <?= $stage['max_duration_days'] ?> يوم
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($stage['started_at']): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-play me-1"></i>
                                                        بدأت في: <?= date('Y-m-d H:i', strtotime($stage['started_at'])) ?>
                                                    </small>
                                                    <?php if ($stage['days_in_stage']): ?>
                                                        <small class="text-muted ms-3">
                                                            (منذ <?= $stage['days_in_stage'] ?> يوم)
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['completed_at']): ?>
                                                <div class="mt-2">
                                                    <small class="text-success">
                                                        <i class="fas fa-check me-1"></i>
                                                        اكتملت في: <?= date('Y-m-d H:i', strtotime($stage['completed_at'])) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['assigned_to_name']): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        المسؤول: <?= htmlspecialchars($stage['assigned_to_name']) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['notes']): ?>
                                                <div class="mt-2">
                                                    <small class="text-info">
                                                        <i class="fas fa-comment me-1"></i>
                                                        <?= htmlspecialchars($stage['notes']) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($stage['rejection_reason']): ?>
                                                <div class="mt-2">
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        سبب الرفض: <?= htmlspecialchars($stage['rejection_reason']) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- تفاصيل الطلب -->
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="fas fa-info-circle me-2 text-primary"></i>تفاصيل الطلب
                        </h5>
                        <p><?= nl2br(htmlspecialchars($request['request_description'])) ?></p>
                        
                        <?php if (!empty($formData)): ?>
                            <h6 class="mt-4 mb-3">معلومات إضافية:</h6>
                            <div class="row">
                                <?php foreach ($formData as $fieldName => $fieldValue): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong><?= htmlspecialchars($fieldName) ?>:</strong> 
                                        <?= htmlspecialchars($fieldValue) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- المستندات المرفقة -->
                    <?php if (!empty($documents)): ?>
                        <div class="info-card">
                            <h5 class="mb-3">
                                <i class="fas fa-paperclip me-2 text-primary"></i>المستندات المرفقة
                            </h5>
                            <ul class="documents-list">
                                <?php foreach ($documents as $doc): ?>
                                    <li>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-file me-2 text-primary"></i>
                                                <strong><?= htmlspecialchars($doc['original_filename']) ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">
                                                    <?= date('Y-m-d', strtotime($doc['uploaded_at'])) ?>
                                                    | <?= number_format($doc['file_size'] / 1024, 1) ?> KB
                                                </small>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- التحديثات -->
                    <?php if (!empty($updates)): ?>
                        <div class="info-card">
                            <h5 class="mb-3">
                                <i class="fas fa-history me-2 text-primary"></i>تحديثات الطلب
                            </h5>
                            <?php foreach ($updates as $update): ?>
                                <div class="update-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <p class="mb-1"><?= htmlspecialchars($update['update_text']) ?></p>
                                            <?php if ($update['updated_by_name']): ?>
                                                <small class="text-muted">
                                                    بواسطة: <?= htmlspecialchars($update['updated_by_name']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('Y-m-d H:i', strtotime($update['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- أزرار الإجراءات -->
                    <div class="text-center mt-4">
                        <a href="citizen-requests-advanced.php" class="btn btn-primary me-3">
                            <i class="fas fa-plus me-2"></i>طلب جديد
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>الرئيسية
                        </a>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 