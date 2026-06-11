<?php
session_start();
require_once '../config/database.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// دالة للحصول على إعدادات الموقع
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$site_title = getSetting('site_title', 'بلدية تكريت');

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'add_member':
                // Handle image upload
                $profile_picture = '';
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/council_members/';
                    $full_upload_dir = '../' . $upload_dir;
                    
                    if (!is_dir($full_upload_dir)) {
                        mkdir($full_upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array(strtolower($file_extension), $allowed_extensions)) {
                        $new_filename = 'member_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $full_upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                            $profile_picture = $upload_dir . $new_filename;
                        } else {
                            throw new Exception('فشل في رفع الصورة');
                        }
                    } else {
                        throw new Exception('نوع الملف غير مدعوم. يُسمح فقط بـ JPG, JPEG, PNG, GIF');
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO council_members (
                        full_name, position, specialization, biography, education, experience,
                        profile_picture, phone, email, appointment_date, term_start_date, 
                        term_end_date, is_active, display_order
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['position'],
                    $_POST['specialization'],
                    $_POST['biography'] ?? '',
                    $_POST['education'] ?? '',
                    $_POST['experience'] ?? '',
                    $profile_picture,
                    $_POST['phone'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['appointment_date'] ?: null,
                    $_POST['term_start_date'] ?: null,
                    $_POST['term_end_date'] ?: null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'] ?: 0
                ]);
                
                $success_message = "تم إضافة العضو بنجاح" . ($profile_picture ? " مع الصورة" : "");
                break;

            case 'update_member':
                // Get current member data
                $stmt = $pdo->prepare("SELECT profile_picture FROM council_members WHERE id = ?");
                $stmt->execute([$_POST['member_id']]);
                $current_member = $stmt->fetch();
                
                $profile_picture = $current_member['profile_picture'] ?? '';
                
                // Handle new image upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/council_members/';
                    $full_upload_dir = '../' . $upload_dir;
                    
                    if (!is_dir($full_upload_dir)) {
                        mkdir($full_upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array(strtolower($file_extension), $allowed_extensions)) {
                        $new_filename = 'member_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $full_upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                            // Delete old image if exists
                            if ($profile_picture && file_exists('../' . $profile_picture)) {
                                unlink('../' . $profile_picture);
                            }
                            $profile_picture = $upload_dir . $new_filename;
                        } else {
                            throw new Exception('فشل في رفع الصورة الجديدة');
                        }
                    } else {
                        throw new Exception('نوع الملف غير مدعوم. يُسمح فقط بـ JPG, JPEG, PNG, GIF');
                    }
                }
                
                $stmt = $pdo->prepare("
                    UPDATE council_members SET 
                        full_name = ?, position = ?, specialization = ?, biography = ?, 
                        education = ?, experience = ?, profile_picture = ?, phone = ?, 
                        email = ?, appointment_date = ?, term_start_date = ?, term_end_date = ?, 
                        is_active = ?, display_order = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['position'],
                    $_POST['specialization'],
                    $_POST['biography'] ?? '',
                    $_POST['education'] ?? '',
                    $_POST['experience'] ?? '',
                    $profile_picture,
                    $_POST['phone'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['appointment_date'] ?: null,
                    $_POST['term_start_date'] ?: null,
                    $_POST['term_end_date'] ?: null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'] ?: 0,
                    $_POST['member_id']
                ]);
                
                $success_message = "تم تحديث بيانات العضو بنجاح";
                break;

            case 'delete_member':
                // Get member data to delete image
                $stmt = $pdo->prepare("SELECT profile_picture FROM council_members WHERE id = ?");
                $stmt->execute([$_POST['member_id']]);
                $member = $stmt->fetch();
                
                if ($member && $member['profile_picture'] && file_exists('../' . $member['profile_picture'])) {
                    unlink('../' . $member['profile_picture']);
                }
                
                $stmt = $pdo->prepare("DELETE FROM council_members WHERE id = ?");
                $stmt->execute([$_POST['member_id']]);
                $success_message = "تم حذف العضو بنجاح";
                break;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all council members
