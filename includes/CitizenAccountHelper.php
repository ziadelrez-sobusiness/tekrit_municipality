<?php
/**
 * Citizen Account Helper
 * مساعد إدارة حسابات المواطنين
 * بلدية تكريت - عكار، شمال لبنان
 */

class CitizenAccountHelper {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * الحصول على حساب مواطن أو إنشاؤه
     */
    public function getOrCreateAccount($phone, $name = null, $email = null, $nationalId = null, $telegramChatId = null, $telegramUsername = null) {
        try {
            // محاولة استخدام Stored Procedure
            $stmt = $this->db->prepare("CALL sp_get_or_create_citizen_account(?, ?, ?, ?, ?, ?)");
            $stmt->execute([$phone, $name, $email, $nationalId, $telegramChatId, $telegramUsername]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            if ($result) {
                return [
                    'success' => true,
                    'citizen_id' => $result['citizen_id'],
                    'access_code' => $result['access_code']
                ];
            }
            
        } catch (PDOException $e) {
            // إذا فشل Stored Procedure، استخدم SQL مباشر
            error_log("Stored Procedure failed, using direct SQL: " . $e->getMessage());
        }
        
        // الطريقة البديلة: SQL مباشر
        try {
            // البحث عن الحساب
            $stmt = $this->db->prepare("SELECT id, permanent_access_code FROM citizens_accounts WHERE phone = ?");
            $stmt->execute([$phone]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                // تحديث البيانات
                $accessCode = $account['permanent_access_code'];
                
                // إذا لم يكن هناك رمز دخول، أنشئ واحد
                if (empty($accessCode)) {
                    $accessCode = $this->generateAccessCode();
                    $stmt = $this->db->prepare("UPDATE citizens_accounts SET permanent_access_code = ? WHERE id = ?");
                    $stmt->execute([$accessCode, $account['id']]);
                }
                
                // تحديث البيانات الأخرى إذا كانت موجودة
                $updateFields = [];
                $updateValues = [];
                
                if ($name) {
                    $updateFields[] = "name = ?";
                    $updateValues[] = $name;
                }
                if ($email) {
                    $updateFields[] = "email = ?";
                    $updateValues[] = $email;
                }
                if ($nationalId) {
                    $updateFields[] = "national_id = ?";
                    $updateValues[] = $nationalId;
                }
                if ($telegramChatId) {
                    $updateFields[] = "telegram_chat_id = ?";
                    $updateValues[] = $telegramChatId;
                }
                if ($telegramUsername) {
                    $updateFields[] = "telegram_username = ?";
                    $updateValues[] = $telegramUsername;
                }
                
                if (!empty($updateFields)) {
                    $updateValues[] = $account['id'];
                    
                    $sql = "UPDATE citizens_accounts SET " . implode(", ", $updateFields) . " WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($updateValues);
                }
                
                return [
                    'success' => true,
                    'citizen_id' => $account['id'],
                    'access_code' => $accessCode
                ];
                
            } else {
                // إنشاء حساب جديد
                $accessCode = $this->generateAccessCode();
                
                $stmt = $this->db->prepare("
                    INSERT INTO citizens_accounts (
                        phone, name, email, national_id, 
                        telegram_chat_id, telegram_username,
                        permanent_access_code, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $phone, $name, $email, $nationalId,
                    $telegramChatId, $telegramUsername,
                    $accessCode
                ]);
                
                return [
                    'success' => true,
                    'citizen_id' => $this->db->lastInsertId(),
                    'access_code' => $accessCode
                ];
            }
            
        } catch (Exception $e) {
            error_log("Citizen Account Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * توليد رمز دخول ثابت
     */
    private function generateAccessCode() {
        $prefix = 'TKT-';
        $digits = ['0','1','2','3','4','5','6','7','8','9'];
        $maxAttempts = 100;
        $attempt = 0;
        
        do {
            $availableDigits = $digits;
            $codeDigits = '';
            
            // اختيار 5 أرقام فريدة
            for ($i = 0; $i < 5; $i++) {
                $index = random_int(0, count($availableDigits) - 1);
                $codeDigits .= $availableDigits[$index];
                array_splice($availableDigits, $index, 1);
            }
            
            $fullCode = $prefix . $codeDigits;
            
            // التحقق من عدم تكرار الرمز
            $stmt = $this->db->prepare("SELECT id FROM citizens_accounts WHERE permanent_access_code = ?");
            $stmt->execute([$fullCode]);
            
            if (!$stmt->fetch()) {
                return $fullCode; // الرمز فريد
            }
            
            $attempt++;
        } while ($attempt < $maxAttempts);
        
        // في حالة نادرة جداً: إذا فشل التوليد بعد 100 محاولة
        $shuffled = str_shuffle('0123456789');
        return $prefix . substr($shuffled, 0, 5);
    }
    
    /**
     * الحصول على حساب مواطن برمز الدخول
     */
    public function getAccountByAccessCode($accessCode) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM citizens_accounts 
                WHERE permanent_access_code = ?
            ");
            $stmt->execute([$accessCode]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                // تحديث آخر دخول
                $updateStmt = $this->db->prepare("UPDATE citizens_accounts SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$account['id']]);
                
                return [
                    'success' => true,
                    'account' => $account
                ];
            }
            
            return [
                'success' => false,
                'error' => 'رمز الدخول غير صحيح'
            ];
            
        } catch (Exception $e) {
            error_log("Get Account Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * الحصول على طلبات المواطن
     */
    public function getCitizenRequests($citizenPhone, $limit = null) {
        try {
            // DEBUG
            error_log("=== getCitizenRequests DEBUG ===");
            error_log("Input Phone: " . $citizenPhone);
            
            $sql = "
                SELECT cr.*, rt.type_name
                FROM citizen_requests cr
                LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                WHERE cr.citizen_phone = ?
                ORDER BY cr.created_at DESC
            ";
            
            if ($limit) {
                $sql .= " LIMIT " . intval($limit);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$citizenPhone]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // DEBUG
            error_log("Results Count: " . count($results));
            error_log("SQL: " . $sql);
            error_log("Results: " . print_r($results, true));
            
            // إذا لم يجد نتائج، جرّب البحث بدون مسافات/أصفار
            if (empty($results)) {
                error_log("No results found, trying smart search...");
                
                // تنظيف الرقم
                $cleanPhone = preg_replace('/\s+/', '', $citizenPhone);
                $cleanPhone = ltrim($cleanPhone, '0');
                
                error_log("Clean Phone: " . $cleanPhone);
                
                // البحث عن أرقام مشابهة
                $sql2 = "
                    SELECT cr.*, rt.type_name
                    FROM citizen_requests cr
                    LEFT JOIN request_types rt ON cr.request_type_id = rt.id
                    WHERE REPLACE(LTRIM(cr.citizen_phone, '0'), ' ', '') = ?
                    ORDER BY cr.created_at DESC
                ";
                
                if ($limit) {
                    $sql2 .= " LIMIT " . intval($limit);
                }
                
                $stmt2 = $this->db->prepare($sql2);
                $stmt2->execute([$cleanPhone]);
                $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Smart Search Results Count: " . count($results));
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Get Citizen Requests Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على رسائل المواطن
     */
    public function getCitizenMessages($citizenId, $limit = 20) {
        try {
            $limit = intval($limit); // تأكد من أنه رقم صحيح
            
            $stmt = $this->db->prepare("
                SELECT * FROM citizen_messages
                WHERE citizen_id = ?
                ORDER BY created_at DESC
                LIMIT $limit
            ");
            $stmt->execute([$citizenId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Citizen Messages Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على إحصائيات المواطن
     */
    public function getCitizenStats($citizenPhone) {
        try {
            $stats = [
                'total_requests' => 0,
                'active_requests' => 0,
                'completed_requests' => 0,
                'rejected_requests' => 0
            ];
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('جديد', 'قيد المراجعة', 'قيد التنفيذ') THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'مكتمل' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'مرفوض' THEN 1 ELSE 0 END) as rejected
                FROM citizen_requests
                WHERE citizen_phone = ?
            ");
            $stmt->execute([$citizenPhone]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stats['total_requests'] = $result['total'];
                $stats['active_requests'] = $result['active'];
                $stats['completed_requests'] = $result['completed'];
                $stats['rejected_requests'] = $result['rejected'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get Citizen Stats Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ربط Telegram Chat ID بحساب المواطن
     */
    public function linkTelegramAccount($phone, $chatId, $username = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE citizens_accounts 
                SET telegram_chat_id = ?, 
                    telegram_username = ?
                WHERE phone = ?
            ");
            
            $stmt->execute([$chatId, $username, $phone]);
            
            return [
                'success' => true,
                'message' => 'تم ربط حساب Telegram بنجاح'
            ];
            
        } catch (Exception $e) {
            error_log("Link Telegram Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
