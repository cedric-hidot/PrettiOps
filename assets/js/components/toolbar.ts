import { Controller } from "@hotwired/stimulus"

/**
 * Toolbar Controller
 * 
 * Handles snippet action toolbar interactions including edit, copy, export,
 * compare, comment, and pin actions. Works with snippet cards and editor views.
 * 
 * Targets:
 * - editBtn: Edit button
 * - copyBtn: Copy button  
 * - exportBtn: Export button
 * - compareBtn: Compare button
 * - commentBtn: Comment button
 * - pinBtn: Pin button
 * 
 * Values:
 * - snippetId: ID of the snippet
 * - snippetUrl: Base URL for snippet actions
 */
export default class extends Controller {
    static targets = ["editBtn", "copyBtn", "exportBtn", "compareBtn", "commentBtn", "pinBtn"]
    
    static values = {
        snippetId: String,
        snippetUrl: String
    }

    connect() {
        console.log("Toolbar controller connected")
        this.setupTooltips()
    }

    /**
     * Edit snippet
     */
    edit(event) {
        event.stopPropagation()
        
        if (this.snippetIdValue) {
            window.location.href = `/editor/${this.snippetIdValue}`
        } else {
            console.error("No snippet ID provided for edit action")
        }
        
        this.trackAction('edit')
    }

    /**
     * Copy snippet to clipboard
     */
    async copy(event) {
        event.stopPropagation()
        
        try {
            const content = await this.getSnippetContent()
            
            if (content) {
                await navigator.clipboard.writeText(content)
                this.showSuccess("Code copied to clipboard!")
                this.animateButton(this.copyBtnTarget, "✅")
            } else {
                throw new Error("No content to copy")
            }
        } catch (error) {
            console.error("Failed to copy to clipboard:", error)
            this.showError("Failed to copy to clipboard")
        }
        
        this.trackAction('copy')
    }

    /**
     * Export snippet as file
     */
    async export(event) {
        event.stopPropagation()
        
        try {
            const snippetData = await this.getSnippetData()
            
            if (snippetData) {
                this.downloadFile(snippetData.content, snippetData.filename, snippetData.mimeType)
                this.showSuccess("Download started!")
                this.animateButton(this.exportBtnTarget, "✅")
            } else {
                throw new Error("No data to export")
            }
        } catch (error) {
            console.error("Failed to export snippet:", error)
            this.showError("Failed to export snippet")
        }
        
        this.trackAction('export')
    }

    /**
     * Compare snippet versions
     */
    compare(event) {
        event.stopPropagation()
        
        if (this.snippetIdValue) {
            // Open compare modal or navigate to compare view
            this.openCompareModal()
        } else {
            console.error("No snippet ID provided for compare action")
        }
        
        this.trackAction('compare')
    }

    /**
     * Add comment to snippet
     */
    comment(event) {
        event.stopPropagation()
        
        if (this.snippetIdValue) {
            // Open comment sidebar or modal
            this.openCommentPanel()
        } else {
            console.error("No snippet ID provided for comment action")
        }
        
        this.trackAction('comment')
    }

    /**
     * Pin/unpin snippet
     */
    async pin(event) {
        event.stopPropagation()
        
        try {
            const isPinned = this.pinBtnTarget.classList.contains('pinned')
            
            // Toggle pin state via API
            await this.togglePinState(!isPinned)
            
            // Update UI
            if (isPinned) {
                this.pinBtnTarget.classList.remove('pinned')
                this.pinBtnTarget.title = "Pin snippet"
                this.showSuccess("Snippet unpinned")
            } else {
                this.pinBtnTarget.classList.add('pinned')
                this.pinBtnTarget.title = "Unpin snippet"
                this.showSuccess("Snippet pinned")
            }
            
            this.animateButton(this.pinBtnTarget, isPinned ? "📌" : "📍")
        } catch (error) {
            console.error("Failed to toggle pin state:", error)
            this.showError("Failed to update pin state")
        }
        
        this.trackAction('pin')
    }

