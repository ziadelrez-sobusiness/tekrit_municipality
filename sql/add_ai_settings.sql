-- إضافة إعدادات الذكاء الاصطناعي إلى جدول system_settings
-- تاريخ الإنشاء: 2025-11-24

-- 1. نوع مزود الذكاء الاصطناعي
INSERT INTO system_settings (setting_key, setting_value, setting_description, created_at, updated_at)
VALUES ('ai_provider', 'openai', 'نوع مزود خدمة الذكاء الاصطناعي (openai, gemini, claude, custom)', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 2. مفتاح API للذكاء الاصطناعي (سيتم تشفيره في الكود)
INSERT INTO system_settings (setting_key, setting_value, setting_description, created_at, updated_at)
VALUES ('ai_api_key', '', 'مفتاح API للذكاء الاصطناعي (مشفر)', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 3. نموذج الذكاء الاصطناعي المستخدم
INSERT INTO system_settings (setting_key, setting_value, setting_description, created_at, updated_at)
VALUES ('ai_model', 'gpt-4', 'نموذج الذكاء الاصطناعي المستخدم (gpt-4, gpt-3.5-turbo, gemini-pro, claude-3, etc)', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 4. تفعيل/تعطيل الذكاء الاصطناعي
INSERT INTO system_settings (setting_key, setting_value, setting_description, created_at, updated_at)
VALUES ('ai_enabled', '0', 'تفعيل الذكاء الاصطناعي (1 = مفعل، 0 = معطل)', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 5. نوع خدمة إنشاء الصور
INSERT INTO system_settings (setting_key, setting_value, setting_description, created_at, updated_at)
VALUES ('ai_image_provider', 'dall-e', 'نوع خدمة إنشاء الصور (dall-e, midjourney, stable-diffusion, auto)', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 6. درجة الحرارة للذكاء الاصطناعي (creativity level)
INSERT INTO system_settings (setting_key, setting_value, setting_description, created_at, updated_at)
VALUES ('ai_temperature', '0.7', 'درجة الإبداع للذكاء الاصطناعي (0.0 - 1.0)', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 7. الحد الأقصى للكلمات في الرد
INSERT INTO system_settings (setting_key, setting_value, setting_description, created_at, updated_at)
VALUES ('ai_max_tokens', '2000', 'الحد الأقصى لعدد الكلمات في رد الذكاء الاصطناعي', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();
