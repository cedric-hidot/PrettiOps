/**
 * Mega Menu Controller - Tiptap-inspired
 * 
 * Handles mega menu interactions with smooth animations and accessibility
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["trigger", "menu", "arrow"]
    static values = { 
        delay: { type: Number, default: 200 },
        animationDuration: { type: Number, default: 300 },
        enableKeyboard: { type: Boolean, default: true }
    }

    connect() {
        this.showTimeout = null
        this.hideTimeout = null
        this.isOpen = false
        
        if (this.enableKeyboardValue) {
            this.bindKeyboardEvents()
        }
        
        // Set initial ARIA attributes
        this.setupAccessibility()
    }

    setupAccessibility() {
        if (this.hasTriggerTarget) {
            this.triggerTarget.setAttribute('aria-haspopup', 'true')
            this.triggerTarget.setAttribute('aria-expanded', 'false')
            
            if (this.hasMenuTarget) {
                const menuId = this.menuTarget.id || `mega-menu-${Math.random().toString(36).substr(2, 9)}`
                this.menuTarget.id = menuId
                this.triggerTarget.setAttribute('aria-controls', menuId)
            }
        }
    }

    bindKeyboardEvents() {
        if (this.hasTriggerTarget) {
            this.triggerTarget.addEventListener('keydown', this.handleKeydown.bind(this))
        }
        
        if (this.hasMenuTarget) {
            this.menuTarget.addEventListener('keydown', this.handleMenuKeydown.bind(this))
        }
    }

    show(event) {
        // Clear any existing hide timeout
        clearTimeout(this.hideTimeout)
        
        // Set show timeout for smooth UX
        this.showTimeout = setTimeout(() => {
            this.openMenu()
        }, this.delayValue)
    }

    hide(event) {
        // Clear any existing show timeout
        clearTimeout(this.showTimeout)
        
        // Set hide timeout to allow moving between trigger and menu
        this.hideTimeout = setTimeout(() => {
            this.closeMenu()
        }, this.delayValue)
    }

    openMenu() {
        if (this.isOpen) return
        
        this.isOpen = true
        
        if (this.hasMenuTarget) {
            // Make menu visible
            this.menuTarget.classList.remove('opacity-0', 'invisible')
            this.menuTarget.classList.add('opacity-100', 'visible')
            
            // Add entrance animation
            this.menuTarget.style.transform = 'translateX(-50%) translateY(-10px)'
            
            requestAnimationFrame(() => {
                this.menuTarget.style.transform = 'translateX(-50%) translateY(0)'
                this.menuTarget.style.transition = `all ${this.animationDurationValue}ms cubic-bezier(0.4, 0, 0.2, 1)`
            })
        }
        
        // Rotate arrow
        if (this.hasArrowTarget) {
            this.arrowTarget.style.transform = 'rotate(180deg)'
        }
        
        // Update ARIA
        if (this.hasTriggerTarget) {
            this.triggerTarget.setAttribute('aria-expanded', 'true')
        }
        
        // Dispatch custom event
        this.dispatch('opened', { detail: { menu: this.menuTarget } })
    }

    closeMenu() {
        if (!this.isOpen) return
        
        this.isOpen = false
        
        if (this.hasMenuTarget) {
            // Add exit animation
            this.menuTarget.style.transform = 'translateX(-50%) translateY(-10px)'
            this.menuTarget.style.transition = `all ${this.animationDurationValue}ms cubic-bezier(0.4, 0, 0.2, 1)`
            
            setTimeout(() => {
                this.menuTarget.classList.remove('opacity-100', 'visible')
                this.menuTarget.classList.add('opacity-0', 'invisible')
            }, this.animationDurationValue)
        }
        
        // Rotate arrow back
        if (this.hasArrowTarget) {
            this.arrowTarget.style.transform = 'rotate(0deg)'
        }
        
        // Update ARIA
        if (this.hasTriggerTarget) {
            this.triggerTarget.setAttribute('aria-expanded', 'false')
        }
        
        // Dispatch custom event
        this.dispatch('closed', { detail: { menu: this.menuTarget } })
    }

    handleKeydown(event) {
        switch (event.key) {
            case 'Enter':
            case ' ':
                event.preventDefault()
                if (this.isOpen) {
                    this.closeMenu()
                } else {
                    this.openMenu()
                    this.focusFirstMenuItem()
                }
                break
            case 'ArrowDown':
                event.preventDefault()
                if (!this.isOpen) {
                    this.openMenu()
                }
                this.focusFirstMenuItem()
                break
            case 'Escape':
                if (this.isOpen) {
                    this.closeMenu()
                    this.triggerTarget.focus()
                }
                break
        }
    }

    handleMenuKeydown(event) {
        switch (event.key) {
            case 'Escape':
                this.closeMenu()
                this.triggerTarget.focus()
                break
            case 'Tab':
                // Handle tab navigation within menu
                this.handleTabNavigation(event)
                break
            case 'ArrowDown':
                event.preventDefault()
                this.focusNextMenuItem()
                break
            case 'ArrowUp':
                event.preventDefault()
                this.focusPreviousMenuItem()
                break
        }
    }

    handleTabNavigation(event) {
        const focusableElements = this.getFocusableMenuItems()
        const firstElement = focusableElements[0]
        const lastElement = focusableElements[focusableElements.length - 1]
        
        if (event.shiftKey) {
            // Shift + Tab
            if (document.activeElement === firstElement) {
                event.preventDefault()
                this.closeMenu()
                this.triggerTarget.focus()
            }
        } else {
            // Tab
            if (document.activeElement === lastElement) {
                event.preventDefault()
                this.closeMenu()
            }
        }
    }

    focusFirstMenuItem() {
        const menuItems = this.getFocusableMenuItems()
        if (menuItems.length > 0) {
            menuItems[0].focus()
        }
    }

    focusNextMenuItem() {
        const menuItems = this.getFocusableMenuItems()
        const currentIndex = menuItems.indexOf(document.activeElement)
        const nextIndex = currentIndex < menuItems.length - 1 ? currentIndex + 1 : 0
        menuItems[nextIndex].focus()
    }

    focusPreviousMenuItem() {
        const menuItems = this.getFocusableMenuItems()
        const currentIndex = menuItems.indexOf(document.activeElement)
        const previousIndex = currentIndex > 0 ? currentIndex - 1 : menuItems.length - 1
        menuItems[previousIndex].focus()
    }

    getFocusableMenuItems() {
        if (!this.hasMenuTarget) return []
        
        const selectors = [
            'a[href]',
            'button:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(', ')
        
        return Array.from(this.menuTarget.querySelectorAll(selectors))
    }

    // Handle clicks outside the menu
    clickOutside(event) {
        if (this.isOpen && !this.element.contains(event.target)) {
            this.closeMenu()
        }
    }

    // Public method to programmatically toggle
    toggle() {
        if (this.isOpen) {
            this.closeMenu()
        } else {
            this.openMenu()
        }
    }

    disconnect() {
        clearTimeout(this.showTimeout)
        clearTimeout(this.hideTimeout)
        
        // Remove global event listeners
        document.removeEventListener('click', this.clickOutside.bind(this))
    }

    // Add global click listener on first connect
    initialize() {
        document.addEventListener('click', this.clickOutside.bind(this))
    }
}

// Add CSS for better animations
const style = document.createElement('style')
style.textContent = `
.mega-menu {
    transform-origin: top center;
}

.mega-menu-item:hover .mega-menu-icon {
    transform: translateY(-1px);
}

/* Focus styles for accessibility */
.mega-menu a:focus,
.mega-menu button:focus {
    outline: 2px solid #6f00ff;
    outline-offset: 2px;
    border-radius: 8px;
}

/* Smooth transitions for all interactive elements */
.mega-menu * {
    transition: all 0.2s ease-in-out;
}
`
document.head.appendChild(style)