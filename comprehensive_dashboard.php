<?php
// تحميل أنظمة الأمان (Security Headers و دوال مساعدة)
if (file_exists(__DIR__ . '/includes/init_security.php')) {
    require_once __DIR__ . '/includes/init_security.php';
}

require_once 'includes/auth.php';
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    // إذا كان هناك معامل خطأ، عرض رسالة مناسبة
    if (isset($_GET['error']) && $_GET['error'] === 'no_permission') {
        header('Location: login.php?message=يجب تسجيل الدخول أولاً للوصول إلى هذه الصفحة');
        exit();
    }
    header('Location: login.php');
    exit();
}

$user = $auth->getCurrentUser();

// التأكد من وجود بيانات المستخدم
if (!$user || !$user['id']) {
    session_destroy();
    header('Location: public/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم الشاملة - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="public/assets/css/tekrit-theme.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Cairo', sans-serif; 
            overflow-x: hidden;
        }
        .sidebar-icon { width: 1.5rem; height: 1.5rem; }
        .sidebar {
            height: 100vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.3) transparent;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        .main-content {
            height: 100vh;
            overflow-y: auto;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #6366f1;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* تحسين النافذة الجانبية للجوال */
        @media (max-width: 768px) {
            .sidebar {
                height: 100vh;
                z-index: 50;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
    <div x-data="{ open: false, currentSection: 'dashboard' }" class="flex h-screen">
        <!-- Sidebar -->
        <aside class="sidebar bg-indigo-800 text-white w-64 space-y-2 py-4 px-2 absolute inset-y-0 right-0 transform md:relative md:translate-x-0 transition-transform duration-200 ease-in-out" 
               :class="{'translate-x-0': open, 'translate-x-full': !open}">
            
            <div class="text-white flex items-center justify-center space-x-2 px-4 mb-6 bg-white rounded-lg p-4 shadow-sm">
                <img src="public/assets/images/Tekrit_LOGO.jpg" alt="شعار بلدية تكريت" class="tekrit-logo ml-4">
                <span class="text-lg font-extrabold text-gray-800">بلدية تكريت - النظام الشامل</span>
            </div>

            <nav x-data="{ active: 'dashboard' }">

                <!-- ═══════════════════════════════════ -->
                <!-- 🏠 الرئيسية -->
                <!-- ═══════════════════════════════════ -->
                <a @click.prevent="showSection('dashboard', $event.currentTarget)" href="#" class="nav-item bg-indigo-900 flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📊</span>
                    <span class="mr-3 font-semibold">لوحة التحكم الرئيسية</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 🏛️ الإدارة العامة -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">🏛️ الإدارة العامة</p>
                </div>

                <a href="modules/municipality_management.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🏛️</span>
                    <span class="mr-3">إدارة البلدية</span>
                </a>

                <a href="modules/council_management.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">👥</span>
                    <span class="mr-3">المجلس البلدي</span>
                </a>

                <a href="modules/hr.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">👔</span>
                    <span class="mr-3">الموارد البشرية</span>
                </a>

                <a href="modules/permissions.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🔐</span>
                    <span class="mr-3">الصلاحيات</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 💰 النظام المالي -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">💰 النظام المالي</p>
                </div>

                <a href="modules/financial_dashboard.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700 bg-gradient-to-l from-indigo-700">
                    <span class="sidebar-icon">📊</span>
                    <span class="mr-3 font-semibold">لوحة التحكم المالية</span>
                </a>

                <a href="modules/finance.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">💵</span>
                    <span class="mr-3">المعاملات المالية</span>
                </a>

                <a href="modules/budgets.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📊</span>
                    <span class="mr-3">الميزانيات</span>
                </a>

                <a href="modules/suppliers.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🏪</span>
                    <span class="mr-3">الموردين</span>
                </a>

                <a href="modules/invoices.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📄</span>
                    <span class="mr-3">الفواتير</span>
                </a>

                <a href="modules/tax_collection.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🧾</span>
                    <span class="mr-3">الجباية</span>
                </a>

                <a href="modules/donations.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">💖</span>
                    <span class="mr-3">التبرعات</span>
                </a>

                <a href="modules/contributions.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🤝</span>
                    <span class="mr-3">المساهمات الشعبية</span>
                </a>

                <a href="modules/currencies.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">💱</span>
                    <span class="mr-3">العملات</span>
                </a>

                <a href="modules/tax_types.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📋</span>
                    <span class="mr-3">أنواع الضرائب</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 🏗️ المشاريع والعقود -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">🏗️ المشاريع والعقود</p>
                </div>

                <a href="modules/projects_unified.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700 bg-gradient-to-l from-green-700">
                    <span class="sidebar-icon">🏗️</span>
                    <span class="mr-3 font-semibold">إدارة المشاريع</span>
                </a>

                <a href="modules/projects_finance.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">💵</span>
                    <span class="mr-3">التتبع المالي للمشاريع</span>
                </a>

                <a href="modules/contracts.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📋</span>
                    <span class="mr-3">العقود والمناقصات</span>
                </a>

                <a href="modules/donor_organizations.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🏛️</span>
                    <span class="mr-3">المنظمات المانحة</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 👥 خدمات المواطنين -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">👥 خدمات المواطنين</p>
                </div>

                <a href="modules/citizens.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">👨‍👩‍👧‍👦</span>
                    <span class="mr-3">إدارة المواطنين</span>
                </a>

                <a href="modules/citizens_accounts.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">👤</span>
                    <span class="mr-3">حسابات المواطنين</span>
                </a>

                <a href="modules/complaints.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📢</span>
                    <span class="mr-3">الشكاوى</span>
                </a>

                <a href="modules/building_permit.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📝</span>
                    <span class="mr-3">رخص البناء</span>
                </a>

                <a href="modules/violations.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">⚠️</span>
                    <span class="mr-3">المخالفات</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 🚚 الخدمات والصيانة -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">🚚 الخدمات والصيانة</p>
                </div>

                <a href="modules/vehicles.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🚚</span>
                    <span class="mr-3">الآليات</span>
                </a>

                <a href="modules/drivers_section.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🚗</span>
                    <span class="mr-3">السائقين</span>
                </a>

                <a href="modules/maintenance.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🔧</span>
                    <span class="mr-3">الصيانة</span>
                </a>

                <a href="modules/waste.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🗑️</span>
                    <span class="mr-3">النفايات</span>
                </a>

                <a href="modules/inventory.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📦</span>
                    <span class="mr-3">المخزون</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 🗺️ الخرائط والمرافق -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">🗺️ الخرائط والمرافق</p>
                </div>

                <a href="modules/facilities_management.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🏢</span>
                    <span class="mr-3">إدارة المرافق</span>
                </a>

                <a href="modules/facilities_categories.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📂</span>
                    <span class="mr-3">فئات المرافق</span>
                </a>

                <a href="modules/map_settings.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🗺️</span>
                    <span class="mr-3">إعدادات الخريطة</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 🌐 الموقع والاتصالات -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">🌐 الموقع والاتصالات</p>
                </div>

                <a href="modules/public_content_management.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🌐</span>
                    <span class="mr-3">الموقع العام</span>
                </a>

                <a href="modules/contact_management.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📞</span>
                    <span class="mr-3">اتصل بنا</span>
                </a>

                <a href="modules/telegram_messages.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">✈️</span>
                    <span class="mr-3">رسائل Telegram</span>
                </a>

                <a href="modules/sms.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📱</span>
                    <span class="mr-3">الرسائل النصية</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- 📊 التقارير والأرشفة -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">📊 التقارير والأرشفة</p>
                </div>

                <a href="modules/reports.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700 bg-gradient-to-l from-purple-700">
                    <span class="sidebar-icon">📊</span>
                    <span class="mr-3 font-semibold">التقارير الموحدة</span>
                </a>

                <a href="modules/archive.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">📁</span>
                    <span class="mr-3">الأرشيف الإلكتروني</span>
                </a>

                <a href="all_tables_manager.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">🗄️</span>
                    <span class="mr-3">الجداول المرجعية</span>
                </a>

                <!-- ═══════════════════════════════════ -->
                <!-- ⚙️ الإعدادات -->
                <!-- ═══════════════════════════════════ -->
                <div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">
                    <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">⚙️ الإعدادات</p>
                </div>

                <a href="modules/system_settings.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">⚙️</span>
                    <span class="mr-3">إعدادات النظام</span>
                </a>

                <a href="modules/telegram_settings.php" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700">
                    <span class="sidebar-icon">✈️</span>
                    <span class="mr-3">إعدادات Telegram</span>
                </a>

            </nav>
        </aside>

        <!-- Main content -->
        <div class="main-content flex-1 flex flex-col">
            <!-- Top bar -->
            <header class="bg-white shadow-md p-4 flex justify-between items-center flex-shrink-0">
                <div class="flex items-center">
                    <button @click="open = !open" class="text-slate-500 focus:outline-none md:hidden ml-4">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <h1 id="header-title" class="text-xl font-semibold text-slate-700">لوحة التحكم الرئيسية</h1>
                </div>
                <div class="flex items-center space-x-reverse space-x-4">
                    <span class="text-sm">أهلاً، <?= htmlspecialchars($user['full_name'] ?? 'المستخدم') ?></span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 mr-4">خروج</a>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-6 md:p-8 overflow-y-auto">
                <!-- Dashboard Section -->
                <div id="dashboard" class="content-section">
                    <div class="mb-6">
                        <p class="text-slate-600">النظام الشامل لإدارة بلدية تكريت - جميع الأقسام والوظائف متصلة ومتكاملة</p>
                    </div>
                    
                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">الشكاوى النشطة</p>
                                    <p class="text-3xl font-bold text-red-600">42</p>
                                </div>
                                <div class="bg-red-100 text-red-600 p-3 rounded-full">📢</div>
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">المشاريع النشطة</p>
                                    <p class="text-3xl font-bold text-blue-600">8</p>
                                </div>
                                <div class="bg-blue-100 text-blue-600 p-3 rounded-full">🏗️</div>
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">إجمالي الموظفين</p>
                                    <p class="text-3xl font-bold text-green-600">156</p>
                                </div>
                                <div class="bg-green-100 text-green-600 p-3 rounded-full">👥</div>
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-slate-500">الميزانية المتاحة</p>
                                    <p class="text-3xl font-bold text-purple-600">2.4M</p>
                                </div>
                                <div class="bg-purple-100 text-purple-600 p-3 rounded-full">💰</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <h3 class="font-semibold mb-4">توزيع الميزانية حسب الأقسام</h3>
                            <div class="chart-container">
                                <canvas id="budgetChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <h3 class="font-semibold mb-4">تطور المشاريع الشهرية</h3>
                            <div class="chart-container">
                                <canvas id="projectsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Municipality Management Section -->
                <div id="municipality" class="content-section hidden">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-800 mb-2">إدارة البلدية</h2>
                        <p class="text-slate-600">إدارة الهيكل الإداري، اللجان، الجلسات والقرارات البلدية</p>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <h3 class="font-semibold mb-4">🏢 الهيكل الإداري</h3>
                            <p class="text-sm text-slate-600 mb-4">إدارة الأقسام والتسلسل الهرمي</p>
                            <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">إدارة الأقسام</button>
                        </div>
                        
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <h3 class="font-semibold mb-4">👥 إدارة اللجان</h3>
                            <p class="text-sm text-slate-600 mb-4">إنشاء وإدارة لجان البلدية</p>
                            <button class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">إدارة اللجان</button>
                        </div>
                        
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <h3 class="font-semibold mb-4">📋 الجلسات والقرارات</h3>
                            <p class="text-sm text-slate-600 mb-4">جدولة الاجتماعات وتوثيق القرارات</p>
                            <button class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">إدارة الجلسات</button>
                        </div>
                    </div>
                </div>

                <!-- Financial Management Section -->
                <div id="finance" class="content-section hidden">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-800 mb-2">الإدارة المالية</h2>
                        <p class="text-slate-600">نظام شامل لإدارة الإيرادات والمصروفات والميزانيات</p>
                    </div>
                    
                    <!-- Financial Entry Form -->
                    <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                        <h3 class="font-semibold mb-4">إضافة قيد مالي جديد</h3>
                        <form id="financialForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">نوع القيد</label>
                                <select class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="revenue">إيراد</option>
                                    <option value="expense">مصروف</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ</label>
                                <input type="number" class="w-full p-2 border border-gray-300 rounded-md" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">العملة</label>
                                <select class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="1">ليرة لبنانية (LBP)</option>
                                    <option value="2">دولار أمريكي (USD)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الفئة</label>
                                <select class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="">اختر الفئة</option>
                                    <option value="salaries">رواتب</option>
                                    <option value="maintenance">صيانة</option>
                                    <option value="taxes">ضرائب</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">القسم المرتبط</label>
                                <select class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="">اختر القسم</option>
                                    <option value="1">الهندسة</option>
                                    <option value="2">النظافة</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                                <input type="date" class="w-full p-2 border border-gray-300 rounded-md">
                            </div>
                            <div class="md:col-span-2 lg:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                                <textarea class="w-full p-2 border border-gray-300 rounded-md" rows="2" placeholder="وصف تفصيلي للقيد المالي"></textarea>
                            </div>
                            <div class="md:col-span-2 lg:col-span-3">
                                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700">حفظ القيد المالي</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Financial Summary -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <h3 class="font-semibold mb-4">الملخص المالي الشهري</h3>
                            <div class="chart-container">
                                <canvas id="monthlyFinanceChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <h3 class="font-semibold mb-4">آخر العمليات المالية</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                                    <span class="text-sm">إيراد - ضريبة الأملاك</span>
                                    <span class="font-semibold text-green-600">+2,500,000 ل.ل</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                                    <span class="text-sm">مصروف - رواتب الموظفين</span>
                                    <span class="font-semibold text-red-600">-45,000,000 ل.ل</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                                    <span class="text-sm">مصروف - صيانة الطرق</span>
                                    <span class="font-semibold text-blue-600">-8,200,000 ل.ل</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- قسم الموارد البشرية -->
                <div id="hr" class="content-section hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                        <!-- إحصائيات الموظفين -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">إجمالي الموظفين</h3>
                                    <p class="text-3xl font-bold text-indigo-600 mt-2">247</p>
                                </div>
                                <div class="text-4xl">👥</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">الحضور اليوم</h3>
                                    <p class="text-3xl font-bold text-green-600 mt-2">234</p>
                                </div>
                                <div class="text-4xl">✅</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">في إجازة</h3>
                                    <p class="text-3xl font-bold text-yellow-600 mt-2">13</p>
                                </div>
                                <div class="text-4xl">🏖️</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">وظائف شاغرة</h3>
                                    <p class="text-3xl font-bold text-blue-600 mt-2">8</p>
                                </div>
                                <div class="text-4xl">💼</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- إدارة الموظفين -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-slate-800">إدارة الموظفين</h3>
                                <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors" onclick="openAddEmployeeModal()">
                                    إضافة موظف جديد
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-3 border border-slate-200 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                            <span class="text-indigo-600 font-bold">أح</span>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold">أحمد محمد العراقي</h4>
                                            <p class="text-sm text-slate-600">مهندس - قسم الهندسة</p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded" onclick="editEmployee(1)">تعديل</button>
                                        <button class="px-3 py-1 text-green-600 hover:bg-green-50 rounded" onclick="viewEmployee(1)">عرض</button>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center p-3 border border-slate-200 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                            <span class="text-green-600 font-bold">ف</span>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold">فاطمة علي حسن</h4>
                                            <p class="text-sm text-slate-600">محاسبة - القسم المالي</p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded" onclick="editEmployee(2)">تعديل</button>
                                        <button class="px-3 py-1 text-green-600 hover:bg-green-50 rounded" onclick="viewEmployee(2)">عرض</button>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center p-3 border border-slate-200 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                            <span class="text-purple-600 font-bold">م</span>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold">محمد خالد الجبوري</h4>
                                            <p class="text-sm text-slate-600">فني صيانة - قسم الصيانة</p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded" onclick="editEmployee(3)">تعديل</button>
                                        <button class="px-3 py-1 text-green-600 hover:bg-green-50 rounded" onclick="viewEmployee(3)">عرض</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الحضور والغياب -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-slate-800">الحضور والغياب</h3>
                                <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors" onclick="generateAttendanceReport()">
                                    تقرير الحضور
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div class="p-3 bg-green-50 border-r-4 border-green-400 rounded">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-semibold text-green-800">حضور في الوقت</h4>
                                            <p class="text-sm text-green-600">189 موظف</p>
                                        </div>
                                        <span class="text-2xl">✅</span>
                                    </div>
                                </div>
                                <div class="p-3 bg-yellow-50 border-r-4 border-yellow-400 rounded">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-semibold text-yellow-800">تأخير</h4>
                                            <p class="text-sm text-yellow-600">45 موظف</p>
                                        </div>
                                        <span class="text-2xl">⏰</span>
                                    </div>
                                </div>
                                <div class="p-3 bg-red-50 border-r-4 border-red-400 rounded">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-semibold text-red-800">غياب</h4>
                                            <p class="text-sm text-red-600">13 موظف</p>
                                        </div>
                                        <span class="text-2xl">❌</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الرواتب والإجازات -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-slate-800">إدارة الرواتب</h3>
                                <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors" onclick="processSalaries()">
                                    معالجة الرواتب
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                                    <span class="text-sm font-medium">إجمالي الرواتب الشهرية</span>
                                    <span class="text-lg font-bold text-purple-600">85.5 مليون ل.ل</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                    <span class="text-sm font-medium">رواتب مدفوعة</span>
                                    <span class="text-lg font-bold text-green-600">234 موظف</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg">
                                    <span class="text-sm font-medium">في انتظار المعالجة</span>
                                    <span class="text-lg font-bold text-yellow-600">13 موظف</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-slate-800">إدارة الإجازات</h3>
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors" onclick="manageLeaves()">
                                    إدارة الإجازات
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div class="p-3 border-r-4 border-blue-400 bg-blue-50 rounded">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-semibold text-blue-800">طلبات إجازة جديدة</h4>
                                            <p class="text-sm text-blue-600">7 طلبات في انتظار الموافقة</p>
                                        </div>
                                        <button class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700" onclick="reviewLeaveRequests()">مراجعة</button>
                                    </div>
                                </div>
                                <div class="p-3 border-r-4 border-green-400 bg-green-50 rounded">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-semibold text-green-800">إجازات معتمدة</h4>
                                            <p class="text-sm text-green-600">13 موظف في إجازة حالياً</p>
                                        </div>
                                        <button class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700" onclick="viewApprovedLeaves()">عرض</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الإجراءات السريعة للموارد البشرية -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-slate-800">الإجراءات السريعة</h3>
                            <a href="modules/hr.php" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                الانتقال للصفحة الكاملة
                            </a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <button class="p-4 text-center bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" onclick="openQuickHRAction('employees')">
                                <div class="text-2xl mb-2">👥</div>
                                <div class="text-sm font-medium">إدارة الموظفين</div>
                            </button>
                            <button class="p-4 text-center bg-green-50 hover:bg-green-100 rounded-lg transition-colors" onclick="openQuickHRAction('attendance')">
                                <div class="text-2xl mb-2">📋</div>
                                <div class="text-sm font-medium">الحضور والغياب</div>
                            </button>
                            <button class="p-4 text-center bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors" onclick="openQuickHRAction('salaries')">
                                <div class="text-2xl mb-2">💰</div>
                                <div class="text-sm font-medium">الرواتب</div>
                            </button>
                            <button class="p-4 text-center bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors" onclick="openQuickHRAction('leaves')">
                                <div class="text-2xl mb-2">🏖️</div>
                                <div class="text-sm font-medium">الإجازات</div>
                            </button>
                            <button class="p-4 text-center bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors" onclick="openQuickHRAction('recruitment')">
                                <div class="text-2xl mb-2">💼</div>
                                <div class="text-sm font-medium">التوظيف</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- قسم إدارة الجباية -->
                <div id="collections" class="content-section hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">إجمالي الإيرادات</h3>
                                    <p class="text-3xl font-bold text-green-600 mt-2">245.8م</p>
                                </div>
                                <div class="text-4xl">💰</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">المعاملات اليوم</h3>
                                    <p class="text-3xl font-bold text-blue-600 mt-2">87</p>
                                </div>
                                <div class="text-4xl">📋</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">رسوم معلقة</h3>
                                    <p class="text-3xl font-bold text-yellow-600 mt-2">23</p>
                                </div>
                                <div class="text-4xl">⏳</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">إجمالي المتأخرات</h3>
                                    <p class="text-3xl font-bold text-red-600 mt-2">12.5م</p>
                                </div>
                                <div class="text-4xl">⚠️</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-slate-800">إجراءات الجباية السريعة</h3>
                            <a href="modules/tax_collection.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                الانتقال للصفحة الكاملة
                            </a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <button class="p-4 text-center bg-green-50 hover:bg-green-100 rounded-lg transition-colors" onclick="openCollectionAction('new_payment')">
                                <div class="text-2xl mb-2">💳</div>
                                <div class="text-sm font-medium">دفعة جديدة</div>
                            </button>
                            <button class="p-4 text-center bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors" onclick="openCollectionAction('search_account')">
                                <div class="text-2xl mb-2">🔍</div>
                                <div class="text-sm font-medium">البحث عن حساب</div>
                            </button>
                            <button class="p-4 text-center bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors" onclick="openCollectionAction('pending_fees')">
                                <div class="text-2xl mb-2">📄</div>
                                <div class="text-sm font-medium">الرسوم المعلقة</div>
                            </button>
                            <button class="p-4 text-center bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors" onclick="openCollectionAction('reports')">
                                <div class="text-2xl mb-2">📊</div>
                                <div class="text-sm font-medium">التقارير</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- قسم إدارة المشاريع -->
                <div id="projects" class="content-section hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">المشاريع النشطة</h3>
                                    <p class="text-3xl font-bold text-blue-600 mt-2">8</p>
                                </div>
                                <div class="text-4xl">🏗️</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">المشاريع المكتملة</h3>
                                    <p class="text-3xl font-bold text-green-600 mt-2">15</p>
                                </div>
                                <div class="text-4xl">✅</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">الميزانية المستخدمة</h3>
                                    <p class="text-3xl font-bold text-purple-600 mt-2">65%</p>
                                </div>
                                <div class="text-4xl">💰</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">مشاريع معلقة</h3>
                                    <p class="text-3xl font-bold text-yellow-600 mt-2">3</p>
                                </div>
                                <div class="text-4xl">⏸️</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- المشاريع النشطة -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-slate-800">المشاريع النشطة</h3>
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors" onclick="openAddProjectModal()">
                                    إضافة مشروع جديد
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div class="p-4 border border-slate-200 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-slate-800">تطوير شارع الجمهورية</h4>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">نشط</span>
                                    </div>
                                    <p class="text-sm text-slate-600 mb-3">إعادة تأهيل الشارع الرئيسي بطول 2 كم</p>
                                    <div class="flex justify-between items-center">
                                        <div class="text-sm text-slate-500">الميزانية: 250 مليون ل.ل</div>
                                        <div class="flex space-x-2">
                                            <button class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded" onclick="editProject(1)">تعديل</button>
                                            <button class="px-3 py-1 text-green-600 hover:bg-green-50 rounded" onclick="viewProject(1)">عرض</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4 border border-slate-200 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-slate-800">حديقة الأطفال المركزية</h4>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">قريب الإنتهاء</span>
                                    </div>
                                    <p class="text-sm text-slate-600 mb-3">إنشاء حديقة أطفال مجهزة بألعاب حديثة</p>
                                    <div class="flex justify-between items-center">
                                        <div class="text-sm text-slate-500">الميزانية: 180 مليون ل.ل</div>
                                        <div class="flex space-x-2">
                                            <button class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded" onclick="editProject(2)">تعديل</button>
                                            <button class="px-3 py-1 text-green-600 hover:bg-green-50 rounded" onclick="viewProject(2)">عرض</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- إحصائيات المشاريع -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-800 mb-4">إحصائيات الإنجاز</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium">تطوير البنية التحتية</span>
                                        <span class="text-sm font-bold text-blue-600">75%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: 75%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium">المرافق العامة</span>
                                        <span class="text-sm font-bold text-green-600">90%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: 90%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium">النظافة والبيئة</span>
                                        <span class="text-sm font-bold text-yellow-600">60%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2">
                                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 60%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-slate-800">إجراءات المشاريع السريعة</h3>
                            <a href="modules/projects.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                الانتقال للصفحة الكاملة
                            </a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <button class="p-4 text-center bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors" onclick="openProjectAction('new_project')">
                                <div class="text-2xl mb-2">🆕</div>
                                <div class="text-sm font-medium">مشروع جديد</div>
                            </button>
                            <button class="p-4 text-center bg-green-50 hover:bg-green-100 rounded-lg transition-colors" onclick="openProjectAction('progress_report')">
                                <div class="text-2xl mb-2">📈</div>
                                <div class="text-sm font-medium">تقرير التقدم</div>
                            </button>
                            <button class="p-4 text-center bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors" onclick="openProjectAction('budget_analysis')">
                                <div class="text-2xl mb-2">💹</div>
                                <div class="text-sm font-medium">تحليل الميزانية</div>
                            </button>
                            <button class="p-4 text-center bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors" onclick="openProjectAction('timeline')">
                                <div class="text-2xl mb-2">📅</div>
                                <div class="text-sm font-medium">الجدول الزمني</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- قسم إدارة الشكاوى -->
                <div id="complaints" class="content-section hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">الشكاوى النشطة</h3>
                                    <p class="text-3xl font-bold text-red-600 mt-2">42</p>
                                </div>
                                <div class="text-4xl">📢</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">معالجة اليوم</h3>
                                    <p class="text-3xl font-bold text-blue-600 mt-2">12</p>
                                </div>
                                <div class="text-4xl">🔄</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">شكاوى محلولة</h3>
                                    <p class="text-3xl font-bold text-green-600 mt-2">156</p>
                                </div>
                                <div class="text-4xl">✅</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">وقت الاستجابة</h3>
                                    <p class="text-3xl font-bold text-purple-600 mt-2">4.2س</p>
                                </div>
                                <div class="text-4xl">⏱️</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- الشكاوى الجديدة -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-slate-800">الشكاوى الجديدة</h3>
                                <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors" onclick="openAddComplaintModal()">
                                    تسجيل شكوى جديدة
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div class="p-4 border-r-4 border-red-400 bg-red-50 rounded">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-red-800">تراكم النفايات - حي الجمهورية</h4>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">عاجل</span>
                                    </div>
                                    <p class="text-sm text-red-600 mb-2">مواطن: أحمد محمد العراقي</p>
                                    <p class="text-xs text-slate-600 mb-3">تم استلامها: منذ 2 ساعة</p>
                                    <div class="flex space-x-2">
                                        <button class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700" onclick="assignComplaint(1)">تعيين</button>
                                        <button class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700" onclick="viewComplaint(1)">عرض</button>
                                    </div>
                                </div>
                                <div class="p-4 border-r-4 border-yellow-400 bg-yellow-50 rounded">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-yellow-800">إنارة الشارع معطلة</h4>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded">متوسط</span>
                                    </div>
                                    <p class="text-sm text-yellow-600 mb-2">مواطن: فاطمة علي حسن</p>
                                    <p class="text-xs text-slate-600 mb-3">تم استلامها: منذ 4 ساعات</p>
                                    <div class="flex space-x-2">
                                        <button class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700" onclick="assignComplaint(2)">تعيين</button>
                                        <button class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700" onclick="viewComplaint(2)">عرض</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- إحصائيات الحلول -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-800 mb-4">إحصائيات الحلول</h3>
                            <div class="space-y-3">
                                <div class="p-3 bg-green-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium">النظافة والبيئة</span>
                                        <span class="text-lg font-bold text-green-600">85%</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">34 من 40 شكوى تم حلها</p>
                                </div>
                                <div class="p-3 bg-blue-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium">البنية التحتية</span>
                                        <span class="text-lg font-bold text-blue-600">72%</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">18 من 25 شكوى تم حلها</p>
                                </div>
                                <div class="p-3 bg-purple-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium">الخدمات العامة</span>
                                        <span class="text-lg font-bold text-purple-600">90%</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">27 من 30 شكوى تم حلها</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-slate-800">إجراءات الشكاوى السريعة</h3>
                            <a href="modules/complaints.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                الانتقال للصفحة الكاملة
                            </a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <button class="p-4 text-center bg-red-50 hover:bg-red-100 rounded-lg transition-colors" onclick="openComplaintAction('new_complaint')">
                                <div class="text-2xl mb-2">📝</div>
                                <div class="text-sm font-medium">شكوى جديدة</div>
                            </button>
                            <button class="p-4 text-center bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors" onclick="openComplaintAction('assign_complaints')">
                                <div class="text-2xl mb-2">👤</div>
                                <div class="text-sm font-medium">توزيع الشكاوى</div>
                            </button>
                            <button class="p-4 text-center bg-green-50 hover:bg-green-100 rounded-lg transition-colors" onclick="openComplaintAction('track_progress')">
                                <div class="text-2xl mb-2">📊</div>
                                <div class="text-sm font-medium">تتبع التقدم</div>
                            </button>
                            <button class="p-4 text-center bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors" onclick="openComplaintAction('citizen_feedback')">
                                <div class="text-2xl mb-2">⭐</div>
                                <div class="text-sm font-medium">تقييم المواطن</div>
                            </button>
                            <button class="p-4 text-center bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors" onclick="openComplaintAction('reports')">
                                <div class="text-2xl mb-2">📈</div>
                                <div class="text-sm font-medium">التقارير</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- قسم إدارة النفايات -->
                <div id="waste" class="content-section hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">الشاحنات النشطة</h3>
                                    <p class="text-3xl font-bold text-green-600 mt-2">24</p>
                                </div>
                                <div class="text-4xl">🚛</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">المناطق المكتملة</h3>
                                    <p class="text-3xl font-bold text-blue-600 mt-2">18</p>
                                </div>
                                <div class="text-4xl">✅</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">أطنان النفايات</h3>
                                    <p class="text-3xl font-bold text-purple-600 mt-2">156</p>
                                </div>
                                <div class="text-4xl">🗑️</div>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800">شكاوى النظافة</h3>
                                    <p class="text-3xl font-bold text-red-600 mt-2">8</p>
                                </div>
                                <div class="text-4xl">📢</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- إدارة المسارات -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-slate-800">مسارات الجمع اليومية</h3>
                                <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors" onclick="planNewRoute()">
                                    تخطيط مسار جديد
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div class="p-4 border-r-4 border-green-400 bg-green-50 rounded">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-green-800">المسار الشمالي</h4>
                                            <p class="text-sm text-green-600">شاحنة رقم 12 - أحمد محمد</p>
                                            <p class="text-xs text-slate-600">25 منطقة - مكتمل</p>
                                        </div>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">مكتمل</span>
                                    </div>
                                </div>
                                <div class="p-4 border-r-4 border-blue-400 bg-blue-50 rounded">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-blue-800">المسار الجنوبي</h4>
                                            <p class="text-sm text-blue-600">شاحنة رقم 08 - علي حسن</p>
                                            <p class="text-xs text-slate-600">30 منطقة - جاري العمل</p>
                                        </div>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">نشط</span>
                                    </div>
                                </div>
                                <div class="p-4 border-r-4 border-yellow-400 bg-yellow-50 rounded">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-yellow-800">المسار الشرقي</h4>
                                            <p class="text-sm text-yellow-600">شاحنة رقم 15 - محمد عراقي</p>
                                            <p class="text-xs text-slate-600">20 منطقة - انتظار</p>
                                        </div>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded">قريباً</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- إحصائيات الأداء -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="text-lg font-bold text-slate-800 mb-4">إحصائيات الأداء</h3>
                            <div class="space-y-4">
                                <div class="p-3 bg-green-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium">معدل التغطية اليومية</span>
                                        <span class="text-lg font-bold text-green-600">94%</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">85 من 90 منطقة تم تغطيتها</p>
                                </div>
                                <div class="p-3 bg-blue-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium">الشاحنات العاملة</span>
                                        <span class="text-lg font-bold text-blue-600">24/26</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">2 شاحنة في الصيانة</p>
                                </div>
                                <div class="p-3 bg-purple-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium">كفاءة العمال</span>
                                        <span class="text-lg font-bold text-purple-600">87%</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">45 عامل نظافة نشط</p>
                                </div>
                                <div class="p-3 bg-yellow-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium">استهلاك الوقود</span>
                                        <span class="text-lg font-bold text-yellow-600">320L</span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">ضمن الحد المسموح</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- إجراءات النظافة السريعة -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-slate-800">إجراءات النظافة السريعة</h3>
                            <a href="modules/waste.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                الانتقال للصفحة الكاملة
                            </a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <button class="p-4 text-center bg-green-50 hover:bg-green-100 rounded-lg transition-colors" onclick="openWasteAction('schedule_pickup')">
                                <div class="text-2xl mb-2">📅</div>
                                <div class="text-sm font-medium">جدولة الجمع</div>
                            </button>
                            <button class="p-4 text-center bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors" onclick="openWasteAction('track_vehicles')">
                                <div class="text-2xl mb-2">🚛</div>
                                <div class="text-sm font-medium">تتبع الشاحنات</div>
                            </button>
                            <button class="p-4 text-center bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors" onclick="openWasteAction('manage_workers')">
                                <div class="text-2xl mb-2">👷</div>
                                <div class="text-sm font-medium">إدارة العمال</div>
                            </button>
                            <button class="p-4 text-center bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors" onclick="openWasteAction('fuel_management')">
                                <div class="text-2xl mb-2">⛽</div>
                                <div class="text-sm font-medium">إدارة الوقود</div>
                            </button>
                            <button class="p-4 text-center bg-red-50 hover:bg-red-100 rounded-lg transition-colors" onclick="openWasteAction('emergency_cleanup')">
                                <div class="text-2xl mb-2">🚨</div>
                                <div class="text-sm font-medium">تنظيف طارئ</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- الأقسام الأخرى ستكون "قيد التطوير" مؤقتاً -->
                <?php
                $remaining_sections = [
                    'inventory' => 'إدارة المخزون والمشتريات',
                    'vehicles' => 'إدارة الآليات',
                    'maintenance' => 'إدارة الصيانة الشاملة',
                    'permits' => 'رخص البناء والنماذج البلدية',
                    'donations' => 'إدارة التبرعات',
                    'citizens' => 'إدارة المواطنين',
                    'violations' => 'إدارة المخالفات',
                    'archive' => 'الأرشيف الإلكتروني',
                    'sms' => 'إرسال الرسائل النصية',
                    'contracts' => 'العقود والمناقصات',
                    'settings' => 'إعدادات النظام',
                    'permissions' => 'إدارة الصلاحيات والمستخدمين'
                ];
                
                foreach ($remaining_sections as $id => $title): ?>
                    <div id="<?= $id ?>" class="content-section hidden">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-slate-800 mb-2"><?= $title ?></h2>
                            <p class="text-slate-600">قسم <?= $title ?> - قيد التطوير والتنفيذ</p>
                        </div>
                        <div class="bg-white p-8 rounded-lg shadow-sm text-center">
                            <div class="text-6xl mb-4">🚧</div>
                            <h3 class="text-xl font-semibold mb-2">قيد التطوير</h3>
                            <p class="text-slate-600">هذا القسم قيد التطوير وسيتم إضافة جميع الوظائف والميزات المطلوبة قريباً</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        // Global variables for charts
        let budgetChart, projectsChart, monthlyFinanceChart;

        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Charts after page load
            setTimeout(() => {
                initializeBudgetChart();
                initializeProjectsChart();
                initializeMonthlyFinanceChart();
            }, 100);
        });

        function showSection(sectionId, element) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Show selected section
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.classList.remove('hidden');
            }

            // Update navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('bg-indigo-900');
            });
            if (element) {
                element.classList.add('bg-indigo-900');
                
                // Update header title
                const titleElement = element.querySelector('span:last-child');
                if (titleElement) {
                    document.getElementById('header-title').textContent = titleElement.textContent;
                }
            }

            // إعادة تهيئة الرسوم البيانية عند عرض قسم لوحة التحكم
            if (sectionId === 'dashboard') {
                setTimeout(() => {
                    if (budgetChart) budgetChart.resize();
                    if (projectsChart) projectsChart.resize();
                }, 100);
            }

            // إعادة تهيئة الرسم البياني المالي عند عرض القسم المالي
            if (sectionId === 'finance') {
                setTimeout(() => {
                    if (monthlyFinanceChart) monthlyFinanceChart.resize();
                }, 100);
            }

            // إغلاق القائمة الجانبية في الجوال بعد التنقل
            if (window.innerWidth < 768) {
                Alpine.store('dashboard', { open: false });
            }
        }

        function initializeBudgetChart() {
            const canvas = document.getElementById('budgetChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            budgetChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['الموارد البشرية', 'الهندسة', 'النظافة', 'الإدارة', 'أخرى'],
                    datasets: [{
                        data: [35, 25, 20, 15, 5],
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    }
                }
            });
        }

        function initializeProjectsChart() {
            const canvas = document.getElementById('projectsChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            projectsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                    datasets: [{
                        label: 'المشاريع المكتملة',
                        data: [2, 4, 3, 5, 2, 3],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'المشاريع الجديدة',
                        data: [3, 2, 4, 1, 3, 2],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.1,
                        fill: true
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
        }

        function initializeMonthlyFinanceChart() {
            const canvas = document.getElementById('monthlyFinanceChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            monthlyFinanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                    datasets: [{
                        label: 'الإيرادات (مليون ل.ل)',
                        data: [120, 150, 130, 140, 160, 135],
                        backgroundColor: '#10b981',
                        borderColor: '#10b981',
                        borderWidth: 1
                    }, {
                        label: 'المصروفات (مليون ل.ل)',
                        data: [100, 120, 110, 115, 130, 120],
                        backgroundColor: '#ef4444',
                        borderColor: '#ef4444',
                        borderWidth: 1
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
                            beginAtZero: true,
                            ticks: {
                                callback: function(value, index, values) {
                                    return value + ' م.ل.ل';
                                }
                            }
                        }
                    }
                }
            });
        }

        // إضافة مستمع للنوافذ المصغرة لإعادة تحجيم الرسوم البيانية
        window.addEventListener('resize', function() {
            setTimeout(() => {
                if (budgetChart) budgetChart.resize();
                if (projectsChart) projectsChart.resize();
                if (monthlyFinanceChart) monthlyFinanceChart.resize();
            }, 100);
        });

        // التأكد من أن Alpine.js يعمل بشكل صحيح
        document.addEventListener('alpine:init', () => {
            console.log('Alpine.js initialized successfully');
        });

        // وظائف إدارة البلدية - ربط مع الصفحات الحقيقية
        function openAddDepartmentModal() {
            window.open('modules/departments.php#add-department', '_blank');
        }

        function editDepartment(id) {
            window.open(`modules/departments.php?edit=${id}`, '_blank');
        }

        function viewDepartment(id) {
            window.open(`modules/departments.php?view=${id}`, '_blank');
        }

        function scheduleNewMeeting() {
            window.open('modules/departments.php#meetings', '_blank');
        }

        function openQuickAction(action) {
            const actionLinks = {
                'departments': 'modules/departments.php',
                'meetings': 'modules/departments.php#meetings',
                'decisions': 'modules/departments.php#decisions',
                'reports': 'modules/departments.php#reports'
            };
            window.open(actionLinks[action], '_blank');
        }

        // وظائف الموارد البشرية - ربط مع الصفحات الحقيقية
        function openAddEmployeeModal() {
            window.open('modules/hr.php#add-employee', '_blank');
        }

        function editEmployee(id) {
            window.open(`modules/edit_employee.php?id=${id}`, '_blank');
        }

        function viewEmployee(id) {
            window.open(`modules/get_employee.php?id=${id}`, '_blank');
        }

        function generateAttendanceReport() {
            window.open('modules/hr.php#attendance-report', '_blank');
        }

        function processSalaries() {
            window.open('modules/hr.php#salary-management', '_blank');
        }

        function manageLeaves() {
            window.open('modules/hr.php#leave-management', '_blank');
        }

        function reviewLeaveRequests() {
            window.open('modules/hr.php#leave-requests', '_blank');
        }

        function viewApprovedLeaves() {
            window.open('modules/hr.php#approved-leaves', '_blank');
        }

        function openQuickHRAction(action) {
            const hrLinks = {
                'employees': 'modules/hr.php',
                'attendance': 'modules/hr.php#attendance',
                'salaries': 'modules/hr.php#salaries', 
                'leaves': 'modules/hr.php#leaves',
                'recruitment': 'modules/hr.php#recruitment'
            };
            window.open(hrLinks[action], '_blank');
        }

        // وظائف إدارة الجباية - ربط مع الصفحات الحقيقية
        function openCollectionAction(action) {
            const collectionLinks = {
                'new_payment': 'modules/tax_collection.php#new-payment',
                'search_account': 'modules/tax_collection.php#search',
                'pending_fees': 'modules/tax_collection.php#pending',
                'reports': 'modules/tax_collection.php#reports'
            };
            window.open(collectionLinks[action], '_blank');
        }

        // وظائف إدارة المشاريع - ربط مع الصفحات الحقيقية
        function openAddProjectModal() {
            window.open('modules/projects.php#add-project', '_blank');
        }

        function editProject(id) {
            window.open(`modules/projects.php?edit=${id}`, '_blank');
        }

        function viewProject(id) {
            window.open(`modules/projects.php?view=${id}`, '_blank');
        }

        function openProjectAction(action) {
            const projectLinks = {
                'new_project': 'modules/projects.php#new',
                'progress_report': 'modules/projects.php#progress',
                'budget_analysis': 'modules/projects.php#budget',
                'timeline': 'modules/projects.php#timeline'
            };
            window.open(projectLinks[action], '_blank');
        }

        // وظائف إدارة الشكاوى - ربط مع الصفحات الحقيقية
        function openAddComplaintModal() {
            window.open('modules/complaints.php#add-complaint', '_blank');
        }

        function assignComplaint(id) {
            window.open(`modules/complaints.php?assign=${id}`, '_blank');
        }

        function viewComplaint(id) {
            window.open(`modules/complaints.php?view=${id}`, '_blank');
        }

        function openComplaintAction(action) {
            const complaintLinks = {
                'new_complaint': 'modules/complaints.php#new',
                'assign_complaints': 'modules/complaints.php#assign',
                'track_progress': 'modules/complaints.php#track',
                'citizen_feedback': 'modules/complaints.php#feedback',
                'reports': 'modules/complaints.php#reports'
            };
            window.open(complaintLinks[action], '_blank');
        }

        // وظائف إدارة النفايات - ربط مع الصفحات الحقيقية
        function planNewRoute() {
            window.open('modules/waste.php#route-planning', '_blank');
        }

        function openWasteAction(action) {
            const wasteLinks = {
                'schedule_pickup': 'modules/waste.php#schedule',
                'track_vehicles': 'modules/vehicles.php',
                'manage_workers': 'modules/waste.php#workers',
                'fuel_management': 'modules/vehicles.php#fuel',
                'emergency_cleanup': 'modules/waste.php#emergency'
            };
            window.open(wasteLinks[action], '_blank');
        }
    </script>
</body>
</html> 
