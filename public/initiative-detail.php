<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/recaptcha_helper.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$initiative_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$initiative_id) {
    header("Location: initiatives.php");
    exit();
}

// جلب تفاصيل المبادرة
$stmt = $db->prepare("
    SELECT i.*, 
           (SELECT COUNT(*) FROM initiative_volunteers WHERE initiative_id = i.id AND registration_status = 'مقبول') as registered_volunteers
    FROM youth_environmental_initiatives i
    WHERE i.id = ?
");
$stmt->execute([$initiative_id]);
$initiative = $stmt->fetch();

if (!$initiative) {
    header("Location: initiatives.php");
    exit();
}

// جلب صور المبادرة
$stmt = $db->prepare("
    SELECT * FROM initiative_images 
    WHERE initiative_id = ? AND is_active = 1 
    ORDER BY display_order, created_at
");
$stmt->execute([$initiative_id]);
$images = $stmt->fetchAll();

// جلب الأنشطة المرتبطة
$stmt = $db->prepare("
    SELECT * FROM initiative_activities 
    WHERE initiative_id = ? 
    ORDER BY activity_date DESC
");
$stmt->execute([$initiative_id]);
$activities = $stmt->fetchAll();

// معالجة طلب التسجيل
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $motivation = trim($_POST['motivation'] ?? '');
    
    // التحقق من reCAPTCHA أولاً
    $recaptcha_result = verify_recaptcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null);
    
    if (!$recaptcha_result['success']) {
        $error = $recaptcha_result['error'];
    } elseif (empty($name) || empty($email) || empty($phone)) {
        $error = "يرجى ملء جميع الحقول المطلوبة";
    } else {
        // التحقق من عدم التسجيل المسبق
        $check_stmt = $db->prepare("SELECT id FROM initiative_volunteers WHERE initiative_id = ? AND email = ?");
        $check_stmt->execute([$initiative_id, $email]);
        
        if ($check_stmt->fetch()) {
            $error = "لقد قمت بالتسجيل في هذه المبادرة مسبقاً";
        } else {
            // إدراج طلب التسجيل
            $insert_stmt = $db->prepare("
                INSERT INTO initiative_volunteers 
                (initiative_id, volunteer_name, email, phone, volunteer_experience, motivation, registration_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $status = $initiative['auto_approval'] ? 'مقبول' : 'قيد المراجعة';
            
            if ($insert_stmt->execute([$initiative_id, $name, $email, $phone, $experience, $motivation, $status])) {
                // تحديث عدد المتطوعين المسجلين إذا تم القبول التلقائي
                if ($initiative['auto_approval']) {
                    $update_stmt = $db->prepare("UPDATE youth_environmental_initiatives SET registered_volunteers = registered_volunteers + 1 WHERE id = ?");
                    $update_stmt->execute([$initiative_id]);
                }
                
                $message = $initiative['auto_approval'] ? 
                    "تم قبول تسجيلك في المبادرة بنجاح!" : 
                    "تم إرسال طلب التسجيل بنجاح. سيتم مراجعته قريباً.";
                    
                // إعادة جلب بيانات المبادرة المحدثة
                $stmt = $db->prepare("SELECT * FROM youth_environmental_initiatives WHERE id = ? AND is_active = 1");
                $stmt->execute([$initiative_id]);
                $initiative = $stmt->fetch();
            } else {
                // الحصول على تفاصيل الخطأ لتشخيص أفضل
                $errorInfo = $insert_stmt->errorInfo();
                if (strpos($errorInfo[2], 'Duplicate entry') !== false) {
                    $error = "يبدو أنك مسجل مسبقاً في هذه المبادرة. يرجى التحقق من بريدك الإلكتروني.";
                } else {
                    $error = "حدث خطأ في التسجيل. يرجى المحاولة مرة أخرى.";
                }
            }
        }
    }
}

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

$site_title = getSetting('site_title', 'بلدية تكريت');

// دالة لتنسيق التاريخ
function formatDate($date) {
    return date('Y/m/d', strtotime($date));
}

// دالة لحالة المبادرة
function getStatusBadge($status) {
    switch($status) {
        case 'مخطط': return '<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">📋 مخطط</span>';
        case 'قيد التنفيذ': return '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">⚙️ قيد التنفيذ</span>';
        case 'مكتمل': return '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">✅ مكتمل</span>';
        case 'متوقف': return '<span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm">⏸️ متوقف</span>';
        default: return '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm">📋 غير محدد</span>';
    }
}

// دالة لأيقونة نوع المبادرة
function getInitiativeIcon($type) {
    switch($type) {
        case 'شبابية': return '👥';
        case 'بيئية': return '🌱';
        case 'تطوعية': return '🤝';
        case 'تعليمية': return '📚';
        case 'رياضية': return '⚽';
        case 'ثقافية': return '🎭';
        default: return '🎯';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($initiative['initiative_name']) ?> - <?= htmlspecialchars($site_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/tekrit-theme.css" rel="stylesheet">
    <?= RecaptchaHelper::renderScript() ?>
    <?= RecaptchaHelper::renderCSS() ?>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .image-gallery img { cursor: pointer; transition: transform 0.3s ease; }
        .image-gallery img:hover { transform: scale(1.05); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); }
        .modal-content { margin: auto; display: block; width: 80%; max-width: 700px; }
        .close { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="tekrit-header sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center">
                    <img src="assets/images/Tekrit_LOGO.png" alt="شعار بلدية تكريت" class="tekrit-logo ml-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($site_title) ?></h1>
                        <p class="text-sm text-gray-600 hidden sm:block">خدمات إلكترونية للمواطنين</p>
                    </div>
                </div>
                <nav class="hidden lg:flex space-x-8 space-x-reverse">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium">الرئيسية</a>
                    <a href="initiatives.php" class="text-gray-700 hover:text-blue-600 font-medium">المبادرات</a>
                    <a href="projects.php" class="text-gray-700 hover:text-blue-600 font-medium">المشاريع</a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600 font-medium">الأخبار</a>
                    <a href="contact.php" class="text-gray-700 hover:text-blue-600 font-medium">اتصل بنا</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-4 space-x-reverse">
                    <li><a href="index.php" class="text-gray-500 hover:text-gray-700">الرئيسية</a></li>
                    <li><span class="text-gray-400">/</span></li>
                    <li><a href="initiatives.php" class="text-gray-500 hover:text-gray-700">المبادرات</a></li>
                    <li><span class="text-gray-400">/</span></li>
                    <li class="text-gray-900"><?= htmlspecialchars($initiative['initiative_name']) ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <!-- Hero Image -->
                <?php if ($initiative['main_image']): ?>
                    <div class="mb-8">
                        <img src="../uploads/initiatives/<?= htmlspecialchars($initiative['main_image']) ?>" 
                             alt="<?= htmlspecialchars($initiative['initiative_name']) ?>" 
                             class="w-full h-64 md:h-80 object-cover rounded-lg shadow-lg">
                    </div>
                <?php endif; ?>

                <!-- Initiative Details -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <div class="flex justify-between items-start mb-4">
                        <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($initiative['initiative_name']) ?></h1>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                            <?= htmlspecialchars($initiative['initiative_type']) ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600"><?= $initiative['max_volunteers'] ?></div>
                            <div class="text-sm text-gray-600">المتطوعين المطلوبين</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600"><?= $initiative['registered_volunteers'] ?></div>
                            <div class="text-sm text-gray-600">المسجلين حالياً</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">
                                <?= max(0, $initiative['max_volunteers'] - $initiative['registered_volunteers']) ?>
                            </div>
                            <div class="text-sm text-gray-600">المقاعد المتبقية</div>
                        </div>
                    </div>

                    <?php if ($initiative['max_volunteers'] > 0): ?>
                        <div class="mb-6">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>نسبة التسجيل</span>
                                <span><?= round(($initiative['registered_volunteers'] / $initiative['max_volunteers']) * 100) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-600 h-3 rounded-full" 
                                     style="width: <?= ($initiative['registered_volunteers'] / $initiative['max_volunteers']) * 100 ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="prose max-w-none">
                        <h3 class="text-xl font-semibold mb-3">وصف المبادرة</h3>
                        <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($initiative['initiative_description'])) ?></p>
                    </div>

                    <?php if ($initiative['requirements']): ?>
                        <div class="mt-6">
                            <h3 class="text-xl font-semibold mb-3">المتطلبات</h3>
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars($initiative['requirements'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($initiative['benefits']): ?>
                        <div class="mt-6">
                            <h3 class="text-xl font-semibold mb-3">المزايا والفوائد</h3>
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars($initiative['benefits'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                        <?php if ($initiative['location']): ?>
                            <div><strong>📍 الموقع:</strong> <?= htmlspecialchars($initiative['location']) ?></div>
                        <?php endif; ?>
                        <?php if ($initiative['registration_deadline']): ?>
                            <div><strong>📅 آخر موعد للتسجيل:</strong> <?= date('Y/m/d', strtotime($initiative['registration_deadline'])) ?></div>
                        <?php endif; ?>
                        <?php if ($initiative['budget']): ?>
                            <div><strong>💰 الميزانية:</strong> <?= number_format($initiative['budget']) ?> ل.ل.</div>
                        <?php endif; ?>
                        <div><strong>📊 الحالة:</strong> 
                            <span class="<?= $initiative['is_active'] ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $initiative['is_active'] ? 'نشطة' : 'غير نشطة' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Image Gallery -->
                <?php if (!empty($images)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h3 class="text-xl font-semibold mb-4">معرض الصور</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 image-gallery">
                            <?php foreach ($images as $image): ?>
                                <div class="relative">
                                    <img src="../uploads/initiatives/<?= htmlspecialchars($image['image_path']) ?>" 
                                         alt="<?= htmlspecialchars($image['image_description'] ?: $image['image_name']) ?>"
                                         class="w-full h-32 object-cover rounded-lg shadow-md"
                                         onclick="openModal('../uploads/initiatives/<?= htmlspecialchars($image['image_path']) ?>', '<?= htmlspecialchars($image['image_description'] ?: $image['image_name']) ?>')">
                                    <?php if ($image['image_type'] === 'رئيسية'): ?>
                                        <span class="absolute top-2 right-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">رئيسية</span>
                                    <?php endif; ?>
                                    <?php if ($image['image_description']): ?>
                                        <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs p-2 rounded-b-lg">
                                            <?= htmlspecialchars($image['image_description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Activities -->
                <?php if (!empty($activities)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold mb-4">الأنشطة والفعاليات</h3>
                        <div class="space-y-4">
                            <?php foreach ($activities as $activity): ?>
                                <div class="border-r-4 border-blue-500 pr-4">
                                    <h4 class="font-semibold"><?= htmlspecialchars($activity['activity_name']) ?></h4>
                                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($activity['activity_description']) ?></p>
                                    <div class="text-xs text-gray-500 mt-1">
                                        📅 <?= date('Y/m/d H:i', strtotime($activity['activity_date'])) ?>
                                        <?php if ($activity['location']): ?>
                                            | 📍 <?= htmlspecialchars($activity['location']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Registration Form -->
                <?php if ($initiative['is_active'] && $initiative['registered_volunteers'] < $initiative['max_volunteers']): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="text-xl font-semibold mb-4 text-center">انضم إلى المبادرة</h3>
                        
                        <?php if ($message): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                <?= $message ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                                <?= $error ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-4" id="registerForm">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
                                <input type="text" name="name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني *</label>
                                <input type="email" name="email" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف *</label>
                                <input type="tel" name="phone" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الخبرة السابقة</label>
                                <textarea name="experience" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="اذكر خبرتك السابقة في المجال (اختياري)"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">سبب الانضمام</label>
                                <textarea name="motivation" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="لماذا تريد الانضمام لهذه المبادرة؟"></textarea>
                            </div>
                            
                            <!-- reCAPTCHA v3 -->
                            <div class="recaptcha-container">
                                <?= RecaptchaHelper::renderWidget('initiative_register') ?>
                            </div>
                            
                            <button type="submit" name="register" 
                                    class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-300"
                                    onclick="return validateForm()">
                                تسجيل الانضمام
                            </button>
                        </form>
                    </div>
                <?php elseif (!$initiative['is_active']): ?>
                    <div class="bg-gray-100 rounded-lg p-6 text-center">
                        <p class="text-gray-600">هذه المبادرة غير نشطة حالياً</p>
                    </div>
                <?php else: ?>
                    <div class="bg-yellow-100 rounded-lg p-6 text-center">
                        <p class="text-yellow-800">تم الوصول للعدد المطلوب من المتطوعين</p>
                    </div>
                <?php endif; ?>

                <!-- Share -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4">شارك المبادرة</h3>
                    <div class="flex space-x-2 space-x-reverse">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                           target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700">
                            فيسبوك
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($_SERVER['REQUEST_URI']) ?>&text=<?= urlencode($initiative['initiative_name']) ?>" 
                           target="_blank" class="bg-blue-400 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-500">
                            تويتر
                        </a>
                        <button onclick="copyToClipboard()" class="bg-gray-600 text-white px-4 py-2 rounded-md text-sm hover:bg-gray-700">
                            نسخ الرابط
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
        <div id="caption" class="text-center text-white mt-4"></div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-center md:text-left mb-4 md:mb-0">
                    <p class="text-gray-400">© <?= date('Y') ?> جميع الحقوق محفوظة - <?= htmlspecialchars($site_title) ?></p>
                </div>
                <div class="flex items-center text-center md:text-right">
                    <a href="https://www.sobusiness.group/" target="_blank" class="hover:opacity-80 transition-opacity">
                        <img src="assets/images/sobusiness-logo.png" alt="SoBusiness Group" class="h-8 w-auto">
                    </a>
					<span class="text-gray-400 text-sm mr-2">Development and Designed By</span>
                </div>
            </div>
        </div>
    </footer>

    <script>
        function openModal(imageSrc, caption) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const captionText = document.getElementById('caption');
            
            modal.style.display = 'block';
            modalImg.src = imageSrc;
            captionText.innerHTML = caption;
        }

        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        function copyToClipboard() {
            navigator.clipboard.writeText(window.location.href).then(function() {
                alert('تم نسخ الرابط بنجاح!');
            });
        }

        function validateForm() {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();

            if (!name || !email || !phone) {
                alert('يرجى ملء جميع الحقول المطلوبة');
                return false;
            }

            // التحقق من صحة البريد الإلكتروني
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('يرجى إدخال بريد إلكتروني صحيح');
                return false;
            }

            // التحقق من رقم الهاتف
            const phoneRegex = /^[0-9+\-\s]+$/;
            if (!phoneRegex.test(phone)) {
                alert('يرجى إدخال رقم هاتف صحيح');
                return false;
            }

            return true;
        }

        // إضافة event listener لنموذج التسجيل
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                    }
                });
            }

            // إضافة anchor للانتقال إلى نموذج التسجيل
            if (window.location.hash === '#register') {
                const registerSection = document.getElementById('registerForm');
                if (registerSection) {
                    registerSection.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });

        // Close modal when clicking outside the image
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html> 
