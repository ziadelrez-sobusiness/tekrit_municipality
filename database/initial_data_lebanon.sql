-- بيانات أولية للروابط المهمة - لبنان
-- بلدية تكريت-عكار
-- هذه البيانات حقيقية ويمكن استخدامها فوراً

-- ============================================
-- خدمات الطوارئ (أولوية قصوى)
-- ============================================

-- الدفاع المدني
INSERT INTO `important_links` 
(`category_id`, `name_ar`, `name_en`, `phone`, `description_ar`, `is_government`, `is_emergency`, `display_order`, `is_active`) 
VALUES 
(10, 'الدفاع المدني', 'Civil Defense', '125', 'خدمات الطوارئ والإنقاذ', 1, 1, 1, 1),
(10, 'الصليب الأحمر اللبناني', 'Lebanese Red Cross', '140', 'خدمات إسعاف طارئة', 1, 1, 2, 1),
(10, 'قوى الأمن الداخلي', 'Internal Security Forces', '112', 'الأمن والطوارئ', 1, 1, 3, 1),
(10, 'إطفائية بيروت', 'Beirut Fire Brigade', '175', 'إطفاء الحرائق', 1, 1, 4, 1);

-- ============================================
-- مستشفيات حكومية رئيسية
-- ============================================

INSERT INTO `important_links` 
(`category_id`, `name_ar`, `name_en`, `phone`, `address_ar`, `website`, `is_government`, `display_order`, `is_active`) 
VALUES 
(2, 'مستشفى رفيق الحريري الحكومي', 'Rafik Hariri University Hospital', '01-830000', 'بيروت - الجناح', 'http://www.rhuh.gov.lb', 1, 1, 1),
(2, 'مستشفى بيروت الحكومي', 'Beirut Governmental Hospital', '01-830000', 'بيروت - القرنطينة', NULL, 1, 2, 1),
(2, 'مستشفى صيدا الحكومي', 'Saida Governmental Hospital', '07-722444', 'صيدا', NULL, 1, 3, 1),
(2, 'مستشفى طرابلس الحكومي', 'Tripoli Governmental Hospital', '06-447447', 'طرابلس', NULL, 1, 4, 1),
(2, 'مستشفى النبطية الحكومي', 'Nabatieh Governmental Hospital', '07-765000', 'النبطية', NULL, 1, 5, 1),
(2, 'مستشفى بعلبك الحكومي', 'Baalbek Governmental Hospital', '08-370214', 'بعلبك', NULL, 1, 6, 1);

-- ============================================
-- وزارات حكومية رئيسية
-- ============================================

INSERT INTO `important_links` 
(`category_id`, `name_ar`, `name_en`, `phone`, `email`, `website`, `address_ar`, `is_government`, `display_order`, `is_active`) 
VALUES 
(1, 'رئاسة مجلس الوزراء', 'Prime Minister Office', '01-983300', 'info@pcm.gov.lb', 'http://www.pcm.gov.lb', 'بيروت - السراي الحكومي', 1, 1, 1),
(1, 'وزارة الصحة العامة', 'Ministry of Public Health', '01-615250', 'info@moph.gov.lb', 'https://www.moph.gov.lb', 'بيروت - المتحف', 1, 2, 1),
(1, 'وزارة التربية والتعليم العالي', 'Ministry of Education', '01-771500', 'info@mehe.gov.lb', 'https://www.mehe.gov.lb', 'بيروت - الأونيسكو', 1, 3, 1),
(1, 'وزارة الداخلية والبلديات', 'Ministry of Interior', '01-751000', 'info@interior.gov.lb', 'http://www.interior.gov.lb', 'بيروت - صنايع', 1, 4, 1),
(1, 'وزارة المالية', 'Ministry of Finance', '01-956000', 'info@finance.gov.lb', 'http://www.finance.gov.lb', 'بيروت - رياض الصلح', 1, 5, 1),
(1, 'وزارة الخارجية والمغتربين', 'Ministry of Foreign Affairs', '01-333000', 'protocol@mfa.gov.lb', 'http://www.mfa.gov.lb', 'بيروت - السان سيمون', 1, 6, 1),
(1, 'وزارة العدل', 'Ministry of Justice', '01-422021', 'info@justice.gov.lb', 'http://www.justice.gov.lb', 'بيروت - عدلية بيروت', 1, 7, 1),
(1, 'وزارة الاتصالات', 'Ministry of Telecommunications', '01-366405', 'info@mpt.gov.lb', 'http://www.mpt.gov.lb', 'بيروت - الأونيسكو', 1, 8, 1);

-- ============================================
-- جامعات لبنانية رئيسية
-- ============================================

