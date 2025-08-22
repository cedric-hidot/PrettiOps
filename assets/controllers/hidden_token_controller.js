import { Controller } from "@hotwired/stimulus"

/**
 * Hidden Token Controller
 * 
 * Handles the security component for masked sensitive data with 2FA verification.
 * Manages click interactions, 2FA verification flow, and token reveal.
 * 
 * Targets:
 * - icon: Lock icon element
 * - text: Hidden text element
 * 
 * Values:
 * - url: Verification URL
 * - tokenId: Token identifier
 * - revealed: Whether token is revealed
 */
export default class extends Controller {
    static targets = ["icon", "text"]
    
    static values = {
        url: String,
        tokenId: String,
        revealed: Boolean
    }

    connect() {
        console.log("Hidden token controller connected")
        this.setupAriaLabels()
        this.revealedValue = false
    }

    /**
     * Handle click to reveal token
     */
    async reveal(event) {
        event.preventDefault()
        event.stopPropagation()
        
        if (this.revealedValue) {
            // Already revealed, hide again
            this.hide()
            return
        }

        try {
            this.showLoading()
            
            // Check if user is already verified for this session
            const isVerified = await this.checkVerificationStatus()
            
            if (isVerified) {
                await this.revealToken()
            } else {
                await this.initiate2FA()
            }
            
        } catch (error) {
            console.error("Failed to reveal token:", error)
            this.showError("Failed to reveal sensitive data")
        } finally {
            this.hideLoading()
        }
    }

