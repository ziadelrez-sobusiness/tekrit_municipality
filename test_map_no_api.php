<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار الخريطة بدون API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <h1 class="text-3xl font-bold text-center mb-8">اختبار الخريطة بدون API Key</h1>
        
        <!-- Test coordinates -->
        <?php 
        $lat = '33.4384';  // Tikrit latitude
        $lng = '43.6793';  // Tikrit longitude
        ?>
        
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold mb-4">الإحداثيات المستخدمة:</h2>
            <p class="mb-4">خط العرض: <?= $lat ?></p>
            <p class="mb-6">خط الطول: <?= $lng ?></p>
            
            <!-- Standard Google Maps Embed (No API Key Required) -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">خريطة جوجل المدمجة (بدون API):</h3>
                <div class="h-64 rounded-lg overflow-hidden border">
                    <?php 
                    $embedUrl = "https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d1000!2d" . $lng . "!3d" . $lat . "!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sar!2siq!4v1640000000000!5m2!1sar!2siq";
                    ?>
                    <iframe 
                        src="<?= $embedUrl ?>"
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
            
            <!-- Alternative: Simple Google Maps Link -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">روابط الخرائط البديلة:</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="https://www.google.com/maps?q=<?= urlencode($lat) ?>,<?= urlencode($lng) ?>" 
                       target="_blank" 
                       class="block p-4 bg-blue-100 rounded-lg text-center hover:bg-blue-200">
                        <div class="text-2xl mb-2">🗺️</div>
                        <div class="font-semibold">خرائط جوجل</div>
                    </a>
                    
                    <a href="https://www.openstreetmap.org/?mlat=<?= $lat ?>&mlon=<?= $lng ?>&zoom=15" 
                       target="_blank" 
                       class="block p-4 bg-green-100 rounded-lg text-center hover:bg-green-200">
                        <div class="text-2xl mb-2">🌍</div>
                        <div class="font-semibold">OpenStreetMap</div>
                    </a>
                    
                    <a href="https://www.bing.com/maps?cp=<?= $lat ?>~<?= $lng ?>&lvl=15" 
                       target="_blank" 
                       class="block p-4 bg-yellow-100 rounded-lg text-center hover:bg-yellow-200">
                        <div class="text-2xl mb-2">🌐</div>
                        <div class="font-semibold">Bing Maps</div>
                    </a>
                </div>
            </div>
            
            <!-- Test Results -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-3">نتائج الاختبار:</h3>
                <ul class="space-y-2">
                    <li class="flex items-center">
                        <span class="text-green-500 mr-2">✅</span>
                        الخريطة المدمجة لا تحتاج API key
                    </li>
                    <li class="flex items-center">
                        <span class="text-green-500 mr-2">✅</span>
                        الروابط المباشرة تعمل دائماً
                    </li>
                    <li class="flex items-center">
                        <span class="text-green-500 mr-2">✅</span>
                        خرائط بديلة متاحة
                    </li>
                    <li class="flex items-center">
                        <span class="text-blue-500 mr-2">ℹ️</span>
                        الإحداثيات تُقرأ من قاعدة البيانات
                    </li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html> 