-- إصلاح جدول العملات بإضافة الأعمدة المفقودة
USE tekrit_municipality;

-- إضافة الأعمدة المفقودة
ALTER TABLE currencies 
ADD COLUMN currency_symbol VARCHAR(10) NOT NULL DEFAULT 'IQD' AFTER currency_name,
ADD COLUMN exchange_rate DECIMAL(15,6) NOT NULL DEFAULT 1.000000 AFTER currency_symbol,
ADD COLUMN is_default BOOLEAN NOT NULL DEFAULT FALSE AFTER is_active;

-- تحديث البيانات الموجودة
UPDATE currencies SET currency_symbol = 'IQD', exchange_rate = 1.000000, is_default = TRUE WHERE currency_code = 'IQD';
UPDATE currencies SET currency_symbol = 'USD', exchange_rate = 1320.000000 WHERE currency_code = 'USD';
UPDATE currencies SET currency_symbol = 'EUR', exchange_rate = 1450.000000 WHERE currency_code = 'EUR';
UPDATE currencies SET currency_symbol = 'GBP', exchange_rate = 1680.000000 WHERE currency_code = 'GBP';
UPDATE currencies SET currency_symbol = 'SAR', exchange_rate = 352.000000 WHERE currency_code = 'SAR';
UPDATE currencies SET currency_symbol = 'AED', exchange_rate = 359.000000 WHERE currency_code = 'AED';
UPDATE currencies SET currency_symbol = 'KWD', exchange_rate = 4315.000000 WHERE currency_code = 'KWD';
UPDATE currencies SET currency_symbol = 'QAR', exchange_rate = 362.000000 WHERE currency_code = 'QAR';
UPDATE currencies SET currency_symbol = 'BHD', exchange_rate = 3500.000000 WHERE currency_code = 'BHD';
UPDATE currencies SET currency_symbol = 'OMR', exchange_rate = 3430.000000 WHERE currency_code = 'OMR';
UPDATE currencies SET currency_symbol = 'JOD', exchange_rate = 1862.000000 WHERE currency_code = 'JOD';
UPDATE currencies SET currency_symbol = 'LBP', exchange_rate = 0.875000 WHERE currency_code = 'LBP';
UPDATE currencies SET currency_symbol = 'EGP', exchange_rate = 42.500000 WHERE currency_code = 'EGP';
UPDATE currencies SET currency_symbol = 'TRY', exchange_rate = 39.800000 WHERE currency_code = 'TRY';

-- إنشاء الفهارس
CREATE INDEX idx_currencies_default ON currencies(is_default);
CREATE INDEX idx_currencies_active ON currencies(is_active);

-- إنشاء view للعملات النشطة
CREATE OR REPLACE VIEW active_currencies AS
SELECT * FROM currencies WHERE is_active = 1
ORDER BY is_default DESC, currency_name ASC;

SELECT 'تم إصلاح جدول العملات بنجاح!' as message; 