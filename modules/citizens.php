<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة مواطن جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_citizen'])) {
    $citizen_number = trim($_POST['citizen_number']);
    $full_name = trim($_POST['full_name']);
    $father_name = trim($_POST['father_name']);
    $grandfather_name = trim($_POST['grandfather_name']);
    $surname = trim($_POST['surname']);
    $mother_name = trim($_POST['mother_name']);
    $birth_date = $_POST['birth_date'];
    $birth_place = trim($_POST['birth_place']);
    $gender = $_POST['gender'];
    $marital_status = $_POST['marital_status'];
    $nationality = trim($_POST['nationality']) ?: 'لبناني';
    $religion = trim($_POST['religion']);
    $district = trim($_POST['district']);
    $area = trim($_POST['area']);
    $neighborhood = trim($_POST['neighborhood']);
    $street = trim($_POST['street']);
    $house_number = trim($_POST['house_number']);
    $building_type = $_POST['building_type'];
    $phone = trim($_POST['phone']);
    $mobile = trim($_POST['mobile']);
    $email = trim($_POST['email']);
    $profession = trim($_POST['profession']);
    $workplace = trim($_POST['workplace']);
    $monthly_income = !empty($_POST['monthly_income']) ? floatval($_POST['monthly_income']) : null;
    $income_currency_id = !empty($_POST['income_currency_id']) ? intval($_POST['income_currency_id']) : null;
    $residence_status = $_POST['residence_status'];
    $social_status = $_POST['social_status'];
    $family_members_count = intval($_POST['family_members_count']) ?: 1;
    $dependents_count = intval($_POST['dependents_count']) ?: 0;
    $special_needs = trim($_POST['special_needs']);
    $notes = trim($_POST['notes']);
    
    if (!empty($full_name) && !empty($citizen_number)) {
        try {
            $query = "INSERT INTO citizens (citizen_number, full_name, father_name, grandfather_name, surname, mother_name, birth_date, birth_place, gender, marital_status, nationality, religion, district, area, neighborhood, street, house_number, building_type, phone, mobile, email, profession, workplace, monthly_income, income_currency_id, residence_status, social_status, family_members_count, dependents_count, special_needs, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$citizen_number, $full_name, $father_name, $grandfather_name, $surname, $mother_name, $birth_date, $birth_place, $gender, $marital_status, $nationality, $religion, $district, $area, $neighborhood, $street, $house_number, $building_type, $phone, $mobile, $email, $profession, $workplace, $monthly_income, $income_currency_id, $residence_status, $social_status, $family_members_count, $dependents_count, $special_needs, $notes]);
            $message = 'تم إضافة المواطن بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المواطن: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// معالجة تحديث حالة المواطن
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_citizen_status'])) {
    $citizen_id = intval($_POST['citizen_id']);
    $verification_status = $_POST['verification_status'];
    
    try {
        $query = "UPDATE citizens SET verification_status = ?, last_update_date = CURRENT_DATE WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$verification_status, $citizen_id]);
        $message = 'تم تحديث حالة التحقق بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تحديث حالة المواطن: ' . $e->getMessage();
    }
}

// معالجة تحديث بيانات المواطن
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_citizen'])) {
    $citizen_id = intval($_POST['citizen_id']);
    $citizen_number = trim($_POST['citizen_number']);
    $full_name = trim($_POST['full_name']);
    $father_name = trim($_POST['father_name']);
    $grandfather_name = trim($_POST['grandfather_name']);
    $surname = trim($_POST['surname']);
    $mother_name = trim($_POST['mother_name']);
    $birth_date = $_POST['birth_date'];
    $birth_place = trim($_POST['birth_place']);
    $gender = $_POST['gender'];
    $marital_status = $_POST['marital_status'];
    $nationality = trim($_POST['nationality']) ?: 'لبناني';
    $religion = trim($_POST['religion']);
    $district = trim($_POST['district']);
    $area = trim($_POST['area']);
    $neighborhood = trim($_POST['neighborhood']);
    $street = trim($_POST['street']);
    $house_number = trim($_POST['house_number']);
    $building_type = $_POST['building_type'];
    $phone = trim($_POST['phone']);
    $mobile = trim($_POST['mobile']);
    $email = trim($_POST['email']);
    $profession = trim($_POST['profession']);
    $workplace = trim($_POST['workplace']);
    $monthly_income = !empty($_POST['monthly_income']) ? floatval($_POST['monthly_income']) : null;
    $income_currency_id = !empty($_POST['income_currency_id']) ? intval($_POST['income_currency_id']) : null;
    $residence_status = $_POST['residence_status'];
    $social_status = $_POST['social_status'];
    $family_members_count = intval($_POST['family_members_count']) ?: 1;
    $dependents_count = intval($_POST['dependents_count']) ?: 0;
    $special_needs = trim($_POST['special_needs']);
    $notes = trim($_POST['notes']);
    
    if (!empty($full_name) && !empty($citizen_number)) {
        try {
            $query = "UPDATE citizens SET 
                citizen_number = ?, full_name = ?, father_name = ?, grandfather_name = ?, surname = ?, 
                mother_name = ?, birth_date = ?, birth_place = ?, gender = ?, marital_status = ?, 
                nationality = ?, religion = ?, district = ?, area = ?, neighborhood = ?, 
                street = ?, house_number = ?, building_type = ?, phone = ?, mobile = ?, 
                email = ?, profession = ?, workplace = ?, monthly_income = ?, income_currency_id = ?, residence_status = ?, 
                social_status = ?, family_members_count = ?, dependents_count = ?, special_needs = ?, 
                notes = ?, last_update_date = CURRENT_DATE 
                WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$citizen_number, $full_name, $father_name, $grandfather_name, $surname, 
                          $mother_name, $birth_date, $birth_place, $gender, $marital_status, 
                          $nationality, $religion, $district, $area, $neighborhood, 
                          $street, $house_number, $building_type, $phone, $mobile, 
                          $email, $profession, $workplace, $monthly_income, $income_currency_id, $residence_status, 
                          $social_status, $family_members_count, $dependents_count, $special_needs, 
                          $notes, $citizen_id]);
            $message = 'تم تحديث بيانات المواطن بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في تحديث بيانات المواطن: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// معالجة حذف المواطن
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_citizen'])) {
    $citizen_id = intval($_POST['citizen_id']);
    
    try {
        $query = "DELETE FROM citizens WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$citizen_id]);
        $message = 'تم حذف المواطن بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في حذف المواطن: ' . $e->getMessage();
    }
}

