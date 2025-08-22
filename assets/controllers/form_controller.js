import { Controller } from '@hotwired/stimulus'

/*
 * Form Controller
 * Enhanced form handling with validation, loading states, and UX improvements
 */
export default class extends Controller {
    static targets = ['submit', 'loading', 'error', 'success', 'field']
    static values = { 
        loading: Boolean,
        validateOnInput: Boolean,
        showSuccess: Boolean,
        resetOnSuccess: Boolean
    }
    static classes = ['loading', 'error', 'success', 'disabled']
    
    connect() {
        this.originalSubmitText = this.hasSubmitTarget ? this.submitTarget.textContent : ''
        this.validators = new Map()
        this.setupValidation()
    }
    
    setupValidation() {
        if (this.validateOnInputValue) {
            this.fieldTargets.forEach(field => {
                field.addEventListener('input', this.validateField.bind(this, field))
                field.addEventListener('blur', this.validateField.bind(this, field))
            })
        }
    }
    
    submit(event) {
        event.preventDefault()
        
        // Clear previous states
        this.clearMessages()
        
        // Validate form
        if (!this.validate()) {
            return
        }
        
        // Show loading state
        this.setLoading(true)
        
        // Collect form data
        const formData = new FormData(this.element)
        const url = this.element.action
        const method = this.element.method || 'POST'
        
        // Submit form
        this.submitForm(url, method, formData)
            .then(response => this.handleSuccess(response))
            .catch(error => this.handleError(error))
            .finally(() => this.setLoading(false))
    }
    
    async submitForm(url, method, formData) {
        const options = {
            method: method.toUpperCase(),
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]')
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken.getAttribute('content')
        }
        
