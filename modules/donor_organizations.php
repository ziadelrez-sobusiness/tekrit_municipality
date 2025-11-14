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

// معالجة إضافة جهة مانحة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_donor'])) {
    $donor_code = trim($_POST['donor_code']);
    $organization_name = trim($_POST['organization_name']);
    $organization_name_en = trim($_POST['organization_name_en']);
    $donor_type = $_POST['donor_type'];
    $country = trim($_POST['country']);
    $city = trim($_POST['city']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website']);
    $contact_person_name = trim($_POST['contact_person_name']);
    $contact_person_title = trim($_POST['contact_person_title']);
    $contact_person_phone = trim($_POST['contact_person_phone']);
    $contact_person_email = trim($_POST['contact_person_email']);
    $specialization = !empty($_POST['specialization']) ? json_encode($_POST['specialization']) : null;
    $preferred_currency_id = intval($_POST['preferred_currency_id']);
    $partnership_level = $_POST['partnership_level'];
    $reliability_rating = $_POST['reliability_rating'];
    
    if (!empty($organization_name) && !empty($donor_code)) {
        try {
            $query = "INSERT INTO donor_organizations (donor_code, organization_name, organization_name_en, donor_type, country, city, address, phone, email, website, contact_person_name, contact_person_title, contact_person_phone, contact_person_email, specialization, preferred_currency_id, partnership_level, reliability_rating, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$donor_code, $organization_name, $organization_name_en, $donor_type, $country, $city, $address, $phone, $email, $website, $contact_person_name, $contact_person_title, $contact_person_phone, $contact_person_email, $specialization, $preferred_currency_id, $partnership_level, $reliability_rating, $user['id']]);
            $message = 'تم إضافة الجهة المانحة بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة الجهة المانحة: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// معالجة تحديث حالة الجهة المانحة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $donor_id = intval($_POST['donor_id']);
    $status = $_POST['status'];
    
    try {
        $query = "UPDATE donor_organizations SET status = ?, updated_by_user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$status, $user['id'], $donor_id]);
        $message = 'تم تحديث حالة الجهة المانحة بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تحديث الجهة المانحة: ' . $e->getMessage();
    }
}

// جلب الجهات المانحة
try {
    $filter_type = $_GET['type'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $filter_country = $_GET['country'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($filter_type)) {
        $where_conditions[] = "donor_type = ?";
        $params[] = $filter_type;
    }
    
    if (!empty($filter_status)) {
        $where_conditions[] = "status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_country)) {
        $where_conditions[] = "country LIKE ?";
        $params[] = "%$filter_country%";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT do.*, 
               c.currency_symbol, c.currency_code,
               u.full_name as created_by_name
        FROM donor_organizations do 
        LEFT JOIN currencies c ON do.preferred_currency_id = c.id
        LEFT JOIN users u ON do.created_by_user_id = u.id
        $where_clause
        ORDER BY do.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute($params);
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات الجهات المانحة
    $stmt = $db->query("
        SELECT 
            donor_type,
            COUNT(*) as count,
            SUM(total_donations_iqd) as total_donations
        FROM donor_organizations 
        GROUP BY donor_type
    ");
    $donor_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_donors,
            SUM(total_donations_iqd) as total_donations_iqd,
            COUNT(CASE WHEN status = 'نشط' THEN 1 END) as active_donors,
            COUNT(CASE WHEN partnership_level = 'استراتيجي' THEN 1 END) as strategic_partners
        FROM donor_organizations
    ");
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب العملات
    $stmt = $db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب البلدان المتاحة
    $stmt = $db->query("SELECT DISTINCT country FROM donor_organizations WHERE country IS NOT NULL ORDER BY country");
    $countries = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $donors = [];
    $donor_stats = [];
    $general_stats = ['total_donors' => 0, 'total_donations_iqd' => 0, 'active_donors' => 0, 'strategic_partners' => 0];
    $currencies = [];
    $countries = [];
}

$donor_types = [
    'حكومي محلي', 'حكومي فيدرالي', 'منظمة دولية', 'منظمة خيرية دولية', 
    'منظمة خيرية محلية', 'شركة محلية', 'شركة دولية', 'بنك', 'سفارة', 
    'أفراد', 'جمعية', 'اتحاد', 'مؤسسة تعليمية', 'مؤسسة طبية', 'أخرى'
];

$specializations = [
    'البنية التحتية', 'التعليم', 'الصحة', 'البيئة', 'التنمية الاقتصادية',
    'التنمية المستدامة', 'حقوق الإنسان', 'الديمقراطية', 'الثقافة',
    'الرياضة', 'المرأة والطفل', 'كبار السن', 'ذوي الاحتياجات الخاصة',
    'الطوارئ والإغاثة', 'التكنولوجيا', 'الزراعة', 'المياه والصرف الصحي'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الجهات المانحة - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة الجهات المانحة</h1>
                <div class="flex gap-3">
                    <button onclick="openModal('addDonorModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ➕ جهة مانحة جديدة
                    </button>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">إدارة شاملة للجهات المانحة المحلية والدولية وبيانات الاتصال</p>
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

        <!-- إحصائيات الجهات المانحة -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي الجهات</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $general_stats['total_donors'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">🏢</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">الجهات النشطة</p>
                        <p class="text-2xl font-bold text-green-600"><?= $general_stats['active_donors'] ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">✅</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">الشراكات الاستراتيجية</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $general_stats['strategic_partners'] ?></p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">🤝</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي التبرعات</p>
                        <p class="text-xl font-bold text-yellow-600"><?= number_format($general_stats['total_donations_iqd']) ?> ل.ل</p>
                    </div>
                    <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">💰</div>
                </div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h3 class="font-semibold mb-4">البحث والفلترة</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع الجهة</label>
                    <select name="type" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($donor_types as $type): ?>
                            <option value="<?= $type ?>" <?= ($filter_type === $type) ? 'selected' : '' ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع الحالات</option>
                        <option value="نشط" <?= ($filter_status === 'نشط') ? 'selected' : '' ?>>نشط</option>
                        <option value="غير نشط" <?= ($filter_status === 'غير نشط') ? 'selected' : '' ?>>غير نشط</option>
                        <option value="معلق" <?= ($filter_status === 'معلق') ? 'selected' : '' ?>>معلق</option>
                        <option value="محظور" <?= ($filter_status === 'محظور') ? 'selected' : '' ?>>محظور</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البلد</label>
                    <select name="country" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">جميع البلدان</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= $country ?>" <?= ($filter_country === $country) ? 'selected' : '' ?>><?= $country ?></option>
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

        <!-- جدول الجهات المانحة -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">قائمة الجهات المانحة</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-right p-4 font-semibold">الكود</th>
                            <th class="text-right p-4 font-semibold">اسم الجهة</th>
                            <th class="text-right p-4 font-semibold">النوع</th>
                            <th class="text-right p-4 font-semibold">البلد</th>
                            <th class="text-right p-4 font-semibold">الاتصال</th>
                            <th class="text-right p-4 font-semibold">مستوى الشراكة</th>
                            <th class="text-right p-4 font-semibold">إجمالي التبرعات</th>
                            <th class="text-right p-4 font-semibold">الحالة</th>
                            <th class="text-right p-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors as $donor): ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-4 font-medium"><?= htmlspecialchars($donor['donor_code']) ?></td>
                            <td class="p-4">
                                <div class="font-medium"><?= htmlspecialchars($donor['organization_name']) ?></div>
                                <?php if (!empty($donor['organization_name_en'])): ?>
                                    <div class="text-sm text-slate-500"><?= htmlspecialchars($donor['organization_name_en']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars($donor['donor_type']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div><?= htmlspecialchars($donor['country'] ?? 'غير محدد') ?></div>
                                <?php if (!empty($donor['city'])): ?>
                                    <div class="text-sm text-slate-500"><?= htmlspecialchars($donor['city']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if (!empty($donor['contact_person_name'])): ?>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($donor['contact_person_name']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($donor['phone'])): ?>
                                    <div class="text-sm"><?= htmlspecialchars($donor['phone']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($donor['email'])): ?>
                                    <div class="text-sm text-blue-600"><?= htmlspecialchars($donor['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?= $donor['partnership_level'] === 'استراتيجي' ? 'bg-green-100 text-green-800' : 
                                       ($donor['partnership_level'] === 'مستمر' ? 'bg-blue-100 text-blue-800' : 
                                       ($donor['partnership_level'] === 'مؤقت' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')) ?>">
                                    <?= htmlspecialchars($donor['partnership_level']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-green-600">
                                    <?= number_format($donor['total_donations_iqd']) ?> ل.ل
                                </div>
                                <div class="text-sm text-slate-500">
                                    <?= $donor['total_donations_count'] ?> تبرع
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-sm 
                                    <?= $donor['status'] === 'نشط' ? 'bg-green-100 text-green-800' : 
                                       ($donor['status'] === 'غير نشط' ? 'bg-gray-100 text-gray-800' : 
                                       ($donor['status'] === 'معلق' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')) ?>">
                                    <?= htmlspecialchars($donor['status']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <button onclick="viewDonor(<?= $donor['id'] ?>)" 
                                            class="text-blue-600 hover:text-blue-800 text-sm">
                                        عرض
                                    </button>
                                    <button onclick="updateStatus(<?= $donor['id'] ?>, '<?= htmlspecialchars($donor['status']) ?>')" 
                                            class="text-yellow-600 hover:text-yellow-800 text-sm">
                                        تحديث
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

    <!-- Modal إضافة جهة مانحة جديدة -->
    <div id="addDonorModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold">إضافة جهة مانحة جديدة</h3>
                <button onclick="closeModal('addDonorModal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            
            <form method="POST" class="space-y-6">
                <!-- المعلومات الأساسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">كود الجهة المانحة *</label>
                            <input type="text" name="donor_code" required placeholder="مثال: UNDP001"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">نوع الجهة *</label>
                            <select name="donor_type" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">اختر النوع</option>
                                <?php foreach ($donor_types as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم الجهة (بالعربية) *</label>
                            <input type="text" name="organization_name" required 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">اسم الجهة (بالإنجليزية)</label>
                            <input type="text" name="organization_name_en" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات الاتصال -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">معلومات الاتصال</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">البلد</label>
                            <input type="text" name="country" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">المدينة</label>
                            <input type="text" name="city" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">العنوان</label>
                            <textarea name="address" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">رقم الهاتف</label>
                            <input type="text" name="phone" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">الموقع الإلكتروني</label>
                            <input type="url" name="website" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- معلومات الاتصال الرئيسية -->
                <div class="border-b pb-4">
                    <h4 class="text-lg font-medium text-gray-900 mb-3">الشخص المسؤول</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">اسم الشخص المسؤول</label>
                            <input type="text" name="contact_person_name" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">المنصب</label>
                            <input type="text" name="contact_person_title" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">هاتف الشخص المسؤول</label>
                            <input type="text" name="contact_person_phone" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">إيميل الشخص المسؤول</label>
                            <input type="email" name="contact_person_email" 
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- التفاصيل الإضافية -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-3">تفاصيل الشراكة</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">العملة المفضلة</label>
                            <select name="preferred_currency_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency['id'] ?>" <?= $currency['currency_code'] === 'IQD' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency['currency_name']) ?> (<?= htmlspecialchars($currency['currency_symbol']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">مستوى الشراكة</label>
                            <select name="partnership_level" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="مؤقت">مؤقت</option>
                                <option value="مستمر">مستمر</option>
                                <option value="استراتيجي">استراتيجي</option>
                                <option value="لمرة واحدة">لمرة واحدة</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">تقييم الموثوقية</label>
                            <select name="reliability_rating" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="جيد">جيد</option>
                                <option value="ممتاز">ممتاز</option>
                                <option value="جيد جداً">جيد جداً</option>
                                <option value="مقبول">مقبول</option>
                                <option value="ضعيف">ضعيف</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium mb-2">مجالات التخصص</label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <?php foreach ($specializations as $spec): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="specialization[]" value="<?= $spec ?>" 
                                           class="mr-2 text-blue-600">
                                    <span class="text-sm"><?= $spec ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeModal('addDonorModal')" 
                            class="px-4 py-2 text-slate-600 hover:text-slate-800">
                        إلغاء
                    </button>
                    <button type="submit" name="add_donor" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        إضافة الجهة المانحة
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function viewDonor(id) {
            // عرض تفاصيل الجهة المانحة
            alert('عرض تفاصيل الجهة المانحة #' + id);
        }

        function updateStatus(donorId, currentStatus) {
            const statuses = ['نشط', 'غير نشط', 'معلق', 'محظور', 'منتهي الشراكة'];
            const newStatus = prompt('اختر الحالة الجديدة:\n' + statuses.join('\n'), currentStatus);
            
            if (newStatus && statuses.includes(newStatus) && newStatus !== currentStatus) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="donor_id" value="${donorId}">
                    <input type="hidden" name="status" value="${newStatus}">
                    <input type="hidden" name="update_status" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 
