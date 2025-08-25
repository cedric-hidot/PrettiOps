import { Controller } from "@hotwired/stimulus"

/**
 * Collaboration Controller
 * 
 * Handles collaborative features including comments, versioning, diff comparison,
 * and real-time collaboration. Manages version history and enables team
 * collaboration on code snippets.
 */
export default class extends Controller {
    static targets = [
        "commentsPanel", "commentsList", "commentForm", "commentInput",
        "versionsPanel", "versionsList", "versionCompare", "diffViewer",
        "collaboratorsPanel", "collaboratorsList", "shareButton", "permissionsForm"
    ]
    
    static values = {
        snippetId: String,
        userId: String,
        currentVersion: Number,
        compareVersion: Number,
        pollInterval: Number,
        websocketUrl: String
    }

    static classes = [
        "loading", "error", "success", "hasComments", "comparing"
    ]

    connect() {
        console.log("Collaboration controller connected")
        this.initializeCollaboration()
        this.setupEventListeners()
        this.startPolling()
        this.connectWebSocket()
        
        // Global reference
        window.collaborationController = this
    }

    disconnect() {
        this.stopPolling()
        this.disconnectWebSocket()
        window.collaborationController = null
    }

    /**
     * Initialize collaboration features
     */
    async initializeCollaboration() {
        try {
            await Promise.all([
                this.loadComments(),
                this.loadVersions(),
                this.loadCollaborators()
            ])
        } catch (error) {
            console.error("Failed to initialize collaboration:", error)
            this.showError("Failed to load collaboration data")
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for Monaco editor changes for auto-versioning
        document.addEventListener('monaco-editor:content-changed', (e) => {
            this.handleContentChange(e.detail.content)
        })

        // Listen for snippet save events
        document.addEventListener('snippet:saved', (e) => {
            this.createVersion(e.detail)
        })
    }

    /**
     * Load comments for the snippet
     */
    async loadComments() {
        if (!this.snippetIdValue || !this.hasCommentsListTarget) return

        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/comments`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const comments = await response.json()
                this.renderComments(comments)
            }

        } catch (error) {
            console.error("Failed to load comments:", error)
        }
    }

    /**
     * Add new comment
     */
    async addComment(event) {
        if (event) event.preventDefault()
        
        if (!this.hasCommentInputTarget) return

        const content = this.commentInputTarget.value.trim()
        if (!content) return

        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    content: content,
                    lineNumber: this.getSelectedLineNumber()
                })
            })

            if (response.ok) {
                const comment = await response.json()
                this.addCommentToList(comment)
                this.commentInputTarget.value = ''
                this.showSuccess("Comment added successfully")
                
                // Notify via WebSocket
                this.broadcastComment(comment)
            } else {
                throw new Error('Failed to add comment')
            }

        } catch (error) {
            console.error("Failed to add comment:", error)
            this.showError("Failed to add comment")
        }
    }

    /**
     * Reply to comment
     */
    async replyToComment(commentId, content) {
        try {
            const response = await fetch(`/api/comments/${commentId}/replies`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content })
            })

            if (response.ok) {
                const reply = await response.json()
                this.addReplyToComment(commentId, reply)
                this.showSuccess("Reply added successfully")
                return reply
            } else {
                throw new Error('Failed to add reply')
            }

        } catch (error) {
            console.error("Failed to add reply:", error)
            this.showError("Failed to add reply")
        }
    }

    /**
     * Delete comment
     */
    async deleteComment(commentId) {
        if (!confirm('Delete this comment?')) return

        try {
            const response = await fetch(`/api/comments/${commentId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                this.removeCommentFromList(commentId)
                this.showSuccess("Comment deleted")
            } else {
                throw new Error('Failed to delete comment')
            }

        } catch (error) {
            console.error("Failed to delete comment:", error)
            this.showError("Failed to delete comment")
        }
    }

