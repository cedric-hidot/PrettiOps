import { Controller } from "@hotwired/stimulus"

/**
 * Email Preview Controller
 * 
 * Handles email rendering, client switching, and email preview functionality
 * for the email client mockup and email template generation.
 * 
 * Targets:
 * - tabs: Email client tabs (Gmail, Outlook, Apple Mail)
 * - content: Email content container
 * - snippet: Snippet preview within email
 * - subject: Email subject line
 * - to: Email recipient field
 * - actions: Email action buttons
 * 
 * Values:
 * - snippetId: ID of the snippet being previewed
 * - activeClient: Currently active email client
 * - template: Email template type
 */
export default class extends Controller {
    static targets = ["tabs", "content", "snippet", "subject", "to", "actions"]
    
    static values = {
        snippetId: String,
        activeClient: String,
        template: String
    }

    connect() {
        console.log("Email preview controller connected")
        this.activeClientValue = this.activeClientValue || 'gmail'
        this.setupEmailPreview()
        this.setupInteractions()
    }

    /**
     * Switch between email clients (Gmail, Outlook, Apple Mail)
     */
    switchClient(event) {
        const client = event.currentTarget.dataset.client || event.currentTarget.textContent.toLowerCase()
        
        // Update active tab
        this.tabsTargets.forEach(tab => tab.classList.remove('active'))
        event.currentTarget.classList.add('active')
        
        // Update active client
        this.activeClientValue = client
        
        // Re-render email with client-specific styling
        this.renderEmailForClient(client)
        
        console.log(`Switched to ${client} preview`)
    }

    /**
     * Render email content for specific client
     */
    renderEmailForClient(client) {
        if (!this.hasContentTarget) return

        const clientStyles = this.getClientStyles(client)
        const emailContent = this.generateEmailHTML(client, clientStyles)
        
        this.contentTarget.innerHTML = emailContent
        
        // Apply client-specific CSS
        this.applyClientStyles(client, clientStyles)
    }

    /**
     * Get client-specific styles and limitations
     */
    getClientStyles(client) {
        const styles = {
            gmail: {
                maxWidth: '640px',
                fontFamily: 'Arial, sans-serif',
                supportsCss: {
                    flexbox: true,
                    grid: false,
                    borderRadius: true,
                    boxShadow: true,
                    gradients: true,
                    transforms: false
                },
                quirks: [
                    'Strips <style> tags in body',
                    'Limited CSS support',
                    'Mobile app has different rendering'
                ]
            },
            outlook: {
                maxWidth: '600px',
                fontFamily: 'Calibri, Arial, sans-serif',
                supportsCss: {
                    flexbox: false,
                    grid: false,
                    borderRadius: false,
                    boxShadow: false,
                    gradients: false,
                    transforms: false
                },
                quirks: [
                    'Uses Word rendering engine',
                    'Very limited CSS support',
                    'Requires table-based layouts',
                    'DPI scaling issues'
                ]
            },
            applemail: {
                maxWidth: '600px',
                fontFamily: '-apple-system, BlinkMacSystemFont, sans-serif',
                supportsCss: {
                    flexbox: true,
                    grid: false,
                    borderRadius: true,
                    boxShadow: true,
                    gradients: true,
                    transforms: true
                },
                quirks: [
                    'Excellent CSS support',
                    'Dark mode support',
                    'Retina display considerations'
                ]
            }
        }

        return styles[client] || styles.gmail
    }

    /**
     * Generate email HTML for the specified client
     */
    generateEmailHTML(client, styles) {
        const snippet = this.getSnippetData()
        
        return `
            <div class="email-preview" data-client="${client}">
                <div class="email-header" style="font-family: ${styles.fontFamily}">
                    <div class="email-from">
                        <strong>From:</strong> ${this.getCurrentUser().email}
                    </div>
                    <div class="email-to">
                        <strong>To:</strong> ${this.getEmailRecipient()}
                    </div>
                    <div class="email-subject">
                        <strong>Subject:</strong> ${this.getEmailSubject()}
                    </div>
                    <div class="email-date">
                        <strong>Date:</strong> ${new Date().toLocaleDateString()}
                    </div>
                </div>
                
                <div class="email-body" style="max-width: ${styles.maxWidth}; font-family: ${styles.fontFamily}">
                    ${this.generateEmailBody(client, styles, snippet)}
                </div>
                
                ${this.generateEmailFooter(client, styles)}
            </div>
        `
    }

