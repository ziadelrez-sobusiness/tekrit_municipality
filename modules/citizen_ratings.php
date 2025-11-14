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

// إحصائيات التقييمات
$ratings_stats = [
    'total_rated' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE citizen_rating IS NOT NULL")->fetch()['count'],
    'excellent' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE citizen_rating = 4")->fetch()['count'],
    'good' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE citizen_rating = 3")->fetch()['count'],
    'acceptable' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE citizen_rating = 2")->fetch()['count'],
    'needs_improvement' => $db->query("SELECT COUNT(*) as count FROM citizen_requests WHERE citizen_rating = 1")->fetch()['count'],
    'average_rating' => $db->query("SELECT AVG(citizen_rating) as avg FROM citizen_requests WHERE citizen_rating IS NOT NULL")->fetch()['avg']
];

// التقييمات مع التفاصيل
$ratings = $db->query("
    SELECT 
        cr.id,
        cr.tracking_number,
        cr.citizen_name,
        cr.citizen_phone,
        cr.request_type,
        cr.request_title,
        cr.citizen_rating,
        cr.citizen_feedback,
        cr.completion_date,
        d.department_name,
        u.full_name as handled_by
    FROM citizen_requests cr
    LEFT JOIN departments d ON cr.assigned_to_department_id = d.id
    LEFT JOIN users u ON cr.assigned_to_user_id = u.id
    WHERE cr.citizen_rating IS NOT NULL
    ORDER BY cr.completion_date DESC
")->fetchAll();

// إحصائيات التقييم حسب القسم
$department_ratings = $db->query("
    SELECT 
        d.department_name,
        COUNT(cr.id) as total_ratings,
        AVG(cr.citizen_rating) as avg_rating,
        SUM(CASE WHEN cr.citizen_rating = 4 THEN 1 ELSE 0 END) as excellent,
        SUM(CASE WHEN cr.citizen_rating = 3 THEN 1 ELSE 0 END) as good,
        SUM(CASE WHEN cr.citizen_rating = 2 THEN 1 ELSE 0 END) as acceptable,
        SUM(CASE WHEN cr.citizen_rating = 1 THEN 1 ELSE 0 END) as needs_improvement
    FROM citizen_requests cr
    JOIN departments d ON cr.assigned_to_department_id = d.id
    WHERE cr.citizen_rating IS NOT NULL
    GROUP BY d.id, d.department_name
    ORDER BY avg_rating DESC, total_ratings DESC
")->fetchAll();

// أحدث التعليقات
$recent_feedback = $db->query("
    SELECT 
        cr.tracking_number,
        cr.citizen_name,
        cr.request_type,
        cr.citizen_rating,
        cr.citizen_feedback,
        cr.completion_date
    FROM citizen_requests cr
    WHERE cr.citizen_feedback IS NOT NULL AND cr.citizen_feedback != ''
    ORDER BY cr.completion_date DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقييمات المواطنين - بلدية تكريت</title>
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
                    <div class="bg-yellow-600 text-white p-2 rounded-lg ml-4">⭐</div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">تقييمات المواطنين</h1>
                        <p class="text-sm text-gray-500">آراء وتقييمات المواطنين للخدمات</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="public_content_management.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        🔙 إدارة المحتوى
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
            <h1 class="text-4xl font-bold text-slate-800 mb-2">⭐ تقييمات المواطنين</h1>
            <p class="text-slate-600">آراء المواطنين وتقييماتهم لجودة الخدمات المقدمة</p>
        </div>

        <!-- Overall Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">📊</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">إجمالي التقييمات</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $ratings_stats['total_rated'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">😊</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">ممتاز</p>
                        <p class="text-3xl font-bold text-green-600"><?= $ratings_stats['excellent'] ?></p>
                        <p class="text-xs text-gray-500"><?= $ratings_stats['total_rated'] > 0 ? round(($ratings_stats['excellent'] / $ratings_stats['total_rated']) * 100, 1) : 0 ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">🙂</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">جيد</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $ratings_stats['good'] ?></p>
                        <p class="text-xs text-gray-500"><?= $ratings_stats['total_rated'] > 0 ? round(($ratings_stats['good'] / $ratings_stats['total_rated']) * 100, 1) : 0 ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">😐</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">مقبول</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $ratings_stats['acceptable'] ?></p>
                        <p class="text-xs text-gray-500"><?= $ratings_stats['total_rated'] > 0 ? round(($ratings_stats['acceptable'] / $ratings_stats['total_rated']) * 100, 1) : 0 ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-red-100 p-3 rounded-lg ml-4">
                        <span class="text-2xl">😞</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">يحتاج تحسين</p>
                        <p class="text-3xl font-bold text-red-600"><?= $ratings_stats['needs_improvement'] ?></p>
                        <p class="text-xs text-gray-500"><?= $ratings_stats['total_rated'] > 0 ? round(($ratings_stats['needs_improvement'] / $ratings_stats['total_rated']) * 100, 1) : 0 ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Rating -->
        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg p-6 mb-8">
            <div class="text-center">
                <h3 class="text-xl font-bold text-yellow-800 mb-4">متوسط التقييم العام</h3>
                <div class="flex items-center justify-center space-x-2 space-x-reverse">
                    <span class="text-4xl font-bold text-yellow-600">
                        <?= $ratings_stats['average_rating'] ? round($ratings_stats['average_rating'], 2) : 'لا يوجد' ?>
                    </span>
                    <?php if ($ratings_stats['average_rating']): ?>
                        <div class="flex">
                            <?php for($i = 1; $i <= 4; $i++): ?>
                                <span class="text-2xl <?= $i <= round($ratings_stats['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?>">⭐</span>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="text-yellow-700 mt-2">من 4 نقاط</p>
            </div>
        </div>

        <!-- Charts and Department Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Ratings Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">توزيع التقييمات</h3>
                <div class="relative h-64">
                    <canvas id="ratingsChart"></canvas>
                </div>
            </div>

            <!-- Department Performance -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold mb-4">أداء الأقسام</h3>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php foreach ($department_ratings as $dept): ?>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium"><?= htmlspecialchars($dept['department_name']) ?></span>
                                <div class="flex items-center">
                                    <span class="text-sm font-bold text-yellow-600 ml-2">
                                        <?= round($dept['avg_rating'], 1) ?>
                                    </span>
                                    <div class="flex">
                                        <?php for($i = 1; $i <= 4; $i++): ?>
                                            <span class="text-sm <?= $i <= round($dept['avg_rating']) ? 'text-yellow-400' : 'text-gray-300' ?>">⭐</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-gray-600">
                                إجمالي التقييمات: <?= $dept['total_ratings'] ?> | 
                                ممتاز: <?= $dept['excellent'] ?> | 
                                جيد: <?= $dept['good'] ?> | 
                                مقبول: <?= $dept['acceptable'] ?> | 
                                يحتاج تحسين: <?= $dept['needs_improvement'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Feedback -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-bold mb-4">💬 أحدث تعليقات المواطنين</h3>
            <div class="space-y-4">
                <?php foreach ($recent_feedback as $feedback): ?>
                    <div class="border-l-4 border-blue-400 bg-blue-50 p-4 rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-medium text-blue-900"><?= htmlspecialchars($feedback['citizen_name']) ?></span>
                                <span class="text-blue-600 text-sm">
                                    - <?= htmlspecialchars($feedback['request_type']) ?>
                                    (<?= htmlspecialchars($feedback['tracking_number']) ?>)
                                </span>
                            </div>
                            <div class="flex items-center">
                                <?php
                                $rating_emoji = ['', '😞', '😐', '🙂', '😊'];
                                ?>
                                <span class="text-lg ml-2"><?= $rating_emoji[$feedback['citizen_rating']] ?></span>
                                <span class="text-sm text-gray-500">
                                    <?= date('Y/m/d', strtotime($feedback['completion_date'])) ?>
                                </span>
                            </div>
                        </div>
                        <p class="text-blue-800 italic">"<?= htmlspecialchars($feedback['citizen_feedback']) ?>"</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- All Ratings Table -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">📋 جميع التقييمات</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم التتبع</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المواطن</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع الطلب</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">التقييم</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">القسم المسؤول</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ الإنجاز</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($ratings as $rating): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 text-sm font-medium text-blue-600">
                                    <?= htmlspecialchars($rating['tracking_number']) ?>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="font-medium"><?= htmlspecialchars($rating['citizen_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($rating['citizen_phone']) ?></div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="font-medium"><?= htmlspecialchars($rating['request_type']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(substr($rating['request_title'], 0, 30)) ?>...</div>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="flex items-center">
                                        <?php
                                        $rating_text = ['', 'يحتاج تحسين', 'مقبول', 'جيد', 'ممتاز'];
                                        $rating_color = ['', 'text-red-600', 'text-yellow-600', 'text-blue-600', 'text-green-600'];
                                        $rating_emoji = ['', '😞', '😐', '🙂', '😊'];
                                        $r = $rating['citizen_rating'];
                                        ?>
                                        <span class="text-lg ml-2"><?= $rating_emoji[$r] ?></span>
                                        <span class="font-medium <?= $rating_color[$r] ?>"><?= $rating_text[$r] ?></span>
                                    </div>
                                    <?php if (!empty($rating['citizen_feedback'])): ?>
                                        <div class="text-xs text-gray-600 italic mt-1">
                                            "<?= htmlspecialchars(substr($rating['citizen_feedback'], 0, 50)) ?>..."
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <?php if ($rating['department_name']): ?>
                                        <div class="font-medium"><?= htmlspecialchars($rating['department_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($rating['handled_by']): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($rating['handled_by']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-xs text-gray-500">
                                    <?= date('Y/m/d H:i', strtotime($rating['completion_date'])) ?>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <a href="view_citizen_request.php?id=<?= $rating['id'] ?>" target="_blank" class="text-blue-600 hover:text-blue-900">
                                        👁️ عرض التفاصيل
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Ratings Distribution Chart
        const ratingsCtx = document.getElementById('ratingsChart').getContext('2d');
        new Chart(ratingsCtx, {
            type: 'doughnut',
            data: {
                labels: ['ممتاز', 'جيد', 'مقبول', 'يحتاج تحسين'],
                datasets: [{
                    data: [
                        <?= $ratings_stats['excellent'] ?>,
                        <?= $ratings_stats['good'] ?>,
                        <?= $ratings_stats['acceptable'] ?>,
                        <?= $ratings_stats['needs_improvement'] ?>
                    ],
                    backgroundColor: [
                        '#10B981',  // ممتاز - أخضر
                        '#3B82F6',  // جيد - أزرق
                        '#F59E0B',  // مقبول - أصفر
                        '#EF4444'   // يحتاج تحسين - أحمر
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
    </script>
</body>
</html> 
