import { Controller } from "@hotwired/stimulus"

/**
 * Notification Controller
 * 
 * Handles toast notifications, alerts, and user feedback messages.
 * Provides a unified interface for showing success, error, warning,
 * and info messages with customizable styling and behavior.
 */
export default class extends Controller {
    static targets = ["container"]
    
    static values = {
        position: String,
        maxNotifications: Number,
        autoHideDelay: Number,
        pauseOnHover: Boolean
    }

    static classes = [
        "notification", "success", "error", "warning", "info"
    ]

    connect() {
        console.log("Notification controller connected")
        this.setupContainer()
        this.notifications = new Map()
        
        // Global notification manager
        window.notificationManager = this
        
        // Listen for notification events from other controllers
        this.setupEventListeners()
    }

    disconnect() {
        window.notificationManager = null
        this.clearAllNotifications()
    }

    /**
     * Setup notification container
     */
    setupContainer() {
        if (!this.hasContainerTarget) {
            this.createContainer()
        }
        
        const position = this.positionValue || 'top-right'
        this.containerTarget.className = `notification-container notification-container--${position}`
        this.containerTarget.setAttribute('aria-live', 'polite')
        this.containerTarget.setAttribute('aria-label', 'Notifications')
    }

    /**
     * Create notification container if it doesn't exist
     */
    createContainer() {
        const container = document.createElement('div')
        container.dataset.notificationTarget = 'container'
        this.element.appendChild(container)
    }

    /**
     * Setup event listeners for notifications from other controllers
     */
    setupEventListeners() {
        // Listen for custom notification events
        document.addEventListener('notification:show', (e) => {
            const { message, type, options } = e.detail
            this.show(message, type, options)
        })
        
        document.addEventListener('notification:clear', (e) => {
            if (e.detail?.id) {
                this.hide(e.detail.id)
            } else {
                this.clearAllNotifications()
            }
        })
    }

    /**
     * Show notification
     */
    show(message, type = 'info', options = {}) {
        const id = options.id || this.generateId()
        const notification = this.createNotification(id, message, type, options)
        
        // Remove oldest notification if at max
        this.enforceMaxNotifications()
        
        // Add to container
        this.containerTarget.appendChild(notification.element)
        this.notifications.set(id, notification)
        
        // Trigger animation
        requestAnimationFrame(() => {
            notification.element.classList.add('notification--visible')
        })
        
        // Setup auto-hide
        this.setupAutoHide(notification, options)
        
        // Track notification
        this.trackNotification(type, message)
        
        return id
    }

    /**
     * Show success notification
     */
    success(message, options = {}) {
        return this.show(message, 'success', {
            icon: '✅',
            ...options
        })
    }

    /**
     * Show error notification
     */
    error(message, options = {}) {
        return this.show(message, 'error', {
            icon: '❌',
            persistent: true, // Errors don't auto-hide by default
            ...options
        })
    }

    /**
     * Show warning notification
     */
    warning(message, options = {}) {
        return this.show(message, 'warning', {
            icon: '⚠️',
            ...options
        })
    }

    /**
     * Show info notification
     */
    info(message, options = {}) {
        return this.show(message, 'info', {
            icon: 'ℹ️',
            ...options
        })
    }

    /**
     * Hide specific notification
     */
    hide(id) {
        const notification = this.notifications.get(id)
        if (!notification) return

        // Clear timers
        if (notification.hideTimer) {
            clearTimeout(notification.hideTimer)
        }

        // Animate out
        notification.element.classList.remove('notification--visible')
        notification.element.classList.add('notification--hiding')

        // Remove from DOM
        setTimeout(() => {
            if (notification.element.parentNode) {
                notification.element.parentNode.removeChild(notification.element)
            }
            this.notifications.delete(id)
        }, 300)
    }

    /**
     * Clear all notifications
     */
    clearAllNotifications() {
        for (const [id] of this.notifications) {
            this.hide(id)
        }
    }

    /**
     * Create notification element
     */
    createNotification(id, message, type, options) {
        const element = document.createElement('div')
        element.className = `notification notification--${type}`
        element.setAttribute('role', 'alert')
        element.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite')
        element.dataset.notificationId = id
        
        const icon = options.icon || this.getDefaultIcon(type)
        const title = options.title || this.getDefaultTitle(type)
        
        element.innerHTML = `
            <div class="notification-content">
                ${icon ? `<div class="notification-icon" aria-hidden="true">${icon}</div>` : ''}
                <div class="notification-body">
                    ${title ? `<div class="notification-title">${title}</div>` : ''}
                    <div class="notification-message">${this.escapeHtml(message)}</div>
                    ${options.details ? `<div class="notification-details">${this.escapeHtml(options.details)}</div>` : ''}
                </div>
                <button class="notification-close" aria-label="Close notification" data-action="click->notification#closeNotification" data-notification-id="${id}">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            ${options.actions ? this.renderActions(options.actions, id) : ''}
        `
        
        // Add custom classes
        if (options.className) {
            element.classList.add(...options.className.split(' '))
        }
        
        // Setup hover behavior
        if (this.pauseOnHoverValue) {
            element.addEventListener('mouseenter', () => {
                const notification = this.notifications.get(id)
                if (notification?.hideTimer) {
                    clearTimeout(notification.hideTimer)
                    notification.pausedAt = Date.now()
                }
            })
            
            element.addEventListener('mouseleave', () => {
                const notification = this.notifications.get(id)
                if (notification?.pausedAt && !options.persistent) {
                    const remainingTime = notification.autoHideDelay - (notification.pausedAt - notification.createdAt)
                    this.setupAutoHide(notification, { autoHideDelay: Math.max(1000, remainingTime) })
                }
            })
        }
        
        return {
            id,
            element,
            type,
            message,
            options,
            createdAt: Date.now(),
            autoHideDelay: options.autoHideDelay || this.autoHideDelayValue || this.getDefaultDelay(type)
        }
    }

