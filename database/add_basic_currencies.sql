USE tekrit_municipality;

INSERT INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_iqd, is_active) VALUES 
('IQD', 'الدينار العراقي', 'د.ع', 1.0000, 1),
('USD', 'الدولار الأمريكي', '$', 1320.0000, 1),
('EUR', 'اليورو', '€', 1450.0000, 1)
ON DUPLICATE KEY UPDATE 
currency_symbol = VALUES(currency_symbol), 
exchange_rate_to_iqd = VALUES(exchange_rate_to_iqd);

SELECT 'تم إضافة العملات الأساسية' as message; 