    /**
     * Generate email body content
     */
    generateEmailBody(client, styles, snippet) {
        const securityNotice = snippet.hasHiddenTokens ? this.generateSecurityNotice() : ''
        
        return `
            <div class="email-content">
                <p style="margin-bottom: 16px;">Hi team,</p>
                
                <p style="margin-bottom: 16px;">
                    I've created a code snippet that I think could be useful. 
                    Take a look and let me know your thoughts:
                </p>
                
                ${securityNotice}
                
                ${this.generateSnippetEmbed(client, styles, snippet)}
                
                <p style="margin-top: 20px; margin-bottom: 16px;">
                    Looking forward to your feedback!
                </p>
                
                <p style="margin-top: 20px;">
                    Best regards,<br>
                    <strong>${this.getCurrentUser().fullName}</strong><br>
                    ${this.getCurrentUser().jobTitle || ''}<br>
                    <a href="mailto:${this.getCurrentUser().email}" style="color: #6f00ff;">
                        ${this.getCurrentUser().email}
                    </a>
                </p>
            </div>
        `
    }

    /**
     * Generate security notice
     */
    generateSecurityNotice() {
        return `
            <div class="security-notice" style="
                background-color: #eff6ff;
                border: 1px solid rgba(59, 130, 246, 0.2);
                border-radius: 8px;
                padding: 16px;
                margin: 20px 0;
            ">
                <div style="
                    font-size: 14px;
                    font-weight: 600;
                    color: #1e40af;
                    margin-bottom: 6px;
                ">
                    🔒 Secure Content Protected
                </div>
                <p style="
                    font-size: 13px;
                    color: #1e40af;
                    line-height: 1.4;
                    margin: 0;
                ">
                    This email contains code with sensitive data that has been automatically 
                    secured by PrettiOps. Click on protected tokens to verify with 2FA.
                </p>
            </div>
        `
    }

    /**
     * Generate snippet embed for email
     */
    generateSnippetEmbed(client, styles, snippet) {
        const borderRadius = styles.supportsCss.borderRadius ? '12px' : '0'
        const boxShadow = styles.supportsCss.boxShadow ? '0 1px 3px rgba(0, 0, 0, 0.1)' : 'none'
        
        return `
            <div class="snippet-container" style="
                background-color: #fafafa;
                border: 1px solid #e7e5e4;
                border-radius: ${borderRadius};
                overflow: hidden;
                margin: 24px 0;
                box-shadow: ${boxShadow};
            ">
                ${this.generateSnippetHeader(client, styles, snippet)}
                ${this.generateSnippetCode(client, styles, snippet)}
                ${this.generateSnippetBranding(client, styles)}
            </div>
        `
    }

    /**
     * Generate snippet header
     */
    generateSnippetHeader(client, styles, snippet) {
        return `
            <div class="snippet-header" style="
                background-color: #ffffff;
                padding: 16px 20px;
                border-bottom: 1px solid #f5f4f4;
            ">
                <div style="
                    font-size: 12px;
                    color: #78716c;
                    margin-bottom: 12px;
                ">
                    <span style="
                        background-color: #f3ebff;
                        color: #4a0099;
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-weight: 600;
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: 0.025em;
                    ">${snippet.language}</span> • 
                    ${snippet.lineCount} lines • 
                    ${snippet.tags.join(', ')}
                </div>
                <h3 style="
                    font-size: 18px;
                    line-height: 24px;
                    color: #1c1917;
                    font-weight: 600;
                    margin: 0 0 12px 0;
                ">${snippet.title}</h3>
                <div class="snippet-actions">
                    ${this.generateActionButtons(client, styles, snippet)}
                </div>
            </div>
        `
    }

    /**
     * Generate action buttons
     */
    generateActionButtons(client, styles, snippet) {
        const buttons = [
            { text: '📋 Copy', action: 'copy' },
            { text: '⬇️ Download', action: 'download' },
            { text: '🔧 Open in IDE', action: 'ide' },
            { text: '👁️ View Full', action: 'view', primary: true }
        ]

        return buttons.map(button => {
            const isPrimary = button.primary
            const bgColor = isPrimary ? '#6f00ff' : '#f5f4f4'
            const textColor = isPrimary ? '#ffffff' : '#57534e'
            const borderColor = isPrimary ? '#6f00ff' : '#e7e5e4'

            return `
                <a href="${this.getActionUrl(button.action, snippet)}" style="
                    display: inline-block;
                    padding: 8px 12px;
                    margin-right: 8px;
                    margin-bottom: 8px;
                    background-color: ${bgColor};
                    border: 1px solid ${borderColor};
                    border-radius: 6px;
                    color: ${textColor};
                    font-size: 12px;
                    font-weight: 500;
                    text-decoration: none;
                ">${button.text}</a>
            `
        }).join('')
    }

    /**
     * Generate snippet code block
     */
    generateSnippetCode(client, styles, snippet) {
        const processedContent = this.processCodeForEmail(snippet.content, client)
        
        return `
            <div class="snippet-code" style="
                background-color: #1e1e1e;
                color: #d4d4d4;
                padding: 20px;
                font-family: 'Monaco', 'Consolas', 'Courier New', monospace;
                font-size: 13px;
                line-height: 20px;
                overflow-x: auto;
                white-space: pre;
            ">${processedContent}</div>
        `
    }

