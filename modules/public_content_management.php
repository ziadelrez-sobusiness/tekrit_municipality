<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/currency_formatter.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../comprehensive_dashboard.php?error=no_permission');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$success_message = '';
$error_message = '';

// معالجة طلبات AJAX
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] == 'get_project' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        $project_id = $_GET['id'];
        
        try {
            $stmt = $db->prepare("SELECT * FROM development_projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($project) {
                // معالجة تاريخ البدء قبل إرسال JSON
                if (empty($project['start_date']) || $project['start_date'] === '0000-00-00') {
                    $project['start_date'] = null;
                }
                
                echo json_encode($project);
            } else {
                echo json_encode(['error' => 'Project not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }


if ($_GET['ajax'] == 'get_initiative' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $initiative_id = $_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM youth_environmental_initiatives WHERE id = ?");
        $stmt->execute([$initiative_id]);
        $initiative = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($initiative) {
            echo json_encode($initiative);
        } else {
            echo json_encode(['error' => 'Initiative not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

}

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // إضافة خبر جديد
    if ($action == 'add_news') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $news_type = $_POST['news_type'];
        $publish_date = $_POST['publish_date'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // تضمين فئة إدارة الصور الجديدة
        require_once 'news_image_manager.php';
        $imageManager = new NewsImageManager($db);
        
        if (!empty($title) && !empty($content)) {
            try {
                $db->beginTransaction();
                
                // إضافة الخبر أولاً
                $stmt = $db->prepare("INSERT INTO news_activities (title, content, news_type, publish_date, is_featured, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $news_type, $publish_date, $is_featured, $auth->getCurrentUser()['id']]);
                $news_id = $db->lastInsertId();
                
                $messages = [];
                
                // رفع الصورة الرئيسية
                if (!empty($_FILES['featured_image']['name'])) {
                    $result = $imageManager->uploadFeaturedImage($_FILES['featured_image'], $news_id);
                    if (!$result['success']) {
                        $messages[] = "فشل رفع الصورة الرئيسية: " . $result['error'];
                    }
                }
                
                // رفع صور المعرض
                if (!empty($_FILES['gallery_images']['name'][0])) {
                    $result = $imageManager->uploadGalleryImages($_FILES['gallery_images'], $news_id, $auth->getCurrentUser()['id']);
                    if (!empty($result['errors'])) {
                        $messages[] = "أخطاء في صور المعرض: " . implode(', ', $result['errors']);
                    }
                    if ($result['total_uploaded'] > 0) {
                        $messages[] = "تم رفع {$result['total_uploaded']} صورة للمعرض";
                    }
                }
                
                $db->commit();
                $success_message = "تم إضافة الخبر بنجاح" . (empty($messages) ? "" : ". " . implode(". ", $messages));
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=news&success=1");
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "خطأ في إضافة الخبر: " . $e->getMessage();
            }
        }
    }
    
    // تعديل خبر
    elseif ($action == 'edit_news') {
        $news_id = $_POST['news_id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $news_type = $_POST['news_type'];
        $publish_date = $_POST['publish_date'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // تضمين فئة إدارة الصور الجديدة
        require_once 'news_image_manager.php';
        $imageManager = new NewsImageManager($db);
        
        // جلب البيانات الحالية
        $current_stmt = $db->prepare("SELECT featured_image FROM news_activities WHERE id = ?");
        $current_stmt->execute([$news_id]);
        $current_data = $current_stmt->fetch();
        
        $featured_image = $current_data['featured_image'];
        
        // إنشاء مجلد الصور إذا لم يكن موجوداً
        $upload_dir = '../uploads/news/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // معالجة الصورة الرئيسية الجديدة
        if (!empty($_FILES['featured_image']['name'])) {
            $result = $imageManager->uploadFeaturedImage($_FILES['featured_image'], $news_id);
            if (!$result['success']) {
                $error_message = "فشل تحديث الصورة الرئيسية: " . $result['error'];
            } else {
                $featured_image = $result['filename'];
            }
        }
        
        // معالجة صور المعرض الجديدة
        if (!empty($_FILES['gallery_images']['name'][0])) {
            $result = $imageManager->uploadGalleryImages($_FILES['gallery_images'], $news_id, $auth->getCurrentUser()['id']);
            if (!empty($result['errors'])) {
                $error_message = $error_message ? $error_message . " | أخطاء في صور المعرض: " . implode(', ', $result['errors']) : "أخطاء في صور المعرض: " . implode(', ', $result['errors']);
            }
        }
        
        // حذف صور المعرض المحددة
        if (!empty($_POST['delete_gallery_images'])) {
            foreach ($_POST['delete_gallery_images'] as $image_id) {
                $result = $imageManager->deleteImage($image_id, $auth->getCurrentUser()['id']);
                if (!$result['success']) {
                    $error_message = $error_message ? $error_message . " | فشل حذف صورة" : "فشل حذف بعض الصور";
                }
            }
        }
        
        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $db->prepare("UPDATE news_activities SET title = ?, content = ?, news_type = ?, publish_date = ?, is_featured = ?, featured_image = ? WHERE id = ?");
                $stmt->execute([$title, $content, $news_type, $publish_date, $is_featured, $featured_image, $news_id]);
                $success_message = $error_message ? "تم تحديث الخبر مع تحذيرات: " . $error_message : "تم تحديث الخبر بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=news&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث الخبر: " . $e->getMessage();
            }
        }
    }
    
    // إضافة مشروع جديد
    elseif ($action == 'add_project') {
    $name = trim($_POST['project_name']);
    $description = trim($_POST['project_description']);
    $goal = trim($_POST['project_goal']);
    $location = trim($_POST['project_location']);
    $cost = $_POST['project_base_cost'] ?: 0;
    $duration = trim($_POST['project_duration']);
    $beneficiaries = $_POST['beneficiaries_count'];
    $status = $_POST['project_status'];
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $responsible_dept = $_POST['responsible_department_id'] ?: null;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $allow_contributions = isset($_POST['allow_contributions']) ? 1 : 0;
    
    // نظام التمويل المتقدم
    $project_base_cost = $_POST['project_base_cost'] ?: $cost;
    $project_base_cost_currency_id = $_POST['project_base_cost_currency_id'] ?: $default_currency_id;
    
    $municipality_contribution_amount = $_POST['municipality_contribution_amount'] ?: 0;
    $municipality_contribution_currency_id = $_POST['municipality_contribution_currency_id'] ?: $default_currency_id;
    
    $donor_contribution_amount = $_POST['donor_contribution_amount'] ?: 0;
    $donor_contribution_currency_id = $_POST['donor_contribution_currency_id'] ?: $default_currency_id;
    
    $donors_contribution_amount = $_POST['donors_contribution_amount'] ?: 0;
    $donors_contribution_currency_id = $_POST['donors_contribution_currency_id'] ?: $default_currency_id;
    $donors_list = trim($_POST['donors_list']);
    
    $is_municipality_project = isset($_POST['is_municipality_project']) ? 1 : 0;
    $donor_organization = trim($_POST['donor_organization']);
    $funding_source = $_POST['funding_source'] ?: 'بلدية';
    $funding_notes = trim($_POST['funding_notes']);
    
    // الحقول القديمة للتوافق
    $currency_id = $_POST['currency_id'] ?: $default_currency_id;
    $total_project_cost = $project_base_cost;
    
    // حساب التمويل مباشرة في PHP
    $total_contributions_iqd = $municipality_contribution_amount + $donor_contribution_amount + $donors_contribution_amount;
    $remaining_cost_iqd = $project_base_cost - $total_contributions_iqd;
    $funding_completion_percentage = $project_base_cost > 0 ? min(($total_contributions_iqd / $project_base_cost) * 100, 100) : 0;
    
    if (!empty($name) && !empty($description) && !empty($goal)) {
        try {
            $stmt = $db->prepare("INSERT INTO development_projects (
                project_name, project_description, project_goal, project_location, 
                project_cost, project_duration, beneficiaries_count, project_status, 
                start_date, responsible_department_id, is_featured, allow_contributions, 
                currency_id, is_municipality_project, donor_organization, funding_source, 
                funding_notes, project_base_cost, project_base_cost_currency_id, 
                municipality_contribution_amount, municipality_contribution_currency_id, 
                donor_contribution_amount, donor_contribution_currency_id, 
                donors_contribution_amount, donors_contribution_currency_id, 
                donors_list, total_project_cost, total_contributions_iqd, 
                remaining_cost_iqd, funding_completion_percentage
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $name, $description, $goal, $location, $cost, $duration, 
                $beneficiaries, $status, $start_date, $responsible_dept, 
                $is_featured, $allow_contributions, $currency_id, 
                $is_municipality_project, $donor_organization, $funding_source, 
                $funding_notes, $project_base_cost, $project_base_cost_currency_id, 
                $municipality_contribution_amount, $municipality_contribution_currency_id, 
                $donor_contribution_amount, $donor_contribution_currency_id, 
                $donors_contribution_amount, $donors_contribution_currency_id, 
                $donors_list, $total_project_cost, $total_contributions_iqd, 
                $remaining_cost_iqd, $funding_completion_percentage
            ]);
            
            $success_message = "تم إضافة المشروع بنجاح";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=projects&success=1");
            exit();
        } catch (Exception $e) {
            $error_message = "خطأ في إضافة المشروع: " . $e->getMessage();
        }
    }
}
    
    // تعديل مشروع
    elseif ($action == 'edit_project') {
    $project_id = $_POST['project_id'];
    $name = trim($_POST['project_name']);
    $description = trim($_POST['project_description']);
    $goal = trim($_POST['project_goal']);
    $location = trim($_POST['project_location']);
    $duration = trim($_POST['project_duration']);
    $beneficiaries = $_POST['beneficiaries_count'];
    $status = $_POST['project_status'];
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $responsible_dept = $_POST['responsible_department_id'] ?: null;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $allow_contributions = isset($_POST['allow_contributions']) ? 1 : 0;
    
    // نظام التمويل المتقدم
    $project_base_cost = $_POST['project_base_cost'] ?: 0;
    $project_base_cost_currency_id = $_POST['project_base_cost_currency_id'] ?: $default_currency_id;
    
    $municipality_contribution_amount = $_POST['municipality_contribution_amount'] ?: 0;
    $municipality_contribution_currency_id = $_POST['municipality_contribution_currency_id'] ?: $default_currency_id;
    
    $donor_contribution_amount = $_POST['donor_contribution_amount'] ?: 0;
    $donor_contribution_currency_id = $_POST['donor_contribution_currency_id'] ?: $default_currency_id;
    
    $donors_contribution_amount = $_POST['donors_contribution_amount'] ?: 0;
    $donors_contribution_currency_id = $_POST['donors_contribution_currency_id'] ?: $default_currency_id;
    $donors_list = trim($_POST['donors_list']);
    
    $is_municipality_project = isset($_POST['is_municipality_project']) ? 1 : 0;
    $donor_organization = trim($_POST['donor_organization']);
    $funding_source = $_POST['funding_source'] ?: 'بلدية';
    $funding_notes = trim($_POST['funding_notes']);
    
    // الحقول القديمة للتوافق
    $currency_id = $_POST['currency_id'] ?: $default_currency_id;
    $project_cost = $project_base_cost;
    
    // حساب التمويل مباشرة في PHP
    $total_contributions_iqd = $municipality_contribution_amount + $donor_contribution_amount + $donors_contribution_amount;
    $remaining_cost_iqd = $project_base_cost - $total_contributions_iqd;
    $funding_completion_percentage = $project_base_cost > 0 ? min(($total_contributions_iqd / $project_base_cost) * 100, 100) : 0;
    
    if (!empty($name) && !empty($description) && !empty($goal)) {
        try {
            $stmt = $db->prepare("UPDATE development_projects SET 
                project_name = ?, project_description = ?, project_goal = ?, project_location = ?, 
                project_cost = ?, project_duration = ?, beneficiaries_count = ?, project_status = ?, 
                start_date = ?, responsible_department_id = ?, is_featured = ?, allow_contributions = ?, 
                currency_id = ?, project_base_cost = ?, project_base_cost_currency_id = ?, 
                is_municipality_project = ?, donor_organization = ?, funding_source = ?, 
                funding_notes = ?, municipality_contribution_amount = ?, 
                municipality_contribution_currency_id = ?, donor_contribution_amount = ?, 
                donor_contribution_currency_id = ?, donors_contribution_amount = ?, 
                donors_contribution_currency_id = ?, donors_list = ?, total_contributions_iqd = ?,
                remaining_cost_iqd = ?, funding_completion_percentage = ?, total_project_cost = ?
                  WHERE id = ?");
                
            $stmt->execute([
                $name, $description, $goal, $location, $project_cost, $duration, 
                $beneficiaries, $status, $start_date, $responsible_dept, 
                $is_featured, $allow_contributions, $currency_id, 
                $project_base_cost, $project_base_cost_currency_id, 
                $is_municipality_project, $donor_organization, $funding_source, 
                $funding_notes, $municipality_contribution_amount, 
                $municipality_contribution_currency_id, $donor_contribution_amount, 
                $donor_contribution_currency_id, $donors_contribution_amount, 
                $donors_contribution_currency_id, $donors_list, $total_contributions_iqd, 
                $remaining_cost_iqd, $funding_completion_percentage, $project_base_cost, 
                $project_id
            ]);
            
            $success_message = "تم تحديث المشروع بنجاح";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=projects&success=1");
            exit();
        } catch (Exception $e) {
            $error_message = "خطأ في تحديث المشروع: " . $e->getMessage();
        }
    }
}
    
    // إضافة مبادرة جديدة
    elseif ($action == 'add_initiative') {
        $name = trim($_POST['initiative_name']);
        $description = trim($_POST['initiative_description']);
        $type = $_POST['initiative_type'];
        $goals = trim($_POST['initiative_goals']);
        $requirements = trim($_POST['requirements']);
        $benefits = trim($_POST['benefits']);
        $target_audience = trim($_POST['target_audience']);
        $required_volunteers = $_POST['required_volunteers'] ?: null;
        $max_volunteers = $_POST['max_volunteers'] ?: 50;
        $initiative_status = $_POST['initiative_status'] ?: 'مفتوحة للتسجيل';
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        $registration_deadline = $_POST['registration_deadline'] ?: null;
        $coordinator_name = trim($_POST['coordinator_name']);
        $coordinator_phone = trim($_POST['coordinator_phone']);
        $coordinator_email = trim($_POST['coordinator_email']);
        $location = trim($_POST['location']);
        $budget = $_POST['budget'] ?: 0;
        $status = $_POST['status'] ?: 'مخطط';
        $success_story = trim($_POST['success_story']);
        $impact_description = trim($_POST['impact_description']);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $auto_approval = isset($_POST['auto_approval']) ? 1 : 0;
        
        if (!empty($name) && !empty($description)) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO youth_environmental_initiatives (
                    initiative_name, initiative_description, initiative_type, initiative_goals, 
                    requirements, benefits, target_audience, required_volunteers, max_volunteers, 
                    registered_volunteers, start_date, end_date, registration_deadline, 
                    initiative_status, coordinator_name, coordinator_phone, coordinator_email, 
                    location, budget, success_story, impact_description, is_featured, 
                    is_active, auto_approval, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $name, $description, $type, $goals, $requirements, $benefits, 
                    $target_audience, $required_volunteers, $max_volunteers, 
                    $start_date, $end_date, $registration_deadline, $initiative_status, 
                    $coordinator_name, $coordinator_phone, $coordinator_email, 
                    $location, $budget, $success_story, $impact_description, 
                    $is_featured, $is_active, $auto_approval, $status
                ]);
                
                $initiative_id = $db->lastInsertId();
                $main_image = null;
                $messages = [];
                
                // معالجة الصورة الرئيسية
                if (!empty($_FILES['main_image']['name'])) {
                    $upload_dir = '../uploads/initiatives/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_name = $_FILES['main_image']['name'];
                    $file_tmp = $_FILES['main_image']['tmp_name'];
                    $file_size = $_FILES['main_image']['size'];
                    $file_type = $_FILES['main_image']['type'];
                    
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = 'initiative_main_' . $initiative_id . '_' . time() . '.' . $file_extension;
                        $file_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $main_image = $unique_name;
                            $messages[] = "تم رفع الصورة الرئيسية";
                        }
                    }
                }
                
                // معالجة معرض الصور
                if (!empty($_FILES['gallery_images']['name'][0])) {
                    $upload_dir = '../uploads/initiatives/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_count = count($_FILES['gallery_images']['name']);
                    $uploaded_count = 0;
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['gallery_images']['name'][$i];
                            $file_tmp = $_FILES['gallery_images']['tmp_name'][$i];
                            $file_size = $_FILES['gallery_images']['size'][$i];
                            $file_type = $_FILES['gallery_images']['type'][$i];
                            
                            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                            if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                                $unique_name = 'initiative_' . $initiative_id . '_gallery_' . time() . '_' . $i . '.' . $file_extension;
                                $file_path = $upload_dir . $unique_name;
                                
                                if (move_uploaded_file($file_tmp, $file_path)) {
                                    // إدراج في جدول initiative_images
                                    $img_stmt = $db->prepare("INSERT INTO initiative_images 
                                        (initiative_id, image_path, image_name, image_type, display_order, file_size, uploaded_by) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $img_stmt->execute([
                                        $initiative_id, $unique_name, $file_name, 'معرض', $i, $file_size, $auth->getCurrentUser()['id']
                                    ]);
                                    $uploaded_count++;
                                }
                            }
                        }
                    }
                    
                    if ($uploaded_count > 0) {
                        $messages[] = "تم رفع $uploaded_count صورة للمعرض";
                    }
                }
                
                // تحديث الصورة الرئيسية
                if ($main_image) {
                    $update_stmt = $db->prepare("UPDATE youth_environmental_initiatives SET main_image = ? WHERE id = ?");
                    $update_stmt->execute([$main_image, $initiative_id]);
                }
                
                $db->commit();
                $success_message = "تم إضافة المبادرة بنجاح" . (empty($messages) ? "" : ". " . implode(". ", $messages));
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=initiatives&success=1");
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "خطأ في إضافة المبادرة: " . $e->getMessage();
            }
        } else {
            $error_message = "يرجى ملء الحقول المطلوبة (اسم المبادرة ووصفها)";
        }
    }
    
    // تعديل مبادرة
    elseif ($action == 'edit_initiative') {
        $initiative_id = $_POST['initiative_id'];
        $name = trim($_POST['initiative_name']);
        $description = trim($_POST['initiative_description']);
        $type = $_POST['initiative_type'];
        $goals = trim($_POST['initiative_goals']);
        $requirements = trim($_POST['requirements']);
        $benefits = trim($_POST['benefits']);
        $target_audience = trim($_POST['target_audience']);
        $required_volunteers = $_POST['required_volunteers'] ?: null;
        $max_volunteers = $_POST['max_volunteers'] ?: 50;
        $initiative_status = $_POST['initiative_status'] ?: 'مفتوحة للتسجيل';
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        $registration_deadline = $_POST['registration_deadline'] ?: null;
        $coordinator_name = trim($_POST['coordinator_name']);
        $coordinator_phone = trim($_POST['coordinator_phone']);
        $coordinator_email = trim($_POST['coordinator_email']);
        $location = trim($_POST['location']);
        $budget = $_POST['budget'] ?: 0;
        $status = $_POST['status'] ?: 'مخطط';
        $success_story = trim($_POST['success_story']);
        $impact_description = trim($_POST['impact_description']);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $auto_approval = isset($_POST['auto_approval']) ? 1 : 0;
        
        if (!empty($name) && !empty($description)) {
            try {
                $db->beginTransaction();
                
                // جلب الصورة الرئيسية الحالية
                $current_stmt = $db->prepare("SELECT main_image FROM youth_environmental_initiatives WHERE id = ?");
                $current_stmt->execute([$initiative_id]);
                $current_data = $current_stmt->fetch();
                $main_image = $current_data['main_image'];
                $messages = [];
                
                // معالجة الصورة الرئيسية الجديدة
                if (!empty($_FILES['main_image']['name'])) {
                    $upload_dir = '../uploads/initiatives/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_name = $_FILES['main_image']['name'];
                    $file_tmp = $_FILES['main_image']['tmp_name'];
                    $file_size = $_FILES['main_image']['size'];
                    $file_type = $_FILES['main_image']['type'];
                    
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = 'initiative_main_' . $initiative_id . '_' . time() . '.' . $file_extension;
                        $file_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // حذف الصورة القديمة إذا وجدت
                            if ($main_image && file_exists($upload_dir . $main_image)) {
                                unlink($upload_dir . $main_image);
                            }
                            $main_image = $unique_name;
                            $messages[] = "تم تحديث الصورة الرئيسية";
                        }
                    }
                }
                
                // معالجة معرض الصور الجديدة
                if (!empty($_FILES['gallery_images']['name'][0])) {
                    $upload_dir = '../uploads/initiatives/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_count = count($_FILES['gallery_images']['name']);
                    $uploaded_count = 0;
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['gallery_images']['name'][$i];
                            $file_tmp = $_FILES['gallery_images']['tmp_name'][$i];
                            $file_size = $_FILES['gallery_images']['size'][$i];
                            $file_type = $_FILES['gallery_images']['type'][$i];
                            
                            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                            if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                                $unique_name = 'initiative_' . $initiative_id . '_gallery_' . time() . '_' . $i . '.' . $file_extension;
                                $file_path = $upload_dir . $unique_name;
                                
                                if (move_uploaded_file($file_tmp, $file_path)) {
                                    // إدراج في جدول initiative_images
                                    $img_stmt = $db->prepare("INSERT INTO initiative_images 
                                        (initiative_id, image_path, image_name, image_type, display_order, file_size, uploaded_by) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $img_stmt->execute([
                                        $initiative_id, $unique_name, $file_name, 'معرض', $i, $file_size, $auth->getCurrentUser()['id']
                                    ]);
                                    $uploaded_count++;
                                }
                            }
                        }
                    }
                    
                    if ($uploaded_count > 0) {
                        $messages[] = "تم إضافة $uploaded_count صورة جديدة للمعرض";
                    }
                }
                
                // معالجة حذف صور المعرض
                if (!empty($_POST['delete_gallery_images'])) {
                    $upload_dir = '../uploads/initiatives/';
                    $deleted_count = 0;
                    
                    foreach ($_POST['delete_gallery_images'] as $image_id) {
                        $img_stmt = $db->prepare("SELECT image_path FROM initiative_images WHERE id = ? AND initiative_id = ?");
                        $img_stmt->execute([$image_id, $initiative_id]);
                        $image_data = $img_stmt->fetch();
                        
                        if ($image_data) {
                            // حذف الملف من الخادم
                            if (file_exists($upload_dir . $image_data['image_path'])) {
                                unlink($upload_dir . $image_data['image_path']);
                            }
                            
                            // حذف السجل من قاعدة البيانات
                            $delete_stmt = $db->prepare("DELETE FROM initiative_images WHERE id = ?");
                            $delete_stmt->execute([$image_id]);
                            $deleted_count++;
                        }
                    }
                    
                    if ($deleted_count > 0) {
                        $messages[] = "تم حذف $deleted_count صورة من المعرض";
                    }
                }
                
                $stmt = $db->prepare("UPDATE youth_environmental_initiatives SET 
                    initiative_name = ?, initiative_description = ?, initiative_type = ?, initiative_goals = ?, 
                    requirements = ?, benefits = ?, target_audience = ?, required_volunteers = ?, max_volunteers = ?, 
                    start_date = ?, end_date = ?, registration_deadline = ?, initiative_status = ?, 
                    coordinator_name = ?, coordinator_phone = ?, coordinator_email = ?, location = ?, budget = ?, 
                    success_story = ?, impact_description = ?, is_featured = ?, is_active = ?, auto_approval = ?, 
                    status = ?, main_image = ?, updated_at = NOW() WHERE id = ?");
                
                $result = $stmt->execute([
                    $name, $description, $type, $goals, $requirements, $benefits, 
                    $target_audience, $required_volunteers, $max_volunteers, 
                    $start_date, $end_date, $registration_deadline, $initiative_status, 
                    $coordinator_name, $coordinator_phone, $coordinator_email, 
                    $location, $budget, $success_story, $impact_description, 
                    $is_featured, $is_active, $auto_approval, $status, $main_image, $initiative_id
                ]);
                
                if ($result) {
                    $db->commit();
                    $success_message = "تم تحديث المبادرة بنجاح" . (empty($messages) ? "" : ". " . implode(". ", $messages));
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=initiatives&success=1");
                    exit();
                }
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "خطأ في تحديث المبادرة: " . $e->getMessage();
            }
        } else {
            $error_message = "يرجى ملء الحقول المطلوبة (اسم المبادرة ووصفها)";
        }
    }
    
    // تحديث حالة طلب مواطن
    elseif ($action == 'update_request') {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['status'];
        $assigned_department = $_POST['assigned_to_department_id'] ?: null;
        $assigned_committee = $_POST['assigned_to_committee_id'] ?: null;
        $admin_notes = trim($_POST['admin_notes']);
        $response_text = trim($_POST['response_text']);
        
        try {
            // تحديث الطلب
            $stmt = $db->prepare("UPDATE citizen_requests SET status = ?, assigned_to_department_id = ?, assigned_to_committee_id = ?, assigned_to_user_id = NULL, admin_notes = ?, response_date = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $assigned_department, $assigned_committee, $admin_notes, $request_id]);
            
            // إذا كان الطلب مكتملاً، تحديث تاريخ الإنجاز
            if ($new_status == 'مكتمل') {
                $stmt = $db->prepare("UPDATE citizen_requests SET completion_date = NOW() WHERE id = ?");
                $stmt->execute([$request_id]);
            }
            
            $success_message = "تم تحديث الطلب بنجاح";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=requests&success=1");
            exit();
        } catch (Exception $e) {
            $error_message = "خطأ في تحديث الطلب: " . $e->getMessage();
        }
    }
    
    // إرسال رد على طلب مواطن
    elseif ($action == 'send_response') {
        $request_id = $_POST['request_id'];
        $response_text = trim($_POST['response_text']);
        
        if (!empty($response_text)) {
            try {
                $stmt = $db->prepare("UPDATE citizen_requests SET admin_notes = ?, response_date = NOW() WHERE id = ?");
                $stmt->execute([$response_text, $request_id]);
                
                $success_message = "تم إرسال الرد بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=requests&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في إرسال الرد: " . $e->getMessage();
            }
        }
    }
    
    // إضافة نوع طلب جديد
    elseif ($action == 'add_request_type') {
        $type_name = trim($_POST['type_name']);
        $name_ar = trim($_POST['name_ar']);
        $name_en = trim($_POST['name_en']);
        $type_description = trim($_POST['type_description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = $_POST['display_order'] ?: 999;
        $cost = $_POST['cost'] ?: 0;
        $cost_currency_id = $_POST['cost_currency_id'] ?: $default_currency_id;
        
        // معالجة المستندات المطلوبة
        $required_documents = [];
        if (!empty($_POST['required_documents'])) {
            $required_documents = array_filter($_POST['required_documents']);
        }
        
        if (!empty($type_name) && !empty($name_ar)) {
            try {
                // التحقق من وجود عمود cost
                $columns = $db->query("DESCRIBE request_types")->fetchAll();
                $has_cost = false;
                foreach ($columns as $column) {
                    if ($column['Field'] == 'cost') {
                        $has_cost = true;
                        break;
                    }
                }
                
                if ($has_cost) {
                    $stmt = $db->prepare("INSERT INTO request_types (
                        type_name, name_ar, name_en, type_description, 
                        required_documents, is_active, display_order, cost, cost_currency_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $type_name, $name_ar, $name_en, $type_description,
                        json_encode($required_documents), $is_active, $display_order, $cost, $cost_currency_id
                    ]);
                } else {
                    $stmt = $db->prepare("INSERT INTO request_types (
                        type_name, name_ar, name_en, type_description, 
                        required_documents, is_active, display_order
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $type_name, $name_ar, $name_en, $type_description,
                        json_encode($required_documents), $is_active, $display_order
                    ]);
                }
                
                $success_message = "تم إضافة نوع الطلب بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=request_types&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة نوع الطلب: " . $e->getMessage();
            }
        } else {
            $error_message = "يرجى ملء الحقول المطلوبة";
        }
    }
    
    // تعديل نوع طلب
    elseif ($action == 'edit_request_type') {
        $id = $_POST['request_type_id'];
        $type_name = trim($_POST['type_name']);
        $name_ar = trim($_POST['name_ar']);
        $name_en = trim($_POST['name_en']);
        $type_description = trim($_POST['type_description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = $_POST['display_order'] ?: 999;
        $cost = $_POST['cost'] ?: 0;
        $cost_currency_id = $_POST['cost_currency_id'] ?: $default_currency_id;
        
        // معالجة المستندات المطلوبة
        $required_documents = [];
        if (!empty($_POST['required_documents'])) {
            $required_documents = array_filter($_POST['required_documents']);
        }
        
        if (!empty($type_name) && !empty($name_ar)) {
            try {
                // التحقق من وجود عمود cost
                $columns = $db->query("DESCRIBE request_types")->fetchAll();
                $has_cost = false;
                foreach ($columns as $column) {
                    if ($column['Field'] == 'cost') {
                        $has_cost = true;
                        break;
                    }
                }
                
                if ($has_cost) {
                    $stmt = $db->prepare("UPDATE request_types SET 
                        type_name = ?, name_ar = ?, name_en = ?, type_description = ?, 
                        required_documents = ?, is_active = ?, display_order = ?, cost = ?, cost_currency_id = ?
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $type_name, $name_ar, $name_en, $type_description,
                        json_encode($required_documents), $is_active, $display_order, $cost, $cost_currency_id, $id
                    ]);
                } else {
                    $stmt = $db->prepare("UPDATE request_types SET 
                        type_name = ?, name_ar = ?, name_en = ?, type_description = ?, 
                        required_documents = ?, is_active = ?, display_order = ?
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $type_name, $name_ar, $name_en, $type_description,
                        json_encode($required_documents), $is_active, $display_order, $id
                    ]);
                }
                
                $success_message = "تم تحديث نوع الطلب بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=request_types&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث نوع الطلب: " . $e->getMessage();
            }
        } else {
            $error_message = "يرجى ملء الحقول المطلوبة";
        }
    }
    
    // حذف عنصر
    elseif ($action == 'delete_item') {
        $table = $_POST['table'];
        $id = $_POST['id'];
        
        $allowed_tables = ['news_activities', 'development_projects', 'youth_environmental_initiatives', 'request_types'];
        if (in_array($table, $allowed_tables)) {
            try {
                $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "تم الحذف بنجاح";
                
                // إعادة توجيه حسب نوع الجدول
                if ($table == 'request_types') {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=request_types&success=1");
                } else {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                }
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في الحذف: " . $e->getMessage();
            }
        }
    }
}

