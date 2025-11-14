-- النظام المتقدم لطلبات المواطنين - دعم السيناريوهات المطلوبة
-- تطوير شامل للنماذج الديناميكية ونظام المراجعة والموافقات

-- إضافة النماذج الديناميكية المتقدمة لأنواع الطلبات
DELETE FROM `request_types` WHERE type_name IN ('طلب ترخيص بالبناء', 'طلب ترخيص تجاري', 'طلب ترخيص صناعي');

INSERT INTO `request_types` (`type_name`, `name_ar`, `type_description`, `form_fields`, `required_documents`, `is_active`, `display_order`) VALUES

-- طلب ترخيص البناء مع النموذج الديناميكي الكامل
('طلب ترخيص بالبناء', 'طلب ترخيص بالبناء', 'طلب الحصول على ترخيص بناء من البلدية مع جميع المتطلبات الهندسية والقانونية', 
'{"owner_info":{"label":"معلومات مالك الأرض","type":"section"},"land_owner_name":{"label":"اسم مالك الأرض الكامل","type":"text","required":true,"validation":"required|string|min:3|max:100"},"land_owner_id":{"label":"رقم الهوية الشخصية لمالك الأرض","type":"text","required":true,"validation":"required|digits:12"},"property_deed_number":{"label":"رقم سند الملكية","type":"text","required":true,"validation":"required|string"},"property_info":{"label":"معلومات العقار","type":"section"},"property_address":{"label":"عنوان العقار بالتفصيل","type":"textarea","required":true,"rows":3,"validation":"required|string|min:10"},"property_area":{"label":"مساحة الأرض (متر مربع)","type":"number","required":true,"min":50,"validation":"required|numeric|min:50"},"property_zone":{"label":"المنطقة التنظيمية","type":"select","required":true,"options":["سكنية","تجارية","صناعية","مختلطة","زراعية"],"validation":"required"},"building_info":{"label":"معلومات البناء المراد إنشاؤه","type":"section"},"building_type":{"label":"نوع البناء","type":"select","required":true,"options":["فيلا","شقق سكنية","مبنى تجاري","مصنع","مستودع","مبنى مختلط"],"validation":"required"},"building_floors":{"label":"عدد الطوابق","type":"number","required":true,"min":1,"max":15,"validation":"required|numeric|min:1|max:15"},"building_area":{"label":"مساحة البناء الإجمالية (متر مربع)","type":"number","required":true,"min":20,"validation":"required|numeric|min:20"},"building_height":{"label":"ارتفاع البناء (متر)","type":"number","required":true,"min":2.5,"validation":"required|numeric|min:2.5"},"construction_purpose":{"label":"الغرض من البناء","type":"select","required":true,"options":["سكن شخصي","سكن للإيجار","نشاط تجاري","نشاط صناعي","مكاتب","أخرى"],"validation":"required"},"contractor_info":{"label":"معلومات المقاول والمهندس","type":"section"},"contractor_name":{"label":"اسم المقاول","type":"text","required":false,"validation":"string|max:100"},"contractor_license":{"label":"رقم رخصة المقاول","type":"text","required":false,"validation":"string"},"engineer_name":{"label":"اسم المهندس المشرف","type":"text","required":true,"validation":"required|string|max:100"},"engineer_license":{"label":"رقم رخصة المهندس","type":"text","required":true,"validation":"required|string"},"financial_info":{"label":"المعلومات المالية","type":"section"},"estimated_cost":{"label":"التكلفة التقديرية للمشروع (ليرة لبنانية)","type":"number","required":true,"min":10000000,"validation":"required|numeric|min:10000000"},"construction_period":{"label":"المدة المتوقعة للإنجاز (بالأشهر)","type":"number","required":true,"min":3,"max":60,"validation":"required|numeric|min:3|max:60"},"start_date":{"label":"تاريخ بدء العمل المتوقع","type":"date","required":true,"validation":"required|date"}}',
'["سند الملكية (أصل + صورة)","خريطة مساحية للأرض","المخططات الهندسية المعتمدة","تقرير دراسة التربة","موافقة الدفاع المدني","هوية شخصية لمالك الأرض","رخصة المهندس المشرف","رخصة المقاول (إن وجد)","إيصال دفع الرسوم"]', 1, 1),

