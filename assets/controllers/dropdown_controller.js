import { Controller } from '@hotwired/stimulus'

/*
 * Dropdown Controller
 * Accessible dropdown menus with keyboard navigation
 */
export default class extends Controller {
    static targets = ['menu', 'arrow']
    static values = { 
        open: Boolean,
        closeOnOutsideClick: Boolean,
        closeOnEscape: Boolean
    }
    static classes = ['open']
    
    connect() {
        this.closeOnOutsideClickValue = this.closeOnOutsideClickValue ?? true
        this.closeOnEscapeValue = this.closeOnEscapeValue ?? true
        
        this.boundHandleOutsideClick = this.handleOutsideClick.bind(this)
        this.boundHandleKeydown = this.handleKeydown.bind(this)
        
        // Initialize state
        this.openValue = false
    }
    
    disconnect() {
        this.removeEventListeners()
    }
    
    toggle(event) {
        if (event) {
            event.preventDefault()
            event.stopPropagation()
        }
        
        this.openValue ? this.close() : this.open()
    }
    
    open() {
        if (this.openValue) return
        
        this.openValue = true
        
        // Update UI
        this.updateUI()
        
        // Add event listeners
        this.addEventListeners()
        
        // Focus management
        this.manageFocus()
        
        // Announce to screen readers
        if (window.accessibilityManager) {
            window.accessibilityManager.announce('Menu opened')
        }
        
        this.dispatch('opened')
    }
    
    close() {
        if (!this.openValue) return
        
        this.openValue = false
        
        // Update UI
        this.updateUI()
        
        // Remove event listeners
        this.removeEventListeners()
        
        // Announce to screen readers
        if (window.accessibilityManager) {
            window.accessibilityManager.announce('Menu closed')
        }
        
        this.dispatch('closed')
    }
    
    updateUI() {
        const trigger = this.element.querySelector('[data-action*="dropdown#toggle"]')
        
        // Update trigger attributes
        if (trigger) {
            trigger.setAttribute('aria-expanded', this.openValue.toString())
        }
        
        // Update menu visibility
        if (this.hasMenuTarget) {
            if (this.openValue) {
                this.menuTarget.classList.remove('hidden')
                this.menuTarget.setAttribute('aria-hidden', 'false')
            } else {
                this.menuTarget.classList.add('hidden')
                this.menuTarget.setAttribute('aria-hidden', 'true')
            }
        }
        
        // Update arrow rotation
        if (this.hasArrowTarget) {
            if (this.openValue) {
                this.arrowTarget.style.transform = 'rotate(180deg)'
            } else {
                this.arrowTarget.style.transform = 'rotate(0deg)'
            }
        }
        
        // Update CSS classes
        if (this.hasOpenClass) {
            this.element.classList.toggle(this.openClass, this.openValue)
        }
    }
    
    manageFocus() {
        if (!this.openValue || !this.hasMenuTarget) return
        
        // Focus first focusable item in menu
        const firstFocusable = this.menuTarget.querySelector(
            'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )
        
        if (firstFocusable) {
            firstFocusable.focus()
        }
    }
    
    addEventListeners() {
        if (this.closeOnOutsideClickValue) {
            setTimeout(() => {
                document.addEventListener('click', this.boundHandleOutsideClick)
            }, 0)
        }
        
        if (this.closeOnEscapeValue) {
            document.addEventListener('keydown', this.boundHandleKeydown)
        }
    }
    
    removeEventListeners() {
        document.removeEventListener('click', this.boundHandleOutsideClick)
        document.removeEventListener('keydown', this.boundHandleKeydown)
    }
    
    handleOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.close()
        }
    }
    
    handleKeydown(event) {
        if (!this.openValue) return
        
        // Close on escape
        if (event.key === 'Escape') {
            event.preventDefault()
            this.close()
            
            // Return focus to trigger
            const trigger = this.element.querySelector('[data-action*="dropdown#toggle"]')
            if (trigger) {
                trigger.focus()
            }
        }
        
        // Handle arrow key navigation
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault()
            this.navigateItems(event.key === 'ArrowDown' ? 1 : -1)
        }
        
        // Handle Enter and Space on menu items
        if (event.key === 'Enter' || event.key === ' ') {
            const activeElement = document.activeElement
            if (this.hasMenuTarget && this.menuTarget.contains(activeElement)) {
                if (activeElement.tagName === 'A' || activeElement.tagName === 'BUTTON') {
                    event.preventDefault()
                    activeElement.click()
                    this.close()
                }
            }
        }
    }
    
    navigateItems(direction) {
        if (!this.hasMenuTarget) return
        
        const items = Array.from(this.menuTarget.querySelectorAll(
            'a, button, [tabindex]:not([tabindex="-1"])'
        )).filter(item => {
            return item.offsetWidth > 0 && item.offsetHeight > 0 && !item.disabled
        })
        
        if (items.length === 0) return
        
        const currentIndex = items.indexOf(document.activeElement)
        let nextIndex
        
        if (currentIndex === -1) {
            // No item currently focused, focus first or last
            nextIndex = direction > 0 ? 0 : items.length - 1
        } else {
            // Calculate next index with wrapping
            nextIndex = currentIndex + direction
            if (nextIndex < 0) {
                nextIndex = items.length - 1
            } else if (nextIndex >= items.length) {
                nextIndex = 0
            }
        }
        
        items[nextIndex].focus()
    }
    
    // Method to handle menu item clicks
    selectItem(event) {
        const item = event.currentTarget
        
        // Dispatch selection event
        this.dispatch('itemSelected', {
            detail: { 
                item, 
                value: item.dataset.value || item.textContent.trim() 
            }
        })
        
        // Close dropdown
        this.close()
    }
}