/*
 * Authentication-specific JavaScript
 * Handles OAuth, form enhancements, and auth-related interactions
 */

import './utils/theme.js';
import './utils/accessibility.js';
import './utils/notifications.js';

class AuthManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupOAuthHandlers();
        this.setupFormEnhancements();
        this.setupPasswordStrengthMeter();
    }
    
    setupOAuthHandlers() {
        // Google OAuth handler
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-oauth-provider="google"]')) {
                e.preventDefault();
                this.handleGoogleSignIn();
            }
        });
        
        // GitHub OAuth handler
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-oauth-provider="github"]')) {
                e.preventDefault();
                this.handleGitHubSignIn();
            }
        });
    }
    
    async handleGoogleSignIn() {
        try {
            // Show loading state
            if (window.notificationManager) {
                window.notificationManager.info('Redirecting to Google...', { duration: 2000 });
            }
            
            // Redirect to OAuth endpoint
            window.location.href = '/auth/google';
            
        } catch (error) {
            console.error('Google sign-in error:', error);
            if (window.notificationManager) {
                window.notificationManager.error('Google sign-in failed. Please try again.');
            }
        }
    }
    
    async handleGitHubSignIn() {
        try {
            // Show loading state
            if (window.notificationManager) {
                window.notificationManager.info('Redirecting to GitHub...', { duration: 2000 });
            }
            
            // Redirect to OAuth endpoint
            window.location.href = '/auth/github';
            
        } catch (error) {
            console.error('GitHub sign-in error:', error);
            if (window.notificationManager) {
                window.notificationManager.error('GitHub sign-in failed. Please try again.');
            }
        }
    }
    
    setupFormEnhancements() {
        // Enhanced email validation
        this.setupEmailValidation();
        
        // Enhanced password validation
        this.setupPasswordValidation();
        
        // Form submission enhancements
        this.setupFormSubmission();
    }
    
    setupEmailValidation() {
        const emailInputs = document.querySelectorAll('input[type="email"]');
        
        emailInputs.forEach(input => {
            input.addEventListener('blur', (e) => {
                this.validateEmail(e.target);
            });
            
            input.addEventListener('input', (e) => {
                // Debounced validation
                clearTimeout(input.validationTimeout);
                input.validationTimeout = setTimeout(() => {
                    this.validateEmail(e.target);
                }, 500);
            });
        });
    }
    
    validateEmail(input) {
        const email = input.value.trim();
        const isValid = this.isValidEmail(email);
        const wrapper = input.closest('.form-group');
        
        // Clear previous validation
        this.clearFieldValidation(input);
        
        if (email && !isValid) {
            this.showFieldError(input, 'Please enter a valid email address');
        } else if (isValid) {
            this.showFieldSuccess(input);
        }
        
        return isValid;
    }
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    setupPasswordValidation() {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        
        passwordInputs.forEach(input => {
            // Skip confirm password fields
            if (input.name.includes('confirm') || input.id.includes('confirm')) {
                this.setupPasswordConfirmation(input);
                return;
            }
            
            input.addEventListener('input', (e) => {
                this.validatePassword(e.target);
            });
            
            input.addEventListener('blur', (e) => {
                this.validatePassword(e.target, true);
            });
        });
    }
    
    validatePassword(input, showAllErrors = false) {
        const password = input.value;
        const validation = this.getPasswordStrength(password);
        const wrapper = input.closest('.form-group');
        
        // Clear previous validation
        this.clearFieldValidation(input);
        
        if (password) {
            if (showAllErrors && validation.errors.length > 0) {
                this.showFieldError(input, validation.errors[0]);
            } else if (validation.isStrong) {
                this.showFieldSuccess(input);
            }
            
            // Update strength meter if present
            this.updatePasswordStrengthMeter(input, validation);
        }
        
        return validation.isValid;
    }
    
    getPasswordStrength(password) {
        const errors = [];
        let score = 0;
        
        // Length check
        if (password.length < 8) {
            errors.push('Password must be at least 8 characters long');
        } else {
            score += 1;
        }
        
        // Complexity checks
        if (!/[a-z]/.test(password)) {
            errors.push('Password must contain at least one lowercase letter');
        } else {
            score += 1;
        }
        
        if (!/[A-Z]/.test(password)) {
            errors.push('Password must contain at least one uppercase letter');
        } else {
            score += 1;
        }
        
        if (!/\d/.test(password)) {
            errors.push('Password must contain at least one number');
        } else {
            score += 1;
        }
        
        if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
            errors.push('Password must contain at least one special character');
        } else {
            score += 1;
        }
        
        const strength = score <= 2 ? 'weak' : score <= 3 ? 'medium' : 'strong';
        
        return {
            score,
            strength,
            errors,
            isValid: password.length >= 8,
            isStrong: score >= 4
        };
    }
    
    setupPasswordConfirmation(confirmInput) {
        const passwordInput = document.querySelector('input[name="password"]') || 
                             document.querySelector('input[id="password"]');
        
        if (!passwordInput) return;
        
        const validateConfirmation = () => {
            const password = passwordInput.value;
            const confirmation = confirmInput.value;
            
            this.clearFieldValidation(confirmInput);
            
            if (confirmation) {
                if (password !== confirmation) {
                    this.showFieldError(confirmInput, 'Passwords do not match');
                } else {
                    this.showFieldSuccess(confirmInput);
                }
            }
        };
        
        confirmInput.addEventListener('input', validateConfirmation);
        confirmInput.addEventListener('blur', validateConfirmation);
        passwordInput.addEventListener('input', validateConfirmation);
    }
    
    setupPasswordStrengthMeter() {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        
        passwordInputs.forEach(input => {
            // Skip confirm password fields
            if (input.name.includes('confirm') || input.id.includes('confirm')) {
                return;
            }
            
            // Create strength meter if it doesn't exist
            if (!input.nextElementSibling?.classList.contains('password-strength-meter')) {
                this.createPasswordStrengthMeter(input);
            }
        });
    }
    
    createPasswordStrengthMeter(input) {
        const wrapper = input.closest('.form-group');
        if (!wrapper) return;
        
        const meter = document.createElement('div');
        meter.className = 'password-strength-meter mt-2 hidden';
        meter.innerHTML = `
            <div class="flex gap-1 mb-1">
                <div class="strength-bar flex-1 h-1 bg-gray-200 rounded"></div>
                <div class="strength-bar flex-1 h-1 bg-gray-200 rounded"></div>
                <div class="strength-bar flex-1 h-1 bg-gray-200 rounded"></div>
                <div class="strength-bar flex-1 h-1 bg-gray-200 rounded"></div>
            </div>
            <div class="strength-text text-xs text-gray-500"></div>
        `;
        
        wrapper.appendChild(meter);
    }
    
    updatePasswordStrengthMeter(input, validation) {
        const wrapper = input.closest('.form-group');
        const meter = wrapper?.querySelector('.password-strength-meter');
        
        if (!meter) return;
        
        const bars = meter.querySelectorAll('.strength-bar');
        const textElement = meter.querySelector('.strength-text');
        
        if (input.value) {
            meter.classList.remove('hidden');
            
            // Update bars
            bars.forEach((bar, index) => {
                if (index < validation.score) {
                    bar.className = `strength-bar flex-1 h-1 rounded ${this.getStrengthColor(validation.strength)}`;
                } else {
                    bar.className = 'strength-bar flex-1 h-1 bg-gray-200 rounded';
                }
            });
            
            // Update text
            textElement.textContent = this.getStrengthText(validation.strength);
            textElement.className = `strength-text text-xs ${this.getStrengthTextColor(validation.strength)}`;
            
        } else {
            meter.classList.add('hidden');
        }
    }
    
    getStrengthColor(strength) {
        const colors = {
            weak: 'bg-red-400',
            medium: 'bg-yellow-400',
            strong: 'bg-green-400'
        };
        return colors[strength] || 'bg-gray-200';
    }
    
    getStrengthTextColor(strength) {
        const colors = {
            weak: 'text-red-600',
            medium: 'text-yellow-600',
            strong: 'text-green-600'
        };
        return colors[strength] || 'text-gray-500';
    }
    
    getStrengthText(strength) {
        const texts = {
            weak: 'Weak password',
            medium: 'Medium password',
            strong: 'Strong password'
        };
        return texts[strength] || '';
    }
    
    showFieldError(input, message) {
        const wrapper = input.closest('.form-group');
        if (!wrapper) return;
        
        // Add error styling
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        wrapper.classList.add('error');
        wrapper.classList.remove('success');
        
        // Create or update error message
        let errorElement = wrapper.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error text-xs text-red-500 mt-1';
            wrapper.appendChild(errorElement);
        }
        errorElement.textContent = message;
        
        // Update ARIA attributes
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', input.id + '-error');
        errorElement.id = input.id + '-error';
    }
    
    showFieldSuccess(input) {
        const wrapper = input.closest('.form-group');
        if (!wrapper) return;
        
        // Add success styling
        input.classList.add('border-green-500');
        input.classList.remove('border-red-500');
        wrapper.classList.add('success');
        wrapper.classList.remove('error');
        
        // Remove error message
        const errorElement = wrapper.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
        
        // Update ARIA attributes
        input.setAttribute('aria-invalid', 'false');
        input.removeAttribute('aria-describedby');
    }
    
    clearFieldValidation(input) {
        const wrapper = input.closest('.form-group');
        if (!wrapper) return;
        
        // Remove styling
        input.classList.remove('border-red-500', 'border-green-500');
        wrapper.classList.remove('error', 'success');
        
        // Remove error message
        const errorElement = wrapper.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
        
        // Update ARIA attributes
        input.removeAttribute('aria-invalid');
        input.removeAttribute('aria-describedby');
    }
    
    setupFormSubmission() {
        const forms = document.querySelectorAll('form[data-controller*="form"]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                // Additional validation before submission
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    }
    
    validateForm(form) {
        let isValid = true;
        
        // Validate email fields
        const emailInputs = form.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            if (!this.validateEmail(input)) {
                isValid = false;
            }
        });
        
        // Validate password fields
        const passwordInputs = form.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(input => {
            if (!input.name.includes('confirm') && !input.id.includes('confirm')) {
                if (!this.validatePassword(input, true)) {
                    isValid = false;
                }
            }
        });
        
        return isValid;
    }
}

// Initialize auth manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new AuthManager();
});

console.log('🔐 Auth JavaScript loaded successfully!');