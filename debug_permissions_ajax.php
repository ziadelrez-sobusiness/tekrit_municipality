<?php
// ملف debug لفحص استجابة AJAX
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$database = new Database();
$db = $database->getConnection();

// افترض user_id = 2 (ziad)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 2;

try {
    $stmt = $db->prepare("
        SELECT p.*,
               CASE WHEN up.id IS NOT NULL THEN 1 ELSE 0 END as granted
        FROM permissions p
        LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.user_id = ? AND up.is_active = 1
        WHERE p.is_active = 1
        ORDER BY
            CASE p.category
                WHEN 'general_admin' THEN 1
                WHEN 'finance' THEN 2
                WHEN 'projects' THEN 3
                WHEN 'citizens' THEN 4
                WHEN 'services' THEN 5
                WHEN 'maps' THEN 6
                WHEN 'website' THEN 7
                WHEN 'reports' THEN 8
                WHEN 'settings' THEN 9
                ELSE 10
            END,
            p.sort_order,
            p.display_name
    ");
    $stmt->execute([$user_id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'total_permissions' => count($permissions),
        'permissions' => $permissions
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