    /**
     * Render notification actions
     */
    renderActions(actions, notificationId) {
        const actionsHtml = actions.map(action => {
            const actionClass = action.type === 'primary' ? 'notification-action--primary' : 'notification-action--secondary'
            return `
                <button 
                    class="notification-action ${actionClass}" 
                    data-action="click->notification#handleAction" 
                    data-notification-id="${notificationId}"
                    data-action-id="${action.id}"
                >
                    ${this.escapeHtml(action.label)}
                </button>
            `
        }).join('')
        
        return `<div class="notification-actions">${actionsHtml}</div>`
    }

    /**
     * Handle action button clicks
     */
    handleAction(event) {
        const notificationId = event.target.dataset.notificationId
        const actionId = event.target.dataset.actionId
        const notification = this.notifications.get(notificationId)
        
        if (!notification) return
        
        const action = notification.options.actions?.find(a => a.id === actionId)
        if (!action) return
        
        // Execute action callback
        if (action.callback) {
            action.callback()
        }
        
        // Hide notification if action specifies
        if (action.hideOnClick !== false) {
            this.hide(notificationId)
        }
        
        // Track action
        this.trackNotificationAction(notification.type, actionId)
    }

    /**
     * Close notification (from close button)
     */
    closeNotification(event) {
        const notificationId = event.target.dataset.notificationId
        if (notificationId) {
            this.hide(notificationId)
        }
    }

    /**
     * Setup auto-hide functionality
     */
    setupAutoHide(notification, options) {
        if (options.persistent) return
        
        const delay = options.autoHideDelay || notification.autoHideDelay
        
        notification.hideTimer = setTimeout(() => {
            this.hide(notification.id)
        }, delay)
    }

    /**
     * Enforce maximum number of notifications
     */
    enforceMaxNotifications() {
        const maxNotifications = this.maxNotificationsValue || 5
        
        if (this.notifications.size >= maxNotifications) {
            // Remove oldest notification
            const [oldestId] = this.notifications.keys()
            this.hide(oldestId)
        }
    }

    /**
     * Get default icon for notification type
     */
    getDefaultIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        }
        return icons[type] || 'ℹ️'
    }

    /**
     * Get default title for notification type
     */
    getDefaultTitle(type) {
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        }
        return titles[type]
    }

    /**
     * Get default auto-hide delay for notification type
     */
    getDefaultDelay(type) {
        const delays = {
            success: 3000,
            error: 0, // Don't auto-hide errors
            warning: 5000,
            info: 4000
        }
        return delays[type] || 4000
    }

    /**
     * Generate unique ID for notification
     */
    generateId() {
        return `notification-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
    }

    /**
     * Utility functions
     */
    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    trackNotification(type, message) {
        if (window.analytics) {
            window.analytics.track('notification_shown', {
                type: type,
                message_length: message.length,
                timestamp: new Date().toISOString()
            })
        }
    }

    trackNotificationAction(type, actionId) {
        if (window.analytics) {
            window.analytics.track('notification_action', {
                type: type,
                action: actionId,
                timestamp: new Date().toISOString()
            })
        }
    }

    /**
     * Public API methods for convenience
     */
    
    // Batch notification for multiple messages
    showBatch(notifications) {
        return notifications.map(({ message, type, options }) => 
            this.show(message, type, options)
        )
    }

    // Show loading notification
    showLoading(message = 'Loading...', options = {}) {
        return this.show(message, 'info', {
            icon: '⏳',
            persistent: true,
            className: 'notification--loading',
            ...options
        })
    }

    // Update existing notification
    update(id, message, type, options = {}) {
        this.hide(id)
        return this.show(message, type, { ...options, id })
    }

    // Check if notification exists
    exists(id) {
        return this.notifications.has(id)
    }

    // Get notification count by type
    getCount(type = null) {
        if (!type) return this.notifications.size
        
        let count = 0
        for (const notification of this.notifications.values()) {
            if (notification.type === type) count++
        }
        return count
    }
}