-- طلب ترخيص تجاري
('طلب ترخيص تجاري', 'طلب ترخيص تجاري', 'طلب الحصول على ترخيص لممارسة نشاط تجاري',
'{"business_info":{"label":"معلومات النشاط التجاري","type":"section"},"business_name":{"label":"اسم المحل أو النشاط التجاري","type":"text","required":true,"validation":"required|string|min:3|max:100"},"business_type":{"label":"نوع النشاط التجاري","type":"select","required":true,"options":["بقالة","مطعم","صيدلية","ورشة","مكتب","صالون","محل ملابس","أخرى"],"validation":"required"},"business_address":{"label":"عنوان المحل","type":"textarea","required":true,"rows":2,"validation":"required|string|min:10"},"shop_area":{"label":"مساحة المحل (متر مربع)","type":"number","required":true,"min":10,"validation":"required|numeric|min:10"},"employees_count":{"label":"عدد الموظفين المتوقع","type":"number","required":true,"min":1,"validation":"required|numeric|min:1"}}',
'["هوية شخصية لصاحب النشاط","عقد إيجار أو سند ملكية للمحل","شهادة عدم محكومية","شهادة صحية (للأنشطة الغذائية)","موافقة الدفاع المدني","إيصال دفع الرسوم"]', 1, 2);

-- إنشاء جدول مراحل معالجة الطلبات
CREATE TABLE IF NOT EXISTS `request_workflow_stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_type_id` int(11) NOT NULL,
  `stage_name` varchar(100) NOT NULL COMMENT 'اسم المرحلة',
  `stage_description` text NULL COMMENT 'وصف المرحلة',
  `stage_order` int(11) NOT NULL DEFAULT 1 COMMENT 'ترتيب المرحلة',
  `required_role` varchar(50) NULL COMMENT 'الدور المطلوب للمرحلة',
  `max_duration_days` int(11) NULL COMMENT 'المدة القصوى للمرحلة بالأيام',
  `is_optional` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مرحلة اختيارية',
  `auto_approve` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'موافقة تلقائية',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_request_type_id` (`request_type_id`),
  INDEX `idx_stage_order` (`stage_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج مراحل معالجة طلب ترخيص البناء
INSERT INTO `request_workflow_stages` (`request_type_id`, `stage_name`, `stage_description`, `stage_order`, `required_role`, `max_duration_days`) VALUES
((SELECT id FROM request_types WHERE type_name = 'طلب ترخيص بالبناء'), 'استلام الطلب', 'استلام وفحص اكتمال الوثائق المطلوبة', 1, 'موظف_استقبال', 2),
((SELECT id FROM request_types WHERE type_name = 'طلب ترخيص بالبناء'), 'المراجعة الأولية', 'مراجعة البيانات والوثائق المقدمة', 2, 'موظف_مراجعة', 3),
((SELECT id FROM request_types WHERE type_name = 'طلب ترخيص بالبناء'), 'المراجعة الهندسية', 'مراجعة المخططات والتصاميم الهندسية', 3, 'مهندس', 7),
((SELECT id FROM request_types WHERE type_name = 'طلب ترخيص بالبناء'), 'الكشف الميداني', 'زيارة الموقع والكشف الميداني', 4, 'مهندس', 5),
((SELECT id FROM request_types WHERE type_name = 'طلب ترخيص بالبناء'), 'موافقة المدير', 'موافقة مدير البلدية على الترخيص', 5, 'مدير', 3),
((SELECT id FROM request_types WHERE type_name = 'طلب ترخيص بالبناء'), 'إصدار الترخيص', 'إصدار الترخيص النهائي وتسليمه', 6, 'موظف_إصدار', 2);