    /**
     * Get snippet content from API or editor
     */
    async getSnippetContent() {
        // If we're in the editor, get content directly
        if (window.editorController) {
            return window.editorController.getEditorContent()
        }
        
        // Otherwise fetch from API
        if (this.snippetIdValue) {
            try {
                const response = await fetch(`/api/snippets/${this.snippetIdValue}/content`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                
                if (response.ok) {
                    const data = await response.json()
                    return data.content
                } else {
                    throw new Error("Failed to fetch snippet content")
                }
            } catch (error) {
                console.error("Error fetching snippet content:", error)
                return null
            }
        }
        
        return null
    }

    /**
     * Get snippet data for export
     */
    async getSnippetData() {
        if (!this.snippetIdValue) return null
        
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            
            if (response.ok) {
                const snippet = await response.json()
                
                // Generate filename based on snippet title and language
                const extension = this.getFileExtension(snippet.language)
                const filename = `${this.sanitizeFilename(snippet.title || 'snippet')}.${extension}`
                
                return {
                    content: snippet.content,
                    filename: filename,
                    mimeType: this.getMimeType(snippet.language)
                }
            } else {
                throw new Error("Failed to fetch snippet data")
            }
        } catch (error) {
            console.error("Error fetching snippet data:", error)
            return null
        }
    }

