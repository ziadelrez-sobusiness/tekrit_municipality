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
    <title>إدارة المخالفات - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">⚠️ إدارة المخالفات</h1>
                    <p class="text-gray-600">متابعة المخالفات البلدية والغرامات</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">← العودة</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-red-100 rounded-lg p-6">
                <div class="text-3xl mb-2">📋</div>
                <div class="text-2xl font-bold text-red-800">0</div>
                <div class="text-sm text-red-600">مخالفات جديدة</div>
            </div>
            <div class="bg-yellow-100 rounded-lg p-6">
                <div class="text-3xl mb-2">⏳</div>
                <div class="text-2xl font-bold text-yellow-800">0</div>
                <div class="text-sm text-yellow-600">قيد المراجعة</div>
            </div>
            <div class="bg-green-100 rounded-lg p-6">
                <div class="text-3xl mb-2">💰</div>
                <div class="text-2xl font-bold text-green-800">0</div>
                <div class="text-sm text-green-600">تم السداد</div>
            </div>
            <div class="bg-blue-100 rounded-lg p-6">
                <div class="text-3xl mb-2">💵</div>
                <div class="text-2xl font-bold text-blue-800">0.00</div>
                <div class="text-sm text-blue-600">إجمالي الغرامات</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4">أنواع المخالفات</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border-2 border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-lg">🏗️ مخالفات البناء</h3>
                            <p class="text-sm text-gray-600">بناء بدون ترخيص، تجاوز حدود</p>
                        </div>
                        <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-bold">0</span>
                    </div>
                </div>
                <div class="border-2 border-orange-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-lg">🗑️ مخالفات النظافة</h3>
                            <p class="text-sm text-gray-600">رمي النفايات، عدم النظافة</p>
                        </div>
                        <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-bold">0</span>
                    </div>
                </div>
                <div class="border-2 border-purple-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-lg">🏪 مخالفات المحلات</h3>
                            <p class="text-sm text-gray-600">ترخيص، إشغال، نظافة</p>
                        </div>
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-bold">0</span>
                    </div>
                </div>
                <div class="border-2 border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-lg">🚗 مخالفات المرور</h3>
                            <p class="text-sm text-gray-600">إشغال طريق، وقوف خاطئ</p>
                        </div>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold">0</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6 text-center">
            <div class="text-4xl mb-3">🚧</div>
            <h3 class="text-xl font-bold text-yellow-800 mb-2">قيد الإنشاء</h3>
            <p class="text-yellow-700">هذه الصفحة قيد التطوير. سيتم إضافة نظام المخالفات الكامل قريباً.</p>
        </div>
    </div>
</body>
</html>
