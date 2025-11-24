<?php
/**
 * Mapper للمستشفيات (حكومية وخاصة)
 * يعالج البيانات من وزارة الصحة أو Open Data Lebanon
 */

require_once __DIR__ . '/BaseMapper.php';

class HospitalsMapper extends BaseMapper {
    
    public function map($rawData, $source) {
        $mapped = [];
        
        // إذا كانت البيانات من Excel/CSV
        if (is_array($rawData)) {
            foreach ($rawData as $row) {
                $mapped[] = [
                    'category_id' => $source['category_id'] ?? 2, // مستشفيات حكومية
                    'name_ar' => $this->extractName($row),
                    'name_en' => $row['name_en'] ?? null,
                    'phone' => $this->extractPhone($row),
                    'phone_2' => $row['phone_2'] ?? $row['mobile'] ?? null,
                    'email' => $row['email'] ?? null,
                    'website' => $row['website'] ?? null,
                    'address_ar' => $this->extractAddress($row),
                    'location_lat' => isset($row['latitude']) ? floatval($row['latitude']) : null,
                    'location_lng' => isset($row['longitude']) ? floatval($row['longitude']) : null,
                    'description_ar' => $row['type'] ?? $row['hospital_type'] ?? 'مستشفى',
                    'is_government' => isset($row['is_government']) ? (int)$row['is_government'] : ($source['category_id'] == 2 ? 1 : 0),
                    'is_emergency' => isset($row['has_emergency']) ? (int)$row['has_emergency'] : 1,
                    'working_hours_ar' => $row['working_hours'] ?? '24/7'
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
                'phone' => $item['phone']
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
    
    private function extractName($row) {
        return $row['name_ar'] ?? $row['name'] ?? $row['hospital_name'] ?? $row['facility_name'] ?? 'غير محدد';
    }
    
    private function extractPhone($row) {
        return $row['phone'] ?? $row['telephone'] ?? $row['contact'] ?? null;
    }
    
    private function extractAddress($row) {
        $address = $row['address_ar'] ?? $row['address'] ?? '';
        if (!empty($row['district'])) {
            $address .= ($address ? '، ' : '') . $row['district'];
        }
        if (!empty($row['governorate'])) {
            $address .= ($address ? '، ' : '') . $row['governorate'];
        }
        return $address ?: null;
    }
}

