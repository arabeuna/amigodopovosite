/**
 * Mobile Enhancement JavaScript for Associação PHP System
 * Handles mobile-specific interactions and responsive behavior
 */

class MobileEnhancer {
    constructor() {
        this.sidebar = null;
        this.overlay = null;
        this.menuBtn = null;
        this.isMenuOpen = false;
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.init();
    }

    init() {
        this.createMobileElements();
        this.bindEvents();
        this.handleResize();
        this.enhanceTables();
        this.enhanceForms();
    }

    createMobileElements() {
        // Create mobile menu button
        const header = document.querySelector('.header-enhanced');
        if (header && !document.querySelector('.mobile-menu-btn')) {
            const menuBtn = document.createElement('button');
            menuBtn.className = 'mobile-menu-btn';
            menuBtn.innerHTML = '☰';
            menuBtn.setAttribute('aria-label', 'Abrir menu');
            header.insertBefore(menuBtn, header.firstChild);
            this.menuBtn = menuBtn;
        }

        // Create mobile overlay
        if (!document.querySelector('.mobile-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'mobile-overlay';
            document.body.appendChild(overlay);
            this.overlay = overlay;
        }

        this.sidebar = document.querySelector('.sidebar-enhanced');
    }

    bindEvents() {
        // Menu button click
        if (this.menuBtn) {
            this.menuBtn.addEventListener('click', () => this.toggleMenu());
        }

        // Overlay click
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeMenu());
        }

        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMenuOpen) {
                this.closeMenu();
            }
        });

        // Window resize
        window.addEventListener('resize', () => this.handleResize());

        // Touch events for swipe gestures
        document.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: true });
        document.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        document.addEventListener('touchend', (e) => this.handleTouchEnd(e), { passive: true });

        // Form enhancements
        this.enhanceFormInputs();
    }

    toggleMenu() {
        if (this.isMenuOpen) {
            this.closeMenu();
        } else {
            this.openMenu();
        }
    }

    openMenu() {
        if (this.sidebar && this.overlay) {
            this.sidebar.classList.add('mobile-open');
            this.overlay.classList.add('active');
            this.isMenuOpen = true;
            document.body.style.overflow = 'hidden';
            
            if (this.menuBtn) {
                this.menuBtn.innerHTML = '✕';
                this.menuBtn.setAttribute('aria-label', 'Fechar menu');
            }
        }
    }

    closeMenu() {
        if (this.sidebar && this.overlay) {
            this.sidebar.classList.remove('mobile-open');
            this.overlay.classList.remove('active');
            this.isMenuOpen = false;
            document.body.style.overflow = '';
            
            if (this.menuBtn) {
                this.menuBtn.innerHTML = '☰';
                this.menuBtn.setAttribute('aria-label', 'Abrir menu');
            }
        }
    }

    handleResize() {
        if (window.innerWidth > 768 && this.isMenuOpen) {
            this.closeMenu();
        }
    }

    handleTouchStart(e) {
        this.touchStartX = e.touches[0].clientX;
        this.touchStartY = e.touches[0].clientY;
    }

    handleTouchMove(e) {
        if (!this.touchStartX || !this.touchStartY) return;

        const touchX = e.touches[0].clientX;
        const touchY = e.touches[0].clientY;
        const diffX = this.touchStartX - touchX;
        const diffY = this.touchStartY - touchY;

        // Don't interfere with navigation links
        if (e.target.closest('a') || e.target.closest('button')) {
            return;
        }

        // Prevent horizontal scroll on tables when swiping vertically
        if (Math.abs(diffY) > Math.abs(diffX)) {
            const table = e.target.closest('.data-table-container');
            if (table) {
                e.preventDefault();
            }
        }

        // Swipe to close menu
        if (this.isMenuOpen && diffX > 50 && Math.abs(diffY) < 100) {
            this.closeMenu();
        }

        // Swipe to open menu (from left edge)
        if (!this.isMenuOpen && this.touchStartX < 20 && diffX < -50 && Math.abs(diffY) < 100) {
            this.openMenu();
        }
    }

    handleTouchEnd(e) {
        this.touchStartX = 0;
        this.touchStartY = 0;
    }

    enhanceTables() {
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            const container = table.closest('.data-table-container');
            if (container && !container.querySelector('.swipe-indicator')) {
                const indicator = document.createElement('div');
                indicator.className = 'swipe-indicator';
                container.appendChild(indicator);
            }

            // Add touch scrolling enhancement
            if (container) {
                container.style.webkitOverflowScrolling = 'touch';
            }
        });
    }

    enhanceForms() {
        // Auto-resize textareas
        const textareas = document.querySelectorAll('.form-textarea');
        textareas.forEach(textarea => {
            textarea.addEventListener('input', () => {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            });
        });

        // Enhance select elements for mobile
        const selects = document.querySelectorAll('.form-select');
        selects.forEach(select => {
            select.addEventListener('focus', () => {
                if (window.innerWidth <= 768) {
                    select.size = Math.min(select.options.length, 5);
                }
            });

            select.addEventListener('blur', () => {
                select.size = 1;
            });
        });
    }

    enhanceFormInputs() {
        // Prevent zoom on input focus for iOS
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type !== 'file') {
                input.addEventListener('focus', () => {
                    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                        input.style.fontSize = '16px';
                    }
                });

                input.addEventListener('blur', () => {
                    input.style.fontSize = '';
                });
            }
        });
    }

    // Utility methods
    static isMobile() {
        return window.innerWidth <= 768;
    }

    static isTablet() {
        return window.innerWidth > 768 && window.innerWidth <= 1024;
    }

    static isTouchDevice() {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }

    // Loading state management
    static showLoading(element) {
        if (element) {
            element.classList.add('mobile-loading');
        }
    }

    static hideLoading(element) {
        if (element) {
            element.classList.remove('mobile-loading');
        }
    }

    // Smooth scroll to element
    static scrollToElement(element, offset = 0) {
        if (element) {
            const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
            const offsetPosition = elementPosition - offset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    }

    // Vibration feedback (if supported)
    static vibrate(pattern = [100]) {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }
}

// Initialize mobile enhancements when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new MobileEnhancer();
    });
} else {
    new MobileEnhancer();
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MobileEnhancer;
} else if (typeof window !== 'undefined') {
    window.MobileEnhancer = MobileEnhancer;
}

// Additional mobile utilities
const MobileUtils = {
    // Check if device supports hover
    supportsHover: () => {
        return window.matchMedia('(hover: hover)').matches;
    },

    // Get device orientation
    getOrientation: () => {
        return window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
    },

    // Check if device is in standalone mode (PWA)
    isStandalone: () => {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    },

    // Debounce function for performance
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Throttle function for performance
    throttle: (func, limit) => {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
};

// Make utilities available globally
if (typeof window !== 'undefined') {
    window.MobileUtils = MobileUtils;
}