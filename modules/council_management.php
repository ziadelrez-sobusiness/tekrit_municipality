<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$database = new Database();
$pdo = $database->getConnection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../login.php');
    exit();
}

// Get site settings
function getSetting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
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
    <link href="../public/assets/css/tekrit-theme.css" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    
    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview_img');
            const previewContainer = document.getElementById('new_image_preview');
            const currentImage = document.getElementById('current_image');
            
            if (input.files && input.files[0]) {
                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(input.files[0].type)) {
                    alert('يُسمح فقط بملفات الصور: JPG, JPEG, PNG, GIF');
                    input.value = '';
                    return;
                }
                
                // Check file size (5MB limit)
                if (input.files[0].size > 5 * 1024 * 1024) {
                    alert('حجم الصورة يجب أن يكون أقل من 5MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                    
                    // Dim current image when new one is selected
                    if (currentImage) {
                        currentImage.style.opacity = '0.5';
                        currentImage.style.filter = 'grayscale(50%)';
                    }
                    
                    console.log('✅ تم تحميل معاينة الصورة بنجاح');
                };
                
                reader.onerror = function() {
                    console.error('❌ خطأ في تحميل الصورة');
                    alert('حدث خطأ في تحميل الصورة. يرجى المحاولة مرة أخرى.');
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.style.display = 'none';
                if (currentImage) {
                    currentImage.style.opacity = '1';
                    currentImage.style.filter = 'none';
                }
            }
        }
        
        // Initialize Alpine.js data
        document.addEventListener('alpine:init', () => {
            Alpine.data('councilManager', () => ({
                showForm: false,
                editMode: false,
                currentMember: null,
                
                openAddForm() {
                    this.editMode = false;
                    this.currentMember = null;
                    this.showForm = true;
                    this.resetImagePreview();
                },
                
                openEditForm(member) {
                    this.editMode = true;
                    this.currentMember = member;
                    this.showForm = true;
                    this.resetImagePreview();
                    
                    // Set form values manually for better control
                    setTimeout(() => {
                        // Clear file input
                        const fileInput = document.getElementById('profile_picture_input');
                        if (fileInput) fileInput.value = '';
                        
                        // Show current image if exists
                        const currentImage = document.getElementById('current_image');
                        if (currentImage && member.profile_picture) {
                            currentImage.style.opacity = '1';
                            currentImage.style.filter = 'none';
                            console.log('✅ تم تحميل الصورة الحالية للعضو:', member.full_name);
                        }
                    }, 100);
                },
                
                closeForm() {
                    this.showForm = false;
                    this.currentMember = null;
                    this.editMode = false;
                    this.resetImagePreview();
                },
                
                resetImagePreview() {
                    setTimeout(() => {
                        const previewContainer = document.getElementById('new_image_preview');
                        const fileInput = document.getElementById('profile_picture_input');
                        const currentImage = document.getElementById('current_image');
                        
                        if (previewContainer) previewContainer.style.display = 'none';
                        if (fileInput) fileInput.value = '';
                        if (currentImage) {
                            currentImage.style.opacity = '1';
                            currentImage.style.filter = 'none';
                        }
                    }, 50);
                }
            }));
        });
    </script>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="tekrit-header shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <img src="../public/assets/images/Tekrit_LOGO.jpg" alt="شعار بلدية تكريت" class="tekrit-logo ml-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">إدارة أعضاء المجلس البلدي</h1>
                        <p class="text-sm text-gray-600">إضافة وتعديل ومتابعة أعضاء المجلس</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../public/council.php" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        🌐 معاينة الصفحة العامة
                    </a>
                    <a href="../comprehensive_dashboard.php" class="btn-primary-orange">
                        🏠 العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4" x-data="councilManager()">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">👥 إدارة أعضاء المجلس البلدي</h1>
            <p class="text-slate-600">إدارة شاملة لأعضاء المجلس البلدي ومناصبهم وتخصصاتهم</p>
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
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">إجمالي الأعضاء</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count($council_members) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">الأعضاء النشطون</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count(array_filter($council_members, function($m) { return $m['is_active']; })) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">المناصب المختلفة</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count(array_unique(array_column($council_members, 'position'))) ?></p>
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
            
            <a href="../public/council.php" target="_blank" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                معاينة الصفحة العامة
            </a>
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
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاريخ التعيين</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($council_members as $member): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <?php if ($member['profile_picture']): ?>
                                                <img class="h-10 w-10 rounded-full object-cover" src="../<?= htmlspecialchars($member['profile_picture']) ?>" alt="">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <span class="text-sm font-medium text-gray-700"><?= substr($member['full_name'], 0, 1) ?></span>
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
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                            switch($member['position']) {
                                                case 'رئيس البلدية': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'نائب الرئيس': echo 'bg-blue-100 text-blue-800'; break;
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
                                        <?= $member['is_active'] ? 'نشط' : 'غير نشط' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $member['appointment_date'] ? date('Y/m/d', strtotime($member['appointment_date'])) : '-' ?>
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
                        
                        <?php if (empty($council_members)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="text-6xl mb-4">👥</div>
                                    <p class="text-lg">لا توجد أعضاء مجلس مضافون بعد</p>
                                    <p class="text-sm">اضغط على "إضافة عضو جديد" لبدء إضافة الأعضاء</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add/Edit Member Modal -->
        <div x-show="showForm" x-transition:enter="transition ease-out duration-300" 
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" 
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
            
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900" x-text="editMode ? 'تعديل عضو المجلس' : 'إضافة عضو جديد'"></h3>
                    <button @click="closeForm()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form method="POST" class="space-y-6" enctype="multipart/form-data">
                    <input type="hidden" name="action" x-bind:value="editMode ? 'update_member' : 'add_member'">
                    <input type="hidden" name="member_id" x-bind:value="currentMember ? currentMember.id : ''">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-900">المعلومات الأساسية</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
                                <input type="text" name="full_name" required 
                                       x-bind:value="currentMember ? currentMember.full_name : ''"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المنصب</label>
                                <select name="position" required 
                                        x-bind:value="currentMember ? currentMember.position : ''"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">اختر المنصب</option>
                                    <option value="رئيس البلدية">رئيس البلدية</option>
                                    <option value="نائب الرئيس">نائب الرئيس</option>
                                    <option value="عضو مجلس">عضو مجلس</option>
                                    <option value="أمين السر">أمين السر</option>
                                    <option value="أمين الصندوق">أمين الصندوق</option>
                                    <option value="مقرر">مقرر</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
                                <input type="text" name="specialization" required
                                       x-bind:value="currentMember ? currentMember.specialization : ''"
                                       placeholder="مثل: هندسة مدنية، إدارة عامة، قانون"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                <input type="tel" name="phone"
                                       x-bind:value="currentMember ? currentMember.phone : ''"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                                <input type="email" name="email"
                                       x-bind:value="currentMember ? currentMember.email : ''"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <!-- Professional Information -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-900">المعلومات المهنية</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">السيرة الذاتية</label>
                                <textarea name="biography" rows="3"
                                          x-text="currentMember ? currentMember.biography : ''"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المؤهلات العلمية</label>
                                <textarea name="education" rows="2"
                                          x-text="currentMember ? currentMember.education : ''"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الخبرة العملية</label>
                                <textarea name="experience" rows="2"
                                          x-text="currentMember ? currentMember.experience : ''"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الصورة الشخصية</label>
                                <input type="file" name="profile_picture" accept="image/*" id="profile_picture_input"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                       onchange="previewImage(this)">
                                <p class="text-xs text-gray-500 mt-1">يُسمح بملفات JPG, JPEG, PNG, GIF فقط</p>
                                
                                <!-- Image Preview -->
                                <div class="mt-3">
                                    <!-- Current Image Preview (for editing) -->
                                    <div x-show="editMode && currentMember && currentMember.profile_picture" class="mb-3">
                                        <p class="text-sm text-gray-600 mb-2">الصورة الحالية:</p>
                                        <img x-bind:src="currentMember ? '../' + currentMember.profile_picture : ''" 
                                             class="w-24 h-24 object-cover rounded-lg border border-gray-200" id="current_image">
                                    </div>
                                    
                                    <!-- New Image Preview -->
                                    <div id="new_image_preview" style="display: none;">
                                        <p class="text-sm text-gray-600 mb-2">الصورة الجديدة:</p>
                                        <img id="preview_img" class="w-24 h-24 object-cover rounded-lg border border-gray-200">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dates and Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التعيين</label>
                            <input type="date" name="appointment_date"
                                   x-bind:value="currentMember ? currentMember.appointment_date : ''"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">بداية الفترة</label>
                            <input type="date" name="term_start_date"
                                   x-bind:value="currentMember ? currentMember.term_start_date : ''"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نهاية الفترة</label>
                            <input type="date" name="term_end_date"
                                   x-bind:value="currentMember ? currentMember.term_end_date : ''"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ترتيب العرض</label>
                            <input type="number" name="display_order" min="0"
                                   x-bind:value="currentMember ? currentMember.display_order : ''"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" 
                               x-bind:checked="currentMember ? currentMember.is_active : true"
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label class="mr-2 block text-sm text-gray-900">عضو نشط</label>
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

</body>
</html> 
