<?php
header('Content-Type: text/html; charset=utf-8');

// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../comprehensive_dashboard.php?error=no_permission');
    exit();
}

// إنشاء الاتصال بقاعدة البيانات
$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// إنشاء الجداول المطلوبة إذا لم تكن موجودة
try {
    // جدول اللجان البلدية
    $db->exec("
    CREATE TABLE IF NOT EXISTS municipal_committees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        committee_name VARCHAR(255) NOT NULL UNIQUE,
        committee_description TEXT,
        department_id INT,
        committee_type ENUM('دائمة', 'مؤقتة', 'استشارية', 'تنفيذية') DEFAULT 'دائمة',
        chairman_id INT,
        secretary_id INT,
        formation_date DATE,
        dissolution_date DATE NULL,
        is_active TINYINT(1) DEFAULT 1,
        meeting_frequency ENUM('أسبوعية', 'شهرية', 'ربع سنوية', 'حسب الحاجة') DEFAULT 'شهرية',
        responsibilities TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (chairman_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (secretary_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // جدول أعضاء اللجان
    $db->exec("
    CREATE TABLE IF NOT EXISTS committee_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        committee_id INT NOT NULL,
        user_id INT NOT NULL,
        member_role ENUM('رئيس', 'نائب الرئيس', 'سكرتير', 'عضو', 'مقرر') DEFAULT 'عضو',
        join_date DATE NOT NULL,
        leave_date DATE NULL,
        is_active TINYINT(1) DEFAULT 1,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (committee_id) REFERENCES municipal_committees(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // جدول الجلسات البلدية
    $db->exec("
    CREATE TABLE IF NOT EXISTS municipal_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_number VARCHAR(50) NOT NULL,
        session_title VARCHAR(255) NOT NULL,
        session_type ENUM('عادية', 'طارئة', 'استثنائية', 'لجنة') DEFAULT 'عادية',
        committee_id INT NULL,
        session_date DATE NOT NULL,
        session_time TIME NOT NULL,
        location VARCHAR(255) DEFAULT 'قاعة الاجتماعات الرئيسية',
        agenda TEXT NOT NULL,
        session_status ENUM('مجدولة', 'جارية', 'مكتملة', 'مؤجلة', 'ملغية') DEFAULT 'مجدولة',
        chairperson_id INT,
        secretary_id INT,
        quorum_required INT DEFAULT 5,
        attendees_count INT DEFAULT 0,
        session_minutes TEXT,
        attachments JSON,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (committee_id) REFERENCES municipal_committees(id) ON DELETE SET NULL,
        FOREIGN KEY (chairperson_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (secretary_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // جدول دعوات الجلسات
    $db->exec("
    CREATE TABLE IF NOT EXISTS session_invitations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        invitee_id INT NOT NULL,
        invitation_type ENUM('عضو', 'ضيف', 'خبير', 'مراقب') DEFAULT 'عضو',
        sent_at TIMESTAMP NULL,
        response_status ENUM('لم يرد', 'موافق', 'اعتذار', 'مؤجل') DEFAULT 'لم يرد',
        response_date TIMESTAMP NULL,
        attendance_status ENUM('غير محدد', 'حاضر', 'غائب', 'متأخر') DEFAULT 'غير محدد',
        attendance_time TIMESTAMP NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES municipal_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // جدول القرارات البلدية
    $db->exec("
    CREATE TABLE IF NOT EXISTS municipal_decisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        decision_number VARCHAR(100) NOT NULL UNIQUE,
        session_id INT NOT NULL,
        decision_title VARCHAR(255) NOT NULL,
        decision_text TEXT NOT NULL,
        decision_type ENUM('إداري', 'مالي', 'فني', 'قانوني', 'تنظيمي', 'أخرى') DEFAULT 'إداري',
        priority_level ENUM('عادي', 'مهم', 'عاجل', 'طارئ') DEFAULT 'عادي',
        decision_category VARCHAR(100),
        voting_result ENUM('بالإجماع', 'بالأغلبية', 'مرفوض', 'مؤجل') DEFAULT 'بالأغلبية',
        votes_for INT DEFAULT 0,
        votes_against INT DEFAULT 0,
        votes_abstain INT DEFAULT 0,
        implementation_deadline DATE NULL,
        responsible_department_id INT,
        responsible_person_id INT,
        implementation_status ENUM('قيد الانتظار', 'قيد التنفيذ', 'مكتمل', 'متأخر', 'معلق', 'ملغي') DEFAULT 'قيد الانتظار',
        implementation_progress INT DEFAULT 0,
        implementation_notes TEXT,
        budget_required DECIMAL(15,2) DEFAULT 0,
        legal_review_required TINYINT(1) DEFAULT 0,
        legal_review_status ENUM('غير مطلوب', 'قيد المراجعة', 'موافق عليه', 'يحتاج تعديل') DEFAULT 'غير مطلوب',
        related_project_id INT NULL,
        attachments JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES municipal_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (responsible_department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (responsible_person_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // جدول متابعة تنفيذ القرارات
    $db->exec("
    CREATE TABLE IF NOT EXISTS decision_follow_ups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        decision_id INT NOT NULL,
        follow_up_date DATE NOT NULL,
        follow_up_by INT NOT NULL,
        status_update ENUM('قيد الانتظار', 'قيد التنفيذ', 'مكتمل', 'متأخر', 'معلق', 'ملغي'),
        progress_percentage INT DEFAULT 0,
        notes TEXT,
        obstacles TEXT,
        next_action TEXT,
        next_follow_up_date DATE,
        attachments JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (decision_id) REFERENCES municipal_decisions(id) ON DELETE CASCADE,
        FOREIGN KEY (follow_up_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // إضافة اللجان الأساسية إذا لم تكن موجودة
    $committees_check = $db->query("SELECT COUNT(*) as count FROM municipal_committees")->fetch();
    if ($committees_check['count'] == 0) {
        $committees = [
            ['لجنة الشؤون المالية', 'مراجعة الميزانيات والأمور المالية للبلدية', 'دائمة', 'شهرية', 'مراجعة الميزانية، الموافقة على المصروفات الكبيرة، مراقبة الأداء المالي'],
            ['لجنة التخطيط والتطوير', 'التخطيط للمشاريع التطويرية ومتابعة تنفيذها', 'دائمة', 'شهرية', 'وضع خطط التطوير، مراجعة المشاريع، متابعة التنفيذ'],
            ['لجنة البيئة والنظافة', 'الإشراف على شؤون البيئة والنظافة العامة', 'دائمة', 'شهرية', 'مراقبة النظافة العامة، حماية البيئة، إدارة النفايات'],
            ['لجنة الخدمات العامة', 'الإشراف على الخدمات المقدمة للمواطنين', 'دائمة', 'شهرية', 'تطوير الخدمات، متابعة جودة الخدمة، حل الشكاوى'],
            ['لجنة الطوارئ', 'التعامل مع الحالات الطارئة والأزمات', 'مؤقتة', 'حسب الحاجة', 'إدارة الأزمات، التعامل مع الطوارئ، وضع خطط الطوارئ']
        ];

        $stmt = $db->prepare("INSERT INTO municipal_committees (committee_name, committee_description, committee_type, meeting_frequency, responsibilities, formation_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
        foreach ($committees as $committee) {
            $stmt->execute($committee);
        }
    }

} catch (Exception $e) {
    // إذا حدث خطأ في إنشاء الجداول، نتجاهله ونكمل
}

$success_message = '';
$error_message = '';

// تحديد الصفحة الحالية
$current_page = $_GET['page'] ?? 'departments';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error_message = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // إجراءات الأقسام
        if ($action == 'add_department') {
            $department_name = trim($_POST['department_name']);
            $department_description = trim($_POST['department_description']);
            $department_manager = trim($_POST['department_manager']);
            
            if (!empty($department_name)) {
                try {
                    $stmt = $db->prepare("INSERT INTO departments (department_name, department_description, department_manager) VALUES (?, ?, ?)");
                    $stmt->execute([$department_name, $department_description, $department_manager]);
                    $success_message = "تم إضافة القسم '$department_name' بنجاح";
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $error_message = "خطأ: اسم القسم '$department_name' موجود مسبقاً";
                    } else {
                        $error_message = "خطأ في إضافة القسم: " . $e->getMessage();
                    }
                }
            } else {
                $error_message = "اسم القسم مطلوب";
            }
        }
        
        elseif ($action == 'edit_department') {
            $id = $_POST['id'];
            $department_name = trim($_POST['department_name']);
            $department_description = trim($_POST['department_description']);
            $department_manager = trim($_POST['department_manager']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (!empty($department_name)) {
                try {
                    $stmt = $db->prepare("UPDATE departments SET department_name = ?, department_description = ?, department_manager = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$department_name, $department_description, $department_manager, $is_active, $id]);
                    $success_message = "تم تحديث القسم '$department_name' بنجاح";
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $error_message = "خطأ: اسم القسم '$department_name' موجود مسبقاً";
                    } else {
                        $error_message = "خطأ في تحديث القسم: " . $e->getMessage();
                    }
                }
            }
        }
        
        elseif ($action == 'delete_department') {
            $id = $_POST['id'];
            
            try {
                $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE department_id = ?");
                $check_stmt->execute([$id]);
                $count = $check_stmt->fetch()['count'];
                
                if ($count > 0) {
                    $error_message = "لا يمكن حذف القسم لأنه يحتوي على $count موظف/موظفين";
                } else {
                    $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_message = "تم حذف القسم بنجاح";
                }
            } catch (Exception $e) {
                $error_message = "خطأ في حذف القسم: " . $e->getMessage();
            }
        }
    
    // إجراءات اللجان
    elseif ($action == 'add_committee') {
        $committee_name = trim($_POST['committee_name']);
        $committee_description = trim($_POST['committee_description']);
        $department_id = $_POST['department_id'] ? $_POST['department_id'] : null;
        $committee_type = $_POST['committee_type'];
        $meeting_frequency = $_POST['meeting_frequency'];
        $responsibilities = trim($_POST['responsibilities']);
        $formation_date = $_POST['formation_date'];
        
        if (!empty($committee_name)) {
            try {
                $stmt = $db->prepare("INSERT INTO municipal_committees (committee_name, committee_description, department_id, committee_type, meeting_frequency, responsibilities, formation_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$committee_name, $committee_description, $department_id, $committee_type, $meeting_frequency, $responsibilities, $formation_date]);
                $success_message = "تم إضافة اللجنة '$committee_name' بنجاح";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error_message = "خطأ: اسم اللجنة '$committee_name' موجود مسبقاً";
                } else {
                    $error_message = "خطأ في إضافة اللجنة: " . $e->getMessage();
                }
            }
        }
    }
    
    elseif ($action == 'add_committee_member') {
        $committee_id = $_POST['committee_id'];
        $user_id = $_POST['user_id'];
        $member_role = $_POST['member_role'];
        $join_date = $_POST['join_date'];
        $notes = trim($_POST['notes']);
        
        try {
            // التحقق من أن العضو ليس مضافاً مسبقاً
            $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM committee_members WHERE committee_id = ? AND user_id = ? AND is_active = 1");
            $check_stmt->execute([$committee_id, $user_id]);
            $exists = $check_stmt->fetch()['count'];
            
            if ($exists > 0) {
                $error_message = "هذا العضو مضاف مسبقاً إلى اللجنة";
            } else {
                $stmt = $db->prepare("INSERT INTO committee_members (committee_id, user_id, member_role, join_date, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$committee_id, $user_id, $member_role, $join_date, $notes]);
                $success_message = "تم إضافة العضو إلى اللجنة بنجاح";
            }
        } catch (Exception $e) {
            $error_message = "خطأ في إضافة العضو: " . $e->getMessage();
        }
    }
    
    // إجراءات الجلسات
    elseif ($action == 'add_session') {
        $session_number = trim($_POST['session_number']);
        $session_title = trim($_POST['session_title']);
        $session_type = $_POST['session_type'];
        $committee_id = $_POST['committee_id'] ? $_POST['committee_id'] : null;
        $session_date = $_POST['session_date'];
        $session_time = $_POST['session_time'];
        $location = trim($_POST['location']);
        $agenda = trim($_POST['agenda']);
        $chairperson_id = $_POST['chairperson_id'] ? $_POST['chairperson_id'] : null;
        $secretary_id = $_POST['secretary_id'] ? $_POST['secretary_id'] : null;
        $quorum_required = $_POST['quorum_required'];
        
        if (!empty($session_number) && !empty($session_title) && !empty($agenda)) {
            try {
                $stmt = $db->prepare("INSERT INTO municipal_sessions (session_number, session_title, session_type, committee_id, session_date, session_time, location, agenda, chairperson_id, secretary_id, quorum_required, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$session_number, $session_title, $session_type, $committee_id, $session_date, $session_time, $location, $agenda, $chairperson_id, $secretary_id, $quorum_required, $auth->getCurrentUser()['id']]);
                $success_message = "تم إضافة الجلسة '$session_title' بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة الجلسة: " . $e->getMessage();
            }
        } else {
            $error_message = "جميع الحقول المطلوبة يجب ملؤها";
        }
    }
    
    // إجراءات القرارات
    elseif ($action == 'add_decision') {
        $decision_number = trim($_POST['decision_number']);
        $session_id = $_POST['session_id'];
        $decision_title = trim($_POST['decision_title']);
        $decision_text = trim($_POST['decision_text']);
        $decision_type = $_POST['decision_type'];
        $priority_level = $_POST['priority_level'];
        $voting_result = $_POST['voting_result'];
        $votes_for = $_POST['votes_for'] ?? 0;
        $votes_against = $_POST['votes_against'] ?? 0;
        $votes_abstain = $_POST['votes_abstain'] ?? 0;
        $implementation_deadline = $_POST['implementation_deadline'] ? $_POST['implementation_deadline'] : null;
        $responsible_department_id = $_POST['responsible_department_id'] ? $_POST['responsible_department_id'] : null;
        $responsible_person_id = $_POST['responsible_person_id'] ? $_POST['responsible_person_id'] : null;
        $budget_required = $_POST['budget_required'] ?? 0;
        
        if (!empty($decision_number) && !empty($decision_title) && !empty($decision_text)) {
            try {
                $stmt = $db->prepare("INSERT INTO municipal_decisions (decision_number, session_id, decision_title, decision_text, decision_type, priority_level, voting_result, votes_for, votes_against, votes_abstain, implementation_deadline, responsible_department_id, responsible_person_id, budget_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$decision_number, $session_id, $decision_title, $decision_text, $decision_type, $priority_level, $voting_result, $votes_for, $votes_against, $votes_abstain, $implementation_deadline, $responsible_department_id, $responsible_person_id, $budget_required]);
                $success_message = "تم إضافة القرار '$decision_title' بنجاح";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error_message = "خطأ: رقم القرار '$decision_number' موجود مسبقاً";
                } else {
                    $error_message = "خطأ في إضافة القرار: " . $e->getMessage();
                }
            }
        } else {
            $error_message = "جميع الحقول المطلوبة يجب ملؤها";
        }
    }
}

// جلب البيانات
$departments = $db->query("SELECT d.*, COUNT(u.id) as employee_count FROM departments d LEFT JOIN users u ON d.id = u.department_id GROUP BY d.id ORDER BY d.department_name")->fetchAll();

$committees = $db->query("
    SELECT c.*, 
           d.department_name,
           ch.full_name as chairman_name,
           s.full_name as secretary_name,
           COUNT(cm.id) as members_count
    FROM municipal_committees c 
    LEFT JOIN departments d ON c.department_id = d.id 
    LEFT JOIN users ch ON c.chairman_id = ch.id
    LEFT JOIN users s ON c.secretary_id = s.id
    LEFT JOIN committee_members cm ON c.id = cm.committee_id AND cm.is_active = 1
    GROUP BY c.id 
    ORDER BY c.committee_name
")->fetchAll();

$sessions = $db->query("
    SELECT s.*, 
           c.committee_name,
           ch.full_name as chairperson_name,
           sec.full_name as secretary_name
    FROM municipal_sessions s 
    LEFT JOIN municipal_committees c ON s.committee_id = c.id 
    LEFT JOIN users ch ON s.chairperson_id = ch.id
    LEFT JOIN users sec ON s.secretary_id = sec.id
    ORDER BY s.session_date DESC, s.session_time DESC
    LIMIT 20
")->fetchAll();

$decisions = $db->query("
    SELECT d.*, 
           s.session_title,
           s.session_number,
           dept.department_name as responsible_department,
           u.full_name as responsible_person_name
    FROM municipal_decisions d 
    LEFT JOIN municipal_sessions s ON d.session_id = s.id 
    LEFT JOIN departments dept ON d.responsible_department_id = dept.id
    LEFT JOIN users u ON d.responsible_person_id = u.id
    ORDER BY d.created_at DESC
    LIMIT 20
")->fetchAll();

$users = $db->query("SELECT id, full_name, department_id FROM users ORDER BY full_name")->fetchAll();

// للتعديل
$edit_department = null;
if (isset($_GET['edit_dept'])) {
    $edit_id = $_GET['edit_dept'];
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_department = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة البلدية الشامل - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Cairo', sans-serif; 
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100" x-data="{ activeTab: '<?= $current_page ?>' }">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">
                        🏛️
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">نظام إدارة البلدية الشامل</h1>
                        <p class="text-sm text-gray-500">الهيكل الإداري • اللجان • الجلسات • القرارات</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../comprehensive_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition duration-200">
                        🏠 العودة إلى لوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">نظام إدارة البلدية الشامل</h1>
            <p class="text-slate-600">إدارة شاملة للهيكل الإداري، اللجان، الجلسات والقرارات البلدية</p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <div class="flex">
                    <div class="py-1">
                        <svg class="fill-current h-6 w-6 text-green-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold">نجح!</p>
                        <p class="text-sm"><?php echo $success_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex">
                    <div class="py-1">
                        <svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold">خطأ!</p>
                        <p class="text-sm"><?php echo $error_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 space-x-reverse px-6">
                    <button @click="activeTab = 'departments'" 
                            :class="activeTab === 'departments' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        🏢 الهيكل الإداري
                    </button>
                    <button @click="activeTab = 'committees'" 
                            :class="activeTab === 'committees' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        👥 إدارة اللجان
                    </button>
                    <button @click="activeTab = 'sessions'" 
                            :class="activeTab === 'sessions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        📅 الجلسات البلدية
                    </button>
                    <button @click="activeTab = 'decisions'" 
                            :class="activeTab === 'decisions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        📋 القرارات البلدية
                    </button>
                    <button @click="activeTab = 'reports'" 
                            :class="activeTab === 'reports' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        📊 التقارير
                    </button>
                </nav>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="space-y-6">
            <?php if ($current_page === 'departments'): ?>
                <!-- Departments Content -->
                <div class="bg-white shadow rounded-lg p-6 card-hover">
                    <h3 class="text-lg font-bold text-slate-800 mb-6">
                        <?php echo $edit_department ? '✏️ تعديل القسم' : '➕ إضافة قسم جديد'; ?>
                    </h3>
                    
                    <form method="POST" class="space-y-4">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="<?php echo $edit_department ? 'edit_department' : 'add_department'; ?>">
                        <?php if ($edit_department): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_department['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">🏷️ اسم القسم *</label>
                                <input type="text" name="department_name" 
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       required 
                                       value="<?php echo $edit_department ? htmlspecialchars($edit_department['department_name']) : ''; ?>"
                                       placeholder="مثال: الإدارة المالية">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">👔 مدير القسم</label>
                                <input type="text" name="department_manager" 
                                       class="form-input w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       value="<?php echo $edit_department ? htmlspecialchars($edit_department['department_manager']) : ''; ?>"
                                       placeholder="اسم مدير القسم">
                            </div>
                        </div>
                        
                        <div class="<?php echo $edit_department ? 'grid grid-cols-1 md:grid-cols-3 gap-4' : ''; ?>">
                            <div class="<?php echo $edit_department ? 'md:col-span-2' : ''; ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-2">📝 وصف القسم</label>
                                <textarea name="department_description" rows="3"
                                          class="form-input w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="وصف مختصر عن مهام ومسؤوليات القسم"><?php echo $edit_department ? htmlspecialchars($edit_department['department_description']) : ''; ?></textarea>
                            </div>
                            
                            <?php if ($edit_department): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">🔄 حالة القسم</label>
                                <div class="flex items-center mt-4">
                                    <input type="checkbox" name="is_active" id="is_active"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                           <?php echo $edit_department['is_active'] ? 'checked' : ''; ?>>
                                    <label for="is_active" class="mr-2 block text-sm text-gray-900">قسم نشط</label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse pt-4">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200 card-hover">
                                <?php echo $edit_department ? '💾 حفظ التعديلات' : '➕ إضافة القسم'; ?>
                            </button>
                            
                            <?php if ($edit_department): ?>
                                <a href="departments.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                                    ❌ إلغاء التعديل
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- جدول الأقسام -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">قائمة الأقسام</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">🏢 اسم القسم</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">👥 عدد الموظفين</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">👔 مدير القسم</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">🔄 الحالة</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">📅 تاريخ الإنشاء</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">⚙️ الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($departments as $dept): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#<?php echo $dept['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                            <?php if ($dept['department_description']): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($dept['department_description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo $dept['employee_count']; ?> موظف
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($dept['department_manager'] ?: 'غير محدد'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($dept['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    ✅ نشط
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    ❌ غير نشط
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('Y-m-d', strtotime($dept['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2 space-x-reverse">
                                            <a href="?edit_dept=<?php echo $dept['id']; ?>" 
                                               class="text-indigo-600 hover:text-indigo-900 bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded transition duration-200">
                                                ✏️ تعديل
                                            </a>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('هل تريد تغيير حالة هذا القسم؟')">
                                                <?php echo csrf_input('csrf_token'); ?>
                                                <input type="hidden" name="action" value="edit_department">
                                                <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                                <button type="submit" class="text-blue-600 hover:text-blue-900 bg-blue-100 hover:bg-blue-200 px-3 py-1 rounded transition duration-200">
                                                    🔄 تغيير الحالة
                                                </button>
                                            </form>
                                            
                                            <?php if ($dept['employee_count'] == 0): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('هل تريد حذف هذا القسم نهائياً؟')">
                                                    <?php echo csrf_input('csrf_token'); ?>
                                                    <input type="hidden" name="action" value="delete_department">
                                                    <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 bg-red-100 hover:bg-red-200 px-3 py-1 rounded transition duration-200">
                                                        🗑️ حذف
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="text-gray-400 bg-gray-100 px-3 py-1 rounded cursor-not-allowed" 
                                                        disabled title="لا يمكن حذف القسم لوجود موظفين">
                                                    🗑️ حذف
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- معلومات مهمة -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="mr-3">
                            <h3 class="text-sm font-medium text-blue-800">معلومات مهمة</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>لا يمكن حذف الأقسام التي تحتوي على موظفين</li>
                                    <li>يمكن إلغاء تفعيل القسم بدلاً من حذفه</li>
                                    <li>جميع أسماء الأقسام يجب أن تكون فريدة</li>
                                    <li>يمكن تعديل جميع بيانات القسم في أي وقت</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($current_page === 'committees'): ?>
                <!-- Committees Content -->
                <!-- Add committees content here -->
            <?php elseif ($current_page === 'sessions'): ?>
                <!-- Sessions Content -->
                <!-- Add sessions content here -->
            <?php elseif ($current_page === 'decisions'): ?>
                <!-- Decisions Content -->
                <!-- Add decisions content here -->
            <?php elseif ($current_page === 'reports'): ?>
                <!-- Reports Content -->
                <!-- Add reports content here -->
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
