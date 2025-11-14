/**
 * مدير النماذج الديناميكية
 * يتعامل مع تحميل وعرض النماذج حسب نوع الطلب المختار
 */
class DynamicFormManager {
    constructor() {
        this.currentTypeId = null;
        this.formContainer = document.getElementById('dynamic-form-container');
        this.documentsContainer = document.getElementById('required-documents-container');
        this.typeSelect = document.getElementById('request_type_id');
        this.submitButton = document.getElementById('submit-button');
        
        this.init();
    }
    
    init() {
        if (this.typeSelect) {
            this.typeSelect.addEventListener('change', (e) => {
                this.loadFormFields(e.target.value);
            });
            
            // تحميل النموذج إذا كان هناك نوع محدد مسبقاً
            if (this.typeSelect.value) {
                this.loadFormFields(this.typeSelect.value);
            }
        }
    }
    
    async loadFormFields(typeId) {
        if (!typeId) {
            this.clearForm();
            return;
        }
        
        if (typeId === this.currentTypeId) {
            return; // نفس النوع، لا حاجة لإعادة التحميل
        }
        
        try {
            this.showLoading();
            
            const response = await fetch(`../ajax/get-form-fields.php?type_id=${typeId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'خطأ في تحميل النموذج');
            }
            
            this.currentTypeId = typeId;
            this.renderForm(data);
            this.renderRequiredDocuments(data.required_documents);
            
        } catch (error) {
            console.error('Error loading form fields:', error);
            this.showError('خطأ في تحميل حقول النموذج: ' + error.message);
        }
    }
    
    renderForm(data) {
        if (!this.formContainer) return;
        
        const formFields = data.form_fields || [];
        const typeInfo = data.type_info || {};
        
        let html = '';
        
        // عرض معلومات نوع الطلب
        if (typeInfo.type_description) {
            html += `
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-blue-900 mb-2">وصف الطلب</h3>
                    <p class="text-blue-800">${typeInfo.type_description}</p>
                    ${typeInfo.processing_time ? `<p class="text-sm text-blue-600 mt-2"><strong>مدة المعالجة:</strong> ${typeInfo.processing_time}</p>` : ''}
                    ${typeInfo.fees > 0 ? `<p class="text-sm text-blue-600"><strong>الرسوم:</strong> ${this.formatCurrency(typeInfo.fees)}</p>` : ''}
                </div>
            `;
        }
        
        if (formFields.length > 0) {
            html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
            
            formFields.forEach(field => {
                html += this.renderField(field);
            });
            
            html += '</div>';
        } else {
            html += `
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                    <p class="text-gray-600">لا توجد حقول إضافية مطلوبة لهذا النوع من الطلبات</p>
                </div>
            `;
        }
        
        this.formContainer.innerHTML = html;
        this.initFieldValidation();
        this.enableSubmitButton();
    }
    
    renderField(field) {
        const fieldId = `field_${field.name}`;
        const required = field.required ? 'required' : '';
        const requiredMark = field.required ? '<span class="text-red-500">*</span>' : '';
        
        let fieldHtml = '';
        
        switch (field.type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
                fieldHtml = `
                    <input type="${field.type}" 
                           id="${fieldId}" 
                           name="form_data[${field.name}]" 
                           placeholder="${field.placeholder || ''}"
                           ${required}
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                `;
                break;
                
            case 'number':
                fieldHtml = `
                    <input type="number" 
                           id="${fieldId}" 
                           name="form_data[${field.name}]" 
                           placeholder="${field.placeholder || ''}"
                           ${field.min !== undefined ? `min="${field.min}"` : ''}
                           ${field.max !== undefined ? `max="${field.max}"` : ''}
                           ${field.step !== undefined ? `step="${field.step}"` : ''}
                           ${required}
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                `;
                break;
                
            case 'date':
                fieldHtml = `
                    <input type="date" 
                           id="${fieldId}" 
                           name="form_data[${field.name}]" 
                           ${field.min ? `min="${field.min}"` : ''}
                           ${field.max ? `max="${field.max}"` : ''}
                           ${required}
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                `;
                break;
                
            case 'textarea':
                fieldHtml = `
                    <textarea id="${fieldId}" 
                              name="form_data[${field.name}]" 
                              rows="${field.rows || 3}"
                              placeholder="${field.placeholder || ''}"
                              ${required}
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                `;
                break;
                
            case 'select':
                let options = '';
                if (field.options && Array.isArray(field.options)) {
                    options = field.options.map(option => 
                        `<option value="${option.value || option}">${option.label || option}</option>`
                    ).join('');
                }
                fieldHtml = `
                    <select id="${fieldId}" 
                            name="form_data[${field.name}]" 
                            ${required}
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">اختر...</option>
                        ${options}
                    </select>
                `;
                break;
                
            case 'radio':
                if (field.options && Array.isArray(field.options)) {
                    fieldHtml = '<div class="space-y-2">';
                    field.options.forEach((option, index) => {
                        const optionId = `${fieldId}_${index}`;
                        fieldHtml += `
                            <label class="flex items-center">
                                <input type="radio" 
                                       id="${optionId}" 
                                       name="form_data[${field.name}]" 
                                       value="${option.value || option}"
                                       ${required}
                                       class="ml-2 text-blue-600 focus:ring-blue-500">
                                <span>${option.label || option}</span>
                            </label>
                        `;
                    });
                    fieldHtml += '</div>';
                }
                break;
                
            case 'checkbox':
                if (field.options && Array.isArray(field.options)) {
                    fieldHtml = '<div class="space-y-2">';
                    field.options.forEach((option, index) => {
                        const optionId = `${fieldId}_${index}`;
                        fieldHtml += `
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       id="${optionId}" 
                                       name="form_data[${field.name}][]" 
                                       value="${option.value || option}"
                                       class="ml-2 text-blue-600 focus:ring-blue-500">
                                <span>${option.label || option}</span>
                            </label>
                        `;
                    });
                    fieldHtml += '</div>';
                } else {
                    fieldHtml = `
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   id="${fieldId}" 
                                   name="form_data[${field.name}]" 
                                   value="1"
                                   ${required}
                                   class="ml-2 text-blue-600 focus:ring-blue-500">
                            <span>${field.label}</span>
                        </label>
                    `;
                }
                break;
                
            case 'file':
                fieldHtml = `
                    <input type="file" 
                           id="${fieldId}" 
                           name="form_files[${field.name}]" 
                           ${field.accept ? `accept="${field.accept}"` : ''}
                           ${field.multiple ? 'multiple' : ''}
                           ${required}
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    ${field.help ? `<p class="text-sm text-gray-500 mt-1">${field.help}</p>` : ''}
                `;
                break;
                
            default:
                fieldHtml = `
                    <input type="text" 
                           id="${fieldId}" 
                           name="form_data[${field.name}]" 
                           placeholder="${field.placeholder || ''}"
                           ${required}
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                `;
        }
        
        return `
            <div class="form-field" data-field-type="${field.type}" data-field-name="${field.name}">
                <label for="${fieldId}" class="block text-sm font-medium text-gray-700 mb-2">
                    ${field.label} ${requiredMark}
                </label>
                ${fieldHtml}
                ${field.help && field.type !== 'file' ? `<p class="text-sm text-gray-500 mt-1">${field.help}</p>` : ''}
                <div class="field-error text-red-500 text-sm mt-1 hidden"></div>
            </div>
        `;
    }
    
    renderRequiredDocuments(documents) {
        if (!this.documentsContainer) return;
        
        if (!documents || documents.length === 0) {
            this.documentsContainer.innerHTML = '';
            return;
        }
        
        let html = `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-semibold text-yellow-900 mb-3">المستندات المطلوبة</h3>
                <ul class="space-y-2">
        `;
        
        documents.forEach(doc => {
            html += `<li class="flex items-center text-yellow-800">
                <svg class="w-4 h-4 ml-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                ${doc}
            </li>`;
        });
        
        html += `
                </ul>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        رفع المستندات <span class="text-red-500">*</span>
                    </label>
                    <input type="file" 
                           name="documents[]" 
                           multiple 
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-1">
                        يمكنك رفع ملفات متعددة. الأنواع المدعومة: PDF, JPG, PNG, DOC, DOCX (حد أقصى 5 ميجابايت لكل ملف)
                    </p>
                </div>
            </div>
        `;
        
        this.documentsContainer.innerHTML = html;
    }
    
    initFieldValidation() {
        const fields = this.formContainer.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            field.addEventListener('blur', () => this.validateField(field));
            field.addEventListener('input', () => this.clearFieldError(field));
        });
    }
    
    validateField(field) {
        const fieldContainer = field.closest('.form-field');
        const errorContainer = fieldContainer.querySelector('.field-error');
        
        let isValid = true;
        let errorMessage = '';
        
        // التحقق من الحقول المطلوبة
        if (field.hasAttribute('required') && !field.value.trim()) {
            isValid = false;
            errorMessage = 'هذا الحقل مطلوب';
        }
        
        // التحقق من البريد الإلكتروني
        if (field.type === 'email' && field.value && !this.isValidEmail(field.value)) {
            isValid = false;
            errorMessage = 'يرجى إدخال بريد إلكتروني صحيح';
        }
        
        // التحقق من رقم الهاتف
        if (field.type === 'tel' && field.value && !this.isValidPhone(field.value)) {
            isValid = false;
            errorMessage = 'يرجى إدخال رقم هاتف صحيح';
        }
        
        // التحقق من الأرقام
        if (field.type === 'number' && field.value) {
            const value = parseFloat(field.value);
            if (field.hasAttribute('min') && value < parseFloat(field.getAttribute('min'))) {
                isValid = false;
                errorMessage = `القيمة يجب أن تكون أكبر من أو تساوي ${field.getAttribute('min')}`;
            }
            if (field.hasAttribute('max') && value > parseFloat(field.getAttribute('max'))) {
                isValid = false;
                errorMessage = `القيمة يجب أن تكون أصغر من أو تساوي ${field.getAttribute('max')}`;
            }
        }
        
        if (isValid) {
            this.clearFieldError(field);
        } else {
            this.showFieldError(field, errorMessage);
        }
        
        return isValid;
    }
    
    showFieldError(field, message) {
        const fieldContainer = field.closest('.form-field');
        const errorContainer = fieldContainer.querySelector('.field-error');
        
        field.classList.add('border-red-500');
        errorContainer.textContent = message;
        errorContainer.classList.remove('hidden');
    }
    
    clearFieldError(field) {
        const fieldContainer = field.closest('.form-field');
        const errorContainer = fieldContainer.querySelector('.field-error');
        
        field.classList.remove('border-red-500');
        errorContainer.textContent = '';
        errorContainer.classList.add('hidden');
    }
    
    validateForm() {
        if (!this.formContainer) return true;
        
        const fields = this.formContainer.querySelectorAll('input, select, textarea');
        let isValid = true;
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    clearForm() {
        if (this.formContainer) {
            this.formContainer.innerHTML = '';
        }
        if (this.documentsContainer) {
            this.documentsContainer.innerHTML = '';
        }
        this.currentTypeId = null;
        this.disableSubmitButton();
    }
    
    showLoading() {
        if (this.formContainer) {
            this.formContainer.innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span class="mr-3 text-gray-600">جاري تحميل النموذج...</span>
                </div>
            `;
        }
        this.disableSubmitButton();
    }
    
    showError(message) {
        if (this.formContainer) {
            this.formContainer.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="mr-3">
                            <h3 class="text-sm font-medium text-red-800">خطأ</h3>
                            <p class="text-sm text-red-700 mt-1">${message}</p>
                        </div>
                    </div>
                </div>
            `;
        }
        this.disableSubmitButton();
    }
    
    enableSubmitButton() {
        if (this.submitButton) {
            this.submitButton.disabled = false;
            this.submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
    
    disableSubmitButton() {
        if (this.submitButton) {
            this.submitButton.disabled = true;
            this.submitButton.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
    
    // Helper functions
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{8,}$/;
        return phoneRegex.test(phone);
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('ar-LB', {
            style: 'currency',
            currency: 'LBP'
        }).format(amount);
    }
}

// تهيئة مدير النماذج عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    window.dynamicFormManager = new DynamicFormManager();
    
    // إضافة معالج للنموذج الرئيسي
    const mainForm = document.getElementById('citizen-request-form');
    if (mainForm) {
        mainForm.addEventListener('submit', function(e) {
            if (!window.dynamicFormManager.validateForm()) {
                e.preventDefault();
                alert('يرجى تصحيح الأخطاء في النموذج قبل الإرسال');
                return false;
            }
        });
    }
});

// تصدير الفئة للاستخدام الخارجي
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DynamicFormManager;
}

