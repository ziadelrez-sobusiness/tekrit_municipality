<?php
// إصلاح نهائي لمشكلة income_currency_id
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');

$database = new Database();
$db = $database->getConnection();

$success = false;
$message = '';

try {
    // محاولة إضافة العمود مباشرة
    $db->exec("ALTER TABLE citizens ADD COLUMN income_currency_id INT(11) NULL");
    $success = true;
    $message = '✅ تم إضافة العمود بنجاح!';
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $success = true;
        $message = '✅ العمود موجود بالفعل!';
    } else {
        $message = '❌ خطأ: ' . $e->getMessage();
    }
}

header("Location: modules/citizens.php?fix_result=" . urlencode($message));
exit;

