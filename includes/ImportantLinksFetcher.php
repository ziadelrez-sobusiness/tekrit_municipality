<?php
/**
 * نظام جلب وتحديث روابط مهمة تلقائياً
 * بلدية تكريت
 */

require_once __DIR__ . '/../config/database.php';

class ImportantLinksFetcher {
    private $db;
    private $logMessages = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->db->exec("SET NAMES utf8mb4");
    }
    
    /**
     * جلب البيانات من مصدر محدد
     */
    public function fetchFromSource($sourceId) {
        $startTime = microtime(true);
        
        try {
            // جلب معلومات المصدر
            $stmt = $this->db->prepare("SELECT * FROM important_link_sources WHERE id = ? AND is_active = 1");
            $stmt->execute([$sourceId]);
            $source = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$source) {
                throw new Exception("المصدر غير موجود أو غير نشط");
            }
            
            $this->log("بدء جلب البيانات من: " . $source['name_ar']);
            
            $itemsFetched = 0;
            $itemsImported = 0;
            $itemsUpdated = 0;
            $status = 'success';
            $errorMessage = null;
            
            // جلب البيانات حسب نوع المصدر
            switch ($source['source_type']) {
                case 'api':
                    $data = $this->fetchFromAPI($source);
                    break;
                case 'scraping':
                    $data = $this->fetchFromScraping($source);
                    break;
                case 'csv_import':
                    $data = $this->fetchFromCSV($source);
                    break;
                default:
                    throw new Exception("نوع المصدر غير مدعوم: " . $source['source_type']);
            }
            
            $itemsFetched = count($data);
            
            // معالجة البيانات واستيرادها
            if (!empty($data)) {
                $result = $this->importData($data, $source);
                $itemsImported = $result['imported'];
                $itemsUpdated = $result['updated'];
            }
            
            // تحديث معلومات المصدر
            $this->updateSourceStatus($sourceId, 'success', $itemsFetched, $itemsImported, $itemsUpdated);
            
            // حساب وقت التنفيذ
            $executionTime = round(microtime(true) - $startTime, 2);
            
            // تسجيل العملية
            $this->logFetch($sourceId, 'auto', $status, $itemsFetched, $itemsImported, $itemsUpdated, null, $executionTime);
            
            $this->log("تم جلب " . $itemsFetched . " عنصر، استيراد " . $itemsImported . "، تحديث " . $itemsUpdated);
            
            return [
                'success' => true,
                'items_fetched' => $itemsFetched,
                'items_imported' => $itemsImported,
                'items_updated' => $itemsUpdated,
                'execution_time' => $executionTime
            ];
            
        } catch (Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 2);
            $errorMessage = $e->getMessage();
            
            $this->log("خطأ: " . $errorMessage);
            
            // تحديث معلومات المصدر
            $this->updateSourceStatus($sourceId, 'failed', 0, 0, 0, $errorMessage);
            
            // تسجيل العملية
            $this->logFetch($sourceId, 'auto', 'failed', 0, 0, 0, $errorMessage, $executionTime);
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'execution_time' => $executionTime
            ];
        }
    }
    
    /**
     * جلب البيانات من API
     * يمكن استدعاؤها للاختبار
     */
    public function fetchFromAPI($source) {
        $url = $source['api_url'];
        if (empty($url)) {
            throw new Exception("رابط API غير محدد");
        }
        
        // التحقق من صحة الرابط
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("رابط API غير صحيح: " . $url);
        }
        
        // إضافة API key إذا كان موجوداً
        if (!empty($source['api_key'])) {
            // فك التشفير إذا كان مشفراً
            $apiKey = $this->decryptApiKey($source['api_key']);
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'api_key=' . urlencode($apiKey);
        }
        
        $this->log("محاولة الاتصال بـ: " . $url);
        
        // إعداد cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Tekrit-Municipality-Bot/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log("خطأ cURL: " . $error);
            throw new Exception("خطأ في الاتصال: " . $error . " (URL: " . $url . ")");
        }
        
        if ($httpCode === 0) {
            throw new Exception("فشل الاتصال بالخادم. تحقق من الاتصال بالإنترنت أو صحة الرابط.");
        }
        
        if ($httpCode !== 200) {
            $this->log("HTTP Code: " . $httpCode . ", Response: " . substr($response, 0, 200));
            
            // رسائل خطأ محددة حسب كود HTTP
            $errorMessages = [
                404 => "الرابط غير موجود (404). تحقق من صحة رابط API أو قد يكون الرابط قد تغير.",
                401 => "غير مصرح (401). قد تحتاج إلى API key صحيح.",
                403 => "ممنوع الوصول (403). قد تحتاج إلى صلاحيات خاصة.",
                500 => "خطأ في الخادم (500). الخادم يواجه مشكلة، حاول لاحقاً.",
                503 => "الخدمة غير متاحة (503). الخادم في صيانة.",
            ];
            
            $errorMsg = $errorMessages[$httpCode] ?? "خطأ HTTP " . $httpCode;
            
            // إذا كانت الاستجابة HTML (مثل صفحة 404)، أضف ملاحظة
            if (stripos($response, '<!DOCTYPE') !== false || stripos($response, '<html') !== false) {
                $errorMsg .= " (الاستجابة هي صفحة HTML وليست JSON - الرابط قد يكون خاطئاً)";
            }
            
            throw new Exception($errorMsg);
        }
        
        if (empty($response)) {
            throw new Exception("الاستجابة فارغة من الخادم");
        }
        
        $this->log("تم استلام " . strlen($response) . " بايت من البيانات");
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("خطأ JSON: " . json_last_error_msg() . ", Response preview: " . substr($response, 0, 200));
            throw new Exception("خطأ في تحليل JSON: " . json_last_error_msg() . ". الاستجابة قد تكون HTML أو نص عادي.");
        }
        
        if (!is_array($data)) {
            $this->log("البيانات المستلمة ليست مصفوفة. النوع: " . gettype($data));
            // محاولة استخراج البيانات من بنية مختلفة
            if (is_object($data)) {
                $data = (array)$data;
            } else {
                throw new Exception("البيانات المستلمة ليست في الصيغة المتوقعة (مصفوفة). النوع: " . gettype($data));
            }
        }
        
        $this->log("تم تحليل " . count($data) . " عنصر من JSON");
        
        // تطبيق mapping إذا كان موجوداً
        if (!empty($source['mapping_config'])) {
            $mapping = json_decode($source['mapping_config'], true);
            if ($mapping) {
                $data = $this->applyMapping($data, $mapping);
                $this->log("تم تطبيق mapping، عدد العناصر بعد التطبيق: " . count($data));
            }
        }
        
        // إذا كانت البيانات في مصفوفة داخلية (مثل data.items)
        if (count($data) === 1 && isset($data[0]) && is_array($data[0]) && count($data[0]) > 0) {
            // قد تكون البيانات في مستوى واحد فقط
            $this->log("البيانات في مستوى واحد");
        }
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * جلب البيانات من scraping
     */
    private function fetchFromScraping($source) {
        $url = $source['scraping_url'];
        if (empty($url)) {
            throw new Exception("رابط scraping غير محدد");
        }
        
        // استخدام DOMDocument أو SimpleHTMLDom
        $html = @file_get_contents($url);
        if ($html === false) {
            throw new Exception("فشل في جلب الصفحة");
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $data = [];
        
        // استخدام selector إذا كان محدداً
        if (!empty($source['scraping_selector'])) {
            $selectors = json_decode($source['scraping_selector'], true);
            
            // جلب العناصر
            $items = $xpath->query($selectors['item_selector'] ?? '//div[@class="item"]');
            
            foreach ($items as $item) {
                $itemData = [];
                
                // استخراج البيانات حسب selectors
                foreach ($selectors['fields'] ?? [] as $field => $selector) {
                    $nodes = $xpath->query($selector, $item);
                    if ($nodes->length > 0) {
                        $itemData[$field] = trim($nodes->item(0)->textContent);
                    }
                }
                
                if (!empty($itemData)) {
                    $data[] = $itemData;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * جلب البيانات من CSV
     * يمكن استدعاؤها للاختبار
     */
    public function fetchFromCSV($source) {
        // يمكن إضافة دعم CSV لاحقاً
        throw new Exception("CSV import غير مدعوم حالياً");
    }
    
    /**
     * استيراد البيانات إلى قاعدة البيانات
     */
    private function importData($data, $source) {
        $imported = 0;
        $updated = 0;
        
        foreach ($data as $item) {
            try {
                // تطبيق mapping
                $mappedItem = $this->mapItemData($item, $source);
                
                // البحث عن رابط موجود (بالاسم أو الهاتف)
                $existing = $this->findExistingLink($mappedItem);
                
                if ($existing) {
                    // تحديث الرابط الموجود
                    $this->updateLink($existing['id'], $mappedItem);
                    $updated++;
                } else {
                    // إضافة رابط جديد
                    $this->insertLink($mappedItem);
                    $imported++;
                }
            } catch (Exception $e) {
                $this->log("خطأ في استيراد عنصر: " . $e->getMessage());
                continue;
            }
        }
        
        return ['imported' => $imported, 'updated' => $updated];
    }
    
    /**
     * ربط بيانات العنصر مع الحقول
     */
    private function mapItemData($item, $source) {
        $mapped = [
            'category_id' => $source['category_id'] ?? null,
            'name_ar' => $item['name_ar'] ?? $item['name'] ?? '',
            'name_en' => $item['name_en'] ?? $item['name_en'] ?? null,
            'description_ar' => $item['description_ar'] ?? $item['description'] ?? null,
            'phone' => $item['phone'] ?? $item['telephone'] ?? null,
            'phone_2' => $item['phone_2'] ?? null,
            'email' => $item['email'] ?? null,
            'website' => $item['website'] ?? $item['url'] ?? null,
            'address_ar' => $item['address_ar'] ?? $item['address'] ?? null,
            'location_lat' => isset($item['latitude']) ? floatval($item['latitude']) : null,
            'location_lng' => isset($item['longitude']) ? floatval($item['longitude']) : null,
            'working_hours_ar' => $item['working_hours'] ?? null,
            'is_government' => isset($item['is_government']) ? (int)$item['is_government'] : 0,
            'is_emergency' => isset($item['is_emergency']) ? (int)$item['is_emergency'] : 0,
            'display_order' => 0
        ];
        
        // تطبيق mapping مخصص إذا كان موجوداً
        if (!empty($source['mapping_config'])) {
            $mapping = json_decode($source['mapping_config'], true);
            foreach ($mapping as $dbField => $sourceField) {
                if (isset($item[$sourceField])) {
                    $mapped[$dbField] = $item[$sourceField];
                }
            }
        }
        
        return $mapped;
    }
    
    /**
     * البحث عن رابط موجود
     */
    private function findExistingLink($item) {
        // البحث بالاسم
        if (!empty($item['name_ar'])) {
            $stmt = $this->db->prepare("SELECT * FROM important_links WHERE name_ar = ? LIMIT 1");
            $stmt->execute([$item['name_ar']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return $existing;
            }
        }
        
        // البحث بالهاتف
        if (!empty($item['phone'])) {
            $stmt = $this->db->prepare("SELECT * FROM important_links WHERE phone = ? OR phone_2 = ? LIMIT 1");
            $stmt->execute([$item['phone'], $item['phone']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return $existing;
            }
        }
        
        return null;
    }
    
    /**
     * إدراج رابط جديد
     */
    private function insertLink($item) {
        $stmt = $this->db->prepare("
            INSERT INTO important_links 
            (category_id, name_ar, name_en, description_ar, phone, phone_2, email, website, 
             address_ar, location_lat, location_lng, working_hours_ar, 
             is_government, is_emergency, display_order, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $item['category_id'],
            $item['name_ar'],
            $item['name_en'],
            $item['description_ar'],
            $item['phone'],
            $item['phone_2'],
            $item['email'],
            $item['website'],
            $item['address_ar'],
            $item['location_lat'],
            $item['location_lng'],
            $item['working_hours_ar'],
            $item['is_government'],
            $item['is_emergency'],
            $item['display_order']
        ]);
    }
    
    /**
     * تحديث رابط موجود
     */
    private function updateLink($linkId, $item) {
        $stmt = $this->db->prepare("
            UPDATE important_links SET 
            category_id = COALESCE(?, category_id),
            name_ar = COALESCE(?, name_ar),
            name_en = COALESCE(?, name_en),
            description_ar = COALESCE(?, description_ar),
            phone = COALESCE(?, phone),
            phone_2 = COALESCE(?, phone_2),
            email = COALESCE(?, email),
            website = COALESCE(?, website),
            address_ar = COALESCE(?, address_ar),
            location_lat = COALESCE(?, location_lat),
            location_lng = COALESCE(?, location_lng),
            working_hours_ar = COALESCE(?, working_hours_ar),
            is_government = COALESCE(?, is_government),
            is_emergency = COALESCE(?, is_emergency),
            updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $item['category_id'],
            $item['name_ar'],
            $item['name_en'],
            $item['description_ar'],
            $item['phone'],
            $item['phone_2'],
            $item['email'],
            $item['website'],
            $item['address_ar'],
            $item['location_lat'],
            $item['location_lng'],
            $item['working_hours_ar'],
            $item['is_government'],
            $item['is_emergency'],
            $linkId
        ]);
    }
    
    /**
     * تحديث حالة المصدر
     */
    private function updateSourceStatus($sourceId, $status, $itemsFetched, $itemsImported, $itemsUpdated, $errorMessage = null) {
        $nextUpdate = $this->calculateNextUpdate($sourceId);
        
        $stmt = $this->db->prepare("
            UPDATE important_link_sources SET 
            last_update = NOW(),
            next_update = ?,
            error_log = ?,
            success_count = CASE WHEN ? = 'success' THEN success_count + 1 ELSE success_count END,
            error_count = CASE WHEN ? = 'failed' THEN error_count + 1 ELSE error_count END
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nextUpdate,
            $errorMessage,
            $status,
            $status,
            $sourceId
        ]);
    }
    
    /**
     * حساب موعد التحديث القادم
     */
    private function calculateNextUpdate($sourceId) {
        $stmt = $this->db->prepare("SELECT update_frequency FROM important_link_sources WHERE id = ?");
        $stmt->execute([$sourceId]);
        $frequency = $stmt->fetchColumn();
        
        $now = new DateTime();
        
        switch ($frequency) {
            case 'hourly':
                $now->modify('+1 hour');
                break;
            case 'daily':
                $now->modify('+1 day');
                break;
            case 'weekly':
                $now->modify('+1 week');
                break;
            case 'monthly':
                $now->modify('+1 month');
                break;
            default:
                return null;
        }
        
        return $now->format('Y-m-d H:i:s');
    }
    
    /**
     * تسجيل عملية الجلب
     */
    private function logFetch($sourceId, $fetchType, $status, $itemsFetched, $itemsImported, $itemsUpdated, $errorMessage, $executionTime) {
        $stmt = $this->db->prepare("
            INSERT INTO important_link_fetch_logs 
            (source_id, fetch_type, status, items_fetched, items_imported, items_updated, error_message, execution_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $sourceId,
            $fetchType,
            $status,
            $itemsFetched,
            $itemsImported,
            $itemsUpdated,
            $errorMessage,
            $executionTime
        ]);
    }
    
    /**
     * تطبيق mapping
     */
    private function applyMapping($data, $mapping) {
        // تطبيق mapping مخصص على البيانات
        if (isset($mapping['data_path'])) {
            $path = explode('.', $mapping['data_path']);
            foreach ($path as $key) {
                if (is_array($data) && isset($data[$key])) {
                    $data = $data[$key];
                } elseif (is_object($data) && isset($data->$key)) {
                    $data = $data->$key;
                } else {
                    $this->log("مسار البيانات غير موجود: " . $mapping['data_path']);
                    return [];
                }
            }
        }
        
        // إذا كانت البيانات مصفوفة من العناصر
        if (is_array($data) && !empty($data)) {
            // التحقق إذا كانت المصفوفة تحتوي على عناصر أو بيانات مباشرة
            $firstItem = reset($data);
            if (is_array($firstItem) || is_object($firstItem)) {
                // البيانات في الصيغة الصحيحة
                return array_values($data);
            }
        }
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * فك تشفير API key
     */
    private function decryptApiKey($encrypted) {
        // يمكن إضافة تشفير/فك تشفير هنا
        // حالياً نعيد القيمة كما هي
        return $encrypted;
    }
    
    /**
     * جلب جميع المصادر الجاهزة للتحديث
     */
    public function getSourcesReadyForUpdate() {
        $stmt = $this->db->query("
            SELECT * FROM important_link_sources 
            WHERE is_active = 1 
            AND auto_import = 1
            AND (next_update IS NULL OR next_update <= NOW())
            ORDER BY next_update ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * تحديث جميع المصادر الجاهزة
     */
    public function updateAllReadySources() {
        $sources = $this->getSourcesReadyForUpdate();
        $results = [];
        
        foreach ($sources as $source) {
            $results[$source['id']] = $this->fetchFromSource($source['id']);
        }
        
        return $results;
    }
    
    /**
     * تسجيل رسالة
     */
    private function log($message) {
        $this->logMessages[] = date('Y-m-d H:i:s') . ' - ' . $message;
        error_log("ImportantLinksFetcher: " . $message);
    }
    
    /**
     * الحصول على سجل الرسائل
     */
    public function getLogs() {
        return $this->logMessages;
    }
}

