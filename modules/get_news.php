<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    echo json_encode(['error' => 'غير مصرح لك بالوصول']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

if (isset($_GET['id'])) {
    $news_id = $_GET['id'];
    
    try {
        // جلب بيانات الخبر
        $stmt = $db->prepare("SELECT * FROM news_activities WHERE id = ?");
        $stmt->execute([$news_id]);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($news) {
            // تشخيص: طباعة بيانات الخبر الأساسية
            error_log("بيانات الخبر الأساسية: " . print_r($news, true));
            
            // جلب صور المعرض - استخدام نفس الاستعلام من news-detail.php
            $stmt = $db->prepare("
                SELECT id, image_filename, image_title, image_description, display_order 
                FROM news_images 
                WHERE news_id = ? AND is_active = 1 
                ORDER BY display_order, id
            ");
            $stmt->execute([$news_id]);
            $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تشخيص: طباعة صور المعرض
            error_log("صور المعرض الموجودة: " . print_r($gallery_images, true));
            
            // تحويل البيانات للتوافق مع الكود الجديد
            $processed_gallery = [];
            foreach ($gallery_images as $img) {
                $processed_gallery[] = [
                    'id' => $img['id'],
                    'image_path' => $img['image_filename'], // استخدام image_filename كـ image_path
                    'image_name' => $img['image_title'], // استخدام image_title كـ image_name
                    'image_description' => $img['image_description'],
                    'display_order' => $img['display_order'],
                    // الاحتفاظ بالأسماء الأصلية أيضاً
                    'image_filename' => $img['image_filename'],
                    'image_title' => $img['image_title']
                ];
            }
            
            // إضافة صور المعرض للنتيجة
            $news['gallery_images_data'] = $processed_gallery;
            
            // إنشاء مصفوفة للتوافق مع الكود القديم
            $news['gallery_images'] = array_map(function($img) {
                return $img['image_filename'];
            }, $gallery_images);
            
            // فحص وجود الصور فعلياً في الخادم
            $upload_path = dirname(__FILE__) . '/../uploads/news/';
            
            // فحص الصورة الرئيسية
            if (!empty($news['featured_image'])) {
                $featured_path = $upload_path . $news['featured_image'];
                $featured_exists = file_exists($featured_path);
                error_log("الصورة الرئيسية {$news['featured_image']} موجودة: " . ($featured_exists ? 'نعم' : 'لا'));
                error_log("المسار الكامل للصورة الرئيسية: " . $featured_path);
            }
            
            // فحص صور المعرض
            foreach ($news['gallery_images'] as $index => $image_filename) {
                if (!empty($image_filename)) {
                    $full_path = $upload_path . $image_filename;
                    $exists = file_exists($full_path);
                    error_log("صورة المعرض {$index}: {$image_filename} موجودة: " . ($exists ? 'نعم' : 'لا'));
                    error_log("المسار الكامل لصورة المعرض {$index}: " . $full_path);
                }
            }
            
            // إضافة معلومات إضافية للتشخيص
            $news['debug_info'] = [
                'gallery_count' => count($gallery_images),
                'upload_path_exists' => is_dir($upload_path),
                'upload_path' => $upload_path
            ];
            
            echo json_encode($news, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'الخبر غير موجود']);
        }
    } catch (Exception $e) {
        error_log("خطأ في جلب بيانات الخبر: " . $e->getMessage());
        echo json_encode(['error' => 'خطأ في جلب بيانات الخبر: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'معرف الخبر غير محدد']);
}
?>