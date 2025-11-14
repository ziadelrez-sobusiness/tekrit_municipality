<?php
require_once __DIR__ . '/../config/config.php';

class FileUpload {
    private $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    private $maxFileSize;
    private $uploadPath;
    
    public function __construct() {
        $this->maxFileSize = MAX_FILE_SIZE;
        $this->uploadPath = UPLOAD_PATH;
        
        // إنشاء مجلد الرفع إذا لم يكن موجوداً
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * رفع ملف واحد
     */
    public function uploadFile($file, $subfolder = '') {
        try {
            // التحقق من وجود الملف
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception('لم يتم اختيار ملف للرفع');
            }
            
            // التحقق من عدم وجود أخطاء في الرفع
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception($this->getUploadErrorMessage($file['error']));
            }
            
            // التحقق من حجم الملف
            if ($file['size'] > $this->maxFileSize) {
                $maxSizeMB = $this->maxFileSize / (1024 * 1024);
                throw new Exception("حجم الملف كبير جداً. الحد الأقصى المسموح: {$maxSizeMB} ميجابايت");
            }
            
            // التحقق من نوع الملف
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $this->allowedTypes)) {
                $allowedTypesStr = implode(', ', $this->allowedTypes);
                throw new Exception("نوع الملف غير مسموح. الأنواع المسموحة: {$allowedTypesStr}");
            }
            
            // إنشاء اسم ملف فريد
            $fileName = $this->generateUniqueFileName($file['name']);
            
            // تحديد مسار الحفظ
            $targetPath = $this->uploadPath;
            if (!empty($subfolder)) {
                $targetPath .= $subfolder . '/';
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            }
            
            $fullPath = $targetPath . $fileName;
            
            // نقل الملف إلى المجلد المحدد
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception('فشل في رفع الملف');
            }
            
            return [
                'success' => true,
                'file_name' => $file['name'],
                'saved_name' => $fileName,
                'file_path' => $fullPath,
                'relative_path' => str_replace($this->uploadPath, '', $fullPath),
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
     * رفع عدة ملفات
     */
    public function uploadMultipleFiles($files, $subfolder = '') {
        $results = [];
        
        // التحقق من أن المدخل هو مصفوفة من الملفات
        if (!is_array($files['name'])) {
            // ملف واحد فقط
            return [$this->uploadFile($files, $subfolder)];
        }
        
        // عدة ملفات
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $results[] = $this->uploadFile($file, $subfolder);
        }
        
        return $results;
    }
    
    /**
     * حذف ملف
     */
    public function deleteFile($filePath) {
        try {
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    return ['success' => true];
                } else {
                    throw new Exception('فشل في حذف الملف');
                }
            } else {
                throw new Exception('الملف غير موجود');
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * إنشاء اسم ملف فريد
     */
    private function generateUniqueFileName($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // تنظيف اسم الملف
        $baseName = preg_replace('/[^a-zA-Z0-9\-_\u0600-\u06FF]/', '_', $baseName);
        
        // إضافة طابع زمني
        $timestamp = date('Y-m-d_H-i-s');
        $randomString = substr(md5(uniqid(rand(), true)), 0, 8);
        
        return $baseName . '_' . $timestamp . '_' . $randomString . '.' . $extension;
    }
    
    /**
     * الحصول على رسالة خطأ الرفع
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'حجم الملف أكبر من الحد المسموح في إعدادات الخادم';
            case UPLOAD_ERR_FORM_SIZE:
                return 'حجم الملف أكبر من الحد المسموح في النموذج';
            case UPLOAD_ERR_PARTIAL:
                return 'تم رفع جزء من الملف فقط';
            case UPLOAD_ERR_NO_FILE:
                return 'لم يتم اختيار ملف للرفع';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'مجلد الملفات المؤقتة غير موجود';
            case UPLOAD_ERR_CANT_WRITE:
                return 'فشل في كتابة الملف على القرص';
            case UPLOAD_ERR_EXTENSION:
                return 'امتداد PHP أوقف رفع الملف';
            default:
                return 'خطأ غير معروف في رفع الملف';
        }
    }
    
    /**
     * التحقق من صحة نوع الملف بناءً على محتواه
     */
    public function validateFileType($filePath, $expectedExtension) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        return isset($allowedMimeTypes[$expectedExtension]) && 
               $mimeType === $allowedMimeTypes[$expectedExtension];
    }
    
    /**
     * الحصول على معلومات الملف
     */
    public function getFileInfo($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }
        
        return [
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
            'type' => mime_content_type($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION)
        ];
    }
}
?> 