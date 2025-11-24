<?php
/**
 * Base Mapper لمعالجة البيانات من مصادر مختلفة
 */

abstract class BaseMapper {
    protected $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * تحويل البيانات الخام إلى صيغة موحدة
     */
    abstract public function map($rawData, $source);
    
    /**
     * حفظ البيانات في قاعدة البيانات
     */
    abstract public function save($mappedData, $source);
    
    /**
     * البحث عن رابط موجود
     */
    protected function findExistingLink($criteria) {
        $where = [];
        $params = [];
        
        if (!empty($criteria['name_ar'])) {
            $where[] = "name_ar = ?";
            $params[] = $criteria['name_ar'];
        }
        
        if (!empty($criteria['phone'])) {
            $where[] = "(phone = ? OR phone_2 = ?)";
            $params[] = $criteria['phone'];
            $params[] = $criteria['phone'];
        }
        
        if (!empty($criteria['website'])) {
            $where[] = "website = ?";
            $params[] = $criteria['website'];
        }
        
        if (empty($where)) {
            return null;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM important_links WHERE " . implode(' OR ', $where) . " LIMIT 1");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * إدراج رابط جديد
     */
    protected function insertLink($data) {
        $stmt = $this->db->prepare("
            INSERT INTO important_links 
            (category_id, name_ar, name_en, description_ar, phone, phone_2, email, website, 
             address_ar, location_lat, location_lng, working_hours_ar, 
             is_government, is_emergency, display_order, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $data['category_id'] ?? null,
            $data['name_ar'] ?? '',
            $data['name_en'] ?? null,
            $data['description_ar'] ?? null,
            $data['phone'] ?? null,
            $data['phone_2'] ?? null,
            $data['email'] ?? null,
            $data['website'] ?? null,
            $data['address_ar'] ?? null,
            $data['location_lat'] ?? null,
            $data['location_lng'] ?? null,
            $data['working_hours_ar'] ?? null,
            $data['is_government'] ?? 0,
            $data['is_emergency'] ?? 0,
            $data['display_order'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * تحديث رابط موجود
     */
    protected function updateLink($linkId, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['category_id', 'name_ar', 'name_en', 'description_ar', 'phone', 'phone_2', 
                         'email', 'website', 'address_ar', 'location_lat', 'location_lng', 
                         'working_hours_ar', 'is_government', 'is_emergency', 'display_order'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $linkId;
        $stmt = $this->db->prepare("UPDATE important_links SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?");
        return $stmt->execute($params);
    }
}

