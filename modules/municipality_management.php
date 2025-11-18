<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';

// تحميل CSRF Protection
if (file_exists(__DIR__ . '/../includes/csrf_middleware.php')) {
    require_once __DIR__ . '/../includes/csrf_middleware.php';
}

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
    // التحقق من CSRF
    if (!csrf_protect(false)) {
        $error_message = 'تم رفض الطلب لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
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
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                    exit();
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
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
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
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                    exit();
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
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=committees&success=1");
                    exit();
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
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=committees&success=1");
            exit();
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
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=committees&success=1");
                exit();
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
                $stmt = $db->prepare("INSERT INTO committee_members (committee_id, user_id, member_role, join_date, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$committee_id, $user_id, $role, $join_date, $notes]);
                $success_message = "تم إضافة العضو بنجاح";
                header("Location: ?view_members=" . $committee_id . "&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في إضافة العضو: " . $e->getMessage();
            }
        }
        
        elseif ($action == 'edit_member') {
            $id = $_POST['id'];
            $role = $_POST['member_role'];
            $join_date = $_POST['join_date'];
            $leave_date = $_POST['leave_date'] ?: null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $notes = trim($_POST['notes']);

            try {
                $stmt = $db->prepare("UPDATE committee_members SET member_role = ?, join_date = ?, leave_date = ?, is_active = ?, notes = ? WHERE id = ?");
                $stmt->execute([$role, $join_date, $leave_date, $is_active, $notes, $id]);
                $success_message = "تم تحديث العضو بنجاح";
                header("Location: ?view_members=" . $_POST['committee_id'] . "&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث العضو: " . $e->getMessage();
            }
        }
        
        elseif ($action == 'delete_member') {
            $id = $_POST['id'];
            $committee_id = $_POST['committee_id'];

            try {
                $stmt = $db->prepare("DELETE FROM committee_members WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "تم حذف العضو بنجاح";
                header("Location: ?view_members=" . $committee_id . "&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في حذف العضو: " . $e->getMessage();
            }
        }
        
        // إجراءات الجلسات
        elseif ($action == 'add_session') {
            $number = trim($_POST['session_number']);
            $title = trim($_POST['session_title']);
            $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
            $date = $_POST['session_date'];
            $time = !empty($_POST['session_time']) ? $_POST['session_time'] : null;
            $location = trim($_POST['location']);
            $agenda = trim($_POST['agenda']);
            $minutes = trim($_POST['session_minutes']);
            $attachments = trim($_POST['attachments']);
            
            if ($committee_id && $title && $date) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO committee_sessions
                            (committee_id, session_number, session_title, session_date, session_time, location, agenda, minutes, attachments, created_by)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $committee_id,
                        $number ?: null,
                        $title,
                        $date,
                        $time,
                        $location ?: null,
                        $agenda ?: null,
                        $minutes ?: null,
                        $attachments ?: null,
                        $auth->getCurrentUser()['id']
                    ]);
                    $success_message = "تم إضافة محضر الجلسة بنجاح";
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=sessions&success=1");
                    exit();
                } catch (Exception $e) {
                    $error_message = "خطأ في إضافة الجلسة: " . $e->getMessage();
                }
            } else {
                $error_message = "يرجى تحديد اللجنة، عنوان الجلسة، وتاريخ الانعقاد";
            }
        }
        
        elseif ($action == 'edit_session') {
            $id = intval($_POST['id']);
            $number = trim($_POST['session_number']);
            $title = trim($_POST['session_title']);
            $committee_id = !empty($_POST['committee_id']) ? intval($_POST['committee_id']) : null;
            $date = $_POST['session_date'];
            $time = !empty($_POST['session_time']) ? $_POST['session_time'] : null;
            $location = trim($_POST['location']);
            $agenda = trim($_POST['agenda']);
            $minutes = trim($_POST['session_minutes']);
            $attachments = trim($_POST['attachments']);
            
            try {
                $stmt = $db->prepare("
                    UPDATE committee_sessions
                    SET committee_id = ?, session_number = ?, session_title = ?, session_date = ?, session_time = ?, location = ?, agenda = ?, minutes = ?, attachments = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $committee_id ?: null,
                    $number ?: null,
                    $title,
                    $date,
                    $time,
                    $location ?: null,
                    $agenda ?: null,
                    $minutes ?: null,
                    $attachments ?: null,
                    $id
                ]);
                $success_message = "تم تحديث محضر الجلسة بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=sessions&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث الجلسة: " . $e->getMessage();
            }
        }
        
        elseif ($action == 'delete_session') {
            $id = intval($_POST['id']);
            
            try {
                $stmt = $db->prepare("DELETE FROM committee_sessions WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "تم حذف محضر الجلسة بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=sessions&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في حذف الجلسة: " . $e->getMessage();
            }
        }
        
            // إجراءات القرارات
        elseif ($action == 'add_decision') {
            $number = trim($_POST['decision_number']);
            $session_id = !empty($_POST['session_id']) ? intval($_POST['session_id']) : null;
            $title = trim($_POST['decision_title']);
            $text = trim($_POST['decision_text']);
            $status = $_POST['decision_status'];
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $implemented_at = !empty($_POST['implemented_at']) ? $_POST['implemented_at'] : null;
            $notes = trim($_POST['decision_notes']);
            
            if ($session_id && $title && $text) {
                try {
                    $stmtSession = $db->prepare("SELECT committee_id FROM committee_sessions WHERE id = ?");
                    $stmtSession->execute([$session_id]);
                    $sessionRow = $stmtSession->fetch(PDO::FETCH_ASSOC);
                    if (!$sessionRow) {
                        throw new Exception('الجلسة المحددة غير موجودة');
                    }
                    
                    $committee_id = $sessionRow['committee_id'];
                    
                    $stmt = $db->prepare("
                        INSERT INTO committee_decisions
                            (committee_id, session_id, decision_number, decision_title, decision_text, status, due_date, implemented_at, notes, created_by)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $committee_id,
                        $session_id,
                        $number ?: null,
                        $title,
                        $text,
                        $status,
                        $due_date,
                        $implemented_at,
                        $notes ?: null,
                        $auth->getCurrentUser()['id']
                    ]);
                    
                    $success_message = "تم إضافة قرار اللجنة بنجاح";
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=decisions&success=1");
                    exit();
                } catch (Exception $e) {
                    $error_message = "خطأ في إضافة القرار: " . $e->getMessage();
                }
            } else {
                $error_message = "يرجى اختيار الجلسة وكتابة عنوان ونص القرار";
            }
        }
        
        elseif ($action == 'edit_decision') {
            $id = intval($_POST['id']);
            $session_id = !empty($_POST['session_id']) ? intval($_POST['session_id']) : null;
            $title = trim($_POST['decision_title']);
            $text = trim($_POST['decision_text']);
            $status = $_POST['decision_status'];
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $implemented_at = !empty($_POST['implemented_at']) ? $_POST['implemented_at'] : null;
            $notes = trim($_POST['decision_notes']);
            
            try {
                $stmtSession = $db->prepare("SELECT committee_id FROM committee_sessions WHERE id = ?");
                $stmtSession->execute([$session_id]);
                $sessionRow = $stmtSession->fetch(PDO::FETCH_ASSOC);
                if (!$sessionRow) {
                    throw new Exception('الجلسة المحددة غير موجودة');
                }
                
                $committee_id = $sessionRow['committee_id'];
                
                $stmt = $db->prepare("
                    UPDATE committee_decisions
                    SET committee_id = ?, session_id = ?, decision_number = ?, decision_title = ?, decision_text = ?, status = ?, due_date = ?, implemented_at = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $committee_id,
                    $session_id,
                    trim($_POST['decision_number']) ?: null,
                    $title,
                    $text,
                    $status,
                    $due_date,
                    $implemented_at,
                    $notes ?: null,
                    $id
                ]);
                
                $success_message = "تم تحديث قرار اللجنة بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=decisions&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في تحديث القرار: " . $e->getMessage();
            }
        }
        
        elseif ($action == 'delete_decision') {
            $id = intval($_POST['id']);
            
            try {
                $stmt = $db->prepare("DELETE FROM committee_decisions WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "تم حذف قرار اللجنة بنجاح";
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=decisions&success=1");
                exit();
            } catch (Exception $e) {
                $error_message = "خطأ في حذف القرار: " . $e->getMessage();
            }
        }
    }
}

