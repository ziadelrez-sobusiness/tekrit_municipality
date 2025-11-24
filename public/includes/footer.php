<?php
/**
 * Footer مشترك لجميع صفحات الموقع العام
 * 
 * الاستخدام:
 * require_once 'includes/footer.php';
 * 
 * المتغيرات المطلوبة:
 * - $site_title: عنوان الموقع
 * - $db: اتصال قاعدة البيانات (لجلب الإعدادات)
 * 
 * المتغيرات الاختيارية:
 * - $show_project_statuses: إذا كان true، سيظهر قسم حالات المشاريع (افتراضي: false)
 */

// دالة مساعدة لجلب الإعدادات بشكل آمن
function getFooterSetting($key, $default = '') {
    global $db;
    
    // إذا كان $db غير متاح، استخدم القيمة الافتراضية
    if (!$db || !is_object($db) || !method_exists($db, 'prepare')) {
        return $default;
    }
    
    // إذا كانت دالة getSetting موجودة، جرب استخدامها
    if (function_exists('getSetting')) {
        // جرب استدعاء getSetting مع $db كمعامل ثالث (لصفحات مثل council.php)
        // استخدم @ لقمع الأخطاء في حالة عدم قبول 3 معاملات
        $result = @getSetting($key, $default, $db);
        if ($result !== null && $result !== false) {
            return $result;
        }
        
        // إذا فشل، جرب بدون $db (لصفحات أخرى)
        $result = @getSetting($key, $default);
        if ($result !== null && $result !== false) {
            return $result;
        }
    }
    
    // إذا لم تكن الدالة موجودة أو فشلت، جرب استخدام $db مباشرة
    try {
        $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
        if ($stmt) {
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        }
    } catch (Exception $e) {
        // في حالة الخطأ، استخدم القيمة الافتراضية
    }
    
    return $default;
}

$site_title = isset($site_title) ? $site_title : getFooterSetting('site_title', 'بلدية تكريت');
$show_project_statuses = isset($show_project_statuses) ? $show_project_statuses : false;

// حالات المشاريع (إذا كان مطلوباً)
$project_statuses = ['مطروح', 'قيد التنفيذ', 'منفذ', 'متوقف', 'ملغي'];
?>

<!-- Footer -->
<footer class="bg-gray-900 text-white py-8 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- معلومات البلدية -->
            <div>
                <div class="flex items-center mb-4">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-3">🏛️</div>
                    <h3 class="text-lg font-bold"><?= htmlspecialchars($site_title) ?></h3>
                </div>
                <p class="text-gray-300">تطوير مستمر لخدمات ومرافق المدينة</p>
            </div>
            
            <!-- الأقسام -->
            <div>
                <h4 class="text-lg font-semibold mb-4">الأقسام</h4>
                <ul class="space-y-2">
                    <li><a href="index.php" class="text-gray-300 hover:text-white transition">الرئيسية</a></li>
                    <li><a href="news.php" class="text-gray-300 hover:text-white transition">الأخبار</a></li>
                    <li><a href="citizen-requests.php" class="text-gray-300 hover:text-white transition">طلبات المواطنين</a></li>
                    <li><a href="important-links.php" class="text-gray-300 hover:text-white transition">🔗 روابط مهمة</a></li>
                    <li><a href="contact.php" class="text-gray-300 hover:text-white transition">اتصل بنا</a></li>
                </ul>
            </div>
            
            <!-- حالات المشاريع (اختياري) -->
            <?php if ($show_project_statuses): ?>
            <div>
                <h4 class="text-lg font-semibold mb-4">حالات المشاريع</h4>
                <ul class="space-y-2">
                    <?php foreach ($project_statuses as $status): ?>
                        <li><a href="projects.php?status=<?= urlencode($status) ?>" class="text-gray-300 hover:text-white transition"><?= $status ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php else: ?>
            <!-- بديل: روابط إضافية -->
            <div>
                <h4 class="text-lg font-semibold mb-4">روابط سريعة</h4>
                <ul class="space-y-2">
                    <li><a href="projects.php" class="text-gray-300 hover:text-white transition">المشاريع</a></li>
                    <li><a href="initiatives.php" class="text-gray-300 hover:text-white transition">المبادرات</a></li>
                    <li><a href="facilities-map.php" class="text-gray-300 hover:text-white transition">خريطة المرافق</a></li>
                    <li><a href="council.php" class="text-gray-300 hover:text-white transition">المجلس البلدي</a></li>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- تواصل معنا -->
            <div>
                <h4 class="text-lg font-semibold mb-4">تواصل معنا</h4>
                <div class="space-y-2">
                    <p class="text-gray-300">📞 <?= htmlspecialchars(getFooterSetting('contact_phone', '+9613194685')) ?></p>
                    <p class="text-gray-300">✉️ <?= htmlspecialchars(getFooterSetting('contact_email', 'info@tikrit-municipality.gov.iq')) ?></p>
                </div>
            </div>
        </div>
        
        <hr class="my-8 border-gray-700">
        
        <!-- حقوق النشر -->
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

