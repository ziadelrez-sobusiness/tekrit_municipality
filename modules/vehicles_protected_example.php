<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/auth_helper.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

// التحقق من صلاحية الوصول إلى إدارة الآليات
requirePermission('vehicles_view');

$database = new Database();
$db = $database->getConnection();
$user = $auth->getUserInfo();

$message = '';
$error = '';

// معالجة إضافة آلية جديدة - فقط للمخولين
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle']) && hasPermission('vehicles_add')) {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $license_plate = trim($_POST['license_plate']);
    
    if (!empty($name) && !empty($type) && !empty($license_plate)) {
        try {
            $query = "INSERT INTO vehicles (name, type, license_plate, status) VALUES (?, ?, ?, 'جاهز')";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $type, $license_plate]);
            $message = 'تم إضافة الآلية بنجاح!';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة الآلية: ' . $e->getMessage();
        }
    } else {
        $error = 'يرجى تعبئة الحقول المطلوبة';
    }
}

// معالجة التعديل - فقط للمخولين
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_vehicle']) && hasPermission('vehicles_edit')) {
    $vehicle_id = intval($_POST['vehicle_id']);
    $name = trim($_POST['name']);
    $status = $_POST['status'];
    
    try {
        $query = "UPDATE vehicles SET name = ?, status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$name, $status, $vehicle_id]);
        $message = 'تم تحديث الآلية بنجاح!';
    } catch (PDOException $e) {
        $error = 'خطأ في تحديث الآلية: ' . $e->getMessage();
    }
}

// جلب الآليات
try {
    $stmt = $db->query("SELECT * FROM vehicles ORDER BY created_at DESC LIMIT 10");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vehicles = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الآليات (محمية) - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../dashboard.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        ← العودة للوحة التحكم
                    </a>
                    <h1 class="mr-4 text-xl font-bold text-gray-900">إدارة الآليات</h1>
                </div>
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-gray-700">مرحباً، <?= htmlspecialchars($user['full_name']) ?></span>
                    <a href="../logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                        تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- رسائل النجاح والخطأ -->
        <?php if ($message): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Action Buttons - محمية بالصلاحيات -->
        <div class="mb-6 flex flex-wrap gap-4">
            <!-- زر الإضافة - فقط للمخولين -->
            <?php if (hasPermission('vehicles_add')): ?>
            <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg">
                ➕ إضافة آلية جديدة
            </button>
            <?php endif; ?>

            <!-- روابط إلى صفحات أخرى - محمية بالصلاحيات -->
            <?php if (hasPermission('hr_view')): ?>
            <a href="hr.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg inline-block">
                👥 إدارة الموظفين
            </a>
            <?php else: ?>
            <!-- زر معطل للمستخدمين غير المخولين -->
            <button disabled class="bg-gray-400 text-gray-600 px-6 py-2 rounded-lg cursor-not-allowed">
                👥 إدارة الموظفين (غير مخول)
            </button>
            <?php endif; ?>

            <?php if (hasPermission('finance_view')): ?>
            <a href="finance.php" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg inline-block">
                💰 الشؤون المالية
            </a>
            <?php endif; ?>
        </div>

        <!-- Vehicles List -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">قائمة الآليات</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم الآلية</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">النوع</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم اللوحة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                                
                                <!-- عمود التكلفة - فقط للمخولين مالياً -->
                                <?php if (hasPermission('finance_view')): ?>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">التكلفة</th>
                                <?php endif; ?>
                                
                                <!-- عمود الإجراءات - يظهر فقط إذا كان لدى المستخدم صلاحيات تحرير -->
                                <?php if (hasPermission('vehicles_edit') || hasPermission('vehicles_delete')): ?>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($vehicle['name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($vehicle['type'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($vehicle['license_plate'] ?? 'غير محدد') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        <?= ($vehicle['status'] ?? 'جاهز') == 'جاهز' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                        <?= htmlspecialchars($vehicle['status'] ?? 'جاهز') ?>
                                    </span>
                                </td>
                                
                                <!-- عمود التكلفة - للمخولين فقط -->
                                <?php if (hasPermission('finance_view')): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    $<?= number_format($vehicle['acquisition_cost'] ?? 0) ?>
                                </td>
                                <?php endif; ?>
                                
                                <!-- أزرار الإجراءات - محمية بالصلاحيات -->
                                <?php if (hasPermission('vehicles_edit') || hasPermission('vehicles_delete')): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if (hasPermission('vehicles_edit')): ?>
                                    <button onclick="openEditModal(<?= $vehicle['id'] ?>, '<?= addslashes($vehicle['name']) ?>', '<?= $vehicle['status'] ?? 'جاهز' ?>')" 
                                            class="text-indigo-600 hover:text-indigo-900 ml-4">
                                        تعديل
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('vehicles_delete')): ?>
                                    <button onclick="deleteVehicle(<?= $vehicle['id'] ?>)" 
                                            class="text-red-600 hover:text-red-900">
                                        حذف
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Alert for limited permissions -->
        <?php if (!hasPermission('vehicles_add') && !hasPermission('vehicles_edit')): ?>
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <div class="text-yellow-400 text-xl">⚠️</div>
                </div>
                <div class="mr-3">
                    <h3 class="text-sm font-medium text-yellow-800">صلاحيات محدودة</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        يمكنك عرض البيانات فقط. للحصول على صلاحيات إضافة أو تعديل الآليات، يرجى التواصل مع المدير.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Vehicle Modal - يظهر فقط للمخولين -->
    <?php if (hasPermission('vehicles_add')): ?>
    <div id="addModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 text-center">إضافة آلية جديدة</h3>
                <form method="POST" class="mt-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">اسم الآلية</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">النوع</label>
                        <select name="type" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="">اختر النوع</option>
                            <option value="سيارة">سيارة</option>
                            <option value="شاحنة">شاحنة</option>
                            <option value="حافلة">حافلة</option>
                            <option value="معدة ثقيلة">معدة ثقيلة</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">رقم اللوحة</label>
                        <input type="text" name="license_plate" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="flex justify-between">
                        <button type="submit" name="add_vehicle" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                            إضافة
                        </button>
                        <button type="button" onclick="closeAddModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Vehicle Modal - يظهر فقط للمخولين -->
    <?php if (hasPermission('vehicles_edit')): ?>
    <div id="editModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 text-center">تعديل الآلية</h3>
                <form method="POST" class="mt-4">
                    <input type="hidden" id="edit_vehicle_id" name="vehicle_id">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">اسم الآلية</label>
                        <input type="text" id="edit_name" name="name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">الحالة</label>
                        <select id="edit_status" name="status" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="جاهز">جاهز</option>
                            <option value="قيد الصيانة">قيد الصيانة</option>
                            <option value="معطل">معطل</option>
                        </select>
                    </div>
                    <div class="flex justify-between">
                        <button type="submit" name="edit_vehicle" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            تحديث
                        </button>
                        <button type="button" onclick="closeEditModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(id, name, status) {
            document.getElementById('edit_vehicle_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_status').value = status;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function deleteVehicle(id) {
            if (confirm('هل أنت متأكد من حذف هذه الآلية؟')) {
                // إرسال طلب حذف
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="delete_vehicle" value="1">
                                 <input type="hidden" name="vehicle_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 
