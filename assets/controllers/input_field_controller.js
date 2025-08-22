/**
 * Advanced Input Field Controller - Tiptap-inspired
 * 
 * Handles input validation, state management, and animations for enhanced form inputs
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["input", "wrapper", "state", "error"]
    static values = { 
        required: Boolean,
        pattern: String,
        minLength: Number,
        maxLength: Number,
        validateOnInput: { type: Boolean, default: true }
    }

    connect() {
        this.setupValidation()
        this.bindEvents()
    }

    setupValidation() {
        if (this.requiredValue) {
            this.inputTarget.setAttribute('required', '')
        }
        
        if (this.patternValue) {
            this.inputTarget.setAttribute('pattern', this.patternValue)
        }
        
        if (this.minLengthValue) {
            this.inputTarget.setAttribute('minlength', this.minLengthValue)
        }
        
        if (this.maxLengthValue) {
            this.inputTarget.setAttribute('maxlength', this.maxLengthValue)
        }
    }

    bindEvents() {
        // Add smooth focus effects
        this.inputTarget.addEventListener('focus', this.handleFocus.bind(this))
        this.inputTarget.addEventListener('blur', this.handleBlur.bind(this))
        this.inputTarget.addEventListener('input', this.handleInput.bind(this))
    }

    handleFocus(event) {
        this.element.classList.add('is-focused')
        this.clearError()
        
        // Add subtle animation
        this.animateWrapper('focus')
    }

    handleBlur(event) {
        this.element.classList.remove('is-focused')
        
        // Validate on blur if input has content
        if (this.inputTarget.value.trim() !== '') {
            this.validateField()
        }
        
        this.animateWrapper('blur')
    }

    handleInput(event) {
        if (this.validateOnInputValue) {
            // Debounce validation for better performance
            clearTimeout(this.validationTimeout)
            this.validationTimeout = setTimeout(() => {
                this.validateField()
            }, 300)
        }
        
        // Update character count if maxLength is set
        this.updateCharacterCount()
    }

    validateField() {
        const input = this.inputTarget
        const value = input.value.trim()
        
        // Clear previous states
        this.clearStates()
        
        // Required field validation
        if (this.requiredValue && value === '') {
            this.setError('This field is required')
            return false
        }
        
        // Pattern validation
        if (this.patternValue && value !== '') {
            const regex = new RegExp(this.patternValue)
            if (!regex.test(value)) {
                this.setError('Please enter a valid format')
                return false
            }
        }
        
        // Length validation
        if (this.minLengthValue && value.length < this.minLengthValue) {
            this.setError(`Minimum ${this.minLengthValue} characters required`)
            return false
        }
        
        // Email validation (if input type is email)
        if (input.type === 'email' && value !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
            if (!emailRegex.test(value)) {
                this.setError('Please enter a valid email address')
                return false
            }
        }
        
        // If we get here, validation passed
        if (value !== '') {
            this.setValid()
        }
        
        return true
    }

    setValid() {
        this.element.classList.add('is-valid')
        this.element.classList.remove('is-error')
        
        // Animate success state
        this.animateState('success')
    }

    setError(message) {
        this.element.classList.add('is-error')
        this.element.classList.remove('is-valid')
        
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message
            this.errorTarget.classList.add('opacity-100')
        }
        
        // Animate error state
        this.animateState('error')
        
        // Add subtle shake animation
        this.shakeInput()
    }

    clearError() {
        this.element.classList.remove('is-error')
        
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = ''
            this.errorTarget.classList.remove('opacity-100')
        }
    }

    clearStates() {
        this.element.classList.remove('is-valid', 'is-error')
    }

    updateCharacterCount() {
        if (this.maxLengthValue && this.hasErrorTarget) {
            const currentLength = this.inputTarget.value.length
            const remaining = this.maxLengthValue - currentLength
            
            if (remaining < 10 && remaining > 0) {
                this.errorTarget.textContent = `${remaining} characters remaining`
                this.errorTarget.classList.add('text-amber-600', 'opacity-100')
                this.errorTarget.classList.remove('text-red-600')
            } else if (remaining <= 0) {
                this.errorTarget.textContent = 'Character limit reached'
                this.errorTarget.classList.add('text-red-600', 'opacity-100')
                this.errorTarget.classList.remove('text-amber-600')
            }
        }
    }

    animateWrapper(type) {
        const wrapper = this.hasWrapperTarget ? this.wrapperTarget : this.element
        
        if (type === 'focus') {
            wrapper.style.transform = 'scale(1.02)'
            wrapper.style.transition = 'transform 0.2s ease-out'
            
            setTimeout(() => {
                wrapper.style.transform = 'scale(1)'
            }, 200)
        }
    }

    animateState(type) {
        if (!this.hasStateTarget) return
        
        const stateIcon = this.stateTarget.querySelector(`.InputState__Icon--${type}`)
        if (stateIcon) {
            stateIcon.style.transform = 'scale(0)'
            stateIcon.style.display = 'block'
            
            requestAnimationFrame(() => {
                stateIcon.style.transform = 'scale(1.2)'
                stateIcon.style.transition = 'transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)'
                
                setTimeout(() => {
                    stateIcon.style.transform = 'scale(1)'
                }, 300)
            })
        }
    }

    shakeInput() {
        const input = this.inputTarget
        input.style.animation = 'none'
        
        requestAnimationFrame(() => {
            input.style.animation = 'shake 0.5s ease-in-out'
        })
        
        setTimeout(() => {
            input.style.animation = ''
        }, 500)
    }

    // Public method for external validation
    validate() {
        return this.validateField()
    }

    // Public method to get current value
    getValue() {
        return this.inputTarget.value.trim()
    }

    // Public method to set value
    setValue(value) {
        this.inputTarget.value = value
        this.validateField()
    }

    disconnect() {
        clearTimeout(this.validationTimeout)
    }
}

// Add CSS animations
const style = document.createElement('style')
style.textContent = `
@keyframes shake {
    0%, 20%, 40%, 60%, 80%, 100% {
        transform: translateX(0);
    }
    10%, 30%, 50%, 70%, 90% {
        transform: translateX(-5px);
    }
}
`
document.head.appendChild(style)