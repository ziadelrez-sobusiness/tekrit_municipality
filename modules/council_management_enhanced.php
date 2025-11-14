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
                    $upload_dir = '../uploads/council_members/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array(strtolower($file_extension), $allowed_extensions)) {
                        $new_filename = 'member_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                            $profile_picture = 'uploads/council_members/' . $new_filename;
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
                    $_POST['biography'],
                    $_POST['education'],
                    $_POST['experience'],
                    $profile_picture,
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['appointment_date'] ?: null,
                    $_POST['term_start_date'] ?: null,
                    $_POST['term_end_date'] ?: null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['display_order'] ?: 0
                ]);
                
                $success_message = "تم إضافة العضو بنجاح";
                break;

            case 'update_member':
                // Get current member data for image handling
                $stmt = $pdo->prepare("SELECT profile_picture FROM council_members WHERE id = ?");
                $stmt->execute([$_POST['member_id']]);
                $current_member = $stmt->fetch();
                
                $profile_picture = $current_member['profile_picture'];
                
                // Handle new image upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/council_members/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array(strtolower($file_extension), $allowed_extensions)) {
                        $new_filename = 'member_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                            // Delete old image if exists
                            if ($profile_picture && file_exists('../' . $profile_picture)) {
                                unlink('../' . $profile_picture);
                            }
                            $profile_picture = 'uploads/council_members/' . $new_filename;
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
                    $_POST['biography'],
                    $_POST['education'],
                    $_POST['experience'],
                    $profile_picture,
                    $_POST['phone'],
                    $_POST['email'],
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