-- إنشاء جدول تتبع مراحل الطلبات
CREATE TABLE IF NOT EXISTS `request_stage_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed','rejected','on_hold') NOT NULL DEFAULT 'pending',
  `assigned_to` int(11) NULL COMMENT 'معرف الموظف المكلف',
  `started_at` timestamp NULL,
  `completed_at` timestamp NULL,
  `notes` text NULL COMMENT 'ملاحظات المرحلة',
  `rejection_reason` text NULL COMMENT 'سبب الرفض إن وجد',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_request_id` (`request_id`),
  INDEX `idx_stage_id` (`stage_id`),
  INDEX `idx_assigned_to` (`assigned_to`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول الإشعارات
CREATE TABLE IF NOT EXISTS `request_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NULL COMMENT 'معرف المستخدم المرسل إليه',
  `citizen_phone` varchar(20) NULL COMMENT 'رقم هاتف المواطن',
  `citizen_email` varchar(100) NULL COMMENT 'بريد المواطن الإلكتروني',
  `notification_type` enum('sms','email','system','whatsapp') NOT NULL DEFAULT 'system',
  `title` varchar(255) NOT NULL COMMENT 'عنوان الإشعار',
  `message` text NOT NULL COMMENT 'نص الإشعار',
  `is_sent` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'تم الإرسال',
  `sent_at` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_request_id` (`request_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_notification_type` (`notification_type`),
  INDEX `idx_is_sent` (`is_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول قوالب الإشعارات
CREATE TABLE IF NOT EXISTS `notification_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `template_type` enum('request_received','status_change','stage_change','document_required','completed','rejected') NOT NULL,
  `title_template` varchar(255) NOT NULL,
  `message_template` text NOT NULL,
  `variables` json NULL COMMENT 'المتغيرات المتاحة في القالب',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_template_type` (`template_type`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج قوالب الإشعارات
INSERT INTO `notification_templates` (`template_name`, `template_type`, `title_template`, `message_template`, `variables`) VALUES
('استلام طلب جديد', 'request_received', 'تم استلام طلبك بنجاح', 'عزيزي/تي {{citizen_name}}، تم استلام طلبك "{{request_title}}" بنجاح. رقم التتبع: {{tracking_number}}. يمكنك متابعة حالة طلبك عبر الموقع الإلكتروني.', '{"citizen_name":"اسم المواطن","request_title":"عنوان الطلب","tracking_number":"رقم التتبع"}'),
('تغيير حالة الطلب', 'status_change', 'تحديث حالة طلبك', 'عزيزي/تي {{citizen_name}}، تم تحديث حالة طلبك رقم {{tracking_number}} إلى: {{new_status}}. {{additional_notes}}', '{"citizen_name":"اسم المواطن","tracking_number":"رقم التتبع","new_status":"الحالة الجديدة","additional_notes":"ملاحظات إضافية"}'),
('انتقال لمرحلة جديدة', 'stage_change', 'طلبك في مرحلة جديدة', 'عزيزي/تي {{citizen_name}}، طلبك رقم {{tracking_number}} الآن في مرحلة: {{stage_name}}. {{stage_description}}', '{"citizen_name":"اسم المواطن","tracking_number":"رقم التتبع","stage_name":"اسم المرحلة","stage_description":"وصف المرحلة"}'),
('اكتمال الطلب', 'completed', 'تم إنجاز طلبك', 'عزيزي/تي {{citizen_name}}، تم إنجاز طلبك رقم {{tracking_number}} بنجاح. يمكنك مراجعة البلدية لاستلام المستندات المطلوبة.', '{"citizen_name":"اسم المواطن","tracking_number":"رقم التتبع"}'),
('رفض الطلب', 'rejected', 'تم رفض طلبك', 'عزيزي/تي {{citizen_name}}، نأسف لإبلاغك بأنه تم رفض طلبك رقم {{tracking_number}}. السبب: {{rejection_reason}}. يمكنك تقديم طلب جديد بعد معالجة الأسباب المذكورة.', '{"citizen_name":"اسم المواطن","tracking_number":"رقم التتبع","rejection_reason":"سبب الرفض"}');

-- إنشاء view شامل لتتبع الطلبات
CREATE OR REPLACE VIEW `citizen_requests_full_tracking` AS
SELECT 
    cr.id,
    cr.tracking_number,
    cr.citizen_name,
    cr.citizen_phone,
    cr.citizen_email,
    cr.request_title,
    cr.request_description,
    cr.status,
    cr.priority_level,
    cr.created_at,
    cr.updated_at,
    rt.name_ar as request_type_name,
    rt.type_description,
    DATEDIFF(NOW(), cr.created_at) as days_since_created
FROM citizen_requests cr
LEFT JOIN request_types rt ON cr.request_type_id = rt.id;

-- تحديث الإعدادات
INSERT IGNORE INTO `website_settings` (`setting_key`, `setting_value`, `setting_description`) VALUES
('workflow_enabled', '1', 'تفعيل نظام مراحل العمل'),
('notifications_enabled', '1', 'تفعيل نظام الإشعارات'),
('auto_stage_progression', '0', 'التقدم التلقائي بين المراحل'),
('citizen_can_comment', '1', 'السماح للمواطنين بإضافة تعليقات'),
('max_stage_duration_warning', '2', 'تحذير قبل انتهاء مدة المرحلة (بالأيام)');

-- إنشاء فهارس محسنة للأداء
CREATE INDEX IF NOT EXISTS `idx_request_type_form_fields` ON `request_types` (`type_name`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_workflow_stages_type_order` ON `request_workflow_stages` (`request_type_id`, `stage_order`);
CREATE INDEX IF NOT EXISTS `idx_stage_tracking_request_status` ON `request_stage_tracking` (`request_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_notifications_request_sent` ON `request_notifications` (`request_id`, `is_sent`);
CREATE INDEX IF NOT EXISTS `idx_citizen_requests_type_status` ON `citizen_requests` (`request_type_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_citizen_requests_created_status` ON `citizen_requests` (`created_at`, `status`);

-- إنشاء stored procedures متقدمة

DELIMITER //

-- إنشاء مراحل العمل تلقائياً عند إنشاء طلب جديد
CREATE PROCEDURE IF NOT EXISTS CreateRequestWorkflow(
    IN p_request_id INT,
    IN p_request_type_id INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_stage_id INT;
    DECLARE stage_cursor CURSOR FOR 
        SELECT id FROM request_workflow_stages 
        WHERE request_type_id = p_request_type_id 
        ORDER BY stage_order;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN stage_cursor;
    read_loop: LOOP
        FETCH stage_cursor INTO v_stage_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO request_stage_tracking (request_id, stage_id, status, created_at)
        VALUES (p_request_id, v_stage_id, 'pending', NOW());
        
    END LOOP;
    CLOSE stage_cursor;
    
    -- تفعيل المرحلة الأولى
    UPDATE request_stage_tracking 
    SET status = 'in_progress', started_at = NOW()
    WHERE request_id = p_request_id 
    AND stage_id = (
        SELECT rst.stage_id FROM request_stage_tracking rst
        JOIN request_workflow_stages rws ON rst.stage_id = rws.id
        WHERE rst.request_id = p_request_id
        ORDER BY rws.stage_order ASC
        LIMIT 1
    );
END //

-- تحديث مرحلة الطلب
CREATE PROCEDURE IF NOT EXISTS UpdateRequestStage(
    IN p_request_id INT,
    IN p_stage_id INT,
    IN p_new_status VARCHAR(20),
    IN p_user_id INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_next_stage_id INT DEFAULT NULL;
    DECLARE v_stage_order INT;
    DECLARE v_request_type_id INT;
    
    -- تحديث المرحلة الحالية
    UPDATE request_stage_tracking 
    SET 
        status = p_new_status,
        completed_at = CASE WHEN p_new_status = 'completed' THEN NOW() ELSE completed_at END,
        notes = p_notes,
        updated_at = NOW()
    WHERE request_id = p_request_id AND stage_id = p_stage_id;
    
    -- إضافة تحديث للطلب
    INSERT INTO request_updates (request_id, updated_by, update_type, update_text, created_at)
    VALUES (p_request_id, p_user_id, 'stage_change', 
           CONCAT('تم تحديث المرحلة إلى: ', p_new_status, 
                  CASE WHEN p_notes IS NOT NULL THEN CONCAT('. ملاحظات: ', p_notes) ELSE '' END), 
           NOW());
    
    -- إذا تم إكمال المرحلة، انتقل للمرحلة التالية
    IF p_new_status = 'completed' THEN
        -- الحصول على معلومات المرحلة الحالية
        SELECT rws.stage_order, rws.request_type_id 
        INTO v_stage_order, v_request_type_id
        FROM request_workflow_stages rws 
        WHERE rws.id = p_stage_id;
        
        -- البحث عن المرحلة التالية
        SELECT id INTO v_next_stage_id
        FROM request_workflow_stages 
        WHERE request_type_id = v_request_type_id 
        AND stage_order > v_stage_order
        ORDER BY stage_order ASC
        LIMIT 1;
        
        IF v_next_stage_id IS NOT NULL THEN
            -- تفعيل المرحلة التالية
            UPDATE request_stage_tracking 
            SET status = 'in_progress', started_at = NOW()
            WHERE request_id = p_request_id AND stage_id = v_next_stage_id;
            
            -- تحديث حالة الطلب الرئيسية
            UPDATE citizen_requests 
            SET status = 'قيد المراجعة', updated_at = NOW()
            WHERE id = p_request_id;
        ELSE
            -- لا توجد مراحل أخرى، إكمال الطلب
            UPDATE citizen_requests 
            SET status = 'مكتمل', actual_completion_date = NOW(), updated_at = NOW()
            WHERE id = p_request_id;
        END IF;
    END IF;
    
    -- إذا تم رفض المرحلة، رفض الطلب كاملاً
    IF p_new_status = 'rejected' THEN
        UPDATE citizen_requests 
        SET status = 'مرفوض', updated_at = NOW()
        WHERE id = p_request_id;
    END IF;
END //

-- إرسال إشعار
CREATE PROCEDURE IF NOT EXISTS SendNotification(
    IN p_request_id INT,
    IN p_notification_type VARCHAR(20),
    IN p_template_type VARCHAR(50)
)
BEGIN
    DECLARE v_citizen_name VARCHAR(255);
    DECLARE v_citizen_phone VARCHAR(20);
    DECLARE v_citizen_email VARCHAR(100);
    DECLARE v_tracking_number VARCHAR(50);
    DECLARE v_request_title VARCHAR(255);
    DECLARE v_title_template VARCHAR(255);
    DECLARE v_message_template TEXT;
    DECLARE v_final_title VARCHAR(255);
    DECLARE v_final_message TEXT;
    
    -- جلب بيانات الطلب
    SELECT citizen_name, citizen_phone, citizen_email, tracking_number, request_title
    INTO v_citizen_name, v_citizen_phone, v_citizen_email, v_tracking_number, v_request_title
    FROM citizen_requests 
    WHERE id = p_request_id;
    
    -- جلب قالب الإشعار
    SELECT title_template, message_template
    INTO v_title_template, v_message_template
    FROM notification_templates
    WHERE template_type = p_template_type AND is_active = 1
    LIMIT 1;
    
    -- استبدال المتغيرات في القالب
    SET v_final_title = REPLACE(v_title_template, '{{citizen_name}}', v_citizen_name);
    SET v_final_title = REPLACE(v_final_title, '{{tracking_number}}', v_tracking_number);
    
    SET v_final_message = REPLACE(v_message_template, '{{citizen_name}}', v_citizen_name);
    SET v_final_message = REPLACE(v_final_message, '{{tracking_number}}', v_tracking_number);
    SET v_final_message = REPLACE(v_final_message, '{{request_title}}', v_request_title);
    
    -- إدراج الإشعار
    INSERT INTO request_notifications (
        request_id, citizen_phone, citizen_email, notification_type, 
        title, message, created_at
    ) VALUES (
        p_request_id, v_citizen_phone, v_citizen_email, p_notification_type,
        v_final_title, v_final_message, NOW()
    );
END //

DELIMITER ;

-- إنشاء triggers لأتمتة العمليات
DELIMITER //

-- trigger عند إنشاء طلب جديد
CREATE TRIGGER IF NOT EXISTS after_citizen_request_insert
AFTER INSERT ON citizen_requests
FOR EACH ROW
BEGIN
    -- إنشاء مراحل العمل
    IF NEW.request_type_id IS NOT NULL THEN
        CALL CreateRequestWorkflow(NEW.id, NEW.request_type_id);
    END IF;
    
    -- إرسال إشعار استلام الطلب
    CALL SendNotification(NEW.id, 'system', 'request_received');
END //

-- trigger عند تحديث حالة الطلب
CREATE TRIGGER IF NOT EXISTS after_citizen_request_status_update
AFTER UPDATE ON citizen_requests
FOR EACH ROW
BEGIN
    -- إرسال إشعار تغيير الحالة
    IF OLD.status != NEW.status THEN
        CALL SendNotification(NEW.id, 'system', 'status_change');
    END IF;
END //

DELIMITER ;

-- إنشاء فهارس محسنة للأداء
CREATE INDEX IF NOT EXISTS `idx_request_type_form_fields` ON `request_types` (`type_name`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_workflow_stages_type_order` ON `request_workflow_stages` (`request_type_id`, `stage_order`);
CREATE INDEX IF NOT EXISTS `idx_stage_tracking_request_status` ON `request_stage_tracking` (`request_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_notifications_request_sent` ON `request_notifications` (`request_id`, `is_sent`);
CREATE INDEX IF NOT EXISTS `idx_citizen_requests_type_status` ON `citizen_requests` (`request_type_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_citizen_requests_created_status` ON `citizen_requests` (`created_at`, `status`);

-- تحديث الإعدادات
INSERT IGNORE INTO `website_settings` (`setting_key`, `setting_value`, `setting_description`) VALUES
('workflow_enabled', '1', 'تفعيل نظام مراحل العمل'),
('notifications_enabled', '1', 'تفعيل نظام الإشعارات'),
('auto_stage_progression', '0', 'التقدم التلقائي بين المراحل'),
('citizen_can_comment', '1', 'السماح للمواطنين بإضافة تعليقات'),
('max_stage_duration_warning', '2', 'تحذير قبل انتهاء مدة المرحلة (بالأيام)');
</rewritten_file> 