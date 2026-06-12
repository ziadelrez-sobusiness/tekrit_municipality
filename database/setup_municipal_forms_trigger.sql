-- Trigger to automatically consolidate municipal forms submissions into citizen_requests
DELIMITER //

CREATE TRIGGER IF NOT EXISTS after_municipal_forms_insert
AFTER INSERT ON municipal_forms
FOR EACH ROW
BEGIN
    DECLARE tracking_num VARCHAR(50);
    DECLARE year_val CHAR(4);
    DECLARE next_num INT;

    SET year_val = YEAR(CURDATE());
    
    -- Find last sequence number for current year
    SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_number, 10) AS UNSIGNED)), 0) + 1 INTO next_num
    FROM citizen_requests
    WHERE tracking_number LIKE CONCAT('REQ-', year_val, '-%');
    
    -- Format: REQ-YYYY-XXXXX
    SET tracking_num = CONCAT('REQ-', year_val, '-', LPAD(next_num, 5, '0'));

    -- Insert request record linking back to the form
    INSERT INTO citizen_requests (
        citizen_name,
        citizen_phone,
        citizen_address,
        request_type_id,
        request_title,
        request_description,
        priority_level,
        tracking_number,
        status,
        created_at,
        updated_at
    ) VALUES (
        NEW.applicant_name,
        NEW.applicant_phone,
        NEW.applicant_address,
        1, -- Default/Building permit request type
        CONCAT('طلب نموذج: ', NEW.form_type),
        CONCAT('تم تقديم نموذج من نوع: ', NEW.form_type, '\nالبيانات المرفقة: ', COALESCE(NEW.application_data, '')),
        'عادي',
        tracking_num,
        'جديد',
        NOW(),
        NOW()
    );
END//

DELIMITER ;
