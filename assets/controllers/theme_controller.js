import { Controller } from '@hotwired/stimulus'

/*
 * Theme Controller
 * Handles dark/light mode toggling with Stimulus integration
 */
export default class extends Controller {
    static targets = ['toggle', 'icon', 'label']
    static classes = ['light', 'dark']
    static values = { 
        theme: String,
        showLabel: Boolean 
    }
    
    connect() {
        // Initialize theme from ThemeManager
        if (window.themeManager) {
            this.themeValue = window.themeManager.getCurrentTheme()
            this.updateUI()
            
            // Listen for theme changes from other sources
            document.addEventListener('theme:changed', this.handleThemeChange.bind(this))
        }
    }
    
    disconnect() {
        document.removeEventListener('theme:changed', this.handleThemeChange.bind(this))
    }
    
    toggle() {
        if (window.themeManager) {
            const newTheme = window.themeManager.toggleTheme()
            this.themeValue = newTheme
            this.updateUI()
            
            // Announce theme change for accessibility
            const message = `Switched to ${newTheme} mode`
            if (window.accessibilityManager) {
                window.accessibilityManager.announce(message)
            }
            
            // Show notification
            if (window.notificationManager) {
                window.notificationManager.success(message, { duration: 2000 })
            }
        }
    }
    
    handleThemeChange(event) {
        this.themeValue = event.detail.theme
        this.updateUI()
    }
    
    updateUI() {
        const isDark = this.themeValue === 'dark'
        
        // Update toggle button
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-pressed', isDark.toString())
            this.toggleTarget.setAttribute('aria-label', 
                `Switch to ${isDark ? 'light' : 'dark'} mode`
            )
        }
        
        // Update icon
        if (this.hasIconTarget) {
            this.iconTarget.innerHTML = isDark ? this.lightIcon : this.darkIcon
        }
        
        // Update label
        if (this.hasLabelTarget && this.showLabelValue) {
            this.labelTarget.textContent = isDark ? 'Light mode' : 'Dark mode'
        }
        
        // Update CSS classes if defined
        if (this.hasLightClass || this.hasDarkClass) {
            this.element.classList.toggle(this.lightClass, !isDark)
            this.element.classList.toggle(this.darkClass, isDark)
        }
    }
    
    get lightIcon() {
        return `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        `
    }
    
    get darkIcon() {
        return `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
        `
    }
}