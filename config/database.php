<?php
class Database {
    private $host = "localhost";
    private $db_name = "tekrit_municipality";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                                $this->username, 
                                $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // لا نعرض الخطأ مباشرة، نترك للكود المتصل التعامل معه
            error_log("Database connection error: " . $exception->getMessage());
            $this->conn = null;
        }

        return $this->conn;
    }
}
?> 
