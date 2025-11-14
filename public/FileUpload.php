<?php

class FileUpload {
    private $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    private $maxFileSize = 10485760; // 10MB
    private $uploadPath;
    
    public function __construct() {
        // تحديد مسار الرفع
        $this->uploadPath = __DIR__ . '/uploads/';
        
        // إنشاء مجلد الرفع إذا لم يكن موجوداً
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * رفع ملف واحد
     */
    public function uploadSingle($file, $requestId = null) {
        try {
            // التحقق من وجود خطأ في الرفع
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception($this->getUploadErrorMessage($file['error']));
            }
            
            // التحقق من حجم الملف
            if ($file['size'] > $this->maxFileSize) {
                throw new Exception('حجم الملف كبير جداً. الحد الأقصى المسموح: ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
            }
            
            // التحقق من نوع الملف
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $this->allowedTypes)) {
                throw new Exception('نوع الملف غير مدعوم. الأنواع المدعومة: ' . implode(', ', $this->allowedTypes));
            }
            
            // إنشاء اسم ملف فريد
            $filename = $this->generateUniqueFilename($file['name'], $requestId);
            $filepath = $this->uploadPath . $filename;
            
            // نقل الملف إلى المجلد المحدد
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('فشل في رفع الملف');
            }
            
            return [
                'success' => true,
                'filename' => $filename,
                'original_name' => $file['name'],
                'file_path' => $filepath,
                'file_size' => $file['size'],
                'file_type' => $fileExtension
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * رفع ملفات متعددة
     */
    public function handleMultipleUploads($files, $requestId = null) {
        $results = [];
        
        // التحقق من وجود ملفات
        if (!isset($files['name']) || !is_array($files['name'])) {
            return $results;
        }
        
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            // تجاهل الملفات الفارغة
            if (empty($files['name'][$i]) || $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $result = $this->uploadSingle($file, $requestId);
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * حذف ملف
     */
    public function deleteFile($filename) {
        try {
            $filepath = $this->uploadPath . $filename;
            
            if (file_exists($filepath)) {
                if (unlink($filepath)) {
                    return ['success' => true, 'message' => 'تم حذف الملف بنجاح'];
                } else {
                    throw new Exception('فشل في حذف الملف');
                }
            } else {
                throw new Exception('الملف غير موجود');
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * التحقق من وجود ملف
     */
    public function fileExists($filename) {
        return file_exists($this->uploadPath . $filename);
    }
    
    /**
     * الحصول على معلومات ملف
     */
    public function getFileInfo($filename) {
        $filepath = $this->uploadPath . $filename;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
            'type' => pathinfo($filename, PATHINFO_EXTENSION),
            'modified' => filemtime($filepath)
        ];
    }
    
    /**
     * توليد اسم ملف فريد
     */
    private function generateUniqueFilename($originalName, $requestId = null) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // تنظيف اسم الملف
        $basename = preg_replace('/[^a-zA-Z0-9\-_\u0600-\u06FF]/', '_', $basename);
        $basename = substr($basename, 0, 50); // تحديد طول الاسم
        
        // إضافة معرف الطلب إذا كان متوفراً
        $prefix = $requestId ? "req_{$requestId}_" : '';
        
        // إضافة timestamp لضمان الفرادة
        $timestamp = time();
        $random = rand(100, 999);
        
        return $prefix . $basename . "_{$timestamp}_{$random}." . $extension;
    }
    
    /**
     * الحصول على رسالة خطأ الرفع
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'حجم الملف كبير جداً';
            case UPLOAD_ERR_PARTIAL:
                return 'تم رفع الملف جزئياً فقط';
            case UPLOAD_ERR_NO_FILE:
                return 'لم يتم اختيار ملف';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'مجلد الملفات المؤقتة غير موجود';
            case UPLOAD_ERR_CANT_WRITE:
                return 'فشل في كتابة الملف';
            case UPLOAD_ERR_EXTENSION:
                return 'رفع الملف متوقف بواسطة إضافة PHP';
            default:
                return 'خطأ غير معروف في رفع الملف';
        }
    }
    
    /**
     * الحصول على مسار الرفع
     */
    public function getUploadPath() {
        return $this->uploadPath;
    }
    
    /**
     * تعيين مسار رفع مخصص
     */
    public function setUploadPath($path) {
        $this->uploadPath = rtrim($path, '/') . '/';
        
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * الحصول على الأنواع المدعومة
     */
    public function getAllowedTypes() {
        return $this->allowedTypes;
    }
    
    /**
     * تعيين أنواع ملفات مدعومة
     */
    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }
    
    /**
     * الحصول على الحد الأقصى لحجم الملف
     */
    public function getMaxFileSize() {
        return $this->maxFileSize;
    }
    
    /**
     * تعيين الحد الأقصى لحجم الملف
     */
    public function setMaxFileSize($size) {
        $this->maxFileSize = $size;
    }
}
?>

