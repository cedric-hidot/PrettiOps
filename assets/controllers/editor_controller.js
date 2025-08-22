import { Controller } from "@hotwired/stimulus"

/**
 * Monaco Editor Controller for PrettiOps
 * 
 * This controller manages the Monaco Editor integration providing a rich code editing
 * experience with syntax highlighting, auto-save functionality, and real-time preview
 * generation. It handles editor initialization, content management, and provides
 * fallback mechanisms for environments where Monaco Editor cannot be loaded.
 * 
 * @class EditorController
 * @extends {Controller}
 * 
 * @example
 * // HTML usage
 * <div data-controller="editor"
 *      data-editor-snippet-id-value="123"
 *      data-editor-language-value="javascript"
 *      data-editor-auto-save-value="true">
 *   <div data-editor-target="editor"></div>
 *   <select data-editor-target="languageSelect" data-action="change->editor#updateLanguage">
 *     <option value="javascript">JavaScript</option>
 *   </select>
 * </div>
 * 
 * @requires monaco-editor - For rich code editing experience
 * @requires notificationManager - For user feedback
 * @requires errorBoundary - For error handling
 * 
 * @see {@link https://microsoft.github.io/monaco-editor/} Monaco Editor Documentation
 * @see {@link https://stimulus.hotwired.dev/} Stimulus Framework Documentation
 * 
 * Targets:
 * @property {HTMLElement} editorTarget - Main Monaco editor container
 * @property {HTMLElement} containerTarget - Editor wrapper container  
 * @property {HTMLSelectElement} languageSelectTarget - Language selector dropdown
 * @property {HTMLElement} languageDisplayTarget - Current language display
 * @property {HTMLInputElement} securityToggleTarget - Security mode toggle checkbox
 * @property {HTMLButtonElement} shareBtnTarget - Share snippet button
 * @property {HTMLButtonElement} sendBtnTarget - Send email button
 * @property {HTMLButtonElement} expandBtnTarget - Toggle expanded view button
 * @property {HTMLElement} previewPaneTarget - Preview pane container
 * @property {HTMLElement[]} previewTabTargets - Preview tab navigation buttons
 * @property {HTMLElement} previewContentTarget - Preview content display area
 * @property {HTMLElement} emailSnippetPreviewTarget - Email-specific preview area
 * @property {HTMLElement} lineCountTarget - Line count display element
 * @property {HTMLElement} charCountTarget - Character count display element
 * 
 * Values:
 * @property {string} snippetIdValue - Current snippet unique identifier
 * @property {string} languageValue - Programming language (e.g., 'javascript', 'python')
 * @property {string} themeValue - Editor theme ('light', 'dark', 'prettiops-dark')
 * @property {string} contentValue - Initial editor content
 * @property {boolean} autoSaveValue - Enable/disable auto-save functionality
 * 
 * @author PrettiOps Development Team
 * @since 1.0.0
 * @version 1.0.0
 */
export default class extends Controller {
    static targets = [
        "editor", "container", "languageSelect", "languageDisplay", 
        "securityToggle", "shareBtn", "sendBtn", "expandBtn",
        "previewPane", "previewTab", "previewContent", "emailSnippetPreview",
        "lineCount", "charCount"
    ]
    
    static values = {
        snippetId: String,
        language: String,
        theme: String,
        content: String,
        autoSave: Boolean
    }

    connect() {
        console.log("Editor controller connected")
        this.initializeEditor()
        this.setupAutoSave()
        this.setupKeyboardShortcuts()
        this.updatePreview()
        
        // Store reference globally for other controllers
        window.editorController = this
    }

    disconnect() {
        if (this.editor) {
            this.editor.dispose()
        }
        if (this.autoSaveTimer) {
            clearTimeout(this.autoSaveTimer)
        }
        window.editorController = null
    }

