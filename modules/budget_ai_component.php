<!-- Modal - معالج إنشاء الميزانية بالذكاء الاصطناعي -->
<div id="aiBudgetWizardModal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" style="display: none;">
    <div class="glass-card w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4 rounded-t-lg sticky top-0 z-10">
            <h3 class="text-2xl font-semibold">🤖 معالج إنشاء الميزانية بالذكاء الاصطناعي</h3>
            <p class="text-sm opacity-90 mt-1">سيساعدك الذكاء الاصطناعي في إنشاء ميزانية متكاملة</p>
        </div>

        <div class="p-6">
            <!-- خطوات المعالج -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center" id="step1Indicator">
                        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold">1</div>
                        <span class="mr-3 font-semibold">نوع الميزانية</span>
                    </div>
                    <div class="flex items-center" id="step2Indicator">
                        <div class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold">2</div>
                        <span class="mr-3 text-gray-500">الأسئلة</span>
                    </div>
                    <div class="flex items-center" id="step3Indicator">
                        <div class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold">3</div>
                        <span class="mr-3 text-gray-500">المراجعة</span>
                    </div>
                </div>
                <div class="mt-3 h-2 bg-gray-200 rounded-full">
                    <div id="progressBar" class="h-2 bg-blue-600 rounded-full transition-all duration-300" style="width: 33%"></div>
                </div>
            </div>

            <!-- الخطوة 1: اختيار نوع الميزانية -->
            <div id="step1" class="wizard-step">
                <h4 class="text-lg font-semibold mb-4">اختر نوع الميزانية</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border-2 border-gray-200 rounded-lg p-6 cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition"
                         onclick="selectBudgetType('general')">
                        <div class="text-4xl mb-3">🏛️</div>
                        <h5 class="font-bold text-lg mb-2">ميزانية عامة للبلدية</h5>
                        <p class="text-sm text-gray-600">ميزانية شاملة تغطي جميع إيرادات ومصاريف البلدية</p>
                    </div>
                    <div class="border-2 border-gray-200 rounded-lg p-6 cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition"
                         onclick="selectBudgetType('committee')">
                        <div class="text-4xl mb-3">👥</div>
                        <h5 class="font-bold text-lg mb-2">ميزانية لجنة محددة</h5>
                        <p class="text-sm text-gray-600">ميزانية خاصة بلجنة من لجان المجلس البلدي</p>
                    </div>
                </div>
            </div>

            <!-- الخطوة 2: الأسئلة -->
            <div id="step2" class="wizard-step" style="display: none;">
                <h4 class="text-lg font-semibold mb-4">أجب على الأسئلة التالية</h4>
                <div id="questionsContainer" class="space-y-4">
                    <!-- ستتم إضافة الأسئلة ديناميكياً -->
                </div>
            </div>

            <!-- الخطوة 3: المراجعة والإنشاء -->
            <div id="step3" class="wizard-step" style="display: none;">
                <h4 class="text-lg font-semibold mb-4">مراجعة وإنشاء الميزانية</h4>

                <div id="loadingState" class="text-center py-12" style="display: none;">
                    <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-lg font-semibold">جاري إنشاء الميزانية...</p>
                    <p class="text-sm text-gray-600 mt-2">يرجى الانتظار، قد يستغرق هذا بعض الوقت</p>
                </div>

                <div id="budgetPreview" style="display: none;">
                    <!-- سيتم عرض الميزانية هنا -->
                </div>
            </div>

            <!-- أزرار التنقل -->
            <div class="flex justify-between mt-6 pt-4 border-t">
                <button type="button" id="prevBtn" onclick="previousStep()" style="display: none;"
                        class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                    ← السابق
                </button>
                <button type="button" onclick="closeAIWizard()"
                        class="bg-red-500 text-white px-6 py-2 rounded-lg hover:bg-red-600">
                    إلغاء
                </button>
                <button type="button" id="nextBtn" onclick="nextStep()"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    التالي →
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// متغيرات المعالج
let currentStep = 1;
let selectedBudgetType = '';
let questions = [];
let answers = {};
let generatedBudget = null;