// تحديد التبويب النشط
$active_tab = $_GET['tab'] ?? 'departments';

// رسائل النجاح
if (isset($_GET['success'])) {
    $success_message = "تم تنفيذ العملية بنجاح";
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

$committees_by_department = [];
foreach ($committees as $committee) {
    $deptId = $committee['department_id'] ?? null;
    if ($deptId) {
        if (!isset($committees_by_department[$deptId])) {
            $committees_by_department[$deptId] = [];
        }
        $committees_by_department[$deptId][] = $committee;
    }
}

$sessions = $db->query("
    SELECT cs.*,
           mc.committee_name
    FROM committee_sessions cs
    LEFT JOIN municipal_committees mc ON cs.committee_id = mc.id
    ORDER BY cs.session_date DESC, cs.session_time DESC
    LIMIT 20
")->fetchAll();

$all_sessions = $db->query("
    SELECT cs.id,
           cs.session_title,
           cs.session_number,
           cs.session_date
    FROM committee_sessions cs
    ORDER BY cs.session_date DESC, cs.id DESC
")->fetchAll();

$decisions = $db->query("
    SELECT cd.*,
           cs.session_title,
           cs.session_number,
           mc.committee_name
    FROM committee_decisions cd
    LEFT JOIN committee_sessions cs ON cd.session_id = cs.id
    LEFT JOIN municipal_committees mc ON cd.committee_id = mc.id
    ORDER BY cd.created_at DESC
    LIMIT 20
")->fetchAll();

$users = $db->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();

// للتعديل
$edit_department = null;
$edit_committee = null;
$edit_session = null;
$edit_decision = null;
$view_session = null;
$view_decision = null;

if (isset($_GET['edit_dept'])) {
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$_GET['edit_dept']]);
    $edit_department = $stmt->fetch();
    $active_tab = 'departments';
}

if (isset($_GET['edit_committee'])) {
    $stmt = $db->prepare("SELECT * FROM municipal_committees WHERE id = ?");
    $stmt->execute([$_GET['edit_committee']]);
    $edit_committee = $stmt->fetch();
    $active_tab = 'committees';
}

if (isset($_GET['edit_session'])) {
    $stmt = $db->prepare("SELECT * FROM committee_sessions WHERE id = ?");
    $stmt->execute([$_GET['edit_session']]);
    $edit_session = $stmt->fetch();
    $active_tab = 'sessions';
}

if (isset($_GET['edit_decision'])) {
    $stmt = $db->prepare("SELECT * FROM committee_decisions WHERE id = ?");
    $stmt->execute([$_GET['edit_decision']]);
    $edit_decision = $stmt->fetch();
    $active_tab = 'decisions';
}

if (isset($_GET['view_session'])) {
    $stmt = $db->prepare("
        SELECT cs.*, mc.committee_name
        FROM committee_sessions cs
        LEFT JOIN municipal_committees mc ON cs.committee_id = mc.id
        WHERE cs.id = ?
    ");
    $stmt->execute([$_GET['view_session']]);
    $view_session = $stmt->fetch();
    $active_tab = 'sessions';
}

if (isset($_GET['view_decision'])) {
    $stmt = $db->prepare("
        SELECT cd.*, cs.session_title, cs.session_number, mc.committee_name
        FROM committee_decisions cd
        LEFT JOIN committee_sessions cs ON cd.session_id = cs.id
        LEFT JOIN municipal_committees mc ON cd.committee_id = mc.id
        WHERE cd.id = ?
    ");
    $stmt->execute([$_GET['view_decision']]);
    $view_decision = $stmt->fetch();
    $active_tab = 'decisions';
}

// جلب أعضاء اللجان
if (isset($_GET['view_members'])) {
    $committee_id = $_GET['view_members'];
    $members = $db->prepare("
        SELECT cm.*, u.full_name, d.department_name 
        FROM committee_members cm 
        JOIN users u ON cm.user_id = u.id 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE cm.committee_id = ?
        ORDER BY cm.member_role, u.full_name
    ");
    $members->execute([$committee_id]);
    $committee_members = $members->fetchAll();
    
    $committee_info = $db->prepare("SELECT * FROM municipal_committees WHERE id = ?");
    $committee_info->execute([$committee_id]);
    $committee = $committee_info->fetch();
    $committee_name = $committee['committee_name'];
    $active_tab = 'committees';
}

// للتعديل في الأعضاء
$edit_member = null;
if (isset($_GET['edit_member'])) {
    $stmt = $db->prepare("SELECT cm.*, u.full_name FROM committee_members cm JOIN users u ON cm.user_id = u.id WHERE cm.id = ?");
    $stmt->execute([$_GET['edit_member']]);
    $edit_member = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة البلدية الشامل - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../public/assets/css/tekrit-theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active { color: #4f46e5; border-bottom-color: #4f46e5; }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="tekrit-header shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <img src="../public/assets/images/Tekrit_LOGO.jpg" alt="شعار بلدية تكريت" class="tekrit-logo ml-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">نظام إدارة البلدية</h1>
                        <p class="text-sm text-gray-600">إدارة شاملة لجميع أقسام البلدية</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../comprehensive_dashboard.php" class="btn-primary-orange">
                        🏠 العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">نظام إدارة البلدية</h1>
            <p class="text-slate-600">إدارة شاملة للهيكل الإداري، اللجان، الجلسات والقرارات البلدية</p>
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
                    <button onclick="showTab('departments')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'departments' ? 'active' : 'border-transparent text-gray-500' ?>">
                        🏢 الهيكل الإداري
                    </button>
                    <button onclick="showTab('committees')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'committees' ? 'active' : 'border-transparent text-gray-500' ?>">
                        👥 إدارة اللجان
                    </button>
                    <button onclick="showTab('sessions')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'sessions' ? 'active' : 'border-transparent text-gray-500' ?>">
                        📅 الجلسات البلدية
                    </button>
                    <button onclick="showTab('decisions')" 
                            class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab == 'decisions' ? 'active' : 'border-transparent text-gray-500' ?>">
                        📋 القرارات البلدية
                    </button>
                </nav>
            </div>
        </div>

        <!-- 🏢 الهيكل الإداري -->
        <div id="departments" class="tab-content <?= $active_tab == 'departments' ? 'active' : '' ?>">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">🏢 إدارة الهيكل الإداري</h3>
                    <a href="?add_dept=1" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        ➕ إضافة قسم جديد
                    </a>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($departments as $dept): ?>
                        <div class="border rounded-lg p-4 hover:shadow-md">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-semibold text-lg"><?= htmlspecialchars($dept['department_name']) ?></h4>
                                <div class="flex space-x-2 space-x-reverse">
                                    <a href="?edit_dept=<?= $dept['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800">✏️</a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا القسم؟')">
                                        <?php echo csrf_input('csrf_token'); ?>
                                        <input type="hidden" name="action" value="delete_department">
                                        <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800">🗑️</button>
                                    </form>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($dept['department_description']) ?></p>
                            <p class="text-sm text-blue-600">👥 <?= $dept['employee_count'] ?> موظف</p>
                            <p class="text-sm text-green-600">👔 المدير: <?= htmlspecialchars($dept['department_manager'] ?: 'غير محدد') ?></p>
                            <p class="text-sm <?= $dept['is_active'] ? 'text-green-600' : 'text-red-600' ?>">
                                ⏺ <?= $dept['is_active'] ? 'نشط' : 'غير نشط' ?>
                            </p>
                            <div class="mt-3">
                                <p class="text-xs font-semibold text-gray-700 mb-1">👥 اللجان التابعة:</p>
                                <?php if (!empty($committees_by_department[$dept['id']] ?? [])): ?>
                                    <ul class="text-xs text-gray-600 space-y-1">
                                        <?php foreach ($committees_by_department[$dept['id']] as $committee): ?>
                                            <li>
                                                • <a href="?tab=committees#committee-<?= $committee['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?= htmlspecialchars($committee['committee_name']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-xs text-gray-400">لا توجد لجان مرتبطة</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 👥 إدارة اللجان -->
        <div id="committees" class="tab-content <?= $active_tab == 'committees' ? 'active' : '' ?>">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">👥 إدارة اللجان البلدية</h3>
                    <a href="?add_committee=1" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        ➕ إضافة لجنة جديدة
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم اللجنة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">النوع</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الرئيس</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الأعضاء</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">التكرار</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($committees as $committee): ?>
                                <tr id="committee-<?= $committee['id'] ?>">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($committee['committee_name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($committee['committee_description']) ?></div>
                                        <?php if (!empty($committee['department_name'])): ?>
                                            <div class="text-xs text-blue-600 mt-1">🏢 <?= htmlspecialchars($committee['department_name']) ?></div>
                                        <?php else: ?>
                                            <div class="text-xs text-red-500 mt-1">لا يوجد قسم مرتبط</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $committee['committee_type'] ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($committee['chairman_name'] ?: 'غير محدد') ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <a href="?view_members=<?= $committee['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                            👥 <?= $committee['members_count'] ?> عضو
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $committee['meeting_frequency'] ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $committee['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $committee['is_active'] ? 'نشطة' : 'غير نشطة' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium space-x-2 space-x-reverse">
                                        <a href="committee_dashboard.php?id=<?= $committee['id'] ?>" 
                                           class="inline-block px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                                            📊 بوابة اللجنة
                                        </a>
                                        <a href="budgets.php?committee_id=<?= $committee['id'] ?>&committee_name=<?= urlencode($committee['committee_name']) ?>" 
                                           class="inline-block px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700">
                                            💰 الميزانية
                                        </a>
                                        <a href="?edit_committee=<?= $committee['id'] ?>" class="text-blue-600 hover:text-blue-900">✏️ تعديل</a>
                                        <a href="?view_members=<?= $committee['id'] ?>" class="text-green-600 hover:text-green-900">👥 الأعضاء</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذه اللجنة؟')">
                                            <?php echo csrf_input('csrf_token'); ?>
                                            <input type="hidden" name="action" value="delete_committee">
                                            <input type="hidden" name="id" value="<?= $committee['id'] ?>">
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


        <!-- 📅 الجلسات البلدية -->
        <div id="sessions" class="tab-content <?= $active_tab == 'sessions' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-bold">📅 محاضر اجتماعات اللجان</h3>
                    <a href="?add_session=1" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                        ➕ إضافة محضر جديد
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم الجلسة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">العنوان</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اللجنة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">التاريخ</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الموقع</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($session['session_number'] ?? '—') ?></td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($session['session_title']) ?></div>
                                        <?php if (!empty($session['agenda'])): ?>
                                            <div class="text-xs text-gray-500"><?= mb_strimwidth(strip_tags($session['agenda']), 0, 80, '...') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($session['committee_name'] ?? '—') ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($session['session_date']) ?>
                                        <?php if (!empty($session['session_time'])): ?>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($session['session_time']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($session['location'] ?? '—') ?></td>
                                    <td class="px-6 py-4 text-sm font-medium space-x-2 space-x-reverse">
                                        <a href="?view_session=<?= $session['id'] ?>" class="text-indigo-600 hover:text-indigo-900">👁️ عرض</a>
                                        <a href="?edit_session=<?= $session['id'] ?>" class="text-blue-600 hover:text-blue-900">✏️ تعديل</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذه الجلسة؟')">
                                            <?php echo csrf_input('csrf_token'); ?>
                                            <input type="hidden" name="action" value="delete_session">
                                            <input type="hidden" name="id" value="<?= $session['id'] ?>">
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

        <!-- 📋 القرارات البلدية -->
        <div id="decisions" class="tab-content <?= $active_tab == 'decisions' ? 'active' : '' ?>">
            <div class="glass-card p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-bold">📋 قرارات اللجان</h3>
                    <a href="?add_decision=1" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                        ➕ إضافة قرار جديد
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم القرار</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">العنوان</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الجلسة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ الاستحقاق</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($decisions as $decision): ?>
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($decision['decision_number'] ?? '—') ?></td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($decision['decision_title']) ?></div>
                                        <?php if (!empty($decision['notes'])): ?>
                                            <div class="text-xs text-gray-500"><?= mb_strimwidth(strip_tags($decision['notes']), 0, 80, '...') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars(trim(($decision['session_number'] ?? '') . ' - ' . ($decision['session_title'] ?? ''), ' -')) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            <?= $decision['status'] == 'منفذ' ? 'bg-green-100 text-green-800' : 
                                               ($decision['status'] == 'مرفوض' ? 'bg-red-100 text-red-800' :
                                               ($decision['status'] == 'معلق' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800')) ?>">
                                            <?= htmlspecialchars($decision['status']) ?>
                                        </span>
                                        <?php if (!empty($decision['implemented_at'])): ?>
                                            <div class="text-xs text-gray-500 mt-1">تاريخ التنفيذ: <?= htmlspecialchars($decision['implemented_at']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($decision['due_date'] ?? '—') ?></td>
                                    <td class="px-6 py-4 text-sm font-medium space-x-2 space-x-reverse">
                                        <a href="?view_decision=<?= $decision['id'] ?>" class="text-indigo-600 hover:text-indigo-900">👁️ عرض</a>
                                        <a href="?edit_decision=<?= $decision['id'] ?>" class="text-blue-600 hover:text-blue-900">✏️ تعديل</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا القرار؟')">
                                            <?php echo csrf_input('csrf_token'); ?>
                                            <input type="hidden" name="action" value="delete_decision">
                                            <input type="hidden" name="id" value="<?= $decision['id'] ?>">
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

        <!-- نماذج الإضافة والتعديل -->
        <?php if (isset($_GET['add_dept']) || isset($edit_department)): ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4"><?= isset($edit_department) ? 'تعديل القسم' : 'إضافة قسم جديد' ?></h3>
                    <form method="POST">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="<?= isset($edit_department) ? 'edit_department' : 'add_department' ?>">
                        <?php if (isset($edit_department)): ?>
                            <input type="hidden" name="id" value="<?= $edit_department['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">اسم القسم</label>
                            <input type="text" name="department_name" value="<?= isset($edit_department) ? htmlspecialchars($edit_department['department_name']) : '' ?>" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">وصف القسم</label>
                            <textarea name="department_description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?= isset($edit_department) ? htmlspecialchars($edit_department['department_description']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">المسؤول عن القسم</label>
                            <input type="text" name="department_manager" value="<?= isset($edit_department) ? htmlspecialchars($edit_department['department_manager']) : '' ?>" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        
                        <?php if (isset($edit_department)): ?>
                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_active" class="form-checkbox" <?= $edit_department['is_active'] ? 'checked' : '' ?>>
                                <span class="ml-2">القسم نشط</span>
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse">
                            <a href="?tab=departments" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">إلغاء</a>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
		
		
        <?php if (isset($_GET['add_committee']) || isset($edit_committee)): ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4"><?= isset($edit_committee) ? 'تعديل اللجنة' : 'إضافة لجنة جديدة' ?></h3>
                    <form method="POST">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="<?= isset($edit_committee) ? 'edit_committee' : 'add_committee' ?>">
                        <?php if (isset($edit_committee)): ?>
                            <input type="hidden" name="id" value="<?= $edit_committee['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">اسم اللجنة</label>
                            <input type="text" name="committee_name" value="<?= isset($edit_committee) ? htmlspecialchars($edit_committee['committee_name']) : '' ?>" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">وصف اللجنة</label>
                            <textarea name="committee_description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?= isset($edit_committee) ? htmlspecialchars($edit_committee['committee_description']) : '' ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">القسم التابع</label>
                            <select name="department_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">بدون قسم</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= (isset($edit_committee) && $edit_committee['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">نوع اللجنة</label>
                                <select name="committee_type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                    <option value="دائمة" <?= (isset($edit_committee) && $edit_committee['committee_type'] == 'دائمة') ? 'selected' : '' ?>>دائمة</option>
                                    <option value="مؤقتة" <?= (isset($edit_committee) && $edit_committee['committee_type'] == 'مؤقتة') ? 'selected' : '' ?>>مؤقتة</option>
                                    <option value="استشارية" <?= (isset($edit_committee) && $edit_committee['committee_type'] == 'استشارية') ? 'selected' : '' ?>>استشارية</option>
                                    <option value="تنفيذية" <?= (isset($edit_committee) && $edit_committee['committee_type'] == 'تنفيذية') ? 'selected' : '' ?>>تنفيذية</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">تكرار الاجتماعات</label>
                                <select name="meeting_frequency" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                    <option value="أسبوعية" <?= (isset($edit_committee) && $edit_committee['meeting_frequency'] == 'أسبوعية') ? 'selected' : '' ?>>أسبوعية</option>
                                    <option value="شهرية" <?= (isset($edit_committee) && $edit_committee['meeting_frequency'] == 'شهرية') ? 'selected' : '' ?>>شهرية</option>
                                    <option value="ربع سنوية" <?= (isset($edit_committee) && $edit_committee['meeting_frequency'] == 'ربع سنوية') ? 'selected' : '' ?>>ربع سنوية</option>
                                    <option value="حسب الحاجة" <?= (isset($edit_committee) && $edit_committee['meeting_frequency'] == 'حسب الحاجة') ? 'selected' : '' ?>>حسب الحاجة</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">المسؤوليات</label>
                            <textarea name="responsibilities" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?= isset($edit_committee) ? htmlspecialchars($edit_committee['responsibilities']) : '' ?></textarea>
                        </div>
                        
                        <?php if (isset($edit_committee)): ?>
                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_active" class="form-checkbox" <?= $edit_committee['is_active'] ? 'checked' : '' ?>>
                                <span class="ml-2">اللجنة نشطة</span>
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse">
                            <a href="?tab=committees" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">إلغاء</a>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['add_session']) || isset($edit_session)): ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="glass-card p-6 space-y-4 max-w-3xl">
                    <h3 class="text-xl font-bold"><?= isset($edit_session) ? 'تعديل محضر جلسة' : 'إضافة محضر جلسة جديدة' ?></h3>
                    <form method="POST" class="space-y-4">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="<?= isset($edit_session) ? 'edit_session' : 'add_session' ?>">
                        <?php if (isset($edit_session)): ?>
                            <input type="hidden" name="id" value="<?= $edit_session['id'] ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">رقم الجلسة</label>
                                <input type="text" name="session_number" value="<?= isset($edit_session) ? htmlspecialchars($edit_session['session_number']) : '' ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">اللجنة *</label>
                                <select name="committee_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                    <option value="">اختر اللجنة</option>
                                    <?php foreach ($committees as $committee): ?>
                                        <option value="<?= $committee['id'] ?>" <?= (isset($edit_session) && $edit_session['committee_id'] == $committee['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($committee['committee_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">عنوان الجلسة *</label>
                            <input type="text" name="session_title" value="<?= isset($edit_session) ? htmlspecialchars($edit_session['session_title']) : '' ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">تاريخ الجلسة *</label>
                                <input type="date" name="session_date" value="<?= isset($edit_session) ? $edit_session['session_date'] : date('Y-m-d') ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">الوقت</label>
                                <input type="time" name="session_time" value="<?= isset($edit_session) ? $edit_session['session_time'] : '' ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">الموقع</label>
                                <input type="text" name="location" value="<?= isset($edit_session) ? htmlspecialchars($edit_session['location']) : '' ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">جدول الأعمال</label>
                            <textarea name="agenda" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"><?= isset($edit_session) ? htmlspecialchars($edit_session['agenda']) : '' ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">محضر الجلسة</label>
                            <textarea name="session_minutes" rows="4" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"><?= isset($edit_session) ? htmlspecialchars($edit_session['minutes']) : '' ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">روابط المرفقات (اختياري)</label>
                            <input type="text" name="attachments" value="<?= isset($edit_session) ? htmlspecialchars($edit_session['attachments']) : '' ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="مثال: رابط Google Drive أو ملف PDF">
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="?tab=sessions" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">إلغاء</a>
                            <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                                حفظ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
<?php endif; ?>

        <?php if (isset($_GET['add_decision']) || isset($edit_decision)): ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="glass-card p-6 space-y-4 max-w-3xl">
                    <h3 class="text-xl font-bold"><?= isset($edit_decision) ? 'تعديل قرار لجنة' : 'إضافة قرار لجنة جديد' ?></h3>
                    <form method="POST" class="space-y-4">
                        <?php echo csrf_input('csrf_token'); ?>
                        <input type="hidden" name="action" value="<?= isset($edit_decision) ? 'edit_decision' : 'add_decision' ?>">
                        <?php if (isset($edit_decision)): ?>
                            <input type="hidden" name="id" value="<?= $edit_decision['id'] ?>">
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">رقم القرار</label>
                                <input type="text" name="decision_number" value="<?= isset($edit_decision) ? htmlspecialchars($edit_decision['decision_number']) : '' ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">الجلسة *</label>
                                <select name="session_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500" required>
                                    <option value="">اختر الجلسة المرتبطة</option>
                                    <?php foreach ($all_sessions as $session): ?>
                                        <option value="<?= $session['id'] ?>" <?= (isset($edit_decision) && $edit_decision['session_id'] == $session['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim(($session['session_number'] ?? '') . ' - ' . $session['session_title'])) ?> (<?= htmlspecialchars($session['session_date']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">عنوان القرار *</label>
                            <input type="text" name="decision_title" value="<?= isset($edit_decision) ? htmlspecialchars($edit_decision['decision_title']) : '' ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">نص القرار *</label>
                            <textarea name="decision_text" rows="4" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500" required><?= isset($edit_decision) ? htmlspecialchars($edit_decision['decision_text']) : '' ?></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">حالة القرار</label>
                                <select name="decision_status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500">
                                    <?php $statuses = ['قيد المتابعة','منفذ','مرفوض','معلق']; ?>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= $status ?>" <?= (isset($edit_decision) && $edit_decision['status'] == $status) ? 'selected' : '' ?>><?= $status ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">تاريخ الاستحقاق</label>
                                <input type="date" name="due_date" value="<?= isset($edit_decision) ? $edit_decision['due_date'] : '' ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">تاريخ التنفيذ</label>
                                <input type="date" name="implemented_at" value="<?= isset($edit_decision) ? $edit_decision['implemented_at'] : '' ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">ملاحظات إضافية</label>
                            <textarea name="decision_notes" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-yellow-500" placeholder="تفاصيل متابعة التنفيذ أو ملاحظات اللجنة"><?= isset($edit_decision) ? htmlspecialchars($edit_decision['notes']) : '' ?></textarea>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="?tab=decisions" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">إلغاء</a>
                            <button type="submit" class="px-5 py-2 rounded-lg bg-yellow-600 text-white hover:bg-yellow-700">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($committee_members)): ?>
<div class="modal-overlay">
    <div class="modal-content">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">أعضاء <?= htmlspecialchars($committee_name) ?></h3>
                <div>
                    <button onclick="document.getElementById('addMemberModal').classList.remove('hidden')" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 mr-2">
                        ➕ إضافة عضو جديد
                    </button>
                    <a href="?tab=committees" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">إغلاق</a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الاسم</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">القسم</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الدور</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ الانضمام</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($committee_members as $member): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($member['full_name']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($member['department_name'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= $member['member_role'] ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= $member['join_date'] ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $member['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $member['is_active'] ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium space-x-2 space-x-reverse">
                                    <a href="?view_members=<?= $committee_id ?>&edit_member=<?= $member['id'] ?>" class="text-blue-600 hover:text-blue-900">✏️ تعديل</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا العضو؟')">
                                        <?php echo csrf_input('csrf_token'); ?>
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                        <input type="hidden" name="committee_id" value="<?= $committee_id ?>">
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
</div>

<!-- نموذج إضافة عضو جديد -->
<div id="addMemberModal" class="modal-overlay <?= isset($_GET['add_member']) ? '' : 'hidden' ?>">
    <div class="modal-content">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">إضافة عضو جديد إلى <?= htmlspecialchars($committee_name) ?></h3>
                <button onclick="document.getElementById('addMemberModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            
            <form method="POST">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" name="committee_id" value="<?= $committee_id ?>">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">اختر العضو</label>
                    <select name="user_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">اختر موظف</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">الدور في اللجنة</label>
                    <select name="member_role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="رئيس">رئيس</option>
                        <option value="نائب الرئيس">نائب الرئيس</option>
                        <option value="سكرتير">سكرتير</option>
                        <option value="عضو" selected>عضو</option>
                        <option value="مقرر">مقرر</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">تاريخ الانضمام</label>
                    <input type="date" name="join_date" value="<?= date('Y-m-d') ?>" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">ملاحظات</label>
                    <textarea name="notes" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse">
                    <button type="button" onclick="document.getElementById('addMemberModal').classList.add('hidden')" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">إلغاء</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">إضافة العضو</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- نموذج تعديل العضو -->
<?php if (isset($edit_member)): ?>
<div class="modal-overlay">
    <div class="modal-content">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">تعديل عضو: <?= htmlspecialchars($edit_member['full_name']) ?></h3>
                <a href="?view_members=<?= $committee_id ?>" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</a>
            </div>
            
            <form method="POST">
                <?php echo csrf_input('csrf_token'); ?>
                <input type="hidden" name="action" value="edit_member">
                <input type="hidden" name="id" value="<?= $edit_member['id'] ?>">
                <input type="hidden" name="committee_id" value="<?= $committee_id ?>">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">الدور في اللجنة</label>
                    <select name="member_role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="رئيس" <?= $edit_member['member_role'] == 'رئيس' ? 'selected' : '' ?>>رئيس</option>
                        <option value="نائب الرئيس" <?= $edit_member['member_role'] == 'نائب الرئيس' ? 'selected' : '' ?>>نائب الرئيس</option>
                        <option value="سكرتير" <?= $edit_member['member_role'] == 'سكرتير' ? 'selected' : '' ?>>سكرتير</option>
                        <option value="عضو" <?= $edit_member['member_role'] == 'عضو' ? 'selected' : '' ?>>عضو</option>
                        <option value="مقرر" <?= $edit_member['member_role'] == 'مقرر' ? 'selected' : '' ?>>مقرر</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">تاريخ الانضمام</label>
                    <input type="date" name="join_date" value="<?= $edit_member['join_date'] ?>" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">تاريخ المغادرة (إذا كان غير نشط)</label>
                    <input type="date" name="leave_date" value="<?= $edit_member['leave_date'] ?>" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">ملاحظات</label>
                    <textarea name="notes" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?= htmlspecialchars($edit_member['notes']) ?></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_active" class="form-checkbox" <?= $edit_member['is_active'] ? 'checked' : '' ?>>
                        <span class="ml-2">العضو نشط</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse">
                    <a href="?view_members=<?= $committee_id ?>" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">إلغاء</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

        <script>
		
						// إخفاء الرسائل بعد 5 ثوان
				setTimeout(function() {
					// استهداف فقط عناصر الرسائل وليس خلايا الجدول
					const messages = document.querySelectorAll('.bg-green-100.border-green-400, .bg-red-100.border-red-400');
					messages.forEach(msg => msg.style.display = 'none');
				}, 5000);

				// فتح وإغلاق نافذة إضافة عضو
				function openAddMemberModal() {
					document.getElementById('addMemberModal').classList.remove('hidden');
				}

				function closeAddMemberModal() {
					document.getElementById('addMemberModal').classList.add('hidden');
				}
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
                
                // تحديث عنوان URL بدون إعادة تحميل الصفحة
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.pushState({}, '', url);
            }
            
            // عند تحميل الصفحة، تأكد من أن التبويب الصحيح نشط
            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const activeTab = urlParams.get('tab') || 'departments';
                showTab(activeTab);
            });
            
            // إخفاء الرسائل بعد 5 ثوان
           
        </script>
        
    </div>
</body>
</html>

<?php if ($view_session): ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="glass-card p-6 space-y-4 max-w-3xl">
                    <div class="flex justify-between items-start">
                        <h3 class="text-xl font-bold">📄 تفاصيل الجلسة</h3>
                        <div class="flex gap-2">
                            <button onclick="window.print()" class="px-3 py-1 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">🖨️ طباعة</button>
                            <a href="?tab=sessions" class="px-3 py-1 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">إغلاق</a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                        <div><span class="font-semibold text-gray-900">رقم الجلسة:</span> <?= htmlspecialchars($view_session['session_number'] ?? '—') ?></div>
                        <div><span class="font-semibold text-gray-900">اللجنة:</span> <?= htmlspecialchars($view_session['committee_name'] ?? '—') ?></div>
                        <div><span class="font-semibold text-gray-900">التاريخ:</span> <?= htmlspecialchars($view_session['session_date']) ?></div>
                        <div><span class="font-semibold text-gray-900">الوقت:</span> <?= htmlspecialchars($view_session['session_time'] ?? '—') ?></div>
                        <div class="md:col-span-2"><span class="font-semibold text-gray-900">الموقع:</span> <?= htmlspecialchars($view_session['location'] ?? '—') ?></div>
                    </div>
                    <?php if (!empty($view_session['agenda'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">📋 جدول الأعمال</h4>
                            <div class="bg-gray-50 border rounded-lg p-4 text-sm leading-relaxed">
                                <?= nl2br(htmlspecialchars($view_session['agenda'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($view_session['minutes'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">📝 محضر الجلسة</h4>
                            <div class="bg-gray-50 border rounded-lg p-4 text-sm leading-relaxed">
                                <?= nl2br(htmlspecialchars($view_session['minutes'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($view_session['attachments'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">🔗 المرفقات</h4>
                            <a href="<?= htmlspecialchars($view_session['attachments']) ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800">عرض الروابط / المستندات</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php endif; ?>

<?php if ($view_decision): ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="glass-card p-6 space-y-4 max-w-3xl">
                    <div class="flex justify-between items-start">
                        <h3 class="text-xl font-bold">🗒️ تفاصيل القرار</h3>
                        <div class="flex gap-2">
                            <button onclick="window.print()" class="px-3 py-1 rounded-lg bg-yellow-600 text-white hover:bg-yellow-700">🖨️ طباعة</button>
                            <a href="?tab=decisions" class="px-3 py-1 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100">إغلاق</a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                        <div><span class="font-semibold text-gray-900">رقم القرار:</span> <?= htmlspecialchars($view_decision['decision_number'] ?? '—') ?></div>
                        <div><span class="font-semibold text-gray-900">اللجنة:</span> <?= htmlspecialchars($view_decision['committee_name'] ?? '—') ?></div>
                        <div><span class="font-semibold text-gray-900">الجلسة المرتبطة:</span> <?= htmlspecialchars(trim(($view_decision['session_number'] ?? '') . ' - ' . ($view_decision['session_title'] ?? ''))) ?></div>
                        <div><span class="font-semibold text-gray-900">الحالة:</span> <?= htmlspecialchars($view_decision['status']) ?></div>
                        <div><span class="font-semibold text-gray-900">تاريخ الاستحقاق:</span> <?= htmlspecialchars($view_decision['due_date'] ?? '—') ?></div>
                        <div><span class="font-semibold text-gray-900">تاريخ التنفيذ:</span> <?= htmlspecialchars($view_decision['implemented_at'] ?? '—') ?></div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">📌 عنوان القرار</h4>
                        <div class="bg-gray-50 border rounded-lg p-4 text-sm leading-relaxed">
                            <?= nl2br(htmlspecialchars($view_decision['decision_title'])) ?>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">📝 نص القرار</h4>
                        <div class="bg-gray-50 border rounded-lg p-4 text-sm leading-relaxed">
                            <?= nl2br(htmlspecialchars($view_decision['decision_text'])) ?>
                        </div>
                    </div>
                    <?php if (!empty($view_decision['notes'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">🗒️ ملاحظات إضافية</h4>
                            <div class="bg-gray-50 border rounded-lg p-4 text-sm leading-relaxed">
                                <?= nl2br(htmlspecialchars($view_decision['notes'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php endif; ?>

