<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES 'utf8mb4'");

$user = $auth->getUserInfo();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إرسال الرسائل النصية - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">📱 إرسال الرسائل النصية (SMS)</h1>
                    <p class="text-gray-600">إرسال رسائل جماعية للمواطنين</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">← العودة</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-100 rounded-lg p-6">
                <div class="text-3xl mb-2">📤</div>
                <div class="text-2xl font-bold text-blue-800">0</div>
                <div class="text-sm text-blue-600">رسائل مرسلة اليوم</div>
            </div>
            <div class="bg-green-100 rounded-lg p-6">
                <div class="text-3xl mb-2">✅</div>
                <div class="text-2xl font-bold text-green-800">0</div>
                <div class="text-sm text-green-600">رسائل تم استلامها</div>
            </div>
            <div class="bg-yellow-100 rounded-lg p-6">
                <div class="text-3xl mb-2">⏳</div>
                <div class="text-2xl font-bold text-yellow-800">0</div>
                <div class="text-sm text-yellow-600">رسائل معلقة</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4">📝 إرسال رسالة جديدة</h2>
            <form class="space-y-4">
                <div>
                    <label class="block text-sm font-bold mb-2">المستلمون</label>
                    <select class="w-full px-4 py-2 border rounded-lg" required>
                        <option value="">اختر المجموعة...</option>
                        <option value="all">جميع المواطنين</option>
                        <option value="citizens">المواطنين المسجلين فقط</option>
                        <option value="business">أصحاب المحلات</option>
                        <option value="employees">الموظفين</option>
                        <option value="custom">مجموعة مخصصة</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold mb-2">نص الرسالة</label>
                    <textarea rows="5" class="w-full px-4 py-2 border rounded-lg"
                              placeholder="اكتب نص الرسالة هنا..."
                              maxlength="160"
                              required></textarea>
                    <p class="text-sm text-gray-500 mt-1">الحد الأقصى: 160 حرف</p>
                </div>

                <div>
                    <label class="block text-sm font-bold mb-2">نوع الرسالة</label>
                    <select class="w-full px-4 py-2 border rounded-lg">
                        <option value="info">إعلامية</option>
                        <option value="alert">تنبيه</option>
                        <option value="urgent">عاجلة</option>
                        <option value="reminder">تذكير</option>
                    </select>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-bold">
                        📤 إرسال الآن
                    </button>
                    <button type="button" class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 font-bold">
                        ⏰ جدولة لاحقاً
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-4">📊 سجل الرسائل المرسلة</h2>
            <div class="text-center py-12 text-gray-500">
                <div class="text-6xl mb-4">📭</div>
                <p>لا توجد رسائل مرسلة بعد</p>
            </div>
        </div>

        <div class="bg-blue-50 border-2 border-blue-400 rounded-lg p-6 mt-6">
            <div class="flex items-start gap-4">
                <div class="text-3xl">💡</div>
                <div>
                    <h3 class="text-lg font-bold text-blue-800 mb-2">معلومة</h3>
                    <p class="text-blue-700">يمكنك أيضاً استخدام Telegram Bot لإرسال رسائل مجانية بدون حدود. اذهب إلى <a href="telegram_settings.php" class="underline font-bold">إعدادات Telegram</a> لتفعيل البوت.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