// فتح المعالج
function openAIWizard() {
    // فتح المعالج مباشرة
    document.getElementById('aiBudgetWizardModal').style.display = 'flex';
}

// إغلاق المعالج
function closeAIWizard() {
    if (confirm('هل أنت متأكد من إغلاق المعالج؟ ستفقد جميع البيانات المدخلة.')) {
        document.getElementById('aiBudgetWizardModal').style.display = 'none';
        resetWizard();
    }
}

// إعادة تعيين المعالج
function resetWizard() {
    currentStep = 1;
    selectedBudgetType = '';
    questions = [];
    answers = {};
    generatedBudget = null;
    updateWizardUI();
}

// اختيار نوع الميزانية
function selectBudgetType(type) {
    selectedBudgetType = type;
    nextStep();
}

// الخطوة التالية
function nextStep() {
    if (currentStep === 1 && !selectedBudgetType) {
        alert('يرجى اختيار نوع الميزانية');
        return;
    }

    if (currentStep === 1) {
        // تحميل الأسئلة - سينتقل للخطوة التالية بعد تحميلها
        currentStep++;
        updateWizardUI();
        loadQuestions();
    } else if (currentStep === 2) {
        // التحقق من الإجابات
        if (!validateAnswers()) {
            alert('يرجى الإجابة على جميع الأسئلة المطلوبة');
            return;
        }
        // إنشاء الميزانية - سينتقل للخطوة التالية بعد الإنشاء
        currentStep++;
        updateWizardUI();
        generateBudget();
    } else if (currentStep === 3) {
        // حفظ الميزانية
        saveBudget();
        return;
    }
}

// الخطوة السابقة
function previousStep() {
    currentStep--;
    updateWizardUI();
}

// تحديث واجهة المعالج
function updateWizardUI() {
    // إخفاء جميع الخطوات
    document.querySelectorAll('.wizard-step').forEach(step => {
        step.style.display = 'none';
    });

    // إظهار الخطوة الحالية
    document.getElementById('step' + currentStep).style.display = 'block';

    // تحديث مؤشرات الخطوات
    for (let i = 1; i <= 3; i++) {
        const indicator = document.querySelector(`#step${i}Indicator .w-10`);
        const label = document.querySelector(`#step${i}Indicator span`);
        if (i <= currentStep) {
            indicator.className = 'w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold';
            label.className = 'mr-3 font-semibold';
        } else {
            indicator.className = 'w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold';
            label.className = 'mr-3 text-gray-500';
        }
    }

    // تحديث شريط التقدم
    document.getElementById('progressBar').style.width = (currentStep * 33.33) + '%';

    // تحديث الأزرار
    document.getElementById('prevBtn').style.display = currentStep > 1 ? 'block' : 'none';
    const nextBtn = document.getElementById('nextBtn');
    if (currentStep === 3) {
        nextBtn.textContent = '💾 حفظ الميزانية';
        nextBtn.className = 'bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700';
    } else {
        nextBtn.textContent = 'التالي →';
        nextBtn.className = 'bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700';
    }
}