    /**
     * Check if user is already verified for this session
     */
    async checkVerificationStatus() {
        try {
            const response = await fetch('/api/security/verification-status', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            
            if (response.ok) {
                const data = await response.json()
                return data.verified && data.validUntil > Date.now()
            }
            
            return false
        } catch (error) {
            console.error("Error checking verification status:", error)
            return false
        }
    }

    /**
     * Initiate 2FA verification
     */
    async initiate2FA() {
        // Create and show 2FA modal
        const modal = this.create2FAModal()
        this.show2FAModal(modal)
        
        // Start verification process
        try {
            await this.send2FACode()
        } catch (error) {
            console.error("Failed to send 2FA code:", error)
            this.showError("Failed to send verification code")
        }
    }

    /**
     * Send 2FA verification code
     */
    async send2FACode() {
        const response = await fetch('/api/security/send-2fa', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                tokenId: this.tokenIdValue,
                purpose: 'reveal_token'
            })
        })
        
        if (!response.ok) {
            throw new Error("Failed to send 2FA code")
        }
        
        const data = await response.json()
        
        // Update modal with sent confirmation
        this.update2FAModal("Verification code sent to your registered email address and phone number.")
        
        return data
    }

    /**
     * Verify 2FA code
     */
    async verify2FACode(code) {
        const response = await fetch('/api/security/verify-2fa', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                code: code,
                tokenId: this.tokenIdValue,
                purpose: 'reveal_token'
            })
        })
        
        if (!response.ok) {
            const error = await response.json()
            throw new Error(error.message || "Invalid verification code")
        }
        
        return response.json()
    }

    /**
     * Reveal the actual token
     */
    async revealToken() {
        try {
            const response = await fetch(`/api/security/reveal-token/${this.tokenIdValue}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            
            if (!response.ok) {
                throw new Error("Failed to retrieve token")
            }
            
            const data = await response.json()
            
            // Update UI to show revealed token
            this.updateTokenDisplay(data.value, true)
            this.revealedValue = true
            
            // Auto-hide after specified time
            if (data.autoHideAfter) {
                setTimeout(() => {
                    this.hide()
                }, data.autoHideAfter * 1000)
            }
            
            this.trackReveal()
            
        } catch (error) {
            console.error("Failed to reveal token:", error)
            throw error
        }
    }

    /**
     * Hide revealed token
     */
    hide() {
        this.updateTokenDisplay("Hidden — 2FA required", false)
        this.revealedValue = false
        this.trackHide()
    }

    /**
     * Update token display
     */
    updateTokenDisplay(text, revealed) {
        if (this.hasTextTarget) {
            this.textTarget.textContent = text
        }
        
        if (this.hasIconTarget) {
            this.iconTarget.textContent = revealed ? "👁️" : "🔒"
        }
        
        // Update CSS classes
        this.element.classList.toggle('hidden-token--revealed', revealed)
        
        // Update accessibility
        this.element.setAttribute('aria-label', 
            revealed ? 'Sensitive data revealed - click to hide' : 'Hidden sensitive data - click to verify and reveal'
        )
        
        // Update title
        this.element.title = revealed ? 'Click to hide sensitive data' : 'Click to verify and reveal sensitive data'
    }

    /**
     * Create 2FA verification modal
     */
    create2FAModal() {
        const modal = document.createElement('div')
        modal.className = 'modal-overlay'
        modal.innerHTML = `
            <div class="modal modal-sm" role="dialog" aria-labelledby="2fa-title" aria-describedby="2fa-description">
                <div class="modal-header">
                    <h3 id="2fa-title" class="modal-title">
                        <span class="modal-icon">🔒</span>
                        Verify Identity
                    </h3>
                    <button class="modal-close" aria-label="Close verification dialog">×</button>
                </div>
                <div class="modal-body">
                    <div id="2fa-description" class="modal-description">
                        To protect sensitive data, we need to verify your identity with two-factor authentication.
                    </div>
                    <div class="2fa-status" data-2fa-target="status">
                        Sending verification code...
                    </div>
                    <form class="2fa-form hidden" data-2fa-target="form">
                        <div class="form-group">
                            <label for="2fa-code" class="form-label">Verification Code</label>
                            <input 
                                type="text" 
                                id="2fa-code" 
                                class="form-input" 
                                placeholder="Enter 6-digit code"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                required
                                autocomplete="one-time-code"
                                data-2fa-target="codeInput"
                            >
                            <div class="form-help">Enter the 6-digit code sent to your email and phone</div>
                            <div class="form-error" data-2fa-target="error" role="alert" aria-live="polite"></div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-action="click->hidden-token#cancel2FA">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" data-2fa-target="submitBtn">
                                <span data-2fa-target="submitText">Verify</span>
                                <span class="loading-spinner hidden" data-2fa-target="submitLoading"></span>
                            </button>
                        </div>
                    </form>
                    <div class="2fa-help">
                        <p>Didn't receive a code? <button type="button" class="link-button" data-action="click->hidden-token#resend2FA">Resend</button></p>
                        <p>Having trouble? <a href="/support/2fa" target="_blank">Get help</a></p>
                    </div>
                </div>
            </div>
        `
        
        // Add event listeners
        this.setup2FAModalEvents(modal)
        
        return modal
    }

    /**
     * Setup 2FA modal event listeners
     */
    setup2FAModalEvents(modal) {
        // Close button
        const closeBtn = modal.querySelector('.modal-close')
        closeBtn.addEventListener('click', () => this.close2FAModal())
        
        // Form submission
        const form = modal.querySelector('.2fa-form')
        form.addEventListener('submit', async (event) => {
            event.preventDefault()
            await this.handle2FASubmission(modal)
        })
        
        // Code input formatting
        const codeInput = modal.querySelector('[data-2fa-target="codeInput"]')
        codeInput.addEventListener('input', (event) => {
            // Auto-format and validate
            const value = event.target.value.replace(/\D/g, '').slice(0, 6)
            event.target.value = value
            
            // Auto-submit when 6 digits entered
            if (value.length === 6) {
                form.dispatchEvent(new Event('submit'))
            }
        })
        
        // Click outside to close
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                this.close2FAModal()
            }
        })
        
        // Escape key to close
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.close2FAModal()
            }
        })
    }

    /**
     * Show 2FA modal
     */
    show2FAModal(modal) {
        document.body.appendChild(modal)
        
        // Trigger animation
        requestAnimationFrame(() => {
            modal.classList.add('modal-overlay--active')
        })
        
        // Focus management
        const codeInput = modal.querySelector('[data-2fa-target="codeInput"]')
        if (codeInput) {
            setTimeout(() => codeInput.focus(), 300)
        }
        
        this.current2FAModal = modal
    }

    /**
     * Update 2FA modal content
     */
    update2FAModal(message) {
        if (!this.current2FAModal) return
        
        const status = this.current2FAModal.querySelector('[data-2fa-target="status"]')
        const form = this.current2FAModal.querySelector('[data-2fa-target="form"]')
        
        status.textContent = message
        form.classList.remove('hidden')
        
        const codeInput = form.querySelector('[data-2fa-target="codeInput"]')
        if (codeInput) {
            codeInput.focus()
        }
    }

    /**
     * Handle 2FA form submission
     */
    async handle2FASubmission(modal) {
        const codeInput = modal.querySelector('[data-2fa-target="codeInput"]')
        const submitBtn = modal.querySelector('[data-2fa-target="submitBtn"]')
        const submitText = modal.querySelector('[data-2fa-target="submitText"]')
        const submitLoading = modal.querySelector('[data-2fa-target="submitLoading"]')
        const errorElement = modal.querySelector('[data-2fa-target="error"]')
        
        const code = codeInput.value.trim()
        
        if (code.length !== 6) {
            this.show2FAError(errorElement, "Please enter a 6-digit verification code")
            return
        }
        
        // Show loading state
        submitBtn.disabled = true
        submitText.classList.add('hidden')
        submitLoading.classList.remove('hidden')
        errorElement.textContent = ''
        
        try {
            await this.verify2FACode(code)
            
            // Verification successful
            this.close2FAModal()
            await this.revealToken()
            
        } catch (error) {
            this.show2FAError(errorElement, error.message)
        } finally {
            // Reset loading state
            submitBtn.disabled = false
            submitText.classList.remove('hidden')
            submitLoading.classList.add('hidden')
        }
    }

    /**
     * Show 2FA error
     */
    show2FAError(errorElement, message) {
        errorElement.textContent = message
        errorElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    }

    /**
     * Cancel 2FA verification
     */
    cancel2FA() {
        this.close2FAModal()
    }

    /**
     * Resend 2FA code
     */
    async resend2FA() {
        try {
            await this.send2FACode()
            this.showNotification("Verification code resent", "success")
        } catch (error) {
            console.error("Failed to resend 2FA code:", error)
            this.showError("Failed to resend verification code")
        }
    }

    /**
     * Close 2FA modal
     */
    close2FAModal() {
        if (this.current2FAModal) {
            this.current2FAModal.classList.remove('modal-overlay--active')
            
            setTimeout(() => {
                if (this.current2FAModal && this.current2FAModal.parentNode) {
                    this.current2FAModal.parentNode.removeChild(this.current2FAModal)
                }
                this.current2FAModal = null
            }, 300)
        }
    }

    /**
     * Show loading state
     */
    showLoading() {
        this.element.classList.add('hidden-token--loading')
        
        if (this.hasIconTarget) {
            this.iconTarget.textContent = "⏳"
        }
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        this.element.classList.remove('hidden-token--loading')
        
        if (this.hasIconTarget && !this.revealedValue) {
            this.iconTarget.textContent = "🔒"
        }
    }

    /**
     * Setup ARIA labels
     */
    setupAriaLabels() {
        if (!this.element.hasAttribute('aria-label')) {
            this.element.setAttribute('aria-label', 'Hidden sensitive data - click to verify and reveal')
        }
        
        if (!this.element.hasAttribute('role')) {
            this.element.setAttribute('role', 'button')
        }
        
        if (!this.element.hasAttribute('tabindex')) {
            this.element.setAttribute('tabindex', '0')
        }
    }

    /**
     * Track user interactions for security audit
     */
    trackReveal() {
        if (window.analytics) {
            window.analytics.track('token_revealed', {
                token_id: this.tokenIdValue,
                timestamp: new Date().toISOString(),
                user_agent: navigator.userAgent,
                ip_address: '{{ user_ip }}' // This would be filled by backend
            })
        }
        
        console.log("Token revealed:", this.tokenIdValue)
    }

    trackHide() {
        if (window.analytics) {
            window.analytics.track('token_hidden', {
                token_id: this.tokenIdValue,
                timestamp: new Date().toISOString()
            })
        }
        
        console.log("Token hidden:", this.tokenIdValue)
    }

    /**
     * Utility functions
     */
    showNotification(message, type = 'info') {
        if (window.notificationManager) {
            window.notificationManager.show(message, type)
        } else {
            console.log(`${type.toUpperCase()}: ${message}`)
        }
    }

    showError(message) {
        this.showNotification(message, 'error')
    }
}