    /**
     * Load version history
     */
    async loadVersions() {
        if (!this.snippetIdValue || !this.hasVersionsListTarget) return

        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/versions`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const versions = await response.json()
                this.renderVersions(versions)
            }

        } catch (error) {
            console.error("Failed to load versions:", error)
        }
    }

    /**
     * Create new version
     */
    async createVersion(snippetData) {
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/versions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    content: snippetData.content,
                    title: snippetData.title,
                    description: snippetData.description,
                    changeMessage: this.getChangeMessage()
                })
            })

            if (response.ok) {
                const version = await response.json()
                this.addVersionToList(version)
                this.currentVersionValue = version.version
            }

        } catch (error) {
            console.error("Failed to create version:", error)
        }
    }

    /**
     * Restore version
     */
    async restoreVersion(versionId) {
        if (!confirm('Restore this version? Current changes will be lost.')) return

        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/versions/${versionId}/restore`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const restoredSnippet = await response.json()
                
                // Update Monaco editor
                if (window.monacoController) {
                    window.monacoController.loadSnippet(restoredSnippet)
                }
                
                // Update snippet controller
                if (window.snippetController) {
                    window.snippetController.populateForm(restoredSnippet)
                }
                
                this.showSuccess("Version restored successfully")
                
                // Reload versions to show new current version
                await this.loadVersions()

            } else {
                throw new Error('Failed to restore version')
            }

        } catch (error) {
            console.error("Failed to restore version:", error)
            this.showError("Failed to restore version")
        }
    }

    /**
     * Compare versions
     */
    async compareVersions(version1, version2) {
        this.element.classList.add('comparing')
        
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/versions/compare`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    version1: version1,
                    version2: version2
                })
            })

            if (response.ok) {
                const diffData = await response.json()
                this.renderDiff(diffData)
            } else {
                throw new Error('Failed to compare versions')
            }

        } catch (error) {
            console.error("Failed to compare versions:", error)
            this.showError("Failed to compare versions")
        } finally {
            this.element.classList.remove('comparing')
        }
    }

    /**
     * Load collaborators
     */
    async loadCollaborators() {
        if (!this.snippetIdValue || !this.hasCollaboratorsListTarget) return

        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/collaborators`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const collaborators = await response.json()
                this.renderCollaborators(collaborators)
            }

        } catch (error) {
            console.error("Failed to load collaborators:", error)
        }
    }

    /**
     * Share snippet with collaborator
     */
    async shareSnippet(email, permission = 'read') {
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/share`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    email: email,
                    permission: permission
                })
            })

            if (response.ok) {
                const collaborator = await response.json()
                this.addCollaboratorToList(collaborator)
                this.showSuccess(`Shared with ${email}`)
                return collaborator
            } else {
                const error = await response.json()
                throw new Error(error.message || 'Failed to share snippet')
            }

        } catch (error) {
            console.error("Failed to share snippet:", error)
            this.showError(error.message || "Failed to share snippet")
        }
    }

    /**
     * Update collaborator permissions
     */
    async updatePermissions(collaboratorId, permission) {
        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/collaborators/${collaboratorId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ permission })
            })

            if (response.ok) {
                this.updateCollaboratorInList(collaboratorId, { permission })
                this.showSuccess("Permissions updated")
            } else {
                throw new Error('Failed to update permissions')
            }

        } catch (error) {
            console.error("Failed to update permissions:", error)
            this.showError("Failed to update permissions")
        }
    }

    /**
     * Remove collaborator
     */
    async removeCollaborator(collaboratorId) {
        if (!confirm('Remove this collaborator?')) return

        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/collaborators/${collaboratorId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                this.removeCollaboratorFromList(collaboratorId)
                this.showSuccess("Collaborator removed")
            } else {
                throw new Error('Failed to remove collaborator')
            }

        } catch (error) {
            console.error("Failed to remove collaborator:", error)
            this.showError("Failed to remove collaborator")
        }
    }

    /**
     * Render functions
     */
    renderComments(comments) {
        if (!this.hasCommentsListTarget) return

        this.commentsListTarget.innerHTML = comments.map(comment => this.renderComment(comment)).join('')
        this.element.classList.toggle('hasComments', comments.length > 0)
    }

    renderComment(comment) {
        const replies = comment.replies ? comment.replies.map(reply => this.renderReply(reply)).join('') : ''
        
        return `
            <div class="comment" data-comment-id="${comment.id}">
                <div class="comment-header">
                    <div class="comment-author">
                        <img src="${comment.author.avatar || '/default-avatar.png'}" alt="${comment.author.name}" class="comment-avatar">
                        <span class="comment-author-name">${comment.author.name}</span>
                    </div>
                    <div class="comment-meta">
                        <time class="comment-time" datetime="${comment.createdAt}">
                            ${this.formatTimeAgo(comment.createdAt)}
                        </time>
                        ${comment.lineNumber ? `<span class="comment-line">Line ${comment.lineNumber}</span>` : ''}
                    </div>
                </div>
                <div class="comment-content">
                    ${this.formatCommentContent(comment.content)}
                </div>
                <div class="comment-actions">
                    <button class="comment-action" data-action="click->collaboration#replyToComment" data-comment-id="${comment.id}">
                        Reply
                    </button>
                    ${comment.canDelete ? `
                        <button class="comment-action comment-delete" data-action="click->collaboration#deleteComment" data-comment-id="${comment.id}">
                            Delete
                        </button>
                    ` : ''}
                </div>
                <div class="comment-replies">
                    ${replies}
                    <div class="reply-form hidden" data-reply-form="${comment.id}">
                        <textarea class="reply-input" placeholder="Write a reply..."></textarea>
                        <div class="reply-actions">
                            <button class="btn btn-sm btn-primary" data-action="click->collaboration#submitReply" data-comment-id="${comment.id}">Reply</button>
                            <button class="btn btn-sm btn-secondary" data-action="click->collaboration#cancelReply" data-comment-id="${comment.id}">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `
    }

    renderReply(reply) {
        return `
            <div class="comment-reply" data-reply-id="${reply.id}">
                <div class="comment-header">
                    <div class="comment-author">
                        <img src="${reply.author.avatar || '/default-avatar.png'}" alt="${reply.author.name}" class="comment-avatar comment-avatar--small">
                        <span class="comment-author-name">${reply.author.name}</span>
                    </div>
                    <div class="comment-meta">
                        <time class="comment-time" datetime="${reply.createdAt}">
                            ${this.formatTimeAgo(reply.createdAt)}
                        </time>
                    </div>
                </div>
                <div class="comment-content">
                    ${this.formatCommentContent(reply.content)}
                </div>
            </div>
        `
    }

    renderVersions(versions) {
        if (!this.hasVersionsListTarget) return

        this.versionsListTarget.innerHTML = versions.map(version => this.renderVersion(version)).join('')
    }

    renderVersion(version) {
        const isCurrent = version.version === this.currentVersionValue
        
        return `
            <div class="version-item ${isCurrent ? 'version-item--current' : ''}" data-version-id="${version.id}">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-number">v${version.version}</span>
                        <span class="version-author">${version.author.name}</span>
                        <time class="version-time" datetime="${version.createdAt}">
                            ${this.formatTimeAgo(version.createdAt)}
                        </time>
                    </div>
                    <div class="version-actions">
                        <button class="version-action" data-action="click->collaboration#previewVersion" data-version-id="${version.id}">
                            Preview
                        </button>
                        ${!isCurrent ? `
                            <button class="version-action" data-action="click->collaboration#restoreVersion" data-version-id="${version.id}">
                                Restore
                            </button>
                        ` : ''}
                        <button class="version-action" data-action="click->collaboration#compareVersion" data-version-id="${version.id}">
                            Compare
                        </button>
                    </div>
                </div>
                <div class="version-message">
                    ${version.changeMessage || 'No change message'}
                </div>
                <div class="version-stats">
                    <span class="version-stat">+${version.linesAdded || 0}</span>
                    <span class="version-stat">-${version.linesRemoved || 0}</span>
                </div>
            </div>
        `
    }

    renderCollaborators(collaborators) {
        if (!this.hasCollaboratorsListTarget) return

        this.collaboratorsListTarget.innerHTML = collaborators.map(collaborator => this.renderCollaborator(collaborator)).join('')
    }

    renderCollaborator(collaborator) {
        return `
            <div class="collaborator-item" data-collaborator-id="${collaborator.id}">
                <div class="collaborator-info">
                    <img src="${collaborator.user.avatar || '/default-avatar.png'}" alt="${collaborator.user.name}" class="collaborator-avatar">
                    <div class="collaborator-details">
                        <span class="collaborator-name">${collaborator.user.name}</span>
                        <span class="collaborator-email">${collaborator.user.email}</span>
                    </div>
                </div>
                <div class="collaborator-permission">
                    <select class="permission-select" data-action="change->collaboration#updatePermissions" data-collaborator-id="${collaborator.id}">
                        <option value="read" ${collaborator.permission === 'read' ? 'selected' : ''}>Read</option>
                        <option value="write" ${collaborator.permission === 'write' ? 'selected' : ''}>Write</option>
                        <option value="admin" ${collaborator.permission === 'admin' ? 'selected' : ''}>Admin</option>
                    </select>
                </div>
                <div class="collaborator-actions">
                    <button class="collaborator-remove" data-action="click->collaboration#removeCollaborator" data-collaborator-id="${collaborator.id}" title="Remove collaborator">
                        ✕
                    </button>
                </div>
            </div>
        `
    }

    renderDiff(diffData) {
        if (!this.hasDiffViewerTarget) return

        // Simple diff rendering - in production, you'd use a library like monaco-diff-editor
        this.diffViewerTarget.innerHTML = `
            <div class="diff-container">
                <div class="diff-header">
                    <h4>Version ${diffData.version1} vs Version ${diffData.version2}</h4>
                </div>
                <div class="diff-content">
                    <div class="diff-side diff-old">
                        <h5>Version ${diffData.version1}</h5>
                        <pre><code>${this.escapeHtml(diffData.oldContent)}</code></pre>
                    </div>
                    <div class="diff-side diff-new">
                        <h5>Version ${diffData.version2}</h5>
                        <pre><code>${this.escapeHtml(diffData.newContent)}</code></pre>
                    </div>
                </div>
                <div class="diff-stats">
                    <span class="diff-stat diff-additions">+${diffData.linesAdded}</span>
                    <span class="diff-stat diff-deletions">-${diffData.linesRemoved}</span>
                </div>
            </div>
        `
    }

    /**
     * WebSocket functionality
     */
    connectWebSocket() {
        if (!this.websocketUrlValue) return

        this.websocket = new WebSocket(this.websocketUrlValue)
        
        this.websocket.onopen = () => {
            console.log("WebSocket connected")
            this.websocket.send(JSON.stringify({
                type: 'join',
                snippetId: this.snippetIdValue
            }))
        }

        this.websocket.onmessage = (event) => {
            this.handleWebSocketMessage(JSON.parse(event.data))
        }

        this.websocket.onclose = () => {
            console.log("WebSocket disconnected")
            // Attempt to reconnect after 5 seconds
            setTimeout(() => this.connectWebSocket(), 5000)
        }
    }

    disconnectWebSocket() {
        if (this.websocket) {
            this.websocket.close()
            this.websocket = null
        }
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'comment_added':
                this.addCommentToList(data.comment)
                break
            case 'version_created':
                this.addVersionToList(data.version)
                break
            case 'collaborator_joined':
                this.addCollaboratorToList(data.collaborator)
                break
            case 'collaborator_left':
                this.removeCollaboratorFromList(data.collaboratorId)
                break
        }
    }

    broadcastComment(comment) {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify({
                type: 'comment_added',
                comment: comment
            }))
        }
    }

    /**
     * Polling for updates (fallback when WebSocket is not available)
     */
    startPolling() {
        const interval = this.pollIntervalValue || 30000 // 30 seconds default
        
        this.pollTimer = setInterval(async () => {
            try {
                await Promise.all([
                    this.loadComments(),
                    this.loadVersions(),
                    this.loadCollaborators()
                ])
            } catch (error) {
                console.error("Polling failed:", error)
            }
        }, interval)
    }

    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer)
            this.pollTimer = null
        }
    }

    /**
     * Utility functions
     */
    getSelectedLineNumber() {
        if (window.monacoEditor) {
            const position = window.monacoEditor.getPosition()
            return position ? position.lineNumber : null
        }
        return null
    }

    getChangeMessage() {
        // In a real implementation, this might come from a modal or form
        return "Updated snippet content"
    }

    handleContentChange(content) {
        // Debounced auto-save or version tracking
        if (this.contentChangeTimer) {
            clearTimeout(this.contentChangeTimer)
        }
        
        this.contentChangeTimer = setTimeout(() => {
            // Auto-save draft or create checkpoint
            this.saveDraft(content)
        }, 5000)
    }

    async saveDraft(content) {
        try {
            await fetch(`/api/snippets/${this.snippetIdValue}/draft`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content })
            })
        } catch (error) {
            console.error("Failed to save draft:", error)
        }
    }

    formatTimeAgo(dateString) {
        const date = new Date(dateString)
        const now = new Date()
        const diffMs = now.getTime() - date.getTime()
        
        const diffMinutes = Math.floor(diffMs / (1000 * 60))
        const diffHours = Math.floor(diffMinutes / 60)
        const diffDays = Math.floor(diffHours / 24)
        
        if (diffMinutes < 1) return 'just now'
        if (diffMinutes < 60) return `${diffMinutes}m ago`
        if (diffHours < 24) return `${diffHours}h ago`
        if (diffDays < 30) return `${diffDays}d ago`
        return date.toLocaleDateString()
    }

    formatCommentContent(content) {
        // Basic markdown-like formatting
        return content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>')
    }

    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    /**
     * UI helper functions
     */
    addCommentToList(comment) {
        if (!this.hasCommentsListTarget) return
        
        const commentHtml = this.renderComment(comment)
        this.commentsListTarget.insertAdjacentHTML('beforeend', commentHtml)
        this.element.classList.add('hasComments')
    }

    removeCommentFromList(commentId) {
        const commentElement = this.commentsListTarget.querySelector(`[data-comment-id="${commentId}"]`)
        if (commentElement) {
            commentElement.remove()
            
            if (this.commentsListTarget.children.length === 0) {
                this.element.classList.remove('hasComments')
            }
        }
    }

    addVersionToList(version) {
        if (!this.hasVersionsListTarget) return
        
        const versionHtml = this.renderVersion(version)
        this.versionsListTarget.insertAdjacentHTML('afterbegin', versionHtml)
    }

    addCollaboratorToList(collaborator) {
        if (!this.hasCollaboratorsListTarget) return
        
        const collaboratorHtml = this.renderCollaborator(collaborator)
        this.collaboratorsListTarget.insertAdjacentHTML('beforeend', collaboratorHtml)
    }

    removeCollaboratorFromList(collaboratorId) {
        const collaboratorElement = this.collaboratorsListTarget.querySelector(`[data-collaborator-id="${collaboratorId}"]`)
        if (collaboratorElement) {
            collaboratorElement.remove()
        }
    }

    updateCollaboratorInList(collaboratorId, updates) {
        const collaboratorElement = this.collaboratorsListTarget.querySelector(`[data-collaborator-id="${collaboratorId}"]`)
        if (!collaboratorElement) return
        
        if (updates.permission) {
            const select = collaboratorElement.querySelector('.permission-select')
            if (select) {
                select.value = updates.permission
            }
        }
    }

    addReplyToComment(commentId, reply) {
        const commentElement = this.commentsListTarget.querySelector(`[data-comment-id="${commentId}"]`)
        if (!commentElement) return
        
        const repliesContainer = commentElement.querySelector('.comment-replies')
        const replyForm = repliesContainer.querySelector(`[data-reply-form="${commentId}"]`)
        
        const replyHtml = this.renderReply(reply)
        replyForm.insertAdjacentHTML('beforebegin', replyHtml)
        
        // Hide reply form
        replyForm.classList.add('hidden')
        replyForm.querySelector('.reply-input').value = ''
    }

    /**
     * Notification helpers
     */
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
}