-- إضافة صلاحية إدارة روابط مهمة
-- بلدية تكريت

-- إضافة الصلاحية في جدول permissions
INSERT IGNORE INTO permissions (permission_name, display_name, description, category, module_name, page_url, icon, sort_order) 
VALUES (
    'important_links_view',
    'إدارة روابط مهمة',
    'عرض وإدارة روابط مهمة (المرافق العامة)',
    'website',
    'important_links',
    'modules/important_links_management.php',
    '🔗',
    640
);

-- إضافة الصلاحية لجميع المستخدمين من نوع admin تلقائياً
-- (إذا كان النظام يستخدم user_permissions مباشرة)
INSERT IGNORE INTO user_permissions (user_id, permission_id, is_active)
SELECT u.id, p.id, 1
FROM users u
CROSS JOIN permissions p
WHERE (u.user_type = 'admin' OR u.user_type = 'manager') 
  AND p.permission_name = 'important_links_view'
  AND NOT EXISTS (
      SELECT 1 FROM user_permissions up 
      WHERE up.user_id = u.id AND up.permission_id = p.id
  );

