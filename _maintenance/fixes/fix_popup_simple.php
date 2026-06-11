<?php
echo "بدء إصلاح النافذة المنبثقة...\n";

// قراءة الملف
$file = 'public/facilities-map.php';
$content = file_get_contents($file);

// البحث عن النص القديم واستبداله
$oldText = "if (facility.description_ar || facility.description_en) {
                const description = MAP_CONFIG.language === 'ar' ? facility.description_ar : (facility.description_en || facility.description_ar);
                content += `<p class=\"text-sm mb-3\">\${description}</p>`;
            }";

$newText = "// تم حذف عرض الوصف في النافذة المنبثقة لتبسيطها";

$content = str_replace($oldText, $newText, $content);

// تحديث نص زر التفاصيل
$oldButtonText = "📋 \${MAP_CONFIG.language === 'ar' ? 'تفاصيل أكثر' : 'More Details'}";
$newButtonText = "📋 \${MAP_CONFIG.language === 'ar' ? 'عرض التفاصيل الكاملة' : 'View Full Details'}";

$content = str_replace($oldButtonText, $newButtonText, $content);

// حذف زر الموقع الإلكتروني من النافذة المنبثقة
$websiteButton = "if (facility.website) {
                content += `
                        <a href=\"\${facility.website}\" target=\"_blank\" 
                           class=\"bg-purple-500 text-white px-3 py-1 rounded text-xs hover:bg-purple-600\">
                            🌐 \${TEXTS.website}
                        </a>
                `;
            }";

$content = str_replace($websiteButton, "// تم حذف زر الموقع الإلكتروني لتبسيط النافذة المنبثقة", $content);

// حفظ الملف
file_put_contents($file, $content);

echo "تم إصلاح النافذة المنبثقة بنجاح!\n";
echo "التغييرات:\n";
echo "- حذف عرض الوصف من النافذة المنبثقة\n";
echo "- حذف زر الموقع الإلكتروني\n";
echo "- تحديث نص زر التفاصيل\n";
echo "الآن ستظهر النافذة المنبثقة معلومات مبسطة فقط\n";
?> 