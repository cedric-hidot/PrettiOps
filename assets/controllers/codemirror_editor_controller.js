import { Controller } from "@hotwired/stimulus"
import { EditorView, basicSetup } from "codemirror"
import { EditorState } from "@codemirror/state"
import { javascript } from "@codemirror/lang-javascript"
import { python } from "@codemirror/lang-python"
import { php } from "@codemirror/lang-php"
import { java } from "@codemirror/lang-java"
import { cpp } from "@codemirror/lang-cpp"
import { rust } from "@codemirror/lang-rust"
import { go } from "@codemirror/lang-go"
import { sql } from "@codemirror/lang-sql"
import { html } from "@codemirror/lang-html"
import { css } from "@codemirror/lang-css"
import { json } from "@codemirror/lang-json"
import { xml } from "@codemirror/lang-xml"
import { markdown } from "@codemirror/lang-markdown"
import { yaml } from "@codemirror/lang-yaml"
import { oneDark } from "@codemirror/theme-one-dark"
import hljs from 'highlight.js'

// Stimulus controller for CodeMirror editor integration
export default class extends Controller {
    static targets = [
        "container", 
        "editor", 
        "languageSelect", 
        "languageDisplay", 
        "lineCount", 
        "charCount", 
        "emailSnippetPreview",
        "emailSubject",
        "securityToggle",
        "resizeHandle",
        "editorPanel",
        "previewPanel",
        "previewContent"
    ]
    
    static values = {
        snippetId: String,
        language: String,
        theme: String,
        content: String,
        autoSave: Boolean,
        fontSize: Number,
        fontFamily: String
    }

    // Language extensions mapping
    languageExtensions = {
        javascript: javascript(),
        typescript: javascript({ typescript: true }),
        python: python(),
        php: php(),
        java: java(),
        cpp: cpp(),
        c: cpp(),
        rust: rust(),
        go: go(),
        sql: sql(),
        html: html(),
        css: css(),
        json: json(),
        xml: xml(),
        markdown: markdown(),
        shell: null, // Use basic highlighting
        bash: null,  // Use basic highlighting
        yaml: yaml(),
        yml: yaml()
    }

    // Theme mapping - always use oneDark for now to avoid multiple instance issues
    themes = {
        light: null, // Default light theme
        dark: oneDark,
        'github-light': null, // Default light theme
        'github-dark': oneDark,
        'material-light': null, // Default light theme
        'material-dark': oneDark,
        dracula: oneDark, // Using oneDark as fallback
        monokai: oneDark // Using oneDark as Monokai alternative
    }

    // Supported languages for dropdown
    supportedLanguages = [
        { value: 'javascript', label: 'JavaScript' },
        { value: 'typescript', label: 'TypeScript' },
        { value: 'python', label: 'Python' },
        { value: 'php', label: 'PHP' },
        { value: 'java', label: 'Java' },
        { value: 'cpp', label: 'C++' },
        { value: 'c', label: 'C' },
        { value: 'rust', label: 'Rust' },
        { value: 'go', label: 'Go' },
        { value: 'sql', label: 'SQL' },
        { value: 'html', label: 'HTML' },
        { value: 'css', label: 'CSS' },
        { value: 'json', label: 'JSON' },
        { value: 'xml', label: 'XML' },
        { value: 'markdown', label: 'Markdown' },
        { value: 'shell', label: 'Shell/Bash' },
        { value: 'yaml', label: 'YAML' }
    ]

    connect() {
        console.log('🔗 CodeMirror Editor Controller connected')
        
        // Prevent duplicate initialization
        if (this.initialized) {
            console.log('⚠️ Controller already initialized, skipping')
            return
        }
        
        // Reduce timeout and simplify initialization
        setTimeout(() => {
            console.log('🔍 Available targets:', {
                editor: this.hasEditorTarget,
                container: this.hasContainerTarget,
                languageSelect: this.hasLanguageSelectTarget
            })
            
            if (!this.hasEditorTarget && !this.hasContainerTarget) {
                console.log('ℹ️ CodeMirror controller loaded but no editor targets found')
                return
            }
            
            try {
                this.initializeEditor()
                this.initializeResizing()
                this.populateLanguageDropdown()
                this.updateStats()
                this.initialized = true
                console.log('✅ CodeMirror controller fully initialized')
            } catch (error) {
                console.error('❌ Failed to initialize CodeMirror:', error)
                this.showError('Failed to initialize code editor')
            }
        }, 300) // Reduced delay - faster initialization
    }

