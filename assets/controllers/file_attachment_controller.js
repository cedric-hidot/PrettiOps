import { Controller } from "@hotwired/stimulus"

/**
 * File Attachment Controller
 * 
 * Handles file uploads, drag & drop, TTL management, and attachment display.
 * Provides secure file handling with automatic expiration and cleanup.
 * Supports multiple file types with preview functionality.
 */
export default class extends Controller {
    static targets = [
        "dropZone", "fileInput", "attachmentsList", "uploadProgress", 
        "uploadButton", "browseButton", "maxSizeInfo", "acceptedTypesInfo"
    ]
    
    static values = {
        maxFileSize: Number,
        maxFiles: Number,
        acceptedTypes: Array,
        uploadUrl: String,
        ttl: Number, // Time to live in seconds
        snippetId: String,
        allowedExtensions: Array
    }

    static classes = [
        "uploading", "dragOver", "error", "success"
    ]

    connect() {
        console.log("File attachment controller connected")
        this.setupDropZone()
        this.setupFileInput()
        this.attachedFiles = new Map()
        this.uploadQueue = []
        
        // Load existing attachments
        this.loadExistingAttachments()
        
        // Global reference
        window.fileAttachmentController = this
    }

    disconnect() {
        this.clearUploadQueue()
        window.fileAttachmentController = null
    }

    /**
     * Setup drag & drop functionality
     */
    setupDropZone() {
        if (!this.hasDropZoneTarget) return

        // Prevent default drag behaviors
        ;['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZoneTarget.addEventListener(eventName, this.preventDefaults.bind(this), false)
            document.body.addEventListener(eventName, this.preventDefaults.bind(this), false)
        })

        // Highlight drop zone when item is dragged over it
        ;['dragenter', 'dragover'].forEach(eventName => {
            this.dropZoneTarget.addEventListener(eventName, this.highlight.bind(this), false)
        })

        ;['dragleave', 'drop'].forEach(eventName => {
            this.dropZoneTarget.addEventListener(eventName, this.unhighlight.bind(this), false)
        })

