<?php
echo "بدء إصلاح مشكلة z-index للنافذة المنبثقة...\n";

$file = 'public/facilities-map.php';
$content = file_get_contents($file);

$oldCode = "document.getElementById('facilityModalContent').innerHTML = modalContent;
            document.getElementById('facilityModal').classList.remove('hidden');";

$newCode = "document.getElementById('facilityModalContent').innerHTML = modalContent;
            
            // إظهار النافذة مع التأكد من z-index صحيح
            const modal = document.getElementById('facilityModal');
            modal.classList.remove('hidden');
            
            // التأكد من أن النافذة تظهر أمام الخريطة
            modal.style.zIndex = '99999';
            modal.style.position = 'fixed';
            
            // التأكد من أن الخريطة خلف النافذة
            const mapElement = document.getElementById('map');
            if (mapElement) {
                mapElement.style.zIndex = '1';
            }
            
            // التأكد من أن عناصر Leaflet أيضاً خلف النافذة
            const leafletContainer = document.querySelector('.leaflet-container');
            if (leafletContainer) {
                leafletContainer.style.zIndex = '1';
            }";

$content = str_replace($oldCode, $newCode, $content);

if (file_put_contents($file, $content)) {
    echo "✅ تم إصلاح مشكلة z-index للنافذة المنبثقة بنجاح!\n";
    echo "الآن ستظهر نافذة التفاصيل أمام الخريطة وليس خلفها.\n";
} else {
    echo "❌ حدث خطأ في تحديث الملف!\n";
}
?> 