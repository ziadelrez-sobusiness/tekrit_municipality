<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("فشل في الاتصال بقاعدة البيانات");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * تنفيذ استعلام SELECT وإرجاع سجل واحد
     */
    public function fetch($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database fetch error: " . $e->getMessage());
            throw new Exception("خطأ في جلب البيانات");
        }
    }
    
    /**
     * تنفيذ استعلام SELECT وإرجاع جميع السجلات
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database fetchAll error: " . $e->getMessage());
            throw new Exception("خطأ في جلب البيانات");
        }
    }
    
    /**
     * إدراج سجل جديد
     */
    public function insert($table, $data) {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage());
            throw new Exception("خطأ في إدراج البيانات");
        }
    }
    
    /**
     * تحديث سجل
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $setClause = [];
            foreach (array_keys($data) as $key) {
                $setClause[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $setClause);
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $stmt = $this->connection->prepare($sql);
            
            $params = array_merge($data, $whereParams);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database update error: " . $e->getMessage());
            throw new Exception("خطأ في تحديث البيانات");
        }
    }
    
    /**
     * حذف سجل
     */
    public function delete($table, $where, $whereParams = []) {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($whereParams);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database delete error: " . $e->getMessage());
            throw new Exception("خطأ في حذف البيانات");
        }
    }
    
    /**
     * بدء معاملة
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * تأكيد المعاملة
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * إلغاء المعاملة
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * تنفيذ استعلام مخصص
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw new Exception("خطأ في تنفيذ الاستعلام");
        }
    }
}
?> 