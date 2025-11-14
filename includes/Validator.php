<?php
/**
 * Validator - نظام التحقق من المدخلات
 * 
 * يوفر نظام شامل للتحقق من صحة البيانات المدخلة
 * مع دعم القواعد المخصصة والرسائل المخصصة
 */

class Validator {
    private $errors = [];
    private $data = [];
    
    /**
     * إنشاء مثيل جديد من Validator
     */
    public function __construct($data = []) {
        $this->data = $data;
        $this->errors = [];
    }
    
    /**
     * إضافة قاعدة تحقق
     */
    public function rule($field, $rules, $message = null) {
        if (!isset($this->data[$field])) {
            $this->data[$field] = null;
        }
        
        $value = $this->data[$field];
        $rulesArray = is_array($rules) ? $rules : explode('|', $rules);
        
        foreach ($rulesArray as $rule) {
            $ruleParts = explode(':', $rule);
            $ruleName = $ruleParts[0];
            $ruleValue = isset($ruleParts[1]) ? $ruleParts[1] : null;
            
            if (!$this->validateRule($field, $value, $ruleName, $ruleValue)) {
                $errorMessage = $message ?? $this->getDefaultErrorMessage($field, $ruleName, $ruleValue);
                $this->addError($field, $errorMessage);
                break; // توقف عند أول خطأ
            }
        }
        
        return $this;
    }
    
