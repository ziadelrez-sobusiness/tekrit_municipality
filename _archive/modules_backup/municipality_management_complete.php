<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../comprehensive_dashboard.php?error=no_permission');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// إنشاء الجداول المطلوبة
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
        is_active TINYINT(1) DEFAULT 1,
        meeting_frequency ENUM('أسبوعية', 'شهرية', 'ربع سنوية', 'حسب الحاجة') DEFAULT 'شهرية',
        responsibilities TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (chairman_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (secretary_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
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
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (committee_id) REFERENCES municipal_committees(id) ON DELETE SET NULL,
        FOREIGN KEY (chairperson_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (secretary_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES municipal_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (responsible_department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (responsible_person_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // إضافة اللجان الأساسية إذا لم تكن موجودة
    $committees_check = $db->query("SELECT COUNT(*) as count FROM municipal_committees")->fetch();
    if ($committees_check['count'] == 0) {
        $committees = [
            ['لجنة الشؤون المالية', 'مراجعة الميزانيات والأمور المالية للبلدية', 'دائمة', 'شهرية', 'مراجعة الميزانية والموافقة على المصروفات'],
            ['لجنة التخطيط والتطوير', 'التخطيط للمشاريع التطويرية ومتابعة تنفيذها', 'دائمة', 'شهرية', 'وضع خطط التطوير ومراجعة المشاريع'],
            ['لجنة البيئة والنظافة', 'الإشراف على شؤون البيئة والنظافة العامة', 'دائمة', 'شهرية', 'مراقبة النظافة وحماية البيئة'],
            ['لجنة الخدمات العامة', 'الإشراف على الخدمات المقدمة للمواطنين', 'دائمة', 'شهرية', 'تطوير الخدمات ومتابعة جودتها'],
            ['لجنة الطوارئ', 'التعامل مع الحالات الطارئة والأزمات', 'مؤقتة', 'حسب الحاجة', 'إدارة الأزمات والطوارئ']
        ];

        $stmt = $db->prepare("INSERT INTO municipal_committees (committee_name, committee_description, committee_type, meeting_frequency, responsibilities, formation_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
        foreach ($committees as $committee) {
            $stmt->execute($committee);
        }
    }

} catch (Exception $e) {
    // تجاهل الأخطاء
}

$success_message = '';
$error_message = '';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // إجراءات الأقسام
    if ($action == 'add_department') {
        $name = trim($_POST['department_name']);
        $description = trim($_POST['department_description']);
        $manager = trim($_POST['department_manager']);
        
        if (!empty($name)) {
            try {
                $stmt = $db->prepare("INSERT INTO departments (department_name, department_description, department_manager) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description, $manager]);
                $success_message = "تم إضافة القسم بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة القسم: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action == 'edit_department') {
        $id = $_POST['id'];
        $name = trim($_POST['department_name']);
        $description = trim($_POST['department_description']);
        $manager = trim($_POST['department_manager']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE departments SET department_name = ?, department_description = ?, department_manager = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $description, $manager, $is_active, $id]);
            $success_message = "تم تحديث القسم بنجاح";
        } catch (Exception $e) {
            $error_message = "خطأ في تحديث القسم: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'delete_department') {
        $id = $_POST['id'];
        
        try {
            // التحقق من وجود موظفين
            $check = $db->prepare("SELECT COUNT(*) as count FROM users WHERE department_id = ?");
            $check->execute([$id]);
            $count = $check->fetch()['count'];
            
            if ($count > 0) {
                $error_message = "لا يمكن حذف القسم لأنه يحتوي على موظفين";
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
        $name = trim($_POST['committee_name']);
        $description = trim($_POST['committee_description']);
        $department_id = $_POST['department_id'] ?: null;
        $type = $_POST['committee_type'];
        $frequency = $_POST['meeting_frequency'];
        $responsibilities = trim($_POST['responsibilities']);
        $formation_date = $_POST['formation_date'];
        $chairman_id = $_POST['chairman_id'] ?: null;
        $secretary_id = $_POST['secretary_id'] ?: null;
        
        if (!empty($name)) {
            try {
                $stmt = $db->prepare("INSERT INTO municipal_committees (committee_name, committee_description, department_id, committee_type, meeting_frequency, responsibilities, formation_date, chairman_id, secretary_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $department_id, $type, $frequency, $responsibilities, $formation_date, $chairman_id, $secretary_id]);
                $success_message = "تم إضافة اللجنة بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة اللجنة: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action == 'edit_committee') {
        $id = $_POST['id'];
        $name = trim($_POST['committee_name']);
        $description = trim($_POST['committee_description']);
        $department_id = $_POST['department_id'] ?: null;
        $type = $_POST['committee_type'];
        $frequency = $_POST['meeting_frequency'];
        $responsibilities = trim($_POST['responsibilities']);
        $chairman_id = $_POST['chairman_id'] ?: null;
        $secretary_id = $_POST['secretary_id'] ?: null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE municipal_committees SET committee_name = ?, committee_description = ?, department_id = ?, committee_type = ?, meeting_frequency = ?, responsibilities = ?, chairman_id = ?, secretary_id = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $description, $department_id, $type, $frequency, $responsibilities, $chairman_id, $secretary_id, $is_active, $id]);
            $success_message = "تم تحديث اللجنة بنجاح";
        } catch (Exception $e) {
            $error_message = "خطأ في تحديث اللجنة: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'delete_committee') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM municipal_committees WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "تم حذف اللجنة بنجاح";
        } catch (Exception $e) {
            $error_message = "خطأ في حذف اللجنة: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'add_member') {
        $committee_id = $_POST['committee_id'];
        $user_id = $_POST['user_id'];
        $role = $_POST['member_role'];
        $join_date = $_POST['join_date'];
        $notes = trim($_POST['notes']);
        
        try {
            // التحقق من عدم وجود العضو مسبقاً
            $check = $db->prepare("SELECT COUNT(*) as count FROM committee_members WHERE committee_id = ? AND user_id = ? AND is_active = 1");
            $check->execute([$committee_id, $user_id]);
            if ($check->fetch()['count'] > 0) {
                $error_message = "هذا العضو مضاف مسبقاً للجنة";
            } else {
                $stmt = $db->prepare("INSERT INTO committee_members (committee_id, user_id, member_role, join_date, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$committee_id, $user_id, $role, $join_date, $notes]);
                $success_message = "تم إضافة العضو بنجاح";
            }
        } catch (Exception $e) {
            $error_message = "خطأ في إضافة العضو: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'remove_member') {
        $id = $_POST['member_id'];
        
        try {
            $stmt = $db->prepare("UPDATE committee_members SET is_active = 0, leave_date = CURDATE() WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "تم إزالة العضو من اللجنة";
        } catch (Exception $e) {
            $error_message = "خطأ في إزالة العضو: " . $e->getMessage();
        }
    }
    
    // إجراءات الجلسات
    elseif ($action == 'add_session') {
        $number = trim($_POST['session_number']);
        $title = trim($_POST['session_title']);
        $type = $_POST['session_type'];
        $committee_id = $_POST['committee_id'] ?: null;
        $date = $_POST['session_date'];
        $time = $_POST['session_time'];
        $location = trim($_POST['location']);
        $agenda = trim($_POST['agenda']);
        $chairperson_id = $_POST['chairperson_id'] ?: null;
        $secretary_id = $_POST['secretary_id'] ?: null;
        $quorum = $_POST['quorum_required'];
        
        if (!empty($number) && !empty($title) && !empty($agenda)) {
            try {
                $stmt = $db->prepare("INSERT INTO municipal_sessions (session_number, session_title, session_type, committee_id, session_date, session_time, location, agenda, chairperson_id, secretary_id, quorum_required, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$number, $title, $type, $committee_id, $date, $time, $location, $agenda, $chairperson_id, $secretary_id, $quorum, $auth->getCurrentUser()['id']]);
                $success_message = "تم إضافة الجلسة بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة الجلسة: " . $e->getMessage();
            }
        } else {
            $error_message = "جميع الحقول المطلوبة يجب ملؤها";
        }
    }
    
    elseif ($action == 'edit_session') {
        $id = $_POST['id'];
        $number = trim($_POST['session_number']);
        $title = trim($_POST['session_title']);
        $type = $_POST['session_type'];
        $committee_id = $_POST['committee_id'] ?: null;
        $date = $_POST['session_date'];
        $time = $_POST['session_time'];
        $location = trim($_POST['location']);
        $agenda = trim($_POST['agenda']);
        $status = $_POST['session_status'];
        $chairperson_id = $_POST['chairperson_id'] ?: null;
        $secretary_id = $_POST['secretary_id'] ?: null;
        $quorum = $_POST['quorum_required'];
        $minutes = trim($_POST['session_minutes']);
        
        try {
            $stmt = $db->prepare("UPDATE municipal_sessions SET session_number = ?, session_title = ?, session_type = ?, committee_id = ?, session_date = ?, session_time = ?, location = ?, agenda = ?, session_status = ?, chairperson_id = ?, secretary_id = ?, quorum_required = ?, session_minutes = ? WHERE id = ?");
            $stmt->execute([$number, $title, $type, $committee_id, $date, $time, $location, $agenda, $status, $chairperson_id, $secretary_id, $quorum, $minutes, $id]);
            $success_message = "تم تحديث الجلسة بنجاح";
        } catch (Exception $e) {
            $error_message = "خطأ في تحديث الجلسة: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'delete_session') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM municipal_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "تم حذف الجلسة بنجاح";
        } catch (Exception $e) {
            $error_message = "خطأ في حذف الجلسة: " . $e->getMessage();
        }
    }
    
    // إجراءات القرارات
    elseif ($action == 'add_decision') {
        $number = trim($_POST['decision_number']);
        $session_id = $_POST['session_id'];
        $title = trim($_POST['decision_title']);
        $text = trim($_POST['decision_text']);
        $type = $_POST['decision_type'];
        $priority = $_POST['priority_level'];
        $voting_result = $_POST['voting_result'];
        $votes_for = $_POST['votes_for'] ?? 0;
        $votes_against = $_POST['votes_against'] ?? 0;
        $votes_abstain = $_POST['votes_abstain'] ?? 0;
        $deadline = $_POST['implementation_deadline'] ?: null;
        $responsible_dept = $_POST['responsible_department_id'] ?: null;
        $responsible_person = $_POST['responsible_person_id'] ?: null;
        $budget = $_POST['budget_required'] ?? 0;
        
        if (!empty($number) && !empty($title) && !empty($text)) {
            try {
                $stmt = $db->prepare("INSERT INTO municipal_decisions (decision_number, session_id, decision_title, decision_text, decision_type, priority_level, voting_result, votes_for, votes_against, votes_abstain, implementation_deadline, responsible_department_id, responsible_person_id, budget_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$number, $session_id, $title, $text, $type, $priority, $voting_result, $votes_for, $votes_against, $votes_abstain, $deadline, $responsible_dept, $responsible_person, $budget]);
                $success_message = "تم إضافة القرار بنجاح";
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة القرار: " . $e->getMessage();
            }
        } else {
            $error_message = "جميع الحقول المطلوبة يجب ملؤها";
        }
    }
    
    elseif ($action == 'edit_decision') {
        $id = $_POST['id'];
        $title = trim($_POST['decision_title']);
        $text = trim($_POST['decision_text']);
        $type = $_POST['decision_type'];
        $priority = $_POST['priority_level'];
        $deadline = $_POST['implementation_deadline'] ?: null;
        $responsible_dept = $_POST['responsible_department_id'] ?: null;
        $responsible_person = $_POST['responsible_person_id'] ?: null;
        $status = $_POST['implementation_status'];
        $progress = $_POST['implementation_progress'] ?? 0;
        $notes = trim($_POST['implementation_notes']);
        $budget = $_POST['budget_required'] ?? 0;
        
        try {
            $stmt = $db->prepare("UPDATE municipal_decisions SET decision_title = ?, decision_text = ?, decision_type = ?, priority_level = ?, implementation_deadline = ?, responsible_department_id = ?, responsible_person_id = ?, implementation_status = ?, implementation_progress = ?, implementation_notes = ?, budget_required = ? WHERE id = ?");
            $stmt->execute([$title, $text, $type, $priority, $deadline, $responsible_dept, $responsible_person, $status, $progress, $notes, $budget, $id]);
            $success_message = "تم تحديث القرار بنجاح";
        } catch (Exception $e) {
            $error_message = "خطأ في تحديث القرار: " . $e->getMessage();
        }
    }
    
    elseif ($action == 'delete_decision') {
        $id = $_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM municipal_decisions WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "تم حذف القرار بنجاح";
        } catch (Exception $e) {
            $error_message = "خطأ في حذف القرار: " . $e->getMessage();
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

$users = $db->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();

// للتعديل
$edit_department = null;
$edit_committee = null;
$edit_session = null;
$edit_decision = null;

if (isset($_GET['edit_dept'])) {
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$_GET['edit_dept']]);
    $edit_department = $stmt->fetch();
}

if (isset($_GET['edit_committee'])) {
    $stmt = $db->prepare("SELECT * FROM municipal_committees WHERE id = ?");
    $stmt->execute([$_GET['edit_committee']]);
    $edit_committee = $stmt->fetch();
}

if (isset($_GET['edit_session'])) {
    $stmt = $db->prepare("SELECT * FROM municipal_sessions WHERE id = ?");
    $stmt->execute([$_GET['edit_session']]);
    $edit_session = $stmt->fetch();
}

if (isset($_GET['edit_decision'])) {
    $stmt = $db->prepare("SELECT * FROM municipal_decisions WHERE id = ?");
    $stmt->execute([$_GET['edit_decision']]);
    $edit_decision = $stmt->fetch();
}

// جلب أعضاء اللجان
if (isset($_GET['view_members'])) {
    $committee_id = $_GET['view_members'];
    $members = $db->prepare("
        SELECT cm.*, u.full_name, d.department_name 
        FROM committee_members cm 
        JOIN users u ON cm.user_id = u.id 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE cm.committee_id = ? AND cm.is_active = 1 
        ORDER BY cm.member_role, u.full_name
    ");
    $members->execute([$committee_id]);
    $committee_members = $members->fetchAll();
    
    $committee_info = $db->prepare("SELECT committee_name FROM municipal_committees WHERE id = ?");
    $committee_info->execute([$committee_id]);
    $committee_name = $committee_info->fetch()['committee_name'];
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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.show { display: flex; }
    </style>
</head>
<body class="bg-gray-100" x-data="{ 
    activeTab: 'departments',
    showModal: false,
    modalType: '',
    editingId: null,
    viewingMembers: false
}">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">🏛️</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">نظام إدارة البلدية الشامل</h1>
                        <p class="text-sm text-gray-500">الهيكل الإداري • اللجان • الجلسات • القرارات</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="../comprehensive_dashboard.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">🏠 العودة للوحة التحكم</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">نظام إدارة البلدية الشامل</h1>
            <p class="text-slate-600">إدارة شاملة للهيكل الإداري، اللجان، الجلسات والقرارات البلدية مع جميع عمليات الإضافة والتعديل والحذف</p>
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
                    <button @click="activeTab = 'departments'" 
                            :class="activeTab === 'departments' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-indigo-600">
                        🏢 الهيكل الإداري
                    </button>
                    <button @click="activeTab = 'committees'" 
                            :class="activeTab === 'committees' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-indigo-600">
                        👥 إدارة اللجان
                    </button>
                    <button @click="activeTab = 'sessions'" 
                            :class="activeTab === 'sessions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-indigo-600">
                        📅 الجلسات البلدية
                    </button>
                    <button @click="activeTab = 'decisions'" 
                            :class="activeTab === 'decisions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-medium text-sm hover:text-indigo-600">
                        📋 القرارات البلدية
                    </button>
                </nav>
            </div>
        </div>

        <!-- Messages Flash -->
        <script>
            setTimeout(function() {
                const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
                messages.forEach(msg => msg.style.display = 'none');
            }, 5000);
        </script>

        <!-- Content will be added here -->
        
    </div>
</body>
</html> 
