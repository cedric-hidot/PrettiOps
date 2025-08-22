import { Controller } from '@hotwired/stimulus'

/*
 * Modal Controller
 * Accessible modal dialogs with keyboard navigation and focus management
 */
export default class extends Controller {
    static targets = ['backdrop', 'dialog', 'title', 'body', 'close']
    static values = { 
        open: Boolean,
        closable: Boolean,
        size: String,
        backdrop: Boolean
    }
    static classes = ['open', 'closing']
    
    connect() {
        this.closableValue = this.closableValue ?? true
        this.backdropValue = this.backdropValue ?? true
        
        this.previousFocus = null
        this.setupEventListeners()
    }
    
    disconnect() {
        if (this.openValue) {
            this.close()
        }
    }
    
    setupEventListeners() {
        // Handle backdrop clicks
        if (this.hasBackdropTarget) {
            this.backdropTarget.addEventListener('click', this.handleBackdropClick.bind(this))
        }
        
        // Handle escape key
        document.addEventListener('keydown', this.handleKeydown.bind(this))
        
        // Handle focus trap
        document.addEventListener('keydown', this.handleFocusTrap.bind(this))
    }
    
    open(event) {
        if (event) {
            event.preventDefault()
            this.previousFocus = event.target
        }
        
        if (this.openValue) return
        
        this.openValue = true
        this.showModal()
        
        // Dispatch event
        this.dispatch('opened', { 
            detail: { modal: this.element, trigger: this.previousFocus } 
        })
        
        // Custom event for accessibility manager
        document.dispatchEvent(new CustomEvent('modal:opened', {
            detail: { modal: this.element, trigger: this.previousFocus }
        }))
    }
    
    close(event) {
        if (event) {
            event.preventDefault()
        }
        
        if (!this.openValue || !this.closableValue) return
        
        this.openValue = false
        this.hideModal()
        
        // Dispatch event
        this.dispatch('closed', { 
            detail: { modal: this.element, trigger: this.previousFocus } 
        })
        
        // Custom event for accessibility manager
        document.dispatchEvent(new CustomEvent('modal:closed', {
            detail: { modal: this.element, trigger: this.previousFocus }
        }))
    }
    
    toggle(event) {
        if (this.openValue) {
            this.close(event)
        } else {
            this.open(event)
        }
    }
    
    showModal() {
        // Prevent body scroll
        document.body.style.overflow = 'hidden'
        
        // Show modal
        this.element.classList.remove('hidden')
        this.element.classList.add('flex')
        
        // Trigger reflow to ensure classes are applied
        this.element.offsetHeight
        
        // Add open class for animations
        if (this.hasOpenClass) {
            this.element.classList.add(this.openClass)
        }
        
        // Focus management
        this.manageFocus()
        
        // Announce to screen readers
        if (window.accessibilityManager) {
            const title = this.hasTitleTarget ? this.titleTarget.textContent : 'Dialog opened'
            window.accessibilityManager.announce(`Modal dialog opened: ${title}`)
        }
        
        // Set ARIA attributes
        this.element.setAttribute('role', 'dialog')
        this.element.setAttribute('aria-modal', 'true')
        if (this.hasTitleTarget) {
            this.element.setAttribute('aria-labelledby', this.titleTarget.id || 'modal-title')
        }
    }
    
    hideModal() {
        // Add closing class for animations
        if (this.hasClosingClass) {
            this.element.classList.add(this.closingClass)
        }
        
        // Remove open class
        if (this.hasOpenClass) {
            this.element.classList.remove(this.openClass)
        }
        
        // Wait for animation to complete
        setTimeout(() => {
            this.element.classList.add('hidden')
            this.element.classList.remove('flex')
            
            if (this.hasClosingClass) {
                this.element.classList.remove(this.closingClass)
            }
            
            // Restore body scroll
            document.body.style.overflow = ''
            
            // Restore focus
            this.restoreFocus()
            
        }, 300) // Match animation duration
    }
    
    manageFocus() {
        // Get focusable elements within the modal
        const focusableElements = this.getFocusableElements()
        
        if (focusableElements.length > 0) {
            // Focus first focusable element (usually close button or first input)
            focusableElements[0].focus()
        } else {
            // Focus the modal itself if no focusable elements
            this.element.focus()
        }
    }
    
    restoreFocus() {
        if (this.previousFocus && typeof this.previousFocus.focus === 'function') {
            this.previousFocus.focus()
        }
        this.previousFocus = null
    }
    
    getFocusableElements() {
        const selector = [
            'a[href]',
            'button:not([disabled])',
            'textarea:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(', ')
        
        return Array.from(this.element.querySelectorAll(selector))
            .filter(element => {
                return element.offsetWidth > 0 && element.offsetHeight > 0
            })
    }
    
    handleBackdropClick(event) {
        // Only close if clicking the backdrop itself, not its children
        if (event.target === this.backdropTarget && this.backdropValue) {
            this.close()
        }
    }
    
    handleKeydown(event) {
        if (!this.openValue) return
        
        // Close on escape key
        if (event.key === 'Escape' && this.closableValue) {
            event.preventDefault()
            this.close()
        }
    }
    
    handleFocusTrap(event) {
        if (!this.openValue || event.key !== 'Tab') return
        
        const focusableElements = this.getFocusableElements()
        if (focusableElements.length === 0) return
        
        const firstElement = focusableElements[0]
        const lastElement = focusableElements[focusableElements.length - 1]
        
        // Trap focus within modal
        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault()
            lastElement.focus()
        } else if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault()
            firstElement.focus()
        }
    }
    
    // Static methods for programmatic usage
    static open(modalId, options = {}) {
        const modal = document.getElementById(modalId)
        if (modal && modal.hasAttribute('data-controller')) {
            const controller = this.getController(modal)
            if (controller) {
                controller.open()
            }
        }
    }
    
    static close(modalId) {
        const modal = document.getElementById(modalId)
        if (modal && modal.hasAttribute('data-controller')) {
            const controller = this.getController(modal)
            if (controller) {
                controller.close()
            }
        }
    }
    
    static getController(element) {
        return element[Object.keys(element).find(key => 
            key.startsWith('stimulus') && element[key].identifier === 'modal'
        )]
    }
}