<?php
/**
 * مثال على إعدادات قاعدة البيانات للخادم المحلي والخارجي
 * انسخ هذا الملف إلى database.php وعدل الإعدادات حسب خادمك
 */

class Database {
    // ==============================================
    // إعدادات الخادم المحلي (للتطوير)
    // ==============================================
    /*
    private $host = "localhost";
    private $db_name = "tekrit_municipality";
    private $username = "root";
    private $password = "";
    */
    
    // ==============================================
    // إعدادات الخادم الخارجي (للإنتاج)
    // ==============================================
    // مثال لخادم استضافة عادي
    private $host = "your-server.com";           // أو عنوان IP للخادم
    private $db_name = "your_database_name";     // اسم قاعدة البيانات على الخادم
    private $username = "your_db_username";     // اسم مستخدم قاعدة البيانات
    private $password = "your_secure_password"; // كلمة مرور قوية
    
    // ==============================================
    // أمثلة لخدمات استضافة مختلفة:
    // ==============================================
    
    // مثال لـ cPanel Hosting:
    /*
    private $host = "localhost";
    private $db_name = "username_tekrit";
    private $username = "username_tekrit";
    private $password = "strong_password_123";
    */
    
    // مثال لـ AWS RDS:
    /*
    private $host = "your-rds-endpoint.amazonaws.com";
    private $db_name = "tekrit_municipality";
    private $username = "admin";
    private $password = "your_aws_password";
    */
    
    // مثال لـ Google Cloud SQL:
    /*
    private $host = "your-cloud-sql-ip";
    private $db_name = "tekrit_municipality";
    private $username = "root";
    private $password = "your_gcp_password";
    */
    
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch(PDOException $exception) {
            // في بيئة الإنتاج، لا تظهر تفاصيل الخطأ للمستخدم
            if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
                echo "خطأ في الاتصال بقاعدة البيانات: " . $exception->getMessage();
            } else {
                echo "عذراً، حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.";
                error_log("Database Connection Error: " . $exception->getMessage());
            }
        }

        return $this->conn;
    }
}

/**
 * نصائح مهمة للأمان:
 * 
 * 1. استخدم كلمة مرور قوية لقاعدة البيانات
 * 2. لا تستخدم مستخدم root في بيئة الإنتاج
 * 3. أنشئ مستخدم مخصص مع صلاحيات محدودة فقط لهذه القاعدة
 * 4. فعل SSL للاتصال بقاعدة البيانات إذا كان متاحاً
 * 5. احتفظ بنسخة احتياطية من قاعدة البيانات بانتظام
 */
?> 