    /**
     * Initialize Monaco Editor
     */
    async initializeEditor() {
        try {
            // Import Monaco Editor dynamically
            const monaco = await import('monaco-editor')
            
            // Configure Monaco Editor
            monaco.editor.defineTheme('prettiops-dark', {
                base: 'vs-dark',
                inherit: true,
                rules: [
                    { token: 'comment', foreground: '6a9955' },
                    { token: 'keyword', foreground: 'c586c0' },
                    { token: 'string', foreground: 'ce9178' },
                    { token: 'number', foreground: 'b5cea8' },
                    { token: 'function', foreground: 'dcdcaa' },
                    { token: 'class', foreground: '4ec9b0' },
                    { token: 'variable', foreground: '9cdcfe' }
                ],
                colors: {
                    'editor.background': '#1e1e1e',
                    'editor.foreground': '#d4d4d4'
                }
            })

            // Create editor instance
            this.editor = monaco.editor.create(this.editorTarget, {
                value: this.contentValue || '// Start coding here...',
                language: this.languageValue || 'javascript',
                theme: this.themeValue || 'prettiops-dark',
                automaticLayout: true,
                fontSize: 14,
                fontFamily: 'Fira Code, Monaco, Consolas, Courier New, monospace',
                fontLigatures: true,
                lineNumbers: 'on',
                minimap: { enabled: true },
                scrollBeyondLastLine: false,
                wordWrap: 'on',
                tabSize: 2,
                insertSpaces: true,
                detectIndentation: true,
                folding: true,
                bracketMatching: 'always',
                autoIndent: 'full',
                formatOnPaste: true,
                formatOnType: true,
                suggestOnTriggerCharacters: true,
                quickSuggestions: true,
                parameterHints: { enabled: true },
                hover: { enabled: true }
            })

            // Setup editor event listeners
            this.setupEditorEvents()
            
            console.log("Monaco Editor initialized successfully")
        } catch (error) {
            console.error("Failed to initialize Monaco Editor:", error)
            this.fallbackToTextarea()
        }
    }

    /**
     * Setup editor event listeners
     */
    setupEditorEvents() {
        if (!this.editor) return

        // Content change listener
        this.editor.onDidChangeModelContent(() => {
            this.updateStats()
            this.updatePreview()
            
            if (this.autoSaveValue) {
                this.scheduleAutoSave()
            }
        })

        // Focus/blur listeners
        this.editor.onDidFocusEditorText(() => {
            this.element.classList.add('editor-focused')
        })

        this.editor.onDidBlurEditorText(() => {
            this.element.classList.remove('editor-focused')
        })

        // Cursor position change
        this.editor.onDidChangeCursorPosition(() => {
            this.updateCursorInfo()
        })
    }

    /**
     * Fallback to textarea if Monaco fails
     */
    fallbackToTextarea() {
        const textarea = document.createElement('textarea')
        textarea.className = 'monaco-editor-placeholder'
        textarea.value = this.contentValue || '// Start coding here...'
        textarea.placeholder = 'Enter your code here...'
        
        // Replace editor container
        this.editorTarget.innerHTML = ''
        this.editorTarget.appendChild(textarea)
        
        // Setup textarea events
        textarea.addEventListener('input', () => {
            this.updateStats()
            this.updatePreview()
            if (this.autoSaveValue) {
                this.scheduleAutoSave()
            }
        })

        this.textareaFallback = textarea
        console.log("Using textarea fallback")
    }

    /**
     * Update editor language
     */
    updateLanguage(event) {
        const language = event ? event.target.value : this.languageValue
        
        if (this.editor) {
            const model = this.editor.getModel()
            if (model) {
                monaco.editor.setModelLanguage(model, language)
            }
        }

        // Update language display
        if (this.hasLanguageDisplayTarget) {
            this.languageDisplayTarget.textContent = this.capitalizeFirst(language)
        }

        this.languageValue = language
        this.updatePreview()
        
        console.log(`Language changed to: ${language}`)
    }

    /**
     * Update editor theme
     */
    updateTheme(event) {
        const theme = event ? event.target.value : this.themeValue
        
        if (this.editor) {
            this.editor.updateOptions({ theme: theme === 'dark' ? 'prettiops-dark' : theme })
        }

        this.themeValue = theme
        console.log(`Theme changed to: ${theme}`)
    }

    /**
     * Update security settings
     */
    updateSecurity(event) {
        const enabled = event ? event.target.checked : this.securityToggleTarget.checked
        
        // Update preview with security settings
        this.updatePreview()
        
        console.log(`Security mode: ${enabled ? 'enabled' : 'disabled'}`)
    }

    /**
     * Format code
     */
    async format() {
        if (this.editor) {
            try {
                await this.editor.getAction('editor.action.formatDocument').run()
                console.log("Code formatted successfully")
            } catch (error) {
                console.error("Failed to format code:", error)
                this.showNotification("Failed to format code", "error")
            }
        }
    }

