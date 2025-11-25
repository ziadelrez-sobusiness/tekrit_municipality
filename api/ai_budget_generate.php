<?php
/**
 * API endpoint for AI Budget Generation
 * Handles: get_questions, generate_budget, save_generated_budget
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_helper.php';
require_once __DIR__ . '/../includes/ai_service.php';

// Authentication check
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->exec("SET NAMES utf8mb4");

$user = $auth->getUserInfo();

// Check if AI is enabled
$ai_helper = new AIHelper();
if (!$ai_helper->isAIEnabled()) {
    echo json_encode([
        'success' => false,
        'error' => 'الذكاء الاصطناعي غير مفعل. يرجى تفعيله من إعدادات النظام.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_questions':
            handleGetQuestions($input, $db);
            break;
            
        case 'generate_budget':
            handleGenerateBudget($input, $db, $ai_helper);
            break;
            
        case 'save_generated_budget':
            handleSaveBudget($input, $db, $user);
            break;
            
        default:
            throw new Exception('إجراء غير معروف: ' . $action);
    }
} catch (Exception $e) {
    error_log("AI Budget API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Handle get_questions action
 */
function handleGetQuestions($input, $db) {
    $budget_type = $input['budget_type'] ?? 'general';
    
    // Load questions from JSON file
    $questions_file = __DIR__ . '/budget_questions.json';
    if (!file_exists($questions_file)) {
        throw new Exception('ملف الأسئلة غير موجود');
    }
    
    $questions_data = json_decode(file_get_contents($questions_file), true);
    if (!$questions_data) {
        throw new Exception('خطأ في قراءة ملف الأسئلة');
    }
    
    // Get questions based on budget type
    $question_key = $budget_type === 'committee' ? 'ميزانية_لجنة' : 'ميزانية_عامة';
    $raw_questions = $questions_data['الأسئلة'][$question_key] ?? [];
    
    if (empty($raw_questions)) {
        throw new Exception('لا توجد أسئلة متاحة لهذا النوع من الميزانية');
    }
    
    // Process questions and resolve dynamic options
    $questions = [];
    foreach ($raw_questions as $q) {
        $processed_q = processQuestion($q, $db);
        if ($processed_q) {
            $questions[] = $processed_q;
        }
    }
    
    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'version' => $questions_data['نسخة_المخطط'] ?? '1.0'
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Process a single question and resolve dynamic options
 */
function processQuestion($q, $db) {
    $question = [
        'id' => $q['المعرف'],
        'question' => $q['النص'],
        'type' => mapQuestionType($q['النوع']),
        'required' => $q['إجباري'] ?? false,
        'help' => $q['ملاحظات'] ?? '',
        'section' => $q['القسم'] ?? 'عام'
    ];
    
    // Handle different question types
    switch ($q['النوع']) {
        case 'select':
        case 'select_dynamic':
            if ($q['النوع'] === 'select_dynamic' && isset($q['مصدر_الخيارات'])) {
                // Load dynamic options
                $options = loadDynamicOptions($q['مصدر_الخيارات'], $db);
                $question['options'] = $options;
            } else {
                // Static options
                $options = [];
                foreach ($q['خيارات'] as $opt) {
                    $options[$opt] = $opt;
                }
                $question['options'] = $options;
            }
            break;
            
        case 'boolean':
            $question['options'] = [
                'نعم' => 'نعم',
                'لا' => 'لا'
            ];
            break;
            
        case 'year':
            $question['type'] = 'number';
            $question['default'] = date('Y');
            $question['min'] = 2020;
            $question['max'] = 2100;
            break;
            
        case 'number':
            $question['min'] = 0;
            if (isset($q['نوع_القيمة']) && $q['نوع_القيمة'] === 'مبلغ') {
                $question['step'] = 0.01;
            }
            break;
            
        case 'table':
            $question['columns'] = $q['خيارات'] ?? [];
            $question['column_settings'] = $q['إعدادات_الأعمدة'] ?? [];
            break;
            
        case 'text_multi_line':
            $question['type'] = 'textarea';
            $question['rows'] = 3;
            break;
    }
    
    return $question;
}

/**
 * Map Arabic question types to English types for frontend
 */
function mapQuestionType($arabic_type) {
    $mapping = [
        'text' => 'text',
        'number' => 'number',
        'year' => 'number',
        'textarea' => 'textarea',
        'text_multi_line' => 'textarea',
        'select' => 'select',
        'select_dynamic' => 'select',
        'boolean' => 'select',
        'checkbox' => 'checkbox',
        'table' => 'table'
    ];
    
    return $mapping[$arabic_type] ?? 'text';
}

/**
 * Load dynamic options from system
 */
function loadDynamicOptions($source, $db) {
    switch ($source) {
        case 'system_currencies':
            $stmt = $db->query("SELECT id, currency_name, currency_symbol, currency_code FROM currencies WHERE is_active = 1 ORDER BY currency_code");
            $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $options = [];
            foreach ($currencies as $curr) {
                $key = $curr['id'];
                $value = $curr['currency_name'] . ' (' . $curr['currency_symbol'] . ')';
                $options[$key] = $value;
            }
            return $options;
            
        default:
            return [];
    }
}

/**
 * Handle generate_budget action
 */
function handleGenerateBudget($input, $db, $ai_helper) {
    $budget_type = $input['budget_type'] ?? 'general';
    $answers = $input['answers'] ?? [];
    
    if (empty($answers)) {
        throw new Exception('لم يتم إرسال أي إجابات');
    }
    
    // Build prompt for AI
    $prompt = buildAIPrompt($budget_type, $answers, $db);
    
    // Get AI service
    $ai_service = new AIService();
    
    // Generate budget using AI
    $response = $ai_service->sendTextRequest($prompt, getSystemMessage($budget_type));
    
    if (!$response['success']) {
        throw new Exception($response['error'] ?? 'فشل في إنشاء الميزانية');
    }
    
    // Parse AI response
    $budget_data = parseAIResponse($response['content'], $budget_type, $answers);
    
    echo json_encode([
        'success' => true,
        'budget_data' => $budget_data
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Build AI prompt from answers
 */
function buildAIPrompt($budget_type, $answers, $db) {
    // Load question labels for better context
    $questions_file = __DIR__ . '/budget_questions.json';
    $questions_data = json_decode(file_get_contents($questions_file), true);
    $question_key = $budget_type === 'committee' ? 'ميزانية_لجنة' : 'ميزانية_عامة';
    $questions = $questions_data['الأسئلة'][$question_key] ?? [];
    
    // Create a mapping of question IDs to labels
    $question_labels = [];
    foreach ($questions as $q) {
        $question_labels[$q['المعرف']] = $q['النص'];
    }
    
    // بناء البرومبت بناءً على طلب المستخدم الجديد
    $prompt = "الدور والشخصية (Role and Persona):\n";
    $prompt .= "تصرف كخبير مالي ومحاسب أول متخصص في المالية العامة للبلديات في لبنان. لديك خبرة عميقة في قانون المحاسبة العمومية اللبناني (مرسوم اشتراعي رقم 14969/1963 وتعديلاته)، وتفهم تماماً طبيعة الإيرادات والنفقات للبلديات في المناطق الريفية والنائية مثل قضاء عكار. أنت دقيق، منظم، وتفكر بشكل استراتيجي لضمان الاستدامة المالية وتحقيق الأهداف التنموية للبلدية.\n\n";
    
    $prompt .= "السياق (Context):\n";
    $prompt .= "أنت مكلف بإعداد مسودة ميزانية شاملة لـ بلدية تكريت، وهي بلدة تقع في قضاء عكار، شمال لبنان. يجب أن تعكس الميزانية الواقع الاقتصادي والاجتماعي للمنطقة، مع الأخذ في الاعتبار التحديات مثل ضعف التحصيل الضريبي، والاعتماد على تمويل الصندوق البلدي المستقل، والحاجة إلى مشاريع تنموية أساسية (بنية تحتية، خدمات اجتماعية، بيئة).\n\n";
    
    if ($budget_type === 'general') {
        $prompt .= "المهمة الأساسية (Primary Task):\n";
        $prompt .= "قم بإنشاء مسودة ميزانية سنوية مفصلة لبلدية تكريت لعام 2026. يجب أن تكون الميزانية متوازنة (الإيرادات = النفقات) ومنظمة وفقاً لمعايير المحاسبة العامة اللبنانية.\n\n";
        
        $prompt .= "البيانات المقدمة من المستخدم:\n";
        // Add answers with proper labels
        foreach ($answers as $key => $value) {
            if (!empty($value) && $value !== '0' && $value !== '') {
                $label = $question_labels[$key] ?? $key;
                if (is_array($value)) {
                    $formatted_value = '';
                    if (isset($value[0]) && is_array($value[0])) {
                        // Table data
                        $formatted_value = "\n";
                        foreach ($value as $row) {
                            $formatted_value .= "  - " . implode(' | ', array_values($row)) . "\n";
                        }
                    } else {
                        $formatted_value = implode(', ', $value);
                    }
                    $prompt .= "• " . $label . ": " . $formatted_value . "\n";
                } else {
                    $prompt .= "• " . $label . ": " . $value . "\n";
                }
            }
        }
        
        $prompt .= "\nالخطوات والهيكل المطلوب (Required Steps and Structure):\n";
        
        $prompt .= "الخطوة الأولى: تقدير الإيرادات (Revenue Estimation)\n";
        $prompt .= "قم بتقدير الإيرادات المتوقعة، مقسماً إلى الفئات التالية مع تقديرات واقعية:\n";
        $prompt .= "1. الإيرادات الذاتية: رسوم على العقارات المبنية، رخص البناء، إشغال أملاك عمومية، رسوم أخرى.\n";
        $prompt .= "2. الإيرادات المحوّلة: حصة الصندوق البلدي المستقل (المصدر الرئيسي)، عوائد الاتصالات، إعانات أو هبات.\n\n";
        
        $prompt .= "الخطوة الثانية: توزيع النفقات الإجمالية (Overall Expenditure Allocation)\n";
        $prompt .= "قم بتوزيع النفقات على الأبواب الرئيسية التالية:\n";
        $prompt .= "1. الباب الأول: رواتب وأجور وتعويضات.\n";
        $prompt .= "2. الباب الثاني: نفقات إدارية وتشغيلية.\n";
        $prompt .= "3. الباب الثالث: نفقات الخدمات والمشاريع.\n";
        $prompt .= "4. الباب الرابع: نفقات استثمارية وتنموية.\n";
        $prompt .= "5. الباب الخامس: تسديد ديون وفوائد (إن وجدت).\n";
        $prompt .= "6. احتياطي الميزانية (5-10%).\n\n";
        
        $prompt .= "الخطوة الثالثة: تفصيل ميزانيات اللجان (Committee-Specific Budgets)\n";
        $prompt .= "قم بإنشاء بنود نفقات مفصلة لكل لجنة من اللجان التالية (ضمن مصفوفة النفقات):\n";
        $prompt .= "1. لجنة الأشغال العامة والبنية التحتية (صيانة طرق، إنارة، آليات).\n";
        $prompt .= "2. لجنة البيئة والصحة (نفايات، مكافحة حشرات، توعية).\n";
        $prompt .= "3. لجنة الشؤون الاجتماعية والثقافية (مساعدات، أنشطة، احتفالات).\n\n";
        
        $prompt .= "═══════════════════════════════════════════════════════════\n";
        $prompt .= "صيغة الإرجاع (Output Format):\n";
        $prompt .= "يجب إرجاع النتيجة بصيغة JSON فقط بدون أي نص إضافي قبل أو بعد JSON.\n";
        $prompt .= "جميع النصوص داخل JSON يجب أن تكون بالعربية.\n\n";
        $prompt .= "البنية المطلوبة:\n";
        $prompt .= "{\n";
        $prompt .= '  "budget_summary": {"total_revenues": عدد, "total_expenses": عدد, "balance": عدد},' . "\n";
        $prompt .= '  "revenue_items": [{"name": "اسم البند بالعربية", "description": "وصف تفصيلي للمصدر", "amount": عدد, "category": "الفئة (مثلاً: إيرادات ذاتية)"}],' . "\n";
        $prompt .= '  "expense_items": [{"name": "اسم البند بالعربية", "description": "وصف تفصيلي للغرض", "amount": عدد, "category": "اسم اللجنة أو الباب (مثلاً: لجنة الأشغال العامة)", "item_type": "مصروف"}],' . "\n";
        $prompt .= '  "recommendations": ["توصية استراتيجية 1", "توصية 2", ...]' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "ملاحظة هامة: قم بدمج ميزانيات اللجان داخل قائمة 'expense_items' مع وضع اسم اللجنة في حقل 'category'.\n";
        
    } else {
        // ميزانية لجنة محددة (نفس الشخصية ولكن نطاق أضيق)
        $prompt .= "المهمة: إعداد ميزانية مفصلة للجنة من لجان المجلس البلدي.\n\n";
        
        foreach ($answers as $key => $value) {
            if (!empty($value) && $value !== '0' && $value !== '') {
                $label = $question_labels[$key] ?? $key;
                if (is_array($value)) {
                    $formatted_value = is_array($value[0]) ? "\n" . implode("\n", array_map(function($row) {
                        return "  - " . implode(' | ', array_values($row));
                    }, $value)) : implode(', ', $value);
                    $prompt .= "• " . $label . ": " . $formatted_value . "\n";
                } else {
                    $prompt .= "• " . $label . ": " . $value . "\n";
                }
            }
        }
        
        $prompt .= "\nيرجى إنشاء ميزانية مفصلة للجنة تتضمن:\n";
        $prompt .= "1. بنود الإيرادات الخاصة باللجنة (إن وجدت)\n";
        $prompt .= "2. بنود المصاريف للأنشطة والمشاريع المخططة (مقسمة حسب الأهداف)\n";
        $prompt .= "3. ملخص الميزانية\n";
        $prompt .= "4. توصيات لتوزيع الميزانية\n\n";
        $prompt .= "يرجى إرجاع النتيجة بصيغة JSON بنفس البنية المذكورة أعلاه.\n";
    }
    
    return $prompt;
}

/**
 * Get system message for AI
 */
function getSystemMessage($budget_type) {
    $message = "أنت خبير مالي ومحاسب أول متخصص في المالية العامة للبلديات في لبنان.\n\n";
    $message .= "قواعد صارمة:\n";
    $message .= "1. الرد حصراً بصيغة JSON.\n";
    $message .= "2. اللغة العربية فقط لجميع النصوص.\n";
    $message .= "3. الالتزام بقانون المحاسبة العمومية اللبناني.\n";
    $message .= "4. الميزانية يجب أن تكون متوازنة (الإيرادات = النفقات) قدر الإمكان.\n";
    $message .= "5. دمج ميزانيات اللجان ضمن النفقات العامة مع تحديد الفئة.\n";
    
    return $message;
}

/**
 * Parse AI response and structure budget data
 */
function parseAIResponse($ai_content, $budget_type, $answers) {
    // Log the raw response for debugging
    error_log("AI Response (first 500 chars): " . substr($ai_content, 0, 500));
    
    // Try to extract JSON from response
    $json_start = strpos($ai_content, '{');
    $json_end = strrpos($ai_content, '}');
    
    if ($json_start !== false && $json_end !== false) {
        $json_str = substr($ai_content, $json_start, $json_end - $json_start + 1);
        $parsed = json_decode($json_str, true);
        
        if ($parsed && json_last_error() === JSON_ERROR_NONE) {
            // Validate structure
            if (!isset($parsed['budget_summary'])) {
                $parsed['budget_summary'] = [
                    'total_revenues' => 0,
                    'total_expenses' => 0,
                    'balance' => 0
                ];
            }
            
            // Ensure arrays exist
            if (!isset($parsed['revenue_items']) || !is_array($parsed['revenue_items'])) {
                $parsed['revenue_items'] = [];
            }
            if (!isset($parsed['expense_items']) || !is_array($parsed['expense_items'])) {
                $parsed['expense_items'] = [];
            }
            if (!isset($parsed['recommendations']) || !is_array($parsed['recommendations'])) {
                $parsed['recommendations'] = [];
            }
            
            // Calculate totals if missing
            if (!isset($parsed['budget_summary']['total_revenues']) || $parsed['budget_summary']['total_revenues'] == 0) {
                $parsed['budget_summary']['total_revenues'] = array_sum(array_column($parsed['revenue_items'], 'amount'));
            }
            if (!isset($parsed['budget_summary']['total_expenses']) || $parsed['budget_summary']['total_expenses'] == 0) {
                $parsed['budget_summary']['total_expenses'] = array_sum(array_column($parsed['expense_items'], 'amount'));
            }
            if (!isset($parsed['budget_summary']['balance'])) {
                $parsed['budget_summary']['balance'] = $parsed['budget_summary']['total_revenues'] - $parsed['budget_summary']['total_expenses'];
            }
            
            return $parsed;
        } else {
            error_log("JSON Parse Error: " . json_last_error_msg());
        }
    }
    
    // Fallback: create basic structure from answers
    error_log("Using fallback budget structure");
    return createFallbackBudget($budget_type, $answers);
}

/**
 * Create fallback budget structure if AI parsing fails
 */
function createFallbackBudget($budget_type, $answers) {
    $budget = [
        'budget_summary' => [
            'total_revenues' => 0,
            'total_expenses' => 0,
            'balance' => 0
        ],
        'revenue_items' => [],
        'expense_items' => [],
        'recommendations' => [
            'يرجى مراجعة الميزانية يدوياً والتأكد من جميع المبالغ',
            'تأكد من توازن الإيرادات والمصاريف',
            'راجع الأولويات الاستراتيجية عند توزيع الميزانية'
        ]
    ];
    
    // Extract revenue items from answers
    if (isset($answers['revenue_independent_fund']) && $answers['revenue_independent_fund'] > 0) {
        $budget['revenue_items'][] = [
            'name' => 'الصندوق البلدي المستقل',
            'description' => 'إيرادات من الصندوق البلدي المستقل',
            'amount' => floatval($answers['revenue_independent_fund']),
            'category' => 'إيرادات حكومية'
        ];
        $budget['budget_summary']['total_revenues'] += floatval($answers['revenue_independent_fund']);
    }
    
    if (isset($answers['revenue_local_taxes']) && $answers['revenue_local_taxes'] > 0) {
        $budget['revenue_items'][] = [
            'name' => 'الرسوم والضرائب المحلية',
            'description' => 'إيرادات من الرسوم والضرائب المحلية',
            'amount' => floatval($answers['revenue_local_taxes']),
            'category' => 'إيرادات محلية'
        ];
        $budget['budget_summary']['total_revenues'] += floatval($answers['revenue_local_taxes']);
    }
    
    // Extract expense items
    if (isset($answers['opex_salaries']) && $answers['opex_salaries'] > 0) {
        $budget['expense_items'][] = [
            'name' => 'الرواتب والأجور',
            'description' => 'إجمالي رواتب وأجور موظفي البلدية',
            'amount' => floatval($answers['opex_salaries']),
            'category' => 'نفقات تشغيلية',
            'item_type' => 'مصروف'
        ];
        $budget['budget_summary']['total_expenses'] += floatval($answers['opex_salaries']);
    }
    
    if (isset($answers['opex_utilities']) && $answers['opex_utilities'] > 0) {
        $budget['expense_items'][] = [
            'name' => 'فواتير الكهرباء والمياه والاتصالات',
            'description' => 'فواتير المرافق العامة',
            'amount' => floatval($answers['opex_utilities']),
            'category' => 'نفقات تشغيلية',
            'item_type' => 'مصروف'
        ];
        $budget['budget_summary']['total_expenses'] += floatval($answers['opex_utilities']);
    }
    
    $budget['budget_summary']['balance'] = $budget['budget_summary']['total_revenues'] - $budget['budget_summary']['total_expenses'];
    
    return $budget;
}

/**
 * Handle save_generated_budget action
 */
function handleSaveBudget($input, $db, $user) {
    $budget_data = $input['budget_data'] ?? null;
    $metadata = $input['metadata'] ?? [];
    
    if (!$budget_data) {
        throw new Exception('لا توجد بيانات ميزانية للحفظ');
    }
    
    $db->beginTransaction();
    
    try {
        // Create budget record
        $budget_code = 'BUD-AI-' . date('Y') . '-' . time();
        $name = $metadata['name'] ?? 'ميزانية مُنشأة بالذكاء الاصطناعي';
        $fiscal_year = intval($metadata['fiscal_year'] ?? date('Y'));
        $start_date = $metadata['start_date'] ?? $fiscal_year . '-01-01';
        $end_date = $metadata['end_date'] ?? $fiscal_year . '-12-31';
        $currency_id = intval($metadata['currency_id'] ?? 1);
        $committee_id = !empty($metadata['committee_id']) ? intval($metadata['committee_id']) : null;
        
        // Calculate total amount
        $total_amount = ($budget_data['budget_summary']['total_revenues'] ?? 0);
        
        $generation_params = isset($input['generation_params']) ? json_encode($input['generation_params'], JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $db->prepare("
            INSERT INTO budgets (
                budget_code, name, fiscal_year, start_date, end_date,
                total_amount, currency_id, committee_id,
                description, created_by, status, ai_generation_params
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'مسودة', ?)
        ");
        
        $description = "ميزانية تم إنشاؤها تلقائياً باستخدام الذكاء الاصطناعي\n";
        $description .= "إجمالي الإيرادات: " . number_format($budget_data['budget_summary']['total_revenues'] ?? 0, 2) . "\n";
        $description .= "إجمالي المصاريف: " . number_format($budget_data['budget_summary']['total_expenses'] ?? 0, 2) . "\n";
        $description .= "الرصيد: " . number_format($budget_data['budget_summary']['balance'] ?? 0, 2);
        
        $stmt->execute([
            $budget_code, $name, $fiscal_year, $start_date, $end_date,
            $total_amount, $currency_id, $committee_id,
            $description, $user['id'], $generation_params
        ]);
        
        $budget_id = $db->lastInsertId();
        
        // Add revenue items
        if (!empty($budget_data['revenue_items'])) {
            foreach ($budget_data['revenue_items'] as $item) {
                $stmt = $db->prepare("
                    INSERT INTO budget_items (
                        budget_id, item_code, name, description,
                        item_type, category, allocated_amount, currency_id,
                        remaining_amount
                    ) VALUES (?, ?, ?, ?, 'إيراد', ?, ?, ?, ?)
                ");
                
                $item_code = 'REV-' . str_pad($budget_id, 4, '0', STR_PAD_LEFT) . '-' . uniqid();
                $amount = floatval($item['amount'] ?? 0);
                
                $stmt->execute([
                    $budget_id,
                    $item_code,
                    $item['name'] ?? 'إيراد غير محدد',
                    $item['description'] ?? '',
                    $item['category'] ?? 'إيرادات',
                    $amount,
                    $currency_id,
                    $amount
                ]);
            }
        }
        
        // Add expense items
        if (!empty($budget_data['expense_items'])) {
            foreach ($budget_data['expense_items'] as $item) {
                $stmt = $db->prepare("
                    INSERT INTO budget_items (
                        budget_id, item_code, name, description,
                        item_type, category, allocated_amount, currency_id,
                        remaining_amount
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $item_code = 'EXP-' . str_pad($budget_id, 4, '0', STR_PAD_LEFT) . '-' . uniqid();
                $amount = floatval($item['amount'] ?? 0);
                
                $stmt->execute([
                    $budget_id,
                    $item_code,
                    $item['name'] ?? 'مصروف غير محدد',
                    $item['description'] ?? '',
                    $item['item_type'] ?? 'مصروف',
                    $item['category'] ?? 'نفقات',
                    $amount,
                    $currency_id,
                    $amount
                ]);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'budget_id' => $budget_id,
            'message' => 'تم حفظ الميزانية بنجاح'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('خطأ في حفظ الميزانية: ' . $e->getMessage());
    }
}
?>
