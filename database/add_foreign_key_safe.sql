-- إضافة Foreign Key بشكل آمن (مع التحقق من وجوده)
-- بلدية تكريت

-- التحقق من وجود المفتاح وإضافته إذا لم يكن موجوداً
SET @dbname = DATABASE();
SET @tablename = 'important_link_sources';
SET @constraintname = 'fk_sources_source_category';

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
     WHERE CONSTRAINT_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND CONSTRAINT_NAME = @constraintname) > 0,
    'SELECT "Foreign key already exists" AS result;',
    CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, 
           ' FOREIGN KEY (source_category_id) REFERENCES source_categories(id) ON DELETE SET NULL ON UPDATE CASCADE;')
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'تم التحقق من Foreign Key' AS result;

