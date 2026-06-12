// إصلاح مشكلة ظهور النافذة المنبثقة خلف الخريطة

// دالة لتحديث دالة showFacilityDetails لضمان ظهور النافذة أمام كل شيء
function fixModalZIndex() {
    const modal = document.getElementById('facilityModal');
    if (modal) {
        modal.style.zIndex = '99999';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        
        // تحديث العنصر الداخلي
        const modalContent = modal.querySelector('.bg-white');
        if (modalContent) {
            modalContent.style.zIndex = '99999';
            modalContent.style.position = 'relative';
        }
    }
    
    // التأكد من أن الخريطة لها z-index أقل
    const mapElement = document.getElementById('map');
    if (mapElement) {
        mapElement.style.zIndex = '1';
        mapElement.style.position = 'relative';
    }
}

// دالة محدثة لعرض تفاصيل المرفق
function showFacilityDetailsFixed(facility) {
    // أولاً قم بإصلاح z-index
    fixModalZIndex();
    
    const name = MAP_CONFIG.language === 'ar' ? facility.name_ar : (facility.name_en || facility.name_ar);
    const description = MAP_CONFIG.language === 'ar' ? facility.description_ar : (facility.description_en || facility.description_ar);
    const categoryName = MAP_CONFIG.language === 'ar' ? facility.category_name_ar : (facility.category_name_en || facility.category_name_ar);
    const contactPerson = MAP_CONFIG.language === 'ar' ? facility.contact_person_ar : (facility.contact_person_en || facility.contact_person_ar);
    const address = MAP_CONFIG.language === 'ar' ? facility.address_ar : (facility.address_en || facility.address_ar);
    const workingHours = MAP_CONFIG.language === 'ar' ? facility.working_hours_ar : (facility.working_hours_en || facility.working_hours_ar);

    const modalContent = `
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">${name}</h3>
            <button onclick="closeFacilityModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        ${facility.image_path ? 
            `<img src="../uploads/facilities/${facility.image_path}" alt="${name}" class="w-full h-48 object-cover rounded-lg mb-4">` 
            : ''
        }

        <div class="space-y-4">
            <div>
                <span class="inline-block px-3 py-1 rounded-full text-sm text-white" style="background-color: ${facility.category_color}">
                    ${categoryName}
                </span>
            </div>

            ${description ? `
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">${MAP_CONFIG.language === 'ar' ? 'الوصف' : 'Description'}</h4>
                    <p class="text-gray-600">${description}</p>
                </div>
            ` : ''}

            ${address ? `
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">📍 ${TEXTS.address}</h4>
                    <p class="text-gray-600">${address}</p>
                </div>
            ` : ''}

            ${contactPerson ? `
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">👤 ${TEXTS.contact_person}</h4>
                    <p class="text-gray-600">${contactPerson}</p>
                </div>
            ` : ''}

            ${facility.phone || facility.email ? `
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">📞 ${MAP_CONFIG.language === 'ar' ? 'معلومات الاتصال' : 'Contact Info'}</h4>
                    <div class="space-y-1">
                        ${facility.phone ? `<p class="text-gray-600">${TEXTS.phone}: <a href="tel:${facility.phone}" class="text-blue-600 hover:underline">${facility.phone}</a></p>` : ''}
                        ${facility.email ? `<p class="text-gray-600">${TEXTS.email}: <a href="mailto:${facility.email}" class="text-blue-600 hover:underline">${facility.email}</a></p>` : ''}
                    </div>
                </div>
            ` : ''}

            ${workingHours ? `
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">🕐 ${TEXTS.working_hours}</h4>
                    <p class="text-gray-600">${workingHours}</p>
                </div>
            ` : ''}

            <div class="border-t pt-4">
                <div class="flex flex-wrap gap-2">
                    <button onclick="getDirections(${facility.latitude}, ${facility.longitude})" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        🧭 ${TEXTS.get_directions}
                    </button>
                    
                    ${facility.phone ? `
                        <a href="tel:${facility.phone}" 
                           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            📞 ${TEXTS.call_now}
                        </a>
                    ` : ''}
                    
                    ${facility.website ? `
                        <a href="${facility.website}" target="_blank" 
                           class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                            🌐 ${TEXTS.website}
                        </a>
                    ` : ''}
                </div>
            </div>
        </div>
    `;

    document.getElementById('facilityModalContent').innerHTML = modalContent;
    const modal = document.getElementById('facilityModal');
    modal.classList.remove('hidden');
    
    // التأكد من أن النافذة تظهر أمام كل شيء
    setTimeout(() => {
        fixModalZIndex();
    }, 50);
}

// استبدال دالة showFacilityDetails الأصلية
if (typeof window !== 'undefined') {
    window.showFacilityDetails = showFacilityDetailsFixed;
}

console.log('تم تحميل إصلاحات النافذة المنبثقة'); 