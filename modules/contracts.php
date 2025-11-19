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
    <title>العقود والمناقصات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">📋 العقود والمناقصات</h1>
                    <p class="text-gray-600">إدارة العقود والمناقصات البلدية</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">← العودة</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-100 rounded-lg p-6">
                <div class="text-3xl mb-2">📄</div>
                <div class="text-2xl font-bold text-blue-800">0</div>
                <div class="text-sm text-blue-600">عقود نشطة</div>
            </div>
            <div class="bg-green-100 rounded-lg p-6">
                <div class="text-3xl mb-2">📢</div>
                <div class="text-2xl font-bold text-green-800">0</div>
                <div class="text-sm text-green-600">مناقصات معلنة</div>
            </div>
            <div class="bg-yellow-100 rounded-lg p-6">
                <div class="text-3xl mb-2">⏳</div>
                <div class="text-2xl font-bold text-yellow-800">0</div>
                <div class="text-sm text-yellow-600">قيد المراجعة</div>
            </div>
            <div class="bg-purple-100 rounded-lg p-6">
                <div class="text-3xl mb-2">✅</div>
                <div class="text-2xl font-bold text-purple-800">0</div>
                <div class="text-sm text-purple-600">عقود منتهية</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                    <span>📄</span> العقود
                </h2>
                <div class="space-y-3">
                    <button class="w-full text-right p-4 border-2 border-blue-200 rounded-lg hover:bg-blue-50">
                        <div class="font-bold">عقود الخدمات</div>
                        <div class="text-sm text-gray-600">صيانة، نظافة، حراسة</div>
                    </button>
                    <button class="w-full text-right p-4 border-2 border-green-200 rounded-lg hover:bg-green-50">
                        <div class="font-bold">عقود التوريد</div>
                        <div class="text-sm text-gray-600">مواد، معدات، أثاث</div>
                    </button>
                    <button class="w-full text-right p-4 border-2 border-purple-200 rounded-lg hover:bg-purple-50">
                        <div class="font-bold">عقود المشاريع</div>
                        <div class="text-sm text-gray-600">إنشاءات، تطوير، تحديث</div>
                    </button>
                    <button class="w-full text-right p-4 border-2 border-orange-200 rounded-lg hover:bg-orange-50">
                        <div class="font-bold">عقود الاستشارات</div>
                        <div class="text-sm text-gray-600">دراسات، تصاميم، إشراف</div>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                    <span>📢</span> المناقصات
                </h2>
                <div class="space-y-3">
                    <button class="w-full text-right p-4 border-2 border-red-200 rounded-lg hover:bg-red-50">
                        <div class="font-bold">مناقصات مفتوحة</div>
                        <div class="text-sm text-gray-600">متاحة للتقديم حالياً</div>
                    </button>
                    <button class="w-full text-right p-4 border-2 border-yellow-200 rounded-lg hover:bg-yellow-50">
                        <div class="font-bold">قيد التقييم</div>
                        <div class="text-sm text-gray-600">تحت الدراسة والمراجعة</div>
                    </button>
                    <button class="w-full text-right p-4 border-2 border-green-200 rounded-lg hover:bg-green-50">
                        <div class="font-bold">تم الترسية</div>
                        <div class="text-sm text-gray-600">مناقصات مرساة ومعتمدة</div>
                    </button>
                    <button class="w-full text-right p-4 border-2 border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="font-bold">ملغاة/مؤجلة</div>
                        <div class="text-sm text-gray-600">مناقصات لم تكتمل</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4">📊 آخر العقود والمناقصات</h2>
            <div class="text-center py-12 text-gray-500">
                <div class="text-6xl mb-4">📭</div>
                <p>لا توجد عقود أو مناقصات مسجلة حتى الآن</p>
                <button class="mt-4 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-bold">
                    ➕ إضافة عقد/مناقصة جديدة
                </button>
            </div>
        </div>

        <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6 text-center">
            <div class="text-4xl mb-3">🚧</div>
            <h3 class="text-xl font-bold text-yellow-800 mb-2">قيد الإنشاء</h3>
            <p class="text-yellow-700">هذه الصفحة قيد التطوير. سيتم إضافة نظام العقود والمناقصات الكامل قريباً.</p>
        </div>
    </div>
</body>
</html>
