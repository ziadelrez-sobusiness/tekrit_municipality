<?php
/**
 * API Endpoint - إنشاء محتوى بواسطة الذكاء الاصطناعي
 * للمشاريع، الأخبار، والردود
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_service.php';

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

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات غير صحيحة']);
    exit;
}

$action = $input['action'] ?? '';

try {
    // التحقق من تفعيل AI
    if (!isAIEnabled()) {
        throw new Exception('الذكاء الاصطناعي غير مفعل في النظام');
    }

    $ai_service = new AIService();

    switch ($action) {
        case 'generate_project_description':
            // إنشاء وصف مشروع
            $title = $input['title'] ?? '';
            $keywords = $input['keywords'] ?? '';
            $budget = $input['budget'] ?? '';

            if (empty($title)) {
                throw new Exception('يرجى إدخال عنوان المشروع');
            }

            $prompt = "أنشئ وصفاً شاملاً لمشروع بلدي بعنوان: \"$title\"";
            if ($keywords) {
                $prompt .= "\nالكلمات المفتاحية: $keywords";
            }
            if ($budget) {
                $prompt .= "\nالميزانية المقدرة: $budget";
            }

            $system_message = "أنت خبير في إدارة وتخطيط المشاريع البلدية في لبنان. ";
            $system_message .= "أنشئ وصفاً تفصيلياً للمشروع يتضمن الأهداف، الفوائد، المراحل الرئيسية، والأثر المتوقع على المجتمع.";

            $result = $ai_service->sendTextRequest($prompt, $system_message);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            echo json_encode([
                'success' => true,
                'content' => $result['content']
            ]);
            break;

        case 'generate_news_article':
            // إنشاء مقال خبري
            $title = $input['title'] ?? '';
            $summary = $input['summary'] ?? '';
            $tone = $input['tone'] ?? 'formal';

            if (empty($title)) {
                throw new Exception('يرجى إدخال عنوان الخبر');
            }

            $prompt = "اكتب مقالاً خبرياً بعنوان: \"$title\"";
            if ($summary) {
                $prompt .= "\nالملخص: $summary";
            }

            $system_message = "أنت محرر أخبار محترف. اكتب مقالاً صحفياً ";
            $system_message .= $tone === 'formal' ? 'رسمياً ومهنياً' : 'ودوداً وسهل القراءة';
            $system_message .= ". استخدم لغة عربية فصحى واضحة. قسّم المقال إلى فقرات منطقية.";

            $result = $ai_service->sendTextRequest($prompt, $system_message);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            echo json_encode([
                'success' => true,
                'content' => $result['content']
            ]);
            break;

        case 'generate_news_image':
            // إنشاء صورة للخبر
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';

            if (empty($title)) {
                throw new Exception('يرجى إدخال عنوان الخبر');
            }

            $prompt = "Create a professional news article image for: $title";
            if ($description) {
                $prompt .= ". Description: $description";
            }
            $prompt .= ". Style: professional, news-worthy, Lebanese municipal context, high quality";

            $result = $ai_service->generateImage($prompt, '1024x1024', 'standard');

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            echo json_encode([
                'success' => true,
                'image_url' => $result['image_url'],
                'revised_prompt' => $result['revised_prompt'] ?? null
            ]);
            break;

        case 'generate_response':
            // إنشاء رد على طلب أو شكوى
            $request_type = $input['request_type'] ?? 'general';
            $request_content = $input['request_content'] ?? '';
            $context = $input['context'] ?? '';

            if (empty($request_content)) {
                throw new Exception('يرجى إدخال محتوى الطلب أو الشكوى');
            }

            $prompt = "اكتب رداً رسمياً ";
            $prompt .= $request_type === 'complaint' ? 'على شكوى' : 'على طلب';
            $prompt .= " التالي:\n\n$request_content";

            if ($context) {
                $prompt .= "\n\nمعلومات إضافية: $context";
            }

            $system_message = "أنت موظف خدمة مواطنين في بلدية لبنانية. ";
            $system_message .= "اكتب رداً مهذباً ومهنياً يتضمن: ";
            $system_message .= "1) الاعتراف بالطلب/الشكوى ";
            $system_message .= "2) توضيح الإجراءات المتخذة أو المطلوبة ";
            $system_message .= "3) الالتزام بالمتابعة ";
            $system_message .= "4) شكر المواطن على تواصله. ";
            $system_message .= "استخدم لغة عربية رسمية ومحترمة.";

            $result = $ai_service->sendTextRequest($prompt, $system_message);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            echo json_encode([
                'success' => true,
                'content' => $result['content']
            ]);
            break;

        case 'improve_text':
            // تحسين نص موجود
            $text = $input['text'] ?? '';
            $improvement_type = $input['improvement_type'] ?? 'grammar';

            if (empty($text)) {
                throw new Exception('يرجى إدخال النص المراد تحسينه');
            }

            $improvements = [
                'grammar' => 'قم بتصحيح الأخطاء النحوية والإملائية',
                'professional' => 'أعد صياغة النص ليكون أكثر مهنية ورسمية',
                'concise' => 'اختصر النص مع الحفاظ على المعنى الأساسي',
                'detailed' => 'وسّع النص وأضف المزيد من التفاصيل والتوضيحات'
            ];

            $prompt = ($improvements[$improvement_type] ?? $improvements['grammar']) . ":\n\n$text";

            $system_message = "أنت محرر لغوي محترف. قم بتحسين النص مع الحفاظ على معناه الأصلي.";

            $result = $ai_service->sendTextRequest($prompt, $system_message);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            echo json_encode([
                'success' => true,
                'content' => $result['content']
            ]);
            break;

        case 'expand_text':
            // توسيع نص مختصر
            $text = $input['text'] ?? '';
            $context = $input['context'] ?? '';

            if (empty($text)) {
                throw new Exception('يرجى إدخال النص المراد توسيعه');
            }

            $prompt = "وسّع النص التالي بشكل احترافي:\n\n$text";
            if ($context) {
                $prompt .= "\n\nالسياق: $context";
            }

            $system_message = "أنت كاتب محتوى محترف. وسّع النص بإضافة تفاصيل مفيدة ومعلومات ذات صلة مع الحفاظ على التناسق والاحترافية.";

            $result = $ai_service->sendTextRequest($prompt, $system_message);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            echo json_encode([
                'success' => true,
                'content' => $result['content']
            ]);
            break;

        default:
            throw new Exception('عملية غير معروفة');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
