<?php
/**
 * خريطة الصلاحيات للقائمة الرئيسية
 * يربط كل صفحة بالصلاحية المطلوبة لعرضها
 */

$menu_items = [
    [
        'category' => '🏛️ الإدارة العامة',
        'permissions_check' => ['municipality_view', 'council_view', 'hr_view', 'permissions_view'],
        'items' => [
            [
                'permission' => 'municipality_view',
                'url' => 'modules/municipality_management.php',
                'icon' => '🏛️',
                'label' => 'إدارة البلدية'
            ],
            [
                'permission' => 'council_view',
                'url' => 'modules/council_management.php',
                'icon' => '👥',
                'label' => 'المجلس البلدي'
            ],
            [
                'permission' => 'hr_view',
                'url' => 'modules/hr.php',
                'icon' => '👔',
                'label' => 'الموارد البشرية'
            ],
            [
                'permission' => 'permissions_view',
                'url' => 'modules/permissions.php',
                'icon' => '🔐',
                'label' => 'الصلاحيات'
            ]
        ]
    ],
    [
        'category' => '💰 النظام المالي',
        'permissions_check' => ['financial_dashboard_view', 'finance_view', 'budgets_view', 'suppliers_view', 'invoices_view', 'tax_view'],
        'items' => [
            [
                'permission' => 'financial_dashboard_view',
                'url' => 'modules/financial_dashboard.php',
                'icon' => '📊',
                'label' => 'لوحة التحكم المالية',
                'class' => 'bg-gradient-to-l from-indigo-700 font-semibold'
            ],
            [
                'permission' => 'finance_view',
                'url' => 'modules/accounting_treasury.php',
                'icon' => '💵',
                'label' => 'الحركة المالية'
            ],
            [
                'permission' => 'finance_view',
                'url' => 'modules/accounting_reports.php',
                'icon' => '📈',
                'label' => 'التقارير المالية'
            ],
            [
                'permission' => 'finance_view',
                'url' => 'modules/accounting_cashboxes.php',
                'icon' => '🗃️',
                'label' => 'إدارة الصناديق'
            ],
            [
                'permission' => 'finance_view',
                'url' => 'modules/finance.php',
                'icon' => '📒',
                'label' => 'سجل المعاملات القديم'
            ],
            [
                'permission' => 'budgets_view',
                'url' => 'modules/budgets.php',
                'icon' => '📊',
                'label' => 'الميزانيات العامة (قديم)'
            ],
            [
                'permission' => 'budgets_view',
                'url' => 'modules/accounting_committee_budgets.php',
                'icon' => '🏢',
                'label' => 'ميزانية اللجان'
            ],
            [
                'permission' => 'suppliers_view',
                'url' => 'modules/suppliers.php',
                'icon' => '🏪',
                'label' => 'الموردين'
            ],
            [
                'permission' => 'invoices_view',
                'url' => 'modules/invoices.php',
                'icon' => '📄',
                'label' => 'الفواتير'
            ],
            [
                'permission' => 'tax_view',
                'url' => 'modules/tax_collection.php',
                'icon' => '🧾',
                'label' => 'الجباية'
            ],
            [
                'permission' => 'donations_view',
                'url' => 'modules/donations.php',
                'icon' => '💖',
                'label' => 'التبرعات'
            ],
            [
                'permission' => 'contributions_view',
                'url' => 'modules/contributions.php',
                'icon' => '🤝',
                'label' => 'المساهمات الشعبية'
            ],
            [
                'permission' => 'currencies_manage',
                'url' => 'modules/currencies.php',
                'icon' => '💱',
                'label' => 'العملات'
            ],
            [
                'permission' => 'tax_view',
                'url' => 'modules/tax_types.php',
                'icon' => '📋',
                'label' => 'أنواع الضرائب'
            ]
        ]
    ],
    [
        'category' => '🏗️ المشاريع والعقود',
        'permissions_check' => ['projects_view', 'projects_finance_view', 'contracts_view', 'donors_view'],
        'items' => [
            [
                'permission' => 'projects_view',
                'url' => 'modules/projects_unified.php',
                'icon' => '🏗️',
                'label' => 'إدارة المشاريع',
                'class' => 'bg-gradient-to-l from-green-700 font-semibold'
            ],
            [
                'permission' => 'projects_finance_view',
                'url' => 'modules/projects_finance.php',
                'icon' => '💵',
                'label' => 'التتبع المالي للمشاريع'
            ],
            [
                'permission' => 'contracts_view',
                'url' => 'modules/contracts.php',
                'icon' => '📋',
                'label' => 'العقود والمناقصات'
            ],
            [
                'permission' => 'donors_view',
                'url' => 'modules/donor_organizations.php',
                'icon' => '🏛️',
                'label' => 'المنظمات المانحة'
            ]
        ]
    ],
    [
        'category' => '👥 خدمات المواطنين',
        'permissions_check' => ['citizens_view', 'citizen_accounts_view', 'complaints_view', 'permits_view', 'violations_view'],
        'items' => [
            [
                'permission' => 'citizens_view',
                'url' => 'modules/citizens.php',
                'icon' => '👨‍👩‍👧‍👦',
                'label' => 'إدارة المواطنين'
            ],
            [
                'permission' => 'citizen_accounts_view',
                'url' => 'modules/citizens_accounts.php',
                'icon' => '👤',
                'label' => 'حسابات المواطنين'
            ],
            [
                'permission' => 'complaints_view',
                'url' => 'modules/complaints.php',
                'icon' => '📢',
                'label' => 'الشكاوى'
            ],
            [
                'permission' => 'permits_view',
                'url' => 'modules/building_permit.php',
                'icon' => '📝',
                'label' => 'رخص البناء'
            ],
            [
                'permission' => 'violations_view',
                'url' => 'modules/violations.php',
                'icon' => '⚠️',
                'label' => 'المخالفات'
            ]
        ]
    ],
    [
        'category' => '🚚 الخدمات والصيانة',
        'permissions_check' => ['vehicles_view', 'drivers_view', 'maintenance_view', 'waste_view', 'inventory_view'],
        'items' => [
            [
                'permission' => 'vehicles_view',
                'url' => 'modules/vehicles.php',
                'icon' => '🚚',
                'label' => 'الآليات'
            ],
            [
                'permission' => 'drivers_view',
                'url' => 'modules/drivers_section.php',
                'icon' => '🚗',
                'label' => 'السائقين'
            ],
            [
                'permission' => 'maintenance_view',
                'url' => 'modules/maintenance.php',
                'icon' => '🔧',
                'label' => 'الصيانة'
            ],
            [
                'permission' => 'waste_view',
                'url' => 'modules/waste.php',
                'icon' => '🗑️',
                'label' => 'النفايات'
            ],
            [
                'permission' => 'inventory_view',
                'url' => 'modules/inventory.php',
                'icon' => '📦',
                'label' => 'المخزون'
            ]
        ]
    ],
    [
        'category' => '🗺️ الخرائط والمرافق',
        'permissions_check' => ['facilities_view', 'facility_categories_manage', 'map_settings_manage'],
        'items' => [
            [
                'permission' => 'facilities_view',
                'url' => 'modules/facilities_management.php',
                'icon' => '🏢',
                'label' => 'إدارة المرافق'
            ],
            [
                'permission' => 'facility_categories_manage',
                'url' => 'modules/facilities_categories.php',
                'icon' => '📂',
                'label' => 'فئات المرافق'
            ],
            [
                'permission' => 'map_settings_manage',
                'url' => 'modules/map_settings.php',
                'icon' => '🗺️',
                'label' => 'إعدادات الخريطة'
            ]
        ]
    ],
    [
        'category' => '🌐 الموقع والاتصالات',
        'permissions_check' => ['website_view', 'contact_view', 'telegram_view', 'sms_view', 'important_links_view'],
        'items' => [
            [
                'permission' => 'website_view',
                'url' => 'modules/public_content_management.php',
                'icon' => '🌐',
                'label' => 'الموقع العام'
            ],
            [
                'permission' => 'important_links_view',
                'url' => 'modules/important_links_management.php',
                'icon' => '🔗',
                'label' => 'روابط مهمة'
            ],
            [
                'permission' => 'contact_view',
                'url' => 'modules/contact_management.php',
                'icon' => '📞',
                'label' => 'اتصل بنا'
            ],
            [
                'permission' => 'telegram_view',
                'url' => 'modules/telegram_messages.php',
                'icon' => '✈️',
                'label' => 'رسائل Telegram'
            ],
            [
                'permission' => 'sms_view',
                'url' => 'modules/sms.php',
                'icon' => '📱',
                'label' => 'الرسائل النصية'
            ]
        ]
    ],
    [
        'category' => '📊 التقارير والأرشفة',
        'permissions_check' => ['reports_view', 'archive_view'],
        'items' => [
            [
                'permission' => 'reports_view',
                'url' => 'modules/reports.php',
                'icon' => '📊',
                'label' => 'التقارير الموحدة',
                'class' => 'bg-gradient-to-l from-purple-700 font-semibold'
            ],
            [
                'permission' => 'archive_view',
                'url' => 'modules/archive.php',
                'icon' => '📁',
                'label' => 'الأرشيف الإلكتروني'
            ],
            [
                'permission' => 'reports_view',
                'url' => 'all_tables_manager.php',
                'icon' => '🗄️',
                'label' => 'الجداول المرجعية'
            ]
        ]
    ],
    [
        'category' => '⚙️ الإعدادات',
        'permissions_check' => ['settings_view', 'telegram_settings'],
        'items' => [
            [
                'permission' => 'settings_view',
                'url' => 'modules/system_settings.php',
                'icon' => '⚙️',
                'label' => 'إعدادات النظام'
            ],
            [
                'permission' => 'telegram_settings',
                'url' => 'modules/telegram_settings.php',
                'icon' => '✈️',
                'label' => 'إعدادات Telegram'
            ]
        ]
    ]
];

