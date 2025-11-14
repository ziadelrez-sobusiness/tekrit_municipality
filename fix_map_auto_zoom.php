<?php
echo "<h1>🔧 إصلاح مركز الخريطة التلقائي</h1>";

$map_file = 'public/facilities-map.php';
$content = file_get_contents($map_file);

// البحث عن الدالة الحالية وإصلاحها
$old_function = '        // عرض المرافق على الخريطة
        function displayFacilitiesOnMap(facilities) {
            // مسح النقاط السابقة
            clearMarkers();

            facilities.forEach(facility => {
                const marker = createFacilityMarker(facility);
                markers.push(marker);
                
                if (MAP_CONFIG.enableClustering) {
                    markerClusterGroup.addLayer(marker);
                } else {
                    marker.addTo(map);
                }
            });
        }';

$new_function = '        // عرض المرافق على الخريطة
        function displayFacilitiesOnMap(facilities) {
            // مسح النقاط السابقة
            clearMarkers();

            if (facilities.length === 0) {
                return;
            }

            facilities.forEach(facility => {
                const marker = createFacilityMarker(facility);
                markers.push(marker);
                
                if (MAP_CONFIG.enableClustering) {
                    markerClusterGroup.addLayer(marker);
                } else {
                    marker.addTo(map);
                }
            });

            // ضبط مركز الخريطة والزوم تلقائياً بناءً على المرافق
            autoFitMapToFacilities(facilities);
        }

        // ضبط مركز الخريطة تلقائياً بناءً على المرافق الموجودة
        function autoFitMapToFacilities(facilities) {
            if (facilities.length === 0) return;

            if (facilities.length === 1) {
                // إذا كان هناك مرفق واحد فقط، اعرضه بزوم 15
                map.setView([facilities[0].latitude, facilities[0].longitude], 15);
                console.log(`تركيز على مرفق واحد: ${facilities[0].name_ar} في ${facilities[0].latitude}, ${facilities[0].longitude}`);
            } else {
                // إذا كان هناك أكثر من مرفق، احسب الحدود واعرض جميع المرافق
                const latLngs = facilities.map(facility => [facility.latitude, facility.longitude]);
                const bounds = L.latLngBounds(latLngs);
                map.fitBounds(bounds.pad(0.1)); // إضافة هامش 10%
                console.log(`تركيز على ${facilities.length} مرافق`);
            }
        }';

// استبدال الدالة القديمة بالجديدة
$updated_content = str_replace($old_function, $new_function, $content);

