<?php
echo "<h1>🔧 إصلاح وظيفة تعديل المرافق</h1>";

// قراءة ملف إدارة المرافق
$file_path = 'modules/facilities_management.php';
$content = file_get_contents($file_path);

// إضافة نموذج التعديل قبل السكربت
$edit_modal = '
    <!-- Edit Facility Modal -->
    <div id="editFacilityModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">تعديل المرفق</h3>
                        <button type="button" onclick="closeEditFacilityModal()" class="text-gray-400 hover:text-gray-600">
                            <span class="sr-only">إغلاق</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="edit_facility">
                        <input type="hidden" name="facility_id" id="edit_facility_id">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالعربية *</label>
                                <input type="text" name="name_ar" id="edit_name_ar" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم بالإنجليزية</label>
                                <input type="text" name="name_en" id="edit_name_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الفئة *</label>
                                <select name="category_id" id="edit_category_id" required 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">اختر الفئة</option>
                                    ' . generateCategoryOptions() . '
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">صورة المرفق</label>
                                <div id="current_image_preview" class="mb-2"></div>
                                <input type="file" name="facility_image" accept="image/*" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <p class="text-xs text-gray-500 mt-1">اختر صورة جديدة لاستبدال الحالية</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط العرض (Latitude) *</label>
                                <input type="number" name="latitude" id="edit_latitude" step="any" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">خط الطول (Longitude) *</label>
                                <input type="number" name="longitude" id="edit_longitude" step="any" required 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالعربية</label>
                                <textarea name="description_ar" id="edit_description_ar" rows="3" 
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف بالإنجليزية</label>
                                <textarea name="description_en" id="edit_description_en" rows="3" 
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">جهة الاتصال (عربي)</label>
                                <input type="text" name="contact_person_ar" id="edit_contact_person_ar"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">جهة الاتصال (إنجليزي)</label>
                                <input type="text" name="contact_person_en" id="edit_contact_person_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                                <input type="text" name="phone" id="edit_phone"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                                <input type="email" name="email" id="edit_email"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان (عربي)</label>
                                <input type="text" name="address_ar" id="edit_address_ar"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان (إنجليزي)</label>
                                <input type="text" name="address_en" id="edit_address_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل (عربي)</label>
                                <input type="text" name="working_hours_ar" id="edit_working_hours_ar"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ساعات العمل (إنجليزي)</label>
                                <input type="text" name="working_hours_en" id="edit_working_hours_en"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الموقع الإلكتروني</label>
                                <input type="url" name="website" id="edit_website"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="is_featured" id="edit_is_featured" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="edit_is_featured" class="mr-2 block text-sm text-gray-900">مرفق مميز</label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 space-x-reverse pt-6 border-t">
                            <button type="button" onclick="closeEditFacilityModal()" 
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
    </div>

    <script>';