// تحميل الأسئلة
function loadQuestions() {
    // إظهار حالة التحميل
    const container = document.getElementById('questionsContainer');
    container.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-12 w-12 border-b-4 border-blue-600 mx-auto mb-4"></div><p>جاري تحميل الأسئلة...</p></div>';
    
    fetch('../api/ai_budget_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_questions',
            budget_type: selectedBudgetType
        })
    })
    .then(response => {
        // التحقق من استجابة الخادم
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error('خطأ في الاتصال بالخادم: ' + response.status + ' - ' + text);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('API Response:', data); // للتدقيق

        if (data.success && data.questions) {
            questions = data.questions;
            if (!questions || questions.length === 0) {
                throw new Error('لم يتم إرجاع أسئلة من الخادم');
            }
            renderQuestions();
        } else {
            throw new Error(data.error || 'خطأ غير معروف في تحميل الأسئلة');
        }
    })
    .catch(error => {
        console.error('Error loading questions:', error);
        container.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                <p class="font-bold mb-2">❌ خطأ في تحميل الأسئلة</p>
                <p class="text-sm">${error.message}</p>
                <button onclick="previousStep()" class="mt-4 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    العودة للخطوة السابقة
                </button>
            </div>
        `;
    });
}

// عرض الأسئلة
function renderQuestions() {
    const container = document.getElementById('questionsContainer');
    container.innerHTML = '';

    // Group questions by section
    const questionsBySection = {};
    questions.forEach(q => {
        const section = q.section || 'عام';
        if (!questionsBySection[section]) {
            questionsBySection[section] = [];
        }
        questionsBySection[section].push(q);
    });

    // Render by section
    Object.entries(questionsBySection).forEach(([section, sectionQuestions]) => {
        const sectionDiv = document.createElement('div');
        sectionDiv.className = 'mb-6';
        sectionDiv.innerHTML = `<h5 class="font-bold text-lg mb-3 text-blue-700 border-b pb-2">${section}</h5>`;
        
        const questionsDiv = document.createElement('div');
        questionsDiv.className = 'space-y-4';
        
        sectionQuestions.forEach((q, index) => {
            const div = document.createElement('div');
            div.className = 'p-4 border rounded-lg bg-white';
            div.innerHTML = renderQuestion(q);
            questionsDiv.appendChild(div);
        });
        
        sectionDiv.appendChild(questionsDiv);
        container.appendChild(sectionDiv);
    });
}

// Render single question
function renderQuestion(q) {
    let inputHTML = '';

    switch (q.type) {
        case 'text':
            inputHTML = `<input type="text" id="q_${q.id}" class="w-full p-2 border rounded" ${q.required ? 'required' : ''}>`;
            break;
            
        case 'number':
            const min = q.min !== undefined ? `min="${q.min}"` : '';
            const max = q.max !== undefined ? `max="${q.max}"` : '';
            const step = q.step !== undefined ? `step="${q.step}"` : '';
            const defaultValue = q.default !== undefined ? `value="${q.default}"` : '';
            inputHTML = `<input type="number" id="q_${q.id}" class="w-full p-2 border rounded" ${min} ${max} ${step} ${defaultValue} ${q.required ? 'required' : ''}>`;
            break;
            
        case 'textarea':
            const rows = q.rows || 3;
            inputHTML = `<textarea id="q_${q.id}" rows="${rows}" class="w-full p-2 border rounded" ${q.required ? 'required' : ''}></textarea>`;
            break;
            
        case 'select':
            inputHTML = `<select id="q_${q.id}" class="w-full p-2 border rounded" ${q.required ? 'required' : ''} onchange="handleQuestionChange('${q.id}', this.value)">`;
            inputHTML += '<option value="">-- اختر --</option>';
            if (q.options) {
                Object.entries(q.options).forEach(([key, value]) => {
                    inputHTML += `<option value="${key}">${value}</option>`;
                });
            }
            inputHTML += '</select>';
            break;
            
        case 'checkbox':
            if (q.options) {
                Object.entries(q.options).forEach(([key, value]) => {
                    inputHTML += `
                        <label class="flex items-center mb-2">
                            <input type="checkbox" name="q_${q.id}" value="${key}" class="ml-2">
                            <span>${value}</span>
                        </label>
                    `;
                });
            }
            break;
            
        case 'table':
            inputHTML = renderTableQuestion(q);
            break;
            
        default:
            inputHTML = `<input type="text" id="q_${q.id}" class="w-full p-2 border rounded" ${q.required ? 'required' : ''}>`;
    }

    return `
        <label class="block font-semibold mb-2">
            ${q.question} ${q.required ? '<span class="text-red-500">*</span>' : ''}
        </label>
        ${inputHTML}
        ${q.help ? `<p class="text-xs text-gray-500 mt-1">💡 ${q.help}</p>` : ''}
    `;
}

// Render table question
function renderTableQuestion(q) {
    if (!q.columns || q.columns.length === 0) {
        return '<p class="text-gray-500">لا توجد أعمدة محددة</p>';
    }
    
    let html = `
        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300" id="table_${q.id}">
                <thead>
                    <tr class="bg-gray-100">
    `;
    
    q.columns.forEach(col => {
        html += `<th class="border border-gray-300 p-2 text-right text-sm">${col}</th>`;
    });
    html += '<th class="border border-gray-300 p-2 text-center text-sm">إجراءات</th>';
    html += '</tr></thead><tbody id="table_body_' + q.id + '">';
    html += '</tbody></table>';
    html += `<button type="button" onclick="addTableRow('${q.id}')" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
        ➕ إضافة صف
    </button>`;
    html += '</div>';
    
    // Store column settings for later use
    if (!window.tableQuestions) window.tableQuestions = {};
    window.tableQuestions[q.id] = {
        columns: q.columns,
        settings: q.column_settings || {}
    };
    
    // Add initial row
    setTimeout(() => addTableRow(q.id), 100);
    
    return html;
}

// Add row to table
function addTableRow(questionId) {
    const tbody = document.getElementById('table_body_' + questionId);
    if (!tbody) return;
    
    const tableConfig = window.tableQuestions[questionId];
    if (!tableConfig) return;
    
    const row = document.createElement('tr');
    row.className = 'border-b';
    
    tableConfig.columns.forEach((col, index) => {
        const td = document.createElement('td');
        td.className = 'border border-gray-300 p-2';
        
        const colSettings = tableConfig.settings[col] || {};
        let input;
        
        if (colSettings['النوع'] === 'select_dynamic' && colSettings['مصدر_الخيارات'] === 'system_currencies') {
            // Dynamic currency select - will be populated from system
            input = document.createElement('select');
            input.className = 'w-full p-1 border rounded';
            input.name = `table_${questionId}_${col}`;
            // Options will be loaded from system
            loadCurrencyOptions(input);
        } else if (colSettings['نوع_القيمة'] === 'مبلغ') {
            input = document.createElement('input');
            input.type = 'number';
            input.step = '0.01';
            input.min = '0';
            input.className = 'w-full p-1 border rounded';
            input.name = `table_${questionId}_${col}`;
        } else if (col.includes('تاريخ')) {
            input = document.createElement('input');
            input.type = 'date';
            input.className = 'w-full p-1 border rounded';
            input.name = `table_${questionId}_${col}`;
        } else {
            input = document.createElement('input');
            input.type = 'text';
            input.className = 'w-full p-1 border rounded';
            input.name = `table_${questionId}_${col}`;
        }
        
        td.appendChild(input);
        row.appendChild(td);
    });
    
    // Delete button
    const deleteTd = document.createElement('td');
    deleteTd.className = 'border border-gray-300 p-2 text-center';
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'text-red-600 hover:text-red-800';
    deleteBtn.textContent = '🗑️';
    deleteBtn.onclick = () => row.remove();
    deleteTd.appendChild(deleteBtn);
    row.appendChild(deleteTd);
    
    tbody.appendChild(row);
}

// Load currency options for dynamic selects
function loadCurrencyOptions(selectElement) {
    // Use currencies passed from PHP or fetch from API
    let currenciesList = [];
    
    <?php if (isset($currencies_js)): ?>
    try {
        currenciesList = <?= $currencies_js ?>;
    } catch(e) {
        console.error('Error parsing currencies:', e);
    }
    <?php endif; ?>
    
    if (currenciesList.length > 0) {
        currenciesList.forEach(curr => {
            const option = document.createElement('option');
            option.value = curr.id;
            option.textContent = curr.name;
            selectElement.appendChild(option);
        });
    } else {
        // Fallback: fetch from API
        fetch('../api/get_currencies.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.currencies) {
                    data.currencies.forEach(curr => {
                        const option = document.createElement('option');
                        option.value = curr.id;
                        option.textContent = curr.name + ' (' + curr.symbol + ')';
                        selectElement.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading currencies:', error);
            });
    }
}

// Handle question change (e.g., show/hide conditional questions)
function handleQuestionChange(questionId, value) {
    // Handle conditional logic
    if (questionId === 'committee_name' && value === 'لجنة أخرى (يتم تحديدها يدوياً)') {
        const customInput = document.getElementById('q_committee_name_custom');
        if (customInput) {
            customInput.closest('div').style.display = 'block';
            customInput.required = true;
        }
    } else if (questionId === 'committee_name') {
        const customInput = document.getElementById('q_committee_name_custom');
        if (customInput) {
            customInput.closest('div').style.display = 'none';
            customInput.required = false;
        }
    }
    
    if (questionId === 'has_capital_projects' && value === 'لا') {
        const projectsTable = document.getElementById('table_capital_projects_list');
        if (projectsTable) {
            projectsTable.closest('div').style.display = 'none';
        }
    } else if (questionId === 'has_capital_projects' && value === 'نعم') {
        const projectsTable = document.getElementById('table_capital_projects_list');
        if (projectsTable) {
            projectsTable.closest('div').style.display = 'block';
        }
    }
}

// التحقق من الإجابات
function validateAnswers() {
    answers = {};
    let valid = true;

    questions.forEach(q => {
        if (q.type === 'checkbox') {
            const checkboxes = document.querySelectorAll(`input[name="q_${q.id}"]:checked`);
            answers[q.id] = Array.from(checkboxes).map(cb => cb.value);
            if (q.required && answers[q.id].length === 0) valid = false;
        } else if (q.type === 'table') {
            // Collect table data
            const tableData = [];
            const tbody = document.getElementById(`table_body_${q.id}`);
            if (tbody) {
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(row => {
                    const rowData = {};
                    const tableConfig = window.tableQuestions[q.id];
                    if (tableConfig) {
                        tableConfig.columns.forEach((col, index) => {
                            const input = row.querySelector(`input[name="table_${q.id}_${col}"], select[name="table_${q.id}_${col}"]`);
                            if (input) {
                                rowData[col] = input.value;
                            }
                        });
                        // Only add non-empty rows
                        if (Object.values(rowData).some(val => val && val.trim() !== '')) {
                            tableData.push(rowData);
                        }
                    }
                });
            }
            answers[q.id] = tableData;
            if (q.required && tableData.length === 0) valid = false;
        } else {
            const input = document.getElementById(`q_${q.id}`);
            if (input) {
                answers[q.id] = input.value;
                if (q.required && !input.value) valid = false;
            }
        }
    });

    return valid;
}

// إنشاء الميزانية
function generateBudget() {
    const loadingState = document.getElementById('loadingState');
    const budgetPreview = document.getElementById('budgetPreview');
    const nextBtn = document.getElementById('nextBtn');
    
    loadingState.style.display = 'block';
    budgetPreview.style.display = 'none';
    nextBtn.disabled = true;
    nextBtn.textContent = 'جاري الإنشاء...';

    fetch('../api/ai_budget_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'generate_budget',
            budget_type: selectedBudgetType,
            answers: answers
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                let errorData;
                try {
                    errorData = JSON.parse(text);
                } catch(e) {
                    errorData = { error: text };
                }
                throw new Error(errorData.error || 'خطأ في الاتصال بالخادم: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        loadingState.style.display = 'none';
        nextBtn.disabled = false;
        nextBtn.textContent = '💾 حفظ الميزانية';

        if (data.success && data.budget_data) {
            // Validate budget data structure
            if (!data.budget_data.budget_summary) {
                console.error('Invalid budget data structure:', data.budget_data);
                throw new Error('بيانات الميزانية غير صحيحة: لا يوجد budget_summary');
            }
            
            // Ensure all required fields exist
            generatedBudget = {
                budget_summary: {
                    total_revenues: data.budget_data.budget_summary?.total_revenues || 0,
                    total_expenses: data.budget_data.budget_summary?.total_expenses || 0,
                    balance: data.budget_data.budget_summary?.balance || 0
                },
                revenue_items: data.budget_data.revenue_items || [],
                expense_items: data.budget_data.expense_items || [],
                recommendations: data.budget_data.recommendations || []
            };
            
            displayBudgetPreview();
        } else {
            throw new Error(data.error || 'فشل في إنشاء الميزانية');
        }
    })
    .catch(error => {
        console.error('Error generating budget:', error);
        loadingState.style.display = 'none';
        nextBtn.disabled = false;
        nextBtn.textContent = '💾 حفظ الميزانية';
        
        // عرض رسالة خطأ مفصلة
        budgetPreview.style.display = 'block';
        budgetPreview.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-red-700">
                <h5 class="font-bold text-lg mb-2">❌ خطأ في إنشاء الميزانية</h5>
                <p class="mb-4">${error.message}</p>
                <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-4">
                    <p class="text-sm font-semibold mb-2">💡 حلول مقترحة:</p>
                    <ul class="text-sm list-disc list-inside space-y-1">
                        <li>تأكد من تفعيل الذكاء الاصطناعي في إعدادات النظام</li>
                        <li>تحقق من صحة مفتاح API</li>
                        <li>تأكد من أن النموذج المحدد متاح (gemini-1.5-pro أو gemini-1.5-flash)</li>
                        <li>تحقق من الاتصال بالإنترنت</li>
                    </ul>
                </div>
                <button onclick="previousStep()" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700">
                    العودة للخطوة السابقة
                </button>
            </div>
        `;
    });
}