// إضافة معالج خاص لأول تحميل للبيانات
$init_load_old = '        // تحميل المرافق
        async function loadFacilities(search = \'\', categoryId = \'\') {
            showLoading(true);
            
            try {
                const params = new URLSearchParams({
                    action: \'get_facilities\',
                    search: search,
                    category: categoryId,
                    lang: MAP_CONFIG.language
                });

                const response = await fetch(\'../modules/facilities_api.php?\' + params);
                const data = await response.json();

                if (data.success) {
                    allFacilities = data.facilities;
                    displayFacilitiesOnMap(data.facilities);
                    updateFacilityCount(data.facilities.length);
                } else {
                    console.error(\'Error loading facilities:\', data.error);
                }
            } catch (error) {
                console.error(\'Error:\', error);
            } finally {
                showLoading(false);
            }
        }';

$init_load_new = '        // تحميل المرافق
        async function loadFacilities(search = \'\', categoryId = \'\') {
            showLoading(true);
            
            try {
                const params = new URLSearchParams({
                    action: \'get_facilities\',
                    search: search,
                    category: categoryId,
                    lang: MAP_CONFIG.language
                });

                const response = await fetch(\'../modules/facilities_api.php?\' + params);
                const data = await response.json();

                if (data.success) {
                    allFacilities = data.facilities;
                    displayFacilitiesOnMap(data.facilities);
                    updateFacilityCount(data.facilities.length);
                    
                    // إذا كان هذا التحميل الأول وهناك مرافق، ركز عليها
                    if (data.facilities.length > 0 && !search && !categoryId) {
                        console.log("تحميل أول مرة - التركيز على المرافق");
                    }
                } else {
                    console.error(\'Error loading facilities:\', data.error);
                    console.log("لا توجد مرافق للعرض");
                }
            } catch (error) {
                console.error(\'Error:\', error);
            } finally {
                showLoading(false);
            }
        }';

$updated_content = str_replace($init_load_old, $init_load_new, $updated_content);

// إضافة تحميل تلقائي عند تحميل الصفحة
$init_old = '        // تهيئة الصفحة
        document.addEventListener(\'DOMContentLoaded\', function() {
            initMap();';

$init_new = '        // تهيئة الصفحة
        document.addEventListener(\'DOMContentLoaded\', function() {
            initMap();
            
            // تحميل المرافق تلقائياً عند فتح الصفحة
            setTimeout(() => {
                loadFacilities();
            }, 1000); // انتظار ثانية واحدة لضمان تحميل الخريطة';

$updated_content = str_replace($init_old, $init_new, $updated_content);

// حفظ الملف المحدث
if (file_put_contents($map_file, $updated_content)) {
    echo "<div style='background: #f0fdf4; border: 1px solid #16a34a; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3 style='color: #16a34a; margin: 0 0 10px 0;'>✅ تم إصلاح مركز الخريطة بنجاح!</h3>";
    echo "<p>الآن الخريطة ستتركز تلقائياً على المرافق الموجودة عند فتح الصفحة.</p>";
    echo "</div>";
    
    echo "<h2>🔧 التحسينات المضافة:</h2>";
    echo "<ul style='margin: 10px 0; padding-right: 20px; line-height: 1.8;'>";
    echo "<li>✅ <strong>تركيز تلقائي:</strong> الخريطة تتركز على المرافق عند فتح الصفحة</li>";
    echo "<li>✅ <strong>زوم ذكي:</strong> إذا كان مرفق واحد - زوم 15، إذا كان عدة مرافق - يعرض الكل</li>";
    echo "<li>✅ <strong>تحميل تلقائي:</strong> المرافق تُحمل تلقائياً عند فتح الصفحة</li>";
    echo "<li>✅ <strong>هامش إضافي:</strong> 10% هامش حول المرافق لعرض أفضل</li>";
    echo "</ul>";
    
    echo "<h2>🌍 كيف يعمل الآن:</h2>";
    echo "<ol style='margin: 10px 0; padding-right: 20px; line-height: 1.8;'>";
    echo "<li><strong>عند فتح الصفحة:</strong> تُحمل المرافق تلقائياً</li>";
    echo "<li><strong>مرفق واحد:</strong> تتركز الخريطة عليه بزوم 15</li>";
    echo "<li><strong>عدة مرافق:</strong> تُعرض جميع المرافق في إطار واحد</li>";
    echo "<li><strong>البحث والفلترة:</strong> تحافظ على التركيز على النتائج الجديدة</li>";
    echo "</ol>";
    
} else {
    echo "<div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3 style='color: #dc2626; margin: 0 0 10px 0;'>❌ فشل في حفظ التعديلات!</h3>";
    echo "<p>تحقق من صلاحيات الكتابة على الملف.</p>";
    echo "</div>";
}

echo "<h2>🔗 اختبر الآن:</h2>";
echo "<div style='margin: 20px 0;'>";
echo "<a href='public/facilities-map.php' target='_blank' style='background: #2563eb; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block; font-size: 18px; font-weight: bold;'>🗺️ اختبر الخريطة الآن</a>";
echo "<a href='modules/facilities_management.php' target='_blank' style='background: #f59e0b; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 10px; display: inline-block;'>⚙️ إدارة المرافق</a>";
echo "</div>";

echo "<h3>💡 ملاحظة مهمة:</h3>";
echo "<div style='background: #fffbeb; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p style='margin: 0;'><strong>تأكد من وجود مرافق في قاعدة البيانات مع إحداثيات صحيحة.</strong> إذا لم تكن هناك مرافق، ستظل الخريطة على المركز الافتراضي.</p>";
echo "</div>";
?> 