    disconnect() {
        if (this.editorView) {
            this.editorView.destroy()
        }
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval)
        }
    }

    // Initialize the CodeMirror editor
    initializeEditor() {
        try {
            console.log('🔧 Initializing CodeMirror editor...')
            
            // Clear any existing content in the container
            console.log('🔍 Checking targets:', {
                hasEditor: this.hasEditorTarget,
                hasContainer: this.hasContainerTarget,
                editorElement: this.hasEditorTarget ? this.editorTarget : null,
                containerElement: this.hasContainerTarget ? this.containerTarget : null
            })
            
            if (this.hasEditorTarget) {
                this.editorTarget.innerHTML = ''
                console.log('✅ Editor target found and cleared')
            } else if (this.hasContainerTarget) {
                // Fallback to container if editor target not found
                console.log('⚠️ Using container target as fallback')
                this.containerTarget.innerHTML = ''
            } else {
                console.error('❌ No editor or container target found!')
                return
            }

            // Get initial values - remove placeholder text to fix the issue
            const initialContent = this.contentValue && this.contentValue.trim() !== '// Start coding here...' ? this.contentValue : ''
            const language = this.languageValue || 'javascript'
            const theme = this.themeValue || 'dark'
            
            console.log('📋 Editor config:', { initialContent, language, theme })

            // Get extensions
            const languageExt = this.getLanguageExtension(language)
            const themeExts = this.getTheme(theme)
            
            // Use official basicSetup + language + theme + change listener
            const extensions = [
                basicSetup,
                // Add language support if available
                ...(languageExt ? [languageExt] : []),
                // Add theme if available
                ...(Array.isArray(themeExts) ? themeExts : themeExts ? [themeExts] : []),
                // Change listener
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        this.handleContentChange()
                    }
                })
            ]
            
            console.log(`🔧 Building editor with ${extensions.length} extensions`)
            
            // Create editor state with all professional features
            const state = EditorState.create({
                doc: initialContent,
                extensions: extensions
            })
            
            console.log('📋 Editor state created successfully')

            // Create editor view
            const parentElement = this.hasEditorTarget ? this.editorTarget : this.containerTarget
            console.log('🎯 Creating editor in:', parentElement)
            
            this.editorView = new EditorView({
                state,
                parent: parentElement
            })

            console.log('✅ CodeMirror editor initialized successfully')

            // Set up auto-save if enabled
            if (this.autoSaveValue) {
                this.setupAutoSave()
            }

            // Update initial preview
            this.updatePreview()

        } catch (error) {
            console.error('❌ Failed to initialize CodeMirror editor:', error)
            this.showError('Failed to initialize code editor')
        }
    }

    // Get language extension for CodeMirror
    getLanguageExtension(language) {
        return this.languageExtensions[language] || this.languageExtensions.javascript
    }

    // Get theme for CodeMirror
    getTheme(themeName) {
        const theme = this.themes[themeName]
        // Must return an array of extensions or empty array
        if (theme) {
            return [theme]
        }
        return []
    }

    // Helper method to create editor with specific content and settings
    createEditorWithContent(parentElement, content, language, theme) {
        try {
            console.log('🔧 Creating editor with:', { content: content.length, language, theme })
            
            // Get extensions
            const languageExt = this.getLanguageExtension(language)
            const themeExts = this.getTheme(theme)
            
            // Use official basicSetup + language + theme + change listener
            const extensions = [
                basicSetup,
                // Add language support if available
                ...(languageExt ? [languageExt] : []),
                // Add theme if available
                ...(Array.isArray(themeExts) ? themeExts : themeExts ? [themeExts] : []),
                // Change listener
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        this.handleContentChange()
                    }
                })
            ]
            
            // Create editor state
            const state = EditorState.create({
                doc: content,
                extensions: extensions
            })
            
            // Create new editor view
            this.editorView = new EditorView({
                state,
                parent: parentElement
            })
            
            console.log('✅ Editor recreated successfully')
            
        } catch (error) {
            console.error('❌ Failed to create editor:', error)
            this.showError('Failed to update editor')
        }
    }

    // Handle content changes
    handleContentChange() {
        this.updateStats()
        this.updatePreview()
        
        // Debounced auto-save
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout)
        }
        
        this.autoSaveTimeout = setTimeout(() => {
            if (this.autoSaveValue) {
                this.save()
            }
        }, 1000)
    }

    // Update statistics display
    updateStats() {
        if (!this.editorView) return

        const content = this.editorView.state.doc.toString()
        const lineCount = this.editorView.state.doc.lines
        const charCount = content.length

        if (this.hasLineCountTarget) {
            this.lineCountTarget.textContent = `Lines: ${lineCount}`
        }

        if (this.hasCharCountTarget) {
            this.charCountTarget.textContent = `Characters: ${charCount}`
        }
    }

    // Update email preview with syntax highlighting
    updatePreview() {
        if (!this.editorView || !this.hasEmailSnippetPreviewTarget) return

        const content = this.editorView.state.doc.toString()
        const language = this.languageValue || 'javascript'

        if (content.trim()) {
            try {
                // Use Highlight.js for email preview syntax highlighting
                const highlighted = hljs.highlight(content, { language: this.getHljsLanguage(language) })
                
                this.emailSnippetPreviewTarget.innerHTML = `
                    <div class="code-snippet-preview">
                        <div class="code-header">
                            <span class="code-language">${this.getLanguageLabel(language)}</span>
                            <span class="code-lines">${this.editorView.state.doc.lines} lines</span>
                        </div>
                        <pre><code class="hljs language-${language}">${highlighted.value}</code></pre>
                    </div>
                `
            } catch (error) {
                console.warn('Highlight.js failed, using plain text:', error)
                this.emailSnippetPreviewTarget.innerHTML = `
                    <div class="code-snippet-preview">
                        <div class="code-header">
                            <span class="code-language">${this.getLanguageLabel(language)}</span>
                            <span class="code-lines">${this.editorView.state.doc.lines} lines</span>
                        </div>
                        <pre><code>${content}</code></pre>
                    </div>
                `
            }
        } else {
            // Show placeholder when empty
            this.emailSnippetPreviewTarget.innerHTML = `
                <div class="snippet-placeholder">
                    <svg class="placeholder-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                    </svg>
                    <p>Start typing to see your code preview</p>
                </div>
            `
        }

        // Update email subject with snippet title
        if (this.hasEmailSubjectTarget) {
            const title = document.querySelector('[data-auto-save-field-value="title"]')?.value || 'Code Snippet'
            this.emailSubjectTarget.textContent = title
        }
    }

    // Map CodeMirror language to Highlight.js language
    getHljsLanguage(language) {
        const mapping = {
            javascript: 'javascript',
            typescript: 'typescript',
            python: 'python',
            php: 'php',
            java: 'java',
            cpp: 'cpp',
            c: 'c',
            rust: 'rust',
            go: 'go',
            sql: 'sql',
            html: 'xml',
            css: 'css',
            json: 'json',
            xml: 'xml',
            markdown: 'markdown',
            shell: 'bash',
            bash: 'bash',
            yaml: 'yaml',
            yml: 'yaml'
        }
        return mapping[language] || 'plaintext'
    }

    // Get display label for language
    getLanguageLabel(language) {
        const lang = this.supportedLanguages.find(l => l.value === language)
        return lang ? lang.label : language.charAt(0).toUpperCase() + language.slice(1)
    }

    // Populate language dropdown
    populateLanguageDropdown() {
        if (!this.hasLanguageSelectTarget) return

        this.languageSelectTarget.innerHTML = ''
        
        this.supportedLanguages.forEach(lang => {
            const option = document.createElement('option')
            option.value = lang.value
            option.textContent = lang.label
            option.selected = lang.value === this.languageValue
            this.languageSelectTarget.appendChild(option)
        })
    }

    // Handle language change
    updateLanguage(event) {
        const newLanguage = event.target.value
        console.log('🌐 Language change:', newLanguage)
        this.languageValue = newLanguage

        if (this.editorView) {
            // Simple approach: reinitialize editor with new language
            // This avoids complex reconfiguration that causes issues
            const currentContent = this.editorView.state.doc.toString()
            const parentElement = this.editorView.dom.parentNode
            
            // Destroy current editor
            this.editorView.destroy()
            
            // Create new editor with new language
            this.createEditorWithContent(parentElement, currentContent, newLanguage, this.themeValue)
        }

        // Update language display
        if (this.hasLanguageDisplayTarget) {
            this.languageDisplayTarget.textContent = this.getLanguageLabel(newLanguage)
        }

        // Update preview
        this.updatePreview()

        // Auto-save language change
        if (this.autoSaveValue) {
            this.save()
        }
    }

    // Handle theme change
    updateTheme(event) {
        const themeButton = event.target.closest('.theme-option')
        if (!themeButton) return

        const newTheme = themeButton.dataset.theme
        console.log('🎨 Theme change:', newTheme)
        this.themeValue = newTheme

        // Update active theme button
        document.querySelectorAll('.theme-option').forEach(btn => btn.classList.remove('active'))
        themeButton.classList.add('active')

        if (this.editorView) {
            // Simple approach: reinitialize editor with new theme
            // This avoids complex reconfiguration that causes issues
            const currentContent = this.editorView.state.doc.toString()
            const parentElement = this.editorView.dom.parentNode
            
            // Destroy current editor
            this.editorView.destroy()
            
            // Create new editor with new theme
            this.createEditorWithContent(parentElement, currentContent, this.languageValue, newTheme)
        }

        // Auto-save theme change
        if (this.autoSaveValue) {
            this.save()
        }
    }

    // Handle security toggle
    updateSecurity(event) {
        const isEnabled = event.target.checked
        // Implement security filtering logic here
        console.log('Security mode:', isEnabled ? 'enabled' : 'disabled')
        
        if (this.autoSaveValue) {
            this.save()
        }
    }

    // Get autocompletions based on language
    getAutocompletions(context) {
        const language = this.languageValue || 'javascript'
        const completions = this.getLanguageCompletions(language)
        
        return completions.map(completion => ({
            label: completion,
            type: 'keyword'
        }))
    }

    // Language-specific completions
    getLanguageCompletions(language) {
        const completions = {
            javascript: ['function', 'const', 'let', 'var', 'if', 'else', 'for', 'while', 'return', 'class', 'import', 'export', 'async', 'await', 'try', 'catch'],
            python: ['def', 'class', 'if', 'elif', 'else', 'for', 'while', 'import', 'from', 'return', 'try', 'except', 'finally', 'with', 'as', 'lambda'],
            php: ['<?php', 'function', 'class', 'if', 'else', 'elseif', 'for', 'foreach', 'while', 'return', 'try', 'catch', 'finally', 'namespace', 'use'],
            java: ['public', 'private', 'protected', 'class', 'interface', 'if', 'else', 'for', 'while', 'return', 'try', 'catch', 'finally', 'import', 'package'],
            cpp: ['#include', 'int', 'char', 'double', 'float', 'if', 'else', 'for', 'while', 'return', 'class', 'struct', 'namespace', 'using'],
        }
        
        return completions[language] || completions.javascript
    }

    // Initialize split-pane resizing
    initializeResizing() {
        if (!this.hasResizeHandleTarget || !this.hasEditorPanelTarget || !this.hasPreviewPanelTarget) {
            return
        }

        let isResizing = false
        let startX = 0
        let startWidth = 0

        this.resizeHandleTarget.addEventListener('mousedown', (e) => {
            isResizing = true
            startX = e.clientX
            startWidth = this.editorPanelTarget.getBoundingClientRect().width
            document.body.style.cursor = 'col-resize'
            document.body.style.userSelect = 'none'
        })

        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return

            const diff = e.clientX - startX
            const newWidth = startWidth + diff
            const containerWidth = this.editorPanelTarget.parentElement.getBoundingClientRect().width
            const minWidth = 300
            const maxWidth = containerWidth - 300

            if (newWidth >= minWidth && newWidth <= maxWidth) {
                this.editorPanelTarget.style.width = `${newWidth}px`
                this.previewPanelTarget.style.width = `${containerWidth - newWidth - 16}px` // 16px for gap
            }
        })

        document.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false
                document.body.style.cursor = ''
                document.body.style.userSelect = ''
            }
        })
    }

    // Tab switching functionality
    switchTab(event) {
        const tabButton = event.target.closest('.tab-button')
        if (!tabButton) return

        const targetTab = tabButton.dataset.tab

        // Update active tab button
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'))
        tabButton.classList.add('active')

        // Show/hide panels based on tab (for mobile)
        if (window.innerWidth <= 768) {
            if (targetTab === 'editor') {
                this.editorPanelTarget.style.display = 'flex'
                this.previewPanelTarget.style.display = 'none'
            } else {
                this.editorPanelTarget.style.display = 'none'
                this.previewPanelTarget.style.display = 'flex'
            }
        }
    }

    // Format code
    format() {
        if (!this.editorView) return

        const content = this.editorView.state.doc.toString()
        
        // Simple formatting for demonstration - in production you'd use proper formatters
        try {
            let formatted = content
            const language = this.languageValue

            // Basic formatting based on language
            if (language === 'json') {
                formatted = JSON.stringify(JSON.parse(content), null, 2)
            } else {
                // Basic indentation fix
                formatted = this.basicFormat(content)
            }

            // Replace editor content
            this.editorView.dispatch({
                changes: {
                    from: 0,
                    to: this.editorView.state.doc.length,
                    insert: formatted
                }
            })

            this.showSuccess('Code formatted successfully!')
        } catch (error) {
            console.error('Formatting failed:', error)
            this.showError('Failed to format code')
        }
    }

    // Basic code formatting
    basicFormat(code) {
        const lines = code.split('\n')
        let indentLevel = 0
        const indentSize = 2

        return lines.map(line => {
            const trimmed = line.trim()
            
            if (trimmed.includes('}') || trimmed.includes(']') || trimmed.includes(')')) {
                indentLevel = Math.max(0, indentLevel - 1)
            }
            
            const formatted = ' '.repeat(indentLevel * indentSize) + trimmed
            
            if (trimmed.includes('{') || trimmed.includes('[') || trimmed.includes('(')) {
                indentLevel++
            }
            
            return formatted
        }).join('\n')
    }

    // Validate syntax
    validate() {
        if (!this.editorView) return

        const content = this.editorView.state.doc.toString()
        const language = this.languageValue

        try {
            // Basic validation based on language
            let isValid = true
            let message = 'Syntax is valid!'

            if (language === 'json') {
                JSON.parse(content)
            } else {
                // Check for basic syntax issues
                const openBraces = (content.match(/{/g) || []).length
                const closeBraces = (content.match(/}/g) || []).length
                const openParens = (content.match(/\(/g) || []).length
                const closeParens = (content.match(/\)/g) || []).length

                if (openBraces !== closeBraces) {
                    isValid = false
                    message = 'Mismatched braces detected'
                } else if (openParens !== closeParens) {
                    isValid = false
                    message = 'Mismatched parentheses detected'
                }
            }

            if (isValid) {
                this.showSuccess(message)
            } else {
                this.showError(message)
            }
        } catch (error) {
            this.showError('Syntax error: ' + error.message)
        }
    }

    // Setup auto-save
    setupAutoSave() {
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval)
        }

        // Auto-save every 30 seconds if there are changes
        this.autoSaveInterval = setInterval(() => {
            if (this.hasUnsavedChanges) {
                this.save()
                this.hasUnsavedChanges = false
            }
        }, 30000)
    }

    // Save snippet
    async save() {
        if (!this.editorView) return

        const content = this.editorView.state.doc.toString()
        const title = document.querySelector('[data-auto-save-field-value="title"]')?.value || 'Untitled Snippet'
        
        const data = {
            id: this.snippetIdValue,
            title: title,
            content: content,
            language: this.languageValue,
            theme: this.themeValue,
            securityEnabled: this.hasSecurityToggleTarget ? this.securityToggleTarget.checked : true
        }

        try {
            const response = await fetch('/api/snippets/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })

            if (response.ok) {
                const result = await response.json()
                this.snippetIdValue = result.id
                this.showSuccess('Snippet saved successfully!')
            } else {
                throw new Error('Failed to save snippet')
            }
        } catch (error) {
            console.error('Save failed:', error)
            this.showError('Failed to save snippet')
        }
    }

    // Send email
    send() {
        // Implement email sending logic
        this.showInfo('Email sending functionality coming soon!')
    }

    // Share snippet
    share() {
        if (this.snippetIdValue) {
            const shareUrl = `${window.location.origin}/snippets/${this.snippetIdValue}`
            navigator.clipboard.writeText(shareUrl).then(() => {
                this.showSuccess('Share link copied to clipboard!')
            }).catch(() => {
                this.showError('Failed to copy share link')
            })
        } else {
            this.showError('Please save the snippet first')
        }
    }

    // Show success message
    showSuccess(message) {
        this.showNotification(message, 'success')
    }

    // Show error message
    showError(message) {
        this.showNotification(message, 'error')
    }

    // Show info message
    showInfo(message) {
        this.showNotification(message, 'info')
    }

    // Show notification
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div')
        notification.className = `notification notification--${type} fade-in`
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `

        // Add to DOM
        document.body.appendChild(notification)

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove()
            }
        }, 5000)
    }
}