<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// إنشاء جدول أعضاء المجلس البلدي إذا لم يكن موجوداً
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS council_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        position ENUM('رئيس البلدية', 'نائب رئيس البلدية', 'عضو مجلس', 'سكرتير المجلس', 'أمين المال') NOT NULL,
        specialization VARCHAR(255),
        biography TEXT,
        education TEXT,
        experience TEXT,
        profile_picture VARCHAR(500),
        phone VARCHAR(20),
        email VARCHAR(100),
        appointment_date DATE,
        term_start_date DATE,
        term_end_date DATE,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // إضافة بيانات تجريبية
    $count_check = $db->query("SELECT COUNT(*) as count FROM council_members")->fetch();
    if ($count_check['count'] == 0) {
        $sample_members = [
            ['رئيس البلدية', 'د. أحمد محمد العلي', 'إدارة عامة وتطوير المدن', 'رئيس بلدية تكريت، حاصل على دكتوراه في الإدارة العامة من جامعة بغداد. يتمتع بخبرة واسعة في إدارة المشاريع التطويرية والخدمات البلدية.', 'دكتوراه إدارة عامة - جامعة بغداد، ماجستير تخطيط حضري - الجامعة التكنولوجية', 'أكثر من 15 عامًا في الإدارة العامة والتطوير الحضري، شارك في تطوير العديد من المشاريع الاستراتيجية', '2022-01-15', '2022-01-15', '2026-01-15', 1],
            ['نائب رئيس البلدية', 'م. فاطمة حسن الجبوري', 'هندسة مدنية وبنية تحتية', 'نائب رئيس بلدية تكريت، مهندسة مدنية متخصصة في مشاريع البنية التحتية والتطوير الحضري.', 'بكالوريوس هندسة مدنية - جامعة تكريت، ماجستير إدارة مشاريع - الجامعة المستنصرية', '12 عامًا في مجال الهندسة المدنية ومشاريع البنية التحتية', '2022-01-15', '2022-01-15', '2026-01-15', 2],
            ['عضو مجلس', 'أ. سعد عبدالله الطائي', 'القانون والشؤون الإدارية', 'عضو مجلس البلدية، محامي وخبير في القانون الإداري والشؤون القانونية للبلديات.', 'بكالوريوس قانون - جامعة بغداد، دبلوم عالي في القانون الإداري', '10 سنوات في المحاماة والاستشارات القانونية، خبير في قوانين البلديات', '2022-01-15', '2022-01-15', '2026-01-15', 3]
        ];

        $stmt = $db->prepare("INSERT INTO council_members (position, full_name, specialization, biography, education, experience, appointment_date, term_start_date, term_end_date, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($sample_members as $member) {
            $stmt->execute($member);
        }
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
}

