<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "غير مصرح";
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "معرف الرسالة غير موجود";
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        echo "الرسالة غير موجودة";
        exit();
    }
    
    ?>
    <div class="space-y-4">
        <div>
            <h4 class="font-semibold text-gray-900">المرسل:</h4>
            <p class="text-gray-600"><?= htmlspecialchars($message['sender_name']) ?></p>
        </div>
        
        <div>
            <h4 class="font-semibold text-gray-900">البريد الإلكتروني:</h4>
            <p class="text-gray-600"><?= htmlspecialchars($message['sender_email']) ?></p>
        </div>
        
        <?php if ($message['sender_phone']): ?>
        <div>
            <h4 class="font-semibold text-gray-900">رقم الهاتف:</h4>
            <p class="text-gray-600"><?= htmlspecialchars($message['sender_phone']) ?></p>
        </div>
        <?php endif; ?>
        
        <div>
            <h4 class="font-semibold text-gray-900">الموضوع:</h4>
            <p class="text-gray-600"><?= htmlspecialchars($message['subject']) ?></p>
        </div>
        
        <div>
            <h4 class="font-semibold text-gray-900">الرسالة:</h4>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($message['message']) ?></p>
            </div>
        </div>
        
        <div>
            <h4 class="font-semibold text-gray-900">تاريخ الإرسال:</h4>
            <p class="text-gray-600"><?= date('Y/m/d H:i:s', strtotime($message['created_at'])) ?></p>
        </div>
        
        <div>
            <h4 class="font-semibold text-gray-900">الحالة:</h4>
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                <?php 
                    switch($message['status']) {
                        case 'جديد': echo 'bg-red-100 text-red-800'; break;
                        case 'قيد المراجعة': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'تم الرد': echo 'bg-green-100 text-green-800'; break;
                        case 'مغلق': echo 'bg-gray-100 text-gray-800'; break;
                    }
                ?>">
                <?= $message['status'] ?>
            </span>
        </div>
    </div>
    <?php
    
} catch(PDOException $e) {
    http_response_code(500);
    echo "خطأ في قاعدة البيانات";
}
?> 