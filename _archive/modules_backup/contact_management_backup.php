<?php
session_start();
require_once '../config/database.php';

// Disable warnings for cleaner output
error_reporting(E_ERROR | E_PARSE);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle AJAX get message request
if (isset($_GET['action']) && $_GET['action'] == 'get_message') {
    header('Content-Type: text/html; charset=utf-8');
    
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo "معرف الرسالة غير موجود";
        exit();
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $message = $stmt->fetch();
        
        if (!$message) {
            http_response_code(404);
            echo "الرسالة غير موجودة";
            exit();
        }
        
        ?>
        <div class="space-y-4">
            <div>
                <h4 class="font-semibold text-gray-900">المرسل:</h4>
                <p class="text-gray-600"><?= htmlspecialchars($message['sender_name']) ?></p>
            </div>
            
            <div>
                <h4 class="font-semibold text-gray-900">البريد الإلكتروني:</h4>
                <p class="text-gray-600"><?= htmlspecialchars($message['sender_email']) ?></p>
            </div>
            
            <?php if (!empty($message['sender_phone'])): ?>
            <div>
                <h4 class="font-semibold text-gray-900">رقم الهاتف:</h4>
                <p class="text-gray-600"><?= htmlspecialchars($message['sender_phone']) ?></p>
            </div>
            <?php endif; ?>
            
            <div>
                <h4 class="font-semibold text-gray-900">الموضوع:</h4>
                <p class="text-gray-600"><?= htmlspecialchars($message['subject']) ?></p>
            </div>
            
            <div>
                <h4 class="font-semibold text-gray-900">الرسالة:</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($message['message']) ?></p>
                </div>
            </div>
            
            <div>
                <h4 class="font-semibold text-gray-900">تاريخ الإرسال:</h4>
                <p class="text-gray-600"><?= date('Y/m/d H:i:s', strtotime($message['created_at'])) ?></p>
            </div>
            
            <div>
                <h4 class="font-semibold text-gray-900">الحالة:</h4>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                    <?php 
                        switch($message['status']) {
                            case 'جديد': echo 'bg-red-100 text-red-800'; break;
                            case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'تم الرد': echo 'bg-green-100 text-green-800'; break;
                            case 'مغلق': echo 'bg-gray-100 text-gray-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                    ?>">
                    <?= htmlspecialchars($message['status']) ?>
                </span>
            </div>
        </div>
        <?php
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo "خطأ في قاعدة البيانات";
    }
    exit();
}

$success_message = '';
$error_message = '';

// Handle message status update
if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $message_id = isset($_POST['message_id']) ? trim($_POST['message_id']) : '';
    $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    if (!empty($message_id) && !empty($new_status)) {
        try {
            $stmt = $db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $message_id]);
            $success_message = "تم تحديث حالة الرسالة بنجاح";
        } catch(PDOException $e) {
            $error_message = "خطأ في تحديث الحالة: " . $e->getMessage();
        }
    } else {
        $error_message = "بيانات غير مكتملة لتحديث الحالة";
    }
}

// Handle settings update
if (isset($_POST['action']) && $_POST['action'] == 'update_settings') {
    $settings = [
        'contact_phone' => isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '',
        'contact_email' => isset($_POST['contact_email']) ? trim($_POST['contact_email']) : '',
        'contact_address' => isset($_POST['contact_address']) ? trim($_POST['contact_address']) : '',
        'emergency_phone' => isset($_POST['emergency_phone']) ? trim($_POST['emergency_phone']) : '',
        'contact_location_lat' => isset($_POST['contact_location_lat']) ? trim($_POST['contact_location_lat']) : '',
        'contact_location_lng' => isset($_POST['contact_location_lng']) ? trim($_POST['contact_location_lng']) : '',
        'contact_location_name' => isset($_POST['contact_location_name']) ? trim($_POST['contact_location_name']) : ''
    ];
    
    try {
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $success_message = "تم تحديث معلومات الاتصال بنجاح";
    } catch(PDOException $e) {
        $error_message = "خطأ في تحديث المعلومات: " . $e->getMessage();
    }
}

