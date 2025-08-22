import { Controller } from '@hotwired/stimulus'

/*
 * Password Toggle Controller
 * Toggles password visibility with accessible icon switching
 */
export default class extends Controller {
    static targets = ['showIcon', 'hideIcon', 'button']
    
    connect() {
        this.passwordInput = this.element.querySelector('input[type="password"], input[type="text"]')
        
        if (!this.passwordInput) {
            console.warn('Password toggle controller requires a password or text input field')
            return
        }
        
        // Initialize state
        this.isVisible = this.passwordInput.type === 'text'
        this.updateVisibility()
    }
    
    toggle() {
        if (!this.passwordInput) return
        
        this.isVisible = !this.isVisible
        this.updateVisibility()
        
        // Announce change for screen readers
        if (window.accessibilityManager) {
            const message = this.isVisible ? 'Password is now visible' : 'Password is now hidden'
            window.accessibilityManager.announce(message)
        }
    }
    
    updateVisibility() {
        if (!this.passwordInput) return
        
        // Update input type
        this.passwordInput.type = this.isVisible ? 'text' : 'password'
        
        // Update icons
        if (this.hasShowIconTarget && this.hasHideIconTarget) {
            if (this.isVisible) {
                this.showIconTarget.classList.add('hidden')
                this.hideIconTarget.classList.remove('hidden')
            } else {
                this.showIconTarget.classList.remove('hidden')
                this.hideIconTarget.classList.add('hidden')
            }
        }
        
        // Update button attributes
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-pressed', this.isVisible.toString())
            this.buttonTarget.setAttribute('aria-label', 
                this.isVisible ? 'Hide password' : 'Show password'
            )
        }
        
        // Restore cursor position
        if (document.activeElement === this.passwordInput) {
            const cursorPosition = this.passwordInput.selectionStart
            setTimeout(() => {
                this.passwordInput.setSelectionRange(cursorPosition, cursorPosition)
            }, 0)
        }
    }
}