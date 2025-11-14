<?php
/**
 * فئة إدارة صور الأخبار
 * تتعامل مع النظام الجديد للصور المنفصلة
 */
class NewsImageManager {
    private $db;
    private $upload_dir;
    private $allowed_types;
    private $max_file_size;
    
    public function __construct($database) {
        $this->db = $database;
        $this->upload_dir = '../uploads/news/';
        $this->loadSettings();
    }
    
    /**
     * تحميل إعدادات الصور من قاعدة البيانات
     */
    private function loadSettings() {
        $this->allowed_types = $this->getSetting('allowed_extensions', 'jpg,jpeg,png,gif,webp');
        $this->max_file_size = (int)$this->getSetting('max_file_size', '5242880'); // 5MB
    }
    
    /**
     * الحصول على إعدادات الصور
     */
    private function getSetting($name, $default = '') {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM news_image_settings WHERE setting_name = ?");
            $stmt->execute([$name]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * رفع الصورة الرئيسية للخبر
     */
    public function uploadFeaturedImage($file, $news_id) {
        if (!$this->validateImage($file)) {
            return ['success' => false, 'error' => 'ملف غير صالح'];
        }
        
        $filename = $this->generateFilename($file, 'featured');
        $full_path = $this->upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // تحديث الصورة الرئيسية في جدول الأخبار
            $stmt = $this->db->prepare("UPDATE news_activities SET featured_image = ? WHERE id = ?");
            $stmt->execute([$filename, $news_id]);
            
            return ['success' => true, 'filename' => $filename];
        }
        
        return ['success' => false, 'error' => 'فشل في رفع الملف'];
    }
    
    /**
     * رفع صور المعرض للخبر
     */
    public function uploadGalleryImages($files, $news_id, $uploaded_by = null) {
        $uploaded = [];
        $errors = [];
        
        // التحقق من عدد الصور الحالية
        $current_count = $this->getImageCount($news_id);
        $max_images = (int)$this->getSetting('max_images_per_news', '10');
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($current_count + count($uploaded) >= $max_images) {
                $errors[] = "تم الوصول للحد الأقصى من الصور ($max_images)";
                break;
            }
            
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            if ($file['error'] !== UPLOAD_ERR_OK || empty($file['name'])) {
                continue;
            }
            
            if (!$this->validateImage($file)) {
                $errors[] = "ملف غير صالح: " . $file['name'];
                continue;
            }
            
            $filename = $this->generateFilename($file, 'gallery');
            $full_path = $this->upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $full_path)) {
                // إضافة الصورة لجدول صور الأخبار
                $display_order = $this->getNextDisplayOrder($news_id);
                $stmt = $this->db->prepare("
                    INSERT INTO news_images (news_id, image_filename, image_type, display_order, image_size, uploaded_by) 
                    VALUES (?, ?, 'gallery', ?, ?, ?)
                ");
                $stmt->execute([$news_id, $filename, $display_order, $file['size'], $uploaded_by]);
                
                $uploaded[] = [
                    'id' => $this->db->lastInsertId(),
                    'filename' => $filename,
                    'size' => $file['size']
                ];
            } else {
                $errors[] = "فشل في رفع: " . $file['name'];
            }
        }
        
        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
            'total_uploaded' => count($uploaded)
        ];
    }
    
    /**
     * الحصول على عدد صور الخبر
     */
    public function getImageCount($news_id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM news_images WHERE news_id = ? AND is_active = 1");
        $stmt->execute([$news_id]);
        return $stmt->fetch()['count'];
    }
    
    /**
     * الحصول على ترتيب العرض التالي
     */
    private function getNextDisplayOrder($news_id) {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM news_images WHERE news_id = ?");
        $stmt->execute([$news_id]);
        return $stmt->fetch()['next_order'];
    }
    
    /**
     * التحقق من صحة الصورة
     */
    private function validateImage($file) {
        // التحقق من الحجم
        if ($file['size'] > $this->max_file_size) {
            return false;
        }
        
        // التحقق من نوع الملف
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = explode(',', $this->allowed_types);
        
        if (!in_array($extension, $allowed_extensions)) {
            return false;
        }
        
        // التحقق من نوع MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'image/jpeg', 'image/jpg', 'image/png', 
            'image/gif', 'image/webp'
        ];
        
        return in_array($mime_type, $allowed_mimes);
    }
    
    /**
     * إنشاء اسم ملف فريد
     */
    private function generateFilename($file, $prefix = '') {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return "news_{$prefix}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * الحصول على صور الخبر
     */
    public function getNewsImages($news_id, $include_featured = true) {
        $images = [
            'featured' => null,
            'gallery' => []
        ];
        
        if ($include_featured) {
            // الحصول على الصورة الرئيسية
            $stmt = $this->db->prepare("SELECT featured_image FROM news_activities WHERE id = ?");
            $stmt->execute([$news_id]);
            $news = $stmt->fetch();
            $images['featured'] = $news['featured_image'] ?? null;
        }
        
        // الحصول على صور المعرض
        $stmt = $this->db->prepare("
            SELECT * FROM news_images 
            WHERE news_id = ? AND is_active = 1 
            ORDER BY display_order, id
        ");
        $stmt->execute([$news_id]);
        $images['gallery'] = $stmt->fetchAll();
        
        return $images;
    }
    
    /**
     * حذف صورة
     */
    public function deleteImage($image_id, $user_id = null) {
        try {
            // الحصول على معلومات الصورة
            $stmt = $this->db->prepare("SELECT * FROM news_images WHERE id = ?");
            $stmt->execute([$image_id]);
            $image = $stmt->fetch();
            
            if (!$image) {
                return ['success' => false, 'error' => 'الصورة غير موجودة'];
            }
            
            // حذف الملف الفعلي
            $file_path = $this->upload_dir . $image['image_filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // حذف السجل من قاعدة البيانات
            $stmt = $this->db->prepare("DELETE FROM news_images WHERE id = ?");
            $stmt->execute([$image_id]);
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * تحديث ترتيب الصور
     */
    public function updateImageOrder($news_id, $image_orders) {
        try {
            $this->db->beginTransaction();
            
            foreach ($image_orders as $order) {
                $stmt = $this->db->prepare("
                    UPDATE news_images 
                    SET display_order = ? 
                    WHERE id = ? AND news_id = ?
                ");
                $stmt->execute([$order['order'], $order['id'], $news_id]);
            }
            
            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * الحصول على معلومات الصورة
     */
    public function getImageInfo($image_id) {
        $stmt = $this->db->prepare("SELECT * FROM news_images WHERE id = ?");
        $stmt->execute([$image_id]);
        return $stmt->fetch();
    }
    
    /**
     * تحديث معلومات الصورة
     */
    public function updateImageInfo($image_id, $title, $description) {
        try {
            $stmt = $this->db->prepare("
                UPDATE news_images 
                SET image_title = ?, image_description = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $image_id]);
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * الحصول على إحصائيات الصور
     */
    public function getStatistics() {
        $stats = [];
        
        // إجمالي الصور
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM news_images WHERE is_active = 1");
        $stats['total_images'] = $stmt->fetch()['total'];
        
        // الأخبار التي لها صور رئيسية
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM news_activities WHERE featured_image IS NOT NULL AND featured_image != ''");
        $stats['news_with_featured'] = $stmt->fetch()['total'];
        
        // متوسط عدد الصور لكل خبر
        $stmt = $this->db->query("
            SELECT AVG(image_count) as avg_count 
            FROM (
                SELECT COUNT(*) as image_count 
                FROM news_images 
                WHERE is_active = 1 
                GROUP BY news_id
            ) as counts
        ");
        $result = $stmt->fetch();
        $stats['avg_images_per_news'] = round($result['avg_count'] ?? 0, 2);
        
        // حجم الملفات الإجمالي
        $stmt = $this->db->query("SELECT SUM(image_size) as total_size FROM news_images WHERE is_active = 1");
        $stats['total_size'] = $stmt->fetch()['total_size'] ?? 0;
        
        return $stats;
    }
}
?> 