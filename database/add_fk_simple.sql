-- إضافة Foreign Key للمصادر
-- بلدية تكريت

-- الطريقة البسيطة (إذا لم يكن موجوداً)
-- إذا ظهر خطأ "Duplicate key name"، يعني أن المفتاح موجود بالفعل

ALTER TABLE `important_link_sources` 
ADD CONSTRAINT `fk_sources_source_category` 
FOREIGN KEY (`source_category_id`) 
REFERENCES `source_categories` (`id`) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

