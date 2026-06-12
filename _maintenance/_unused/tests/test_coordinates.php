<?php
// Database connection
$host = 'localhost';
$dbname = 'tekrit_municipality';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get settings (same as in contact.php)
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch(PDOException $e) {
        return $default;
    }
}

echo "<h2>اختبار قراءة الإحداثيات من قاعدة البيانات</h2>";

// Test coordinates
$lat = getSetting('contact_location_lat', '33.4384');
$lng = getSetting('contact_location_lng', '43.6793');
$name = getSetting('contact_location_name', 'بلدية تكريت');
$address = getSetting('contact_address', 'تكريت، العراق');

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr><th style='padding: 10px; background: #f0f0f0;'>المفتاح</th><th style='padding: 10px; background: #f0f0f0;'>القيمة</th></tr>";
echo "<tr><td style='padding: 10px;'>contact_location_lat</td><td style='padding: 10px;'>" . htmlspecialchars($lat) . "</td></tr>";
echo "<tr><td style='padding: 10px;'>contact_location_lng</td><td style='padding: 10px;'>" . htmlspecialchars($lng) . "</td></tr>";
echo "<tr><td style='padding: 10px;'>contact_location_name</td><td style='padding: 10px;'>" . htmlspecialchars($name) . "</td></tr>";
echo "<tr><td style='padding: 10px;'>contact_address</td><td style='padding: 10px;'>" . htmlspecialchars($address) . "</td></tr>";
echo "</table>";

// Test Google Maps URLs
$googleMapsUrl = "https://www.google.com/maps?q=" . urlencode($lat) . "," . urlencode($lng);
$embedUrl = "https://www.google.com/maps/embed/v1/place?key=AIzaSyBOti4mM-6x9WDnZIjIeyEU21OpBXqWBgw&q=" . urlencode($lat . "," . $lng) . "&zoom=15&language=ar";

echo "<h3>روابط الخرائط المُولدة:</h3>";
echo "<p><strong>رابط خرائط جوجل:</strong><br><a href='" . $googleMapsUrl . "' target='_blank'>" . $googleMapsUrl . "</a></p>";
echo "<p><strong>رابط الخريطة المدمجة:</strong><br>" . htmlspecialchars($embedUrl) . "</p>";

// Test map embed
echo "<h3>اختبار الخريطة المدمجة:</h3>";
echo "<iframe src='" . $embedUrl . "' width='100%' height='400' style='border:0;' allowfullscreen='' loading='lazy'></iframe>";

// Show all website settings
echo "<h3>جميع إعدادات الموقع:</h3>";
$stmt = $pdo->query("SELECT * FROM website_settings ORDER BY setting_key");
$allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr><th style='padding: 10px; background: #f0f0f0;'>المفتاح</th><th style='padding: 10px; background: #f0f0f0;'>القيمة</th></tr>";
foreach ($allSettings as $setting) {
    echo "<tr>";
    echo "<td style='padding: 10px;'>" . htmlspecialchars($setting['setting_key']) . "</td>";
    echo "<td style='padding: 10px;'>" . htmlspecialchars($setting['setting_value']) . "</td>";
    echo "</tr>";
}
echo "</table>";
?> 