    /**
     * التحقق من قاعدة معينة
     */
    private function validateRule($field, $value, $ruleName, $ruleValue) {
        switch ($ruleName) {
            case 'required':
                return !empty($value) || $value === '0' || $value === 0;
            
            case 'email':
                return empty($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            
            case 'numeric':
                return empty($value) || is_numeric($value);
            
            case 'integer':
                return empty($value) || filter_var($value, FILTER_VALIDATE_INT) !== false;
            
            case 'float':
                return empty($value) || filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
            
            case 'min':
                if (empty($value)) return true;
                $min = (float)$ruleValue;
                if (is_numeric($value)) {
                    return (float)$value >= $min;
                }
                return mb_strlen($value) >= $min;
            
            case 'max':
                if (empty($value)) return true;
                $max = (float)$ruleValue;
                if (is_numeric($value)) {
                    return (float)$value <= $max;
                }
                return mb_strlen($value) <= $max;
            
            case 'length':
                if (empty($value)) return true;
                $length = (int)$ruleValue;
                return mb_strlen($value) === $length;
            
            case 'min_length':
                if (empty($value)) return true;
                $minLength = (int)$ruleValue;
                return mb_strlen($value) >= $minLength;
            
            case 'max_length':
                if (empty($value)) return true;
                $maxLength = (int)$ruleValue;
                return mb_strlen($value) <= $maxLength;
            
            case 'regex':
                if (empty($value)) return true;
                return preg_match($ruleValue, $value) === 1;
            
            case 'in':
                if (empty($value)) return true;
                $allowedValues = explode(',', $ruleValue);
                return in_array($value, $allowedValues);
            
            case 'not_in':
                if (empty($value)) return true;
                $forbiddenValues = explode(',', $ruleValue);
                return !in_array($value, $forbiddenValues);
            
            case 'date':
                if (empty($value)) return true;
                $d = DateTime::createFromFormat('Y-m-d', $value);
                return $d && $d->format('Y-m-d') === $value;
            
            case 'datetime':
                if (empty($value)) return true;
                $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                return $d && $d->format('Y-m-d H:i:s') === $value;
            
            case 'url':
                return empty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
            
            case 'phone':
                if (empty($value)) return true;
                // دعم الأرقام اللبنانية
                $phone = preg_replace('/[^0-9]/', '', $value);
                // أرقام لبنانية: تبدأ بـ 03 أو 01 أو 05 أو 07 أو 09
                return preg_match('/^(03|01|05|07|09)[0-9]{6,7}$/', $phone);
            
            case 'lebanese_phone':
                if (empty($value)) return true;
                $phone = preg_replace('/[^0-9]/', '', $value);
                return preg_match('/^(03|01|05|07|09)[0-9]{6,7}$/', $phone);
            
            case 'national_id':
                if (empty($value)) return true;
                // رقم وطني لبناني (11 رقم)
                $id = preg_replace('/[^0-9]/', '', $value);
                return strlen($id) === 11 && $id[0] !== '0';
            
            case 'unique':
                // هذا يتطلب قاعدة بيانات - سنتركه للمستقبل
                return true;
            
            case 'exists':
                // هذا يتطلب قاعدة بيانات - سنتركه للمستقبل
                return true;
            
            default:
                return true;
        }
    }
    
    /**
     * الحصول على رسالة الخطأ الافتراضية
     */
    private function getDefaultErrorMessage($field, $ruleName, $ruleValue) {
        $messages = [
            'required' => "حقل '$field' مطلوب",
            'email' => "حقل '$field' يجب أن يكون بريد إلكتروني صحيح",
            'numeric' => "حقل '$field' يجب أن يكون رقماً",
            'integer' => "حقل '$field' يجب أن يكون رقماً صحيحاً",
            'float' => "حقل '$field' يجب أن يكون رقماً عشرياً",
            'min' => "حقل '$field' يجب أن يكون أكبر من أو يساوي $ruleValue",
            'max' => "حقل '$field' يجب أن يكون أصغر من أو يساوي $ruleValue",
            'length' => "حقل '$field' يجب أن يكون طوله $ruleValue",
            'min_length' => "حقل '$field' يجب أن يكون طوله على الأقل $ruleValue",
            'max_length' => "حقل '$field' يجب أن يكون طوله على الأكثر $ruleValue",
            'regex' => "حقل '$field' غير صحيح",
            'in' => "حقل '$field' يجب أن يكون واحداً من: $ruleValue",
            'not_in' => "حقل '$field' لا يمكن أن يكون: $ruleValue",
            'date' => "حقل '$field' يجب أن يكون تاريخاً صحيحاً (YYYY-MM-DD)",
            'datetime' => "حقل '$field' يجب أن يكون تاريخاً ووقتاً صحيحاً",
            'url' => "حقل '$field' يجب أن يكون رابطاً صحيحاً",
            'phone' => "حقل '$field' يجب أن يكون رقم هاتف صحيح",
            'lebanese_phone' => "حقل '$field' يجب أن يكون رقم هاتف لبناني صحيح",
            'national_id' => "حقل '$field' يجب أن يكون رقم وطني لبناني صحيح (11 رقم)"
        ];
        
        return $messages[$ruleName] ?? "حقل '$field' غير صحيح";
    }
    
    /**
     * إضافة خطأ
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * التحقق من صحة جميع القواعد
     */
    public function validate() {
        return empty($this->errors);
    }
    
    /**
     * الحصول على جميع الأخطاء
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * الحصول على أول خطأ لحقل معين
     */
    public function getError($field) {
        return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
    }
    
    /**
     * الحصول على جميع الأخطاء كسلسلة نصية
     */
    public function getErrorsAsString($separator = '<br>') {
        $allErrors = [];
        foreach ($this->errors as $field => $errors) {
            $allErrors = array_merge($allErrors, $errors);
        }
        return implode($separator, $allErrors);
    }
    
    /**
     * التحقق من وجود أخطاء
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * التحقق من وجود خطأ في حقل معين
     */
    public function hasError($field) {
        return isset($this->errors[$field]);
    }
    
    /**
     * تنظيف البيانات (إزالة HTML tags)
     */
    public function sanitize($field, $value) {
        // إزالة HTML tags
        $value = strip_tags($value);
        
        // تنظيف المسافات الزائدة
        $value = trim($value);
        
        // حماية من XSS
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
    /**
     * تنظيف جميع البيانات
     */
    public function sanitizeAll() {
        foreach ($this->data as $field => $value) {
            if (is_string($value)) {
                $this->data[$field] = $this->sanitize($field, $value);
            }
        }
        return $this->data;
    }
    
    /**
     * الحصول على البيانات المفلترة
     */
    public function getData($fields = null) {
        if ($fields === null) {
            return $this->data;
        }
        
        $filtered = [];
        $fieldsArray = is_array($fields) ? $fields : explode(',', $fields);
        
        foreach ($fieldsArray as $field) {
            $field = trim($field);
            if (isset($this->data[$field])) {
                $filtered[$field] = $this->data[$field];
            }
        }
        
        return $filtered;
    }
    
    /**
     * طريقة سريعة للتحقق من حقل واحد
     */
    public static function quick($value, $rules, $fieldName = 'field') {
        $validator = new self([$fieldName => $value]);
        $validator->rule($fieldName, $rules);
        return $validator->validate();
    }
}

