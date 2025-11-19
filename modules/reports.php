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
    <title>التقارير الموحدة - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">

        <!-- العنوان -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">📊 التقارير الموحدة</h1>
                    <p class="text-gray-600">تقارير شاملة لجميع أنظمة البلدية</p>
                </div>
                <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    ← العودة
                </a>
            </div>
        </div>

        <!-- التقارير المالية -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                <span>💰</span> التقارير المالية
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button class="text-right p-4 border-2 border-blue-200 rounded-lg hover:bg-blue-50 transition">
                    <div class="text-3xl mb-2">📈</div>
                    <div class="font-bold">تقرير الإيرادات والمصروفات</div>
                    <div class="text-sm text-gray-600">تقرير شامل حسب الفترة الزمنية</div>
                </button>

                <button class="text-right p-4 border-2 border-green-200 rounded-lg hover:bg-green-50 transition">
                    <div class="text-3xl mb-2">💵</div>
                    <div class="font-bold">تقرير الميزانيات</div>
                    <div class="text-sm text-gray-600">المخصص/المصروف/المتبقي</div>
                </button>

                <button class="text-right p-4 border-2 border-purple-200 rounded-lg hover:bg-purple-50 transition">
                    <div class="text-3xl mb-2">📄</div>
                    <div class="font-bold">تقرير الفواتير</div>
                    <div class="text-sm text-gray-600">حالة السداد والموردين</div>
                </button>

                <button class="text-right p-4 border-2 border-yellow-200 rounded-lg hover:bg-yellow-50 transition">
                    <div class="text-3xl mb-2">🧾</div>
                    <div class="font-bold">تقرير الجباية</div>
                    <div class="text-sm text-gray-600">المستحقات والمتحصلات</div>
                </button>

                <button class="text-right p-4 border-2 border-red-200 rounded-lg hover:bg-red-50 transition">
                    <div class="text-3xl mb-2">💸</div>
                    <div class="font-bold">تقرير التدفقات النقدية</div>
                    <div class="text-sm text-gray-600">الوضع المالي العام</div>
                </button>

                <button class="text-right p-4 border-2 border-indigo-200 rounded-lg hover:bg-indigo-50 transition">
                    <div class="text-3xl mb-2">🏦</div>
                    <div class="font-bold">تقرير العملات</div>
                    <div class="text-sm text-gray-600">أرصدة جميع العملات</div>
                </button>
            </div>
        </div>

        <!-- التقارير الإدارية -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                <span>👥</span> التقارير الإدارية
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button class="text-right p-4 border-2 border-blue-200 rounded-lg hover:bg-blue-50 transition">
                    <div class="text-3xl mb-2">👔</div>
                    <div class="font-bold">تقرير الموظفين</div>
                    <div class="text-sm text-gray-600">إحصائيات الموارد البشرية</div>
                </button>

                <button class="text-right p-4 border-2 border-green-200 rounded-lg hover:bg-green-50 transition">
                    <div class="text-3xl mb-2">📦</div>
                    <div class="font-bold">تقرير المخزون</div>
                    <div class="text-sm text-gray-600">حالة الأصناف والمستودعات</div>
                </button>

                <button class="text-right p-4 border-2 border-purple-200 rounded-lg hover:bg-purple-50 transition">
                    <div class="text-3xl mb-2">🚚</div>
                    <div class="font-bold">تقرير الآليات</div>
                    <div class="text-sm text-gray-600">حالة المركبات والصيانة</div>
                </button>
            </div>
        </div>

        <!-- التقارير الخدمية -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                <span>🏗️</span> التقارير الخدمية
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button class="text-right p-4 border-2 border-blue-200 rounded-lg hover:bg-blue-50 transition">
                    <div class="text-3xl mb-2">🏗️</div>
                    <div class="font-bold">تقرير المشاريع</div>
                    <div class="text-sm text-gray-600">حالة التنفيذ والإنجاز</div>
                </button>

                <button class="text-right p-4 border-2 border-green-200 rounded-lg hover:bg-green-50 transition">
                    <div class="text-3xl mb-2">📢</div>
                    <div class="font-bold">تقرير الشكاوى</div>
                    <div class="text-sm text-gray-600">الواردة والمعالجة</div>
                </button>

                <button class="text-right p-4 border-2 border-purple-200 rounded-lg hover:bg-purple-50 transition">
                    <div class="text-3xl mb-2">🗑️</div>
                    <div class="font-bold">تقرير النفايات</div>
                    <div class="text-sm text-gray-600">الجمع والمعالجة</div>
                </button>

                <button class="text-right p-4 border-2 border-yellow-200 rounded-lg hover:bg-yellow-50 transition">
                    <div class="text-3xl mb-2">🔧</div>
                    <div class="font-bold">تقرير الصيانة</div>
                    <div class="text-sm text-gray-600">أعمال الصيانة والإصلاح</div>
                </button>

                <button class="text-right p-4 border-2 border-red-200 rounded-lg hover:bg-red-50 transition">
                    <div class="text-3xl mb-2">⚠️</div>
                    <div class="font-bold">تقرير المخالفات</div>
                    <div class="text-sm text-gray-600">الأنواع والغرامات</div>
                </button>

                <button class="text-right p-4 border-2 border-indigo-200 rounded-lg hover:bg-indigo-50 transition">
                    <div class="text-3xl mb-2">🏛️</div>
                    <div class="font-bold">تقرير رخص البناء</div>
                    <div class="text-sm text-gray-600">الطلبات والتراخيص</div>
                </button>
            </div>
        </div>

        <!-- أدوات التقارير -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2">
                <span>🛠️</span> أدوات التقارير
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <button class="text-center p-6 border-2 border-blue-200 rounded-lg hover:bg-blue-50 transition">
                    <div class="text-5xl mb-2">📅</div>
                    <div class="font-bold">تقارير مخصصة</div>
                    <div class="text-sm text-gray-600">حدد الفترة والبيانات</div>
                </button>

                <button class="text-center p-6 border-2 border-green-200 rounded-lg hover:bg-green-50 transition">
                    <div class="text-5xl mb-2">📊</div>
                    <div class="font-bold">الرسوم البيانية</div>
                    <div class="text-sm text-gray-600">تحليل مرئي للبيانات</div>
                </button>

                <button class="text-center p-6 border-2 border-purple-200 rounded-lg hover:bg-purple-50 transition">
                    <div class="text-5xl mb-2">📄</div>
                    <div class="font-bold">تصدير PDF</div>
                    <div class="text-sm text-gray-600">طباعة التقارير</div>
                </button>

                <button class="text-center p-6 border-2 border-yellow-200 rounded-lg hover:bg-yellow-50 transition">
                    <div class="text-5xl mb-2">📊</div>
                    <div class="font-bold">تصدير Excel</div>
                    <div class="text-sm text-gray-600">تحليل متقدم</div>
                </button>
            </div>
        </div>

        <!-- ملاحظة -->
        <div class="bg-blue-50 border-2 border-blue-400 rounded-lg p-6 mt-6">
            <div class="flex items-start gap-4">
                <div class="text-4xl">💡</div>
                <div>
                    <h3 class="text-lg font-bold text-blue-800 mb-2">معلومة</h3>
                    <p class="text-blue-700">جميع التقارير متاحة للتصدير بصيغة PDF و Excel. يمكنك أيضاً جدولة التقارير الدورية ليتم إرسالها تلقائياً عبر البريد الإلكتروني.</p>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
