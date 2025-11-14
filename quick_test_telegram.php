<?php
require_once 'config/database.php';
require_once 'includes/TelegramService.php';

$database = new Database();
$db = $database->getConnection();

// جلب آخر مواطن مربوط
$stmt = $db->query("SELECT * FROM citizens_accounts WHERE telegram_chat_id IS NOT NULL LIMIT 1");
$citizen = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$citizen) {
    die("❌ لا يوجد مواطنين مربوطين");
}

echo "👤 المواطن: " . $citizen['name'] . "\n";
echo "📱 الهاتف: " . $citizen['phone'] . "\n";
echo "💬 Chat ID: " . $citizen['telegram_chat_id'] . "\n\n";

$telegramService = new TelegramService($db);

$result = $telegramService->sendWelcomeMessage(
    [
        'name' => $citizen['name'],
        'phone' => $citizen['phone'],
        'citizen_id' => $citizen['id'],
        'telegram_chat_id' => $citizen['telegram_chat_id'],
        'telegram_username' => $citizen['telegram_username']
    ],
    [
        'request_id' => 999,
        'type_name' => 'طلب اختبار',
        'tracking_number' => 'REQ-2025-TEST',
        'request_title' => 'اختبار النظام'
    ],
    $citizen['permanent_access_code']
);

echo "📊 النتيجة:\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

if ($result['success']) {
    echo "✅ نجح! تحقق من Telegram\n";
} else {
    echo "❌ فشل! الرسالة: " . $result['message'] . "\n";
}
?>

