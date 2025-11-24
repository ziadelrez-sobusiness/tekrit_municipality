<?php
/**
 * API Endpoint - إنشاء ميزانية بواسطة الذكاء الاصطناعي
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_service.php';
require_once __DIR__ . '/../includes/ai_budget_questions.php';

// التأكد من تسجيل الدخول
$auth->requireLogin();

// التحقق من الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مسموحة']);
    exit;
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

// التعامل مع JSON غير صحيح
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'بيانات JSON غير صحيحة: ' . json_last_error_msg()
    ]);
    exit;
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'لا توجد بيانات']);
    exit;
}

$action = $input['action'] ?? '';

// تسجيل الطلب للتدقيق (في بيئة التطوير فقط)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("AI Budget API Request - Action: $action, Budget Type: " . ($input['budget_type'] ?? 'N/A'));
}

try {
    switch ($action) {
        case 'get_questions':
            // إرجاع الأسئلة (لا يحتاج تفعيل AI)
            $budget_type = $input['budget_type'] ?? 'general';
            $questions = $budget_type === 'general' ?
                AIBudgetQuestions::getMunicipalBudgetQuestions() :
                AIBudgetQuestions::getCommitteeBudgetQuestions();

            echo json_encode([
                'success' => true,
                'questions' => $questions,
                'budget_type' => $budget_type
            ]);
            break;

        case 'generate_budget':
            // الحصول على الإجابات
            $answers = $input['answers'] ?? [];
            $budget_type = $input['budget_type'] ?? 'general';

            if (empty($answers)) {
                throw new Exception('يرجى الإجابة على الأسئلة أولاً');
            }

            // التحقق من تفعيل AI
            $ai_enabled = false;
            try {
                $ai_enabled = isAIEnabled();
            } catch (Exception $e) {
                // تجاهل خطأ الاتصال بقاعدة البيانات
            }

            if (!$ai_enabled) {
                // وضع تجريبي: إنشاء ميزانية نموذجية
                $budget_data = AIBudgetQuestions::generateSampleBudget($answers, $budget_type);

                echo json_encode([
                    'success' => true,
                    'budget_data' => $budget_data,
                    'demo_mode' => true,
                    'message' => 'تم إنشاء ميزانية نموذجية. لاستخدام الذكاء الاصطناعي، يرجى تفعيله من إعدادات النظام.'
                ]);
                break;
            }

            // بناء prompt
            $prompt = AIBudgetQuestions::buildAIPrompt($answers, $budget_type);

            $system_message = "أنت خبير في المالية العامة والإدارة البلدية في لبنان. ";
            $system_message .= "قم بإنشاء ميزانية واقعية ومتوازنة بناءً على المعلومات المقدمة. ";
            $system_message .= "يجب أن تكون جميع المبالغ بالليرة اللبنانية وأن تكون متوافقة مع قانون البلديات اللبناني. ";
            $system_message .= "أعط الرد بصيغة JSON فقط بدون أي نص إضافي.";

            // إرسال الطلب للذكاء الاصطناعي
            try {
                $ai_service = new AIService();
                $result = $ai_service->sendTextRequest($prompt, $system_message, [
                    'temperature' => 0.7,
                    'max_tokens' => 3000
                ]);

                if (!$result['success']) {
                    throw new Exception($result['error'] ?? 'فشل الاتصال بخدمة الذكاء الاصطناعي');
                }

                // تحليل النتيجة
                $ai_content = $result['content'];

                // محاولة استخراج JSON من الرد
                $json_start = strpos($ai_content, '{');
                $json_end = strrpos($ai_content, '}');

                if ($json_start !== false && $json_end !== false) {
                    $json_string = substr($ai_content, $json_start, $json_end - $json_start + 1);
                    $budget_data = json_decode($json_string, true);

                    if (!$budget_data) {
                        throw new Exception('فشل تحليل رد الذكاء الاصطناعي');
                    }

                    echo json_encode([
                        'success' => true,
                        'budget_data' => $budget_data,
                        'raw_response' => $ai_content,
                        'demo_mode' => false
                    ]);
                } else {
                    throw new Exception('الرد لا يحتوي على بيانات صحيحة');
                }
            } catch (Exception $e) {
                // في حالة فشل AI، استخدم الوضع التجريبي
                $budget_data = AIBudgetQuestions::generateSampleBudget($answers, $budget_type);

                echo json_encode([
                    'success' => true,
                    'budget_data' => $budget_data,
                    'demo_mode' => true,
                    'message' => 'تم إنشاء ميزانية نموذجية بسبب خطأ في AI: ' . $e->getMessage()
                ]);
            }
            break;

        case 'save_generated_budget':
            // حفظ الميزانية المُنشأة
            $budget_data = $input['budget_data'] ?? null;
            $metadata = $input['metadata'] ?? [];

            if (!$budget_data) {
                throw new Exception('لا توجد بيانات ميزانية لحفظها');
            }

            $database = new Database();
            $db = $database->getConnection();
            $user = $auth->getUserInfo();

            $db->beginTransaction();

            // إنشاء الميزانية
            $budget_code = 'AI-BUD-' . time();
            $name = $metadata['name'] ?? 'ميزانية مُنشأة بالذكاء الاصطناعي';
            $fiscal_year = $metadata['fiscal_year'] ?? date('Y');
            $start_date = $metadata['start_date'] ?? date('Y') . '-01-01';
            $end_date = $metadata['end_date'] ?? date('Y') . '-12-31';
            $total_amount = $budget_data['budget_summary']['total_revenues'] ?? 0;
            $currency_id = $metadata['currency_id'] ?? 1;
            $committee_id = $metadata['committee_id'] ?? null;
            $description = 'ميزانية تم إنشاؤها تلقائياً بواسطة الذكاء الاصطناعي';

            $stmt = $db->prepare("
                INSERT INTO budgets (
                    budget_code, name, fiscal_year, start_date, end_date,
                    total_amount, currency_id, committee_id,
                    description, created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'مسودة')
            ");

            $stmt->execute([
                $budget_code, $name, $fiscal_year, $start_date, $end_date,
                $total_amount, $currency_id, $committee_id,
                $description, $user['id']
            ]);

            $budget_id = $db->lastInsertId();

            // إضافة بنود الإيرادات
            if (isset($budget_data['revenue_items'])) {
                foreach ($budget_data['revenue_items'] as $item) {
                    $stmt = $db->prepare("
                        INSERT INTO budget_items (
                            budget_id, item_code, name, description,
                            item_type, category, allocated_amount,
                            currency_id, remaining_amount, spent_amount
                        ) VALUES (?, ?, ?, ?, 'إيراد', 'إيرادات', ?, ?, ?, 0)
                    ");

                    $stmt->execute([
                        $budget_id,
                        $item['code'] ?? 'REV-' . rand(100, 999),
                        $item['name'],
                        $item['description'] ?? '',
                        $item['amount'],
                        $currency_id,
                        $item['amount']
                    ]);
                }
            }

            // إضافة بنود المصاريف
            if (isset($budget_data['expense_items'])) {
                foreach ($budget_data['expense_items'] as $item) {
                    $stmt = $db->prepare("
                        INSERT INTO budget_items (
                            budget_id, item_code, name, description,
                            item_type, category, allocated_amount,
                            currency_id, remaining_amount, spent_amount
                        ) VALUES (?, ?, ?, ?, 'مصروف', 'مصاريف', ?, ?, ?, 0)
                    ");

                    $stmt->execute([
                        $budget_id,
                        $item['code'] ?? 'EXP-' . rand(100, 999),
                        $item['name'],
                        $item['description'] ?? '',
                        $item['amount'],
                        $currency_id,
                        $item['amount']
                    ]);
                }
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'budget_id' => $budget_id,
                'message' => 'تم حفظ الميزانية بنجاح'
            ]);
            break;

        default:
            throw new Exception('عملية غير معروفة');
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