// تحديد التبويب النشط
$active_tab = $_GET['tab'] ?? 'requests';

// رسائل النجاح
if (isset($_GET['success'])) {
    $success_message = "تم تنفيذ العملية بنجاح";
}

// جلب البيانات
$news = $db->query("SELECT n.*, u.full_name as creator_name FROM news_activities n LEFT JOIN users u ON n.created_by = u.id ORDER BY n.publish_date DESC")->fetchAll();
$projects = $db->query("SELECT p.*, d.department_name FROM development_projects p LEFT JOIN departments d ON p.responsible_department_id = d.id ORDER BY p.created_at DESC")->fetchAll();
$initiatives = $db->query("SELECT * FROM youth_environmental_initiatives ORDER BY created_at DESC")->fetchAll();
$departments = $db->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
$committees = $db->query("SELECT id, committee_name, department_id FROM municipal_committees WHERE is_active = 1 ORDER BY committee_name")->fetchAll();
$currencies = $db->query("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE is_active = 1 ORDER BY currency_name")->fetchAll();

// جلب أنواع الطلبات
$request_types = $db->query("SELECT rt.*, c.currency_symbol, c.currency_code FROM request_types rt LEFT JOIN currencies c ON rt.cost_currency_id = c.id ORDER BY rt.display_order ASC, rt.type_name ASC")->fetchAll();

// جلب العملة الافتراضية من إعدادات النظام
function getDefaultCurrencySettings($db) {
    try {
        // البحث في website_settings أولاً
        $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = 'default_currency_id'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && $result['setting_value']) {
            $default_currency_id = $result['setting_value'];
        } else {
            // البحث في system_settings
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_currency_id'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result && $result['setting_value']) {
                $default_currency_id = $result['setting_value'];
            } else {
                // البحث عن عملة مميزة كافتراضية
                $stmt = $db->query("SELECT id FROM currencies WHERE is_default = 1 AND is_active = 1 LIMIT 1");
                $currency = $stmt->fetch();
                $default_currency_id = $currency ? $currency['id'] : null;
            }
        }
        
        if ($default_currency_id) {
            $stmt = $db->prepare("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE id = ? AND is_active = 1");
            $stmt->execute([$default_currency_id]);
            $currency_info = $stmt->fetch();
            
            if ($currency_info) {
                return [
                    'id' => $currency_info['id'],
                    'info' => $currency_info
                ];
            }
        }
        
        // إذا لم تجد العملة الافتراضية، استخدم الدينار العراقي
        $stmt = $db->query("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE currency_code = 'IQD' AND is_active = 1 LIMIT 1");
        $currency_info = $stmt->fetch();
        
        if ($currency_info) {
            return [
                'id' => $currency_info['id'],
                'info' => $currency_info
            ];
        }
        
        // كحل أخير، استخدم أول عملة نشطة
        $stmt = $db->query("SELECT id, currency_code, currency_name, currency_symbol FROM currencies WHERE is_active = 1 ORDER BY id LIMIT 1");
        $currency_info = $stmt->fetch();
        
        return [
            'id' => $currency_info ? $currency_info['id'] : 1,
            'info' => $currency_info ?: [
                'id' => 1,
                'currency_code' => 'IQD',
                'currency_name' => 'الدينار العراقي',
                'currency_symbol' => 'د.ع'
            ]
        ];
        
    } catch (Exception $e) {
        // في حالة الخطأ، إرجاع بيانات افتراضية
        return [
            'id' => 1,
            'info' => [
                'id' => 1,
                'currency_code' => 'IQD',
                'currency_name' => 'الدينار العراقي',
                'currency_symbol' => 'د.ع'
            ]
        ];
    }
}

