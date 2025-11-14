<?php
/**
 * قسم إدارة السائقين - جزء من وحدة الآليات
 */
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

// جلب السائقين مع الآليات المخصصة لهم
try {
    $stmt = $db->query("
        SELECT 
            u.id, 
            u.full_name, 
            u.phone, 
            u.email,
            u.department,
            u.position,
            COUNT(v.id) as assigned_vehicles,
            GROUP_CONCAT(CONCAT(v.name, ' (', v.license_plate, ')') SEPARATOR ', ') as vehicle_list
        FROM users u 
        LEFT JOIN vehicles v ON u.id = v.assigned_driver_id 
        WHERE u.is_active = 1 
        AND (u.department IN ('النظافة', 'الصيانة', 'الهندسة', 'المياه', 'الطوارئ') 
             OR v.assigned_driver_id IS NOT NULL)
        GROUP BY u.id, u.full_name, u.phone, u.email, u.department, u.position
        ORDER BY u.full_name
    ");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات السائقين
    $stats = [
        'total_drivers' => count($drivers),
        'drivers_with_vehicles' => count(array_filter($drivers, function($d) { return $d['assigned_vehicles'] > 0; })),
        'available_drivers' => count(array_filter($drivers, function($d) { return $d['assigned_vehicles'] == 0; })),
        'total_assigned_vehicles' => array_sum(array_column($drivers, 'assigned_vehicles'))
    ];
    
} catch (PDOException $e) {
    $drivers = [];
    $stats = ['total_drivers' => 0, 'drivers_with_vehicles' => 0, 'available_drivers' => 0, 'total_assigned_vehicles' => 0];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة السائقين - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="min-h-screen p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-slate-800">إدارة السائقين</h1>
                <div class="flex gap-3">
                    <a href="vehicles.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        🚗 الآليات
                    </a>
                    <a href="../comprehensive_dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                </div>
            </div>
            <p class="text-slate-600 mt-2">عرض وإدارة السائقين والآليات المخصصة لهم</p>
        </div>

        <!-- إحصائيات السائقين -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">إجمالي السائقين</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $stats['total_drivers'] ?></p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">👤</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">السائقون المشغولون</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['drivers_with_vehicles'] ?></p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">🚗</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">السائقون المتاحون</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $stats['available_drivers'] ?></p>
                    </div>
                    <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">⏱️</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">الآليات المخصصة</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $stats['total_assigned_vehicles'] ?></p>
                    </div>
                    <div class="bg-purple-100 text-purple-600 p-3 rounded-full">🔗</div>
                </div>
            </div>
        </div>

        <!-- قائمة السائقين -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">قائمة السائقين والآليات المخصصة</h2>
                <p class="text-sm text-gray-600 mt-1">لإضافة أو تعديل سائق جديد، يرجى الذهاب إلى وحدة الموارد البشرية</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">اسم السائق</th>
                            <th class="px-6 py-3">رقم الهاتف</th>
                            <th class="px-6 py-3">القسم</th>
                            <th class="px-6 py-3">المنصب</th>
                            <th class="px-6 py-3">عدد الآليات</th>
                            <th class="px-6 py-3">الآليات المخصصة</th>
                            <th class="px-6 py-3">الحالة</th>
                            <th class="px-6 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $driver): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($driver['full_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($driver['email'] ?? 'لا يوجد إيميل') ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= htmlspecialchars($driver['phone'] ?? 'غير محدد') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($driver['department']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= htmlspecialchars($driver['position'] ?? 'غير محدد') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded font-semibold
                                        <?= $driver['assigned_vehicles'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= $driver['assigned_vehicles'] ?> آلية
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <?php if ($driver['vehicle_list']): ?>
                                            <p class="text-sm text-gray-700 truncate" title="<?= htmlspecialchars($driver['vehicle_list']) ?>">
                                                <?= htmlspecialchars($driver['vehicle_list']) ?>
                                            </p>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">لا توجد آليات مخصصة</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded font-medium
                                        <?= $driver['assigned_vehicles'] > 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                        <?= $driver['assigned_vehicles'] > 0 ? 'مشغول' : 'متاح' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <a href="vehicles.php?driver=<?= $driver['id'] ?>" 
                                           class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-200 transition">
                                            عرض الآليات
                                        </a>
                                        <a href="hr.php?edit=<?= $driver['id'] ?>" 
                                           class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs hover:bg-green-200 transition">
                                            تعديل البيانات
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($drivers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <div class="text-6xl mb-4">👤</div>
                                        <p class="text-lg font-medium mb-2">لا يوجد سائقون في النظام</p>
                                        <p class="text-sm">يمكنك إضافة موظفين جدد من وحدة الموارد البشرية</p>
                                        <a href="hr.php" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                            إضافة موظف جديد
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ملاحظات مهمة -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-800 mb-3">📋 ملاحظات مهمة:</h3>
            <ul class="space-y-2 text-blue-700">
                <li>• لإضافة سائق جديد، قم بإضافة موظف جديد في وحدة <strong>الموارد البشرية</strong></li>
                <li>• لتخصيص آلية لسائق، استخدم نموذج "إضافة آلية" أو "تعديل آلية" في وحدة الآليات</li>
                <li>• السائقون المتاحون هم الذين لا توجد لهم آليات مخصصة حالياً</li>
                <li>• يمكن للسائق الواحد أن يكون مسؤولاً عن عدة آليات</li>
                <li>• لتعديل بيانات السائق (الهاتف، القسم، المنصب)، استخدم وحدة الموارد البشرية</li>
            </ul>
        </div>

        <!-- روابط سريعة -->
        <div class="mt-6 flex flex-wrap gap-4">
            <a href="vehicles.php" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                🚗 إدارة الآليات
            </a>
            <a href="hr.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                👥 إدارة الموظفين
            </a>
            <a href="../comprehensive_dashboard.php" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition flex items-center gap-2">
                🏠 لوحة التحكم
            </a>
        </div>
    </div>
</body>
</html> 