        const response = await fetch(url, options)
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Network error occurred' }))
            throw new Error(errorData.error || errorData.message || 'Form submission failed')
        }
        
        return response.json()
    }
    
    validate() {
        let isValid = true
        const errors = []
        
        this.fieldTargets.forEach(field => {
            const fieldErrors = this.validateField(field, false)
            if (fieldErrors.length > 0) {
                isValid = false
                errors.push(...fieldErrors)
            }
        })
        
        if (!isValid) {
            this.showErrors(errors)
            
            // Focus first invalid field
            const firstInvalidField = this.element.querySelector('.error')
            if (firstInvalidField) {
                firstInvalidField.focus()
            }
        }
        
        return isValid
    }
    
    validateField(field, showVisualFeedback = true) {
        const errors = []
        const value = field.value.trim()
        const rules = this.getValidationRules(field)
        
        // Required validation
        if (rules.required && !value) {
            errors.push(`${this.getFieldLabel(field)} is required`)
        }
        
        // Email validation
        if (rules.email && value && !this.isValidEmail(value)) {
            errors.push(`${this.getFieldLabel(field)} must be a valid email address`)
        }
        
        // Min length validation
        if (rules.minLength && value.length < rules.minLength) {
            errors.push(`${this.getFieldLabel(field)} must be at least ${rules.minLength} characters`)
        }
        
        // Max length validation
        if (rules.maxLength && value.length > rules.maxLength) {
            errors.push(`${this.getFieldLabel(field)} cannot exceed ${rules.maxLength} characters`)
        }
        
        // Pattern validation
        if (rules.pattern && value && !new RegExp(rules.pattern).test(value)) {
            errors.push(`${this.getFieldLabel(field)} format is invalid`)
        }
        
        // Custom validation
        const customValidator = this.validators.get(field.name)
        if (customValidator && value) {
            const customError = customValidator(value, field)
            if (customError) {
                errors.push(customError)
            }
        }
        
        // Show visual feedback
        if (showVisualFeedback) {
            this.updateFieldValidation(field, errors)
        }
        
        return errors
    }
    
    getValidationRules(field) {
        return {
            required: field.hasAttribute('required'),
            email: field.type === 'email',
            minLength: field.getAttribute('minlength') ? parseInt(field.getAttribute('minlength')) : null,
            maxLength: field.getAttribute('maxlength') ? parseInt(field.getAttribute('maxlength')) : null,
            pattern: field.getAttribute('pattern')
        }
    }
    
    updateFieldValidation(field, errors) {
        const hasErrors = errors.length > 0
        const wrapper = field.closest('.form-group') || field.parentElement
        
        // Update field classes
        field.classList.toggle('border-red-500', hasErrors)
        field.classList.toggle('border-green-500', !hasErrors && field.value.trim())
        
        // Update wrapper classes
        wrapper.classList.toggle('error', hasErrors)
        wrapper.classList.toggle('success', !hasErrors && field.value.trim())
        
        // Update or create error message
        let errorElement = wrapper.querySelector('.field-error')
        if (hasErrors) {
            if (!errorElement) {
                errorElement = document.createElement('div')
                errorElement.className = 'field-error text-xs text-red-500 mt-1'
                wrapper.appendChild(errorElement)
            }
            errorElement.textContent = errors[0] // Show first error
        } else if (errorElement) {
            errorElement.remove()
        }
        
        // Update ARIA attributes
        field.setAttribute('aria-invalid', hasErrors.toString())
        if (hasErrors) {
            field.setAttribute('aria-describedby', field.id + '-error')
            if (errorElement) {
                errorElement.id = field.id + '-error'
            }
        } else {
            field.removeAttribute('aria-describedby')
        }
    }
    
    setLoading(loading) {
        this.loadingValue = loading
        
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = loading
            
            if (loading) {
                if (this.hasLoadingTarget) {
                    this.loadingTarget.classList.remove('hidden')
                }
                this.submitTarget.textContent = 'Processing...'
            } else {
                if (this.hasLoadingTarget) {
                    this.loadingTarget.classList.add('hidden')
                }
                this.submitTarget.textContent = this.originalSubmitText
            }
        }
        
        // Update CSS classes
        if (this.hasLoadingClass) {
            this.element.classList.toggle(this.loadingClass, loading)
        }
    }
    
    handleSuccess(response) {
        if (this.showSuccessValue && response.message) {
            this.showSuccess(response.message)
        }
        
        if (this.resetOnSuccessValue) {
            this.element.reset()
            this.clearValidationStates()
        }
        
        // Show notification
        if (window.notificationManager && response.message) {
            window.notificationManager.success(response.message)
        }
        
        // Dispatch custom event
        this.dispatch('success', { 
            detail: { response, form: this.element } 
        })
        
        // Redirect if specified
        if (response.redirect) {
            window.location.href = response.redirect
        }
    }
    
    handleError(error) {
        const message = error.message || 'An error occurred. Please try again.'
        this.showError(message)
        
        // Show notification
        if (window.notificationManager) {
            window.notificationManager.error(message)
        }
        
        // Dispatch custom event
        this.dispatch('error', { 
            detail: { error, form: this.element } 
        })
    }
    
    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message
            this.errorTarget.classList.remove('hidden')
        }
    }
    
    showSuccess(message) {
        if (this.hasSuccessTarget) {
            this.successTarget.textContent = message
            this.successTarget.classList.remove('hidden')
        }
    }
    
    clearMessages() {
        if (this.hasErrorTarget) {
            this.errorTarget.classList.add('hidden')
        }
        if (this.hasSuccessTarget) {
            this.successTarget.classList.add('hidden')
        }
    }
    
    clearValidationStates() {
        this.fieldTargets.forEach(field => {
            field.classList.remove('border-red-500', 'border-green-500')
            const wrapper = field.closest('.form-group') || field.parentElement
            wrapper.classList.remove('error', 'success')
            
            const errorElement = wrapper.querySelector('.field-error')
            if (errorElement) {
                errorElement.remove()
            }
        })
    }
    
    getFieldLabel(field) {
        const label = field.labels?.[0]?.textContent || 
                     field.getAttribute('placeholder') || 
                     field.name
        return label.replace('*', '').trim()
    }
    
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
    }
    
    // Method to add custom validators
    addValidator(fieldName, validator) {
        this.validators.set(fieldName, validator)
    }
    
    // Method to remove custom validators
    removeValidator(fieldName) {
        this.validators.delete(fieldName)
    }
}