// Get member for editing if specified
$edit_member = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM council_members WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_member = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة أعضاء المجلس البلدي - <?= htmlspecialchars($site_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .image-preview { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- الباقي سيكون مطابق للملف الأصلي مع تحسينات الصور -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">👥</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">إدارة أعضاء المجلس البلدي المحسّن</h1>
                        <p class="text-sm text-gray-500">إضافة وتعديل ومتابعة أعضاء المجلس مع معاينة الصور</p>
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

    <div class="max-w-7xl mx-auto py-6 px-4" x-data="councilManager()">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">👥 إدارة أعضاء المجلس البلدي المحسّن</h1>
            <p class="text-slate-600">إدارة شاملة لأعضاء المجلس البلدي مع ميزات معاينة الصور المتطورة</p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 fade-in">
                <p class="font-bold">✅ نجح! <?= $success_message ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 fade-in">
                <p class="font-bold">❌ خطأ! <?= $error_message ?></p>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">👥</div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">إجمالي الأعضاء</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count($council_members) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">✅</div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">الأعضاء النشطون</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count(array_filter($council_members, function($m) { return $m['is_active']; })) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">🖼️</div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">مع صور شخصية</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count(array_filter($council_members, function($m) { return !empty($m['profile_picture']); })) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center mb-6">
            <button @click="openAddForm()" class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                إضافة عضو جديد
            </button>
        </div>

        <!-- Council Members Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">قائمة أعضاء المجلس البلدي</h3>
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
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <?php if ($member['profile_picture']): ?>
                                                <img class="h-12 w-12 rounded-full object-cover border-2 border-gray-200" 
                                                     src="../<?= htmlspecialchars($member['profile_picture']) ?>" 
                                                     alt="<?= htmlspecialchars($member['full_name']) ?>">
                                            <?php else: ?>
                                                <div class="h-12 w-12 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <span class="text-lg font-medium text-gray-700"><?= substr($member['full_name'], 0, 1) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($member['full_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($member['email'] ?? '') ?></div>
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
                                    <button @click="openEditForm(<?= htmlspecialchars(json_encode($member)) ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900 ml-3">تعديل</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا العضو؟')">
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

        <!-- Add/Edit Member Modal with Enhanced Image Preview -->
        <div x-show="showForm" x-transition class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-5xl shadow-lg rounded-md bg-white">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" x-bind:value="editMode ? 'update_member' : 'add_member'">
                    <input type="hidden" name="member_id" x-bind:value="currentMember ? currentMember.id : ''">
                    
                    <!-- صفوف الحقول مع معاينة الصورة -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- معاينة الصورة -->
                        <div class="lg:col-span-1">
                            <h4 class="font-semibold text-gray-900 mb-4">صورة العضو</h4>
                            
                            <!-- منطقة عرض الصورة -->
                            <div class="relative">
                                <div class="w-full h-64 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex flex-col items-center justify-center">
                                    <!-- الصورة الحالية -->
                                    <img x-show="editMode && currentMember && currentMember.profile_picture && !newImageSelected" 
                                         x-bind:src="currentMember ? '../' + currentMember.profile_picture : ''" 
                                         class="w-full h-full object-cover rounded-lg" id="current_image">
                                    
                                    <!-- معاينة الصورة الجديدة -->
                                    <img x-show="newImageSelected" 
                                         id="preview_img" 
                                         class="w-full h-full object-cover rounded-lg">
                                    
                                    <!-- رسالة افتراضية -->
                                    <div x-show="!newImageSelected && !(editMode && currentMember && currentMember.profile_picture)" class="text-center p-6">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600">اختر صورة للعضو</p>
                                        <p class="text-xs text-gray-500">JPG, PNG, GIF</p>
                                    </div>
                                </div>
                                
                                <!-- حقل اختيار الملف -->
                                <input type="file" 
                                       name="profile_picture" 
                                       accept="image/*" 
                                       id="profile_picture_input"
                                       @change="handleImagePreview($event)"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            </div>
                            
                            <!-- أزرار التحكم بالصورة -->
                            <div class="mt-4 flex gap-2">
                                <button type="button" @click="clearImage()" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                    مسح الصورة
                                </button>
                                <button type="button" @click="document.getElementById('profile_picture_input').click()" class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    اختيار صورة
                                </button>
                            </div>
                        </div>

                        <!-- بيانات العضو -->
                        <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
                                <input type="text" name="full_name" required 
                                       x-model="currentMember.full_name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المنصب</label>
                                <select name="position" required 
                                        x-model="currentMember.position"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">اختر المنصب</option>
                                    <option value="رئيس البلدية">رئيس البلدية</option>
                                    <option value="نائب رئيس البلدية">نائب رئيس البلدية</option>
                                    <option value="عضو مجلس">عضو مجلس</option>
                                    <option value="سكرتير المجلس">سكرتير المجلس</option>
                                    <option value="أمين المال">أمين المال</option>
                                </select>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
                                <input type="text" name="specialization" required
                                       x-model="currentMember.specialization"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                <input type="tel" name="phone"
                                       x-model="currentMember.phone"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                                <input type="email" name="email"
                                       x-model="currentMember.email"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">السيرة الذاتية</label>
                                <textarea name="biography" rows="3"
                                          x-model="currentMember.biography"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                        <button type="button" @click="closeForm()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            إلغاء
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            <span x-text="editMode ? 'تحديث البيانات' : 'إضافة العضو'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function councilManager() {
            return {
                showForm: false,
                editMode: <?= $edit_member ? 'true' : 'false' ?>,
                currentMember: <?= $edit_member ? json_encode($edit_member) : '{}' ?>,
                newImageSelected: false,
                
                openAddForm() {
                    this.showForm = true;
                    this.editMode = false;
                    this.currentMember = {};
                    this.newImageSelected = false;
                    this.clearImage();
                },
                
                openEditForm(member) {
                    this.showForm = true;
                    this.editMode = true;
                    this.currentMember = member;
                    this.newImageSelected = false;
                },
                
                closeForm() {
                    this.showForm = false;
                    this.editMode = false;
                    this.currentMember = {};
                    this.newImageSelected = false;
                    this.clearImage();
                },
                
                handleImagePreview(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            document.getElementById('preview_img').src = e.target.result;
                            this.newImageSelected = true;
                        };
                        reader.readAsDataURL(file);
                    }
                },
                
                clearImage() {
                    document.getElementById('profile_picture_input').value = '';
                    this.newImageSelected = false;
                }
            }
        }
    </script>
</body>
</html> 
