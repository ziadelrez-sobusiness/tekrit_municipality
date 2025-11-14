<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/currency_formatter.php';

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$project_id = $_GET['id'] ?? null;
if (!$project_id) {
    header('Location: projects.php');
    exit();
}

// جلب المشروع من الجدول الموحد
$stmt = $db->prepare("
    SELECT p.*,
           bc.currency_symbol as budget_currency_symbol,
           bc.currency_code as budget_currency_code,
           cc.currency_symbol as contributions_currency_symbol,
           cc.currency_code as contributions_currency_code,
           a.name as association_name
    FROM projects p
    LEFT JOIN currencies bc ON p.budget_currency_id = bc.id
    LEFT JOIN currencies cc ON p.contributions_currency_id = cc.id
    LEFT JOIN associations a ON p.association_id = a.id
    WHERE p.id = ? AND p.is_public = 1
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: projects.php?error=not_found');
    exit();
}

// إضافة أسماء بديلة للتوافق مع الكود القديم
$project['project_name'] = $project['project_name'] ?? '';
$project['project_description'] = $project['description'] ?? '';
$project['project_location'] = $project['location'] ?? '';
$project['project_cost'] = $project['budget'] ?? 0;
$project['project_status'] = $project['status'] ?? '';
$project['completion_percentage'] = $project['progress_percentage'] ?? 0;

function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

// جلب معلومات العملات
$currency_stmt = $db->prepare("SELECT * FROM currencies ORDER BY currency_name");
$currency_stmt->execute();
$currencies = $currency_stmt->fetchAll();

$currency_map = [];
foreach ($currencies as $currency) {
    $currency_map[$currency['id']] = $currency;
}

$default_currency_id = getSetting('default_currency_id', 1);
$default_currency = $currency_map[$default_currency_id] ?? $currencies[0] ?? null;

// جلب معلومات التمويل - استخدام الأسماء الصحيحة للأعمدة
$base_cost = $project['project_base_cost'] ?? $project['project_cost'] ?? 0;
$base_currency_id = $project['project_base_cost_currency_id'] ?? $default_currency_id;
$municipality_amount = $project['municipality_contribution_amount'] ?? $project['municipality_contribution'] ?? 0;
$municipality_currency_id = $project['municipality_contribution_currency_id'] ?? $default_currency_id;
$donor_amount = $project['donor_contribution_amount'] ?? $project['donor_contribution'] ?? 0;
$donor_currency_id = $project['donor_contribution_currency_id'] ?? $default_currency_id;
$contributor_amount = $project['donors_contribution_amount'] ?? $project['contributors_contribution'] ?? 0;
$contributor_currency_id = $project['donors_contribution_currency_id'] ?? $default_currency_id;
$donor_name = $project['donor_organization'] ?? '';

// حساب التحويل مع أسعار صرف افتراضية
function convertCurrency($amount, $from_id, $to_id, $currency_map) {
    // إذا كان المبلغ صفر أو العملتان متشابهتان، إرجاع المبلغ كما هو
    if ($amount == 0 || $from_id == $to_id) return $amount;
    
    // التحقق من وجود العملات
    $from = $currency_map[$from_id] ?? null;
    $to = $currency_map[$to_id] ?? null;
    
    // إذا لم توجد إحدى العملات، إرجاع المبلغ كما هو
    if (!$from || !$to) return $amount;
    
    // أسعار صرف افتراضية مقابل الدينار العراقي (IQD)
    $default_rates = [
        'IQD' => 1,           // دينار عراقي (العملة الأساسية)
        'USD' => 1320,        // دولار أمريكي
        'EUR' => 1450,        // يورو
        'LBP' => 0.88,        // ليرة لبنانية
        'SAR' => 352,         // ريال سعودي
        'JOD' => 1863,        // دينار أردني
        'TRY' => 39,          // ليرة تركية
    ];
    
    // الحصول على رموز العملات
    $from_code = $from['currency_code'] ?? 'IQD';
    $to_code = $to['currency_code'] ?? 'IQD';
    
    // الحصول على أسعار الصرف
    $from_rate = $default_rates[$from_code] ?? 1;
    $to_rate = $default_rates[$to_code] ?? 1;
    
    // التحويل: تحويل إلى IQD أولاً ثم إلى العملة المطلوبة
    $amount_in_iqd = $amount * $from_rate;
    $converted_amount = $amount_in_iqd / $to_rate;
    
    return $converted_amount;
}

