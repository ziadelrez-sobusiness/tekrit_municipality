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
    // التحقق من تفعيل AI
    fetch('../api/ai_budget_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'check_status' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.enabled === false) {
            alert('الذكاء الاصطناعي غير مفعل. يرجى تفعيله من إعدادات النظام أولاً.');
            return;
        }
        document.getElementById('aiBudgetWizardModal').style.display = 'flex';
    })
    .catch(() => {
        document.getElementById('aiBudgetWizardModal').style.display = 'flex';
    });
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
        // تحميل الأسئلة
        loadQuestions();
    } else if (currentStep === 2) {
        // التحقق من الإجابات
        if (!validateAnswers()) {
            alert('يرجى الإجابة على جميع الأسئلة المطلوبة');
            return;
        }
        // إنشاء الميزانية
        generateBudget();
    } else if (currentStep === 3) {
        // حفظ الميزانية
        saveBudget();
        return;
    }

    currentStep++;
    updateWizardUI();
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
    fetch('../api/ai_budget_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_questions',
            budget_type: selectedBudgetType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            questions = data.questions;
            renderQuestions();
        } else {
            alert('خطأ في تحميل الأسئلة: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال');
    });
}

// عرض الأسئلة
function renderQuestions() {
    const container = document.getElementById('questionsContainer');
    container.innerHTML = '';

    questions.forEach((q, index) => {
        const div = document.createElement('div');
        div.className = 'p-4 border rounded-lg';

        let inputHTML = '';

        switch (q.type) {
            case 'text':
                inputHTML = `<input type="text" id="q_${q.id}" class="w-full p-2 border rounded" ${q.required ? 'required' : ''}>`;
                break;
            case 'number':
                inputHTML = `<input type="number" id="q_${q.id}" class="w-full p-2 border rounded" ${q.default ? 'value="' + q.default + '"' : ''} ${q.required ? 'required' : ''}>`;
                break;
            case 'textarea':
                inputHTML = `<textarea id="q_${q.id}" rows="3" class="w-full p-2 border rounded" ${q.required ? 'required' : ''}></textarea>`;
                break;
            case 'select':
                inputHTML = `<select id="q_${q.id}" class="w-full p-2 border rounded" ${q.required ? 'required' : ''}>`;
                inputHTML += '<option value="">-- اختر --</option>';
                Object.entries(q.options).forEach(([key, value]) => {
                    inputHTML += `<option value="${key}">${value}</option>`;
                });
                inputHTML += '</select>';
                break;
            case 'checkbox':
                Object.entries(q.options).forEach(([key, value]) => {
                    inputHTML += `
                        <label class="flex items-center mb-2">
                            <input type="checkbox" name="q_${q.id}" value="${key}" class="ml-2">
                            <span>${value}</span>
                        </label>
                    `;
                });
                break;
        }

        div.innerHTML = `
            <label class="block font-semibold mb-2">
                ${q.question} ${q.required ? '<span class="text-red-500">*</span>' : ''}
            </label>
            ${inputHTML}
            ${q.help ? `<p class="text-xs text-gray-500 mt-1">${q.help}</p>` : ''}
        `;

        container.appendChild(div);
    });
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
        } else {
            const input = document.getElementById(`q_${q.id}`);
            answers[q.id] = input.value;
            if (q.required && !input.value) valid = false;
        }
    });

    return valid;
}

// إنشاء الميزانية
function generateBudget() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('budgetPreview').style.display = 'none';
    document.getElementById('nextBtn').disabled = true;

    fetch('../api/ai_budget_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'generate_budget',
            budget_type: selectedBudgetType,
            answers: answers
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('nextBtn').disabled = false;

        if (data.success) {
            generatedBudget = data.budget_data;
            displayBudgetPreview();
        } else {
            alert('خطأ في إنشاء الميزانية: ' + data.error);
            previousStep();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('nextBtn').disabled = false;
        alert('حدث خطأ في الاتصال بخدمة الذكاء الاصطناعي');
        previousStep();
    });
}

// عرض معاينة الميزانية
function displayBudgetPreview() {
    const preview = document.getElementById('budgetPreview');
    preview.style.display = 'block';

    let html = '<div class="space-y-4">';

    // الملخص
    html += '<div class="bg-blue-50 p-4 rounded-lg">';
    html += '<h5 class="font-bold mb-2">ملخص الميزانية</h5>';
    html += `<p>إجمالي الإيرادات: <strong>${formatNumber(generatedBudget.budget_summary.total_revenues)}</strong></p>`;
    html += `<p>إجمالي المصاريف: <strong>${formatNumber(generatedBudget.budget_summary.total_expenses)}</strong></p>`;
    html += `<p>الرصيد: <strong class="${generatedBudget.budget_summary.balance >= 0 ? 'text-green-600' : 'text-red-600'}">${formatNumber(generatedBudget.budget_summary.balance)}</strong></p>`;
    html += '</div>';

    // بنود الإيرادات
    html += '<div><h5 class="font-bold mb-2">بنود الإيرادات</h5>';
    html += '<div class="space-y-2">';
    generatedBudget.revenue_items.forEach(item => {
        html += `<div class="border p-3 rounded flex justify-between items-center">`;
        html += `<div><strong>${item.name}</strong><br><small class="text-gray-600">${item.description || ''}</small></div>`;
        html += `<div class="font-bold text-green-600">${formatNumber(item.amount)}</div>`;
        html += `</div>`;
    });
    html += '</div></div>';

    // بنود المصاريف
    html += '<div><h5 class="font-bold mb-2">بنود المصاريف</h5>';
    html += '<div class="space-y-2">';
    generatedBudget.expense_items.forEach(item => {
        html += `<div class="border p-3 rounded flex justify-between items-center">`;
        html += `<div><strong>${item.name}</strong><br><small class="text-gray-600">${item.description || ''}</small></div>`;
        html += `<div class="font-bold text-red-600">${formatNumber(item.amount)}</div>`;
        html += `</div>`;
    });
    html += '</div></div>';

    // التوصيات
    if (generatedBudget.recommendations && generatedBudget.recommendations.length > 0) {
        html += '<div class="bg-yellow-50 p-4 rounded-lg">';
        html += '<h5 class="font-bold mb-2">💡 توصيات</h5>';
        html += '<ul class="list-disc list-inside space-y-1">';
        generatedBudget.recommendations.forEach(rec => {
            html += `<li>${rec}</li>`;
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

    document.getElementById('nextBtn').disabled = true;
    document.getElementById('nextBtn').textContent = 'جاري الحفظ...';

    fetch('../api/ai_budget_generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_generated_budget',
            budget_data: generatedBudget,
            metadata: {
                name: answers.committee_name || 'ميزانية مُنشأة بالذكاء الاصطناعي',
                fiscal_year: answers.fiscal_year || new Date().getFullYear(),
                start_date: `${answers.fiscal_year || new Date().getFullYear()}-01-01`,
                end_date: `${answers.fiscal_year || new Date().getFullYear()}-12-31`,
                currency_id: 1,
                committee_id: <?= $selected_committee_id ?? 'null' ?>
            }
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تم حفظ الميزانية بنجاح!');
            window.location.href = 'budgets.php?budget_id=' + data.budget_id;
        } else {
            alert('خطأ في حفظ الميزانية: ' + data.error);
            document.getElementById('nextBtn').disabled = false;
            document.getElementById('nextBtn').textContent = '💾 حفظ الميزانية';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ في حفظ الميزانية');
        document.getElementById('nextBtn').disabled = false;
        document.getElementById('nextBtn').textContent = '💾 حفظ الميزانية';
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
