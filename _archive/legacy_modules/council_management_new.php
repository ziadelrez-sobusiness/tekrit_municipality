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
                            throw new Exception('فشل في رفع الصورة: ' . error_get_last()['message']);
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
                            $success_message = "تم تحديث بيانات العضو والصورة بنجاح";
                        } else {
                            throw new Exception('فشل في رفع الصورة الجديدة');
                        }
                    } else {
                        throw new Exception('نوع الملف غير مدعوم. يُسمح فقط بـ JPG, JPEG, PNG, GIF');
                    }
                } else {
                    $success_message = "تم تحديث بيانات العضو بنجاح";
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
                $success_message = "تم حذف العضو والصورة بنجاح";
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
    <title>إدارة أعضاء المجلس البلدي (محدث)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">👥</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">إدارة أعضاء المجلس البلدي (محدث)</h1>
                        <p class="text-sm text-gray-500">نظام محدث لإدارة الصور والبيانات</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../public/council.php" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        🌐 معاينة الصفحة العامة
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
            <h2 class="text-xl font-bold mb-4">إضافة عضو جديد</h2>
            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="add_member">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
                    <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المنصب *</label>
                    <select name="position" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
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
                    <input type="text" name="specialization" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                    <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">الصورة الشخصية</label>
                    <input type="file" name="profile_picture" accept="image/*" id="imageInput" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                           onchange="previewImage(this, 'imagePreview')">
                    <div id="imagePreview" class="mt-2 hidden">
                        <p class="text-sm text-green-600 mb-2 font-medium">✨ معاينة الصورة المختارة:</p>
                        <img id="previewImg" class="w-32 h-32 object-cover rounded-lg border-2 border-green-300">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">يُسمح بـ JPG, PNG, GIF - الحد الأقصى 5MB</p>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">السيرة الذاتية</label>
                    <textarea name="biography" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                </div>
                
                <div class="md:col-span-2 flex gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" checked class="mr-2">
                        عضو نشط
                    </label>
                    <input type="number" name="display_order" placeholder="ترتيب العرض" class="px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="md:col-span-2">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        إضافة العضو
                    </button>
                </div>
            </form>
        </div>

        <!-- Members List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium">أعضاء المجلس البلدي (<?= count($council_members) ?>)</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">العضو</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">المنصب</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">التخصص</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($council_members as $member): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-12 w-12 flex-shrink-0">
                                            <?php if ($member['profile_picture']): ?>
                                                <img class="h-12 w-12 rounded-full object-cover" 
                                                     src="../<?= htmlspecialchars($member['profile_picture']) ?>" 
                                                     alt="<?= htmlspecialchars($member['full_name']) ?>">
                                            <?php else: ?>
                                                <div class="h-12 w-12 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <span class="text-lg font-medium text-gray-700">
                                                        <?= substr($member['full_name'], 0, 1) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($member['full_name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($member['phone'] ?? '') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($member['position']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($member['specialization']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $member['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $member['is_active'] ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editMember(<?= htmlspecialchars(json_encode($member)) ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900 ml-3">تعديل</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد؟')">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
                <h3 class="text-lg font-bold text-gray-900 mb-4">تعديل عضو المجلس</h3>
                <form method="POST" enctype="multipart/form-data" id="editForm" class="space-y-4">
                    <input type="hidden" name="action" value="update_member">
                    <input type="hidden" name="member_id" id="edit_member_id">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
                            <input type="text" name="full_name" id="edit_full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المنصب</label>
                            <select name="position" id="edit_position" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="رئيس البلدية">رئيس البلدية</option>
                                <option value="نائب رئيس البلدية">نائب رئيس البلدية</option>
                                <option value="عضو مجلس">عضو مجلس</option>
                                <option value="سكرتير المجلس">سكرتير المجلس</option>
                                <option value="أمين المال">أمين المال</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
                        <input type="text" name="specialization" id="edit_specialization" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الصورة الشخصية الجديدة</label>
                        <input type="file" name="profile_picture" accept="image/*" id="editImageInput"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md"
                               onchange="previewImage(this, 'editImagePreview')">
                        <div id="editImagePreview" class="mt-2">
                            <img id="editPreviewImg" class="w-32 h-32 object-cover rounded-lg border">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 space-x-reverse pt-4">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            إلغاء
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            تحديث البيانات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const img = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.classList.remove('hidden');
                    console.log('✅ تم تحميل معاينة الصورة بنجاح');
                };
                reader.onerror = function() {
                    console.error('❌ خطأ في تحميل الصورة');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
                console.log('ℹ️ لم يتم اختيار صورة');
            }
        }
        
        function editMember(member) {
            document.getElementById('edit_member_id').value = member.id;
            document.getElementById('edit_full_name').value = member.full_name;
            document.getElementById('edit_position').value = member.position;
            document.getElementById('edit_specialization').value = member.specialization;
            
            // Show current image if exists
            const preview = document.getElementById('editImagePreview');
            const img = document.getElementById('editPreviewImg');
            if (member.profile_picture && member.profile_picture.trim() !== '') {
                img.src = '../' + member.profile_picture;
                preview.classList.remove('hidden');
                console.log('✅ تم تحميل الصورة الحالية للعضو:', member.full_name);
            } else {
                preview.classList.add('hidden');
                console.log('ℹ️ لا توجد صورة للعضو:', member.full_name);
            }
            
            // Reset file input and new image preview
            document.getElementById('editImageInput').value = '';
            
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editImageInput').value = '';
            document.getElementById('editImagePreview').classList.add('hidden');
        }
    </script>
</body>
</html> 
