-- إضافة Foreign Key مع التحقق من وجوده
-- بلدية تكريت

-- التحقق من وجود المفتاح وإضافته إذا لم يكن موجوداً
SET @dbname = DATABASE();
SET @tablename = 'important_link_sources';
SET @constraintname = 'fk_sources_source_category';

-- التحقق من وجود العمود
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND COLUMN_NAME = 'source_category_id'
);

-- التحقق من وجود المفتاح
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND CONSTRAINT_NAME = @constraintname
);

-- بناء SQL
SET @sql = IF(
    @column_exists > 0 AND @constraint_exists = 0,
    CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, 
           ' FOREIGN KEY (source_category_id) REFERENCES source_categories(id) ',
           'ON DELETE SET NULL ON UPDATE CASCADE;'),
    'SELECT "Foreign key already exists or column missing" AS result;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 
    CASE 
        WHEN @constraint_exists > 0 THEN 'Foreign key already exists'
        WHEN @column_exists = 0 THEN 'Column source_category_id does not exist'
        ELSE 'Foreign key added successfully'
    END AS result;

