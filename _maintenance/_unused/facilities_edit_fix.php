<?php
echo "<h1>🔧 إصلاح وظيفة تعديل المرافق</h1>";

// قراءة الملف الحالي
$file_path = 'modules/facilities_management.php';
$content = file_get_contents($file_path);

// إصلاح وظيفة editFacility في JavaScript
$old_function = '        function editFacility(facilityId) {
            // سيتم إضافة هذه الوظيفة لاحقاً
            alert(\'سيتم إضافة وظيفة التعديل قريباً\');
        }';

$new_function = '        function editFacility(facilityId) {
            // جلب بيانات المرفق عبر AJAX
            const xhr = new XMLHttpRequest();
            xhr.open(\'GET\', \'facilities_api.php?action=get_facility_details&facility_id=\' + facilityId);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success && data.facility) {
                            openEditModal(data.facility);
                        } else {
                            alert(\'خطأ في جلب بيانات المرفق: \' + (data.error || \'غير محدد\'));
                        }
                    } catch (e) {
                        alert(\'خطأ في معالجة البيانات\');
                    }
                } else {
                    alert(\'خطأ في الاتصال بالخادم\');
                }
            };
            xhr.send();
        }

        function openEditModal(facility) {
            // إنشاء نموذج التعديل ديناميكياً
            const modal = document.createElement(\'div\');
            modal.id = \'editModal\';
            modal.className = \'fixed inset-0 bg-gray-600 bg-opacity-50 z-50\';
            modal.innerHTML = `
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-medium text-gray-900">تعديل المرفق</h3>
                                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                                    <span class="text-2xl">×</span>
                                </button>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="action" value="edit_facility">
                                <input type="hidden" name="facility_id" value="${facility.id}">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربية *</label>
                                        <input type="text" name="name_ar" value="${facility.name_ar || \'\'}" required 
                                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزية</label>
                                        <input type="text" name="name_en" value="${facility.name_en || \'\'}"
                                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">خط العرض *</label>
                                        <input type="number" name="latitude" value="${facility.latitude || \'\'}" step="any" required 
                                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">خط الطول *</label>
                                        <input type="number" name="longitude" value="${facility.longitude || \'\'}" step="any" required 
                                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                                        <input type="text" name="phone" value="${facility.phone || \'\'}"
                                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                                        <input type="email" name="email" value="${facility.email || \'\'}"
                                               class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالعربية</label>
                                    <textarea name="description_ar" rows="3" 
                                              class="w-full border border-gray-300 rounded-md px-3 py-2">${facility.description_ar || \'\'}</textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">العنوان بالعربية</label>
                                    <input type="text" name="address_ar" value="${facility.address_ar || \'\'}"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="is_featured" ${facility.is_featured == 1 ? \'checked\' : \'\'} 
                                           class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    <label class="mr-2 block text-sm text-gray-900">مرفق مميز</label>
                                </div>
                                
                                <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                                    <button type="button" onclick="closeEditModal()" 
                                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                        إلغاء
                                    </button>
                                    <button type="submit" 
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                        تحديث المرفق
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        function closeEditModal() {
            const modal = document.getElementById(\'editModal\');
            if (modal) {
                modal.remove();
            }
        }';

// استبدال الوظيفة القديمة بالجديدة
$updated_content = str_replace($old_function, $new_function, $content);

// حفظ الملف المحدث
if (file_put_contents($file_path, $updated_content)) {
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ تم إصلاح وظيفة تعديل المرافق بنجاح!</p>";
    
    echo "<div style='margin: 20px 0; padding: 15px; background: #e8f5e8; border: 1px solid #4caf50; border-radius: 5px;'>";
    echo "<h3 style='color: #2e7d32; margin: 0 0 10px 0;'>🎉 التعديل جاهز للاستخدام!</h3>";
    echo "<p style='margin: 0;'>الآن يمكنك النقر على زر \"✏️ تعديل\" بجانب أي مرفق لتعديل بياناته.</p>";
    echo "</div>";
    
    echo "<h3>🔗 روابط سريعة:</h3>";
    echo "<a href='http://localhost:8080/tekrit_municipality/modules/facilities_management.php' target='_blank' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🛠️ إدارة المرافق</a>";
    echo "<a href='http://localhost:8080/tekrit_municipality/public/facilities-map.php' target='_blank' style='background: #2196f3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🗺️ الخريطة العامة</a>";
    
} else {
    echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ فشل في حفظ التعديلات!</p>";
}
?> 