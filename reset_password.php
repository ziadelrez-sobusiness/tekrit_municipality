<?php
/**
 * صفحة إعادة تعيين كلمة المرور
 * استخدم هذه الصفحة لتغيير كلمة مرور أي مستخدم
 */

$message = '';
$error = '';

// الاتصال بقاعدة البيانات
try {
    $db = new PDO('mysql:host=localhost;dbname=tekrit_municipality;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
}

// جلب قائمة المستخدمين
$users = [];
try {
    $stmt = $db->query("SELECT id, username, full_name, email FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'خطأ في جلب المستخدمين: ' . $e->getMessage();
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($user_id) || empty($new_password)) {
        $error = 'يرجى اختيار المستخدم وإدخال كلمة المرور الجديدة';
    } elseif ($new_password !== $confirm_password) {
        $error = 'كلمة المرور وتأكيد كلمة المرور غير متطابقتين';
    } elseif (strlen($new_password) < 4) {
        $error = 'كلمة المرور يجب أن تكون 4 أحرف على الأقل';
    } else {
        try {
            // تشفير كلمة المرور الجديدة
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // تحديث كلمة المرور في قاعدة البيانات
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // جلب معلومات المستخدم
            $stmt = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message = "✅ تم تغيير كلمة المرور بنجاح!<br>";
            $message .= "👤 المستخدم: " . htmlspecialchars($user['username']) . "<br>";
            $message .= "🔑 كلمة المرور الجديدة: <strong>" . htmlspecialchars($new_password) . "</strong><br>";
            $message .= "🔐 الهاش: <code style='font-size:10px;'>" . htmlspecialchars($hashed_password) . "</code>";
            
        } catch (Exception $e) {
            $error = 'خطأ في تحديث كلمة المرور: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-pink-50 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto">
        
        <!-- العنوان -->
        <div class="bg-white rounded-3xl shadow-2xl p-8 mb-8 text-center">
            <div class="text-9xl mb-4">🔑</div>
            <h1 class="text-5xl font-black text-purple-600 mb-3">
                إعادة تعيين كلمة المرور
            </h1>
            <p class="text-xl text-gray-600">بلدية تكريت - عكار</p>
        </div>

        <!-- الرسائل -->
        <?php if ($message): ?>
        <div class="bg-green-100 border-2 border-green-400 rounded-2xl p-6 mb-8">
            <div class="text-center">
                <div class="text-6xl mb-4">✅</div>
                <div class="text-green-800 text-lg">
                    <?= $message ?>
                </div>
                <a href="login.php" class="inline-block mt-6 bg-green-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                    🔓 الذهاب لصفحة تسجيل الدخول
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border-2 border-red-400 rounded-2xl p-6 mb-8">
            <div class="text-center text-red-800 text-lg">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- النموذج -->
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">🔐 تغيير كلمة المرور</h2>
            
            <form method="POST" class="space-y-6">
                <!-- اختيار المستخدم -->
                <div>
                    <label for="user_id" class="block text-lg font-bold text-gray-700 mb-3">
                        👤 اختر المستخدم:
                    </label>
                    <select name="user_id" id="user_id" required
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg">
                        <option value="">-- اختر المستخدم --</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>">
                            <?= htmlspecialchars($user['username']) ?> 
                            (<?= htmlspecialchars($user['full_name']) ?>)
                            <?php if ($user['email']): ?>
                                - <?= htmlspecialchars($user['email']) ?>
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- كلمة المرور الجديدة -->
                <div>
                    <label for="new_password" class="block text-lg font-bold text-gray-700 mb-3">
                        🔑 كلمة المرور الجديدة:
                    </label>
                    <input type="text" name="new_password" id="new_password" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg"
                           placeholder="أدخل كلمة المرور الجديدة (4 أحرف على الأقل)">
                    <p class="text-sm text-gray-500 mt-2">💡 استخدم type="text" لرؤية كلمة المرور أثناء الكتابة</p>
                </div>

                <!-- تأكيد كلمة المرور -->
                <div>
                    <label for="confirm_password" class="block text-lg font-bold text-gray-700 mb-3">
                        🔁 تأكيد كلمة المرور:
                    </label>
                    <input type="text" name="confirm_password" id="confirm_password" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg"
                           placeholder="أعد إدخال كلمة المرور">
                </div>

                <!-- زر الإرسال -->
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-4 px-6 rounded-lg hover:from-purple-700 hover:to-pink-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition duration-200 font-bold text-xl">
                    🔄 تغيير كلمة المرور
                </button>
            </form>
        </div>

        <!-- قائمة المستخدمين -->
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">👥 قائمة المستخدمين</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-purple-100">
                            <th class="px-4 py-3 text-right font-bold text-purple-900">ID</th>
                            <th class="px-4 py-3 text-right font-bold text-purple-900">اسم المستخدم</th>
                            <th class="px-4 py-3 text-right font-bold text-purple-900">الاسم الكامل</th>
                            <th class="px-4 py-3 text-right font-bold text-purple-900">البريد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr class="border-b hover:bg-purple-50">
                            <td class="px-4 py-3"><?= $user['id'] ?></td>
                            <td class="px-4 py-3 font-bold"><?= htmlspecialchars($user['username']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($user['full_name']) ?></td>
                            <td class="px-4 py-3 text-sm"><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ملاحظات -->
        <div class="bg-yellow-50 border-2 border-yellow-400 rounded-2xl p-8 mb-8">
            <h2 class="text-3xl font-bold text-yellow-900 mb-4 text-center">💡 ملاحظات مهمة</h2>
            
            <div class="space-y-4 text-yellow-800">
                <div class="bg-white rounded-lg p-4">
                    <p class="font-bold mb-2">🔐 عن الهاش الذي أرسلته:</p>
                    <code class="text-xs bg-yellow-100 px-2 py-1 rounded block break-all">
                        $2y$10$zyPnmhh.FjdtGOXJC4SBcO60YxP1/yeSbEWoBb/t0wLJ3B8C.GAQq
                    </code>
                    <p class="mt-2 text-sm">
                        ❌ هذا هو الهاش (النص المشفر) وليس كلمة المرور الأصلية.<br>
                        ❌ لا يمكن معرفة كلمة المرور الأصلية من الهاش.<br>
                        ✅ الحل: استخدم هذه الصفحة لتعيين كلمة مرور جديدة.
                    </p>
                </div>
                
                <div class="bg-white rounded-lg p-4">
                    <p class="font-bold mb-2">🔒 الأمان:</p>
                    <ul class="space-y-1 text-sm mr-6">
                        <li>✅ كلمات المرور مشفرة باستخدام <code class="bg-yellow-200 px-1 rounded">bcrypt</code></li>
                        <li>✅ لا يمكن فك تشفير الهاش للحصول على كلمة المرور الأصلية</li>
                        <li>✅ هذا يحمي المستخدمين حتى لو تم اختراق قاعدة البيانات</li>
                    </ul>
                </div>
                
                <div class="bg-white rounded-lg p-4">
                    <p class="font-bold mb-2">📝 كيفية الاستخدام:</p>
                    <ol class="space-y-1 text-sm mr-6">
                        <li>1️⃣ اختر المستخدم من القائمة</li>
                        <li>2️⃣ أدخل كلمة المرور الجديدة (مثلاً: <code class="bg-yellow-200 px-1 rounded">admin123</code>)</li>
                        <li>3️⃣ أعد إدخال كلمة المرور للتأكيد</li>
                        <li>4️⃣ اضغط "تغيير كلمة المرور"</li>
                        <li>5️⃣ استخدم كلمة المرور الجديدة لتسجيل الدخول</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- روابط سريعة -->
        <div class="bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl shadow-2xl p-8 text-white text-center">
            <h2 class="text-3xl font-bold mb-6">🔗 روابط سريعة</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="login.php" 
                   class="bg-white bg-opacity-20 hover:bg-opacity-30 rounded-xl p-6 transition">
                    <div class="text-5xl mb-2">🔓</div>
                    <p class="font-bold text-xl">تسجيل الدخول</p>
                </a>
                
                <a href="comprehensive_dashboard.php" 
                   class="bg-white bg-opacity-20 hover:bg-opacity-30 rounded-xl p-6 transition">
                    <div class="text-5xl mb-2">🏠</div>
                    <p class="font-bold text-xl">لوحة التحكم</p>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-gray-600">
            <p class="font-bold text-2xl mb-2">🏛️ بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
            <p class="text-sm text-gray-500 mt-4">
                ⚠️ احذف هذا الملف بعد إعادة تعيين كلمة المرور لأسباب أمنية
            </p>
        </div>
    </div>

    <script>
        // التحقق من تطابق كلمات المرور
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            
            if (password && confirm && password !== confirm) {
                this.style.borderColor = '#f87171';
            } else {
                this.style.borderColor = '#d1d5db';
            }
        });
    </script>
</body>
</html>

