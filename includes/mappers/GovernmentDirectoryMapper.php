<?php
/**
 * Mapper لدليل الحكومة اللبنانية
 * يعالج البيانات من TRA أو مصادر حكومية أخرى
 */

require_once __DIR__ . '/BaseMapper.php';

class GovernmentDirectoryMapper extends BaseMapper {
    
    public function map($rawData, $source) {
        $mapped = [];
        
        // إذا كانت البيانات من HTML scraping
        if (is_array($rawData) && isset($rawData[0]) && is_array($rawData[0])) {
            foreach ($rawData as $item) {
                $mapped[] = [
                    'category_id' => $source['category_id'] ?? 1, // وزارات
                    'name_ar' => $this->extractName($item),
                    'name_en' => $item['name_en'] ?? null,
                    'website' => $this->extractWebsite($item),
                    'description_ar' => $item['type'] ?? 'مؤسسة حكومية',
                    'is_government' => 1,
                    'is_emergency' => 0
                ];
            }
        }
        
        return $mapped;
    }
    
    public function save($mappedData, $source) {
        $imported = 0;
        $updated = 0;
        
        foreach ($mappedData as $item) {
            $existing = $this->findExistingLink([
                'name_ar' => $item['name_ar'],
                'website' => $item['website']
            ]);
            
            if ($existing) {
                $this->updateLink($existing['id'], $item);
                $updated++;
            } else {
                $this->insertLink($item);
                $imported++;
            }
        }
        
        return ['imported' => $imported, 'updated' => $updated];
    }
    
    private function extractName($item) {
        return $item['name_ar'] ?? $item['name'] ?? $item['title'] ?? 'غير محدد';
    }
    
    private function extractWebsite($item) {
        return $item['website'] ?? $item['url'] ?? $item['website_url'] ?? null;
    }
}