/**
 * دالة لعرض القائمة بناءً على الصلاحيات
 */
function renderMenu($menu_items) {
    foreach ($menu_items as $category) {
        // التحقق إذا كان المستخدم لديه أي صلاحية من الفئة
        // استثناء: قسم "الموقع والاتصالات" يظهر إذا كان المستخدم مسجل دخول (لإظهار روابط مهمة)
        $should_show_category = false;
        if ($category['category'] == '🌐 الموقع والاتصالات' && isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            $should_show_category = true;
        } else {
            $should_show_category = hasCategoryPermission($category['permissions_check']);
        }
        
        if (!$should_show_category) {
            continue;
        }

        // عرض رأس الفئة
        echo '<div class="mt-4 mb-2 px-4 border-t border-indigo-600 pt-3">';
        echo '<p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">' . $category['category'] . '</p>';
        echo '</div>';

        // عرض العناصر
        foreach ($category['items'] as $item) {
            // السماح بعرض روابط مهمة لجميع المستخدمين المسجلين (إذا لم تكن الصلاحية موجودة)
            $show_item = false;
            if ($item['permission'] == 'important_links_view') {
                // إذا كان المستخدم مسجل دخول، اعرض الرابط
                $show_item = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
                // أو إذا كان لديه الصلاحية
                if (!$show_item) {
                    $show_item = hasPermission($item['permission']);
                }
            } else {
                $show_item = hasPermission($item['permission']);
            }
            
            if ($show_item) {
                $class = isset($item['class']) ? $item['class'] : '';
                echo '<a href="' . $item['url'] . '" class="nav-item flex items-center py-2.5 px-4 rounded transition duration-200 hover:bg-indigo-700 ' . $class . '">';
                echo '    <span class="sidebar-icon">' . $item['icon'] . '</span>';
                echo '    <span class="mr-3' . (isset($item['class']) && strpos($item['class'], 'font-semibold') !== false ? ' font-semibold' : '') . '">' . $item['label'] . '</span>';
                echo '</a>';
            }
        }
    }
}
?>
