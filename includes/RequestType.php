<?php
/**
 * كلاس إدارة أنواع الطلبات
 * يدعم 40 نوع طلب مع النماذج الديناميكية
 */
class RequestType {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * جلب جميع أنواع الطلبات النشطة
     */
    public function getAllActiveTypes() {
        $stmt = $this->db->prepare("SELECT * FROM request_types WHERE is_active = 1 ORDER BY type_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب نوع طلب محدد
     */
    public function getTypeById($id) {
        $stmt = $this->db->prepare("SELECT * FROM request_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب نوع طلب بالاسم
     */
    public function getTypeByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM request_types WHERE type_name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * جلب الحقول المطلوبة لنوع طلب
     */
    public function getFormFields($typeId) {
        $type = $this->getTypeById($typeId);
        if ($type && $type['form_fields']) {
            return json_decode($type['form_fields'], true);
        }
        return [];
    }
    
    /**
     * جلب المستندات المطلوبة لنوع طلب
     */
    public function getRequiredDocuments($typeId) {
        $type = $this->getTypeById($typeId);
        if ($type && $type['required_documents']) {
            return json_decode($type['required_documents'], true);
        }
        return [];
    }
    
    /**
     * إنشاء HTML للنموذج الديناميكي
     */
    public function generateFormHTML($typeId, $existingData = []) {
        $fields = $this->getFormFields($typeId);
        if (empty($fields)) {
            return '<p class="text-gray-500">لا توجد حقول إضافية لهذا النوع من الطلبات</p>';
        }
        
        $html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
        
        foreach ($fields as $fieldName => $fieldConfig) {
            $html .= $this->generateFieldHTML($fieldName, $fieldConfig, $existingData);
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * إنشاء HTML لحقل واحد
     */
    private function generateFieldHTML($fieldName, $config, $existingData = []) {
        $value = isset($existingData[$fieldName]) ? htmlspecialchars($existingData[$fieldName]) : '';
        $required = isset($config['required']) && $config['required'] ? 'required' : '';
        $label = $config['label'] ?? $fieldName;
        
        $html = '<div class="mb-4">';
        $html .= '<label for="' . $fieldName . '" class="block text-sm font-medium text-gray-700 mb-2">';
        $html .= $label;
        if ($required) {
            $html .= ' <span class="text-red-500">*</span>';
        }
        $html .= '</label>';
        
        $inputClass = "w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500";
        
        switch ($config['type']) {
            case 'text':
                $html .= '<input type="text" id="' . $fieldName . '" name="' . $fieldName . '" value="' . $value . '" ' . $required . ' class="' . $inputClass . '">';
                break;
                
            case 'textarea':
                $rows = $config['rows'] ?? 4;
                $html .= '<textarea id="' . $fieldName . '" name="' . $fieldName . '" rows="' . $rows . '" ' . $required . ' class="' . $inputClass . '">' . $value . '</textarea>';
                break;
                
            case 'select':
                $html .= '<select id="' . $fieldName . '" name="' . $fieldName . '" ' . $required . ' class="' . $inputClass . '">';
                $html .= '<option value="">اختر...</option>';
                if (isset($config['options'])) {
                    foreach ($config['options'] as $option) {
                        $selected = ($value == $option) ? 'selected' : '';
                        $html .= '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
                    }
                }
                $html .= '</select>';
                break;
                
            case 'number':
                $min = isset($config['min']) ? 'min="' . $config['min'] . '"' : '';
                $max = isset($config['max']) ? 'max="' . $config['max'] . '"' : '';
                $html .= '<input type="number" id="' . $fieldName . '" name="' . $fieldName . '" value="' . $value . '" ' . $required . ' ' . $min . ' ' . $max . ' class="' . $inputClass . '">';
                break;
                
            case 'date':
                $html .= '<input type="date" id="' . $fieldName . '" name="' . $fieldName . '" value="' . $value . '" ' . $required . ' class="' . $inputClass . '">';
                break;
                
            case 'email':
                $html .= '<input type="email" id="' . $fieldName . '" name="' . $fieldName . '" value="' . $value . '" ' . $required . ' class="' . $inputClass . '">';
                break;
                
            case 'tel':
                $html .= '<input type="tel" id="' . $fieldName . '" name="' . $fieldName . '" value="' . $value . '" ' . $required . ' class="' . $inputClass . '">';
                break;
        }
        
        // إضافة نص المساعدة إن وجد
        if (isset($config['help'])) {
            $html .= '<p class="text-sm text-gray-500 mt-1">' . htmlspecialchars($config['help']) . '</p>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * التحقق من صحة البيانات المرسلة
     */
    public function validateFormData($typeId, $data) {
        $fields = $this->getFormFields($typeId);
        $errors = [];
        
        foreach ($fields as $fieldName => $fieldConfig) {
            $value = isset($data[$fieldName]) ? trim($data[$fieldName]) : '';
            
            // التحقق من الحقول المطلوبة
            if (isset($fieldConfig['required']) && $fieldConfig['required'] && empty($value)) {
                $errors[$fieldName] = 'هذا الحقل مطلوب';
                continue;
            }
            
            // التحقق من صحة البيانات حسب النوع
            if (!empty($value)) {
                $validation = $fieldConfig['validation'] ?? '';
                if ($validation) {
                    $validationResult = $this->validateField($value, $validation, $fieldConfig);
                    if ($validationResult !== true) {
                        $errors[$fieldName] = $validationResult;
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * التحقق من صحة حقل واحد
     */
    private function validateField($value, $validation, $config) {
        $rules = explode('|', $validation);
        
        foreach ($rules as $rule) {
            if (strpos($rule, ':') !== false) {
                list($ruleName, $ruleValue) = explode(':', $rule, 2);
            } else {
                $ruleName = $rule;
                $ruleValue = null;
            }
            
            switch ($ruleName) {
                case 'required':
                    if (empty($value)) return 'هذا الحقل مطلوب';
                    break;
                    
                case 'string':
                    if (!is_string($value)) return 'يجب أن يكون النص';
                    break;
                    
                case 'numeric':
                    if (!is_numeric($value)) return 'يجب أن يكون رقماً';
                    break;
                    
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return 'بريد إلكتروني غير صحيح';
                    break;
                    
                case 'min':
                    if (strlen($value) < intval($ruleValue)) return 'يجب أن يكون أكثر من ' . $ruleValue . ' أحرف';
                    break;
                    
                case 'max':
                    if (strlen($value) > intval($ruleValue)) return 'يجب أن يكون أقل من ' . $ruleValue . ' حرف';
                    break;
                    
                case 'digits':
                    if (!preg_match('/^\d{' . $ruleValue . '}$/', $value)) return 'يجب أن يكون ' . $ruleValue . ' أرقام';
                    break;
                    
                case 'date':
                    if (!strtotime($value)) return 'تاريخ غير صحيح';
                    break;
                    
                case 'after':
                    if ($ruleValue == 'today' && strtotime($value) <= time()) return 'يجب أن يكون التاريخ في المستقبل';
                    break;
                    
                case 'before_or_equal':
                    if ($ruleValue == 'today' && strtotime($value) > time()) return 'يجب أن يكون التاريخ اليوم أو في الماضي';
                    break;
                    
                case 'in':
                    $allowedValues = explode(',', $ruleValue);
                    if (!in_array($value, $allowedValues)) return 'قيمة غير مسموحة';
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * حفظ بيانات النموذج
     */
    public function saveFormData($requestId, $typeId, $data) {
        try {
            // التحقق من صحة البيانات أولاً
            $errors = $this->validateFormData($typeId, $data);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // حفظ البيانات في جدول request_form_data
            $stmt = $this->db->prepare("INSERT INTO request_form_data (request_id, form_data, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$requestId, json_encode($data, JSON_UNESCAPED_UNICODE)]);
            
            if ($result) {
                return ['success' => true, 'form_data_id' => $this->db->lastInsertId()];
            } else {
                return ['success' => false, 'error' => 'فشل في حفظ البيانات'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()];
        }
    }
    
    /**
     * جلب بيانات النموذج المحفوظة
     */
    public function getFormData($requestId) {
        $stmt = $this->db->prepare("SELECT form_data FROM request_form_data WHERE request_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$requestId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['form_data']) {
            return json_decode($result['form_data'], true);
        }
        
        return [];
    }
}
?> 