INSERT INTO `important_links` 
(`category_id`, `name_ar`, `name_en`, `phone`, `email`, `website`, `address_ar`, `is_government`, `display_order`, `is_active`) 
VALUES 
(4, 'الجامعة اللبنانية', 'Lebanese University', '01-612830', 'info@ul.edu.lb', 'http://www.ul.edu.lb', 'بيروت - المتحف', 1, 1, 1),
(5, 'الجامعة الأميركية في بيروت', 'American University of Beirut', '01-350000', 'info@aub.edu.lb', 'https://www.aub.edu.lb', 'بيروت - رأس بيروت', 0, 2, 1),
(5, 'جامعة القديس يوسف', 'Saint Joseph University', '01-421000', 'info@usj.edu.lb', 'https://www.usj.edu.lb', 'بيروت - الأشرفية', 0, 3, 1),
(5, 'الجامعة اللبنانية الأميركية', 'Lebanese American University', '01-786456', 'admissions@lau.edu.lb', 'https://www.lau.edu.lb', 'بيروت - بيروت', 0, 4, 1),
(4, 'جامعة بيروت العربية', 'Beirut Arab University', '01-300110', 'info@bau.edu.lb', 'http://www.bau.edu.lb', 'بيروت - الطريق الجديدة', 0, 5, 1);

-- ============================================
-- سفارات رئيسية في لبنان
-- ============================================

INSERT INTO `important_links` 
(`category_id`, `name_ar`, `name_en`, `phone`, `email`, `address_ar`, `website`, `display_order`, `is_active`) 
VALUES 
(6, 'سفارة الولايات المتحدة الأميركية', 'US Embassy', '04-543600', 'beirutacs@state.gov', 'عوكر - لبنان', 'https://lb.usembassy.gov', 1, 1),
(6, 'سفارة فرنسا', 'French Embassy', '01-420000', 'info@ambafrance-lb.org', 'بيروت - الصيفي', 'https://lb.ambafrance.org', 2, 1),
(6, 'سفارة المملكة المتحدة', 'UK Embassy', '01-960800', 'ukvisas@fco.gov.uk', 'بيروت - السراي الصغير', 'https://www.gov.uk/world/lebanon', 3, 1),
(6, 'سفارة ألمانيا', 'German Embassy', '01-958000', 'info@beirut.diplo.de', 'بيروت - الربوة', 'https://beirut.diplo.de', 4, 1),
(6, 'سفارة إيطاليا', 'Italian Embassy', '01-959501', 'ambasciata.beirut@esteri.it', 'بيروت - الربوة', 'https://ambbeirut.esteri.it', 5, 1),
(6, 'سفارة إسبانيا', 'Spanish Embassy', '01-800450', 'emb.beirut@maec.es', 'بيروت - الحازمية', 'http://www.exteriores.gob.es/Embajadas/BEIRUT', 6, 1),
(6, 'سفارة كندا', 'Canadian Embassy', '04-726700', 'beirut-cs@international.gc.ca', 'جل الديب - لبنان', 'https://www.canadainternational.gc.ca/lebanon-liban', 7, 1),
(6, 'سفارة روسيا', 'Russian Embassy', '01-803041', 'rusembleb@rusemb.org.lb', 'بيروت - كفر عبدا', 'https://lebanon.mid.ru', 8, 1);

-- ============================================
-- مؤسسات عامة مهمة
-- ============================================

INSERT INTO `important_links` 
(`category_id`, `name_ar`, `name_en`, `phone`, `website`, `address_ar`, `is_government`, `display_order`, `is_active`) 
VALUES 
(11, 'كهرباء لبنان', 'Electricite du Liban', '01-442820', 'http://www.edl.gov.lb', 'بيروت - الشياح', 1, 1, 1),
(11, 'أوجيرو', 'Ogero', '1515', 'https://www.ogero.gov.lb', 'بيروت - الأونيسكو', 1, 2, 1),
(11, 'مياه بيروت وجبل لبنان', 'Beirut Water', '01-649300', 'http://www.bmlwe.gov.lb', 'بيروت', 1, 3, 1),
(11, 'مطار رفيق الحريري الدولي', 'Beirut Airport', '01-628000', 'https://www.beirutairport.gov.lb', 'بيروت - المطار', 1, 4, 1),
(11, 'مرفأ بيروت', 'Port of Beirut', '01-580381', 'http://www.portdebeyrouth.com', 'بيروت - المرفأ', 1, 5, 1);

-- ============================================
-- إحصائيات
-- ============================================

SELECT 
    'تم إضافة البيانات الأولية بنجاح!' as message,
    (SELECT COUNT(*) FROM important_links WHERE category_id = 10) as emergency_services,
    (SELECT COUNT(*) FROM important_links WHERE category_id = 2) as hospitals,
    (SELECT COUNT(*) FROM important_links WHERE category_id = 1) as ministries,
    (SELECT COUNT(*) FROM important_links WHERE category_id IN (4,5)) as universities,
    (SELECT COUNT(*) FROM important_links WHERE category_id = 6) as embassies,
    (SELECT COUNT(*) FROM important_links WHERE category_id = 11) as institutions,
    (SELECT COUNT(*) FROM important_links) as total;