    /**
     * Validate syntax
     */
    validate() {
        if (this.editor) {
            const model = this.editor.getModel()
            const markers = monaco.editor.getModelMarkers({ resource: model.uri })
            
            if (markers.length === 0) {
                this.showNotification("No syntax errors found", "success")
            } else {
                const errorCount = markers.filter(m => m.severity === 8).length
                const warningCount = markers.filter(m => m.severity === 4).length
                this.showNotification(
                    `Found ${errorCount} errors and ${warningCount} warnings`, 
                    errorCount > 0 ? "error" : "warning"
                )
            }
        }
    }

    /**
     * Toggle expanded editor view
     */
    toggleExpand() {
        const isExpanded = this.element.classList.contains('editor-expanded')
        
        if (isExpanded) {
            this.element.classList.remove('editor-expanded')
            this.expandBtnTarget.innerHTML = '<span aria-hidden="true">⛶</span> Expand'
        } else {
            this.element.classList.add('editor-expanded')
            this.expandBtnTarget.innerHTML = '<span aria-hidden="true">⛶</span> Collapse'
        }

        // Trigger resize for Monaco Editor
        if (this.editor) {
            setTimeout(() => this.editor.layout(), 100)
        }
    }

    /**
     * Switch preview tab
     */
    switchPreviewTab(event) {
        const tab = event.currentTarget.dataset.tab
        
        // Update active tab
        this.previewTabTargets.forEach(t => t.classList.remove('active'))
        event.currentTarget.classList.add('active')
        
        // Update preview content
        this.updatePreviewForTab(tab)
        
        console.log(`Preview tab switched to: ${tab}`)
    }

    /**
     * Update preview for specific tab
     */
    updatePreviewForTab(tab) {
        const content = this.getEditorContent()
        const language = this.languageValue
        
        switch (tab) {
            case 'email':
                this.renderEmailPreview(content, language)
                break
            case 'web':
                this.renderWebPreview(content, language)
                break
            case 'raw':
                this.renderRawPreview(content)
                break
        }
    }

    /**
     * Render email preview
     */
    renderEmailPreview(content, language) {
        if (!this.hasEmailSnippetPreviewTarget) return

        const preview = this.generateSnippetCard(content, language)
        this.emailSnippetPreviewTarget.innerHTML = preview
    }

    /**
     * Render web preview
     */
    renderWebPreview(content, language) {
        // Implementation for web preview
        console.log("Rendering web preview...")
    }

    /**
     * Render raw preview
     */
    renderRawPreview(content) {
        if (this.hasPreviewContentTarget) {
            this.previewContentTarget.innerHTML = `<pre><code>${this.escapeHtml(content)}</code></pre>`
        }
    }

    /**
     * Generate snippet card HTML
     */
    generateSnippetCard(content, language) {
        const securityEnabled = this.hasSecurityToggleTarget ? this.securityToggleTarget.checked : false
        const processedContent = securityEnabled ? this.maskSensitiveData(content) : content
        const highlightedContent = this.highlightSyntax(processedContent, language)

        return `
            <div class="snippet-card">
                <div class="snippet-header">
                    <div class="snippet-meta">
                        <span class="snippet-language">${this.capitalizeFirst(language)}</span>
                        <span>${this.countLines(content)} lines</span>
                        <span>${new Date().toLocaleDateString()}</span>
                    </div>
                    <div class="snippet-toolbar">
                        <div class="snippet-tools">
                            <button class="snippet-tool" title="Copy">📋</button>
                            <button class="snippet-tool" title="Download">⬇️</button>
                            <button class="snippet-tool" title="Open in IDE">🔧</button>
                        </div>
                    </div>
                </div>
                <div class="snippet-code">${highlightedContent}</div>
            </div>
        `
    }

