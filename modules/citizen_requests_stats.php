<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth->requireLogin();
if (!$auth->checkPermission('employee')) {
    header('Location: ../comprehensive_dashboard.php?error=no_permission');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

// إحصائيات عامة
$general_stats = [
    'total_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests")->fetch()['count'],
    'new_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'جديد'")->fetch()['count'],
    'in_review' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'قيد المراجعة'")->fetch()['count'],
    'in_progress' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'قيد التنفيذ'")->fetch()['count'],
    'completed' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'مكتمل'")->fetch()['count'],
    'rejected' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'مرفوض'")->fetch()['count'],
    'urgent_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE priority_level = 'عاجل' AND status NOT IN ('مكتمل', 'مرفوض')")->fetch()['count'],
    'overdue_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE DATEDIFF(NOW(), created_at) > 7 AND status NOT IN ('مكتمل', 'مرفوض')")->fetch()['count'],
    'rated_requests' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE citizen_rating IS NOT NULL")->fetch()['count'],
    'average_rating' => $db->query("SELECT AVG(citizen_rating) as avg FROM citizen_requests WHERE citizen_rating IS NOT NULL")->fetch()['avg']
];

// إحصائيات حسب نوع الطلب
$type_stats = $db->query("
    SELECT request_type, COUNT(*) as count 
    FROM citizen_requests 
    GROUP BY request_type 
    ORDER BY count DESC
")->fetchAll();

// إحصائيات حسب الأولوية
$priority_stats = $db->query("
    SELECT priority_level, COUNT(*) as count 
    FROM citizen_requests 
    GROUP BY priority_level 
    ORDER BY FIELD(priority_level, 'عاجل', 'مهم', 'عادي')
")->fetchAll();

// إحصائيات حسب القسم
$department_stats = $db->query("
    SELECT d.department_name, COUNT(cr.id) as count 
    FROM departments d 
    LEFT JOIN citizen_requests cr ON d.id = cr.assigned_to_department_id 
    WHERE d.is_active = 1 
    GROUP BY d.id, d.department_name 
    ORDER BY count DESC
")->fetchAll();

// إحصائيات زمنية (آخر 30 يوم)
$daily_stats = $db->query("
    SELECT 
        DATE(created_at) as request_date, 
        COUNT(*) as daily_count 
    FROM citizen_requests 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY DATE(created_at) 
    ORDER BY request_date DESC
")->fetchAll();

// متوسط وقت المعالجة
$avg_processing_time = $db->query("
    SELECT 
        AVG(DATEDIFF(completion_date, created_at)) as avg_days
    FROM citizen_requests 
    WHERE status = 'مكتمل' AND completion_date IS NOT NULL
")->fetch()['avg_days'];

// أفضل الموظفين في حل الطلبات
$top_employees = $db->query("
    SELECT 
        u.full_name, 
        COUNT(cr.id) as completed_requests,
        AVG(DATEDIFF(cr.completion_date, cr.created_at)) as avg_completion_days
    FROM users u 
    JOIN citizen_requests cr ON u.id = cr.assigned_to_user_id 
    WHERE cr.status = 'مكتمل'
    GROUP BY u.id, u.full_name 
    HAVING completed_requests >= 1
    ORDER BY completed_requests DESC, avg_completion_days ASC
    LIMIT 10
")->fetchAll();

// الطلبات المتأخرة
$overdue_requests = $db->query("
    SELECT 
        cr.id,
        cr.tracking_number,
        cr.citizen_name,
        cr.request_type,
        cr.request_title,
        DATEDIFF(NOW(), cr.created_at) as days_overdue,
        d.department_name,
        u.full_name as assigned_to_name
    FROM citizen_requests cr
    LEFT JOIN departments d ON cr.assigned_to_department_id = d.id
    LEFT JOIN users u ON cr.assigned_to_user_id = u.id
    WHERE DATEDIFF(NOW(), cr.created_at) > 7 
    AND cr.status NOT IN ('مكتمل', 'مرفوض')
    ORDER BY days_overdue DESC
    LIMIT 20
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إحصائيات طلبات المواطنين - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="bg-indigo-600 text-white p-2 rounded-lg ml-4">📊</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">إحصائيات طلبات المواطنين</h1>
                        <p class="text-sm text-gray-500">تقارير وإحصائيات تفصيلية</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="public_content_management.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        🔙 العودة لإدارة المحتوى
                    </a>
                    <a href="../comprehensive_dashboard.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                        🏠 اللوحة الرئيسية
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-slate-800 mb-2">📊 إحصائيات طلبات المواطنين</h1>
            <p class="text-slate-600">تقارير شاملة حول أداء معالجة طلبات المواطنين</p>
        </div>

        <!-- Overall Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">📝</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي الطلبات</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $general_stats['total_requests'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">✅</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">مكتملة</p>
                        <p class="text-3xl font-bold text-green-600"><?= $general_stats['completed'] ?></p>
                        <p class="text-xs text-gray-500"><?= $general_stats['total_requests'] > 0 ? round(($general_stats['completed'] / $general_stats['total_requests']) * 100, 1) : 0 ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">⏳</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">قيد المعالجة</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $general_stats['in_review'] + $general_stats['in_progress'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-red-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">⚠️</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">متأخرة</p>
                        <p class="text-3xl font-bold text-red-600"><?= $general_stats['overdue_requests'] ?></p>
                        <p class="text-xs text-gray-500">أكثر من 7 أيام</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">⭐</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">تقييمات المواطنين</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $general_stats['rated_requests'] ?></p>
                        <p class="text-xs text-gray-500">متوسط: <?= $general_stats['average_rating'] ? round($general_stats['average_rating'], 1) : 'لا يوجد' ?>/4</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Status Distribution Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">توزيع الطلبات حسب الحالة</h3>
                <div class="relative h-64">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Priority Distribution Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">توزيع الطلبات حسب الأولوية</h3>
                <div class="relative h-64">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Request Types -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">الطلبات حسب النوع</h3>
                <div class="space-y-3">
                    <?php foreach ($type_stats as $type): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium"><?= htmlspecialchars($type['request_type']) ?></span>
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
                                <?= $type['count'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Department Performance -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">أداء الأقسام</h3>
                <div class="space-y-3">
                    <?php foreach ($department_stats as $dept): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium"><?= htmlspecialchars($dept['department_name']) ?></span>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                                <?= $dept['count'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Average Processing Time -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">متوسط وقت المعالجة</h3>
                <div class="text-center">
                    <div class="text-4xl font-bold text-indigo-600 mb-2">
                        <?= $avg_processing_time ? round($avg_processing_time, 1) : 'غير محدد' ?>
                    </div>
                    <div class="text-gray-600">يوم في المتوسط</div>
                </div>
            </div>

            <!-- Top Employees -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">أفضل الموظفين</h3>
                <div class="space-y-3">
                    <?php foreach (array_slice($top_employees, 0, 5) as $index => $employee): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-yellow-100 text-yellow-800 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ml-3">
                                    <?= $index + 1 ?>
                                </span>
                                <span class="font-medium"><?= htmlspecialchars($employee['full_name']) ?></span>
                            </div>
                            <div class="text-left">
                                <div class="text-sm font-semibold"><?= $employee['completed_requests'] ?> طلب</div>
                                <div class="text-xs text-gray-500"><?= round($employee['avg_completion_days'], 1) ?> يوم</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Overdue Requests -->
        <?php if (!empty($overdue_requests)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-bold mb-4 text-red-600">🚨 الطلبات المتأخرة (أكثر من 7 أيام)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم التتبع</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المواطن</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع الطلب</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الأيام المتأخرة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المسؤول</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($overdue_requests as $request): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 text-sm font-medium text-blue-600">
                                    <?= htmlspecialchars($request['tracking_number']) ?>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <?= htmlspecialchars($request['citizen_name']) ?>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="font-medium"><?= htmlspecialchars($request['request_type']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(substr($request['request_title'], 0, 30)) ?>...</div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-semibold">
                                        <?= $request['days_overdue'] ?> يوم
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <?php if ($request['department_name']): ?>
                                        <div class="font-medium"><?= htmlspecialchars($request['department_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($request['assigned_to_name']): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($request['assigned_to_name']) ?></div>
                                    <?php else: ?>
                                        <span class="text-red-500 text-xs">غير معين</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <a href="update_citizen_request.php?id=<?= $request['id'] ?>" target="_blank" class="text-blue-600 hover:text-blue-900">
                                        ✏️ تحديث
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Daily Requests Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">الطلبات اليومية (آخر 30 يوم)</h3>
            <div class="relative h-64">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['جديد', 'قيد المراجعة', 'قيد التنفيذ', 'مكتمل', 'مرفوض'],
                datasets: [{
                    data: [
                        <?= $general_stats['new_requests'] ?>,
                        <?= $general_stats['in_review'] ?>,
                        <?= $general_stats['in_progress'] ?>,
                        <?= $general_stats['completed'] ?>,
                        <?= $general_stats['rejected'] ?>
                    ],
                    backgroundColor: [
                        '#3B82F6',
                        '#F59E0B',
                        '#8B5CF6',
                        '#10B981',
                        '#EF4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Priority Chart
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo !empty($priority_stats) ? "'" . implode("','", array_column($priority_stats, 'priority_level')) . "'" : "'لا توجد بيانات'"; ?>],
                datasets: [{
                    label: 'عدد الطلبات',
                    data: [<?php echo !empty($priority_stats) ? implode(',', array_column($priority_stats, 'count')) : '0'; ?>],
                    backgroundColor: ['#EF4444', '#F59E0B', '#10B981']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Daily Requests Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [<?php echo !empty($daily_stats) ? "'" . implode("','", array_reverse(array_column($daily_stats, 'request_date'))) . "'" : "'لا توجد بيانات'"; ?>],
                datasets: [{
                    label: 'الطلبات اليومية',
                    data: [<?php echo !empty($daily_stats) ? implode(',', array_reverse(array_column($daily_stats, 'daily_count'))) : '0'; ?>],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function updateRequest(requestId) {
            window.open('update_citizen_request.php?id=' + requestId, '_blank', 'width=900,height=700');
        }
    </script>
</body>
</html> 