        // Handle dropped files
        this.dropZoneTarget.addEventListener('drop', this.handleDrop.bind(this), false)
    }

    /**
     * Setup file input
     */
    setupFileInput() {
        if (!this.hasFileInputTarget) return

        this.fileInputTarget.addEventListener('change', (event) => {
            this.handleFiles(event.target.files)
        })

        // Set input attributes based on values
        if (this.acceptedTypesValue) {
            this.fileInputTarget.accept = this.acceptedTypesValue.join(',')
        }

        if (this.maxFilesValue > 1) {
            this.fileInputTarget.multiple = true
        }
    }

    /**
     * Handle browse button click
     */
    browseFiles(event) {
        event.preventDefault()
        if (this.hasFileInputTarget) {
            this.fileInputTarget.click()
        }
    }

    /**
     * Prevent default drag behaviors
     */
    preventDefaults(e) {
        e.preventDefault()
        e.stopPropagation()
    }

    /**
     * Highlight drop zone
     */
    highlight(e) {
        this.dropZoneTarget.classList.add('drag-over')
    }

    /**
     * Remove highlight from drop zone
     */
    unhighlight(e) {
        this.dropZoneTarget.classList.remove('drag-over')
    }

    /**
     * Handle dropped files
     */
    handleDrop(e) {
        const dt = e.dataTransfer
        const files = dt.files
        this.handleFiles(files)
    }

    /**
     * Handle selected/dropped files
     */
    async handleFiles(files) {
        const fileArray = Array.from(files)
        
        // Validate files
        const validFiles = this.validateFiles(fileArray)
        
        if (validFiles.length === 0) {
            this.showError("No valid files to upload")
            return
        }

        // Check max files limit
        const totalFiles = this.attachedFiles.size + validFiles.length
        if (this.maxFilesValue && totalFiles > this.maxFilesValue) {
            this.showError(`Maximum ${this.maxFilesValue} files allowed`)
            return
        }

        // Add to upload queue
        validFiles.forEach(file => {
            this.uploadQueue.push({
                id: this.generateFileId(),
                file,
                status: 'queued',
                progress: 0
            })
        })

        // Start uploading
        await this.processUploadQueue()
    }

    /**
     * Validate files before upload
     */
    validateFiles(files) {
        return files.filter(file => {
            // Check file size
            if (this.maxFileSizeValue && file.size > this.maxFileSizeValue) {
                this.showError(`File "${file.name}" is too large (max: ${this.formatFileSize(this.maxFileSizeValue)})`)
                return false
            }

            // Check file type
            if (this.acceptedTypesValue && !this.acceptedTypesValue.includes(file.type)) {
                this.showError(`File type "${file.type}" is not allowed`)
                return false
            }

            // Check file extension
            if (this.allowedExtensionsValue) {
                const extension = '.' + file.name.split('.').pop().toLowerCase()
                if (!this.allowedExtensionsValue.includes(extension)) {
                    this.showError(`File extension "${extension}" is not allowed`)
                    return false
                }
            }

            return true
        })
    }

    /**
     * Process upload queue
     */
    async processUploadQueue() {
        if (this.uploadQueue.length === 0) return

        this.setUploadingState(true)

        try {
            // Upload files concurrently (max 3 at a time)
            const maxConcurrent = 3
            const batches = this.createBatches(this.uploadQueue, maxConcurrent)

            for (const batch of batches) {
                await Promise.all(batch.map(fileData => this.uploadFile(fileData)))
            }

            this.showSuccess(`${this.uploadQueue.length} file(s) uploaded successfully`)
            this.uploadQueue = []

        } catch (error) {
            console.error("Upload failed:", error)
            this.showError("Some files failed to upload")
        } finally {
            this.setUploadingState(false)
        }
    }

    /**
     * Upload single file
     */
    async uploadFile(fileData) {
        try {
            fileData.status = 'uploading'
            this.updateFileProgress(fileData.id, 0, 'Uploading...')

            const formData = new FormData()
            formData.append('file', fileData.file)
            formData.append('snippet_id', this.snippetIdValue)
            formData.append('ttl', this.ttlValue || 86400) // 24 hours default

            const response = await fetch(this.uploadUrlValue || '/api/attachments', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })

            if (!response.ok) {
                throw new Error('Upload failed')
            }

            const result = await response.json()
            
            // Add to attached files
            this.attachedFiles.set(fileData.id, {
                ...result,
                localId: fileData.id,
                fileName: fileData.file.name,
                fileSize: fileData.file.size,
                uploadedAt: Date.now()
            })

            // Update UI
            this.addAttachmentToList(this.attachedFiles.get(fileData.id))
            fileData.status = 'completed'
            
            this.trackUpload(fileData.file)

        } catch (error) {
            console.error("File upload failed:", error)
            fileData.status = 'error'
            this.updateFileProgress(fileData.id, 0, 'Upload failed')
            throw error
        }
    }

    /**
     * Load existing attachments
     */
    async loadExistingAttachments() {
        if (!this.snippetIdValue) return

        try {
            const response = await fetch(`/api/snippets/${this.snippetIdValue}/attachments`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const attachments = await response.json()
                
                attachments.forEach(attachment => {
                    this.attachedFiles.set(attachment.id, attachment)
                    this.addAttachmentToList(attachment)
                })
            }

        } catch (error) {
            console.error("Failed to load existing attachments:", error)
        }
    }

    /**
     * Add attachment to the display list
     */
    addAttachmentToList(attachment) {
        if (!this.hasAttachmentsListTarget) return

        const attachmentElement = document.createElement('div')
        attachmentElement.className = 'attachment-item'
        attachmentElement.dataset.attachmentId = attachment.id || attachment.localId
        
        const fileIcon = this.getFileIcon(attachment.fileName || attachment.name)
        const fileSize = this.formatFileSize(attachment.fileSize || attachment.size)
        const expiresAt = this.formatExpiration(attachment.expiresAt)
        
        attachmentElement.innerHTML = `
            <div class="attachment-content">
                <div class="attachment-icon" aria-hidden="true">${fileIcon}</div>
                <div class="attachment-info">
                    <div class="attachment-name" title="${attachment.fileName || attachment.name}">
                        ${attachment.fileName || attachment.name}
                    </div>
                    <div class="attachment-meta">
                        <span class="attachment-size">${fileSize}</span>
                        <span class="attachment-separator">•</span>
                        <span class="attachment-expires" title="Expires: ${expiresAt}">
                            Expires ${this.getRelativeTime(attachment.expiresAt)}
                        </span>
                    </div>
                </div>
                <div class="attachment-actions">
                    <button 
                        class="attachment-action attachment-download" 
                        data-action="click->file-attachment#downloadAttachment"
                        data-attachment-id="${attachment.id || attachment.localId}"
                        title="Download file"
                        aria-label="Download ${attachment.fileName || attachment.name}"
                    >
                        📥
                    </button>
                    <button 
                        class="attachment-action attachment-delete" 
                        data-action="click->file-attachment#removeAttachment"
                        data-attachment-id="${attachment.id || attachment.localId}"
                        title="Remove file"
                        aria-label="Remove ${attachment.fileName || attachment.name}"
                    >
                        🗑️
                    </button>
                </div>
            </div>
            <div class="attachment-progress hidden">
                <div class="attachment-progress-bar" style="width: 0%"></div>
                <div class="attachment-progress-text">0%</div>
            </div>
        `

        this.attachmentsListTarget.appendChild(attachmentElement)
        
        // Setup expiration monitoring
        this.setupExpirationMonitoring(attachment)
    }

    /**
     * Download attachment
     */
    async downloadAttachment(event) {
        const attachmentId = event.target.dataset.attachmentId
        const attachment = this.attachedFiles.get(attachmentId)
        
        if (!attachment) return

        try {
            const response = await fetch(`/api/attachments/${attachment.id}/download`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (!response.ok) {
                throw new Error('Download failed')
            }

            const blob = await response.blob()
            const url = URL.createObjectURL(blob)
            
            const a = document.createElement('a')
            a.href = url
            a.download = attachment.fileName || attachment.name
            document.body.appendChild(a)
            a.click()
            document.body.removeChild(a)
            
            URL.revokeObjectURL(url)
            
            this.showSuccess(`Downloaded ${attachment.fileName || attachment.name}`)
            this.trackDownload(attachment)

        } catch (error) {
            console.error("Download failed:", error)
            this.showError("Failed to download file")
        }
    }

    /**
     * Remove attachment
     */
    async removeAttachment(event) {
        const attachmentId = event.target.dataset.attachmentId
        const attachment = this.attachedFiles.get(attachmentId)
        
        if (!attachment) return

        if (!confirm(`Remove "${attachment.fileName || attachment.name}"?`)) {
            return
        }

        try {
            const response = await fetch(`/api/attachments/${attachment.id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (!response.ok) {
                throw new Error('Delete failed')
            }

            // Remove from UI
            const attachmentElement = this.attachmentsListTarget.querySelector(`[data-attachment-id="${attachmentId}"]`)
            if (attachmentElement) {
                attachmentElement.style.transition = 'all 0.3s ease-out'
                attachmentElement.style.opacity = '0'
                attachmentElement.style.transform = 'translateX(-100%)'
                
                setTimeout(() => {
                    attachmentElement.remove()
                }, 300)
            }

            // Remove from map
            this.attachedFiles.delete(attachmentId)
            
            this.showSuccess("File removed successfully")
            this.trackRemoval(attachment)

        } catch (error) {
            console.error("Remove failed:", error)
            this.showError("Failed to remove file")
        }
    }

    /**
     * Setup expiration monitoring for attachment
     */
    setupExpirationMonitoring(attachment) {
        if (!attachment.expiresAt) return

        const expirationTime = new Date(attachment.expiresAt).getTime()
        const now = Date.now()
        const timeUntilExpiration = expirationTime - now

        if (timeUntilExpiration <= 0) {
            // Already expired
            this.markAsExpired(attachment.id || attachment.localId)
            return
        }

        // Set timer to mark as expired
        setTimeout(() => {
            this.markAsExpired(attachment.id || attachment.localId)
        }, timeUntilExpiration)

        // Show warning if expires soon (within 1 hour)
        if (timeUntilExpiration < 3600000) {
            this.showExpirationWarning(attachment)
        }
    }

    /**
     * Mark attachment as expired
     */
    markAsExpired(attachmentId) {
        const attachmentElement = this.attachmentsListTarget.querySelector(`[data-attachment-id="${attachmentId}"]`)
        if (attachmentElement) {
            attachmentElement.classList.add('attachment-item--expired')
            
            const expiresElement = attachmentElement.querySelector('.attachment-expires')
            if (expiresElement) {
                expiresElement.textContent = 'Expired'
                expiresElement.classList.add('attachment-expires--expired')
            }

            // Disable download button
            const downloadBtn = attachmentElement.querySelector('.attachment-download')
            if (downloadBtn) {
                downloadBtn.disabled = true
                downloadBtn.title = 'File has expired'
            }
        }
    }

    /**
     * Show expiration warning
     */
    showExpirationWarning(attachment) {
        this.showWarning(`File "${attachment.fileName || attachment.name}" expires soon`, {
            actions: [{
                id: 'extend',
                label: 'Extend TTL',
                type: 'primary',
                callback: () => this.extendTTL(attachment.id)
            }]
        })
    }

    /**
     * Extend TTL for attachment
     */
    async extendTTL(attachmentId) {
        try {
            const response = await fetch(`/api/attachments/${attachmentId}/extend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    ttl: this.ttlValue || 86400
                })
            })

            if (!response.ok) {
                throw new Error('Failed to extend TTL')
            }

            const result = await response.json()
            
            // Update local attachment data
            const attachment = this.attachedFiles.get(attachmentId)
            if (attachment) {
                attachment.expiresAt = result.expiresAt
                this.updateExpirationDisplay(attachmentId, result.expiresAt)
                this.setupExpirationMonitoring(attachment)
            }

            this.showSuccess("File expiration extended successfully")

        } catch (error) {
            console.error("Failed to extend TTL:", error)
            this.showError("Failed to extend file expiration")
        }
    }

    /**
     * Update expiration display
     */
    updateExpirationDisplay(attachmentId, expiresAt) {
        const attachmentElement = this.attachmentsListTarget.querySelector(`[data-attachment-id="${attachmentId}"]`)
        if (!attachmentElement) return

        const expiresElement = attachmentElement.querySelector('.attachment-expires')
        if (expiresElement) {
            expiresElement.textContent = `Expires ${this.getRelativeTime(expiresAt)}`
            expiresElement.title = `Expires: ${this.formatExpiration(expiresAt)}`
            expiresElement.classList.remove('attachment-expires--expired')
        }

        attachmentElement.classList.remove('attachment-item--expired')
        
        const downloadBtn = attachmentElement.querySelector('.attachment-download')
        if (downloadBtn) {
            downloadBtn.disabled = false
            downloadBtn.title = 'Download file'
        }
    }

    /**
     * Update file upload progress
     */
    updateFileProgress(fileId, progress, statusText) {
        const attachmentElement = this.attachmentsListTarget.querySelector(`[data-attachment-id="${fileId}"]`)
        if (!attachmentElement) return

        const progressElement = attachmentElement.querySelector('.attachment-progress')
        const progressBar = progressElement.querySelector('.attachment-progress-bar')
        const progressText = progressElement.querySelector('.attachment-progress-text')

        progressElement.classList.remove('hidden')
        progressBar.style.width = `${progress}%`
        progressText.textContent = statusText || `${progress}%`
    }

    /**
     * Set uploading state
     */
    setUploadingState(uploading) {
        this.element.classList.toggle('uploading', uploading)
        
        if (this.hasUploadButtonTarget) {
            this.uploadButtonTarget.disabled = uploading
            this.uploadButtonTarget.textContent = uploading ? 'Uploading...' : 'Upload'
        }

        if (this.hasBrowseButtonTarget) {
            this.browseButtonTarget.disabled = uploading
        }
    }

    /**
     * Utility functions
     */
    generateFileId() {
        return `file-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
    }

    getFileIcon(filename) {
        const extension = filename.split('.').pop().toLowerCase()
        const iconMap = {
            pdf: '📄',
            doc: '📝', docx: '📝',
            xls: '📊', xlsx: '📊',
            ppt: '📊', pptx: '📊',
            txt: '📄',
            jpg: '🖼️', jpeg: '🖼️', png: '🖼️', gif: '🖼️',
            mp4: '🎥', mov: '🎥', avi: '🎥',
            mp3: '🎵', wav: '🎵',
            zip: '🗜️', rar: '🗜️',
            js: '📜', css: '📜', html: '📜'
        }
        return iconMap[extension] || '📎'
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes'
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB', 'GB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    }

    formatExpiration(expiresAt) {
        return new Date(expiresAt).toLocaleString()
    }

    getRelativeTime(dateString) {
        const date = new Date(dateString)
        const now = new Date()
        const diffMs = date.getTime() - now.getTime()
        
        if (diffMs < 0) return 'expired'
        
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
        const diffDays = Math.floor(diffHours / 24)
        
        if (diffDays > 0) return `in ${diffDays} day${diffDays > 1 ? 's' : ''}`
        if (diffHours > 0) return `in ${diffHours} hour${diffHours > 1 ? 's' : ''}`
        
        const diffMinutes = Math.floor(diffMs / (1000 * 60))
        return `in ${diffMinutes} minute${diffMinutes > 1 ? 's' : ''}`
    }

    createBatches(array, batchSize) {
        const batches = []
        for (let i = 0; i < array.length; i += batchSize) {
            batches.push(array.slice(i, i + batchSize))
        }
        return batches
    }

    clearUploadQueue() {
        this.uploadQueue.forEach(fileData => {
            if (fileData.status === 'uploading') {
                // Cancel upload if possible
                fileData.status = 'cancelled'
            }
        })
        this.uploadQueue = []
    }

    /**
     * Tracking functions
     */
    trackUpload(file) {
        if (window.analytics) {
            window.analytics.track('file_uploaded', {
                file_size: file.size,
                file_type: file.type,
                timestamp: new Date().toISOString()
            })
        }
    }

    trackDownload(attachment) {
        if (window.analytics) {
            window.analytics.track('file_downloaded', {
                attachment_id: attachment.id,
                file_size: attachment.fileSize || attachment.size,
                timestamp: new Date().toISOString()
            })
        }
    }

    trackRemoval(attachment) {
        if (window.analytics) {
            window.analytics.track('file_removed', {
                attachment_id: attachment.id,
                timestamp: new Date().toISOString()
            })
        }
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

    showWarning(message, options = {}) {
        if (window.notificationManager) {
            window.notificationManager.warning(message, options)
        }
    }
}