// عرض معاينة الميزانية
function displayBudgetPreview() {
    const preview = document.getElementById('budgetPreview');
    preview.style.display = 'block';

    // Validate generatedBudget exists
    if (!generatedBudget) {
        preview.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-red-700">
                <h5 class="font-bold text-lg mb-2">❌ خطأ في عرض الميزانية</h5>
                <p>لم يتم إنشاء بيانات الميزانية بشكل صحيح.</p>
            </div>
        `;
        return;
    }

    // Validate budget_summary exists
    if (!generatedBudget.budget_summary) {
        console.error('Missing budget_summary:', generatedBudget);
        preview.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-red-700">
                <h5 class="font-bold text-lg mb-2">❌ خطأ في بنية البيانات</h5>
                <p>بيانات الميزانية غير مكتملة. يرجى المحاولة مرة أخرى.</p>
                <pre class="mt-2 text-xs bg-gray-100 p-2 rounded overflow-auto">${JSON.stringify(generatedBudget, null, 2)}</pre>
            </div>
        `;
        return;
    }

    let html = '<div class="space-y-4">';

    // الملخص
    html += '<div class="bg-blue-50 p-4 rounded-lg">';
    html += '<h5 class="font-bold mb-2">ملخص الميزانية</h5>';
    const totalRevenues = generatedBudget.budget_summary.total_revenues || 0;
    const totalExpenses = generatedBudget.budget_summary.total_expenses || 0;
    const balance = generatedBudget.budget_summary.balance || (totalRevenues - totalExpenses);
    
    html += `<p>إجمالي الإيرادات: <strong>${formatNumber(totalRevenues)}</strong></p>`;
    html += `<p>إجمالي المصاريف: <strong>${formatNumber(totalExpenses)}</strong></p>`;
    html += `<p>الرصيد: <strong class="${balance >= 0 ? 'text-green-600' : 'text-red-600'}">${formatNumber(balance)}</strong></p>`;
    html += '</div>';

    // بنود الإيرادات
    html += '<div><h5 class="font-bold mb-2">بنود الإيرادات</h5>';
    html += '<div class="space-y-2">';
    if (generatedBudget.revenue_items && generatedBudget.revenue_items.length > 0) {
        generatedBudget.revenue_items.forEach(item => {
            html += `<div class="border p-3 rounded flex justify-between items-center">`;
            html += `<div><strong>${item.name || 'إيراد غير محدد'}</strong><br><small class="text-gray-600">${item.description || 'لا يوجد وصف'}</small></div>`;
            html += `<div class="font-bold text-green-600">${formatNumber(item.amount || 0)}</div>`;
            html += `</div>`;
        });
    } else {
        html += '<p class="text-gray-500 text-sm">لا توجد بنود إيرادات</p>';
    }
    html += '</div></div>';

    // بنود المصاريف
    html += '<div><h5 class="font-bold mb-2">بنود المصاريف</h5>';
    html += '<div class="space-y-2">';
    if (generatedBudget.expense_items && generatedBudget.expense_items.length > 0) {
        generatedBudget.expense_items.forEach(item => {
            html += `<div class="border p-3 rounded flex justify-between items-center">`;
            html += `<div><strong>${item.name || 'مصروف غير محدد'}</strong><br><small class="text-gray-600">${item.description || 'لا يوجد وصف'}</small></div>`;
            html += `<div class="font-bold text-red-600">${formatNumber(item.amount || 0)}</div>`;
            html += `</div>`;
        });
    } else {
        html += '<p class="text-gray-500 text-sm">لا توجد بنود مصاريف</p>';
    }
    html += '</div></div>';

    // التوصيات
    if (generatedBudget.recommendations && generatedBudget.recommendations.length > 0) {
        html += '<div class="bg-yellow-50 p-4 rounded-lg">';
        html += '<h5 class="font-bold mb-2">💡 توصيات</h5>';
        html += '<ul class="list-disc list-inside space-y-1">';
        generatedBudget.recommendations.forEach(rec => {
            html += `<li>${rec || ''}</li>`;
        });
        html += '</ul></div>';
    }

    html += '</div>';
    preview.innerHTML = html;
}

