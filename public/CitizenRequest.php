<?php

class CitizenRequest {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * إنشاء طلب جديد
     */
    public function create($requestData, $formData = []) {
        try {
            $this->db->beginTransaction();
            
            // إنشاء رقم تتبع فريد
            $trackingNumber = $this->generateTrackingNumber();
            
            // إدراج الطلب الأساسي
            $sql = "INSERT INTO citizen_requests (
                        tracking_number, citizen_name, citizen_phone, citizen_email, 
                        citizen_address, national_id, request_type_id, priority_level,
                        request_status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'جديد', NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $trackingNumber,
                $requestData['citizen_name'],
                $requestData['citizen_phone'],
                $requestData['citizen_email'],
                $requestData['citizen_address'],
                $requestData['national_id'],
                $requestData['request_type_id'],
                $requestData['priority_level']
            ]);
            
            if (!$result) {
                throw new Exception("فشل في إنشاء الطلب");
            }
            
            $requestId = $this->db->lastInsertId();
            
            // حفظ بيانات النموذج الديناميكية
            if (!empty($formData)) {
                $this->saveFormData($requestId, $formData);
            }
            
            $this->db->commit();
            return $requestId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in CitizenRequest::create(): " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * حفظ بيانات النموذج الديناميكية
     */
    private function saveFormData($requestId, $formData) {
        try {
            $sql = "INSERT INTO request_form_data (request_id, field_name, field_value, created_at) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            
            foreach ($formData as $fieldName => $fieldValue) {
                if (!empty($fieldValue)) {
                    $stmt->execute([$requestId, $fieldName, $fieldValue]);
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::saveFormData(): " . $e->getMessage());
            throw new Exception("فشل في حفظ بيانات النموذج");
        }
    }
    
    /**
     * إضافة مستند للطلب
     */
    public function addDocument($requestId, $filename, $originalName, $documentType = 'مستند مرفق') {
        try {
            $sql = "INSERT INTO request_documents (request_id, document_type, file_name, original_name, uploaded_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([$requestId, $documentType, $filename, $originalName]);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::addDocument(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على طلب بواسطة المعرف
     */
    public function getById($id) {
        try {
            $sql = "SELECT cr.*, rt.type_name as request_type_name
                    FROM citizen_requests cr
                    LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                    WHERE cr.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::getById(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على طلب بواسطة رقم التتبع
     */
    public function getByTrackingNumber($trackingNumber) {
        try {
            $sql = "SELECT cr.*, rt.type_name as request_type_name
                    FROM citizen_requests cr
                    LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                    WHERE cr.tracking_number = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$trackingNumber]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::getByTrackingNumber(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على بيانات النموذج للطلب
     */
    public function getFormData($requestId) {
        try {
            $sql = "SELECT field_name, field_value FROM request_form_data WHERE request_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$requestId]);
            
            $formData = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $formData[$row['field_name']] = $row['field_value'];
            }
            
            return $formData;
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::getFormData(): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على مستندات الطلب
     */
    public function getDocuments($requestId) {
        try {
            $sql = "SELECT * FROM request_documents WHERE request_id = ? ORDER BY uploaded_at";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$requestId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::getDocuments(): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تحديث حالة الطلب
     */
    public function updateStatus($id, $status, $notes = null) {
        try {
            $sql = "UPDATE citizen_requests SET request_status = ?, updated_at = NOW()";
            $params = [$status];
            
            if ($notes) {
                $sql .= ", admin_notes = ?";
                $params[] = $notes;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::updateStatus(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إضافة تحديث للطلب
     */
    public function addUpdate($requestId, $updateText, $updatedBy = null) {
        try {
            $sql = "INSERT INTO request_updates (request_id, update_text, updated_by, created_at) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([$requestId, $updateText, $updatedBy]);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::addUpdate(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على تحديثات الطلب
     */
    public function getUpdates($requestId) {
        try {
            $sql = "SELECT * FROM request_updates WHERE request_id = ? ORDER BY created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$requestId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::getUpdates(): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * البحث في الطلبات
     */
    public function search($filters = []) {
        try {
            $sql = "SELECT cr.*, rt.name as request_type_name 
                    FROM citizen_requests cr
                    LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['tracking_number'])) {
                $sql .= " AND cr.tracking_number LIKE ?";
                $params[] = '%' . $filters['tracking_number'] . '%';
            }
            
            if (!empty($filters['citizen_name'])) {
                $sql .= " AND cr.citizen_name LIKE ?";
                $params[] = '%' . $filters['citizen_name'] . '%';
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND cr.request_status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['type_id'])) {
                $sql .= " AND cr.request_type_id = ?";
                $params[] = $filters['type_id'];
            }
            
            if (!empty($filters['priority'])) {
                $sql .= " AND cr.priority_level = ?";
                $params[] = $filters['priority'];
            }
            
            $sql .= " ORDER BY cr.created_at DESC";
            
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT " . (int)$filters['limit'];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::search(): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على إحصائيات الطلبات
     */
    public function getStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_requests,
                        SUM(CASE WHEN request_status = 'جديد' THEN 1 ELSE 0 END) as new_requests,
                        SUM(CASE WHEN request_status = 'قيد المراجعة' THEN 1 ELSE 0 END) as under_review,
                        SUM(CASE WHEN request_status = 'قيد التنفيذ' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN request_status = 'مكتمل' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN priority_level = 'عاجل' THEN 1 ELSE 0 END) as urgent_requests
                    FROM citizen_requests";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in CitizenRequest::getStats(): " . $e->getMessage());
            return [
                'total_requests' => 0,
                'new_requests' => 0,
                'under_review' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'urgent_requests' => 0
            ];
        }
    }
    
    /**
     * توليد رقم تتبع فريد
     */
    private function generateTrackingNumber() {
        $prefix = 'REQ' . date('Y');
        $maxAttempts = 10;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $number = $prefix . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // التحقق من عدم تكرار الرقم
            $sql = "SELECT COUNT(*) as count FROM citizen_requests WHERE tracking_number = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$number]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                return $number;
            }
        }
        
        // في حالة فشل توليد رقم فريد، استخدم timestamp
        return $prefix . '-' . time();
    }
}
?>

