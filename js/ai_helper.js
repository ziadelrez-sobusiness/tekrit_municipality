/**
 * AI Helper Functions - دوال مساعدة للذكاء الاصطناعي
 * يمكن استخدامها في أي صفحة
 */

class AIHelper {
    constructor() {
        this.apiUrl = '../api/ai_content_generate.php';
        this.isLoading = false;
    }

    /**
     * طلب عام للذكاء الاصطناعي
     */
    async request(action, data = {}) {
        if (this.isLoading) {
            throw new Error('يوجد طلب قيد المعالجة، يرجى الانتظار');
        }

        this.isLoading = true;

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    ...data
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'حدث خطأ غير متوقع');
            }

            return result;
        } catch (error) {
            console.error('AI Error:', error);
            throw error;
        } finally {
            this.isLoading = false;
        }
    }

    /**
     * إنشاء وصف مشروع
     */
    async generateProjectDescription(title, keywords = '', budget = '') {
        return await this.request('generate_project_description', {
            title,
            keywords,
            budget
        });
    }

    /**
     * إنشاء مقال خبري
     */
    async generateNewsArticle(title, summary = '', tone = 'formal') {
        return await this.request('generate_news_article', {
            title,
            summary,
            tone
        });
    }

    /**
     * إنشاء صورة للخبر
     */
    async generateNewsImage(title, description = '') {
        return await this.request('generate_news_image', {
            title,
            description
        });
    }

    /**
     * إنشاء رد على طلب أو شكوى
     */
    async generateResponse(requestContent, requestType = 'general', context = '') {
        return await this.request('generate_response', {
            request_content: requestContent,
            request_type: requestType,
            context
        });
    }

    /**
     * تحسين نص
     */
    async improveText(text, improvementType = 'grammar') {
        return await this.request('improve_text', {
            text,
            improvement_type: improvementType
        });
    }

    /**
     * توسيع نص
     */
    async expandText(text, context = '') {
        return await this.request('expand_text', {
            text,
            context
        });
    }

    /**
     * إضافة زر AI لحقل نصي
     */
    addAIButtonToField(fieldId, options = {}) {
        const field = document.getElementById(fieldId);
        if (!field) {
            console.error('Field not found:', fieldId);
            return;
        }

        const container = field.parentElement;
        container.style.position = 'relative';

        // إنشاء زر AI
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ai-assist-btn';
        button.innerHTML = '🤖 مساعدة AI';
        button.style.cssText = `
            position: absolute;
            left: 10px;
            top: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
            transition: all 0.3s;
        `;

        button.onmouseover = () => {
            button.style.transform = 'scale(1.05)';
        };

        button.onmouseout = () => {
            button.style.transform = 'scale(1)';
        };

        button.onclick = async () => {
            if (options.onClick) {
                options.onClick(field, this);
            } else {
                this.showAIMenu(field, options);
            }
        };

        container.appendChild(button);
    }

    /**
     * عرض قائمة خيارات AI
     */
    showAIMenu(field, options = {}) {
        const menu = document.createElement('div');
        menu.className = 'ai-menu';
        menu.style.cssText = `
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 8px;
            z-index: 1000;
            min-width: 200px;
        `;

        const actions = options.actions || [
            { label: '✨ تحسين النص', value: 'improve' },
            { label: '📝 توسيع النص', value: 'expand' },
            { label: '✅ تصحيح الأخطاء', value: 'grammar' },
            { label: '💼 جعله أكثر احترافية', value: 'professional' }
        ];

        actions.forEach(action => {
            const item = document.createElement('button');
            item.type = 'button';
            item.textContent = action.label;
            item.style.cssText = `
                display: block;
                width: 100%;
                text-align: right;
                padding: 8px 12px;
                border: none;
                background: none;
                cursor: pointer;
                border-radius: 4px;
                transition: background 0.2s;
            `;

            item.onmouseover = () => {
                item.style.background = '#f3f4f6';
            };

            item.onmouseout = () => {
                item.style.background = 'none';
            };

            item.onclick = async () => {
                menu.remove();
                await this.handleAIAction(field, action.value);
            };

            menu.appendChild(item);
        });

        // إضافة زر إغلاق
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.textContent = '✕ إغلاق';
        closeBtn.style.cssText = `
            display: block;
            width: 100%;
            text-align: center;
            padding: 8px 12px;
            border: none;
            background: #ef4444;
            color: white;
            cursor: pointer;
            border-radius: 4px;
            margin-top: 4px;
        `;
        closeBtn.onclick = () => menu.remove();
        menu.appendChild(closeBtn);

        // وضع القائمة بجانب الحقل
        const rect = field.getBoundingClientRect();
        menu.style.left = rect.left + 'px';
        menu.style.top = (rect.top + window.scrollY + field.offsetHeight + 5) + 'px';

        document.body.appendChild(menu);

        // إغلاق القائمة عند النقر خارجها
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 100);
    }

    /**
     * تنفيذ إجراء AI
     */
    async handleAIAction(field, action) {
        const originalValue = field.value;

        if (!originalValue || originalValue.trim() === '') {
            alert('يرجى إدخال نص أولاً');
            return;
        }

        // عرض مؤشر التحميل
        field.disabled = true;
        field.style.opacity = '0.6';

        try {
            let result;

            switch (action) {
                case 'improve':
                case 'grammar':
                case 'professional':
                    result = await this.improveText(originalValue, action);
                    break;
                case 'expand':
                    result = await this.expandText(originalValue);
                    break;
                default:
                    throw new Error('إجراء غير معروف');
            }

            if (result.content) {
                field.value = result.content;
                this.showSuccessMessage('تم تحسين النص بنجاح!');
            }

        } catch (error) {
            alert('خطأ: ' + error.message);
            field.value = originalValue; // استعادة النص الأصلي
        } finally {
            field.disabled = false;
            field.style.opacity = '1';
        }
    }

    /**
     * عرض رسالة نجاح
     */
    showSuccessMessage(message) {
        const toast = document.createElement('div');
        toast.textContent = '✅ ' + message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// تصدير instance عام
const aiHelper = new AIHelper();

// إضافة CSS للأنيميشن
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