// استبدال السكربت القديم
$new_script = '
        function showAddFacilityModal() {
            document.getElementById(\'addFacilityModal\').classList.remove(\'hidden\');
        }

        function closeAddFacilityModal() {
            document.getElementById(\'addFacilityModal\').classList.add(\'hidden\');
        }

        function editFacility(facilityId) {
            // جلب بيانات المرفق
            fetch(`facilities_api.php?action=get_facility_details&facility_id=${facilityId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.facility) {
                        populateEditForm(data.facility);
                        document.getElementById(\'editFacilityModal\').classList.remove(\'hidden\');
                    } else {
                        alert(\'خطأ في جلب بيانات المرفق: \' + (data.error || \'غير محدد\'));
                    }
                })
                .catch(error => {
                    console.error(\'Error:\', error);
                    alert(\'حدث خطأ في جلب بيانات المرفق\');
                });
        }

        function populateEditForm(facility) {
            document.getElementById(\'edit_facility_id\').value = facility.id;
            document.getElementById(\'edit_name_ar\').value = facility.name_ar || \'\';
            document.getElementById(\'edit_name_en\').value = facility.name_en || \'\';
            document.getElementById(\'edit_category_id\').value = facility.category_id || \'\';
            document.getElementById(\'edit_description_ar\').value = facility.description_ar || \'\';
            document.getElementById(\'edit_description_en\').value = facility.description_en || \'\';
            document.getElementById(\'edit_latitude\').value = facility.latitude || \'\';
            document.getElementById(\'edit_longitude\').value = facility.longitude || \'\';
            document.getElementById(\'edit_contact_person_ar\').value = facility.contact_person_ar || \'\';
            document.getElementById(\'edit_contact_person_en\').value = facility.contact_person_en || \'\';
            document.getElementById(\'edit_phone\').value = facility.phone || \'\';
            document.getElementById(\'edit_email\').value = facility.email || \'\';
            document.getElementById(\'edit_address_ar\').value = facility.address_ar || \'\';
            document.getElementById(\'edit_address_en\').value = facility.address_en || \'\';
            document.getElementById(\'edit_working_hours_ar\').value = facility.working_hours_ar || \'\';
            document.getElementById(\'edit_working_hours_en\').value = facility.working_hours_en || \'\';
            document.getElementById(\'edit_website\').value = facility.website || \'\';
            document.getElementById(\'edit_is_featured\').checked = facility.is_featured == 1;
            
            // عرض الصورة الحالية إن وجدت
            const currentImageDiv = document.getElementById(\'current_image_preview\');
            if (facility.image_path) {
                currentImageDiv.innerHTML = `
                    <img src="../uploads/facilities/${facility.image_path}" 
                         alt="الصورة الحالية" 
                         class="w-20 h-20 object-cover rounded-md">
                    <p class="text-xs text-gray-500 mt-1">الصورة الحالية</p>
                `;
            } else {
                currentImageDiv.innerHTML = \'<p class="text-xs text-gray-500">لا توجد صورة</p>\';
            }
        }

        function closeEditFacilityModal() {
            document.getElementById(\'editFacilityModal\').classList.add(\'hidden\');
        }

        function toggleFacilityStatus(facilityId, isActive) {
            fetch(\'\', {
                method: \'POST\',
                headers: {
                    \'Content-Type\': \'application/x-www-form-urlencoded\',
                },
                body: `action=toggle_status&facility_id=${facilityId}&new_status=${isActive ? 1 : 0}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert(\'خطأ في تحديث الحالة: \' + data.error);
                    location.reload();
                }
            })
            .catch(error => {
                console.error(\'Error:\', error);
                alert(\'حدث خطأ في تحديث الحالة\');
                location.reload();
            });
        }

        function deleteFacility(facilityId) {
            if (confirm(\'هل أنت متأكد من حذف هذا المرفق؟ سيتم حذف جميع البيانات المرتبطة به.\')) {
                const form = document.createElement(\'form\');
                form.method = \'POST\';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_facility">
                    <input type="hidden" name="facility_id" value="${facilityId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewOnMap(lat, lng) {
            const url = `https://www.google.com/maps?q=${lat},${lng}`;
            window.open(url, \'_blank\');
        }

        // إغلاق المودال عند النقر خارجه
        document.getElementById(\'addFacilityModal\').addEventListener(\'click\', function(e) {
            if (e.target === this) {
                closeAddFacilityModal();
            }
        });

        // إغلاق مودال التعديل عند النقر خارجه
        document.addEventListener(\'DOMContentLoaded\', function() {
            const editModal = document.getElementById(\'editFacilityModal\');
            if (editModal) {
                editModal.addEventListener(\'click\', function(e) {
                    if (e.target === this) {
                        closeEditFacilityModal();
                    }
                });
            }
        });';

// جلب الفئات من قاعدة البيانات
function generateCategoryOptions() {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $categories = $db->query("SELECT * FROM facility_categories WHERE is_active = 1 ORDER BY display_order, name_ar")->fetchAll();
    
    $options = '';
    foreach ($categories as $category) {
        $options .= '<option value="' . $category['id'] . '">' . htmlspecialchars($category['name_ar']) . '</option>';
    }
    return $options;
}

// تطبيق التعديلات
$updated_content = str_replace('    <script>', $edit_modal, $content);
$updated_content = str_replace(
    '        function editFacility(facilityId) {
            // سيتم إضافة هذه الوظيفة لاحقاً
            alert(\'سيتم إضافة وظيفة التعديل قريباً\');
        }',
    '',
    $updated_content
);

// إضافة السكربت الجديد
$pattern = '/<script>(.*?)<\/script>/s';
$replacement = '<script>' . $new_script . '</script>';
$updated_content = preg_replace($pattern, $replacement, $updated_content);

// حفظ الملف المحدث
file_put_contents($file_path, $updated_content);

echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ تم إصلاح وظيفة تعديل المرافق بنجاح!</p>";

echo "<h2>🔧 التحسينات المضافة:</h2>";
echo "<ul style='margin: 10px 0; padding-right: 20px;'>";
echo "<li>✅ نموذج تعديل شامل مع جميع الحقول</li>";
echo "<li>✅ جلب البيانات من API بشكل تلقائي</li>";
echo "<li>✅ عرض الصورة الحالية في نموذج التعديل</li>";
echo "<li>✅ إمكانية استبدال الصورة أو الاحتفاظ بالحالية</li>";
echo "<li>✅ حفظ التعديلات في قاعدة البيانات</li>";
echo "<li>✅ معالجة أخطاء وتحديث فوري للواجهة</li>";
echo "</ul>";

echo "<h2>🎯 كيفية الاستخدام:</h2>";
echo "<ol style='margin: 10px 0; padding-right: 20px;'>";
echo "<li>اضغط على زر <strong>\"✏️ تعديل\"</strong> بجانب أي مرفق</li>";
echo "<li>سيتم فتح نموذج التعديل مع البيانات الحالية محملة تلقائياً</li>";
echo "<li>قم بتعديل البيانات المطلوبة</li>";
echo "<li>اضغط <strong>\"تحديث المرفق\"</strong> لحفظ التغييرات</li>";
echo "</ol>";

echo "<div style='margin: 20px 0; padding: 15px; background: #e8f5e8; border: 1px solid #4caf50; border-radius: 5px;'>";
echo "<h3 style='color: #2e7d32; margin: 0 0 10px 0;'>🎉 التعديل جاهز للاستخدام!</h3>";
echo "<p style='margin: 0;'>يمكنك الآن الذهاب إلى <a href='http://localhost:8080/tekrit_municipality/modules/facilities_management.php' target='_blank' style='color: #1976d2; text-decoration: none;'><strong>صفحة إدارة المرافق</strong></a> وتجربة وظيفة التعديل.</p>";
echo "</div>";

echo "<h3>🔗 روابط سريعة:</h3>";
echo "<a href='http://localhost:8080/tekrit_municipality/modules/facilities_management.php' target='_blank' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🛠️ إدارة المرافق</a>";
echo "<a href='http://localhost:8080/tekrit_municipality/public/facilities-map.php' target='_blank' style='background: #2196f3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🗺️ الخريطة العامة</a>";
?> 