// Handle message deletion
if (isset($_POST['action']) && $_POST['action'] == 'delete_message') {
    $message_id = isset($_POST['message_id']) ? trim($_POST['message_id']) : '';
    
    if (!empty($message_id)) {
        try {
            $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$message_id]);
            $success_message = "تم حذف الرسالة بنجاح";
        } catch(PDOException $e) {
            $error_message = "خطأ في حذف الرسالة: " . $e->getMessage();
        }
    } else {
        $error_message = "معرف الرسالة غير موجود";
    }
}

// Get messages with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(sender_name LIKE ? OR sender_email LIKE ? OR subject LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM contact_messages $where_clause";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_messages = $count_stmt->fetchColumn();
$total_pages = ceil($total_messages / $limit);

// Get messages
$sql = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Get current settings
function getSetting($key, $default = '') {
    global $db;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch(PDOException $e) {
        return $default;
    }
}

$current_settings = [
    'contact_phone' => getSetting('contact_phone', ''),
    'contact_email' => getSetting('contact_email', ''),
    'contact_address' => getSetting('contact_address', ''),
    'emergency_phone' => getSetting('emergency_phone', ''),
    'contact_location_lat' => getSetting('contact_location_lat', '33.4384'),
    'contact_location_lng' => getSetting('contact_location_lng', '43.6793'),
    'contact_location_name' => getSetting('contact_location_name', 'بلدية تكريت')
];

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'جديد' THEN 1 ELSE 0 END) as new_messages,
    SUM(CASE WHEN status = 'قيد المراجعة' THEN 1 ELSE 0 END) as in_review,
    SUM(CASE WHEN status = 'تم الرد' THEN 1 ELSE 0 END) as replied,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM contact_messages";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة صفحة اتصل بنا</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="../public/assets/css/tekrit-theme.css" rel="stylesheet">
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBOti4mM-6x9WDnZIjIeyEU21OpBXqWBgw&libraries=places"></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        #map { height: 400px; width: 100%; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <img src="../public/assets/images/Tekrit_LOGO.jpg" alt="شعار بلدية تكريت" class="w-12 h-12 ml-3">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">إدارة صفحة اتصل بنا</h1>
                            <p class="text-sm text-gray-600">إدارة الرسائل ومعلومات الاتصال</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <a href="../comprehensive_dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            العودة للوحة التحكم
                        </a>
                        <a href="../public/contact.php" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            عرض الصفحة
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 space-x-reverse">
                        <button onclick="showTab('messages')" id="messages-tab" class="tab-button active py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                            الرسائل الواردة
                        </button>
                        <button onclick="showTab('settings')" id="settings-tab" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            إعدادات الاتصال
                        </button>
                        <button onclick="showTab('location')" id="location-tab" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            موقع البلدية
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Messages Tab -->
            <div id="messages-content" class="tab-content">
                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6 text-center">
                        <div class="text-3xl font-bold text-blue-600"><?= $stats['total'] ?></div>
                        <div class="text-sm text-gray-600">إجمالي الرسائل</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6 text-center">
                        <div class="text-3xl font-bold text-red-600"><?= $stats['new_messages'] ?></div>
                        <div class="text-sm text-gray-600">رسائل جديدة</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6 text-center">
                        <div class="text-3xl font-bold text-yellow-600"><?= $stats['in_review'] ?></div>
                        <div class="text-sm text-gray-600">قيد المراجعة</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6 text-center">
                        <div class="text-3xl font-bold text-green-600"><?= $stats['replied'] ?></div>
                        <div class="text-sm text-gray-600">تم الرد</div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6 text-center">
                        <div class="text-3xl font-bold text-purple-600"><?= $stats['today'] ?></div>
                        <div class="text-sm text-gray-600">اليوم</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">البحث</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="ابحث بالاسم أو البريد أو الموضوع..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">حالة الرسالة</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">جميع الحالات</option>
                                <option value="جديد" <?= $status_filter == 'جديد' ? 'selected' : '' ?>>جديد</option>
                                <option value="قيد المراجعة" <?= $status_filter == 'قيد المراجعة' ? 'selected' : '' ?>>قيد المراجعة</option>
                                <option value="تم الرد" <?= $status_filter == 'تم الرد' ? 'selected' : '' ?>>تم الرد</option>
                                <option value="مغلق" <?= $status_filter == 'مغلق' ? 'selected' : '' ?>>مغلق</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                بحث
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Messages List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المرسل</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الموضوع</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($messages as $message): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($message['sender_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($message['sender_email']) ?></div>
                                                <?php if (!empty($message['sender_phone'])): ?>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($message['sender_phone']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?= htmlspecialchars($message['subject']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('Y/m/d H:i', strtotime($message['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                <?php 
                                                    switch($message['status']) {
                                                        case 'جديد': echo 'bg-red-100 text-red-800'; break;
                                                        case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                                                        case 'تم الرد': echo 'bg-green-100 text-green-800'; break;
                                                        case 'مغلق': echo 'bg-gray-100 text-gray-800'; break;
                                                        default: echo 'bg-gray-100 text-gray-800';
                                                    }
                                                ?>">
                                                <?= htmlspecialchars($message['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="viewMessage(<?= $message['id'] ?>)" class="text-blue-600 hover:text-blue-900 ml-3">عرض</button>
                                            <button onclick="updateStatus(<?= $message['id'] ?>, '<?= $message['status'] ?>')" class="text-green-600 hover:text-green-900 ml-3">تحديث</button>
                                            <button onclick="deleteMessage(<?= $message['id'] ?>)" class="text-red-600 hover:text-red-900">حذف</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-700">
                                    عرض <?= min($offset + 1, $total_messages) ?> إلى <?= min($offset + $limit, $total_messages) ?> من أصل <?= $total_messages ?> رسالة
                                </div>
                                <div class="flex space-x-2 space-x-reverse">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" 
                                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">السابق</a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" 
                                           class="px-3 py-2 border rounded-md <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" 
                                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">التالي</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">إعدادات معلومات الاتصال</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف الرئيسي</label>
                                <input type="text" name="contact_phone" value="<?= htmlspecialchars($current_settings['contact_phone']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="+964 XXX XXX XXXX">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                                <input type="email" name="contact_email" value="<?= htmlspecialchars($current_settings['contact_email']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="info@tekrit-municipality.gov.iq">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الطوارئ</label>
                                <input type="text" name="emergency_phone" value="<?= htmlspecialchars($current_settings['emergency_phone']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="+964 XXX XXX XXXX">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">العنوان الكامل</label>
                            <textarea name="contact_address" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="العنوان الكامل للبلدية"><?= htmlspecialchars($current_settings['contact_address']) ?></textarea>
                        </div>
                        
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط العرض (Latitude)</label>
                                <input type="text" name="contact_location_lat" value="<?= htmlspecialchars($current_settings['contact_location_lat']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="33.4384">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط الطول (Longitude)</label>
                                <input type="text" name="contact_location_lng" value="<?= htmlspecialchars($current_settings['contact_location_lng']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="43.6793">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اسم الموقع</label>
                                <input type="text" name="contact_location_name" value="<?= htmlspecialchars($current_settings['contact_location_name']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="بلدية تكريت">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Location Tab -->
            <div id="location-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">موقع البلدية على الخريطة</h2>
                    
                    <div class="mb-4">
                        <p class="text-gray-600">اضغط على الخريطة لتحديد موقع البلدية أو ابحث عن العنوان:</p>
                        <input type="text" id="address-search" placeholder="ابحث عن العنوان..." 
                               class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div id="map" class="rounded-lg border border-gray-300"></div>
                    
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">خط العرض</label>
                            <input type="text" id="lat-input" value="<?= htmlspecialchars($current_settings['contact_location_lat']) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">خط الطول</label>
                            <input type="text" id="lng-input" value="<?= htmlspecialchars($current_settings['contact_location_lng']) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md" readonly>
                        </div>
                        <div>
                            <button onclick="saveLocation()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                حفظ الموقع
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">تفاصيل الرسالة</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="messageContent"></div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-bold mb-4">تحديث حالة الرسالة</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="message_id" id="status_message_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">الحالة الجديدة</label>
                    <select name="status" id="status_select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="جديد">جديد</option>
                        <option value="قيد المراجعة">قيد المراجعة</option>
                        <option value="تم الرد">تم الرد</option>
                        <option value="مغلق">مغلق</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse">
                    <button type="button" onclick="closeStatusModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        إلغاء
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        تحديث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let map;
        let marker;
        
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById(tabName + '-tab');
            activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            
            // Initialize map when location tab is shown
            if (tabName === 'location') {
                setTimeout(() => initMap(), 100);
            }
        }

        // Initialize Google Maps
        function initMap() {
            const lat = parseFloat(document.getElementById('lat-input').value) || 33.4384;
            const lng = parseFloat(document.getElementById('lng-input').value) || 43.6793;
            
            map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: lat, lng: lng },
                zoom: 15
            });

            marker = new google.maps.Marker({
                position: { lat: lat, lng: lng },
                map: map,
                draggable: true,
                title: 'موقع البلدية'
            });

            // Update coordinates when marker is dragged
            marker.addListener('dragend', function() {
                const position = marker.getPosition();
                document.getElementById('lat-input').value = position.lat().toFixed(6);
                document.getElementById('lng-input').value = position.lng().toFixed(6);
            });

            // Add click listener to map
            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                document.getElementById('lat-input').value = event.latLng.lat().toFixed(6);
                document.getElementById('lng-input').value = event.latLng.lng().toFixed(6);
            });

            // Initialize Places Autocomplete
            const searchBox = new google.maps.places.SearchBox(document.getElementById('address-search'));
            
            searchBox.addListener('places_changed', function() {
                const places = searchBox.getPlaces();
                if (places.length === 0) return;

                const place = places[0];
                if (!place.geometry || !place.geometry.location) return;

                map.setCenter(place.geometry.location);
                marker.setPosition(place.geometry.location);
                document.getElementById('lat-input').value = place.geometry.location.lat().toFixed(6);
                document.getElementById('lng-input').value = place.geometry.location.lng().toFixed(6);
            });
        }

        // Save location
        function saveLocation() {
            const lat = document.getElementById('lat-input').value;
            const lng = document.getElementById('lng-input').value;
            
            if (!lat || !lng) {
                alert('يرجى تحديد موقع صحيح على الخريطة');
                return;
            }
            
            // Update the settings form and submit
            const form = document.querySelector('form[action=""]');
            const latField = form.querySelector('input[name="contact_location_lat"]');
            const lngField = form.querySelector('input[name="contact_location_lng"]');
            
            if (latField) latField.value = lat;
            if (lngField) lngField.value = lng;
            
            // Auto-submit the settings form
            const settingsForm = document.querySelector('input[name="action"][value="update_settings"]').form;
            settingsForm.submit();
        }

        // Message functions
        function viewMessage(id) {
            // Get message details via AJAX
            fetch('?action=get_message&id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('messageContent').innerHTML = data;
                    document.getElementById('messageModal').classList.remove('hidden');
                    document.getElementById('messageModal').classList.add('flex');
                });
        }

        function closeModal() {
            document.getElementById('messageModal').classList.add('hidden');
            document.getElementById('messageModal').classList.remove('flex');
        }

        function updateStatus(id, currentStatus) {
            document.getElementById('status_message_id').value = id;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').classList.add('flex');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('statusModal').classList.remove('flex');
        }

        function deleteMessage(id) {
            if (confirm('هل أنت متأكد من حذف هذه الرسالة؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 