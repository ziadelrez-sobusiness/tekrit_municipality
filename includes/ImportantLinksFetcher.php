<?php
/**
 * نظام جلب وتحديث روابط مهمة تلقائياً
 * بلدية تكريت
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mappers/BaseMapper.php';
require_once __DIR__ . '/mappers/GovernmentDirectoryMapper.php';
require_once __DIR__ . '/mappers/HospitalsMapper.php';
require_once __DIR__ . '/mappers/EmbassiesMapper.php';

class ImportantLinksFetcher {
    private $db;
    private $logMessages = [];
    private $mappers = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->db->exec("SET NAMES utf8mb4");
        
        // تهيئة Mappers
        $this->mappers = [
            'GOV_DIRECTORY' => new GovernmentDirectoryMapper($db),
            'PUBLIC_HOSPITALS' => new HospitalsMapper($db),
            'PRIVATE_HOSPITALS' => new HospitalsMapper($db),
            'EMBASSIES' => new EmbassiesMapper($db)
        ];
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
            
            // جلب البيانات حسب نوع المصدر وطريقة الجلب
            $fetchMethod = $source['fetch_method'] ?? $source['source_type'];
            
            switch ($fetchMethod) {
                case 'api':
                    $data = $this->fetchFromAPI($source);
                    break;
                case 'html_scraper':
                case 'scraping':
                    $data = $this->fetchFromScraping($source);
                    break;
                case 'file_import':
                    $data = $this->fetchFromFile($source);
                    break;
                default:
                    throw new Exception("طريقة الجلب غير مدعومة: " . $fetchMethod);
            }
            
            $itemsFetched = count($data);
            
            // استخدام Mapper إذا كان محدداً
            if (!empty($source['source_category_id'])) {
                $categoryStmt = $this->db->prepare("SELECT code FROM source_categories WHERE id = ?");
                $categoryStmt->execute([$source['source_category_id']]);
                $categoryCode = $categoryStmt->fetchColumn();
                
                if ($categoryCode && isset($this->mappers[$categoryCode])) {
                    $mapper = $this->mappers[$categoryCode];
                    $mappedData = $mapper->map($data, $source);
                    $result = $mapper->save($mappedData, $source);
                    $itemsImported = $result['imported'];
                    $itemsUpdated = $result['updated'];
                } else {
                    // استخدام الطريقة القديمة
                    if (!empty($data)) {
                        $result = $this->importData($data, $source);
                        $itemsImported = $result['imported'];
                        $itemsUpdated = $result['updated'];
                    }
                }
            } else {
                // استخدام الطريقة القديمة
                if (!empty($data)) {
                    $result = $this->importData($data, $source);
                    $itemsImported = $result['imported'];
                    $itemsUpdated = $result['updated'];
                    $itemsSkipped = $result['skipped'] ?? 0;
                }
            }
            
            // تحديث معلومات المصدر
            $this->updateSourceStatus($sourceId, 'success', $itemsFetched, $itemsImported, $itemsUpdated);
            
            // حساب وقت التنفيذ
            $executionTime = round(microtime(true) - $startTime, 2);
            
            // تسجيل العملية
            $this->logFetch($sourceId, 'auto', $status, $itemsFetched, $itemsImported, $itemsUpdated, $itemsSkipped ?? 0, null, $executionTime);
            
            $logMessage = "تم جلب " . $itemsFetched . " عنصر، استيراد " . $itemsImported . "، تحديث " . $itemsUpdated;
            if (isset($itemsSkipped) && $itemsSkipped > 0) {
                $logMessage .= "، متخطى " . $itemsSkipped;
            }
            $this->log($logMessage);
            
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
    public function fetchFromScraping($source) {
        $url = $source['scraping_url'] ?? $source['api_url'];
        if (empty($url)) {
            throw new Exception("رابط scraping غير محدد");
        }
        
        $this->log("بدء scraping من: " . $url);
        
        // استخدام cURL للحصول على HTML
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("خطأ في الاتصال: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("خطأ HTTP " . $httpCode);
        }
        
        if (empty($html)) {
            throw new Exception("الصفحة فارغة");
        }
        
        $this->log("تم جلب " . strlen($html) . " بايت من HTML");
        
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $data = [];
        
        // استخدام selector إذا كان محدداً
        if (!empty($source['scraping_selector'])) {
            $selectors = json_decode($source['scraping_selector'], true);
            
            if ($selectors) {
                // جلب العناصر
                $itemSelector = $selectors['item_selector'] ?? '//tr | //div[@class="item"] | //li';
                $items = $xpath->query($itemSelector);
                
                $this->log("تم العثور على " . $items->length . " عنصر");
                
                foreach ($items as $item) {
                    $itemData = [];
                    
                    // استخراج البيانات حسب selectors
                    foreach ($selectors['fields'] ?? [] as $field => $selector) {
                        $nodes = $xpath->query($selector, $item);
                        if ($nodes->length > 0) {
                            $value = trim($nodes->item(0)->textContent);
                            // استخراج الروابط
                            if ($field == 'website' || $field == 'url') {
                                $href = $xpath->query('.//a/@href', $item);
                                if ($href->length > 0) {
                                    $value = $href->item(0)->nodeValue;
                                    if (!preg_match('/^https?:\/\//', $value)) {
                                        $value = $this->resolveUrl($url, $value);
                                    }
                                }
                            }
                            $itemData[$field] = $value;
                        }
                    }
                    
                    if (!empty($itemData)) {
                        $data[] = $itemData;
                    }
                }
            }
        } else {
            // محاولة استخراج تلقائي من الجداول
            $tables = $xpath->query('//table');
            foreach ($tables as $table) {
                $rows = $xpath->query('.//tr', $table);
                $headers = [];
                $firstRow = true;
                
                foreach ($rows as $row) {
                    $cells = $xpath->query('.//td | .//th', $row);
                    $rowData = [];
                    
                    foreach ($cells as $cell) {
                        $value = trim($cell->textContent);
                        if ($firstRow) {
                            $headers[] = $value;
                        } else {
                            $rowData[] = $value;
                        }
                    }
                    
                    if ($firstRow && !empty($headers)) {
                        $firstRow = false;
                    } elseif (!empty($rowData) && count($rowData) === count($headers)) {
                        $data[] = array_combine($headers, $rowData);
                    }
                }
            }
        }
        
        $this->log("تم استخراج " . count($data) . " عنصر من HTML");
        
        return $data;
    }
    
    /**
     * حل URL نسبي إلى مطلق
     */
    private function resolveUrl($baseUrl, $relativeUrl) {
        $base = parse_url($baseUrl);
        $relative = parse_url($relativeUrl);
        
        if (isset($relative['scheme'])) {
            return $relativeUrl;
        }
        
        $url = $base['scheme'] . '://' . $base['host'];
        if (isset($base['port'])) {
            $url .= ':' . $base['port'];
        }
        
        if (isset($relative['path'])) {
            if (strpos($relative['path'], '/') === 0) {
                $url .= $relative['path'];
            } else {
                $url .= dirname($base['path']) . '/' . $relative['path'];
            }
        } else {
            $url .= $base['path'];
        }
        
        return $url;
    }
    
    /**
     * جلب البيانات من ملف (Excel, CSV, PDF)
     */
    public function fetchFromFile($source) {
        $fileUrl = $source['file_url'] ?? $source['api_url'] ?? null;
        $fileFormat = $source['file_format'] ?? 'xlsx';
        
        if (empty($fileUrl)) {
            throw new Exception("رابط الملف غير محدد");
        }
        
        $this->log("تحميل الملف من: " . $fileUrl);
        
        // تحميل الملف
        $tempFile = tempnam(sys_get_temp_dir(), 'links_import_');
        $fileContent = @file_get_contents($fileUrl);
        
        if ($fileContent === false) {
            throw new Exception("فشل في تحميل الملف من: " . $fileUrl);
        }
        
        file_put_contents($tempFile, $fileContent);
        $this->log("تم تحميل " . strlen($fileContent) . " بايت");
        
        try {
            // معالجة الملف حسب النوع
            switch ($fileFormat) {
                case 'csv':
                    $data = $this->parseCSV($tempFile);
                    break;
                case 'xlsx':
                case 'xls':
                    $data = $this->parseExcel($tempFile);
                    break;
                case 'pdf':
                    $data = $this->parsePDF($tempFile);
                    break;
                default:
                    throw new Exception("نوع الملف غير مدعوم: " . $fileFormat);
            }
            
            return $data;
        } finally {
            // حذف الملف المؤقت
            @unlink($tempFile);
        }
    }
    
    /**
     * تحليل ملف CSV
     */
    private function parseCSV($filePath) {
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            throw new Exception("فشل في فتح ملف CSV");
        }
        
        // قراءة header
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new Exception("ملف CSV فارغ أو تالف");
        }
        
        // قراءة البيانات
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
        
        fclose($handle);
        $this->log("تم تحليل " . count($data) . " صف من CSV");
        
        return $data;
    }
    
    /**
     * تحليل ملف Excel
     */
    private function parseExcel($filePath) {
        // استخدام PhpSpreadsheet إذا كان متاحاً
        if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = [];
            
            $headers = [];
            $firstRow = true;
            
            foreach ($worksheet->getRowIterator() as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if ($firstRow) {
                        $headers[] = $value;
                    } else {
                        $rowData[] = $value;
                    }
                }
                
                if ($firstRow) {
                    $firstRow = false;
                } else {
                    if (count($rowData) === count($headers)) {
                        $data[] = array_combine($headers, $rowData);
                    }
                }
            }
            
            $this->log("تم تحليل " . count($data) . " صف من Excel");
            return $data;
        } else {
            // Fallback: محاولة قراءة كـ CSV
            $this->log("PhpSpreadsheet غير متاح، محاولة قراءة كـ CSV");
            return $this->parseCSV($filePath);
        }
    }
    
    /**
     * تحليل ملف PDF (بسيط - يحتاج مكتبات متقدمة)
     */
    private function parsePDF($filePath) {
        // يمكن استخدام مكتبة مثل FPDI أو Smalot\PdfParser
        throw new Exception("تحليل PDF يحتاج إلى مكتبات إضافية. يرجى تحويل PDF إلى CSV أو Excel أولاً.");
    }
    
    /**
     * جلب البيانات من CSV (للتوافق مع الكود القديم)
     */
    public function fetchFromCSV($source) {
        return $this->fetchFromFile($source);
    }
    
    /**
     * استيراد البيانات إلى قاعدة البيانات
     */
    private function importData($data, $source) {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        $this->log("بدء استيراد " . count($data) . " عنصر");
        
        foreach ($data as $index => $item) {
            try {
                // تخطي العناصر الفارغة
                if (empty($item) || (!is_array($item))) {
                    $skipped++;
                    continue;
                }
                
                // تطبيق mapping
                $mappedItem = $this->mapItemData($item, $source);
                
                // التحقق من وجود اسم (مطلوب)
                if (empty($mappedItem['name_ar']) || $mappedItem['name_ar'] === 'غير محدد') {
                    $this->log("⏭️ تخطي عنصر #" . ($index + 1) . " - لا يوجد اسم. الحقول المتاحة: " . implode(', ', array_keys($item)));
                    $skipped++;
                    continue;
                }
                
                // التحقق من وجود category_id
                if (empty($mappedItem['category_id'])) {
                    $this->log("⚠️ تحذير: عنصر بدون فئة. سيتم استخدام فئة المصدر الافتراضية.");
                    $mappedItem['category_id'] = $source['category_id'] ?? null;
                    if (empty($mappedItem['category_id'])) {
                        $this->log("⏭️ تخطي عنصر #" . ($index + 1) . " - لا توجد فئة متاحة");
                        $skipped++;
                        continue;
                    }
                }
                
                // البحث عن رابط موجود (بالاسم أو الهاتف)
                $existing = $this->findExistingLink($mappedItem);
                
                if ($existing) {
                    // تحديث الرابط الموجود
                    $this->updateLink($existing['id'], $mappedItem);
                    $updated++;
                    if ($updated % 100 == 0) {
                        $this->log("تم تحديث " . $updated . " رابط حتى الآن...");
                    }
                } else {
                    // إضافة رابط جديد
                    $this->insertLink($mappedItem);
                    $imported++;
                    if ($imported % 100 == 0) {
                        $this->log("تم استيراد " . $imported . " رابط حتى الآن...");
                    }
                }
            } catch (Exception $e) {
                $this->log("❌ خطأ في استيراد عنصر #" . ($index + 1) . ": " . $e->getMessage());
                $skipped++;
                continue;
            }
        }
        
        $this->log("✅ انتهى الاستيراد: " . $imported . " مستورد، " . $updated . " محدث، " . $skipped . " متخطى");
        
        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }
    
    /**
     * ربط بيانات العنصر مع الحقول
     */
    private function mapItemData($item, $source) {
        // البحث عن اسم في أي حقل محتمل
        $name_ar = null;
        $possibleNameFields = ['name_ar', 'name', 'اسم', 'الاسم', 'hospital_name', 'facility_name', 'institution_name', 'ministry_name', 'embassy_name'];
        foreach ($possibleNameFields as $field) {
            if (!empty($item[$field])) {
                $name_ar = trim($item[$field]);
                break;
            }
        }
        
        // البحث عن هاتف في أي حقل محتمل
        $phone = null;
        $possiblePhoneFields = ['phone', 'telephone', 'tel', 'phone_number', 'هاتف', 'رقم الهاتف', 'contact_phone'];
        foreach ($possiblePhoneFields as $field) {
            if (!empty($item[$field])) {
                $phone = trim($item[$field]);
                break;
            }
        }
        
        // البحث عن عنوان في أي حقل محتمل
        $address_ar = null;
        $possibleAddressFields = ['address_ar', 'address', 'عنوان', 'location', 'موقع'];
        foreach ($possibleAddressFields as $field) {
            if (!empty($item[$field])) {
                $address_ar = trim($item[$field]);
                break;
            }
        }
        
        // البحث عن موقع إلكتروني
        $website = null;
        $possibleWebsiteFields = ['website', 'url', 'website_url', 'link', 'موقع', 'رابط'];
        foreach ($possibleWebsiteFields as $field) {
            if (!empty($item[$field])) {
                $website = trim($item[$field]);
                break;
            }
        }
        
        $mapped = [
            'category_id' => $source['category_id'] ?? null,
            'name_ar' => $name_ar ?: ($item['name_ar'] ?? $item['name'] ?? 'غير محدد'),
            'name_en' => $item['name_en'] ?? $item['name_en'] ?? null,
            'description_ar' => $item['description_ar'] ?? $item['description'] ?? null,
            'phone' => $phone ?: ($item['phone'] ?? $item['telephone'] ?? null),
            'phone_2' => $item['phone_2'] ?? null,
            'email' => $item['email'] ?? $item['e_mail'] ?? $item['email_address'] ?? null,
            'website' => $website ?: ($item['website'] ?? $item['url'] ?? null),
            'address_ar' => $address_ar ?: ($item['address_ar'] ?? $item['address'] ?? null),
            'location_lat' => isset($item['latitude']) ? floatval($item['latitude']) : (isset($item['lat']) ? floatval($item['lat']) : null),
            'location_lng' => isset($item['longitude']) ? floatval($item['longitude']) : (isset($item['lng']) ? floatval($item['lng']) : (isset($item['lon']) ? floatval($item['lon']) : null)),
            'working_hours_ar' => $item['working_hours'] ?? $item['hours'] ?? null,
            'is_government' => isset($item['is_government']) ? (int)$item['is_government'] : 0,
            'is_emergency' => isset($item['is_emergency']) ? (int)$item['is_emergency'] : 0,
            'display_order' => 0
        ];
        
        // تطبيق mapping مخصص إذا كان موجوداً
        if (!empty($source['mapping_config'])) {
            $mapping = json_decode($source['mapping_config'], true);
            if ($mapping && is_array($mapping)) {
                foreach ($mapping as $dbField => $sourceField) {
                    if (isset($item[$sourceField]) && !empty($item[$sourceField])) {
                        $mapped[$dbField] = $item[$sourceField];
                    }
                }
            }
        }
        
        // تسجيل تحذير إذا كان الاسم فارغاً
        if (empty($mapped['name_ar']) || $mapped['name_ar'] === 'غير محدد') {
            $this->log("⚠️ تحذير: عنصر بدون اسم. البيانات: " . json_encode(array_keys($item), JSON_UNESCAPED_UNICODE));
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
    private function logFetch($sourceId, $fetchType, $status, $itemsFetched, $itemsImported, $itemsUpdated, $itemsSkipped = 0, $errorMessage = null, $executionTime = null) {
        // إضافة ملاحظة عن العناصر المتخطاة في error_message إذا لم يكن هناك خطأ
        $finalErrorMessage = $errorMessage;
        if ($itemsSkipped > 0 && empty($errorMessage)) {
            $finalErrorMessage = "ملاحظة: تم تخطي " . $itemsSkipped . " عنصر (بدون اسم أو فئة صحيحة)";
        }
        
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
            $finalErrorMessage,
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

