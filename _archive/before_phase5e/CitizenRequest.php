<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RequestType.php';
require_once __DIR__ . '/../config/config.php';

class CitizenRequest {
    private $db;
    private $requestType;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->requestType = new RequestType();
    }
    
    /**
     * إنشاء طلب جديد
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // إنشاء رقم تتبع فريد
            $trackingNumber = $this->generateTrackingNumber();
            
            // تحديد اسم نوع الطلب
            $requestTypeName = $data['request_type'] ?? '';
            if (isset($data['request_type_id'])) {
                $requestTypeInfo = $this->requestType->getById($data['request_type_id']);
                if ($requestTypeInfo) {
                    $requestTypeName = $requestTypeInfo['type_name'] ?? $requestTypeInfo['name_ar'];
                }
            }

            // إدراج الطلب الأساسي
            $requestData = [
                'tracking_number' => $trackingNumber,
                'citizen_id' => $data['citizen_id'] ?? null,
                'citizen_name' => $data['citizen_name'],
                'citizen_phone' => $data['citizen_phone'],
                'citizen_email' => $data['citizen_email'] ?? null,
                'citizen_address' => $data['citizen_address'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'request_type_id' => $data['request_type_id'] ?? null,
                'request_type' => $requestTypeName,
                'project_id' => $data['project_id'] ?? null,
                'request_title' => $data['request_title'],
                'request_description' => $data['request_description'],
                'priority_level' => $data['priority_level'] ?? 'عادي',
                'status' => 'جديد'
            ];
            
            $requestId = $this->db->insert('citizen_requests', $requestData);
            
            // إدراج بيانات النموذج إذا كانت موجودة
            if (!empty($data['form_data'])) {
                foreach ($data['form_data'] as $fieldName => $fieldValue) {
                    $formData = [
                        'request_id' => $requestId,
                        'field_name' => $fieldName,
                        'field_value' => is_array($fieldValue) ? json_encode($fieldValue, JSON_UNESCAPED_UNICODE) : $fieldValue,
                        'field_type' => gettype($fieldValue)
                    ];
                    $this->db->insert('request_form_data', $formData);
                }
            }
            
            // إدراج المستندات إذا كانت موجودة
            if (!empty($data['documents'])) {
                foreach ($data['documents'] as $document) {
                    $docData = [
                        'request_id' => $requestId,
                        'document_name' => $document['document_name'] ?? 'مستند مرفق',
                        'original_filename' => $document['original_filename'],
                        'file_path' => $document['file_path'],
                        'file_size' => $document['file_size'] ?? 0,
                        'file_type' => $document['file_type'] ?? null
                    ];
                    $this->db->insert('request_documents', $docData);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'request_id' => $requestId,
                'tracking_number' => $trackingNumber
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * الحصول على طلب بواسطة رقم التتبع
     */
    public function getByTrackingNumber($trackingNumber) {
        $sql = "
            SELECT cr.*, rt.type_name as request_type_name_ar, rt.type_description as request_type_description,
                   dp.project_name, mc.committee_name as assigned_committee_name
            FROM citizen_requests cr
            LEFT JOIN request_types rt ON cr.request_type_id = rt.id
            LEFT JOIN development_projects dp ON cr.project_id = dp.id
            LEFT JOIN municipal_committees mc ON cr.assigned_to_committee_id = mc.id
            WHERE cr.tracking_number = :tracking_number
        ";
        
        $request = $this->db->fetch($sql, ['tracking_number' => $trackingNumber]);
        
        if ($request) {
            // الحصول على بيانات النموذج
            $request['form_data'] = $this->getFormData($request['id']);
            
            // الحصول على المستندات
            $request['documents'] = $this->getDocuments($request['id']);
            
            // الحصول على التحديثات
            $request['updates'] = $this->getUpdates($request['id']);
        }
        
        return $request;
    }
    
    /**
     * الحصول على طلب بواسطة المعرف
     */
    public function getById($id) {
        $sql = "
            SELECT cr.*, rt.type_name as request_type_name_ar, rt.type_description as request_type_description,
                   dp.project_name, d.department_name, mc.committee_name as assigned_committee_name, u.full_name as assigned_user_name
            FROM citizen_requests cr
            LEFT JOIN request_types rt ON cr.request_type_id = rt.id
            LEFT JOIN development_projects dp ON cr.project_id = dp.id
            LEFT JOIN departments d ON cr.assigned_to_department_id = d.id
            LEFT JOIN municipal_committees mc ON cr.assigned_to_committee_id = mc.id
            LEFT JOIN users u ON cr.assigned_to_user_id = u.id
            WHERE cr.id = :id
        ";
        
        $request = $this->db->fetch($sql, ['id' => $id]);
        
        if ($request) {
            // الحصول على بيانات النموذج
            $request['form_data'] = $this->getFormData($request['id']);
            
            // الحصول على المستندات
            $request['documents'] = $this->getDocuments($request['id']);
            
            // الحصول على التحديثات
            $request['updates'] = $this->getUpdates($request['id']);
        }
        
        return $request;
    }
    
    /**
     * الحصول على جميع الطلبات مع إمكانية التصفية
     */
    public function getAll($filters = []) {
        $sql = "
            SELECT cr.*, rt.type_name as request_type_name_ar, dp.project_name,
                   d.department_name, mc.committee_name as assigned_committee_name, u.full_name as assigned_user_name
            FROM citizen_requests cr
            LEFT JOIN request_types rt ON cr.request_type_id = rt.id
            LEFT JOIN development_projects dp ON cr.project_id = dp.id
            LEFT JOIN departments d ON cr.assigned_to_department_id = d.id
            LEFT JOIN municipal_committees mc ON cr.assigned_to_committee_id = mc.id
            LEFT JOIN users u ON cr.assigned_to_user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND cr.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['request_type'])) {
            $sql .= " AND cr.request_type = :request_type";
            $params['request_type'] = $filters['request_type'];
        }
        
        if (!empty($filters['request_type_id'])) {
            $sql .= " AND cr.request_type_id = :request_type_id";
            $params['request_type_id'] = $filters['request_type_id'];
        }
        
        if (!empty($filters['citizen_name'])) {
            $sql .= " AND cr.citizen_name LIKE :citizen_name";
            $params['citizen_name'] = '%' . $filters['citizen_name'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(cr.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(cr.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['priority_level'])) {
            $sql .= " AND cr.priority_level = :priority_level";
            $params['priority_level'] = $filters['priority_level'];
        }

        if (!empty($filters['assigned_to_user_id'])) {
            $sql .= " AND cr.assigned_to_user_id = :assigned_to_user_id";
            $params['assigned_to_user_id'] = $filters['assigned_to_user_id'];
        }

        $sql .= " ORDER BY cr.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . intval($filters['offset']);
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * تحديث حالة الطلب
     */
    public function updateStatus($requestId, $newStatus, $userId = null, $comment = null) {
        try {
            $this->db->beginTransaction();
            
            // الحصول على الحالة الحالية
            $currentRequest = $this->getById($requestId);
            if (!$currentRequest) {
                throw new Exception('الطلب غير موجود');
            }
            
            $oldStatus = $currentRequest['status'];
            
            // تحديث حالة الطلب
            $updateData = ['status' => $newStatus];
            
            if ($newStatus === 'مكتمل') {
                $updateData['actual_completion_date'] = date('Y-m-d H:i:s');
            }
            
            $this->db->update('citizen_requests', $updateData, 'id = :id', ['id' => $requestId]);
            
            // إضافة تحديث في سجل التحديثات
            $updateRecord = [
                'request_id' => $requestId,
                'updated_by' => $userId,
                'update_type' => 'status_change',
                'update_text' => "تغيير الحالة من '{$oldStatus}' إلى '{$newStatus}'" . ($comment ? ": {$comment}" : '')
            ];
            
            $this->db->insert('request_updates', $updateRecord);
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * إضافة تعليق على الطلب
     */
    public function addComment($requestId, $comment, $userId = null, $isVisibleToCitizen = 1) {
        $updateRecord = [
            'request_id' => $requestId,
            'updated_by' => $userId,
            'update_type' => 'comment',
            'update_text' => $comment,
            'is_visible_to_citizen' => $isVisibleToCitizen
        ];
        
        try {
            $this->db->insert('request_updates', $updateRecord);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * تحديث بيانات الطلب (للموظفين)
     */
    public function updateRequest($requestId, $data, $userId = null) {
        try {
            $this->db->beginTransaction();

            $updateData = [];
            $updateText = "";

            // حقول يمكن تحديثها
            $allowedFields = [
                'citizen_name', 'citizen_phone', 'citizen_email', 'citizen_address',
                'national_id', 'request_title', 'request_description', 'priority_level',
                'status', 'assigned_to_department_id', 'assigned_to_user_id',
                'assigned_to', 'admin_notes', 'response_text', 'estimated_completion_date'
            ];

            $currentRequest = $this->getById($requestId);
            if (!$currentRequest) {
                throw new Exception('الطلب غير موجود');
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field]) && $data[$field] !== $currentRequest[$field]) {
                    $updateData[$field] = $data[$field];
                    $updateText .= "تم تحديث {$field} من '{$currentRequest[$field]}' إلى '{$data[$field]}'. ";
                }
            }

            if (!empty($updateData)) {
                $this->db->update('citizen_requests', $updateData, 'id = :id', ['id' => $requestId]);
                
                // إضافة تحديث في سجل التحديثات
                $updateRecord = [
                    'request_id' => $requestId,
                    'updated_by' => $userId,
                    'update_type' => 'request_update',
                    'update_text' => $updateText,
                    'is_visible_to_citizen' => 0
                ];
                $this->db->insert('request_updates', $updateRecord);
            }

            $this->db->commit();
            return ['success' => true];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * الحصول على بيانات النموذج
     */
    private function getFormData($requestId) {
        $sql = "SELECT field_name, field_value FROM request_form_data WHERE request_id = :request_id";
        $rawFormData = $this->db->fetchAll($sql, ['request_id' => $requestId]);
        $formData = [];
        foreach ($rawFormData as $row) {
            $formData[$row['field_name']] = $row['field_value'];
        }
        return $formData;
    }
    
    /**
     * الحصول على المستندات
     */
    private function getDocuments($requestId) {
        $sql = "SELECT * FROM request_documents WHERE request_id = :request_id ORDER BY uploaded_at";
        return $this->db->fetchAll($sql, ['request_id' => $requestId]);
    }
    
    /**
     * الحصول على التحديثات
     */
    private function getUpdates($requestId) {
        $sql = "
            SELECT ru.*, u.full_name as user_name
            FROM request_updates ru
            LEFT JOIN users u ON ru.updated_by = u.id
            WHERE ru.request_id = :request_id
            ORDER BY ru.created_at DESC
        ";
        return $this->db->fetchAll($sql, ['request_id' => $requestId]);
    }
    
    /**
     * إنشاء رقم تتبع فريد
     */
    private function generateTrackingNumber() {
        $prefix = TRACKING_PREFIX . TRACKING_YEAR . '-';
        $randomNumber = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        // التأكد من عدم وجود رقم مشابه
        $trackingNumber = $prefix . $randomNumber;
        $exists = $this->db->fetch(
            "SELECT id FROM citizen_requests WHERE tracking_number = :tracking_number",
            ['tracking_number' => $trackingNumber]
        );
        
        if ($exists) {
            // إعادة المحاولة مع رقم جديد
            return $this->generateTrackingNumber();
        }
        
        return $trackingNumber;
    }
    
    /**
     * الحصول على إحصائيات الطلبات
     */
    public function getStatistics() {
        $stats = [];
        
        // إجمالي الطلبات
        $stats['total'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests")['count'];
        
        // الطلبات الجديدة
        $stats['new'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'جديد'")['count'];
        
        // الطلبات قيد المراجعة
        $stats['in_review'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'قيد المراجعة'")['count'];
        
        // الطلبات قيد التنفيذ
        $stats['in_progress'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'قيد التنفيذ'")['count'];
        
        // الطلبات المكتملة
        $stats['completed'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'مكتمل'")['count'];
        
        // الطلبات المرفوضة
        $stats['rejected'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'مرفوض'")['count'];
        
        // الطلبات المعلقة
        $stats['pending'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE status = 'معلق'")['count'];
        
        // الطلبات العاجلة
        $stats['urgent'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE priority_level = 'عاجل'")['count'];
        
        // الطلبات هذا الشهر
        $stats['this_month'] = $this->db->fetch("SELECT COUNT(*) as count FROM citizen_requests WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")['count'];
        
        return $stats;
    }
}
?> 