$base_currency = $currency_map[$base_currency_id] ?? $default_currency;
$municipality_converted = convertCurrency($municipality_amount, $municipality_currency_id, $base_currency_id, $currency_map);
$donor_converted = convertCurrency($donor_amount, $donor_currency_id, $base_currency_id, $currency_map);
$contributor_converted = convertCurrency($contributor_amount, $contributor_currency_id, $base_currency_id, $currency_map);

$total_funding = $municipality_converted + $donor_converted + $contributor_converted;
$remaining_amount = $base_cost - $total_funding;
$funding_percentage = $base_cost > 0 ? ($total_funding / $base_cost) * 100 : 0;

function formatAmount($amount, $currency) {
    return number_format($amount, 0) . ' ' . ($currency['currency_symbol'] ?? $currency['currency_name']);
}

$site_title = getSetting('site_title', 'بلدية تكريت');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['project_name'] ?? 'مشروع') ?> - <?= htmlspecialchars($site_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <header class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <nav class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($site_title) ?></h1>
                    <p class="text-sm text-gray-500">تفاصيل المشروع</p>
                </div>
                <div class="space-x-4 space-x-reverse">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600">الرئيسية</a>
                    <a href="projects.php" class="text-blue-600 font-medium">المشاريع</a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600">الأخبار</a>
                </div>
            </nav>
        </div>
    </header>

    <div class="max-w-6xl mx-auto py-8 px-4">
        <!-- Project Header -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-8">
                <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($project['project_name'] ?? 'مشروع بدون اسم') ?></h1>
                <p class="text-blue-100 text-lg"><?= htmlspecialchars($project['project_status'] ?? 'غير محدد') ?></p>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="flex items-center">
                            <span class="text-2xl ml-3">📅</span>
                            <div>
                                <p class="text-blue-100 text-sm">تاريخ البدء</p>
                                <p class="font-semibold"><?= date('Y/m/d', strtotime($project['start_date'] ?? $project['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="flex items-center">
                            <span class="text-2xl ml-3">💰</span>
                            <div>
                                <p class="text-blue-100 text-sm">التكلفة</p>
                                <p class="font-semibold"><?= formatProjectCost($project, $db) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="flex items-center">
                            <span class="text-2xl ml-3">📍</span>
                            <div>
                                <p class="text-blue-100 text-sm">الموقع</p>
                                <p class="font-semibold"><?= htmlspecialchars($project['project_location'] ?? 'غير محدد') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <?php 
                $completion_percentage = $project['completion_percentage'] ?? 0;
                if ($completion_percentage > 0): 
                ?>
                <div class="mt-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-blue-100">نسبة الإنجاز</span>
                        <span class="font-bold"><?= $completion_percentage ?>%</span>
                    </div>
                    <div class="w-full bg-white bg-opacity-20 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full transition-all duration-300" style="width: <?= $completion_percentage ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Project Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <!-- Description -->
                <div class="bg-white rounded-lg shadow p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">📝 وصف المشروع</h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($project['project_description'] ?? 'لا يوجد وصف متاح')) ?></p>
                </div>

                <!-- Project Goal -->
                <?php if (!empty($project['project_goal'])): ?>
                <div class="bg-white rounded-lg shadow p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">🎯 هدف المشروع</h2>
                    <div class="bg-blue-50 border-l-4 border-blue-400 rounded-lg p-6">
                        <p class="text-blue-800 leading-relaxed"><?= nl2br(htmlspecialchars($project['project_goal'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Funding Information -->
                <div class="bg-white rounded-lg shadow p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">💰 معلومات التمويل</h2>
                    
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-3xl text-blue-600 mb-2">💵</div>
                                <h4 class="text-lg font-bold text-gray-900 mb-1">التكلفة الإجمالية</h4>
                                <p class="text-2xl font-bold text-blue-600"><?= formatAmount($base_cost, $base_currency) ?></p>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-3xl text-green-600 mb-2">📈</div>
                                <h4 class="text-lg font-bold text-gray-900 mb-1">التمويل المتوفر</h4>
                                <p class="text-2xl font-bold text-green-600"><?= formatAmount($total_funding, $base_currency) ?></p>
                                <div class="text-sm text-gray-600 mt-1"><?= number_format($funding_percentage, 1) ?>% من التكلفة</div>
                            </div>
                            
                            <div class="text-center">
                                <div class="text-3xl <?= $remaining_amount > 0 ? 'text-orange-600' : 'text-green-600' ?> mb-2">
                                    <?= $remaining_amount > 0 ? '⏳' : '✅' ?>
                                </div>
                                <h4 class="text-lg font-bold text-gray-900 mb-1">المبلغ المتبقي</h4>
                                <p class="text-2xl font-bold <?= $remaining_amount > 0 ? 'text-orange-600' : 'text-green-600' ?>">
                                    <?= formatAmount(abs($remaining_amount), $base_currency) ?>
                                </p>
                                <?php if ($remaining_amount <= 0): ?>
                                    <div class="text-sm text-green-600 mt-1">✨ تم تمويل المشروع بالكامل</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 font-medium">نسبة التمويل</span>
                                <span class="font-bold text-lg"><?= number_format($funding_percentage, 1) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-4">
                                <div class="bg-gradient-to-r from-green-400 to-green-600 h-4 rounded-full transition-all duration-300" 
                                     style="width: <?= min($funding_percentage, 100) ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($municipality_amount > 0 || $donor_amount > 0 || $contributor_amount > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php if ($municipality_amount > 0): ?>
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-green-100 text-green-600 p-3 rounded-full text-2xl ml-4">🏛️</div>
                                <div>
                                    <h4 class="text-lg font-bold text-green-900">مساهمة البلدية</h4>
                                    <p class="text-sm text-green-700">التمويل الحكومي</p>
                                </div>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600 mb-2">
                                    <?= formatAmount($municipality_amount, $currency_map[$municipality_currency_id]) ?>
                                </p>
                                <?php if ($municipality_currency_id != $base_currency_id): ?>
                                    <p class="text-sm text-green-700">
                                        (<?= formatAmount($municipality_converted, $base_currency) ?>)
                                    </p>
                                <?php endif; ?>
                                <div class="w-full bg-green-100 rounded-full h-2 mt-3">
                                    <div class="bg-green-500 h-2 rounded-full" 
                                         style="width: <?= $base_cost > 0 ? min(($municipality_converted / $base_cost) * 100, 100) : 0 ?>%"></div>
                                </div>
                                <p class="text-xs text-green-600 mt-1">
                                    <?= $base_cost > 0 ? number_format(($municipality_converted / $base_cost) * 100, 1) : 0 ?>% من التكلفة الإجمالية
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($donor_amount > 0): ?>
                        <div class="bg-gradient-to-br from-yellow-50 to-amber-50 border border-yellow-200 rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full text-2xl ml-4">🤝</div>
                                <div>
                                    <h4 class="text-lg font-bold text-yellow-900">الجهة المانحة</h4>
                                    <p class="text-sm text-yellow-700"><?= htmlspecialchars($donor_name ?: 'جهة مانحة') ?></p>
                                </div>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-yellow-600 mb-2">
                                    <?= formatAmount($donor_amount, $currency_map[$donor_currency_id]) ?>
                                </p>
                                <?php if ($donor_currency_id != $base_currency_id): ?>
                                    <p class="text-sm text-yellow-700">
                                        (<?= formatAmount($donor_converted, $base_currency) ?>)
                                    </p>
                                <?php endif; ?>
                                <div class="w-full bg-yellow-100 rounded-full h-2 mt-3">
                                    <div class="bg-yellow-500 h-2 rounded-full" 
                                         style="width: <?= $base_cost > 0 ? min(($donor_converted / $base_cost) * 100, 100) : 0 ?>%"></div>
                                </div>
                                <p class="text-xs text-yellow-600 mt-1">
                                    <?= $base_cost > 0 ? number_format(($donor_converted / $base_cost) * 100, 1) : 0 ?>% من التكلفة الإجمالية
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($contributor_amount > 0): ?>
                        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-purple-100 text-purple-600 p-3 rounded-full text-2xl ml-4">👥</div>
                                <div>
                                    <h4 class="text-lg font-bold text-purple-900">مساهمة المواطنين</h4>
                                    <p class="text-sm text-purple-700">التبرعات المجتمعية</p>
                                </div>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-purple-600 mb-2">
                                    <?= formatAmount($contributor_amount, $currency_map[$contributor_currency_id]) ?>
                                </p>
                                <?php if ($contributor_currency_id != $base_currency_id): ?>
                                    <p class="text-sm text-purple-700">
                                        (<?= formatAmount($contributor_converted, $base_currency) ?>)
                                    </p>
                                <?php endif; ?>
                                <div class="w-full bg-purple-100 rounded-full h-2 mt-3">
                                    <div class="bg-purple-500 h-2 rounded-full" 
                                         style="width: <?= $base_cost > 0 ? min(($contributor_converted / $base_cost) * 100, 100) : 0 ?>%"></div>
                                </div>
                                <p class="text-xs text-purple-600 mt-1">
                                    <?= $base_cost > 0 ? number_format(($contributor_converted / $base_cost) * 100, 1) : 0 ?>% من التكلفة الإجمالية
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                        <div class="text-6xl text-gray-300 mb-4">💰</div>
                        <h3 class="text-xl font-bold text-gray-600 mb-2">معلومات التمويل التفصيلية غير متوفرة</h3>
                        <p class="text-gray-500">التكلفة الإجمالية: <?= formatProjectCost($project, $db) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Beneficiaries -->
                <?php if (!empty($project['beneficiaries_description'])): ?>
                <div class="bg-white rounded-lg shadow p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">👥 المستفيدون</h2>
                    <div class="bg-blue-50 border-l-4 border-blue-400 rounded-lg p-6">
                        <p class="text-blue-800 leading-relaxed"><?= nl2br(htmlspecialchars($project['beneficiaries_description'])) ?></p>
                        <?php if ($project['beneficiaries_count'] > 0): ?>
                            <p class="text-blue-600 font-semibold mt-2">عدد المستفيدين المتوقع: <?= number_format($project['beneficiaries_count']) ?> شخص</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">ℹ️ معلومات سريعة</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">الحالة:</span>
                            <span class="font-medium"><?= htmlspecialchars($project['project_status'] ?? 'غير محدد') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">تاريخ الإضافة:</span>
                            <span class="font-medium"><?= date('Y/m/d', strtotime($project['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($project['start_date'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">تاريخ البدء:</span>
                            <span class="font-medium"><?= date('Y/m/d', strtotime($project['start_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($project['end_date'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">تاريخ الانتهاء:</span>
                            <span class="font-medium"><?= date('Y/m/d', strtotime($project['end_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($project['project_duration'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">المدة:</span>
                            <span class="font-medium"><?= htmlspecialchars($project['project_duration']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($project['contractor'])): ?>
                        <div>
                            <span class="text-gray-600 block mb-2">المقاول:</span>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-sm text-gray-700"><?= htmlspecialchars($project['contractor']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($project['funding_source'])): ?>
                        <div>
                            <span class="text-gray-600 block mb-2">مصدر التمويل:</span>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-sm text-gray-700"><?= htmlspecialchars($project['funding_source']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-12 text-center">
            <a href="projects.php" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-300">
                ← العودة إلى المشاريع
            </a>
        </div>
    </div>

    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-center md:text-left mb-4 md:mb-0">
                    <p>&copy; 2024 <?= htmlspecialchars($site_title) ?>. جميع الحقوق محفوظة.</p>
                </div>
                <div class="flex items-center text-center md:text-right">
                    <span class="text-gray-400 text-sm mr-2">Development And Designed By</span>
                    <a href="https://www.sobusiness.group/" target="_blank" class="hover:opacity-80 transition-opacity">
                        <img src="assets/images/sobusiness-logo.svg" alt="SoBusiness Group" class="h-8 w-auto">
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html> 