    /**
     * Generate PrettiOps branding
     */
    generateSnippetBranding(client, styles) {
        return `
            <div class="snippet-branding" style="
                background-color: #f5f4f4;
                padding: 12px 20px;
                border-top: 1px solid #f5f4f4;
                font-size: 11px;
                color: #78716c;
            ">
                <span style="
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    background-color: #6f00ff;
                    color: #ffffff;
                    text-align: center;
                    line-height: 16px;
                    font-size: 10px;
                    font-weight: 700;
                    border-radius: 3px;
                    vertical-align: middle;
                    margin-right: 6px;
                ">P</span>
                Styled with <a href="https://prettiops.dev" style="color: #6f00ff; text-decoration: none; font-weight: 600;">PrettiOps</a> • 
                Expires in 23h 45m • 
                <a href="${this.getSnippetUrl()}/settings" style="color: #6f00ff; text-decoration: none; font-weight: 600;">Manage</a>
            </div>
        `
    }

    /**
     * Generate email footer
     */
    generateEmailFooter(client, styles) {
        return `
            <div class="email-footer" style="
                margin-top: 32px;
                padding-top: 20px;
                border-top: 1px solid #e7e5e4;
                font-size: 12px;
                color: #78716c;
                text-align: center;
            ">
                <p>
                    <a href="#" style="color: #78716c;">Unsubscribe</a> • 
                    <a href="https://prettiops.dev/privacy" style="color: #78716c;">Privacy Policy</a> • 
                    <a href="https://prettiops.dev" style="color: #78716c;">PrettiOps</a>
                </p>
            </div>
        `
    }

    /**
     * Process code content for email compatibility
     */
    processCodeForEmail(content, client) {
        // Escape HTML
        let processed = this.escapeHtml(content)
        
        // Apply syntax highlighting for email-safe rendering
        processed = this.applyEmailSyntaxHighlighting(processed)
        
        // Handle hidden tokens
        processed = this.processHiddenTokens(processed)
        
        return processed
    }

    /**
     * Apply email-safe syntax highlighting
     */
    applyEmailSyntaxHighlighting(content) {
        // Basic patterns for syntax highlighting that work in email
        const patterns = [
            { pattern: /(\/\/.*$)/gm, style: 'color: #6a9955;' }, // Comments
            { pattern: /\b(const|let|var|function|class|if|else|for|while|return|import|export|from)\b/g, style: 'color: #c586c0;' }, // Keywords
            { pattern: /(['"])((?:(?!\1)[^\\]|\\.)*)(\1)/g, style: 'color: #ce9178;' }, // Strings
            { pattern: /\b\d+(\.\d+)?\b/g, style: 'color: #b5cea8;' } // Numbers
        ]

        let highlighted = content
        patterns.forEach(({ pattern, style }) => {
            highlighted = highlighted.replace(pattern, `<span style="${style}">$&</span>`)
        })

        return highlighted
    }

    /**
     * Process hidden tokens for email
     */
    processHiddenTokens(content) {
        // Pattern to match common sensitive data
        const tokenPattern = /(api[_-]?key|token|secret|password|pwd)\s*[:=]\s*['"][^'"]+['"]/gi
        
        return content.replace(tokenPattern, (match) => {
            return `<a href="${this.getVerificationUrl()}" style="
                display: inline-block;
                background-color: #f3ebff;
                color: #4a0099;
                border: 1px solid rgba(111, 0, 255, 0.2);
                border-radius: 4px;
                padding: 2px 8px;
                font-size: 11px;
                font-weight: 600;
                text-decoration: none;
                font-family: 'Monaco', 'Consolas', 'Courier New', monospace;
            ">🔒 Hidden — 2FA required</a>`
        })
    }

    /**
     * Apply client-specific styles to the preview
     */
    applyClientStyles(client, styles) {
        const previewElement = this.contentTarget.querySelector('.email-preview')
        if (!previewElement) return

        // Apply client-specific styling
        previewElement.style.maxWidth = styles.maxWidth
        previewElement.style.fontFamily = styles.fontFamily

        // Add client-specific classes
        previewElement.className = `email-preview email-preview--${client}`

        // Show/hide features based on client support
        this.toggleClientFeatures(client, styles)
    }

    /**
     * Toggle features based on client support
     */
    toggleClientFeatures(client, styles) {
        const features = this.contentTarget.querySelectorAll('[data-feature]')
        
        features.forEach(feature => {
            const featureName = feature.dataset.feature
            const isSupported = styles.supportsCss[featureName]
            
            if (isSupported) {
                feature.classList.remove('unsupported')
            } else {
                feature.classList.add('unsupported')
                
                // Show fallback if available
                const fallback = feature.querySelector('[data-fallback]')
                if (fallback) {
                    fallback.style.display = 'block'
                }
            }
        })
    }

    /**
     * Setup email preview functionality
     */
    setupEmailPreview() {
        // Load initial preview
        this.renderEmailForClient(this.activeClientValue)
        
        // Set up periodic refresh if needed
        if (window.editorController) {
            this.contentRefreshInterval = setInterval(() => {
                if (window.editorController.hasContentChanged) {
                    this.refreshPreview()
                    window.editorController.hasContentChanged = false
                }
            }, 1000)
        }
    }

    /**
     * Setup email interactions
     */
    setupInteractions() {
        // Handle email action clicks
        document.addEventListener('click', (event) => {
            if (event.target.matches('.email-action')) {
                event.preventDefault()
                this.handleEmailAction(event.target.dataset.action)
            }
        })
    }

    /**
     * Handle email actions (reply, forward, etc.)
     */
    handleEmailAction(action) {
        switch (action) {
            case 'reply':
                console.log('Reply to email')
                break
            case 'forward':
                console.log('Forward email')
                break
            case 'archive':
                console.log('Archive email')
                break
            case 'delete':
                console.log('Delete email')
                break
            case 'star':
                console.log('Star email')
                break
        }
    }

    /**
     * Refresh preview content
     */
    refreshPreview() {
        this.renderEmailForClient(this.activeClientValue)
    }

    /**
     * Export email as HTML
     */
    exportAsHtml() {
        const emailHtml = this.contentTarget.innerHTML
        const blob = new Blob([emailHtml], { type: 'text/html' })
        const url = URL.createObjectURL(blob)
        
        const link = document.createElement('a')
        link.href = url
        link.download = `email-preview-${Date.now()}.html`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        
        URL.revokeObjectURL(url)
    }

    /**
     * Send test email
     */
    async sendTestEmail() {
        try {
            const response = await fetch('/api/email/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    snippetId: this.snippetIdValue,
                    client: this.activeClientValue,
                    recipient: this.getEmailRecipient()
                })
            })

            if (response.ok) {
                this.showNotification('Test email sent successfully!', 'success')
            } else {
                throw new Error('Failed to send test email')
            }
        } catch (error) {
            console.error('Error sending test email:', error)
            this.showNotification('Failed to send test email', 'error')
        }
    }

