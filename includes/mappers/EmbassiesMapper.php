<?php
/**
 * Mapper للسفارات
 * يعالج البيانات من AUB أو مصادر أخرى
 */

require_once __DIR__ . '/BaseMapper.php';

class EmbassiesMapper extends BaseMapper {
    
    public function map($rawData, $source) {
        $mapped = [];
        
        if (is_array($rawData)) {
            foreach ($rawData as $item) {
                $mapped[] = [
                    'category_id' => $source['category_id'] ?? 6, // سفارات
                    'name_ar' => $this->extractName($item),
                    'name_en' => $item['name_en'] ?? $item['country'] ?? null,
                    'phone' => $item['phone'] ?? $item['telephone'] ?? null,
                    'phone_2' => $item['phone_2'] ?? null,
                    'email' => $item['email'] ?? null,
                    'website' => $item['website'] ?? $item['url'] ?? null,
                    'address_ar' => $this->extractAddress($item),
                    'location_lat' => isset($item['latitude']) ? floatval($item['latitude']) : null,
                    'location_lng' => isset($item['longitude']) ? floatval($item['longitude']) : null,
                    'description_ar' => 'سفارة ' . ($item['country_ar'] ?? $item['country'] ?? ''),
                    'is_government' => 0,
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
        $name = $item['name_ar'] ?? $item['name'] ?? '';
        if (empty($name) && !empty($item['country'])) {
            $name = 'سفارة ' . $item['country'];
        }
        return $name ?: 'غير محدد';
    }
    
    private function extractAddress($item) {
        $parts = [];
        if (!empty($item['address_ar'])) $parts[] = $item['address_ar'];
        if (!empty($item['address'])) $parts[] = $item['address'];
        if (!empty($item['city'])) $parts[] = $item['city'];
        if (!empty($item['area'])) $parts[] = $item['area'];
        
        return !empty($parts) ? implode('، ', $parts) : null;
    }
}