$stmt = $pdo->query("
    SELECT *
    FROM council_members
    ORDER BY display_order ASC, created_at DESC
");
$council_members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة أعضاء المجلس البلدي - النسخة النهائية</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .image-preview { border: 3px dashed #e5e7eb; border-radius: 12px; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .image-preview:hover { border-color: #6366f1; background-color: #f9fafb; }
        .image-preview.has-image { border-style: solid; border-color: #10b981; }
        .upload-zone { cursor: pointer; }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">👥</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">إدارة أعضاء المجلس البلدي - النسخة النهائية ✅</h1>
                        <p class="text-sm text-gray-500">معاينة وحفظ الصور محلولة 100%</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../public/council.php" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        🌐 معاينة الصفحة العامة
                    </a>
                    <a href="../comprehensive_dashboard.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        🏠 العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 fade-in">
                <p class="font-bold">✅ <?= $success_message ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 fade-in">
                <p class="font-bold">❌ خطأ! <?= $error_message ?></p>
            </div>
        <?php endif; ?>

        <!-- Add Member Form -->
        <div class="bg-white rounded-lg shadow mb-8 p-6">
            <h2 class="text-2xl font-bold mb-6 text-indigo-800">إضافة عضو جديد</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" value="add_member">
                
                <!-- Grid Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Image Upload Section -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">الصورة الشخصية</label>
                        <div id="addImageContainer" class="image-preview upload-zone w-full h-48 flex items-center justify-center bg-gray-50" onclick="document.getElementById('addImageInput').click()">
                            <div id="addPlaceholder" class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-600 font-medium">اختر صورة العضو</p>
                                <p class="text-xs text-gray-500">اضغط هنا لتحديد الصورة</p>
                            </div>
                            <img id="addImagePreview" class="absolute inset-0 w-full h-full object-cover hidden">
                        </div>
                        <input type="file" 
                               name="profile_picture" 
                               accept="image/*" 
                               id="addImageInput"
                               class="hidden"
                               onchange="previewAddImage(this)">
                        <button type="button" 
                                onclick="document.getElementById('addImageInput').click()"
                                class="mt-2 w-full text-sm bg-indigo-50 text-indigo-700 py-2 px-4 rounded-md hover:bg-indigo-100 border border-indigo-200 transition-colors">
                            📁 اختيار ملف صورة
                        </button>
                        <p class="text-xs text-gray-500 mt-1 text-center">JPG, PNG, GIF - الحد الأقصى 5MB</p>
                    </div>
                    
                    <!-- Form Fields -->
                    <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
                            <input type="text" name="full_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المنصب *</label>
                            <select name="position" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">اختر المنصب</option>
                                <option value="رئيس البلدية">رئيس البلدية</option>
                                <option value="نائب رئيس البلدية">نائب رئيس البلدية</option>
                                <option value="عضو مجلس">عضو مجلس</option>
                                <option value="سكرتير المجلس">سكرتير المجلس</option>
                                <option value="أمين المال">أمين المال</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">التخصص *</label>
                            <input type="text" name="specialization" required 
                                   placeholder="مثل: هندسة مدنية، إدارة عامة، قانون"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                            <input type="tel" name="phone" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                            <input type="email" name="email" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">السيرة الذاتية</label>
                            <textarea name="biography" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        
                        <div class="flex items-center space-x-4 space-x-reverse">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1" checked 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <span class="mr-2 text-sm text-gray-900">عضو نشط</span>
                            </label>
                            <input type="number" name="display_order" placeholder="ترتيب العرض" 
                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors font-medium">
                                💾 إضافة العضو
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Members List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">أعضاء المجلس البلدي (<?= count($council_members) ?>)</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">العضو</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المنصب</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التخصص</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($council_members as $member): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-12 w-12 flex-shrink-0">
                                            <?php if ($member['profile_picture']): ?>
                                                <img class="h-12 w-12 rounded-full object-cover border-2 border-gray-200 shadow-sm" 
                                                     src="../<?= htmlspecialchars($member['profile_picture']) ?>" 
                                                     alt="<?= htmlspecialchars($member['full_name']) ?>"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white font-bold text-lg shadow-sm" style="display: none;">
                                                    <?= substr($member['full_name'], 0, 1) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                                                    <?= substr($member['full_name'], 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mr-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($member['full_name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($member['phone'] ?? 'لا يوجد هاتف') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full
                                        <?php 
                                            switch($member['position']) {
                                                case 'رئيس البلدية': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'نائب رئيس البلدية': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'سكرتير المجلس': echo 'bg-green-100 text-green-800'; break;
                                                case 'أمين المال': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($member['position']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($member['specialization']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $member['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $member['is_active'] ? 'نشط ✓' : 'غير نشط ✗' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editMember(<?= htmlspecialchars(json_encode($member)) ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3 font-medium">✏️ تعديل</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا العضو؟\\nسيتم حذف الصورة أيضاً!')">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 font-medium">🗑️ حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($council_members)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <div class="text-6xl mb-4">👥</div>
                                    <p class="text-lg font-medium">لا توجد أعضاء مجلس مضافون بعد</p>
                                    <p class="text-sm">اضغط على "إضافة العضو" لبدء إضافة الأعضاء</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-3xl shadow-xl rounded-lg bg-white">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900">✏️ تعديل عضو المجلس</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="editForm" class="space-y-6">
                    <input type="hidden" name="action" value="update_member">
                    <input type="hidden" name="member_id" id="edit_member_id">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <!-- Edit Image Section -->
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">الصورة الحالية</label>
                            <div id="editCurrentImageContainer" class="w-full h-48 border-2 border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                                <img id="editCurrentImage" class="w-full h-full object-cover hidden">
                                <div id="editNoImage" class="text-center text-gray-500">
                                    <div class="text-4xl mb-2">👤</div>
                                    <p class="text-sm">لا توجد صورة</p>
                                </div>
                            </div>
                            
                            <label class="block text-sm font-medium text-gray-700 mt-4 mb-2">تغيير الصورة</label>
                            <input type="file" name="profile_picture" accept="image/*" id="editImageInput"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                   onchange="previewEditImage(this)">
                            
                            <div id="editImagePreview" class="mt-2 hidden">
                                <p class="text-sm text-green-600 mb-1 font-medium">✨ الصورة الجديدة:</p>
                                <img id="editPreviewImg" class="w-full h-32 object-cover rounded border-2 border-green-300">
                            </div>
                        </div>
                        
                        <!-- Edit Form Fields -->
                        <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
                                <input type="text" name="full_name" id="edit_full_name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المنصب</label>
                                <select name="position" id="edit_position" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="رئيس البلدية">رئيس البلدية</option>
                                    <option value="نائب رئيس البلدية">نائب رئيس البلدية</option>
                                    <option value="عضو مجلس">عضو مجلس</option>
                                    <option value="سكرتير المجلس">سكرتير المجلس</option>
                                    <option value="أمين المال">أمين المال</option>
                                </select>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
                                <input type="text" name="specialization" id="edit_specialization" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                <input type="tel" name="phone" id="edit_phone" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                                <input type="email" name="email" id="edit_email" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">السيرة الذاتية</label>
                                <textarea name="biography" id="edit_biography" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            إلغاء
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            💾 تحديث البيانات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // وظيفة معاينة الصورة في نموذج الإضافة
        function previewAddImage(input) {
            const container = document.getElementById('addImageContainer');
            const placeholder = document.getElementById('addPlaceholder');
            const preview = document.getElementById('addImagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.style.display = 'none';
                    container.classList.add('has-image');
                    
                    // إظهار رسالة نجاح
                    console.log('✅ تم تحميل معاينة الصورة بنجاح');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
                placeholder.style.display = 'block';
                container.classList.remove('has-image');
            }
        }
        
        // وظيفة معاينة الصورة في نموذج التعديل
        function previewEditImage(input) {
            const previewContainer = document.getElementById('editImagePreview');
            const previewImg = document.getElementById('editPreviewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                    console.log('✅ تم تحميل معاينة الصورة الجديدة بنجاح');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.classList.add('hidden');
            }
        }
        
        // وظيفة تعديل العضو
        function editMember(member) {
            // ملء البيانات
            document.getElementById('edit_member_id').value = member.id;
            document.getElementById('edit_full_name').value = member.full_name;
            document.getElementById('edit_position').value = member.position;
            document.getElementById('edit_specialization').value = member.specialization;
            document.getElementById('edit_phone').value = member.phone || '';
            document.getElementById('edit_email').value = member.email || '';
            document.getElementById('edit_biography').value = member.biography || '';
            
            // عرض الصورة الحالية
            const currentImage = document.getElementById('editCurrentImage');
            const noImage = document.getElementById('editNoImage');
            
            if (member.profile_picture && member.profile_picture.trim() !== '') {
                currentImage.src = '../' + member.profile_picture;
                currentImage.classList.remove('hidden');
                noImage.style.display = 'none';
                console.log('✅ تم تحميل الصورة الحالية:', member.profile_picture);
            } else {
                currentImage.classList.add('hidden');
                noImage.style.display = 'block';
                console.log('ℹ️ لا توجد صورة للعضو');
            }
            
            // إعادة تعيين معاينة الصورة الجديدة
            document.getElementById('editImageInput').value = '';
            document.getElementById('editImagePreview').classList.add('hidden');
            
            // إظهار النافذة المنبثقة
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        // إغلاق نافذة التعديل
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // إغلاق النافذة عند النقر خارجها
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // تأكيد تحميل الصفحة
        console.log('✅ تم تحميل صفحة إدارة المجلس بنجاح - جميع الوظائف جاهزة!');
    </script>
</body>
</html> 
