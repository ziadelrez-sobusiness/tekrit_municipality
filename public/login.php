<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دخول المواطن - بلدية تكريت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }

        /* تحسينات responsive */
        @media (max-width: 640px) {
            .emoji-large {
                font-size: 3rem;
            }
            h1 {
                font-size: 1.75rem;
                line-height: 2.25rem;
            }
            h2 {
                font-size: 1.5rem;
                line-height: 2rem;
            }
            .emoji-medium {
                font-size: 2.5rem;
            }
            input {
                font-size: 16px; /* منع zoom في iOS */
            }
            button {
                min-height: 44px; /* حجم touch friendly */
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">

        <!-- الشعار -->
        <div class="text-center mb-6 md:mb-8">
            <div class="text-6xl md:text-8xl mb-3 md:mb-4 emoji-large">🏛️</div>
            <h1 class="text-2xl md:text-4xl font-black text-gray-800 mb-2">بلدية تكريت - عكار</h1>
            <p class="text-sm md:text-base text-gray-600">شمال لبنان</p>
        </div>

        <!-- نموذج الدخول -->
        <div class="bg-white rounded-2xl md:rounded-3xl shadow-2xl p-6 md:p-8 mb-4 md:mb-6">
            <div class="text-center mb-4 md:mb-6">
                <div class="text-4xl md:text-6xl mb-3 md:mb-4 emoji-medium">🔐</div>
                <h2 class="text-xl md:text-3xl font-bold text-gray-800 mb-2">دخول المواطن</h2>
                <p class="text-sm md:text-base text-gray-600">أدخل رمز الدخول الخاص بك</p>
            </div>

            <form action="citizen-dashboard.php" method="GET" class="space-y-4 md:space-y-6" id="loginForm">
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-base md:text-lg">رمز الدخول</label>
                    <div class="flex items-center border-2 border-gray-300 rounded-xl focus-within:border-blue-500 bg-white overflow-hidden" style="direction: ltr;">
                        <div class="px-3 md:px-4 py-3 md:py-4 text-lg md:text-2xl font-bold text-gray-500 flex items-center">
                            <span>TKT-</span>
                        </div>
                        <input type="text"
                               id="codeInput"
                               class="flex-1 px-3 md:px-4 py-3 md:py-4 border-0 focus:ring-0 focus:outline-none text-center text-xl md:text-2xl font-bold tracking-wider"
                               placeholder="12345"
                               required
                               maxlength="5"
                               pattern="[0-9]{5}"
                               title="أدخل 5 أرقام فقط"
                               inputmode="numeric"
                               autofocus>
                    </div>
                    <input type="hidden" name="code" id="fullCode">
                    <p class="text-gray-500 text-xs md:text-sm mt-2 text-center">أدخل 5 أرقام فقط (مثال: 12345)</p>
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 md:py-4 rounded-xl font-bold hover:from-blue-700 hover:to-purple-700 transition text-base md:text-xl shadow-lg">
                    🔓 دخول
                </button>
            </form>
            
            <script>
                document.getElementById('loginForm').addEventListener('submit', function(e) {
                    const inputField = document.getElementById('codeInput');
                    const codeInput = inputField.value.trim().replace(/\D/g, '');
                    inputField.value = codeInput;
                    
                    if (codeInput.length !== 5) {
                        e.preventDefault();
                        alert('الرجاء إدخال 5 أرقام');
                        return false;
                    }
                    document.getElementById('fullCode').value = 'TKT-' + codeInput;
                });
            </script>
        </div>

        <!-- روابط إضافية -->
        <div class="bg-white rounded-xl md:rounded-2xl shadow-xl p-4 md:p-6">
            <p class="text-center text-gray-700 font-bold mb-3 md:mb-4 text-sm md:text-base">لا تملك رمز دخول؟</p>

            <div class="space-y-2 md:space-y-3">
                <a href="citizen-requests.php"
                   class="block bg-green-600 text-white px-4 md:px-6 py-2.5 md:py-3 rounded-xl font-bold hover:bg-green-700 transition text-center text-sm md:text-base">
                    📝 تقديم طلب جديد
                </a>

                <a href="track-request.php"
                   class="block bg-purple-600 text-white px-4 md:px-6 py-2.5 md:py-3 rounded-xl font-bold hover:bg-purple-700 transition text-center text-sm md:text-base">
                    🔍 تتبع طلب موجود
                </a>

                <a href="index.php"
                   class="block bg-gray-600 text-white px-4 md:px-6 py-2.5 md:py-3 rounded-xl font-bold hover:bg-gray-700 transition text-center text-sm md:text-base">
                    🏠 الصفحة الرئيسية
                </a>
            </div>
        </div>

        <!-- معلومات -->
        <div class="mt-4 md:mt-6 bg-blue-50 border-2 border-blue-300 rounded-xl md:rounded-2xl p-4 md:p-6">
            <h3 class="font-bold text-blue-900 mb-2 md:mb-3 text-base md:text-lg">💡 كيف أحصل على رمز الدخول؟</h3>
            <ul class="text-blue-800 space-y-1.5 md:space-y-2 text-xs md:text-sm">
                <li class="flex items-start gap-2">
                    <span class="flex-shrink-0">1️⃣</span>
                    <span>قدّم طلب جديد من خلال صفحة "تقديم طلب"</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex-shrink-0">2️⃣</span>
                    <span>ستحصل على رمز دخول ثابت بعد تقديم الطلب</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex-shrink-0">3️⃣</span>
                    <span>استخدم هذا الرمز للدخول لحسابك في أي وقت</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex-shrink-0">4️⃣</span>
                    <span>احتفظ بالرمز في مكان آمن</span>
                </li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="mt-4 md:mt-6 text-center text-gray-600">
            <p class="text-xs md:text-sm">© 2025 بلدية تكريت - عكار، شمال لبنان 🇱🇧</p>
        </div>
    </div>
</body>
</html>

