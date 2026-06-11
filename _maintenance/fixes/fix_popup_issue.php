<?php
// ملف لإصلاح مشكلة النافذة المنبثقة
// المشكلة: النافذة المنبثقة تظهر تفاصيل كثيرة، المطلوب تبسيطها

// قراءة محتوى الملف الأصلي
$filePath = 'public/facilities-map.php';
$content = file_get_contents($filePath);

// البحث عن دالة createPopupContent وتبديلها
$oldFunction = "        // إنشاء محتوى النافذة المنبثقة
        function createPopupContent(facility) {
            const name = MAP_CONFIG.language === 'ar' ? facility.name_ar : (facility.name_en || facility.name_ar);
            const categoryName = MAP_CONFIG.language === 'ar' ? facility.category_name_ar : (facility.category_name_en || facility.category_name_ar);
            
            let content = `
                <div class=\"facility-popup\">
                    \${facility.image_path ? 
                        `<img src=\"../uploads/facilities/\${facility.image_path}\" alt=\"\${name}\" onerror=\"this.style.display='none'\">` 
                        : ''
                    }
                    <h3 class=\"font-bold text-lg mb-2\">\${name}</h3>
                    <p class=\"text-sm text-gray-600 mb-2\">
                        <span class=\"inline-block px-2 py-1 rounded text-xs text-white\" style=\"background-color: \${facility.category_color}\">
                            \${categoryName}
                        </span>
                    </p>
            `;

            if (facility.description_ar || facility.description_en) {
                const description = MAP_CONFIG.language === 'ar' ? facility.description_ar : (facility.description_en || facility.description_ar);
                content += `<p class=\"text-sm mb-3\">\${description}</p>`;
            }

            content += `
                    <div class=\"flex flex-wrap gap-2 mb-3\">
                        <button onclick=\"getDirections(\${facility.latitude}, \${facility.longitude})\" 
                                class=\"bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600\">
                            🧭 \${TEXTS.get_directions}
                        </button>
            `;

            if (facility.phone) {
                content += `
                        <a href=\"tel:\${facility.phone}\" 
                           class=\"bg-green-500 text-white px-3 py-1 rounded text-xs hover:bg-green-600\">
                            📞 \${TEXTS.call_now}
                        </a>
                `;
            }

            if (facility.website) {
                content += `
                        <a href=\"\${facility.website}\" target=\"_blank\" 
                           class=\"bg-purple-500 text-white px-3 py-1 rounded text-xs hover:bg-purple-600\">
                            🌐 \${TEXTS.website}
                        </a>
                `;
            }

            content += `
                    </div>
                    <button onclick=\"showFacilityDetails(\${JSON.stringify(facility).replace(/\"/g, '&quot;')})\" 
                            class=\"w-full bg-gray-800 text-white py-2 rounded text-sm hover:bg-gray-900\">
                        📋 \${MAP_CONFIG.language === 'ar' ? 'تفاصيل أكثر' : 'More Details'}
                    </button>
                </div>
            `;

            return content;
        }";

$newFunction = "        // إنشاء محتوى النافذة المنبثقة (مبسط)
        function createPopupContent(facility) {
            const name = MAP_CONFIG.language === 'ar' ? facility.name_ar : (facility.name_en || facility.name_ar);
            const categoryName = MAP_CONFIG.language === 'ar' ? facility.category_name_ar : (facility.category_name_en || facility.category_name_ar);
            
            let content = `
                <div class=\"facility-popup\">
                    \${facility.image_path ? 
                        `<img src=\"../uploads/facilities/\${facility.image_path}\" alt=\"\${name}\" class=\"w-20 h-20 object-cover rounded-lg mx-auto mb-2\" onerror=\"this.style.display='none'\">` 
                        : ''
                    }
                    <h3 class=\"font-bold text-lg mb-2 text-center\">\${name}</h3>
                    <p class=\"text-sm text-gray-600 mb-3 text-center\">
                        <span class=\"inline-block px-2 py-1 rounded text-xs text-white\" style=\"background-color: \${facility.category_color}\">
                            \${categoryName}
                        </span>
                    </p>
                    
                    <div class=\"flex justify-center mb-3\">
                        <button onclick=\"getDirections(\${facility.latitude}, \${facility.longitude})\" 
                                class=\"bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600 mr-2\">
                            🧭 \${TEXTS.get_directions}
                        </button>
            `;

            if (facility.phone) {
                content += `
                        <a href=\"tel:\${facility.phone}\" 
                           class=\"bg-green-500 text-white px-3 py-1 rounded text-xs hover:bg-green-600\">
                            📞 \${TEXTS.call_now}
                        </a>`;
            }

            content += `
                    </div>
                    
                    <button onclick=\"showFacilityDetails(\${JSON.stringify(facility).replace(/\"/g, '&quot;')})\" 
                            class=\"w-full bg-indigo-600 text-white py-2 rounded text-sm hover:bg-indigo-700 font-semibold\">
                        📋 \${MAP_CONFIG.language === 'ar' ? 'عرض التفاصيل الكاملة' : 'View Full Details'}
                    </button>
                </div>
            `;

            return content;
        }";

// استبدال الدالة
$newContent = str_replace($oldFunction, $newFunction, $content);

// حفظ الملف المحدث
if (file_put_contents($filePath, $newContent)) {
    echo "تم إصلاح النافذة المنبثقة بنجاح!\n";
    echo "الآن النافذة المنبثقة ستظهر فقط:\n";
    echo "- اسم المرفق\n";
    echo "- فئة المرفق\n"; 
    echo "- الصورة (مصغرة)\n";
    echo "- أزرار الاتجاهات والاتصال\n";
    echo "- زر 'عرض التفاصيل الكاملة' فقط\n";
    echo "والتفاصيل الكاملة ستظهر عند النقر على الزر فقط.\n";
} else {
    echo "حدث خطأ في تحديث الملف!\n";
}
?> 