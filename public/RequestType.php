<?php

class RequestType {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * الحصول على جميع أنواع الطلبات النشطة
     */
    public function getAll() {
        try {
            $sql = "SELECT * FROM request_types WHERE is_active = 1 ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in RequestType::getAll(): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على جميع أنواع الطلبات النشطة (اسم بديل للتوافق)
     */
    public function getAllActiveTypes() {
        return $this->getAll();
    }
    
    /**
     * الحصول على نوع طلب محدد بواسطة المعرف
     */
    public function getById($id) {
        try {
            $sql = "SELECT * FROM request_types WHERE id = ? AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in RequestType::getById(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على نوع طلب محدد بواسطة الاسم
     */
    public function getByName($name) {
        try {
            $sql = "SELECT * FROM request_types WHERE name = ? AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in RequestType::getByName(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إضافة نوع طلب جديد
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO request_types (name, description, required_documents, form_fields, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['required_documents'] ?? null,
                $data['form_fields'] ?? null,
                $data['is_active'] ?? 1
            ]);
            
            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Error in RequestType::create(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تحديث نوع طلب
     */
    public function update($id, $data) {
        try {
            $setParts = [];
            $params = [];
            
            if (isset($data['name'])) {
                $setParts[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['description'])) {
                $setParts[] = "description = ?";
                $params[] = $data['description'];
            }
            if (isset($data['required_documents'])) {
                $setParts[] = "required_documents = ?";
                $params[] = $data['required_documents'];
            }
            if (isset($data['form_fields'])) {
                $setParts[] = "form_fields = ?";
                $params[] = $data['form_fields'];
            }
            if (isset($data['is_active'])) {
                $setParts[] = "is_active = ?";
                $params[] = $data['is_active'];
            }
            
            if (empty($setParts)) {
                return false;
            }
            
            $setParts[] = "updated_at = NOW()";
            $params[] = $id;
            
            $sql = "UPDATE request_types SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error in RequestType::update(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف نوع طلب (إلغاء تفعيل)
     */
    public function deactivate($id) {
        return $this->update($id, ['is_active' => 0]);
    }
    
    /**
     * تفعيل نوع طلب
     */
    public function activate($id) {
        return $this->update($id, ['is_active' => 1]);
    }
    
    /**
     * البحث في أنواع الطلبات
     */
    public function search($keyword) {
        try {
            $sql = "SELECT * FROM request_types 
                    WHERE (name LIKE ? OR description LIKE ?) 
                    AND is_active = 1 
                    ORDER BY name";
            
            $searchTerm = '%' . $keyword . '%';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in RequestType::search(): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على إحصائيات أنواع الطلبات
     */
    public function getStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_types,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_types,
                        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_types
                    FROM request_types";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in RequestType::getStats(): " . $e->getMessage());
            return [
                'total_types' => 0,
                'active_types' => 0,
                'inactive_types' => 0
            ];
        }
    }
}
?>