// جلب إعدادات الموقع
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '', $db = null) {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

$site_title = getSetting('site_title', 'بلدية تكريت', $db);

// جلب أعضاء المجلس البلدي
$council_query = $db->query("
    SELECT * FROM council_members 
    WHERE is_active = 1 
    ORDER BY display_order, 
        CASE position 
            WHEN 'رئيس البلدية' THEN 1 
            WHEN 'نائب رئيس البلدية' THEN 2 
            WHEN 'أمين المال' THEN 3 
            WHEN 'سكرتير المجلس' THEN 4 
            WHEN 'عضو مجلس' THEN 5 
            ELSE 6 
        END, full_name
");
$council_members = $council_query->fetchAll();

function getPositionIcon($position) {
    switch($position) {
        case 'رئيس البلدية': return '👑';
        case 'نائب رئيس البلدية': return '🎖️';
        case 'أمين المال': return '💰';
        case 'سكرتير المجلس': return '📝';
        case 'عضو مجلس': return '👤';
        default: return '👥';
    }
}

function getProfilePicture($member) {
    // تحقق من وجود صورة مرفوعة
    if (!empty($member['profile_picture']) && trim($member['profile_picture']) !== '') {
        $image_path = $member['profile_picture'];
        
        // للصفحة العامة في مجلد public، نحتاج إضافة '../' للمسار
        $full_path = '../' . $image_path;
        
        if (file_exists($full_path)) {
            // إرجاع المسار الصحيح للعرض في الصفحة العامة
            return '../' . $image_path;
        }
    }
    
    // في حالة عدم وجود صورة، استخدم avatar تلقائي
    $name = $member['full_name'];
    $is_female = false;
    
    // تحديد الجنس بناءً على الاسم
    $female_names = ['فاطمة', 'مريم', 'عائشة', 'زينب', 'ليلى', 'نور', 'هدى', 'أسماء', 'خديجة', 'سارة'];
    foreach ($female_names as $female_name) {
        if (strpos($name, $female_name) !== false) {
            $is_female = true;
            break;
        }
    }
    
    $bg_color = $is_female ? 'ec4899' : '3b82f6';
    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=' . $bg_color . '&color=fff&size=200&font-size=0.6';
}

// دالة مساعدة للتحقق من الصور
function debugImagePath($member) {
    if (!empty($member['profile_picture'])) {
        echo "<!-- تشخيص الصورة للعضو: " . $member['full_name'] . " -->\n";
        echo "<!-- المسار في قاعدة البيانات: " . $member['profile_picture'] . " -->\n";
        
        $paths = [
            '../' . $member['profile_picture'],
            $member['profile_picture'],
            '../uploads/council_members/' . basename($member['profile_picture'])
        ];
        
        foreach ($paths as $i => $path) {
            echo "<!-- المسار " . ($i+1) . ": " . $path . " - موجود: " . (file_exists($path) ? 'نعم' : 'لا') . " -->\n";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المجلس البلدي - <?= htmlspecialchars($site_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .member-card { transition: all 0.3s ease; }
        .member-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .member-detail { display: none; }
        .member-detail.active { display: block; }
    </style>
</head>
<body class="bg-gray-50">
    <header class="gradient-bg text-white py-8 shadow-lg">
        <div class="container mx-auto px-4">
            <h1 class="text-3xl md:text-4xl font-bold mb-2"><?= htmlspecialchars($site_title) ?></h1>
            <p class="text-blue-100">أعضاء المجلس البلدي ومناصبهم</p>
            
            <nav class="flex flex-wrap gap-4 text-sm mt-4">
                <a href="index.php" class="hover:text-blue-200">🏠 الرئيسية</a> |
                <a href="projects.php" class="hover:text-blue-200">🏗️ المشاريع</a> |
                <a href="committees.php" class="hover:text-blue-200">📋 اللجان البلدية</a> |
                <span class="text-blue-300 font-semibold">👥 المجلس البلدي</span>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">👥 أعضاء المجلس البلدي</h2>
            <p class="text-gray-600 max-w-2xl mx-auto text-lg">
                تعرف على أعضاء المجلس البلدي ومناصبهم واختصاصاتهم وخبراتهم
            </p>
            <div class="w-24 h-1 bg-blue-500 mx-auto mt-6"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($council_members as $member): ?>
                <div class="member-card bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="relative bg-gradient-to-br from-blue-500 to-purple-600 px-6 pt-6 pb-20">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-white text-xs font-medium">
                            <?= getPositionIcon($member['position']) ?> <?= $member['position'] ?>
                        </span>
                        
                        <div class="absolute left-1/2 transform -translate-x-1/2 -bottom-16">
                            <img 
                                src="<?= getProfilePicture($member) ?>" 
                                alt="<?= htmlspecialchars($member['full_name']) ?>"
                                class="w-32 h-32 rounded-full border-4 border-white object-cover"
                                onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($member['full_name']) ?>&background=3b82f6&color=fff&size=200'"
                            >
                        </div>
                    </div>

                    <div class="px-6 pt-20 pb-6">
                        <div class="text-center mb-4">
                            <h3 class="text-xl font-bold text-gray-800 mb-1">
                                <?= htmlspecialchars($member['full_name']) ?>
                            </h3>
                            <p class="text-blue-600 font-medium">
                                <?= htmlspecialchars($member['specialization']) ?>
                            </p>
                        </div>

                        <?php if ($member['biography']): ?>
                            <p class="text-gray-600 text-sm mb-4">
                                <?= htmlspecialchars(mb_substr($member['biography'], 0, 100)) ?>...
                            </p>
                        <?php endif; ?>

                        <button 
                            onclick="toggleMemberDetails(<?= $member['id'] ?>)"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium transition-colors"
                        >
                            عرض التفاصيل
                        </button>

                        <div id="member-details-<?= $member['id'] ?>" class="member-detail mt-6 pt-6 border-t">
                            <?php if ($member['biography']): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold mb-2">📋 نبذة تعريفية</h4>
                                    <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($member['biography'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($member['education']): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold mb-2">🎓 المؤهلات العلمية</h4>
                                    <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($member['education'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($member['experience']): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold mb-2">💼 الخبرة العملية</h4>
                                    <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($member['experience'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400">© <?= date('Y') ?> جميع الحقوق محفوظة - <?= htmlspecialchars($site_title) ?></p>
        </div>
    </footer>

    <script>
        function toggleMemberDetails(memberId) {
            const details = document.getElementById(`member-details-${memberId}`);
            const button = event.target;
            
            if (details.classList.contains('active')) {
                details.classList.remove('active');
                button.textContent = 'عرض التفاصيل';
            } else {
                details.classList.add('active');
                button.textContent = 'إخفاء التفاصيل';
            }
        }
    </script>
</body>
</html>
