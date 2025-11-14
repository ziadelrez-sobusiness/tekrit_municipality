/**
 * وظائف JavaScript محسنة لإدارة طلبات المواطنين
 * تتضمن عرض التفاصيل، التعديل، والمستندات المرفقة
 */

// متغيرات عامة
let currentRequestId = null;
let currentRequestData = null;

/**
 * عرض تفاصيل الطلب مع المستندات ونوع الطلب
 */
async function viewRequestDetails(requestId) {
    try {
        currentRequestId = requestId;
        
        // جلب تفاصيل الطلب من الخادم
        const response = await fetch(`get-request-details.php?id=${requestId}`);
        const data = await response.json();
        
        if (data.success) {
            currentRequestData = data.request;
            displayRequestDetails(data.request);
            showModal('requestDetailsModal');
        } else {
            showAlert('خطأ في جلب تفاصيل الطلب: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('خطأ في جلب تفاصيل الطلب:', error);
        showAlert('حدث خطأ في جلب تفاصيل الطلب', 'error');
    }
}

/**
 * عرض تفاصيل الطلب في المودال
 */
function displayRequestDetails(request) {
    // معلومات أساسية
    document.getElementById('modal-tracking-number').textContent = request.tracking_number;
    document.getElementById('modal-request-title').textContent = request.request_title;
    document.getElementById('modal-request-description').textContent = request.request_description;
    document.getElementById('modal-request-type').textContent = request.type_name || 'غير محدد';
    document.getElementById('modal-created-at').textContent = formatDate(request.created_at);
    
    // معلومات المواطن
    document.getElementById('modal-citizen-name').textContent = request.citizen_name;
    document.getElementById('modal-citizen-phone').textContent = request.citizen_phone;
    document.getElementById('modal-citizen-email').textContent = request.citizen_email || 'غير محدد';
    document.getElementById('modal-citizen-address').textContent = request.citizen_address || 'غير محدد';
    document.getElementById('modal-national-id').textContent = request.national_id || 'غير محدد';

    // الحالة والأولوية
    const statusBadge = document.getElementById('modal-status');
    statusBadge.textContent = request.status;
    statusBadge.className = 'status-badge ' + getStatusColor(request.status);
    
    document.getElementById('modal-priority-level').textContent = request.priority_level;
    document.getElementById('modal-estimated-completion-date').textContent = request.estimated_completion_date || 'غير محدد';

    // عرض البيانات الإضافية من request_form_data
    displayFormData(request.form_data || []);
    
    // عرض المستندات المرفقة
    displayDocuments(request.documents || []);
    
    // عرض التحديثات
    displayRequestUpdates(request.updates || []);
    
    // ملء نماذج التحديث
    document.getElementById('update-request-id').value = request.id;
    document.getElementById('update-status').value = request.status;
    document.getElementById('update-priority').value = request.priority_level;
    document.getElementById('update-estimated-date').value = request.estimated_completion_date || '';
    document.getElementById('update-admin-notes').value = request.admin_notes || '';
}

/**
 * عرض البيانات الإضافية من النموذج الديناميكي
 */
function displayFormData(formData) {
    const container = document.getElementById('modal-form-data');
    container.innerHTML = '';
    
    if (formData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm">لا توجد بيانات إضافية</p>';
        return;
    }
    
    formData.forEach(field => {
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'mb-3 p-3 bg-gray-50 rounded-lg';
        
        let displayValue = field.field_value;
        if (field.field_type === 'checkbox' && field.field_value === '1') {
            displayValue = '✓ نعم';
        } else if (field.field_type === 'checkbox' && field.field_value === '0') {
            displayValue = '✗ لا';
        }
        
        fieldDiv.innerHTML = `
            <div class="flex justify-between items-start">
                <span class="font-medium text-sm text-gray-700">${field.field_name}:</span>
                <span class="text-sm text-gray-900 mr-2">${displayValue}</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">نوع الحقل: ${field.field_type}</div>
        `;
        
        container.appendChild(fieldDiv);
    });
}

/**
 * عرض المستندات المرفقة
 */
function displayDocuments(documents) {
    const container = document.getElementById('modal-documents');
    container.innerHTML = '';
    
    if (documents.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm">لا توجد مستندات مرفقة</p>';
        return;
    }
    
    documents.forEach(doc => {
        const docDiv = document.createElement('div');
        docDiv.className = 'mb-3 p-3 border border-gray-200 rounded-lg';
        
        const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(doc.original_filename);
        const fileSize = formatFileSize(doc.file_size);
        
        docDiv.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">
                        ${isImage ? '🖼️' : '📄'}
                    </div>
                    <div>
                        <div class="font-medium text-sm">${doc.document_name}</div>
                        <div class="text-xs text-gray-500">${doc.original_filename}</div>
                        <div class="text-xs text-gray-400">${fileSize} • ${doc.file_type}</div>
                        <div class="text-xs text-gray-400">تم الرفع: ${formatDate(doc.uploaded_at)}</div>
                    </div>
                </div>
                <div class="flex space-x-2 space-x-reverse">
                    <button onclick="viewDocument('${doc.file_path}')" 
                            class="text-blue-600 hover:text-blue-800 text-sm">
                        👁️ عرض
                    </button>
                    <button onclick="downloadDocument('${doc.file_path}', '${doc.original_filename}')" 
                            class="text-green-600 hover:text-green-800 text-sm">
                        📥 تحميل
                    </button>
                </div>
            </div>
            ${doc.is_required ? '<div class="text-xs text-red-600 mt-2">📌 مستند مطلوب</div>' : ''}
        `;
        
        container.appendChild(docDiv);
    });
}

/**
 * عرض تحديثات الطلب
 */
function displayRequestUpdates(updates) {
    const container = document.getElementById('modal-updates-timeline');
    container.innerHTML = '';
    
    if (updates.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm">لا توجد تحديثات</p>';
        return;
    }
    
    updates.forEach(update => {
        const updateDiv = document.createElement('div');
        updateDiv.className = 'mb-4 p-4 bg-gray-50 rounded-lg border-r-4 border-blue-500';
        
        updateDiv.innerHTML = `
            <div class="flex justify-between items-start mb-2">
                <span class="font-medium text-sm text-gray-800">${update.update_type}</span>
                <span class="text-xs text-gray-500">${formatDate(update.created_at)}</span>
            </div>
            <p class="text-sm text-gray-700 mb-2">${update.update_text}</p>
            ${update.updated_by ? `<p class="text-xs text-gray-600">بواسطة: ${update.updated_by}</p>` : ''}
            <div class="text-xs mt-2">
                <span class="${update.is_visible_to_citizen ? 'text-green-600' : 'text-red-600'}">
                    ${update.is_visible_to_citizen ? '👁️ مرئي للمواطن' : '🔒 غير مرئي للمواطن'}
                </span>
            </div>
        `;
        
        container.appendChild(updateDiv);
    });
}

/**
 * تعديل الطلب
 */
async function editRequest(requestId) {
    try {
        // جلب تفاصيل الطلب أولاً
        const response = await fetch(`get-request-details.php?id=${requestId}`);
        const data = await response.json();
        
        if (data.success) {
            currentRequestData = data.request;
            populateEditForm(data.request);
            showModal('editRequestModal');
        } else {
            showAlert('خطأ في جلب بيانات الطلب: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('خطأ في جلب بيانات الطلب:', error);
        showAlert('حدث خطأ في جلب بيانات الطلب', 'error');
    }
}

/**
 * ملء نموذج التعديل
 */
function populateEditForm(request) {
    document.getElementById('edit-request-id').value = request.id;
    document.getElementById('edit-status').value = request.status;
    document.getElementById('edit-priority').value = request.priority_level;
    document.getElementById('edit-estimated-date').value = request.estimated_completion_date || '';
    document.getElementById('edit-admin-notes').value = request.admin_notes || '';
    
    // ملء البيانات الديناميكية
    populateEditFormData(request.form_data || []);
}

/**
 * ملء البيانات الديناميكية في نموذج التعديل
 */
function populateEditFormData(formData) {
    const container = document.getElementById('edit-form-data-container');
    container.innerHTML = '';
    
    if (formData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm">لا توجد بيانات إضافية للتعديل</p>';
        return;
    }
    
    formData.forEach(field => {
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'mb-4';
        
        let inputHtml = '';
        switch (field.field_type) {
            case 'text':
            case 'email':
            case 'number':
                inputHtml = `<input type="${field.field_type}" name="form_data[${field.field_name}]" 
                                   value="${field.field_value}" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">`;
                break;
            case 'textarea':
                inputHtml = `<textarea name="form_data[${field.field_name}]" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-md" rows="3">${field.field_value}</textarea>`;
                break;
            case 'select':
                // يحتاج إلى معلومات إضافية عن الخيارات
                inputHtml = `<input type="text" name="form_data[${field.field_name}]" 
                                   value="${field.field_value}" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">`;
                break;
            case 'checkbox':
                const checked = field.field_value === '1' ? 'checked' : '';
                inputHtml = `<input type="checkbox" name="form_data[${field.field_name}]" 
                                   value="1" ${checked} 
                                   class="rounded border-gray-300">`;
                break;
            case 'date':
                inputHtml = `<input type="date" name="form_data[${field.field_name}]" 
                                   value="${field.field_value}" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">`;
                break;
            default:
                inputHtml = `<input type="text" name="form_data[${field.field_name}]" 
                                   value="${field.field_value}" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">`;
        }
        
        fieldDiv.innerHTML = `
            <label class="block text-sm font-medium text-gray-700 mb-2">${field.field_name}</label>
            ${inputHtml}
            <div class="text-xs text-gray-500 mt-1">نوع الحقل: ${field.field_type}</div>
        `;
        
        container.appendChild(fieldDiv);
    });
}

/**
 * حفظ تعديلات الطلب
 */
async function saveRequestEdit() {
    try {
        const form = document.getElementById('editRequestForm');
        const formData = new FormData(form);
        
        const response = await fetch('update-request.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('تم تحديث الطلب بنجاح', 'success');
            hideModal('editRequestModal');
            refreshRequests();
        } else {
            showAlert('خطأ في تحديث الطلب: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('خطأ في تحديث الطلب:', error);
        showAlert('حدث خطأ في تحديث الطلب', 'error');
    }
}

/**
 * إضافة تعليق على الطلب
 */
function addComment(requestId) {
    currentRequestId = requestId;
    document.getElementById('comment-request-id').value = requestId;
    document.getElementById('comment-text').value = '';
    document.getElementById('is-visible-to-citizen').checked = true;
    showModal('addCommentModal');
}

/**
 * حفظ التعليق
 */
async function saveComment() {
    try {
        const form = document.getElementById('addCommentForm');
        const formData = new FormData(form);
        
        const response = await fetch('add-comment.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('تم إضافة التعليق بنجاح', 'success');
            hideModal('addCommentModal');
            if (currentRequestData) {
                viewRequestDetails(currentRequestId); // إعادة تحميل التفاصيل
            }
        } else {
            showAlert('خطأ في إضافة التعليق: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('خطأ في إضافة التعليق:', error);
        showAlert('حدث خطأ في إضافة التعليق', 'error');
    }
}

/**
 * عرض المستند
 */
function viewDocument(filePath) {
    const fullPath = `../uploads/${filePath}`;
    window.open(fullPath, '_blank');
}

/**
 * تحميل المستند
 */
function downloadDocument(filePath, originalFilename) {
    const link = document.createElement('a');
    link.href = `../uploads/${filePath}`;
    link.download = originalFilename;
    link.click();
}

/**
 * وظائف مساعدة
 */

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA') + ' ' + date.toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getStatusColor(status) {
    switch (status) {
        case 'جديد': return 'bg-blue-100 text-blue-800';
        case 'قيد المراجعة': return 'bg-yellow-100 text-yellow-800';
        case 'قيد التنفيذ': return 'bg-purple-100 text-purple-800';
        case 'مكتمل': return 'bg-green-100 text-green-800';
        case 'مرفوض': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function showModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function hideModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = 'auto';
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    alertDiv.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="mr-2 text-lg">&times;</button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

function refreshRequests() {
    window.location.reload();
}

function exportRequests() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'excel');
    window.open(currentUrl.toString(), '_blank');
}

// تهيئة الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // إضافة مستمعي الأحداث للنماذج
    const editForm = document.getElementById('editRequestForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveRequestEdit();
        });
    }
    
    const commentForm = document.getElementById('addCommentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveComment();
        });
    }
    
    // إغلاق المودالات عند النقر خارجها
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                hideModal(modal.id);
            }
        }
    });
});

