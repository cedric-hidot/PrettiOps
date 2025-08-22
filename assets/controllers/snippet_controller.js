import { Controller } from "@hotwired/stimulus"

/**
 * Snippet Controller
 * 
 * Manages snippet-related functionality including navigation, state management,
 * and interaction with snippet cards and lists.
 * 
 * Targets:
 * - card: Snippet card elements
 * - title: Snippet title
 * - description: Snippet description  
 * - language: Language display
 * - status: Status indicator
 * - stats: Statistics display
 * 
 * Values:
 * - id: Snippet ID
 * - url: Snippet URL
 * - status: Current status
 * - pinned: Pin state
 */
export default class extends Controller {
    static targets = ["card", "title", "description", "language", "status", "stats"]
    
    static values = {
        id: String,
        url: String,
        status: String,
        pinned: Boolean
    }

    connect() {
        console.log("Snippet controller connected", this.idValue)
        this.setupCardInteractions()
        this.checkStatus()
    }

    /**
     * Navigate to snippet (when card is clicked)
     */
    navigate(event) {
        // Don't navigate if clicking on toolbar buttons
        if (event.target.closest('.snippet-tool') || event.target.closest('.snippet-toolbar')) {
            return
        }

        if (this.urlValue) {
            window.location.href = this.urlValue
        } else if (this.idValue) {
            window.location.href = `/snippets/${this.idValue}`
        }
        
        this.trackInteraction('navigate')
    }

