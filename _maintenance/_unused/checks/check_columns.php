<?php
$db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h2>Columns in citizen_requests:</h2>";
$stmt = $db->query("SHOW COLUMNS FROM citizen_requests");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "<br>";
}
?>

