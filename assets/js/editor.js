/*
 * Monaco Editor Integration for PrettiOps
 * Handles code editing with syntax highlighting, themes, and auto-save
 */

import './utils/theme.js';

class CodeEditor {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.editor = null;
        this.models = new Map();
        this.currentLanguage = 'javascript';
        
        this.options = {
            theme: 'vs-dark',
            language: 'javascript',
            fontSize: 14,
            fontFamily: "'Fira Code', 'SF Mono', 'Monaco', 'Cascadia Code', monospace",
            fontLigatures: true,
            minimap: { enabled: true },
            wordWrap: 'on',
            lineNumbers: 'on',
            renderWhitespace: 'selection',
            tabSize: 4,
            insertSpaces: true,
            automaticLayout: true,
            scrollBeyondLastLine: false,
            formatOnPaste: true,
            formatOnType: true,
            ...options
        };
        
        this.init();
    }
    
    async init() {
        if (!this.container) {
            console.error(`Editor container with ID '${this.containerId}' not found`);
            return;
        }
        
        try {
            // Load Monaco Editor
            await this.loadMonaco();
            
            // Create editor instance
            this.createEditor();
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Setup auto-save
            this.setupAutoSave();
            
            // Setup theme synchronization
            this.setupThemeSync();
            
            console.log('Monaco Editor initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize Monaco Editor:', error);
            this.fallbackToTextarea();
        }
    }
    
    async loadMonaco() {
        // Set Monaco environment
        window.MonacoEnvironment = {
            getWorkerUrl: function (moduleId, label) {
                if (label === 'json') {
                    return '/build/monaco/vs/language/json/json.worker.js';
                }
                if (label === 'css' || label === 'scss' || label === 'less') {
                    return '/build/monaco/vs/language/css/css.worker.js';
                }
                if (label === 'html' || label === 'handlebars' || label === 'razor') {
                    return '/build/monaco/vs/language/html/html.worker.js';
                }
                if (label === 'typescript' || label === 'javascript') {
                    return '/build/monaco/vs/language/typescript/ts.worker.js';
                }
                return '/build/monaco/vs/editor/editor.worker.js';
            }
        };
        
        // Load Monaco Editor
        const monaco = await import('monaco-editor');
        window.monaco = monaco;
        
        // Register additional languages
        this.registerLanguages();
        
        return monaco;
    }
    
    registerLanguages() {
        const { monaco } = window;
        
        // Define custom themes
        monaco.editor.defineTheme('prettiops-light', {
            base: 'vs',
            inherit: true,
            rules: [
                { token: 'comment', foreground: '6b7280', fontStyle: 'italic' },
                { token: 'keyword', foreground: '7c3aed', fontStyle: 'bold' },
                { token: 'string', foreground: '059669' },
                { token: 'number', foreground: 'dc2626' },
                { token: 'regexp', foreground: 'ea580c' },
                { token: 'type', foreground: '2563eb' },
                { token: 'class', foreground: '7c3aed' },
                { token: 'function', foreground: '0891b2' },
                { token: 'variable', foreground: '374151' },
            ],
            colors: {
                'editor.background': '#fafafa',
                'editor.foreground': '#374151',
                'editor.lineHighlightBackground': '#f3f4f6',
                'editor.selectionBackground': '#e5e7eb',
                'editorCursor.foreground': '#6f00ff',
                'editorWhitespace.foreground': '#d1d5db',
            }
        });
        
        monaco.editor.defineTheme('prettiops-dark', {
            base: 'vs-dark',
            inherit: true,
            rules: [
                { token: 'comment', foreground: '6b7280', fontStyle: 'italic' },
                { token: 'keyword', foreground: 'a855f7', fontStyle: 'bold' },
                { token: 'string', foreground: '10b981' },
                { token: 'number', foreground: 'f59e0b' },
                { token: 'regexp', foreground: 'f97316' },
                { token: 'type', foreground: '3b82f6' },
                { token: 'class', foreground: 'a855f7' },
                { token: 'function', foreground: '06b6d4' },
                { token: 'variable', foreground: 'e5e7eb' },
            ],
            colors: {
                'editor.background': '#0f172a',
                'editor.foreground': '#e5e7eb',
                'editor.lineHighlightBackground': '#1e293b',
                'editor.selectionBackground': '#334155',
                'editorCursor.foreground': '#a855f7',
                'editorWhitespace.foreground': '#475569',
            }
        });
        
        // Register custom completions for common code patterns
        this.registerCompletionProviders();
    }
    
    registerCompletionProviders() {
        const { monaco } = window;
        
        // JavaScript/TypeScript completions
        monaco.languages.registerCompletionItemProvider('javascript', {
            provideCompletionItems: (model, position) => {
                const suggestions = [
                    {
                        label: 'console.log',
                        kind: monaco.languages.CompletionItemKind.Snippet,
                        insertText: 'console.log(${1:object});',
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: 'Log output to console'
                    },
                    {
                        label: 'function',
                        kind: monaco.languages.CompletionItemKind.Snippet,
                        insertText: 'function ${1:name}(${2:params}) {\n\t${3:// body}\n}',
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: 'Function declaration'
                    },
                    {
                        label: 'arrow-function',
                        kind: monaco.languages.CompletionItemKind.Snippet,
                        insertText: 'const ${1:name} = (${2:params}) => {\n\t${3:// body}\n};',
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: 'Arrow function'
                    }
                ];
                
                return { suggestions };
            }
        });
    }
    
    createEditor() {
        const { monaco } = window;
        
        // Create editor
        this.editor = monaco.editor.create(this.container, this.options);
        
        // Set initial theme based on system/user preference
        const theme = window.themeManager ? 
            (window.themeManager.getCurrentTheme() === 'dark' ? 'prettiops-dark' : 'prettiops-light') :
            'prettiops-light';
        
        this.setTheme(theme);
        
        // Store reference globally for theme manager
        window.monacoEditor = this.editor;
        
        // Dispatch ready event
        this.container.dispatchEvent(new CustomEvent('editor:ready', {
            detail: { editor: this.editor }
        }));
    }
    
    setupEventListeners() {
        if (!this.editor) return;
        
        // Content change events
        this.editor.onDidChangeModelContent(() => {
            this.container.dispatchEvent(new CustomEvent('editor:change', {
                detail: { 
                    content: this.getValue(),
                    language: this.currentLanguage
                }
            }));
        });
        
        // Language change events
        this.editor.onDidChangeModel(() => {
            const model = this.editor.getModel();
            if (model) {
                this.currentLanguage = model.getLanguageId();
                this.container.dispatchEvent(new CustomEvent('editor:languageChange', {
                    detail: { language: this.currentLanguage }
                }));
            }
        });
        
        // Focus events
        this.editor.onDidFocusEditorText(() => {
            this.container.classList.add('editor-focused');
        });
        
        this.editor.onDidBlurEditorText(() => {
            this.container.classList.remove('editor-focused');
        });
    }
    
    setupAutoSave() {
        if (!this.editor) return;
        
        let autoSaveTimeout;
        
        this.editor.onDidChangeModelContent(() => {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                this.autoSave();
            }, 1000); // Auto-save after 1 second of inactivity
        });
    }
    
    setupThemeSync() {
        // Listen for theme changes
        document.addEventListener('theme:changed', (event) => {
            const newTheme = event.detail.theme === 'dark' ? 'prettiops-dark' : 'prettiops-light';
            this.setTheme(newTheme);
        });
    }
    
    // Public API methods
    setValue(value) {
        if (this.editor) {
            this.editor.setValue(value);
        }
    }
    
    getValue() {
        return this.editor ? this.editor.getValue() : '';
    }
    
    setLanguage(language) {
        if (this.editor) {
            const { monaco } = window;
            const model = this.editor.getModel();
            if (model) {
                monaco.editor.setModelLanguage(model, language);
                this.currentLanguage = language;
            }
        }
    }
    
    setTheme(theme) {
        if (this.editor) {
            const { monaco } = window;
            monaco.editor.setTheme(theme);
        }
    }
    
    focus() {
        if (this.editor) {
            this.editor.focus();
        }
    }
    
    resize() {
        if (this.editor) {
            this.editor.layout();
        }
    }
    
    dispose() {
        if (this.editor) {
            this.editor.dispose();
        }
    }
    
    autoSave() {
        const content = this.getValue();
        const key = `prettiops-editor-${this.containerId}`;
        
        try {
            localStorage.setItem(key, JSON.stringify({
                content,
                language: this.currentLanguage,
                timestamp: Date.now()
            }));
            
            // Dispatch auto-save event
            this.container.dispatchEvent(new CustomEvent('editor:autoSave', {
                detail: { content, language: this.currentLanguage }
            }));
            
        } catch (error) {
            console.warn('Auto-save failed:', error);
        }
    }
    
    loadAutoSave() {
        const key = `prettiops-editor-${this.containerId}`;
        
        try {
            const saved = localStorage.getItem(key);
            if (saved) {
                const data = JSON.parse(saved);
                this.setValue(data.content);
                this.setLanguage(data.language);
                return data;
            }
        } catch (error) {
            console.warn('Failed to load auto-saved content:', error);
        }
        
        return null;
    }
    
    clearAutoSave() {
        const key = `prettiops-editor-${this.containerId}`;
        localStorage.removeItem(key);
    }
    
    // Fallback to textarea if Monaco fails to load
    fallbackToTextarea() {
        this.container.innerHTML = `
            <textarea 
                class="w-full h-full p-4 font-mono text-sm border border-gray-300 rounded-md resize-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                placeholder="Monaco Editor failed to load. Using fallback textarea..."
                data-fallback-editor="true"
            ></textarea>
        `;
        
        const textarea = this.container.querySelector('textarea');
        
        // Basic functionality for fallback
        textarea.addEventListener('input', () => {
            this.container.dispatchEvent(new CustomEvent('editor:change', {
                detail: { 
                    content: textarea.value,
                    language: this.currentLanguage
                }
            }));
        });
        
        // Store fallback methods
        this.getValue = () => textarea.value;
        this.setValue = (value) => textarea.value = value;
        this.focus = () => textarea.focus();
    }
}

// Export for global usage
window.CodeEditor = CodeEditor;

// Initialize editors on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Auto-initialize editors with data-editor attribute
    const editorContainers = document.querySelectorAll('[data-editor]');
    
    editorContainers.forEach(container => {
        const options = JSON.parse(container.dataset.editorOptions || '{}');
        new CodeEditor(container.id, options);
    });
});

export default CodeEditor;