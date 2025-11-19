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
    <title>إدارة الصيانة الشاملة - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">🔧 إدارة الصيانة الشاملة</h1>
                    <p class="text-gray-600">متابعة أعمال الصيانة والإصلاحات</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">← العودة</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-blue-100 rounded-lg p-6 text-center">
                <div class="text-5xl mb-3">📋</div>
                <div class="text-3xl font-bold text-blue-800">0</div>
                <div class="text-sm text-blue-600">طلبات الصيانة الجديدة</div>
            </div>
            <div class="bg-yellow-100 rounded-lg p-6 text-center">
                <div class="text-5xl mb-3">⚙️</div>
                <div class="text-3xl font-bold text-yellow-800">0</div>
                <div class="text-sm text-yellow-600">أعمال قيد التنفيذ</div>
            </div>
            <div class="bg-green-100 rounded-lg p-6 text-center">
                <div class="text-5xl mb-3">✅</div>
                <div class="text-3xl font-bold text-green-800">0</div>
                <div class="text-sm text-green-600">أعمال مكتملة</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-4">أقسام الصيانة</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="border-2 border-blue-200 rounded-lg p-4 hover:bg-blue-50 cursor-pointer">
                    <div class="text-3xl mb-2">🚗</div>
                    <h3 class="font-bold text-lg">صيانة الآليات</h3>
                    <p class="text-sm text-gray-600">سيارات، شاحنات، معدات ثقيلة</p>
                </div>
                <div class="border-2 border-green-200 rounded-lg p-4 hover:bg-green-50 cursor-pointer">
                    <div class="text-3xl mb-2">🏢</div>
                    <h3 class="font-bold text-lg">صيانة المباني</h3>
                    <p class="text-sm text-gray-600">مكاتب، مرافق عامة، منشآت</p>
                </div>
                <div class="border-2 border-yellow-200 rounded-lg p-4 hover:bg-yellow-50 cursor-pointer">
                    <div class="text-3xl mb-2">💡</div>
                    <h3 class="font-bold text-lg">الكهرباء والإنارة</h3>
                    <p class="text-sm text-gray-600">إنارة عامة، لوحات كهربائية</p>
                </div>
                <div class="border-2 border-purple-200 rounded-lg p-4 hover:bg-purple-50 cursor-pointer">
                    <div class="text-3xl mb-2">🚰</div>
                    <h3 class="font-bold text-lg">المياه والصرف الصحي</h3>
                    <p class="text-sm text-gray-600">شبكات المياه، معالجة الصرف</p>
                </div>
                <div class="border-2 border-red-200 rounded-lg p-4 hover:bg-red-50 cursor-pointer">
                    <div class="text-3xl mb-2">🛣️</div>
                    <h3 class="font-bold text-lg">الطرق والأرصفة</h3>
                    <p class="text-sm text-gray-600">تعبيد، ترميم، حفريات</p>
                </div>
                <div class="border-2 border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer">
                    <div class="text-3xl mb-2">🌳</div>
                    <h3 class="font-bold text-lg">الحدائق والتشجير</h3>
                    <p class="text-sm text-gray-600">تقليم، ري، زراعة</p>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6 mt-6 text-center">
            <div class="text-4xl mb-3">🚧</div>
            <h3 class="text-xl font-bold text-yellow-800 mb-2">قيد الإنشاء</h3>
            <p class="text-yellow-700">هذه الصفحة قيد التطوير. سيتم إضافة جميع الوظائف قريباً.</p>
            <p class="text-sm text-yellow-600 mt-2">يمكنك البدء بإدخال البيانات وسيتم حفظها تلقائياً.</p>
        </div>
    </div>
</body>
</html>