    /**
     * Handle keyboard navigation
     */
    handleKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            this.navigate(event)
        }
    }

    /**
     * Update snippet status
     */
    updateStatus(newStatus) {
        this.statusValue = newStatus
        
        if (this.hasStatusTarget) {
            this.renderStatus(newStatus)
        }
        
        // Update card data attribute for filtering
        if (this.hasCardTarget) {
            this.cardTarget.dataset.status = newStatus
        }
        
        this.trackStatusChange(newStatus)
    }

    /**
     * Update snippet statistics
     */
    updateStats(stats) {
        if (this.hasStatsTarget) {
            this.renderStats(stats)
        }
    }

    /**
     * Toggle pin state
     */
    async togglePin() {
        try {
            const newPinnedState = !this.pinnedValue
            
            // Update via API
            await this.updatePinState(newPinnedState)
            
            // Update local state
            this.pinnedValue = newPinnedState
            
            // Update UI
            this.updatePinDisplay(newPinnedState)
            
            this.showNotification(
                newPinnedState ? "Snippet pinned" : "Snippet unpinned",
                "success"
            )
            
        } catch (error) {
            console.error("Failed to toggle pin state:", error)
            this.showNotification("Failed to update pin state", "error")
        }
    }

    /**
     * Delete snippet
     */
    async delete() {
        if (!confirm("Are you sure you want to delete this snippet? This action cannot be undone.")) {
            return
        }

        try {
            await this.performDelete()
            
            // Remove from DOM with animation
            this.animateRemoval()
            
            this.showNotification("Snippet deleted successfully", "success")
            
        } catch (error) {
            console.error("Failed to delete snippet:", error)
            this.showNotification("Failed to delete snippet", "error")
        }
    }

    /**
     * Duplicate snippet
     */
    async duplicate() {
        try {
            const newSnippet = await this.performDuplicate()
            
            this.showNotification("Snippet duplicated successfully", "success")
            
            // Optionally redirect to new snippet
            if (newSnippet.id) {
                setTimeout(() => {
                    window.location.href = `/snippets/${newSnippet.id}`
                }, 1000)
            }
            
        } catch (error) {
            console.error("Failed to duplicate snippet:", error)
            this.showNotification("Failed to duplicate snippet", "error")
        }
    }

    /**
     * Archive snippet
     */
    async archive() {
        try {
            await this.performArchive()
            
            this.updateStatus('archived')
            this.animateArchival()
            
            this.showNotification("Snippet archived", "success")
            
        } catch (error) {
            console.error("Failed to archive snippet:", error)
            this.showNotification("Failed to archive snippet", "error")
        }
    }

    /**
     * Restore archived snippet
     */
    async restore() {
        try {
            await this.performRestore()
            
            this.updateStatus('active')
            this.animateRestoration()
            
            this.showNotification("Snippet restored", "success")
            
        } catch (error) {
            console.error("Failed to restore snippet:", error)
            this.showNotification("Failed to restore snippet", "error")
        }
    }

    /**
     * Setup card interactions
     */
    setupCardInteractions() {
        if (this.hasCardTarget) {
            // Add hover effects
            this.cardTarget.addEventListener('mouseenter', () => {
                this.cardTarget.classList.add('snippet-card--hover')
            })
            
            this.cardTarget.addEventListener('mouseleave', () => {
                this.cardTarget.classList.remove('snippet-card--hover')
            })
            
            // Add keyboard support
            this.cardTarget.addEventListener('keydown', this.handleKeydown.bind(this))
            
            // Add focus management
            this.cardTarget.addEventListener('focus', () => {
                this.cardTarget.classList.add('snippet-card--focused')
            })
            
            this.cardTarget.addEventListener('blur', () => {
                this.cardTarget.classList.remove('snippet-card--focused')
            })
        }
    }

    /**
     * Check and update status based on expiration
     */
    checkStatus() {
        if (this.statusValue === 'active') {
            this.checkExpiration()
        }
    }

    /**
     * Check if snippet is expiring soon
     */
    checkExpiration() {
        // This would typically check against expiration date
        // For now, we'll simulate the logic
        
        const expirationDate = this.element.dataset.expirationDate
        if (expirationDate) {
            const now = new Date()
            const expires = new Date(expirationDate)
            const timeUntilExpiration = expires.getTime() - now.getTime()
            const hoursUntilExpiration = timeUntilExpiration / (1000 * 60 * 60)
            
            if (hoursUntilExpiration < 0) {
                this.updateStatus('expired')
            } else if (hoursUntilExpiration < 24) {
                this.updateStatus('expiring')
            }
        }
    }

    /**
     * Render status indicator
     */
    renderStatus(status) {
        const statusElement = this.statusTarget
        const statusDot = statusElement.querySelector('.status-dot')
        const statusText = statusElement.querySelector('.status-text')
        
        // Update dot class
        if (statusDot) {
            statusDot.className = `status-dot status-dot--${status}`
        }
        
        // Update text
        if (statusText) {
            const statusTexts = {
                active: 'Active',
                expiring: 'Expiring Soon',
                expired: 'Expired',
                archived: 'Archived',
                draft: 'Draft'
            }
            
            statusText.textContent = statusTexts[status] || status
        }
    }

    /**
     * Render statistics
     */
    renderStats(stats) {
        const statsContainer = this.statsTarget
        
        statsContainer.innerHTML = `
            <span class="stat-item" title="Views">
                <span aria-hidden="true">👁️</span>
                <span class="sr-only">Views: </span>
                ${stats.views || 0}
            </span>
            <span class="stat-item" title="Email sends">
                <span aria-hidden="true">📧</span>
                <span class="sr-only">Email sends: </span>
                ${stats.emailSends || 0}
            </span>
            <span class="stat-item" title="Comments">
                <span aria-hidden="true">💬</span>
                <span class="sr-only">Comments: </span>
                ${stats.comments || 0}
            </span>
        `
    }

    /**
     * Update pin display
     */
    updatePinDisplay(pinned) {
        const pinButton = this.element.querySelector('[data-action*="pin"]')
        
        if (pinButton) {
            if (pinned) {
                pinButton.classList.add('pinned')
                pinButton.title = "Unpin snippet"
            } else {
                pinButton.classList.remove('pinned')
                pinButton.title = "Pin snippet"
            }
        }
    }

    /**
     * Animate card removal
     */
    animateRemoval() {
        if (this.hasCardTarget) {
            this.cardTarget.style.transition = 'all 0.3s ease-out'
            this.cardTarget.style.transform = 'translateX(-100%)'
            this.cardTarget.style.opacity = '0'
            
            setTimeout(() => {
                this.cardTarget.remove()
            }, 300)
        }
    }

    /**
     * Animate archival
     */
    animateArchival() {
        if (this.hasCardTarget) {
            this.cardTarget.classList.add('snippet-card--archived')
            this.cardTarget.style.opacity = '0.6'
        }
    }

    /**
     * Animate restoration
     */
    animateRestoration() {
        if (this.hasCardTarget) {
            this.cardTarget.classList.remove('snippet-card--archived')
            this.cardTarget.style.opacity = '1'
        }
    }

    /**
     * API calls
     */
    async updatePinState(pinned) {
        const response = await fetch(`/api/snippets/${this.idValue}/pin`, {
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

    async performDelete() {
        const response = await fetch(`/api/snippets/${this.idValue}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        
        if (!response.ok) {
            throw new Error("Failed to delete snippet")
        }
        
        return response.json()
    }

    async performDuplicate() {
        const response = await fetch(`/api/snippets/${this.idValue}/duplicate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        
        if (!response.ok) {
            throw new Error("Failed to duplicate snippet")
        }
        
        return response.json()
    }

    async performArchive() {
        const response = await fetch(`/api/snippets/${this.idValue}/archive`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ archived: true })
        })
        
        if (!response.ok) {
            throw new Error("Failed to archive snippet")
        }
        
        return response.json()
    }

    async performRestore() {
        const response = await fetch(`/api/snippets/${this.idValue}/archive`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ archived: false })
        })
        
        if (!response.ok) {
            throw new Error("Failed to restore snippet")
        }
        
        return response.json()
    }

    /**
     * Utility functions
     */
    trackInteraction(action) {
        if (window.analytics) {
            window.analytics.track('snippet_interaction', {
                action: action,
                snippet_id: this.idValue,
                timestamp: new Date().toISOString()
            })
        }
        
        console.log(`Snippet interaction: ${action}`)
    }

    trackStatusChange(newStatus) {
        if (window.analytics) {
            window.analytics.track('snippet_status_change', {
                snippet_id: this.idValue,
                new_status: newStatus,
                timestamp: new Date().toISOString()
            })
        }
        
        console.log(`Snippet status changed to: ${newStatus}`)
    }

    showNotification(message, type = 'info') {
        if (window.notificationManager) {
            window.notificationManager.show(message, type)
        } else {
            console.log(`${type.toUpperCase()}: ${message}`)
        }
    }
}