$default_currency_data = getDefaultCurrencySettings($db);
$default_currency_id = $default_currency_data['id'];
$default_currency_info = $default_currency_data['info'];

// جلب طلبات المواطنين مع تفاصيل إضافية
$requests = $db->query("
    SELECT cr.id, cr.tracking_number, cr.citizen_name, cr.citizen_phone, 
           cr.request_type_id, rt.type_name as request_type, rt.type_description,
           cr.request_title, cr.priority_level, cr.status, cr.created_at, cr.project_id,
           cr.assigned_to_department_id, cr.assigned_to_committee_id,
           d.department_name,
           mc.committee_name,
           dp.project_name,
           DATEDIFF(NOW(), cr.created_at) as days_since_created
    FROM citizen_requests cr 
    LEFT JOIN request_types rt ON cr.request_type_id = rt.id
    LEFT JOIN departments d ON cr.assigned_to_department_id = d.id 
    LEFT JOIN municipal_committees mc ON cr.assigned_to_committee_id = mc.id
    LEFT JOIN development_projects dp ON cr.project_id = dp.id
    ORDER BY 
        CASE cr.status 
            WHEN 'جديد' THEN 1 
            WHEN 'قيد المراجعة' THEN 2 
            WHEN 'قيد التنفيذ' THEN 3 
            ELSE 4 
        END,
        CASE cr.priority_level 
            WHEN 'عاجل' THEN 1 
            WHEN 'مهم' THEN 2 
            ELSE 3 
        END,
        cr.created_at DESC
")->fetchAll();

// إحصائيات
$stats = [
    'total_news' => $db->query("SELECT COUNT(*) as count FROM news_activities")->fetch()['count'],
    'published_news' => $db->query("SELECT COUNT(*) as count FROM news_activities WHERE is_published = 1")->fetch()['count'],
    'total_projects' => $db->query("SELECT COUNT(*) as count FROM development_projects")->fetch()['count'],
    'active_projects' => $db->query("SELECT COUNT(*) as count FROM development_projects WHERE project_status IN ('مطروح', 'قيد التنفيذ')")->fetch()['count'],
    'total_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests")->fetch()['count'],
    'pending_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status IN ('جديد', 'قيد المراجعة')")->fetch()['count'],
    'urgent_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE priority_level = 'عاجل' AND status NOT IN ('مكتمل', 'مرفوض')")->fetch()['count']
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة محتوى الموقع العام - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
	<script src="../public/assets/js/enhanced-requests.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active { color: #4f46e5; border-bottom-color: #4f46e5; }
        
        /* تحسين تنسيق النماذج */
        .form-container {
            max-width: 100%;
            overflow-x: auto;
        }
        
        .funding-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
            border: 1px solid #c7d2fe;
            border-radius: 12px;
            padding: 24px;
            margin: 16px 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .funding-subsection {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin: 12px 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .calculations-display {
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 16px;
            margin: 12px 0;
        }
        
        .calculation-item {
            background: white;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .hidden { display: none !important; }
        
        /* تحسين الأزرار */
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            transform: translateY(-1px);
        }
        
        /* تحسين المشاريع المعروضة */
        .project-card {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        
        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        /* رسائل النجاح والخطأ */
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            margin: 16px 0;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">🌐</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">إدارة محتوى الموقع العام</h1>
                        <p class="text-sm text-gray-500">إدارة الأخبار والمشاريع وطلبات المواطنين</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../public/index.php" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        🌐 عرض الموقع العام
                    </a>
                    <a href="../comprehensive_dashboard.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        🏠 العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">إدارة محتوى الموقع العام</h1>
            <p class="text-slate-600">إدارة المحتوى الذي يراه المواطنون على الموقع الإلكتروني</p>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">📰</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">الأخبار</p>
                        <p class="text-2xl font-bold"><?= $stats['published_news'] ?> / <?= $stats['total_news'] ?></p>
                        <p class="text-xs text-gray-500">منشور / إجمالي</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">🏗️</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">المشاريع</p>
                        <p class="text-2xl font-bold"><?= $stats['active_projects'] ?> / <?= $stats['total_projects'] ?></p>
                        <p class="text-xs text-gray-500">نشط / إجمالي</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">📝</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">طلبات المواطنين</p>
                        <p class="text-2xl font-bold"><?= $stats['pending_requests'] ?> / <?= $stats['total_requests'] ?></p>
                        <p class="text-xs text-gray-500">معلق / إجمالي</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-red-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">⚠️</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">طلبات عاجلة</p>
                        <p class="text-2xl font-bold text-red-600"><?= $stats['urgent_requests'] ?></p>
                        <p class="text-xs text-gray-500">تحتاج عناية فورية</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p class="font-bold">✅ نجح! <?= $success_message ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <p class="font-bold">❌ خطأ! <?= $error_message ?></p>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 space-x-reverse px-6">
                    <button onclick="showTab('requests')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'requests' ? 'active' : 'border-transparent text-gray-500' ?>">
                        📝 طلبات المواطنين 
                        <?php if ($stats['pending_requests'] > 0): ?>
                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full mr-1"><?= $stats['pending_requests'] ?></span>
                        <?php endif; ?>
                    </button>
                    <button onclick="showTab('news')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'news' ? 'active' : 'border-transparent text-gray-500' ?>">
                        📰 الأخبار والأنشطة
                    </button>
                    <button onclick="showTab('projects')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'projects' ? 'active' : 'border-transparent text-gray-500' ?>">
                        🏗️ المشاريع الإنمائية
                    </button>
                    <button onclick="showTab('initiatives')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'initiatives' ? 'active' : 'border-transparent text-gray-500' ?>">
                        🌱 المبادرات
                    </button>
                    <button onclick="showTab('request_types')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'request_types' ? 'active' : 'border-transparent text-gray-500' ?>">
                        📋 أنواع الطلبات
                    </button>
                </nav>
            </div>
        </div>

        <!-- طلبات المواطنين -->
<div id="requests" class="tab-content <?= $active_tab == 'requests' ? 'active' : '' ?>">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold">📝 إدارة طلبات المواطنين</h3>
            <div class="flex items-center space-x-3 space-x-reverse">
                <span class="text-sm text-gray-600">إجمالي الطلبات: <?= count($requests) ?></span>
                <a href="citizen_ratings.php" target="_blank" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                    ⭐ تقييمات المواطنين
                </a>
                <a href="citizen_requests_stats.php" target="_blank" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    📊 إحصائيات مفصلة
                </a>
                <a href="../public/citizen-requests.php" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    🔗 عرض نموذج الطلبات
                </a>
            </div>
        </div>

        <!-- فلترة سريعة -->
        <div class="mb-6 flex flex-wrap gap-2">
            <button onclick="filterRequests('all')" class="filter-btn px-3 py-1 bg-gray-200 rounded-md text-sm hover:bg-gray-300">جميع الطلبات</button>
            <button onclick="filterRequests('جديد')" class="filter-btn px-3 py-1 bg-blue-200 text-blue-800 rounded-md text-sm hover:bg-blue-300">جديد</button>
            <button onclick="filterRequests('قيد المراجعة')" class="filter-btn px-3 py-1 bg-yellow-200 text-yellow-800 rounded-md text-sm hover:bg-yellow-300">قيد المراجعة</button>
            <button onclick="filterRequests('قيد التنفيذ')" class="filter-btn px-3 py-1 bg-purple-200 text-purple-800 rounded-md text-sm hover:bg-purple-300">قيد التنفيذ</button>
            <button onclick="filterRequests('مكتمل')" class="filter-btn px-3 py-1 bg-green-200 text-green-800 rounded-md text-sm hover:bg-green-300">مكتمل</button>
            <button onclick="filterRequests('عاجل')" class="filter-btn px-3 py-1 bg-red-200 text-red-800 rounded-md text-sm hover:bg-red-300">عاجل</button>
            <button onclick="filterRequests('المساهمة في المشروع')" class="filter-btn px-3 py-1 bg-indigo-200 text-indigo-800 rounded-md text-sm hover:bg-indigo-300">🏗️ مساهمات المشاريع</button>
        </div>

        <!-- جدول الطلبات -->
        <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم التتبع</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المواطن</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع الطلب</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الأولوية</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المسؤول</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">التاريخ</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($requests as $request): ?>
                <tr class="request-row" data-status="<?= $request['status'] ?>" data-priority="<?= $request['priority_level'] ?>" data-type="<?= htmlspecialchars($request['request_type'] ?: 'غير محدد') ?>">
                    <td class="px-4 py-4">
                        <div class="font-medium text-sm text-blue-600"><?= htmlspecialchars($request['tracking_number']) ?></div>
                        <div class="text-xs text-gray-500"><?= $request['days_since_created'] ?> يوم مضى</div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="font-medium text-sm"><?= htmlspecialchars($request['citizen_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($request['citizen_phone']) ?></div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="font-medium text-sm">
                            <?= htmlspecialchars($request['request_type'] ?: 'غير محدد') ?>
                        </div>
                        <?php if ($request['type_description']): ?>
                            <div class="text-xs text-gray-500" title="<?= htmlspecialchars($request['type_description']) ?>">
                                📋 <?= htmlspecialchars(substr($request['type_description'], 0, 25)) ?>...
                            </div>
                        <?php endif; ?>
                        <?php if ($request['request_type'] == 'المساهمة في المشروع' && $request['project_name']): ?>
                            <div class="text-xs text-blue-600 font-medium">🏗️ <?= htmlspecialchars($request['project_name']) ?></div>
                        <?php endif; ?>
                        <div class="text-xs text-gray-500" title="<?= htmlspecialchars($request['request_title']) ?>">
                            <?= htmlspecialchars(substr($request['request_title'], 0, 30)) ?>...
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                            switch($request['priority_level']) {
                                case 'عاجل': echo 'bg-red-100 text-red-800'; break;
                                case 'مهم': echo 'bg-orange-100 text-orange-800'; break;
                                default: echo 'bg-green-100 text-green-800';
                            }
                        ?>">
                            <?= $request['priority_level'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                            switch($request['status']) {
                                case 'جديد': echo 'bg-blue-100 text-blue-800'; break;
                                case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'قيد التنفيذ': echo 'bg-purple-100 text-purple-800'; break;
                                case 'مكتمل': echo 'bg-green-100 text-green-800'; break;
                                case 'مرفوض': echo 'bg-red-100 text-red-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?>">
                            <?= $request['status'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-4 text-sm">
                        <!-- عرض محسن للمسؤول -->
                        <?php if ($request['department_name']): ?>
                            <div class="font-medium text-gray-900 text-xs mb-1">
                                🏢 <?= htmlspecialchars($request['department_name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['committee_name'])): ?>
                            <div class="text-sm font-medium text-blue-600">
                                🗂️ <?= htmlspecialchars($request['committee_name']) ?>
                            </div>
                        <?php else: ?>
                            <div class="text-sm text-red-500">❌ غير مرتبط بلجنة</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-xs text-gray-500">
                        <?= date('Y/m/d', strtotime($request['created_at'])) ?>
                    </td>
                    <td class="px-4 py-4 text-sm font-medium space-x-2 space-x-reverse">
                        <button onclick="viewRequest(<?= $request['id'] ?>)" class="text-blue-600 hover:text-blue-900" title="عرض تفاصيل الطلب">👁️ عرض</button>
                        <button onclick="updateRequest(<?= $request['id'] ?>)" class="text-green-600 hover:text-green-900" title="تحديث الطلب">✏️ تحديث</button>
                        <a href="../public/track-request.php?tracking_number=<?= $request['tracking_number'] ?>" target="_blank" class="text-purple-600 hover:text-purple-900" title="عرض صفحة التتبع">🔗 تتبع</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

        <!-- إحصائيات سريعة -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-blue-50 p-3 rounded-lg text-center">
                <div class="text-lg font-bold text-blue-800">
                    <?= count(array_filter($requests, function($r) { return $r['status'] == 'جديد'; })) ?>
                </div>
                <div class="text-sm text-blue-600">طلبات جديدة</div>
            </div>
            <div class="bg-yellow-50 p-3 rounded-lg text-center">
                <div class="text-lg font-bold text-yellow-800">
                    <?= count(array_filter($requests, function($r) { return $r['status'] == 'قيد المراجعة'; })) ?>
                </div>
                <div class="text-sm text-yellow-600">قيد المراجعة</div>
            </div>
            <div class="bg-purple-50 p-3 rounded-lg text-center">
                <div class="text-lg font-bold text-purple-800">
                    <?= count(array_filter($requests, function($r) { return $r['status'] == 'قيد التنفيذ'; })) ?>
                </div>
                <div class="text-sm text-purple-600">قيد التنفيذ</div>
            </div>
            <div class="bg-red-50 p-3 rounded-lg text-center">
                <div class="text-lg font-bold text-red-800">
                    <?= count(array_filter($requests, function($r) { return $r['priority_level'] == 'عاجل' && !in_array($r['status'], ['مكتمل', 'مرفوض']); })) ?>
                </div>
                <div class="text-sm text-red-600">طلبات عاجلة</div>
            </div>
        </div>
    </div>
</div>

        <!-- الأخبار والأنشطة -->
        <div id="news" class="tab-content <?= $active_tab == 'news' ? 'active' : '' ?>">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold">📰 إدارة الأخبار والأنشطة</h3>
                    <button onclick="toggleForm('newsForm')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        ➕ إضافة خبر جديد
                    </button>
                </div>

                <!-- نموذج إضافة خبر -->
                <div id="newsForm" class="hidden mb-6 p-4 border rounded-lg bg-gray-50">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_news">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">عنوان الخبر</label>
                                <input type="text" name="title" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">نوع الخبر</label>
                                <select name="news_type" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="رسمية">رسمية</option>
                                    <option value="مناسبات محلية">مناسبات محلية</option>
                                    <option value="أنشطة اجتماعية">أنشطة اجتماعية</option>
                                    <option value="إعلام رسمي">إعلام رسمي</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ النشر</label>
                                <input type="date" name="publish_date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="flex items-center">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_featured" class="mr-2">
                                    <span class="text-sm text-gray-700">خبر مميز</span>
                                </label>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">محتوى الخبر</label>
                            <textarea name="content" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                        </div>
                        
                        <!-- قسم الصور -->
                        <div class="bg-blue-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-blue-800 mb-4">📷 صور الخبر</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الصورة الرئيسية</label>
                                    <input type="file" name="featured_image" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <p class="text-xs text-gray-500 mt-1">يفضل أن تكون بأبعاد 800x600 بكسل للحصول على أفضل عرض</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">صور المعرض (متعددة)</label>
                                    <input type="file" name="gallery_images[]" accept="image/*" multiple class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <p class="text-xs text-gray-500 mt-1">يمكنك اختيار عدة صور في نفس الوقت (Ctrl+Click)</p>
                                </div>
                            </div>
                            
                            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                <p class="text-sm text-yellow-800">
                                    <strong>ملاحظة:</strong> الأنواع المدعومة: JPG, PNG, GIF, WebP | الحد الأقصى لحجم الصورة: 5MB
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse">
                            <button type="button" onclick="toggleForm('newsForm')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">إضافة الخبر</button>
                        </div>
                    </form>
                </div>

                <!-- نموذج تعديل خبر -->
                <div id="editNewsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-gray-900">✏️ تعديل الخبر</h3>
                            <button onclick="closeEditNewsModal()" class="text-gray-400 hover:text-gray-600">
                                <span class="sr-only">إغلاق</span>
                                ✕
                            </button>
                        </div>
                        
                        <form id="editNewsForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="edit_news">
                            <input type="hidden" name="news_id" id="edit_news_id">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">عنوان الخبر</label>
                                    <input type="text" name="title" id="edit_title" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع الخبر</label>
                                    <select name="news_type" id="edit_news_type" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="رسمية">رسمية</option>
                                        <option value="مناسبات محلية">مناسبات محلية</option>
                                        <option value="أنشطة اجتماعية">أنشطة اجتماعية</option>
                                        <option value="إعلام رسمي">إعلام رسمي</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ النشر</label>
                                    <input type="date" name="publish_date" id="edit_publish_date" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                </div>
                                <div class="flex items-center">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_featured" id="edit_is_featured" class="mr-2">
                                        <span class="text-sm text-gray-700">خبر مميز</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">محتوى الخبر</label>
                                <textarea name="content" id="edit_content" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                            </div>
                            
                            <!-- عرض الصور الحالية -->
                            <div id="currentImagesSection" class="mb-4">
                                <h4 class="font-bold text-gray-800 mb-3">🖼️ الصور الحالية</h4>
                                
                                <div id="currentFeaturedImage" class="mb-4">
                                    <h5 class="font-semibold text-gray-700 mb-2">الصورة الرئيسية:</h5>
                                    <div id="featuredImagePreview"></div>
                                </div>
                                
                                <div id="currentGalleryImages" class="mb-4">
                                    <h5 class="font-semibold text-gray-700 mb-2">صور المعرض:</h5>
                                    <div id="galleryImagesPreview" class="grid grid-cols-2 md:grid-cols-4 gap-2"></div>
                                </div>
                            </div>
                            
                            <!-- إضافة صور جديدة -->
                            <div class="bg-green-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-green-800 mb-4">📷 إضافة/تغيير الصور</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">صورة رئيسية جديدة</label>
                                        <input type="file" name="featured_image" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <p class="text-xs text-gray-500 mt-1">اترك فارغاً للإبقاء على الصورة الحالية</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">صور معرض إضافية</label>
                                        <input type="file" name="gallery_images[]" accept="image/*" multiple class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <p class="text-xs text-gray-500 mt-1">ستضاف إلى الصور الموجودة</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-3 space-x-reverse">
                                <button type="button" onclick="closeEditNewsModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md">حفظ التعديلات</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- قائمة الأخبار -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">العنوان</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">النوع</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ النشر</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الكاتب</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($news as $item): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($item['title']) ?></div>
                                        <?php if ($item['is_featured']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">⭐ مميز</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $item['news_type'] ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('Y/m/d', strtotime($item['publish_date'])) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $item['is_published'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $item['is_published'] ? 'منشور' : 'مسودة' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['creator_name'] ?: 'غير محدد') ?></td>
                                    <td class="px-6 py-4 text-sm font-medium space-x-2 space-x-reverse">
                                        <a href="../public/news-detail.php?id=<?= $item['id'] ?>" target="_blank" class="text-blue-600 hover:text-blue-900">👁️ عرض</a>
                                        <button onclick="editNews(<?= $item['id'] ?>)" class="text-green-600 hover:text-green-900">✏️ تعديل</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا الخبر؟')">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="table" value="news_activities">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">🗑️ حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- المشاريع الإنمائية -->
        <div id="projects" class="tab-content <?= $active_tab == 'projects' ? 'active' : '' ?>">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold">🏗️ إدارة المشاريع الإنمائية</h3>
                    <button onclick="toggleForm('projectForm')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        ➕ إضافة مشروع جديد
                    </button>
                </div>

                <!-- نموذج إضافة مشروع -->
                <div id="projectForm" class="hidden mb-6 form-container">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_project">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اسم المشروع</label>
                                <input type="text" name="project_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">موقع المشروع</label>
                                <input type="text" name="project_location" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">مدة المشروع</label>
                                <input type="text" name="project_duration" placeholder="مثال: 6 أشهر" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">عدد المستفيدين</label>
                                <input type="number" name="beneficiaries_count" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">حالة المشروع</label>
                                <select name="project_status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="مطروح">مطروح</option>
                                    <option value="قيد التنفيذ">قيد التنفيذ</option>
                                    <option value="منفذ">منفذ</option>
                                    <option value="متوقف">متوقف</option>
                                    <option value="ملغي">ملغي</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ البدء</label>
                                <input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">القسم المسؤول</label>
                                <select name="responsible_department_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="">اختر القسم</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">وصف المشروع</label>
                            <textarea name="project_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">هدف المشروع</label>
                            <textarea name="project_goal" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                        </div>
                        
                        <!-- نظام التمويل المتقدم -->
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg mb-4">
                            <h4 class="font-bold text-indigo-800 mb-4">💰 نظام التمويل المتقدم</h4>
                            
                            <!-- تكلفة المشروع الأساسية -->
                            <div class="bg-white p-4 rounded-lg mb-4">
                                <h5 class="font-semibold text-gray-800 mb-3">📊 تكلفة المشروع</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">تكلفة المشروع الأساسية</label>
                                        <input type="number" name="project_base_cost" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">عملة التكلفة</label>
                                        <select name="project_base_cost_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()">
                                            <?php foreach ($currencies as $currency): ?>
                                                <option value="<?= $currency['id'] ?>" <?= $currency['id'] == $default_currency_id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- مصدر التمويل -->
                            <div class="bg-white p-4 rounded-lg mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">مصدر التمويل</label>
                                        <select name="funding_source" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="toggleAdvancedFundingFields(this.value)">
                                            <option value="بلدية">بلدية فقط</option>
                                            <option value="مانح">جهة مانحة فقط</option>
                                            <option value="مختلط">مختلط (متعدد المصادر)</option>
                                            <option value="مساهمين">متبرعين فقط</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="is_municipality_project" class="mr-2" checked>
                                            <span class="text-sm text-gray-700">تساهم البلدية في هذا المشروع</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- مساهمة البلدية -->
                            <div id="municipalityFields" class="bg-green-50 p-4 rounded-lg mb-4">
                                <h5 class="font-semibold text-green-800 mb-3">🏛️ مساهمة البلدية</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ مساهمة البلدية</label>
                                        <input type="number" name="municipality_contribution_amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">عملة مساهمة البلدية</label>
                                        <select name="municipality_contribution_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()">
                                            <?php foreach ($currencies as $currency): ?>
                                                <option value="<?= $currency['id'] ?>" <?= $currency['id'] == $default_currency_id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- مساهمة الجهة المانحة -->
                            <div id="donorFields" class="bg-yellow-50 p-4 rounded-lg mb-4" style="display: none;">
                                <h5 class="font-semibold text-yellow-800 mb-3">🏦 مساهمة الجهة المانحة</h5>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">اسم الجهة المانحة</label>
                                        <input type="text" name="donor_organization" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="مثال: البنك الدولي">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ مساهمة الجهة المانحة</label>
                                        <input type="number" name="donor_contribution_amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">عملة مساهمة الجهة المانحة</label>
                                        <select name="donor_contribution_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()">
                                            <?php foreach ($currencies as $currency): ?>
                                                <option value="<?= $currency['id'] ?>" <?= $currency['currency_code'] == 'USD' ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- مساهمة المتبرعين -->
                            <div id="donorsFields" class="bg-purple-50 p-4 rounded-lg mb-4" style="display: none;">
                                <h5 class="font-semibold text-purple-800 mb-3">👥 مساهمة المتبرعين</h5>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">قائمة أسماء المتبرعين</label>
                                        <textarea name="donors_list" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="اكتب أسماء المتبرعين مفصولة بسطر جديد"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ مساهمة المتبرعين</label>
                                        <input type="number" name="donors_contribution_amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">عملة مساهمة المتبرعين</label>
                                        <select name="donors_contribution_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateFunding()">
                                            <?php foreach ($currencies as $currency): ?>
                                                <option value="<?= $currency['id'] ?>" <?= $currency['id'] == $default_currency_id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- عرض الحسابات -->
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <h5 class="font-semibold text-gray-800 mb-3">📈 حسابات التمويل</h5>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                                    <div class="bg-red-100 p-3 rounded">
                                        <div class="text-sm text-red-600">المبلغ المتبقي</div>
                                        <div id="remainingCostDisplay" class="text-lg font-bold text-red-800">0</div>
                                    </div>
                                    <div class="bg-green-100 p-3 rounded">
                                        <div class="text-sm text-green-600">إجمالي المساهمات</div>
                                        <div id="totalContributionsDisplay" class="text-lg font-bold text-green-800">0</div>
                                    </div>
                                    <div class="bg-indigo-100 p-3 rounded">
                                        <div class="text-sm text-indigo-600">نسبة الإنجاز</div>
                                        <div id="completionPercentageDisplay" class="text-lg font-bold text-indigo-800">0%</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ملاحظات حول التمويل</label>
                                <textarea name="funding_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="أي ملاحظات إضافية حول التمويل..."></textarea>
                            </div>

                            <!-- حقول مخفية للتوافق -->
                            <input type="hidden" name="currency_id" value="<?= $default_currency_id ?>">
                            <input type="hidden" name="project_cost" id="hiddenProjectCost" value="0">
                            <input type="hidden" name="total_project_cost" id="hiddenTotalCost" value="0">
                        </div>
                        
                        <div class="flex items-center space-x-4 space-x-reverse mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_featured" class="mr-2">
                                <span class="text-sm text-gray-700">مشروع مميز</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="allow_contributions" class="mr-2">
                                <span class="text-sm text-gray-700">السماح بالمساهمات</span>
                            </label>
                        </div>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse">
                            <button type="button" onclick="toggleForm('projectForm')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md">إضافة المشروع</button>
                        </div>
                    </form>
                </div>

                <!-- قائمة المشاريع -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($projects as $project): ?>
                        <div class="project-card border rounded-lg p-4 hover:shadow-md bg-white">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-semibold text-lg"><?= htmlspecialchars($project['project_name']) ?></h4>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                        switch($project['project_status']) {
                                            case 'مطروح': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'قيد التنفيذ': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'منفذ': echo 'bg-green-100 text-green-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                    <?= $project['project_status'] ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars(substr($project['project_description'], 0, 100)) ?>...</p>
                            <div class="space-y-1 mb-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">📍 الموقع:</span>
                                    <span><?= htmlspecialchars($project['project_location']) ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">💰 التكلفة:</span>
                                   <span><?php 
    $cost = $project['project_base_cost'] ?: $project['project_cost'];
    $currency_id = $project['project_base_cost_currency_id'] ?: ($project['currency_id'] ?: $default_currency_id);
    echo formatCurrency($cost, $currency_id, $db);
?></span>
                                </div>
                                <?php if ($project['department_name']): ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">🏢 القسم:</span>
                                    <span><?= htmlspecialchars($project['department_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex space-x-2 space-x-reverse">
                                    <a href="../public/project-detail.php?id=<?= $project['id'] ?>" target="_blank" class="text-blue-600 hover:text-blue-800">👁️ عرض</a>
                                    <button onclick="editProject(<?= $project['id'] ?>)" class="text-green-600 hover:text-green-800">✏️ تعديل</button>
                                </div>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا المشروع؟')">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="table" value="development_projects">
                                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">🗑️ حذف</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- المبادرات -->
        <div id="initiatives" class="tab-content <?= $active_tab == 'initiatives' ? 'active' : '' ?>">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold"> إدارة المبادرات</h3>
                    <button onclick="toggleForm('initiativeForm')" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                        ➕ إضافة مبادرة جديدة
                    </button>
                </div>

                <!-- نموذج إضافة مبادرة -->
                <div id="initiativeForm" class="hidden mb-6 p-4 border rounded-lg bg-gray-50">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_initiative">
                        
                        <!-- المعلومات الأساسية -->
                        <div class="bg-blue-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-blue-800 mb-3">📋 المعلومات الأساسية</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المبادرة *</label>
                                    <input type="text" name="initiative_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع المبادرة *</label>
                                    <select name="initiative_type" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                        <option value="">اختر نوع المبادرة</option>
                                        <option value="شبابية">شبابية</option>
                                        <option value="بيئية">بيئية</option>
                                        <option value="مجتمعية">مجتمعية</option>
                                        <option value="تطوعية">تطوعية</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">وصف المبادرة *</label>
                                <textarea name="initiative_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="وصف مفصل للمبادرة وأهدافها" required></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">أهداف المبادرة</label>
                                <textarea name="initiative_goals" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="الأهداف المحددة للمبادرة"></textarea>
                            </div>
                        </div>

                        <!-- التفاصيل الإضافية -->
                        <div class="bg-green-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-green-800 mb-3">📝 التفاصيل الإضافية</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">المتطلبات</label>
                                    <textarea name="requirements" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="المتطلبات اللازمة للمشاركة"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الفوائد المتوقعة</label>
                                    <textarea name="benefits" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="الفوائد التي ستعود على المشاركين والمجتمع"></textarea>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الفئة المستهدفة</label>
                                    <input type="text" name="target_audience" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="مثال: الشباب من 18-30 سنة">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الموقع</label>
                                    <input type="text" name="location" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="موقع تنفيذ المبادرة">
                                </div>
                            </div>
                        </div>

                        <!-- إدارة المتطوعين -->
                        <div class="bg-purple-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-purple-800 mb-3">👥 إدارة المتطوعين</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">عدد المتطوعين المطلوب</label>
                                    <input type="number" name="required_volunteers" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الحد الأقصى للمتطوعين</label>
                                    <input type="number" name="max_volunteers" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0" value="50">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">حالة المبادرة</label>
                                    <select name="initiative_status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="مفتوحة للتسجيل">مفتوحة للتسجيل</option>
                                        <option value="قيد التنفيذ">قيد التنفيذ</option>
                                        <option value="مكتملة">مكتملة</option>
                                        <option value="مؤجلة">مؤجلة</option>
                                        <option value="ملغية">ملغية</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- التواريخ المهمة -->
                        <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-yellow-800 mb-3">📅 التواريخ المهمة</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ البدء</label>
                                    <input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ الانتهاء</label>
                                    <input type="date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">آخر موعد للتسجيل</label>
                                    <input type="date" name="registration_deadline" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                            </div>
                        </div>

                        <!-- معلومات المنسق -->
                        <div class="bg-indigo-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-indigo-800 mb-3">👤 معلومات المنسق</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المنسق</label>
                                    <input type="text" name="coordinator_name" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">رقم هاتف المنسق</label>
                                    <input type="text" name="coordinator_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">بريد المنسق الإلكتروني</label>
                                    <input type="email" name="coordinator_email" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                            </div>
                        </div>

                        <!-- الميزانية والإعدادات -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-gray-800 mb-3">💰 الميزانية والإعدادات</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الميزانية المقدرة (ل.ل.)</label>
                                    <input type="number" name="budget" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0" value="0">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">حالة النشاط</label>
                                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="مخطط">مخطط</option>
                                        <option value="نشط">نشط</option>
                                        <option value="مكتمل">مكتمل</option>
                                        <option value="معلق">معلق</option>
                                        <option value="ملغي">ملغي</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-center">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_featured" class="mr-2">
                                        <span class="text-sm text-gray-700">مبادرة مميزة</span>
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_active" class="mr-2" checked>
                                        <span class="text-sm text-gray-700">نشطة</span>
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="auto_approval" class="mr-2" checked>
                                        <span class="text-sm text-gray-700">موافقة تلقائية للمتطوعين</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- الصور -->
                        <div class="bg-pink-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-pink-800 mb-3">🖼️ الصور</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الصورة الرئيسية</label>
                                    <input type="file" name="main_image" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <p class="text-xs text-gray-500 mt-1">ملف واحد بحد أقصى 5MB - JPG, PNG, GIF</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">معرض الصور</label>
                                    <input type="file" name="gallery_images[]" accept="image/*" multiple class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <p class="text-xs text-gray-500 mt-1">عدة ملفات بحد أقصى 5MB لكل ملف - JPG, PNG, GIF</p>
                                </div>
                            </div>
                        </div>

                        <!-- قصة النجاح والتأثير -->
                        <div class="bg-teal-50 p-4 rounded-lg mb-4">
                            <h4 class="font-bold text-teal-800 mb-3">🏆 قصة النجاح والتأثير</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">قصة النجاح</label>
                                    <textarea name="success_story" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="قصة نجاح المبادرة (إن وجدت)"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">وصف التأثير</label>
                                    <textarea name="impact_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="وصف التأثير المتوقع أو المحقق"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 space-x-reverse pt-4 border-t">
                            <button type="button" onclick="toggleForm('initiativeForm')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">إضافة المبادرة</button>
                        </div>
                    </form>
                </div>

                <!-- قائمة المبادرات -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المبادرة</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">النوع والحالة</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المتطوعين</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">التواريخ</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المنسق</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($initiatives as $initiative): ?>
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($initiative['initiative_name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($initiative['initiative_description'], 0, 100)) ?>...</div>
                                        <?php if ($initiative['is_featured']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">⭐ مميزة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= $initiative['initiative_type'] ?></div>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php 
                                            switch($initiative['initiative_status']) {
                                                case 'مفتوحة للتسجيل': echo 'bg-green-100 text-green-800'; break;
                                                case 'قيد التنفيذ': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'مكتملة': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'مؤجلة': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'ملغية': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= $initiative['initiative_status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        <div class="text-gray-900">
                                            <?= $initiative['registered_volunteers'] ?>/<?= $initiative['max_volunteers'] ?: 'غير محدد' ?>
                                        </div>
                                        <div class="text-gray-500 text-xs">
                                            <?php if ($initiative['required_volunteers']): ?>
                                                مطلوب: <?= $initiative['required_volunteers'] ?>
                                            <?php else: ?>
                                                غير محدد
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        <?php if ($initiative['start_date']): ?>
                                            <div>البدء: <?= date('Y/m/d', strtotime($initiative['start_date'])) ?></div>
                                        <?php endif; ?>
                                        <?php if ($initiative['end_date']): ?>
                                            <div>الانتهاء: <?= date('Y/m/d', strtotime($initiative['end_date'])) ?></div>
                                        <?php endif; ?>
                                        <?php if ($initiative['registration_deadline']): ?>
                                            <div class="text-red-600 text-xs">آخر تسجيل: <?= date('Y/m/d', strtotime($initiative['registration_deadline'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        <?php if ($initiative['coordinator_name']): ?>
                                            <div class="text-gray-900"><?= htmlspecialchars($initiative['coordinator_name']) ?></div>
                                            <?php if ($initiative['coordinator_phone']): ?>
                                                <div class="text-gray-500 text-xs"><?= htmlspecialchars($initiative['coordinator_phone']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">غير محدد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-medium space-x-2 space-x-reverse">
                                        <a href="../public/initiative-detail.php?id=<?= $initiative['id'] ?>" target="_blank" class="text-blue-600 hover:text-blue-900">👁️ عرض</a>
                                        <button onclick="editInitiative(<?= $initiative['id'] ?>)" class="text-green-600 hover:text-green-900">✏️ تعديل</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذه المبادرة؟')">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="table" value="youth_environmental_initiatives">
                                            <input type="hidden" name="id" value="<?= $initiative['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">🗑️ حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- نموذج تعديل المبادرة (مخفي) -->
        <div id="editInitiativeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50" onclick="closeEditInitiativeModal(event)">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-y-auto" onclick="event.stopPropagation()">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">✏️ تعديل المبادرة</h3>
                            <button onclick="closeEditInitiativeModal()" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <form id="editInitiativeForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="edit_initiative">
                            <input type="hidden" name="initiative_id" id="edit_initiative_id">
                            
                            <!-- المعلومات الأساسية -->
                            <div class="bg-blue-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-blue-800 mb-3">📋 المعلومات الأساسية</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">اسم المبادرة *</label>
                                        <input type="text" name="initiative_name" id="edit_initiative_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">نوع المبادرة *</label>
                                        <select name="initiative_type" id="edit_initiative_type" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                            <option value="">اختر نوع المبادرة</option>
                                            <option value="شبابية">شبابية</option>
                                            <option value="بيئية">بيئية</option>
                                            <option value="مجتمعية">مجتمعية</option>
                                            <option value="تطوعية">تطوعية</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">وصف المبادرة *</label>
                                    <textarea name="initiative_description" id="edit_initiative_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">أهداف المبادرة</label>
                                    <textarea name="initiative_goals" id="edit_initiative_goals" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                                </div>
                            </div>

                            <!-- التفاصيل الإضافية -->
                            <div class="bg-green-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-green-800 mb-3">📝 التفاصيل الإضافية</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">المتطلبات</label>
                                        <textarea name="requirements" id="edit_requirements" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الفوائد المتوقعة</label>
                                        <textarea name="benefits" id="edit_benefits" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الفئة المستهدفة</label>
                                        <input type="text" name="target_audience" id="edit_target_audience" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الموقع</label>
                                        <input type="text" name="location" id="edit_location" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                </div>
                            </div>

                            <!-- إدارة المتطوعين -->
                            <div class="bg-purple-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-purple-800 mb-3">👥 إدارة المتطوعين</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">عدد المتطوعين المطلوب</label>
                                        <input type="number" name="required_volunteers" id="edit_required_volunteers" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الحد الأقصى للمتطوعين</label>
                                        <input type="number" name="max_volunteers" id="edit_max_volunteers" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">حالة المبادرة</label>
                                        <select name="initiative_status" id="edit_initiative_status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                            <option value="مفتوحة للتسجيل">مفتوحة للتسجيل</option>
                                            <option value="قيد التنفيذ">قيد التنفيذ</option>
                                            <option value="مكتملة">مكتملة</option>
                                            <option value="مؤجلة">مؤجلة</option>
                                            <option value="ملغية">ملغية</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- التواريخ المهمة -->
                            <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-yellow-800 mb-3">📅 التواريخ المهمة</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ البدء</label>
                                        <input type="date" name="start_date" id="edit_start_date" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ الانتهاء</label>
                                        <input type="date" name="end_date" id="edit_end_date" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">آخر موعد للتسجيل</label>
                                        <input type="date" name="registration_deadline" id="edit_registration_deadline" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                </div>
                            </div>

                            <!-- معلومات المنسق -->
                            <div class="bg-indigo-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-indigo-800 mb-3">👤 معلومات المنسق</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">اسم المنسق</label>
                                        <input type="text" name="coordinator_name" id="edit_coordinator_name" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">رقم هاتف المنسق</label>
                                        <input type="text" name="coordinator_phone" id="edit_coordinator_phone" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">بريد المنسق الإلكتروني</label>
                                        <input type="email" name="coordinator_email" id="edit_coordinator_email" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                </div>
                            </div>

                            <!-- الميزانية والإعدادات -->
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-gray-800 mb-3">💰 الميزانية والإعدادات</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الميزانية المقدرة (ل.ل.)</label>
                                        <input type="number" name="budget" id="edit_budget" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">حالة النشاط</label>
                                        <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                            <option value="مخطط">مخطط</option>
                                            <option value="نشط">نشط</option>
                                            <option value="مكتمل">مكتمل</option>
                                            <option value="معلق">معلق</option>
                                            <option value="ملغي">ملغي</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="flex items-center">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="is_featured" id="edit_is_featured" class="mr-2">
                                            <span class="text-sm text-gray-700">مبادرة مميزة</span>
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="is_active" id="edit_is_active" class="mr-2">
                                            <span class="text-sm text-gray-700">نشطة</span>
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="auto_approval" id="edit_auto_approval" class="mr-2">
                                            <span class="text-sm text-gray-700">موافقة تلقائية للمتطوعين</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- قصة النجاح والتأثير -->
                            <div class="bg-teal-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-teal-800 mb-3">🏆 قصة النجاح والتأثير</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">قصة النجاح</label>
                                        <textarea name="success_story" id="edit_success_story" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">وصف التأثير</label>
                                        <textarea name="impact_description" id="edit_impact_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- الصور -->
                            <div class="bg-pink-50 p-4 rounded-lg mb-4">
                                <h4 class="font-bold text-pink-800 mb-3">🖼️ إدارة الصور</h4>
                                <div id="currentImages" class="mb-4">
                                    <!-- سيتم عرض الصور الحالية هنا بواسطة JavaScript -->
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">تحديث الصورة الرئيسية</label>
                                        <input type="file" name="main_image" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <p class="text-xs text-gray-500 mt-1">ملف واحد بحد أقصى 5MB - JPG, PNG, GIF</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">إضافة صور جديدة للمعرض</label>
                                        <input type="file" name="gallery_images[]" accept="image/*" multiple class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <p class="text-xs text-gray-500 mt-1">عدة ملفات بحد أقصى 5MB لكل ملف - JPG, PNG, GIF</p>
                                    </div>
                                </div>
                                <div id="deleteImagesSection" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">حذف صور من المعرض:</label>
                                    <div id="deleteImagesList" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                        <!-- سيتم إضافة خيارات الحذف هنا بواسطة JavaScript -->
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 space-x-reverse pt-4 border-t">
                                <button type="button" onclick="closeEditInitiativeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">تحديث المبادرة</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- نموذج تعديل المشروع (مخفي) -->
        <div id="editProjectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50" onclick="closeEditModal(event)">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-y-auto" onclick="event.stopPropagation()">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">✏️ تعديل المشروع</h3>
                            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <form id="editProjectForm" method="POST">
                            <input type="hidden" name="action" value="edit_project">
                            <input type="hidden" name="project_id" id="edit_project_id">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المشروع</label>
                                    <input type="text" name="project_name" id="edit_project_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">موقع المشروع</label>
                                    <input type="text" name="project_location" id="edit_project_location" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">مدة المشروع</label>
                                    <input type="text" name="project_duration" id="edit_project_duration" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">عدد المستفيدين</label>
                                    <input type="number" name="beneficiaries_count" id="edit_beneficiaries_count" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">حالة المشروع</label>
                                    <select name="project_status" id="edit_project_status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="مطروح">مطروح</option>
                                        <option value="قيد التنفيذ">قيد التنفيذ</option>
                                        <option value="منفذ">منفذ</option>
                                        <option value="متوقف">متوقف</option>
                                        <option value="ملغي">ملغي</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">تاريخ البدء</label>
                                    <input type="date" name="start_date" id="edit_project_start_date" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">القسم المسؤول</label>
                                    <select name="responsible_department_id" id="edit_responsible_department_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">اختر القسم</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">وصف المشروع</label>
                                <textarea name="project_description" id="edit_project_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">هدف المشروع</label>
                                <textarea name="project_goal" id="edit_project_goal" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                            </div>
                            
                            <!-- نظام التمويل المتقدم -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg mb-4">
                                <h4 class="font-bold text-indigo-800 mb-4">💰 نظام التمويل المتقدم</h4>
                                
                                <!-- تكلفة المشروع الأساسية -->
                                <div class="bg-white p-4 rounded-lg mb-4">
                                    <h5 class="font-semibold text-gray-800 mb-3">📊 تكلفة المشروع</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">تكلفة المشروع الأساسية</label>
                                            <input type="number" name="project_base_cost" id="edit_project_base_cost" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">عملة التكلفة</label>
                                            <select name="project_base_cost_currency_id" id="edit_project_base_cost_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()">
                                                <?php foreach ($currencies as $currency): ?>
                                                    <option value="<?= $currency['id'] ?>">
                                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- مصدر التمويل -->
                                <div class="bg-white p-4 rounded-lg mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">مصدر التمويل</label>
                                            <select name="funding_source" id="edit_funding_source" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="toggleEditAdvancedFundingFields(this.value)">
                                                <option value="بلدية">بلدية فقط</option>
                                                <option value="مانح">جهة مانحة فقط</option>
                                                <option value="مختلط">مختلط (متعدد المصادر)</option>
                                                <option value="مساهمين">متبرعين فقط</option>
                                            </select>
                                        </div>
                                        <div class="flex items-center">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="is_municipality_project" id="edit_is_municipality_project" class="mr-2" checked>
                                                <span class="text-sm text-gray-700">تساهم البلدية في هذا المشروع</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- مساهمة البلدية -->
                                <div id="editMunicipalityFields" class="bg-green-50 p-4 rounded-lg mb-4">
                                    <h5 class="font-semibold text-green-800 mb-3">🏛️ مساهمة البلدية</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ مساهمة البلدية</label>
                                            <input type="number" name="municipality_contribution_amount" id="edit_municipality_contribution_amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">عملة مساهمة البلدية</label>
                                            <select name="municipality_contribution_currency_id" id="edit_municipality_contribution_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()">
                                                <?php foreach ($currencies as $currency): ?>
                                                    <option value="<?= $currency['id'] ?>">
                                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- مساهمة الجهة المانحة -->
                                <div id="editDonorFields" class="bg-yellow-50 p-4 rounded-lg mb-4" style="display: none;">
                                    <h5 class="font-semibold text-yellow-800 mb-3">🏦 مساهمة الجهة المانحة</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم الجهة المانحة</label>
                                            <input type="text" name="donor_organization" id="edit_donor_organization" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="مثال: البنك الدولي">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ مساهمة الجهة المانحة</label>
                                            <input type="number" name="donor_contribution_amount" id="edit_donor_contribution_amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">عملة مساهمة الجهة المانحة</label>
                                            <select name="donor_contribution_currency_id" id="edit_donor_contribution_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()">
                                                <?php foreach ($currencies as $currency): ?>
                                                    <option value="<?= $currency['id'] ?>">
                                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- مساهمة المتبرعين -->
                                <div id="editDonorsFields" class="bg-purple-50 p-4 rounded-lg mb-4" style="display: none;">
                                    <h5 class="font-semibold text-purple-800 mb-3">👥 مساهمة المتبرعين</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">قائمة أسماء المتبرعين</label>
                                            <textarea name="donors_list" id="edit_donors_list" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="اكتب أسماء المتبرعين مفصولة بسطر جديد"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">مبلغ مساهمة المتبرعين</label>
                                            <input type="number" name="donors_contribution_amount" id="edit_donors_contribution_amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">عملة مساهمة المتبرعين</label>
                                            <select name="donors_contribution_currency_id" id="edit_donors_contribution_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateEditFunding()">
                                                <?php foreach ($currencies as $currency): ?>
                                                    <option value="<?= $currency['id'] ?>">
                                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- عرض الحسابات -->
                                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                    <h5 class="font-semibold text-gray-800 mb-3">📈 حسابات التمويل</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                                        <div class="bg-red-100 p-3 rounded">
                                            <div class="text-sm text-red-600">المبلغ المتبقي</div>
                                            <div id="editRemainingCostDisplay" class="text-lg font-bold text-red-800">0</div>
                                        </div>
                                        <div class="bg-green-100 p-3 rounded">
                                            <div class="text-sm text-green-600">إجمالي المساهمات</div>
                                            <div id="editTotalContributionsDisplay" class="text-lg font-bold text-green-800">0</div>
                                        </div>
                                        <div class="bg-indigo-100 p-3 rounded">
                                            <div class="text-sm text-indigo-600">نسبة الإنجاز</div>
                                            <div id="editCompletionPercentageDisplay" class="text-lg font-bold text-indigo-800">0%</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">ملاحظات حول التمويل</label>
                                    <textarea name="funding_notes" id="edit_funding_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="أي ملاحظات إضافية حول التمويل..."></textarea>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4 space-x-reverse mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_featured" id="edit_is_featured" class="mr-2">
                                    <span class="text-sm text-gray-700">مشروع مميز</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="allow_contributions" id="edit_allow_contributions" class="mr-2">
                                    <span class="text-sm text-gray-700">السماح بالمساهمات</span>
                                </label>
                            </div>
                            
                            <!-- حقول مخفية للتوافق -->
                            <input type="hidden" name="currency_id" value="<?= $default_currency_id ?>">
                            <input type="hidden" name="project_cost" id="edit_hiddenProjectCost" value="0">
                            <input type="hidden" name="total_project_cost" id="edit_hiddenTotalCost" value="0">
                            
                            <div class="flex justify-end space-x-3 space-x-reverse pt-4 border-t">
                                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md">تحديث المشروع</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function showTab(tabName) {
                // إخفاء جميع التبويبات
                const tabs = document.querySelectorAll('.tab-content');
                tabs.forEach(tab => tab.classList.remove('active'));
                
                // إزالة التنشيط من جميع الأزرار
                const buttons = document.querySelectorAll('.tab-button');
                buttons.forEach(btn => btn.classList.remove('active'));
                
                // إظهار التبويب المحدد
                document.getElementById(tabName).classList.add('active');
                
                // تنشيط الزر المحدد
                event.target.classList.add('active');
            }
            
            function toggleForm(formId) {
                const form = document.getElementById(formId);
                form.classList.toggle('hidden');
            }
            
            function filterRequests(status) {
                const rows = document.querySelectorAll('.request-row');
                rows.forEach(row => {
                    if (status === 'all') {
                        row.style.display = '';
                    } else {
                        const rowStatus = row.getAttribute('data-status');
                        const rowPriority = row.getAttribute('data-priority');
                        const rowType = row.getAttribute('data-type');
                        if (status === rowStatus || status === rowPriority || status === rowType) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            }
            
            function viewRequest(requestId) {
                // فتح نافذة عرض تفاصيل الطلب
                window.open('view_citizen_request.php?id=' + requestId, '_blank', 'width=800,height=600');
            }
            
            function updateRequest(requestId) {
                // فتح نافذة تحديث الطلب
                window.open('update_citizen_request.php?id=' + requestId, '_blank', 'width=900,height=700');
            }
            
            // دالة للتحكم في إظهار/إخفاء حقول التمويل المتقدم
            function toggleAdvancedFundingFields(fundingSource) {
                const municipalityFields = document.getElementById('municipalityFields');
                const donorFields = document.getElementById('donorFields');
                const donorsFields = document.getElementById('donorsFields');
                
                // إخفاء جميع الحقول أولاً
                municipalityFields.style.display = 'none';
                donorFields.style.display = 'none';
                donorsFields.style.display = 'none';
                
                // إظهار الحقول المناسبة حسب مصدر التمويل
                switch(fundingSource) {
                    case 'بلدية':
                        municipalityFields.style.display = 'block';
                        break;
                    case 'مانح':
                        donorFields.style.display = 'block';
                        break;
                    case 'مختلط':
                        municipalityFields.style.display = 'block';
                        donorFields.style.display = 'block';
                        donorsFields.style.display = 'block';
                        break;
                    case 'مساهمين':
                        donorsFields.style.display = 'block';
                        break;
                }
                calculateFunding();
            }

            // أسعار الصرف (من JSON أو API)
            const exchangeRates = {
                <?php 
                $rates = $db->query("SELECT currency_code, exchange_rate_to_iqd FROM currencies")->fetchAll();
                foreach ($rates as $rate) {
                    echo "'{$rate['currency_code']}': {$rate['exchange_rate_to_iqd']},\n                ";
                }
                ?>
            };

            // دالة تحويل العملة إلى دينار عراقي
            function convertToIQD(amount, currencyId) {
                // الحصول على رمز العملة من القائمة المنسدلة
                const currencySelect = document.querySelector(`option[value="${currencyId}"]`);
                if (!currencySelect) return amount;
                
                const currencyText = currencySelect.textContent;
                const currencyCode = currencyText.match(/\((.*?)\)/);
                
                if (currencyCode && exchangeRates[currencyCode[1]]) {
                    return amount / exchangeRates[currencyCode[1]];
                }
                return amount;
            }

            // دالة حساب التمويل
            function calculateFunding() {
                console.log('=== بدء حساب التمويل ===');
                
                // التكلفة الأساسية
                const baseCost = parseFloat(document.querySelector('[name="project_base_cost"]').value) || 0;
                const baseCostCurrency = document.querySelector('[name="project_base_cost_currency_id"]').value;
                console.log(`التكلفة الأساسية: ${baseCost} عملة ID: ${baseCostCurrency}`);

                // مساهمة البلدية
                const municipalityAmount = parseFloat(document.querySelector('[name="municipality_contribution_amount"]').value) || 0;
                const municipalityCurrency = document.querySelector('[name="municipality_contribution_currency_id"]').value;
                console.log(`مساهمة البلدية: ${municipalityAmount} عملة ID: ${municipalityCurrency}`);
                const municipalityConverted = convertCurrency(municipalityAmount, municipalityCurrency, baseCostCurrency);
                console.log(`مساهمة البلدية محولة: ${municipalityConverted}`);

                // مساهمة الجهة المانحة
                const donorAmount = parseFloat(document.querySelector('[name="donor_contribution_amount"]').value) || 0;
                const donorCurrency = document.querySelector('[name="donor_contribution_currency_id"]').value;
                console.log(`مساهمة المانح: ${donorAmount} عملة ID: ${donorCurrency}`);
                const donorConverted = convertCurrency(donorAmount, donorCurrency, baseCostCurrency);
                console.log(`مساهمة المانح محولة: ${donorConverted}`);

                // مساهمة المتبرعين
                const donorsAmount = parseFloat(document.querySelector('[name="donors_contribution_amount"]').value) || 0;
                const donorsCurrency = document.querySelector('[name="donors_contribution_currency_id"]').value;
                console.log(`مساهمة المتبرعين: ${donorsAmount} عملة ID: ${donorsCurrency}`);
                const donorsConverted = convertCurrency(donorsAmount, donorsCurrency, baseCostCurrency);
                console.log(`مساهمة المتبرعين محولة: ${donorsConverted}`);

                // الحسابات
                const totalContributions = municipalityConverted + donorConverted + donorsConverted;
                const remainingCost = baseCost - totalContributions;
                const completionPercentage = baseCost > 0 ? Math.min((totalContributions / baseCost) * 100, 100) : 0;

                console.log(`إجمالي المساهمات: ${totalContributions}`);
                console.log(`المبلغ المتبقي: ${remainingCost}`);
                console.log(`نسبة الإنجاز: ${completionPercentage}%`);

                // الحصول على رمز العملة
                const currencySymbol = getCurrencySymbol(baseCostCurrency, false);
                console.log(`رمز العملة: ${currencySymbol}`);

                // تحديث العرض
                const remainingDisplay = document.getElementById('remainingCostDisplay');
                const contributionsDisplay = document.getElementById('totalContributionsDisplay');
                const percentageDisplay = document.getElementById('completionPercentageDisplay');
                
                if (remainingDisplay) {
                    remainingDisplay.textContent = formatNumber(remainingCost) + ' ' + currencySymbol;
                    console.log('تم تحديث المبلغ المتبقي');
                }
                
                if (contributionsDisplay) {
                    contributionsDisplay.textContent = formatNumber(totalContributions) + ' ' + currencySymbol;
                    console.log('تم تحديث إجمالي المساهمات');
                }
                
                if (percentageDisplay) {
                    percentageDisplay.textContent = completionPercentage.toFixed(1) + '%';
                    console.log('تم تحديث نسبة الإنجاز');
                }

                // تحديث الحقول المخفية (بالدينار للتوافق)
                const baseCostIQD = convertToIQD(baseCost, baseCostCurrency);
                const hiddenTotalCost = document.getElementById('hiddenTotalCost');
                const hiddenProjectCost = document.getElementById('hiddenProjectCost');
                
                if (hiddenTotalCost) hiddenTotalCost.value = baseCostIQD;
                if (hiddenProjectCost) hiddenProjectCost.value = baseCostIQD;

                // تغيير لون المبلغ المتبقي
                if (remainingDisplay) {
                    if (remainingCost <= 0) {
                        remainingDisplay.className = remainingDisplay.className.replace('text-red-800', 'text-green-800');
                    } else {
                        remainingDisplay.className = remainingDisplay.className.replace('text-green-800', 'text-red-800');
                    }
                }
                
                console.log('=== انتهاء حساب التمويل ===');
            }

            // دالة تنسيق الأرقام
            function formatNumber(num) {
                return new Intl.NumberFormat('ar-EG').format(Math.round(num));
            }

            // دالة تحويل العملة بين عملتين
            function convertCurrency(amount, fromCurrencyId, toCurrencyId) {
                if (fromCurrencyId === toCurrencyId || !amount || amount === 0) {
                    return amount;
                }
                
                // الحصول على رموز العملات
                const fromCurrencyOption = document.querySelector(`option[value="${fromCurrencyId}"]`);
                const toCurrencyOption = document.querySelector(`option[value="${toCurrencyId}"]`);
                
                if (!fromCurrencyOption || !toCurrencyOption) {
                    console.log('عملة غير موجودة:', fromCurrencyId, toCurrencyId);
                    return amount;
                }
                
                const fromCurrencyCode = fromCurrencyOption.textContent.match(/\((.*?)\)/);
                const toCurrencyCode = toCurrencyOption.textContent.match(/\((.*?)\)/);
                
                if (!fromCurrencyCode || !toCurrencyCode) {
                    console.log('لا يمكن استخراج رمز العملة');
                    return amount;
                }
                
                const fromCode = fromCurrencyCode[1];
                const toCode = toCurrencyCode[1];
                
                console.log(`تحويل ${amount} من ${fromCode} إلى ${toCode}`);
                
                // التحويل عبر الدينار العراقي
                if (exchangeRates[fromCode] && exchangeRates[toCode]) {
                    // تحويل إلى دينار عراقي أولاً
                    const amountInIQD = amount / exchangeRates[fromCode];
                    // ثم تحويل من دينار عراقي إلى العملة المطلوبة
                    const convertedAmount = amountInIQD * exchangeRates[toCode];
                    
                    console.log(`${amount} ${fromCode} = ${amountInIQD.toFixed(2)} IQD = ${convertedAmount.toFixed(2)} ${toCode}`);
                    return convertedAmount;
                } else {
                    console.log('أسعار صرف غير متوفرة:', fromCode, toCode);
                    console.log('أسعار الصرف المتوفرة:', exchangeRates);
                }
                
                return amount;
            }

            // دالة للحصول على رمز العملة
            function getCurrencySymbol(currencyId, isEditMode = false) {
                console.log(`البحث عن العملة ID: ${currencyId}, وضع التعديل: ${isEditMode}`);
                
                // تحديد النموذج المناسب
                const selector = isEditMode ? '#editProjectModal' : '#addProjectModal';
                const form = document.querySelector(selector);
                
                let currencyOption = null;
                
                // البحث في النموذج المحدد أولاً
                if (form) {
                    currencyOption = form.querySelector(`option[value="${currencyId}"]`);
                }
                
                // إذا لم نجد، ابحث في جميع النماذج
                if (!currencyOption) {
                    currencyOption = document.querySelector(`option[value="${currencyId}"]`);
                }
                
                if (!currencyOption) {
                    console.log('لم يتم العثور على العملة، إرجاع العملة الافتراضية');
                    return '$'; // إرجاع الدولار كعملة افتراضية
                }
                
                const currencyText = currencyOption.textContent;
                console.log(`نص العملة: ${currencyText}`);
                
                // البحث عن الرمز بين الأقواس في نهاية النص
                const symbolMatch = currencyText.match(/\(([^)]+)\)\s*$/);
                if (symbolMatch) {
                    console.log(`رمز العملة المستخرج: ${symbolMatch[1]}`);
                    return symbolMatch[1];
                }
                
                // إذا لم نجد الرمز، نحاول استخراج العملة من النص مباشرة
                if (currencyText.includes('$')) return '$';
                if (currencyText.includes('€')) return '€';
                if (currencyText.includes('د.ع')) return 'د.ع';
                if (currencyText.includes('ل.ل')) return 'ل.ل';
                if (currencyText.includes('ر.س')) return 'ر.س';
                if (currencyText.includes('د.إ')) return 'د.إ';
                if (currencyText.includes('₺')) return '₺';
                
                // كحل أخير، إرجاع العملة الافتراضية
                console.log('لم يتم العثور على رمز العملة، إرجاع الدولار');
                return '$';
            }

            // دالة للحصول على اسم العملة
            function getCurrencyName(currencyId, isEditMode = false) {
                // تحديد النموذج المناسب
                const selector = isEditMode ? '#editProjectModal' : '#addProjectModal';
                const form = document.querySelector(selector);
                
                let currencyOption = null;
                
                // البحث في النموذج المحدد أولاً
                if (form) {
                    currencyOption = form.querySelector(`option[value="${currencyId}"]`);
                }
                
                // إذا لم نجد، ابحث في جميع النماذج
                if (!currencyOption) {
                    currencyOption = document.querySelector(`option[value="${currencyId}"]`);
                }
                
                if (!currencyOption) return 'الدولار الأمريكي';
                
                const currencyText = currencyOption.textContent;
                const nameMatch = currencyText.match(/^(.*?)\s*\(/);
                return nameMatch ? nameMatch[1].trim() : currencyText.trim();
            }



            // تشغيل الحساب عند تحميل الصفحة
            document.addEventListener('DOMContentLoaded', function() {
                calculateFunding();
                
                // إضافة مستمعين للأحداث لإعادة الحساب عند التغيير
                const inputs = [
                    '[name="project_base_cost"]',
                    '[name="project_base_cost_currency_id"]',
                    '[name="municipality_contribution_amount"]',
                    '[name="municipality_contribution_currency_id"]',
                    '[name="donor_contribution_amount"]',
                    '[name="donor_contribution_currency_id"]',
                    '[name="donors_contribution_amount"]',
                    '[name="donors_contribution_currency_id"]'
                ];
                
                inputs.forEach(selector => {
                    const element = document.querySelector(selector);
                    if (element) {
                        element.addEventListener('input', calculateFunding);
                        element.addEventListener('change', calculateFunding);
                    }
                });
            });
            
           

		// إخفاء الرسائل بعد 5 ثوان
				setTimeout(function() {
					// استهداف فقط عناصر الرسائل وليس خلايا الجدول
					const messages = document.querySelectorAll('.bg-green-100.border-green-400, .bg-red-100.border-red-400');
					messages.forEach(msg => msg.style.display = 'none');
				}, 5000);

            // دالة فتح نموذج تعديل المشروع
            function editProject(projectId) {
                console.log('🔍 بدء تحميل المشروع ID:', projectId);
                
                // جلب بيانات المشروع
                fetch('?ajax=get_project&id=' + projectId)
                    .then(response => response.json())
                    .then(project => {
                        console.log('📦 بيانات المشروع المُستلمة:', project);
                        console.log('🔍 فحص تفصيلي للبيانات:');
                        console.log('- ID:', project.id);
                        console.log('- اسم المشروع:', project.project_name);
                        console.log('- تاريخ البدء (خام):', project.start_date);
                        console.log('- نوع تاريخ البدء:', typeof project.start_date);
                        
                        if (project.error) {
                            console.error('❌ خطأ في المشروع:', project.error);
                            alert('خطأ في جلب بيانات المشروع');
                            return;
                        }
                        
                        // ملء النموذج بالبيانات
                        console.log('📝 بدء ملء النموذج...');
                        document.getElementById('edit_project_id').value = project.id;
                        document.getElementById('edit_project_name').value = project.project_name;
                        document.getElementById('edit_project_location').value = project.project_location;
                        document.getElementById('edit_project_duration').value = project.project_duration || '';
                        document.getElementById('edit_beneficiaries_count').value = project.beneficiaries_count || '';
                        document.getElementById('edit_project_status').value = project.project_status;
                        
                        // تحديد تاريخ البدء - بسيط ومباشر
                        console.log('🗓️ تاريخ البدء الأصلي:', project.start_date);
                        
                        let startDate = '';
                        if (project.start_date && project.start_date !== null && project.start_date !== '0000-00-00') {
                            startDate = project.start_date;
                        }
                        
                        console.log('🗓️ تاريخ البدء المُعالج:', startDate);
                        
                        // تعيين تاريخ البدء
                        const startDateElement = document.getElementById('edit_project_start_date');
                        if (startDateElement) {
                            startDateElement.value = startDate;
                            console.log('✅ تم تعيين تاريخ البدء في الحقل:', startDateElement.value);
                        } else {
                            console.error('❌ لم يتم العثور على حقل تاريخ البدء!');
                        }
                        
                        document.getElementById('edit_responsible_department_id').value = project.responsible_department_id || '';
                        document.getElementById('edit_project_description').value = project.project_description;
                        document.getElementById('edit_project_goal').value = project.project_goal;
                        document.getElementById('edit_project_base_cost').value = project.project_base_cost || project.project_cost || '';
                        document.getElementById('edit_project_base_cost_currency_id').value = project.project_base_cost_currency_id || project.currency_id || '';
                        document.getElementById('edit_is_featured').checked = project.is_featured == 1;
                        document.getElementById('edit_allow_contributions').checked = project.allow_contributions == 1;
                        
                        // حقول التمويل المتقدم
                        document.getElementById('edit_funding_source').value = project.funding_source || 'بلدية';
                        document.getElementById('edit_is_municipality_project').checked = project.is_municipality_project == 1;
                        document.getElementById('edit_municipality_contribution_amount').value = project.municipality_contribution_amount || '';
                        document.getElementById('edit_municipality_contribution_currency_id').value = project.municipality_contribution_currency_id || '';
                        document.getElementById('edit_donor_organization').value = project.donor_organization || '';
                        document.getElementById('edit_donor_contribution_amount').value = project.donor_contribution_amount || '';
                        document.getElementById('edit_donor_contribution_currency_id').value = project.donor_contribution_currency_id || '';
                        document.getElementById('edit_donors_list').value = project.donors_list || '';
                        document.getElementById('edit_donors_contribution_amount').value = project.donors_contribution_amount || '';
                        document.getElementById('edit_donors_contribution_currency_id').value = project.donors_contribution_currency_id || '';
                        document.getElementById('edit_funding_notes').value = project.funding_notes || '';
                        
                        // إظهار الحقول المناسبة حسب مصدر التمويل
                        toggleEditAdvancedFundingFields(project.funding_source || 'بلدية');
                        
                        // إظهار النموذج
                        document.getElementById('editProjectModal').classList.remove('hidden');
                        
                        // حساب التمويل
                        calculateEditFunding();
                        
                        // تحقق نهائي من القيم بعد فترة قصيرة للتأكد من أن DOM جاهز
                        setTimeout(() => {
                            console.log('🔍 فحص نهائي للقيم:');
                            console.log('- اسم المشروع:', document.getElementById('edit_project_name').value);
                            console.log('- تاريخ البدء:', document.getElementById('edit_project_start_date').value);
                            console.log('- حالة المشروع:', document.getElementById('edit_project_status').value);
                            
                            // تحقق إضافي من تاريخ البدء
                            const startDateField = document.getElementById('edit_project_start_date');
                            if (startDateField) {
                                console.log('✅ حقل تاريخ البدء موجود');
                                console.log('- قيمة الحقل:', startDateField.value);
                                console.log('- قيمة الحقل (innerHTML):', startDateField.innerHTML);
                                
                                // إذا كان الحقل فارغاً ولكن المشروع يحتوي على تاريخ، أعد المحاولة
                                if (!startDateField.value && project.start_date && project.start_date !== null && project.start_date !== '0000-00-00') {
                                    console.log('🔄 إعادة تعيين تاريخ البدء...');
                                    startDateField.value = project.start_date;
                                    console.log('✅ تم إعادة تعيين تاريخ البدء:', startDateField.value);
                                }
                            } else {
                                console.error('❌ حقل تاريخ البدء غير موجود بعد فترة التأخير!');
                            }
                        }, 100);
                    })
                    .catch(error => {
                        console.error('❌ خطأ في Ajax:', error);
                        alert('خطأ في جلب بيانات المشروع: ' + error.message);
                    });
            }

            // دالة إغلاق نموذج التعديل
            function closeEditModal(event) {
                if (event && event.target !== event.currentTarget) return;
                document.getElementById('editProjectModal').classList.add('hidden');
            }

            // دالة للتحكم في إظهار/إخفاء حقول التمويل المتقدم في نموذج التعديل
            function toggleEditAdvancedFundingFields(fundingSource) {
                const municipalityFields = document.getElementById('editMunicipalityFields');
                const donorFields = document.getElementById('editDonorFields');
                const donorsFields = document.getElementById('editDonorsFields');
                
                // إخفاء جميع الحقول أولاً
                municipalityFields.style.display = 'none';
                donorFields.style.display = 'none';
                donorsFields.style.display = 'none';
                
                // إظهار الحقول المناسبة حسب مصدر التمويل
                switch(fundingSource) {
                    case 'بلدية':
                        municipalityFields.style.display = 'block';
                        break;
                    case 'مانح':
                        donorFields.style.display = 'block';
                        break;
                    case 'مختلط':
                        municipalityFields.style.display = 'block';
                        donorFields.style.display = 'block';
                        donorsFields.style.display = 'block';
                        break;
                    case 'مساهمين':
                        donorsFields.style.display = 'block';
                        break;
                }
                calculateEditFunding();
            }

            // دالة حساب التمويل في نموذج التعديل
            function calculateEditFunding() {
                console.log('=== بدء حساب التمويل - نموذج التعديل ===');
                
                // التكلفة الأساسية
                const baseCost = parseFloat(document.getElementById('edit_project_base_cost').value) || 0;
                const baseCostCurrency = document.getElementById('edit_project_base_cost_currency_id').value;
                console.log(`التكلفة الأساسية (تعديل): ${baseCost} عملة ID: ${baseCostCurrency}`);

                // مساهمة البلدية
                const municipalityAmount = parseFloat(document.getElementById('edit_municipality_contribution_amount').value) || 0;
                const municipalityCurrency = document.getElementById('edit_municipality_contribution_currency_id').value;
                const municipalityConverted = convertCurrency(municipalityAmount, municipalityCurrency, baseCostCurrency);

                // مساهمة الجهة المانحة
                const donorAmount = parseFloat(document.getElementById('edit_donor_contribution_amount').value) || 0;
                const donorCurrency = document.getElementById('edit_donor_contribution_currency_id').value;
                const donorConverted = convertCurrency(donorAmount, donorCurrency, baseCostCurrency);

                // مساهمة المتبرعين
                const donorsAmount = parseFloat(document.getElementById('edit_donors_contribution_amount').value) || 0;
                const donorsCurrency = document.getElementById('edit_donors_contribution_currency_id').value;
                const donorsConverted = convertCurrency(donorsAmount, donorsCurrency, baseCostCurrency);

                // الحسابات
                const totalContributions = municipalityConverted + donorConverted + donorsConverted;
                const remainingCost = baseCost - totalContributions;
                const completionPercentage = baseCost > 0 ? Math.min((totalContributions / baseCost) * 100, 100) : 0;

                console.log(`إجمالي المساهمات (تعديل): ${totalContributions}`);
                console.log(`المبلغ المتبقي (تعديل): ${remainingCost}`);

                // الحصول على رمز العملة
                const currencySymbol = getCurrencySymbol(baseCostCurrency, true);

                // تحديث العرض
                const remainingDisplay = document.getElementById('editRemainingCostDisplay');
                const contributionsDisplay = document.getElementById('editTotalContributionsDisplay');
                const percentageDisplay = document.getElementById('editCompletionPercentageDisplay');
                
                if (remainingDisplay) {
                    remainingDisplay.textContent = formatNumber(remainingCost) + ' ' + currencySymbol;
                }
                
                if (contributionsDisplay) {
                    contributionsDisplay.textContent = formatNumber(totalContributions) + ' ' + currencySymbol;
                }
                
                if (percentageDisplay) {
                    percentageDisplay.textContent = completionPercentage.toFixed(1) + '%';
                }

                // تحديث الحقول المخفية (بالدينار للتوافق)
                const baseCostIQD = convertToIQD(baseCost, baseCostCurrency);
                const hiddenTotalCost = document.getElementById('edit_hiddenTotalCost');
                const hiddenProjectCost = document.getElementById('edit_hiddenProjectCost');
                
                if (hiddenTotalCost) hiddenTotalCost.value = baseCostIQD;
                if (hiddenProjectCost) hiddenProjectCost.value = baseCostIQD;

                // تغيير لون المبلغ المتبقي
                if (remainingDisplay) {
                    if (remainingCost <= 0) {
                        remainingDisplay.className = remainingDisplay.className.replace('text-red-800', 'text-green-800');
                    } else {
                        remainingDisplay.className = remainingDisplay.className.replace('text-green-800', 'text-red-800');
                    }
                }
                
                console.log('=== انتهاء حساب التمويل - نموذج التعديل ===');
            }

            // دالة فتح نموذج تعديل المبادرة
            function editInitiative(initiativeId) {
                // جلب بيانات المبادرة
                fetch('get_initiative.php?id=' + initiativeId)
                    .then(response => response.json())
                    .then(initiative => {
                        if (initiative.error) {
                            alert('خطأ في جلب بيانات المبادرة: ' + initiative.error);
                            return;
                        }
                        
                        // ملء النموذج بالبيانات
                        document.getElementById('edit_initiative_id').value = initiative.id;
                        document.getElementById('edit_initiative_name').value = initiative.initiative_name || '';
                        document.getElementById('edit_initiative_type').value = initiative.initiative_type || '';
                        document.getElementById('edit_initiative_description').value = initiative.initiative_description || '';
                        document.getElementById('edit_initiative_goals').value = initiative.initiative_goals || '';
                        document.getElementById('edit_requirements').value = initiative.requirements || '';
                        document.getElementById('edit_benefits').value = initiative.benefits || '';
                        document.getElementById('edit_target_audience').value = initiative.target_audience || '';
                        document.getElementById('edit_required_volunteers').value = initiative.required_volunteers || '';
                        document.getElementById('edit_max_volunteers').value = initiative.max_volunteers || '';
                        document.getElementById('edit_initiative_status').value = initiative.initiative_status || '';
                        document.getElementById('edit_start_date').value = initiative.start_date || '';
                        document.getElementById('edit_end_date').value = initiative.end_date || '';
                        document.getElementById('edit_registration_deadline').value = initiative.registration_deadline || '';
                        document.getElementById('edit_coordinator_name').value = initiative.coordinator_name || '';
                        document.getElementById('edit_coordinator_phone').value = initiative.coordinator_phone || '';
                        document.getElementById('edit_coordinator_email').value = initiative.coordinator_email || '';
                        document.getElementById('edit_location').value = initiative.location || '';
                        document.getElementById('edit_budget').value = initiative.budget || '';
                        document.getElementById('edit_status').value = initiative.status || '';
                        document.getElementById('edit_success_story').value = initiative.success_story || '';
                        document.getElementById('edit_impact_description').value = initiative.impact_description || '';
                        document.getElementById('edit_is_featured').checked = initiative.is_featured == 1;
                        document.getElementById('edit_is_active').checked = initiative.is_active == 1;
                        document.getElementById('edit_auto_approval').checked = initiative.auto_approval == 1;
                        
                        // جلب وعرض الصور الحالية
                        displayCurrentInitiativeImages(initiative.id);
                        
                        // إظهار النموذج
                        document.getElementById('editInitiativeModal').classList.remove('hidden');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('خطأ في جلب بيانات المبادرة');
                    });
            }

            // دالة إغلاق نموذج تعديل المبادرة
            function closeEditInitiativeModal(event) {
                if (event && event.target !== event.currentTarget) return;
                document.getElementById('editInitiativeModal').classList.add('hidden');
            }

            // دالة جلب وعرض صور المبادرة الحالية
            function displayCurrentInitiativeImages(initiativeId) {
                fetch(`get_initiative_images.php?id=${initiativeId}`)
                    .then(response => response.json())
                    .then(images => {
                        const currentImagesDiv = document.getElementById('currentImages');
                        const deleteImagesList = document.getElementById('deleteImagesList');
                        const deleteImagesSection = document.getElementById('deleteImagesSection');
                        
                        if (images.length === 0) {
                            currentImagesDiv.innerHTML = '<p class="text-gray-500 text-sm">لا توجد صور حالياً</p>';
                            deleteImagesSection.classList.add('hidden');
                            return;
                        }
                        
                        // عرض الصور الحالية
                        let imagesHTML = '<div class="mb-2"><h5 class="font-semibold text-gray-700">الصور الحالية:</h5></div>';
                        imagesHTML += '<div class="grid grid-cols-2 md:grid-cols-4 gap-2">';
                        
                        let deleteCheckboxes = '';
                        
                        images.forEach(image => {
                            const imageType = image.image_type === 'رئيسية' ? 'رئيسية' : 'معرض';
                            const typeColor = image.image_type === 'رئيسية' ? 'bg-blue-500' : 'bg-green-500';
                            
                            imagesHTML += `
                                <div class="relative">
                                    <img src="../${image.image_path}" 
                                         alt="${image.image_name}" 
                                         class="w-full h-20 object-cover rounded border">
                                    <span class="absolute top-0 right-0 ${typeColor} text-white rounded-full text-xs px-1">${imageType}</span>
                                </div>
                            `;
                            
                            if (image.image_type !== 'رئيسية') { // لا نسمح بحذف الصورة الرئيسية من هنا
                                deleteCheckboxes += `
                                    <label class="flex items-center p-2 border rounded hover:bg-gray-50">
                                        <input type="checkbox" name="delete_gallery_images[]" value="${image.id}" class="mr-2">
                                        <img src="../${image.image_path}" 
                                             alt="${image.image_name}" 
                                             class="w-8 h-8 object-cover rounded mr-2">
                                        <span class="text-xs">${image.image_name}</span>
                                    </label>
                                `;
                            }
                        });
                        
                        imagesHTML += '</div>';
                        currentImagesDiv.innerHTML = imagesHTML;
                        
                        // عرض خيارات الحذف إذا كان هناك صور معرض
                        if (deleteCheckboxes) {
                            deleteImagesList.innerHTML = deleteCheckboxes;
                            deleteImagesSection.classList.remove('hidden');
                        } else {
                            deleteImagesSection.classList.add('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching images:', error);
                        document.getElementById('currentImages').innerHTML = '<p class="text-red-500 text-sm">خطأ في جلب الصور</p>';
                    });
            }

            // دالة فتح نموذج تعديل الخبر
           // دالة فتح نموذج تعديل الخبر - نسخة مبسطة
function editNews(newsId) {
    console.log('🔍 جلب بيانات الخبر:', newsId);
    
    // إظهار النموذج
    document.getElementById('editNewsModal').classList.remove('hidden');
    
    // جلب بيانات الخبر
    fetch('get_news.php?id=' + newsId)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(news => {
            console.log('✅ بيانات الخبر المستلمة:', news);
            
            if (news.error) {
                throw new Error(news.error);
            }
            
            // ملء النموذج بالبيانات
            document.getElementById('edit_news_id').value = news.id;
            document.getElementById('edit_title').value = news.title || '';
            document.getElementById('edit_news_type').value = news.news_type || '';
            document.getElementById('edit_content').value = news.content || '';
            document.getElementById('edit_publish_date').value = news.publish_date || '';
            document.getElementById('edit_is_featured').checked = news.is_featured == 1;
            
            // عرض الصور الحالية
            displayCurrentImages(news);
        })
        .catch(error => {
            console.error('❌ Error:', error);
            alert('خطأ في جلب بيانات الخبر: ' + error.message);
            document.getElementById('editNewsModal').classList.add('hidden');
        });
}


           // دالة عرض الصور الحالية - محدثة للعناصر الموجودة فعلياً في HTML
function displayCurrentImages(news) {
    console.log('🖼️ عرض الصور الحالية:', news);
    
    // البحث عن العناصر الموجودة فعلياً في HTML
    let featuredImagePreview = document.getElementById('featuredImagePreview');
    let galleryImagesPreview = document.getElementById('galleryImagesPreview');
    
    // إذا لم توجد، نحاول البحث بأسماء أخرى
    if (!featuredImagePreview) {
        featuredImagePreview = document.getElementById('currentFeaturedImage');
    }
    if (!galleryImagesPreview) {
        galleryImagesPreview = document.getElementById('currentGalleryImages');
    }
    
    // إذا لم توجد أيضاً، نبحث في نموذج التعديل مباشرة
    if (!featuredImagePreview) {
        const modal = document.getElementById('editNewsModal');
        if (modal) {
            // إنشاء العناصر إذا لم توجد
            let imagesSection = modal.querySelector('#currentImagesSection');
            if (!imagesSection) {
                // البحث عن مكان في النموذج لإدراج قسم الصور
                const form = modal.querySelector('form');
                if (form) {
                    // إدراج قسم الصور قبل الأزرار
                    const buttonsDiv = form.querySelector('.flex.justify-end');
                    if (buttonsDiv) {
                        const imagesSectionHTML = `
                            <div id="currentImagesSection" class="mb-4">
                                <h4 class="font-bold text-gray-800 mb-3">🖼️ الصور الحالية</h4>
                                <div id="currentFeaturedImage" class="mb-4">
                                    <h5 class="font-semibold text-gray-700 mb-2">الصورة الرئيسية:</h5>
                                    <div id="featuredImagePreview"></div>
                                </div>
                                <div id="currentGalleryImages" class="mb-4">
                                    <h5 class="font-semibold text-gray-700 mb-2">صور المعرض:</h5>
                                    <div id="galleryImagesPreview" class="grid grid-cols-2 md:grid-cols-4 gap-2"></div>
                                </div>
                            </div>
                        `;
                        buttonsDiv.insertAdjacentHTML('beforebegin', imagesSectionHTML);
                        
                        // إعادة البحث عن العناصر الجديدة
                        featuredImagePreview = document.getElementById('featuredImagePreview');
                        galleryImagesPreview = document.getElementById('galleryImagesPreview');
                    }
                }
            } else {
                featuredImagePreview = imagesSection.querySelector('#featuredImagePreview');
                galleryImagesPreview = imagesSection.querySelector('#galleryImagesPreview');
            }
        }
    }
    
    // التحقق النهائي من وجود العناصر
    if (!featuredImagePreview || !galleryImagesPreview) {
        console.error('❌ لا يمكن إيجاد أو إنشاء عناصر عرض الصور');
        
        // كحل أخير، نستخدم أي div متاح في النموذج
        const modal = document.getElementById('editNewsModal');
        const anyDiv = modal ? modal.querySelector('div') : null;
        if (anyDiv) {
            anyDiv.insertAdjacentHTML('afterend', `
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded mb-4">
                    <h4 class="font-bold text-yellow-800 mb-3">🖼️ الصور الحالية</h4>
                    <div id="tempFeaturedPreview" class="mb-4"></div>
                    <div id="tempGalleryPreview"></div>
                </div>
            `);
            featuredImagePreview = document.getElementById('tempFeaturedPreview');
            galleryImagesPreview = document.getElementById('tempGalleryPreview');
        } else {
            alert('خطأ: لا يمكن عرض الصور في النموذج الحالي');
            return;
        }
    }
    
    console.log('✅ تم العثور على عناصر العرض:', {
        featuredImagePreview: !!featuredImagePreview,
        galleryImagesPreview: !!galleryImagesPreview
    });
    
    // عرض الصورة الرئيسية
    if (news.featured_image) {
        const imagePath = `../uploads/news/${news.featured_image}`;
        console.log('🖼️ مسار الصورة الرئيسية:', imagePath);
        
        featuredImagePreview.innerHTML = `
            <div class="relative inline-block">
                <img src="${imagePath}" 
                     alt="الصورة الرئيسية" 
                     class="h-20 w-20 object-cover rounded border hover:opacity-80 cursor-pointer"
                     onclick="window.open('${imagePath}', '_blank')"
                     onload="console.log('✅ تم تحميل الصورة الرئيسية بنجاح:', '${imagePath}')"
                     onerror="handleImageError(this, 'الصورة الرئيسية', '${imagePath}')">
                <span class="absolute top-0 right-0 bg-green-500 text-white rounded-full text-xs px-1">رئيسية</span>
            </div>
        `;
    } else {
        featuredImagePreview.innerHTML = '<p class="text-gray-500 text-sm">لا توجد صورة رئيسية</p>';
    }
    
    // عرض صور المعرض
    let galleryHTML = '';
    let hasGalleryImages = false;
    
    if (news.gallery_images_data && news.gallery_images_data.length > 0) {
        console.log('🖼️ صور المعرض (بيانات مفصلة):', news.gallery_images_data);
        hasGalleryImages = true;
        
        news.gallery_images_data.forEach((image, index) => {
            const imagePath = `../uploads/news/${image.image_path || image.image_filename}`;
            const imageName = image.image_name || image.image_title || `صورة ${index + 1}`;
            const imageId = image.id;
            
            console.log(`🖼️ صورة المعرض ${index + 1}:`, {
                path: imagePath,
                name: imageName,
                id: imageId
            });
            
            galleryHTML += `
                <div class="relative inline-block m-1">
                    <img src="${imagePath}" 
                         alt="${imageName}" 
                         class="h-16 w-16 object-cover rounded border hover:opacity-80 cursor-pointer"
                         onclick="window.open('${imagePath}', '_blank')"
                         onload="console.log('✅ تم تحميل صورة المعرض ${index + 1} بنجاح')"
                         onerror="handleImageError(this, 'صورة المعرض ${index + 1}', '${imagePath}')">
                    <button type="button" 
                            onclick="removeGalleryImageById(${imageId}, this)" 
                            class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full text-xs w-5 h-5 flex items-center justify-center hover:bg-red-600"
                            title="حذف: ${imageName}">
                        ×
                    </button>
                </div>
            `;
        });
        
    } else if (news.gallery_images && news.gallery_images.length > 0) {
        console.log('🖼️ صور المعرض (مصفوفة بسيطة):', news.gallery_images);
        hasGalleryImages = true;
        
        news.gallery_images.forEach((imagePath, index) => {
            if (imagePath && imagePath.trim() !== '') {
                const fullImagePath = `../uploads/news/${imagePath}`;
                
                galleryHTML += `
                    <div class="relative inline-block m-1">
                        <img src="${fullImagePath}" 
                             alt="صورة المعرض ${index + 1}" 
                             class="h-16 w-16 object-cover rounded border hover:opacity-80 cursor-pointer"
                             onclick="window.open('${fullImagePath}', '_blank')"
                             onload="console.log('✅ تم تحميل صورة المعرض ${index + 1} بنجاح')"
                             onerror="handleImageError(this, 'صورة المعرض ${index + 1}', '${fullImagePath}')">
                        <button type="button" 
                                onclick="removeGalleryImage('${imagePath}', this)" 
                                class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full text-xs w-5 h-5 flex items-center justify-center hover:bg-red-600"
                                title="حذف صورة ${index + 1}">
                            ×
                        </button>
                    </div>
                `;
            }
        });
    }
    
    if (!hasGalleryImages) {
        galleryHTML = '<p class="text-gray-500 text-sm">لا توجد صور في المعرض</p>';
    }
    
    galleryImagesPreview.innerHTML = galleryHTML;
    
    // عرض معلومات التشخيص
    if (news.debug_info) {
        console.log('🔧 معلومات التشخيص:', news.debug_info);
    }
    
    console.log('✅ تم عرض الصور بنجاح');
}

// دالة معالجة أخطاء تحميل الصور - محدثة
function handleImageError(imgElement, imageName, imagePath) {
    console.error(`❌ فشل تحميل ${imageName}:`, imagePath);
    
    // إخفاء الصورة وإظهار رسالة خطأ
    imgElement.style.display = 'none';
    
    // إنشاء عنصر الخطأ
    const errorDiv = document.createElement('div');
    errorDiv.className = 'bg-red-50 border border-red-200 rounded p-2 text-red-700 text-xs text-center';
    errorDiv.style.width = '64px';
    errorDiv.style.height = '64px';
    errorDiv.style.display = 'flex';
    errorDiv.style.flexDirection = 'column';
    errorDiv.style.justifyContent = 'center';
    errorDiv.innerHTML = `
        <div class="font-semibold">❌</div>
        <div class="text-xs">غير موجود</div>
    `;
    
    // إدراج رسالة الخطأ بدلاً من الصورة
    imgElement.parentNode.insertBefore(errorDiv, imgElement);
}

// دالة حذف صورة من المعرض بالمعرف - محدثة
function removeGalleryImageById(imageId, buttonElement) {
    if (confirm('هل أنت متأكد من حذف هذه الصورة؟')) {
        const form = document.getElementById('editNewsForm');
        if (form) {
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_gallery_images[]';
            deleteInput.value = imageId;
            form.appendChild(deleteInput);
            
            console.log('🗑️ تمت إضافة الصورة لقائمة الحذف (ID):', imageId);
        }
        
        // إزالة الصورة من العرض
        const imageContainer = buttonElement.closest('.relative');
        if (imageContainer) {
            imageContainer.remove();
        }
    }
}

// دالة حذف صورة من المعرض (النظام القديم) - محدثة
function removeGalleryImage(imageName, buttonElement) {
    if (confirm('هل أنت متأكد من حذف هذه الصورة؟')) {
        const form = document.getElementById('editNewsForm');
        if (form) {
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_gallery_images[]';
            deleteInput.value = imageName;
            form.appendChild(deleteInput);
            
            console.log('🗑️ تمت إضافة الصورة لقائمة الحذف (اسم):', imageName);
        }
        
        // إزالة الصورة من العرض
        const imageContainer = buttonElement.closest('.relative');
        if (imageContainer) {
            imageContainer.remove();
        }
    }
}

// دالة إغلاق نموذج تعديل الخبر - محدثة
function closeEditNewsModal(event) {
    if (event && event.target !== event.currentTarget) return;
    
    document.getElementById('editNewsModal').classList.add('hidden');
    
    // إزالة حقول الحذف المضافة ديناميكياً
    const deleteInputs = document.querySelectorAll('input[name="delete_gallery_images[]"]');
    deleteInputs.forEach(input => input.remove());
    
    console.log('🔒 تم إغلاق نموذج تعديل الخبر وتنظيف حقول الحذف');
}
        </script>
        
    </div>
    
    <!-- أنواع الطلبات -->
    <div id="request_types" class="tab-content <?= $active_tab == 'request_types' ? 'active' : '' ?>">
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold">📋 إدارة أنواع الطلبات</h3>
                <button onclick="toggleForm('requestTypeForm')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    ➕ إضافة نوع طلب جديد
                </button>
            </div>

            <!-- نموذج إضافة نوع طلب جديد -->
            <div id="requestTypeForm" class="hidden mb-6 p-4 border rounded-lg bg-gray-50">
                <form method="POST">
                    <input type="hidden" name="action" value="add_request_type">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم نوع الطلب (مفتاح) *</label>
                            <input type="text" name="type_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="مثال: إفادة سكن" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربية *</label>
                            <input type="text" name="name_ar" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="مثال: إفادة سكن" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزية</label>
                            <input type="text" name="name_en" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="مثال: Housing Certificate">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label>
                            <input type="number" name="display_order" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0" value="999">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">وصف نوع الطلب</label>
                        <textarea name="type_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="وصف تفصيلي لنوع الطلب"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">كلفة الطلب</label>
                            <input type="number" name="cost" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0" value="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">عملة الكلفة</label>
                            <select name="cost_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= $currency['id'] ?>" <?= $currency['id'] == $default_currency_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">المستندات المطلوبة</label>
                        <div id="documentsContainer" class="space-y-2">
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <input type="text" name="required_documents[]" class="flex-1 px-3 py-2 border border-gray-300 rounded-md" placeholder="اسم المستند المطلوب">
                                <button type="button" onclick="addDocumentField()" class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">+</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" class="mr-2" checked>
                            <span class="text-sm text-gray-700">نشط</span>
                        </label>
                    </div>
                    
                    <div class="flex justify-end space-x-3 space-x-reverse">
                        <button type="button" onclick="toggleForm('requestTypeForm')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">إضافة النوع</button>
                    </div>
                </form>
            </div>

            <!-- قائمة أنواع الطلبات -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($request_types as $type): ?>
                    <div class="bg-white border rounded-lg p-4 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($type['name_ar']) ?></h4>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $type['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $type['is_active'] ? 'نشط' : 'غير نشط' ?>
                            </span>
                        </div>
                        
                        <?php if ($type['name_en']): ?>
                            <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($type['name_en']) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($type['type_description']): ?>
                            <p class="text-sm text-gray-700 mb-3"><?= htmlspecialchars($type['type_description']) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($type['cost'] > 0): ?>
                            <div class="text-sm font-medium text-green-600 mb-2">
                                💰 الكلفة: <?= number_format($type['cost'], 2) ?> <?= htmlspecialchars($type['currency_symbol'] ?? 'د.ع') ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($type['required_documents']): ?>
                            <?php 
                            $documents = json_decode($type['required_documents'], true);
                            if (is_array($documents) && !empty($documents)): 
                            ?>
                                <div class="text-sm text-gray-600 mb-3">
                                    <span class="font-medium">📎 المستندات المطلوبة:</span>
                                    <ul class="mt-1 space-y-1">
                                        <?php foreach ($documents as $document): ?>
                                            <?php if (!empty($document)): ?>
                                                <li>• <?= htmlspecialchars($document) ?></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="text-xs text-gray-500 mb-3">
                            <span>الترتيب: <?= $type['display_order'] ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div class="flex space-x-2 space-x-reverse">
                                <button onclick="editRequestType(<?= $type['id'] ?>)" class="text-blue-600 hover:text-blue-800">✏️ تعديل</button>
                            </div>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا النوع؟')">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="table" value="request_types">
                                <input type="hidden" name="id" value="<?= $type['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">🗑️ حذف</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- نموذج تعديل نوع الطلب -->
    <div id="editRequestTypeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">✏️ تعديل نوع الطلب</h3>
                        <button onclick="closeEditRequestTypeModal()" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <form id="editRequestTypeForm" method="POST">
                        <input type="hidden" name="action" value="edit_request_type">
                        <input type="hidden" name="request_type_id" id="edit_request_type_id">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اسم نوع الطلب (مفتاح) *</label>
                                <input type="text" name="type_name" id="edit_type_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربية *</label>
                                <input type="text" name="name_ar" id="edit_name_ar" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزية</label>
                                <input type="text" name="name_en" id="edit_name_en" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label>
                                <input type="number" name="display_order" id="edit_display_order" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">وصف نوع الطلب</label>
                            <textarea name="type_description" id="edit_type_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">كلفة الطلب</label>
                                <input type="number" name="cost" id="edit_cost" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md" min="0">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">عملة الكلفة</label>
                                <select name="cost_currency_id" id="edit_cost_currency_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?= $currency['id'] ?>">
                                            <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">المستندات المطلوبة</label>
                            <div id="editDocumentsContainer" class="space-y-2">
                                <!-- سيتم ملء هذا القسم بواسطة JavaScript -->
                            </div>
                            <button type="button" onclick="addEditDocumentField()" class="mt-2 px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">+ إضافة مستند</button>
                        </div>
                        
                        <div class="flex items-center mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" id="edit_is_active" class="mr-2">
                                <span class="text-sm text-gray-700">نشط</span>
                            </label>
                        </div>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse">
                            <button type="button" onclick="closeEditRequestTypeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">إلغاء</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">تحديث النوع</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // دالة إضافة حقل مستند جديد
    function addDocumentField() {
        const container = document.getElementById('documentsContainer');
        const newField = document.createElement('div');
        newField.className = 'flex items-center space-x-2 space-x-reverse';
        newField.innerHTML = `
            <input type="text" name="required_documents[]" class="flex-1 px-3 py-2 border border-gray-300 rounded-md" placeholder="اسم المستند المطلوب">
            <button type="button" onclick="removeDocumentField(this)" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">-</button>
        `;
        container.appendChild(newField);
    }

    // دالة حذف حقل مستند
    function removeDocumentField(button) {
        button.parentElement.remove();
    }

    // دالة تعديل نوع الطلب
    function editRequestType(typeId) {
        console.log('editRequestType called with ID:', typeId);
        
        // جلب بيانات نوع الطلب
        const requestTypes = <?= json_encode($request_types) ?>;
        console.log('Available request types:', requestTypes);
        
        const requestType = requestTypes.find(type => type.id == typeId);
        console.log('Found request type:', requestType);
        
        if (requestType) {
            try {
                // ملء النموذج بالبيانات
                document.getElementById('edit_request_type_id').value = requestType.id;
                document.getElementById('edit_type_name').value = requestType.type_name || '';
                document.getElementById('edit_name_ar').value = requestType.name_ar || '';
                document.getElementById('edit_name_en').value = requestType.name_en || '';
                document.getElementById('edit_display_order').value = requestType.display_order || 999;
                document.getElementById('edit_type_description').value = requestType.type_description || '';
                document.getElementById('edit_cost').value = requestType.cost || 0;
                document.getElementById('edit_cost_currency_id').value = requestType.cost_currency_id || <?= $default_currency_id ?>;
                document.getElementById('edit_is_active').checked = requestType.is_active == 1;
                
                console.log('Form fields filled successfully');
                
                // ملء المستندات
                const documentsContainer = document.getElementById('editDocumentsContainer');
                documentsContainer.innerHTML = '';
                
                let documents = [];
                if (requestType.required_documents) {
                    try {
                        if (typeof requestType.required_documents === 'string') {
                            documents = JSON.parse(requestType.required_documents);
                        } else if (Array.isArray(requestType.required_documents)) {
                            documents = requestType.required_documents;
                        }
                    } catch (e) {
                        console.error('Error parsing required_documents:', e);
                        documents = [];
                    }
                }
                
                if (documents.length === 0) {
                    documents = [''];
                }
                
                documents.forEach(doc => {
                    addEditDocumentField(doc);
                });
                
                console.log('Documents added:', documents);
                
                // إظهار النموذج
                document.getElementById('editRequestTypeModal').classList.remove('hidden');
                console.log('Modal shown');
                
            } catch (error) {
                console.error('Error in editRequestType:', error);
                alert('خطأ في تحميل بيانات الطلب: ' + error.message);
            }
        } else {
            console.error('Request type not found for ID:', typeId);
            alert('لم يتم العثور على نوع الطلب');
        }
    }

    // دالة إضافة حقل مستند في نموذج التعديل
    function addEditDocumentField(value = '') {
        const container = document.getElementById('editDocumentsContainer');
        const newField = document.createElement('div');
        newField.className = 'flex items-center space-x-2 space-x-reverse';
        newField.innerHTML = `
            <input type="text" name="required_documents[]" value="${value}" class="flex-1 px-3 py-2 border border-gray-300 rounded-md" placeholder="اسم المستند المطلوب">
            <button type="button" onclick="removeEditDocumentField(this)" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">-</button>
        `;
        container.appendChild(newField);
        console.log('Edit document field added with value:', value);
    }

    // دالة حذف حقل مستند في نموذج التعديل
    function removeEditDocumentField(button) {
        button.parentElement.remove();
        console.log('Edit document field removed');
    }

    // دالة إغلاق نموذج التعديل
    function closeEditRequestTypeModal() {
        document.getElementById('editRequestTypeModal').classList.add('hidden');
        console.log('Edit modal closed');
    }
    
    // التحقق من تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded, request types available:', <?= json_encode(count($request_types)) ?> + ' items');
        
        // إضافة event listener للنموذج
        const editForm = document.getElementById('editRequestTypeForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                console.log('Edit form submitted');
                
                // التحقق من الحقول المطلوبة
                const typeName = document.getElementById('edit_type_name').value.trim();
                const nameAr = document.getElementById('edit_name_ar').value.trim();
                
                if (!typeName || !nameAr) {
                    e.preventDefault();
                    alert('يرجى ملء الحقول المطلوبة');
                    return false;
                }
                
                console.log('Form validation passed');
                return true;
            });
        }
    });
    </script>
</div>
</body>
</html> 