    /**
     * Mask sensitive data in code
     */
    maskSensitiveData(content) {
        // Pattern to match common sensitive data
        const patterns = [
            /(api[_-]?key|token|secret|password|pwd)\s*[:=]\s*['"][^'"]+['"]/gi,
            /(process\.env\.[A-Z_]+)/gi,
            /(['"][a-zA-Z0-9]{20,}['"])/g // Long strings that might be tokens
        ]

        let maskedContent = content
        patterns.forEach(pattern => {
            maskedContent = maskedContent.replace(pattern, (match) => {
                return `<span class="hidden-token">🔒 Hidden — 2FA required</span>`
            })
        })

        return maskedContent
    }

    /**
     * Basic syntax highlighting
     */
    highlightSyntax(content, language) {
        // Basic syntax highlighting patterns
        const patterns = {
            comment: /\/\/.*$/gm,
            keyword: /\b(const|let|var|function|class|if|else|for|while|return|import|export|from)\b/g,
            string: /(['"])((?:(?!\1)[^\\]|\\.)*)(\1)/g,
            number: /\b\d+(\.\d+)?\b/g
        }

        let highlighted = this.escapeHtml(content)
        
        Object.entries(patterns).forEach(([type, pattern]) => {
            highlighted = highlighted.replace(pattern, `<span class="token-${type}">$&</span>`)
        })

        return highlighted
    }

    /**
     * Share snippet
     */
    async share() {
        try {
            const url = await this.generateShareUrl()
            
            if (navigator.share) {
                await navigator.share({
                    title: 'Code Snippet',
                    text: 'Check out this code snippet',
                    url: url
                })
            } else {
                await navigator.clipboard.writeText(url)
                this.showNotification("Share link copied to clipboard!", "success")
            }
        } catch (error) {
            console.error("Failed to share:", error)
            this.showNotification("Failed to generate share link", "error")
        }
    }

    /**
     * Send email
     */
    send() {
        // Open email composition modal or navigate to send page
        window.location.href = `/snippets/${this.snippetIdValue}/send`
    }

    /**
     * Save snippet
     */
    async save() {
        try {
            const content = this.getEditorContent()
            const data = {
                content: content,
                language: this.languageValue,
                // Add other fields as needed
            }

            const response = await fetch(`/api/snippets/${this.snippetIdValue}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })

            if (response.ok) {
                this.showNotification("Snippet saved successfully", "success")
            } else {
                throw new Error("Save failed")
            }
        } catch (error) {
            console.error("Failed to save:", error)
            this.showNotification("Failed to save snippet", "error")
        }
    }

    /**
     * Setup auto-save functionality
     */
    setupAutoSave() {
        if (!this.autoSaveValue) return
        
        this.autoSaveDelay = 2000 // 2 seconds
        this.autoSaveTimer = null
    }

    /**
     * Schedule auto-save
     */
    scheduleAutoSave() {
        if (this.autoSaveTimer) {
            clearTimeout(this.autoSaveTimer)
        }

        this.autoSaveTimer = setTimeout(() => {
            this.save()
        }, this.autoSaveDelay)
    }

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (event) => {
            // Cmd/Ctrl + S to save
            if ((event.metaKey || event.ctrlKey) && event.key === 's') {
                event.preventDefault()
                this.save()
            }
            
            // Cmd/Ctrl + Enter to send
            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                event.preventDefault()
                this.send()
            }
        })
    }

    /**
     * Update stats display
     */
    updateStats() {
        const content = this.getEditorContent()
        const lineCount = this.countLines(content)
        const charCount = content.length

        if (this.hasLineCountTarget) {
            this.lineCountTarget.textContent = `Lines: ${lineCount}`
        }
        if (this.hasCharCountTarget) {
            this.charCountTarget.textContent = `Characters: ${charCount}`
        }
    }

    /**
     * Update cursor info
     */
    updateCursorInfo() {
        if (this.editor) {
            const position = this.editor.getPosition()
            console.log(`Cursor at line ${position.lineNumber}, column ${position.column}`)
        }
    }

    /**
     * Update preview
     */
    updatePreview() {
        const activeTab = this.previewTabTargets.find(tab => tab.classList.contains('active'))
        if (activeTab) {
            this.updatePreviewForTab(activeTab.dataset.tab)
        }
    }

    /**
     * Get editor content
     */
    getEditorContent() {
        if (this.editor) {
            return this.editor.getValue()
        } else if (this.textareaFallback) {
            return this.textareaFallback.value
        }
        return ''
    }

    /**
     * Generate share URL
     */
    async generateShareUrl() {
        // Implementation depends on backend API
        return `${window.location.origin}/snippets/${this.snippetIdValue}`
    }

    /**
     * Utility functions
     */
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1)
    }

    countLines(content) {
        return content.split('\n').length
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
}