    /**
     * Download file to user's device
     */
    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType })
        const url = URL.createObjectURL(blob)
        
        const link = document.createElement('a')
        link.href = url
        link.download = filename
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        
        // Clean up the URL object
        setTimeout(() => URL.revokeObjectURL(url), 100)
    }

    /**
     * Toggle pin state via API
     */
    async togglePinState(pinned) {
        if (!this.snippetIdValue) return
        
        const response = await fetch(`/api/snippets/${this.snippetIdValue}/pin`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ pinned })
        })
        
        if (!response.ok) {
            throw new Error("Failed to update pin state")
        }
        
        return response.json()
    }

    /**
     * Open compare modal
     */
    openCompareModal() {
        // Create and show compare modal
        const modal = this.createModal('compare-modal', 'Compare Versions')
        modal.innerHTML = `
            <div class="modal-header">
                <h3 class="modal-title">Compare Versions</h3>
                <button class="modal-close" data-action="click->toolbar#closeModal">×</button>
            </div>
            <div class="modal-body">
                <p>Loading version history...</p>
                <div class="compare-container" data-toolbar-target="compareContainer"></div>
            </div>
        `
        
        this.showModal(modal)
        this.loadVersionHistory()
    }

    /**
     * Open comment panel
     */
    openCommentPanel() {
        // Toggle comment sidebar or create comment modal
        let commentPanel = document.querySelector('.comment-panel')
        
        if (!commentPanel) {
            commentPanel = this.createCommentPanel()
            document.body.appendChild(commentPanel)
        }
        
        commentPanel.classList.toggle('active')
        this.loadComments()
    }

    /**
     * Create comment panel
     */
    createCommentPanel() {
        const panel = document.createElement('div')
        panel.className = 'comment-panel'
        panel.innerHTML = `
            <div class="comment-panel-header">
                <h3>Comments</h3>
                <button class="comment-panel-close" data-action="click->toolbar#closeCommentPanel">×</button>
            </div>
            <div class="comment-panel-body">
                <div class="comments-list" data-toolbar-target="commentsList"></div>
                <form class="comment-form" data-action="submit->toolbar#submitComment">
                    <textarea placeholder="Add a comment..." required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">Post Comment</button>
                </form>
            </div>
        `
        
        return panel
    }

    /**
     * Load version history for comparison
     */
    async loadVersionHistory() {
        if (!this.hasCompareContainerTarget) return
        
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/versions`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            
            if (response.ok) {
                const versions = await response.json()
                this.renderVersionHistory(versions)
            } else {
                throw new Error("Failed to load version history")
            }
        } catch (error) {
            console.error("Error loading version history:", error)
            this.compareContainerTarget.innerHTML = '<p class="error">Failed to load version history</p>'
        }
    }

    /**
     * Load comments for snippet
     */
    async loadComments() {
        const commentsList = document.querySelector('[data-toolbar-target="commentsList"]')
        if (!commentsList) return
        
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/comments`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            
            if (response.ok) {
                const comments = await response.json()
                this.renderComments(comments, commentsList)
            } else {
                throw new Error("Failed to load comments")
            }
        } catch (error) {
            console.error("Error loading comments:", error)
            commentsList.innerHTML = '<p class="error">Failed to load comments</p>'
        }
    }

    /**
     * Submit new comment
     */
    async submitComment(event) {
        event.preventDefault()
        
        const form = event.target
        const textarea = form.querySelector('textarea')
        const content = textarea.value.trim()
        
        if (!content) return
        
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content })
            })
            
            if (response.ok) {
                textarea.value = ''
                this.loadComments() // Reload comments
                this.showSuccess("Comment added successfully")
            } else {
                throw new Error("Failed to submit comment")
            }
        } catch (error) {
            console.error("Error submitting comment:", error)
            this.showError("Failed to submit comment")
        }
    }

    /**
     * Setup tooltips for toolbar buttons
     */
    setupTooltips() {
        // Use the static targets array instead of this.targets
        this.constructor.targets.forEach(targetName => {
            const hasTarget = this[`has${targetName.charAt(0).toUpperCase() + targetName.slice(1)}Target`]
            if (hasTarget) {
                const button = this[`${targetName}Target`]
                if (button && button.title) {
                    this.addTooltip(button)
                }
            }
        })
    }

    /**
     * Add tooltip to button
     */
    addTooltip(button) {
        button.addEventListener('mouseenter', () => {
            this.showTooltip(button, button.title)
        })
        
        button.addEventListener('mouseleave', () => {
            this.hideTooltip()
        })
    }

    /**
     * Animate button feedback
     */
    animateButton(button, emoji) {
        const originalText = button.innerHTML
        
        button.innerHTML = emoji
        button.classList.add('toolbar-success')
        
        setTimeout(() => {
            button.innerHTML = originalText
            button.classList.remove('toolbar-success')
        }, 1000)
    }

    /**
     * Track user actions for analytics
     */
    trackAction(action) {
        if (window.analytics) {
            window.analytics.track('snippet_action', {
                action: action,
                snippet_id: this.snippetIdValue,
                timestamp: new Date().toISOString()
            })
        }
        
        console.log(`Toolbar action: ${action}`)
    }

    /**
     * Utility functions
     */
    getFileExtension(language) {
        const extensions = {
            javascript: 'js',
            typescript: 'ts',
            python: 'py',
            php: 'php',
            java: 'java',
            csharp: 'cs',
            go: 'go',
            rust: 'rs',
            sql: 'sql',
            json: 'json',
            yaml: 'yml',
            html: 'html',
            css: 'css',
            markdown: 'md'
        }
        
        return extensions[language] || 'txt'
    }

    getMimeType(language) {
        const mimeTypes = {
            javascript: 'text/javascript',
            typescript: 'text/typescript',
            python: 'text/x-python',
            php: 'text/x-php',
            java: 'text/x-java',
            csharp: 'text/x-csharp',
            go: 'text/x-go',
            rust: 'text/x-rust',
            sql: 'text/x-sql',
            json: 'application/json',
            yaml: 'text/yaml',
            html: 'text/html',
            css: 'text/css',
            markdown: 'text/markdown'
        }
        
        return mimeTypes[language] || 'text/plain'
    }

    sanitizeFilename(filename) {
        return filename.replace(/[^a-z0-9]/gi, '_').toLowerCase()
    }

    showSuccess(message) {
        if (window.notificationManager) {
            window.notificationManager.success(message)
        }
    }

    showError(message) {
        if (window.notificationManager) {
            window.notificationManager.error(message)
        }
    }

    showTooltip(element, text) {
        // Simple tooltip implementation
        const tooltip = document.createElement('div')
        tooltip.className = 'tooltip'
        tooltip.textContent = text
        
        const rect = element.getBoundingClientRect()
        tooltip.style.position = 'absolute'
        tooltip.style.top = `${rect.bottom + 5}px`
        tooltip.style.left = `${rect.left + rect.width / 2}px`
        tooltip.style.transform = 'translateX(-50%)'
        
        document.body.appendChild(tooltip)
        this.currentTooltip = tooltip
    }

    hideTooltip() {
        if (this.currentTooltip) {
            this.currentTooltip.remove()
            this.currentTooltip = null
        }
    }

    createModal(id, title) {
        const modal = document.createElement('div')
        modal.id = id
        modal.className = 'modal-overlay'
        
        const modalContent = document.createElement('div')
        modalContent.className = 'modal'
        modal.appendChild(modalContent)
        
        return modalContent
    }

    showModal(modal) {
        document.body.appendChild(modal.parentElement || modal)
        modal.parentElement.classList.add('active')
    }

    closeModal() {
        const modals = document.querySelectorAll('.modal-overlay.active')
        modals.forEach(modal => {
            modal.classList.remove('active')
            setTimeout(() => modal.remove(), 300)
        })
    }

    closeCommentPanel() {
        const panel = document.querySelector('.comment-panel.active')
        if (panel) {
            panel.classList.remove('active')
        }
    }

    renderVersionHistory(versions) {
        // Implementation for rendering version comparison UI
        console.log("Rendering version history:", versions)
    }

    renderComments(comments, container) {
        container.innerHTML = comments.map(comment => `
            <div class="comment">
                <div class="comment-author">${comment.author.name}</div>
                <div class="comment-content">${comment.content}</div>
                <div class="comment-date">${new Date(comment.createdAt).toLocaleDateString()}</div>
            </div>
        `).join('')
    }
}