    /**
     * Utility functions
     */
    getSnippetData() {
        // Get snippet data from editor or API
        if (window.editorController) {
            return {
                title: window.editorController.getSnippetTitle() || 'Untitled Snippet',
                content: window.editorController.getEditorContent(),
                language: window.editorController.languageValue || 'javascript',
                lineCount: window.editorController.getLineCount(),
                tags: window.editorController.getTags() || [],
                hasHiddenTokens: window.editorController.hasHiddenTokens()
            }
        }
        
        return {
            title: 'Sample Snippet',
            content: '// Sample code\nconsole.log("Hello, World!");',
            language: 'javascript',
            lineCount: 2,
            tags: ['sample'],
            hasHiddenTokens: false
        }
    }

    getCurrentUser() {
        return {
            fullName: 'John Doe',
            email: 'john.doe@company.com',
            jobTitle: 'Senior Developer'
        }
    }

    getEmailRecipient() {
        return this.hasToTarget ? this.toTarget.textContent : 'team@company.com'
    }

    getEmailSubject() {
        return this.hasSubjectTarget ? this.subjectTarget.textContent : 'Code Snippet'
    }

    getActionUrl(action, snippet) {
        const baseUrl = window.location.origin
        const snippetId = this.snippetIdValue || 'preview'
        
        switch (action) {
            case 'copy':
                return `${baseUrl}/snippets/${snippetId}?action=copy`
            case 'download':
                return `${baseUrl}/snippets/${snippetId}?action=download`
            case 'ide':
                return `${baseUrl}/snippets/${snippetId}?action=ide`
            case 'view':
            default:
                return `${baseUrl}/snippets/${snippetId}`
        }
    }

    getSnippetUrl() {
        return `${window.location.origin}/snippets/${this.snippetIdValue || 'preview'}`
    }

    getVerificationUrl() {
        return `${window.location.origin}/auth/verify?token=${this.snippetIdValue}`
    }

    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    showNotification(message, type = 'info') {
        if (window.notificationManager) {
            window.notificationManager.show(message, type)
        } else {
            console.log(`${type.toUpperCase()}: ${message}`)
        }
    }

    disconnect() {
        if (this.contentRefreshInterval) {
            clearInterval(this.contentRefreshInterval)
        }
    }
}