// جلب العملات أولاً
try {
    $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $currencies = [];
}

// جلب المواطنين
try {
    $filter_area = $_GET['area'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $filter_gender = $_GET['gender'] ?? '';
    $filter_name = $_GET['name'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_area)) {
        $where_conditions[] = "c.area LIKE ?";
        $params[] = "%$filter_area%";
    }
    
    if (!empty($filter_status)) {
        $where_conditions[] = "c.verification_status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_gender)) {
        $where_conditions[] = "c.gender = ?";
        $params[] = $filter_gender;
    }
    
    if (!empty($filter_name)) {
        $where_conditions[] = "c.full_name LIKE ?";
        $params[] = "%$filter_name%";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // فحص إذا كان عمود income_currency_id موجود
    $columnsStmt = $db->query("SHOW COLUMNS FROM citizens LIKE 'income_currency_id'");
    $columnExists = $columnsStmt->rowCount() > 0;
    
    if ($columnExists) {
        $stmt = $db->prepare("
            SELECT c.*, cur.currency_symbol, cur.currency_code 
            FROM citizens c
            LEFT JOIN currencies cur ON c.income_currency_id = cur.id
            $where_clause
            ORDER BY c.created_at DESC 
            LIMIT 50
        ");
    } else {
        $stmt = $db->prepare("
            SELECT c.* 
            FROM citizens c
            $where_clause
            ORDER BY c.created_at DESC 
            LIMIT 50
        ");
    }
    $stmt->execute($params);
    $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات المواطنين
    $stmt = $db->query("
        SELECT 
            verification_status,
            COUNT(*) as count
        FROM citizens 
        GROUP BY verification_status
    ");
    $verification_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_citizens,
            COUNT(CASE WHEN gender = 'ذكر' THEN 1 END) as male_count,
            COUNT(CASE WHEN gender = 'أنثى' THEN 1 END) as female_count,
            COUNT(CASE WHEN verification_status = 'مؤكد' THEN 1 END) as verified_count,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
            AVG(YEAR(CURRENT_DATE) - YEAR(birth_date)) as avg_age
        FROM citizens
    ");
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب المناطق المتاحة
    $stmt = $db->query("SELECT DISTINCT area FROM citizens WHERE area IS NOT NULL ORDER BY area");
    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error .= ' | خطأ في جلب المواطنين: ' . $e->getMessage();
    $citizens = [];
    $verification_stats = [];
    $general_stats = ['total_citizens' => 0, 'male_count' => 0, 'female_count' => 0, 'verified_count' => 0, 'active_count' => 0, 'avg_age' => 0];
    $areas = [];
}

$verification_statuses = ['غير مؤكد', 'مؤكد', 'قيد المراجعة'];
$genders = ['ذكر', 'أنثى'];
$marital_statuses = ['أعزب', 'متزوج', 'مطلق', 'أرمل'];
$building_types = ['بيت', 'شقة', 'فيلا', 'أخرى'];
$residence_statuses = ['مقيم دائم', 'مقيم مؤقت', 'نازح', 'لاجئ'];
$social_statuses = ['عادي', 'متقاعد', 'معاق', 'أرملة', 'يتيم', 'عاطل', 'طالب'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المواطنين - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none !important; }
        .modal.active { display: flex !important; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة المواطنين</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addCitizenModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ إضافة مواطن
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة شاملة لسجل المواطنين وبياناتهم الشخصية والاجتماعية</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- إحصائيات المواطنين -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي المواطنين</p>
                        <p class="text-2xl font-bold text-blue-600"><?= number_format($general_stats['total_citizens']) ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">👥</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">ذكور</p>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($general_stats['male_count']) ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">👨</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إناث</p>
                        <p class="text-2xl font-bold text-pink-600"><?= number_format($general_stats['female_count']) ?></p>
                    </div>
                    <div class="bg-pink-100 text-pink-600 p-3 rounded-full">👩</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">البيانات المؤكدة</p>
                        <p class="text-2xl font-bold text-purple-600"><?= number_format($general_stats['verified_count']) ?></p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">متوسط العمر</p>
                        <p class="text-2xl font-bold text-orange-600"><?= number_format($general_stats['avg_age'], 1) ?> سنة</p>
                    </div>
                    <div class="bg-orange-100 text-orange-600 p-3 rounded-full">📅</div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($filter_name) ?>" 
                           placeholder="ابحث بالاسم"
                           class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المنطقة</label>
                    <select name="area" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع المناطق</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?= $area ?>" <?= ($filter_area === $area) ? 'selected' : '' ?>><?= $area ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الجنس</label>
                    <select name="gender" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">الكل</option>
                        <?php foreach ($genders as $gender): ?>
                            <option value="<?= $gender ?>" <?= ($filter_gender === $gender) ? 'selected' : '' ?>><?= $gender ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">حالة التحقق</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الحالات</option>
                        <?php foreach ($verification_statuses as $status): ?>
                            <option value="<?= $status ?>" <?= ($filter_status === $status) ? 'selected' : '' ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        تطبيق الفلتر
                    </button>
                </div>
            </form>
        </div>

        <!-- جدول المواطنين -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">قائمة المواطنين</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-right p-4 font-semibold">رقم البطاقة</th>
                            <th class="text-right p-4 font-semibold">الاسم الكامل</th>
                            <th class="text-right p-4 font-semibold">العمر/الجنس</th>
                            <th class="text-right p-4 font-semibold">المنطقة</th>
                            <th class="text-right p-4 font-semibold">الاتصال</th>
                            <th class="text-right p-4 font-semibold">المهنة</th>
                            <th class="text-right p-4 font-semibold">الحالة الاجتماعية</th>
                            <th class="text-right p-4 font-semibold">حالة التحقق</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citizens as $citizen): ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-4 font-medium"><?= htmlspecialchars($citizen['citizen_number']) ?></td>
                            <td class="p-4">
                                <div class="font-medium"><?= htmlspecialchars($citizen['full_name']) ?></div>
                                <div class="text-sm text-slate-500">
                                    <?= htmlspecialchars($citizen['father_name']) ?> <?= htmlspecialchars($citizen['grandfather_name']) ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <?php
                                $age = $citizen['birth_date'] ? (new DateTime())->diff(new DateTime($citizen['birth_date']))->y : 'غير محدد';
                                ?>
                                <div><?= $age ?> سنة</div>
                                <span class="px-2 py-1 rounded text-xs <?= $citizen['gender'] === 'ذكر' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800' ?>">
                                    <?= htmlspecialchars($citizen['gender']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="font-medium text-sm"><?= htmlspecialchars($citizen['area'] ?? 'غير محدد') ?></div>
                                <div class="text-sm text-slate-500"><?= htmlspecialchars($citizen['neighborhood'] ?? '') ?></div>
                            </td>
                            <td class="p-4">
                                <?php if (!empty($citizen['mobile'])): ?>
                                    <div class="text-sm"><?= htmlspecialchars($citizen['mobile']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($citizen['phone'])): ?>
                                    <div class="text-sm text-slate-500"><?= htmlspecialchars($citizen['phone']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($citizen['email'])): ?>
                                    <div class="text-sm text-blue-600"><?= htmlspecialchars($citizen['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="text-sm"><?= htmlspecialchars($citizen['profession'] ?? 'غير محدد') ?></div>
                                <?php if (!empty($citizen['workplace'])): ?>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($citizen['workplace']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-800">
                                    <?= htmlspecialchars($citizen['marital_status']) ?>
                                </span>
                                <div class="text-xs text-slate-500 mt-1">
                                    أفراد العائلة: <?= $citizen['family_members_count'] ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?= $citizen['verification_status'] === 'مؤكد' ? 'bg-green-100 text-green-800' : 
                                       ($citizen['verification_status'] === 'قيد المراجعة' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= htmlspecialchars($citizen['verification_status']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <button onclick="viewCitizen(<?= $citizen['id'] ?>)" 
                                            class="text-blue-600 hover:text-blue-800 text-sm px-2 py-1 rounded bg-blue-100 hover:bg-blue-200">
                                        عرض
                                    </button>
                                    <button onclick="editCitizen(<?= $citizen['id'] ?>)" 
                                            class="text-yellow-600 hover:text-yellow-800 text-sm px-2 py-1 rounded bg-yellow-100 hover:bg-yellow-200">
                                        تعديل
                                    </button>
                                    <button onclick="updateVerificationStatus(<?= $citizen['id'] ?>, '<?= htmlspecialchars($citizen['verification_status']) ?>')" 
                                            class="text-green-600 hover:text-green-800 text-sm px-2 py-1 rounded bg-green-100 hover:bg-green-200">
                                        تحديث الحالة
                                    </button>
                                    <button onclick="deleteCitizen(<?= $citizen['id'] ?>, '<?= htmlspecialchars($citizen['full_name']) ?>')" 
                                            class="text-red-600 hover:text-red-800 text-sm px-2 py-1 rounded bg-red-100 hover:bg-red-200">
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal إضافة مواطن جديد -->
    <div id="addCitizenModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-6xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">إضافة مواطن جديد</h3>
                <button onclick="closeModal('addCitizenModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-6">
                <!-- المعلومات الشخصية الأساسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">المعلومات الشخصية الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم البطاقة الموحدة *</label>
                            <input type="text" name="citizen_number" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الاسم الكامل *</label>
                            <input type="text" name="full_name" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم الأب</label>
                            <input type="text" name="father_name" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم الجد</label>
                            <input type="text" name="grandfather_name" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اللقب</label>
                            <input type="text" name="surname" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم الأم</label>
                            <input type="text" name="mother_name" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الولادة</label>
                            <input type="date" name="birth_date" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">مكان الولادة</label>
                            <input type="text" name="birth_place" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الجنس *</label>
                            <select name="gender" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">اختر الجنس</option>
                                <?php foreach ($genders as $gender): ?>
                                    <option value="<?= $gender ?>"><?= $gender ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحالة الاجتماعية</label>
                            <select name="marital_status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($marital_statuses as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الجنسية</label>
                            <input type="text" name="nationality" value="لبناني" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الديانة</label>
                            <input type="text" name="religion" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات السكن -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات السكن</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">المحافظة</label>
                            <input type="text" name="district" value="عكار" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">المنطقة</label>
                            <input type="text" name="area" value="تكريت" placeholder="تكريت" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحي</label>
                            <input type="text" name="neighborhood" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الشارع</label>
                            <input type="text" name="street" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الدار</label>
                            <input type="text" name="house_number" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع المبنى</label>
                            <select name="building_type" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($building_types as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- معلومات الاتصال -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات الاتصال</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الهاتف</label>
                            <input type="text" name="phone" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الموبايل</label>
                            <input type="text" name="mobile" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- المعلومات المهنية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">المعلومات المهنية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">المهنة</label>
                            <input type="text" name="profession" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">مكان العمل</label>
                            <input type="text" name="workplace" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الراتب الشهري</label>
                            <input type="number" name="monthly_income" step="1000" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عملة الراتب</label>
                            <?php if (!empty($currencies)): ?>
                                <select name="income_currency_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">اختر العملة (اختياري)</option>
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?= $currency['id'] ?>">
                                            <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="w-full px-3 py-2 border border-yellow-300 bg-yellow-50 rounded-lg text-sm text-yellow-700">
                                    ⚠️ لا توجد عملات متاحة. يرجى إضافة عملات من <a href="all_tables_manager.php" class="underline font-bold">إدارة الجداول المرجعية</a>
                                </div>
                                <input type="hidden" name="income_currency_id" value="">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- الحالة والإقامة -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">حالة الإقامة والوضع الاجتماعي</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">حالة الإقامة</label>
                            <select name="residence_status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($residence_statuses as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحالة الاجتماعية الخاصة</label>
                            <select name="social_status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($social_statuses as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عدد أفراد العائلة</label>
                            <input type="number" name="family_members_count" value="1" min="1" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عدد المعالين</label>
                            <input type="number" name="dependents_count" value="0" min="0" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- ملاحظات خاصة -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات إضافية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">الاحتياجات الخاصة</label>
                            <textarea name="special_needs" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">ملاحظات عامة</label>
                            <textarea name="notes" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('addCitizenModal')" 
                            class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        إلغاء
                    </button>
                    <button type="submit" name="add_citizen" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        إضافة المواطن
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal عرض تفاصيل المواطن -->
    <div id="viewCitizenModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-6xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">تفاصيل المواطن</h3>
                <button onclick="closeModal('viewCitizenModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <div class="space-y-6">
                <!-- المعلومات الشخصية الأساسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3 bg-blue-50 p-2 rounded">المعلومات الشخصية الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="text-sm font-medium text-gray-600">رقم البطاقة:</label>
                            <p id="view_citizen_number" class="font-semibold"></p></div>
                        <div class="md:col-span-2"><label class="text-sm font-medium text-gray-600">الاسم الكامل:</label>
                            <p id="view_full_name" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">اسم الأب:</label>
                            <p id="view_father_name" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">اسم الجد:</label>
                            <p id="view_grandfather_name" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">اللقب:</label>
                            <p id="view_surname" class="font-semibold"></p></div>
                        <div class="md:col-span-2"><label class="text-sm font-medium text-gray-600">اسم الأم:</label>
                            <p id="view_mother_name" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">تاريخ الولادة:</label>
                            <p id="view_birth_date" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">مكان الولادة:</label>
                            <p id="view_birth_place" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الجنس:</label>
                            <p id="view_gender" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الحالة الاجتماعية:</label>
                            <p id="view_marital_status" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الجنسية:</label>
                            <p id="view_nationality" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الديانة:</label>
                            <p id="view_religion" class="font-semibold"></p></div>
                    </div>
                </div>

                <!-- معلومات السكن -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3 bg-green-50 p-2 rounded">معلومات السكن</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="text-sm font-medium text-gray-600">المحافظة:</label>
                            <p id="view_district" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">المنطقة:</label>
                            <p id="view_area" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الحي:</label>
                            <p id="view_neighborhood" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الشارع:</label>
                            <p id="view_street" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">رقم الدار:</label>
                            <p id="view_house_number" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">نوع المبنى:</label>
                            <p id="view_building_type" class="font-semibold"></p></div>
                    </div>
                </div>

                <!-- معلومات الاتصال -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3 bg-purple-50 p-2 rounded">معلومات الاتصال</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="text-sm font-medium text-gray-600">رقم الهاتف:</label>
                            <p id="view_phone" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">رقم الموبايل:</label>
                            <p id="view_mobile" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">البريد الإلكتروني:</label>
                            <p id="view_email" class="font-semibold"></p></div>
                    </div>
                </div>

                <!-- المعلومات المهنية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3 bg-yellow-50 p-2 rounded">المعلومات المهنية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="text-sm font-medium text-gray-600">المهنة:</label>
                            <p id="view_profession" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">مكان العمل:</label>
                            <p id="view_workplace" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الراتب الشهري:</label>
                            <p id="view_monthly_income" class="font-semibold"></p></div>
                    </div>
                </div>

                <!-- الحالة والإقامة -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3 bg-orange-50 p-2 rounded">حالة الإقامة والوضع الاجتماعي</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div><label class="text-sm font-medium text-gray-600">حالة الإقامة:</label>
                            <p id="view_residence_status" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">الحالة الاجتماعية الخاصة:</label>
                            <p id="view_social_status" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">عدد أفراد العائلة:</label>
                            <p id="view_family_members_count" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">عدد المعالين:</label>
                            <p id="view_dependents_count" class="font-semibold"></p></div>
                    </div>
                </div>

                <!-- ملاحظات خاصة -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3 bg-red-50 p-2 rounded">معلومات إضافية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="text-sm font-medium text-gray-600">الاحتياجات الخاصة:</label>
                            <p id="view_special_needs" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">ملاحظات عامة:</label>
                            <p id="view_notes" class="font-semibold"></p></div>
                    </div>
                </div>

                <!-- معلومات النظام -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-3 bg-gray-50 p-2 rounded">معلومات النظام</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="text-sm font-medium text-gray-600">حالة التحقق:</label>
                            <p id="view_verification_status" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">تاريخ الإنشاء:</label>
                            <p id="view_created_at" class="font-semibold"></p></div>
                        <div><label class="text-sm font-medium text-gray-600">آخر تحديث:</label>
                            <p id="view_last_update_date" class="font-semibold"></p></div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeModal('viewCitizenModal')" 
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    إغلاق
                </button>
            </div>
        </div>
    </div>

    <!-- Modal تعديل المواطن -->
    <div id="editCitizenModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-6xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">تعديل بيانات المواطن</h3>
                <button onclick="closeModal('editCitizenModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="citizen_id" id="edit_citizen_id">
                
                <!-- المعلومات الشخصية الأساسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">المعلومات الشخصية الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم البطاقة الموحدة *</label>
                            <input type="text" name="citizen_number" id="edit_citizen_number" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الاسم الكامل *</label>
                            <input type="text" name="full_name" id="edit_full_name" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم الأب</label>
                            <input type="text" name="father_name" id="edit_father_name" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم الجد</label>
                            <input type="text" name="grandfather_name" id="edit_grandfather_name" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">اللقب</label>
                            <input type="text" name="surname" id="edit_surname" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم الأم</label>
                            <input type="text" name="mother_name" id="edit_mother_name" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تاريخ الولادة</label>
                            <input type="date" name="birth_date" id="edit_birth_date" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">مكان الولادة</label>
                            <input type="text" name="birth_place" id="edit_birth_place" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الجنس *</label>
                            <select name="gender" id="edit_gender" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">اختر الجنس</option>
                                <?php foreach ($genders as $gender): ?>
                                    <option value="<?= $gender ?>"><?= $gender ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحالة الاجتماعية</label>
                            <select name="marital_status" id="edit_marital_status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($marital_statuses as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الجنسية</label>
                            <input type="text" name="nationality" id="edit_nationality" value="لبناني" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الديانة</label>
                            <input type="text" name="religion" id="edit_religion" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات السكن -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات السكن</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">المحافظة</label>
                            <input type="text" name="district" id="edit_district" value="عكار" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">المنطقة</label>
                            <input type="text" name="area" id="edit_area" value="تكريت" placeholder="تكريت" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحي</label>
                            <input type="text" name="neighborhood" id="edit_neighborhood" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الشارع</label>
                            <input type="text" name="street" id="edit_street" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الدار</label>
                            <input type="text" name="house_number" id="edit_house_number" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع المبنى</label>
                            <select name="building_type" id="edit_building_type" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($building_types as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- معلومات الاتصال -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات الاتصال</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الهاتف</label>
                            <input type="text" name="phone" id="edit_phone" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الموبايل</label>
                            <input type="text" name="mobile" id="edit_mobile" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" id="edit_email" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- المعلومات المهنية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">المعلومات المهنية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">المهنة</label>
                            <input type="text" name="profession" id="edit_profession" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">مكان العمل</label>
                            <input type="text" name="workplace" id="edit_workplace" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الراتب الشهري</label>
                            <input type="number" name="monthly_income" id="edit_monthly_income" step="1000" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عملة الراتب</label>
                            <?php if (!empty($currencies)): ?>
                                <select name="income_currency_id" id="edit_income_currency_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">اختر العملة (اختياري)</option>
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?= $currency['id'] ?>">
                                            <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="w-full px-3 py-2 border border-yellow-300 bg-yellow-50 rounded-lg text-sm text-yellow-700">
                                    ⚠️ لا توجد عملات متاحة
                                </div>
                                <input type="hidden" name="income_currency_id" id="edit_income_currency_id" value="">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- الحالة والإقامة -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">حالة الإقامة والوضع الاجتماعي</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">حالة الإقامة</label>
                            <select name="residence_status" id="edit_residence_status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($residence_statuses as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">الحالة الاجتماعية الخاصة</label>
                            <select name="social_status" id="edit_social_status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($social_statuses as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عدد أفراد العائلة</label>
                            <input type="number" name="family_members_count" id="edit_family_members_count" value="1" min="1" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">عدد المعالين</label>
                            <input type="number" name="dependents_count" id="edit_dependents_count" value="0" min="0" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- ملاحظات خاصة -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات إضافية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">الاحتياجات الخاصة</label>
                            <textarea name="special_needs" id="edit_special_needs" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">ملاحظات عامة</label>
                            <textarea name="notes" id="edit_notes" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('editCitizenModal')" 
                            class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        إلغاء
                    </button>
                    <button type="submit" name="edit_citizen" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // تخزين بيانات المواطنين
        const citizensData = <?= json_encode($citizens, JSON_UNESCAPED_UNICODE) ?>;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function viewCitizen(id) {
            const citizen = citizensData.find(c => c.id == id);
            if (!citizen) return;
            
            openModal('viewCitizenModal');
            
            // ملء بيانات العرض
            document.getElementById('view_citizen_number').textContent = citizen.citizen_number || '-';
            document.getElementById('view_full_name').textContent = citizen.full_name || '-';
            document.getElementById('view_father_name').textContent = citizen.father_name || '-';
            document.getElementById('view_grandfather_name').textContent = citizen.grandfather_name || '-';
            document.getElementById('view_surname').textContent = citizen.surname || '-';
            document.getElementById('view_mother_name').textContent = citizen.mother_name || '-';
            document.getElementById('view_birth_date').textContent = citizen.birth_date || '-';
            document.getElementById('view_birth_place').textContent = citizen.birth_place || '-';
            document.getElementById('view_gender').textContent = citizen.gender || '-';
            document.getElementById('view_marital_status').textContent = citizen.marital_status || '-';
            document.getElementById('view_nationality').textContent = citizen.nationality || '-';
            document.getElementById('view_religion').textContent = citizen.religion || '-';
            document.getElementById('view_district').textContent = citizen.district || '-';
            document.getElementById('view_area').textContent = citizen.area || '-';
            document.getElementById('view_neighborhood').textContent = citizen.neighborhood || '-';
            document.getElementById('view_street').textContent = citizen.street || '-';
            document.getElementById('view_house_number').textContent = citizen.house_number || '-';
            document.getElementById('view_building_type').textContent = citizen.building_type || '-';
            document.getElementById('view_phone').textContent = citizen.phone || '-';
            document.getElementById('view_mobile').textContent = citizen.mobile || '-';
            document.getElementById('view_email').textContent = citizen.email || '-';
            document.getElementById('view_profession').textContent = citizen.profession || '-';
            document.getElementById('view_workplace').textContent = citizen.workplace || '-';
            
            // عرض الراتب مع العملة
            if (citizen.monthly_income) {
                const currencySymbol = citizen.currency_symbol || 'ل.ل';
                document.getElementById('view_monthly_income').textContent = 
                    citizen.monthly_income.toLocaleString() + ' ' + currencySymbol;
            } else {
                document.getElementById('view_monthly_income').textContent = '-';
            }
            document.getElementById('view_residence_status').textContent = citizen.residence_status || '-';
            document.getElementById('view_social_status').textContent = citizen.social_status || '-';
            document.getElementById('view_family_members_count').textContent = citizen.family_members_count || '-';
            document.getElementById('view_dependents_count').textContent = citizen.dependents_count || '-';
            document.getElementById('view_special_needs').textContent = citizen.special_needs || '-';
            document.getElementById('view_notes').textContent = citizen.notes || '-';
            document.getElementById('view_verification_status').textContent = citizen.verification_status || '-';
            document.getElementById('view_created_at').textContent = citizen.created_at || '-';
            document.getElementById('view_last_update_date').textContent = citizen.last_update_date || '-';
        }

        function editCitizen(id) {
            const citizen = citizensData.find(c => c.id == id);
            if (!citizen) return;
            
            openModal('editCitizenModal');
            
            // ملء النموذج
            document.getElementById('edit_citizen_id').value = citizen.id;
            document.getElementById('edit_citizen_number').value = citizen.citizen_number || '';
            document.getElementById('edit_full_name').value = citizen.full_name || '';
            document.getElementById('edit_father_name').value = citizen.father_name || '';
            document.getElementById('edit_grandfather_name').value = citizen.grandfather_name || '';
            document.getElementById('edit_surname').value = citizen.surname || '';
            document.getElementById('edit_mother_name').value = citizen.mother_name || '';
            document.getElementById('edit_birth_date').value = citizen.birth_date || '';
            document.getElementById('edit_birth_place').value = citizen.birth_place || '';
            document.getElementById('edit_gender').value = citizen.gender || '';
            document.getElementById('edit_marital_status').value = citizen.marital_status || '';
            document.getElementById('edit_nationality').value = citizen.nationality || '';
            document.getElementById('edit_religion').value = citizen.religion || '';
            document.getElementById('edit_district').value = citizen.district || '';
            document.getElementById('edit_area').value = citizen.area || '';
            document.getElementById('edit_neighborhood').value = citizen.neighborhood || '';
            document.getElementById('edit_street').value = citizen.street || '';
            document.getElementById('edit_house_number').value = citizen.house_number || '';
            document.getElementById('edit_building_type').value = citizen.building_type || '';
            document.getElementById('edit_phone').value = citizen.phone || '';
            document.getElementById('edit_mobile').value = citizen.mobile || '';
            document.getElementById('edit_email').value = citizen.email || '';
            document.getElementById('edit_profession').value = citizen.profession || '';
            document.getElementById('edit_workplace').value = citizen.workplace || '';
            document.getElementById('edit_monthly_income').value = citizen.monthly_income || '';
            document.getElementById('edit_income_currency_id').value = citizen.income_currency_id || '';
            document.getElementById('edit_residence_status').value = citizen.residence_status || '';
            document.getElementById('edit_social_status').value = citizen.social_status || '';
            document.getElementById('edit_family_members_count').value = citizen.family_members_count || 1;
            document.getElementById('edit_dependents_count').value = citizen.dependents_count || 0;
            document.getElementById('edit_special_needs').value = citizen.special_needs || '';
            document.getElementById('edit_notes').value = citizen.notes || '';
        }

        function deleteCitizen(id, full_name) {
            if (confirm('هل أنت متأكد من حذف المواطن "' + full_name + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="citizen_id" value="${id}">
                    <input type="hidden" name="delete_citizen" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateVerificationStatus(citizenId, currentStatus) {
            const statuses = ['غير مؤكد', 'مؤكد', 'قيد المراجعة'];
            const newStatus = prompt('اختر حالة التحقق الجديدة:\n' + statuses.join('\n'), currentStatus);
            
            if (newStatus && statuses.includes(newStatus) && newStatus !== currentStatus) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="citizen_id" value="${citizenId}">
                    <input type="hidden" name="verification_status" value="${newStatus}">
                    <input type="hidden" name="update_citizen_status" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // إغلاق المودال عند النقر خارجه
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html> 
