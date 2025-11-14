<?php
class Database {
    // إعدادات قاعدة البيانات للـ hosting
    // **يجب تعديل هذه القيم حسب معلومات hosting الخاص بك**
    
    private $host = "localhost"; // أو عنوان الخادم المحدد من hosting
    private $db_name = "your_database_name"; // اسم قاعدة البيانات على hosting
    private $username = "your_db_username"; // اسم مستخدم قاعدة البيانات
    private $password = "your_db_password"; // كلمة مرور قاعدة البيانات
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            // إعدادات محسنة للـ hosting
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            // في بيئة الإنتاج، لا نعرض تفاصيل الخطأ للمستخدم
            error_log("Database connection error: " . $exception->getMessage());
            
            // للتشخيص فقط - احذف هذا السطر في الإنتاج
            if ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '.local') !== false) {
                echo "خطأ في الاتصال بقاعدة البيانات: " . $exception->getMessage();
            } else {
                echo "خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.";
            }
        }

        return $this->conn;
    }
}

/*
===========================================
إرشادات تكوين قاعدة البيانات للـ hosting:
===========================================

1. **cPanel Hosting:**
   - Host: عادة "localhost"
   - Database Name: عادة "username_dbname"
   - Username: عادة "username_dbuser"
   - Password: كلمة المرور التي اخترتها

2. **Shared Hosting:**
   - تحقق من لوحة التحكم الخاصة بك
   - قد يكون Host مختلف عن localhost

3. **VPS/Dedicated:**
   - Host: عادة "localhost" أو عنوان IP
   - يمكنك استخدام أي اسم قاعدة بيانات تريده

4. **Cloud Hosting (AWS RDS, Google Cloud SQL):**
   - Host: عنوان الخادم المحدد
   - Port: قد تحتاج لتحديد المنفذ

مثال للتكوين:
$host = "localhost"; // أو db.yourhosting.com
$db_name = "username_tekrit_municipality";
$username = "username_dbuser";
$password = "secure_password_123";
*/
?> 