// حفظ الميزانية
function saveBudget() {
    if (!generatedBudget) {
        alert('لا توجد بيانات ميزانية للحفظ');
        return;
    }

    const nextBtn = document.getElementById('nextBtn');
    nextBtn.disabled = true;
    nextBtn.textContent = 'جاري الحفظ...';

    // Extract metadata from answers
    const fiscalYear = answers.year || answers.committee_year || new Date().getFullYear();
    const committeeName = answers.committee_name || answers.committee_name_custom || null;
    const budgetName = committeeName ? `ميزانية ${committeeName} - ${fiscalYear}` : `ميزانية ${fiscalYear}`;
    
    // Get currency from answers (prefer first available)
    const currencyId = answers.revenue_independent_fund_currency || 
                      answers.opex_salaries_currency || 
                      answers.revenue_local_taxes_currency || 
                      1;

    fetch('../api/ai_budget_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_generated_budget',
            budget_data: generatedBudget,
            metadata: {
                name: budgetName,
                fiscal_year: fiscalYear,
                start_date: `${fiscalYear}-01-01`,
                end_date: `${fiscalYear}-12-31`,
                currency_id: currencyId,
                committee_id: <?= $selected_committee_id ?? 'null' ?>
            },
            generation_params: answers
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                let errorData;
                try {
                    errorData = JSON.parse(text);
                } catch(e) {
                    errorData = { error: text };
                }
                throw new Error(errorData.error || 'خطأ في الاتصال بالخادم: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ تم حفظ الميزانية بنجاح!');
            window.location.href = 'budgets.php?budget_id=' + data.budget_id;
        } else {
            throw new Error(data.error || 'فشل في حفظ الميزانية');
        }
    })
    .catch(error => {
        console.error('Error saving budget:', error);
        alert('❌ خطأ في حفظ الميزانية:\n' + error.message);
        nextBtn.disabled = false;
        nextBtn.textContent = '💾 حفظ الميزانية';
    });
}

// تنسيق الأرقام
function formatNumber(num) {
    return new Intl.NumberFormat('ar-LB').format(num);
}
</script>

<style>
.modal { display: none !important; }
.modal[style*="display: flex"] { display: flex !important; }
</style>
