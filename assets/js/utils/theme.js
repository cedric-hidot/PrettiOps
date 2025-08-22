/**
 * Theme Management Utility
 * Handles dark/light mode toggling and persistence
 */

class ThemeManager {
    constructor() {
        this.themeKey = 'prettiops-theme';
        this.init();
    }
    
    init() {
        // Load saved theme or detect system preference
        const savedTheme = localStorage.getItem(this.themeKey);
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        
        this.currentTheme = savedTheme || systemTheme;
        this.applyTheme(this.currentTheme);
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(this.themeKey)) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });
        
        // Dispatch custom event for theme initialization
        document.dispatchEvent(new CustomEvent('theme:initialized', {
            detail: { theme: this.currentTheme }
        }));
    }
    
    setTheme(theme) {
        if (!['light', 'dark'].includes(theme)) {
            console.warn(`Invalid theme: ${theme}. Using 'light' as fallback.`);
            theme = 'light';
        }
        
        this.currentTheme = theme;
        localStorage.setItem(this.themeKey, theme);
        this.applyTheme(theme);
        
        // Dispatch custom event for theme change
        document.dispatchEvent(new CustomEvent('theme:changed', {
            detail: { theme, previous: this.currentTheme }
        }));
    }
    
    applyTheme(theme) {
        const html = document.documentElement;
        
        if (theme === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        
        // Update meta theme-color for mobile browsers
        const themeColorMeta = document.querySelector('meta[name="theme-color"]');
        if (themeColorMeta) {
            themeColorMeta.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
        }
        
        // Update Monaco Editor theme if present
        if (window.monaco && window.monacoEditor) {
            const editorTheme = theme === 'dark' ? 'vs-dark' : 'vs';
            window.monaco.editor.setTheme(editorTheme);
        }
    }
    
    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
        return newTheme;
    }
    
    getCurrentTheme() {
        return this.currentTheme;
    }
    
    isSystemTheme() {
        return !localStorage.getItem(this.themeKey);
    }
    
    resetToSystemTheme() {
        localStorage.removeItem(this.themeKey);
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        this.setTheme(systemTheme);
    }
}

// Create global theme manager instance
window.themeManager = new ThemeManager();

// Export for ES6 modules
export default window.themeManager;