<?php
/**
 * Cron Job لجلب وتحديث روابط مهمة تلقائياً
 * يمكن تشغيله عبر cron أو scheduled task
 * 
 * مثال cron:
 * 0 * * * * php /path/to/tekrit_municipality/cron/fetch_important_links.php
 * (كل ساعة)
 */

// تحديد المسار
define('BASE_DIR', dirname(__DIR__));
require_once BASE_DIR . '/config/database.php';
require_once BASE_DIR . '/includes/ImportantLinksFetcher.php';

// إعداد قاعدة البيانات
$database = new Database();
$db = $database->getConnection();

// إنشاء fetcher
$fetcher = new ImportantLinksFetcher($db);

// جلب وتحديث جميع المصادر الجاهزة
echo "بدء عملية جلب وتحديث روابط مهمة...\n";
$results = $fetcher->updateAllReadySources();

// عرض النتائج
foreach ($results as $sourceId => $result) {
    if ($result['success']) {
        echo "✓ المصدر #$sourceId: تم جلب " . $result['items_fetched'] . " عنصر، استيراد " . $result['items_imported'] . "، تحديث " . $result['items_updated'] . "\n";
    } else {
        echo "✗ المصدر #$sourceId: فشل - " . ($result['error'] ?? 'خطأ غير معروف') . "\n";
    }
}

echo "اكتملت العملية.\n";

