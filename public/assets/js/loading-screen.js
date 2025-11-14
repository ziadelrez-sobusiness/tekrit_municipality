// إدارة شاشة التحميل - Loading Screen Manager
class LoadingScreen {
    constructor() {
        this.loadingOverlay = null;
        this.minimumLoadTime = 2000; // حد أدنى 2 ثانية
        this.startTime = Date.now();
        this.isComplete = false;
        
        this.init();
    }

    init() {
        // إنشاء شاشة التحميل
        this.createLoadingScreen();
        
        // إخفاء محتوى الصفحة مؤقتاً
        document.body.style.overflow = 'hidden';
        
        // انتظار تحميل كامل للصفحة
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.onDOMReady();
            });
        } else {
            this.onDOMReady();
        }
        
        // انتظار تحميل جميع الموارد
        window.addEventListener('load', () => {
            this.onPageFullyLoaded();
        });
    }

    createLoadingScreen() {
        this.loadingOverlay = document.createElement('div');
        this.loadingOverlay.className = 'loading-overlay';
        this.loadingOverlay.innerHTML = `
            <div class="loading-container">
                <div class="city-icon">
                    <div class="city-circle">
                        <div class="city-buildings">
                            <div class="building building-1"></div>
                            <div class="building building-2"></div>
                            <div class="building building-3"></div>
                            <div class="building building-4"></div>
                            <div class="building building-5"></div>
                        </div>
                    </div>
                </div>
                
                <h1 class="loading-text">بلدية تكريت</h1>
                <p class="loading-subtext">جاري التحميل
                    <span class="loading-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </p>
                
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>
        `;
        
        document.body.prepend(this.loadingOverlay);
    }

    onDOMReady() {
        // يمكن إضافة منطق إضافي هنا عند تحميل DOM
        console.log('DOM محمل بالكامل');
    }

    onPageFullyLoaded() {
        // حساب الوقت المنقضي
        const elapsedTime = Date.now() - this.startTime;
        const remainingTime = Math.max(0, this.minimumLoadTime - elapsedTime);
        
        // انتظار الحد الأدنى للوقت ثم إخفاء شاشة التحميل
        setTimeout(() => {
            this.hideLoadingScreen();
        }, remainingTime);
    }

    hideLoadingScreen() {
        if (this.loadingOverlay && !this.isComplete) {
            this.isComplete = true;
            
            // إضافة كلاس الإخفاء
            this.loadingOverlay.classList.add('fade-out');
            
            // إعادة تفعيل التمرير
            document.body.style.overflow = '';
            
            // إزالة العنصر من DOM بعد انتهاء الأنيميشن
            setTimeout(() => {
                if (this.loadingOverlay && this.loadingOverlay.parentNode) {
                    this.loadingOverlay.parentNode.removeChild(this.loadingOverlay);
                    this.loadingOverlay = null;
                }
            }, 500); // 500ms مطابق لمدة transition في CSS
            
            // تشغيل أي أنيميشنز أخرى للصفحة
            this.animatePageContent();
        }
    }

    animatePageContent() {
        // إضافة أنيميشن ظهور لمحتوى الصفحة
        const mainContent = document.querySelector('main') || document.querySelector('.container') || document.body;
        
        if (mainContent) {
            mainContent.style.opacity = '0';
            mainContent.style.transform = 'translateY(20px)';
            mainContent.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
            
            // تأخير قصير قبل الأنيميشن
            setTimeout(() => {
                mainContent.style.opacity = '1';
                mainContent.style.transform = 'translateY(0)';
            }, 100);
        }
        
        // إظهار العناصر تدريجياً
        this.animateElements();
    }

    animateElements() {
        // أنيميشن للعناصر المختلفة
        const elements = document.querySelectorAll('.hero-section, .news-section, .services-section, .card');
        
        elements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            element.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            
            setTimeout(() => {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, 200 + (index * 100)); // تأخير متدرج
        });
    }

    // دالة لإخفاء فوري (للاستخدام عند الحاجة)
    forceHide() {
        if (this.loadingOverlay && !this.isComplete) {
            this.hideLoadingScreen();
        }
    }
}

// تشغيل شاشة التحميل عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    // التأكد من عدم وجود شاشة تحميل مسبقاً
    if (!document.querySelector('.loading-overlay')) {
        new LoadingScreen();
    }
});

// إضافة متغير عام للتحكم في شاشة التحميل
window.loadingScreen = null;

// دالة للإخفاء اليدوي إذا لزم الأمر
window.hideLoadingScreen = function() {
    if (window.loadingScreen) {
        window.loadingScreen.forceHide();
    }
}; 