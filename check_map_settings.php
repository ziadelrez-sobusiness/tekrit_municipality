<?php
// Database connection
$host = 'localhost';
$dbname = 'tekrit_municipality';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>إعدادات الخريطة في قاعدة البيانات:</h2>";
    
    // Get all website settings
    $stmt = $pdo->query("SELECT * FROM website_settings WHERE setting_key LIKE '%contact%' OR setting_key LIKE '%location%'");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "<p>لا توجد إعدادات في قاعدة البيانات</p>";
        
        // Insert default settings
        $defaultSettings = [
            'contact_location_lat' => '33.4384',
            'contact_location_lng' => '43.6793',
            'contact_location_name' => 'بلدية تكريت',
            'contact_address' => 'تكريت، محافظة صلاح الدين، العراق',
            'contact_phone' => '+964 25 123 4567',
            'contact_email' => 'info@tekrit-municipality.gov.iq',
            'emergency_phone' => '+964 25 999 8888'
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        echo "<p>تم إدراج الإعدادات الافتراضية</p>";
        
        // Get settings again
        $stmt = $pdo->query("SELECT * FROM website_settings WHERE setting_key LIKE '%contact%' OR setting_key LIKE '%location%'");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>المفتاح</th><th>القيمة</th></tr>";
    foreach ($settings as $setting) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch(PDOException $e) {
    echo "خطأ في الاتصال: " . $e->getMessage();
}
?> 