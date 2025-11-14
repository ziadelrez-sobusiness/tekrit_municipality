<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$news_id = $_GET['id'] ?? 0;

if (!$news_id) {
    header('Location: news.php');
    exit();
}

// جلب تفاصيل الخبر
$stmt = $db->prepare("
    SELECT n.*, u.full_name as creator_name 
    FROM news_activities n 
    LEFT JOIN users u ON n.created_by = u.id 
    WHERE n.id = ? AND n.is_published = 1
");
$stmt->execute([$news_id]);
$news = $stmt->fetch();

if (!$news) {
    header('Location: news.php');
    exit();
}

// جلب صور المعرض من الجدول المنفصل
$stmt = $db->prepare("
    SELECT * FROM news_images 
    WHERE news_id = ? AND is_active = 1 
    ORDER BY display_order, id
");
$stmt->execute([$news_id]);
$gallery_images = $stmt->fetchAll();

// زيادة عدد المشاهدات
$db->prepare("UPDATE news_activities SET views_count = views_count + 1 WHERE id = ?")->execute([$news_id]);

// جلب الأخبار ذات الصلة
$related_news = $db->prepare("
    SELECT * FROM news_activities 
    WHERE id != ? AND news_type = ? AND is_published = 1 
    ORDER BY publish_date DESC 
    LIMIT 3
");
$related_news->execute([$news_id, $news['news_type']]);
$related = $related_news->fetchAll();

// جلب إعدادات الموقع
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

$site_title = getSetting('site_title', 'بلدية تكريت');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($news['title']) ?> - <?= htmlspecialchars($site_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars(substr($news['content'], 0, 160)) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .content-text { line-height: 1.8; }
        .card-hover { transition: transform 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); }
        .image-gallery img { transition: transform 0.3s ease; cursor: pointer; }
        .image-gallery img:hover { transform: scale(1.05); }
        
        /* Modal styles */
        .modal { display: none; }
        .modal.show { display: flex; }
        .modal-content { max-width: 90vw; max-height: 90vh; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">🏛️</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($site_title) ?></h1>
                        <p class="text-sm text-gray-500">تفاصيل الخبر</p>
                    </div>
                </div>
                <nav class="hidden md:flex space-x-8 space-x-reverse">
                    <a href="index.php" class="text-gray-700 hover:text-indigo-600 font-medium">الرئيسية</a>
                    <a href="news.php" class="text-indigo-600 font-medium">الأخبار</a>
                    <a href="projects.php" class="text-gray-700 hover:text-indigo-600 font-medium">المشاريع</a>
                    <a href="citizen-requests.php" class="text-gray-700 hover:text-indigo-600 font-medium">طلبات المواطنين</a>
                    <a href="contact.php" class="text-gray-700 hover:text-indigo-600 font-medium">اتصل بنا</a>
                </nav>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-8">
            <ol class="flex items-center space-x-2 space-x-reverse text-sm text-gray-500">
                <li><a href="index.php" class="hover:text-indigo-600">الرئيسية</a></li>
                <li class="before:content-['›'] before:mx-2">
                    <a href="news.php" class="hover:text-indigo-600">الأخبار</a>
                </li>
                <li class="before:content-['›'] before:mx-2">
                    <span class="text-gray-900"><?= htmlspecialchars($news['title']) ?></span>
                </li>
            </ol>
        </nav>

        <!-- Main Article -->
        <article class="bg-white rounded-lg shadow-lg overflow-hidden mb-12">
            <!-- Featured Image -->
            <?php if ($news['featured_image']): ?>
                <div class="relative">
                    <img src="../uploads/news/<?= htmlspecialchars($news['featured_image']) ?>" 
                         alt="<?= htmlspecialchars($news['title']) ?>" 
                         class="w-full h-64 md:h-96 object-cover cursor-pointer"
                         onclick="openImageModal('../uploads/news/<?= htmlspecialchars($news['featured_image']) ?>', '<?= htmlspecialchars($news['title']) ?>')">
                    <div class="absolute bottom-4 right-4 bg-black bg-opacity-60 text-white px-3 py-1 rounded-lg text-sm">
                        📷 الصورة الرئيسية
                    </div>
                </div>
            <?php else: ?>
                <div class="w-full h-64 md:h-96 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                    <span class="text-white text-8xl">
                        <?php 
                            switch($news['news_type']) {
                                case 'رسمية': echo '📋'; break;
                                case 'مناسبات محلية': echo '🎉'; break;
                                case 'أنشطة اجتماعية': echo '🤝'; break;
                                case 'إعلام رسمي': echo '📢'; break;
                                default: echo '📰';
                            }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="p-8">
                <!-- Article Header -->
                <div class="mb-6">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                            <?= $news['news_type'] ?>
                        </span>
                        <?php if ($news['is_featured']): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                ⭐ خبر مميز
                            </span>
                        <?php endif; ?>
                        <?php if (count($gallery_images) > 0): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                🖼️ <?= count($gallery_images) ?> صورة
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 leading-tight">
                        <?= htmlspecialchars($news['title']) ?>
                    </h1>
                    
                    <div class="flex flex-wrap items-center gap-6 text-sm text-gray-600">
                        <div class="flex items-center">
                            <span class="ml-2">📅</span>
                            <span><?= date('Y/m/d', strtotime($news['publish_date'])) ?></span>
                        </div>
                        <div class="flex items-center">
                            <span class="ml-2">👁️</span>
                            <span><?= number_format($news['views_count']) ?> مشاهدة</span>
                        </div>
                        <div class="flex items-center">
                            <span class="ml-2">✍️</span>
                            <span>بواسطة: <?= htmlspecialchars($news['creator_name'] ?: 'إدارة البلدية') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Article Content -->
                <div class="prose prose-lg max-w-none content-text text-gray-800 mb-8">
                    <?= nl2br(htmlspecialchars($news['content'])) ?>
                </div>

                <!-- Gallery Images -->
                <?php if (!empty($gallery_images)): ?>
                    <div class="mt-8 border-t border-gray-200 pt-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <span class="ml-2">🖼️</span>
                            صور إضافية من الخبر (<?= count($gallery_images) ?>)
                        </h3>
                        <div class="image-gallery grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($gallery_images as $index => $image): ?>
                                <div class="relative group">
                                    <img src="../uploads/news/<?= htmlspecialchars($image['image_filename']) ?>" 
                                         alt="<?= htmlspecialchars($image['image_title'] ?: 'صورة من الخبر ' . ($index + 1)) ?>" 
                                         class="w-full h-48 object-cover rounded-lg shadow-md"
                                         onclick="openImageModal('../uploads/news/<?= htmlspecialchars($image['image_filename']) ?>', '<?= htmlspecialchars($image['image_title'] ?: 'صورة من الخبر ' . ($index + 1)) ?>')">
                                    
                                    <!-- Image overlay -->
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 rounded-lg flex items-center justify-center">
                                        <span class="text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 text-sm font-medium">
                                            🔍 اضغط للتكبير
                                        </span>
                                    </div>
                                    
                                    <!-- Image number -->
                                    <div class="absolute top-2 left-2 bg-black bg-opacity-60 text-white text-xs px-2 py-1 rounded">
                                        <?= $index + 1 ?>
                                    </div>
                                    
                                    <!-- Image title -->
                                    <?php if ($image['image_title']): ?>
                                        <div class="absolute bottom-2 left-2 right-2 bg-black bg-opacity-60 text-white text-xs p-2 rounded truncate">
                                            <?= htmlspecialchars($image['image_title']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Gallery note -->
                        <div class="mt-4 text-sm text-gray-500 text-center">
                            💡 اضغط على أي صورة لمشاهدتها بحجم كبير
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Share Section -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">شارك هذا الخبر</h3>
                        <div class="flex items-center space-x-4 space-x-reverse">
                            <button onclick="shareOnFacebook()" class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                📘 فيسبوك
                            </button>
                            <button onclick="shareOnTwitter()" class="p-2 bg-blue-400 text-white rounded-lg hover:bg-blue-500">
                                🐦 تويتر
                            </button>
                            <button onclick="copyLink()" class="p-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                🔗 نسخ الرابط
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <!-- Navigation -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center">
                <a href="news.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    ← العودة لجميع الأخبار
                </a>
                <a href="news.php?type=<?= urlencode($news['news_type']) ?>" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    أخبار <?= $news['news_type'] ?> →
                </a>
            </div>
        </div>

        <!-- Related News -->
        <?php if (!empty($related)): ?>
            <div class="bg-white rounded-lg shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">أخبار ذات صلة</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($related as $item): ?>
                        <article class="card-hover border rounded-lg overflow-hidden">
                            <?php if ($item['featured_image']): ?>
                                <img src="../uploads/news/<?= htmlspecialchars($item['featured_image']) ?>" 
                                     alt="<?= htmlspecialchars($item['title']) ?>" 
                                     class="w-full h-32 object-cover">
                            <?php else: ?>
                                <div class="w-full h-32 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                    <span class="text-white text-3xl">📰</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-4">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2 leading-tight">
                                    <a href="news-detail.php?id=<?= $item['id'] ?>" class="hover:text-indigo-600">
                                        <?= htmlspecialchars(substr($item['title'], 0, 60)) ?><?= strlen($item['title']) > 60 ? '...' : '' ?>
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-600 mb-3">
                                    <?= htmlspecialchars(substr($item['content'], 0, 80)) ?>...
                                </p>
                                <div class="flex justify-between items-center text-xs text-gray-500">
                                    <span><?= date('Y/m/d', strtotime($item['publish_date'])) ?></span>
                                    <a href="news-detail.php?id=<?= $item['id'] ?>" class="text-indigo-600 hover:text-indigo-800">
                                        قراءة المزيد
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
        function shareOnFacebook() {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent('<?= addslashes($news['title']) ?>');
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank');
        }

        function shareOnTwitter() {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent('<?= addslashes($news['title']) ?>');
            window.open(`https://twitter.com/intent/tweet?url=${url}&text=${title}`, '_blank');
        }

        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(function() {
                alert('تم نسخ الرابط إلى الحافظة');
            });
        }
    </script>
</body>
</html> 
