/**
 * Notification System
 * Handles toast notifications, alerts, and user feedback
 */

class NotificationManager {
    constructor() {
        this.container = null;
        this.notifications = new Map();
        this.defaultDuration = 5000;
        this.init();
    }
    
    init() {
        this.createContainer();
        this.setupEventListeners();
    }
    
    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'notification-container';
        this.container.className = 'fixed top-4 right-4 z-50 flex flex-col gap-3 pointer-events-none';
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('role', 'region');
        this.container.setAttribute('aria-label', 'Notifications');
        document.body.appendChild(this.container);
    }
    
    setupEventListeners() {
        // Listen for custom notification events
        document.addEventListener('notification:show', (e) => {
            this.show(e.detail);
        });
        
        document.addEventListener('notification:hide', (e) => {
            this.hide(e.detail.id);
        });
        
        document.addEventListener('notification:clear', () => {
            this.clear();
        });
        
        // Listen for form validation events
        document.addEventListener('form:error', (e) => {
            this.error(e.detail.message);
        });
        
        document.addEventListener('form:success', (e) => {
            this.success(e.detail.message);
        });
    }
    
    show(options = {}) {
        const config = {
            type: 'info',
            title: null,
            message: '',
            duration: this.defaultDuration,
            persistent: false,
            actions: [],
            id: this.generateId(),
            ...options
        };
        
        const notification = this.createNotification(config);
        this.container.appendChild(notification);
        this.notifications.set(config.id, { element: notification, config });
        
        // Animate in
        requestAnimationFrame(() => {
            notification.classList.add('animate-slide-in-right');
        });
        
        // Auto-hide if not persistent
        if (!config.persistent && config.duration > 0) {
            setTimeout(() => {
                this.hide(config.id);
            }, config.duration);
        }
        
        // Announce to screen readers
        if (window.accessibilityManager) {
            const isUrgent = config.type === 'error';
            window.accessibilityManager.announce(
                `${config.type}: ${config.title || config.message}`,
                isUrgent
            );
        }
        
        return config.id;
    }
    
    createNotification(config) {
        const notification = document.createElement('div');
        notification.className = `
            notification max-w-sm w-full bg-white border border-gray-200 rounded-lg shadow-lg 
            pointer-events-auto transform translate-x-full transition-transform duration-300 ease-out
            ${this.getTypeClasses(config.type)}
        `;
        notification.setAttribute('role', 'alert');
        notification.setAttribute('data-notification-id', config.id);
        
        const icon = this.getIcon(config.type);
        const hasActions = config.actions && config.actions.length > 0;
        
        notification.innerHTML = `
            <div class="flex p-4">
                <div class="flex-shrink-0">
                    ${icon}
                </div>
                <div class="ml-3 flex-1">
                    ${config.title ? `<p class="text-sm font-medium text-gray-900">${config.title}</p>` : ''}
                    <p class="text-sm text-gray-500 ${config.title ? 'mt-1' : ''}">${config.message}</p>
                    ${hasActions ? this.createActions(config.actions, config.id) : ''}
                </div>
                <div class="ml-4 flex-shrink-0 flex">
                    <button 
                        class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        onclick="window.notificationManager.hide('${config.id}')"
                        aria-label="Close notification"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        return notification;
    }
    
    createActions(actions, notificationId) {
        const actionsHtml = actions.map(action => {
            const buttonClass = action.primary 
                ? 'btn btn-primary btn-sm' 
                : 'btn btn-ghost btn-sm';
            
            return `
                <button 
                    class="${buttonClass} mr-2" 
                    onclick="${action.handler}; window.notificationManager.hide('${notificationId}')"
                >
                    ${action.label}
                </button>
            `;
        }).join('');
        
        return `<div class="mt-3">${actionsHtml}</div>`;
    }
    
    getTypeClasses(type) {
        const classes = {
            success: 'border-green-200 bg-green-50',
            error: 'border-red-200 bg-red-50',
            warning: 'border-yellow-200 bg-yellow-50',
            info: 'border-blue-200 bg-blue-50'
        };
        
        return classes[type] || classes.info;
    }
    
    getIcon(type) {
        const icons = {
            success: `
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            `,
            error: `
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            `,
            warning: `
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            `,
            info: `
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            `
        };
        
        return icons[type] || icons.info;
    }
    
    hide(id) {
        const notification = this.notifications.get(id);
        if (!notification) return;
        
        const element = notification.element;
        element.classList.add('animate-slide-out-right');
        
        setTimeout(() => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
            this.notifications.delete(id);
        }, 300);
    }
    
    clear() {
        this.notifications.forEach((_, id) => {
            this.hide(id);
        });
    }
    
    generateId() {
        return `notification-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }
    
    // Convenience methods
    success(message, options = {}) {
        return this.show({
            type: 'success',
            message,
            ...options
        });
    }
    
    error(message, options = {}) {
        return this.show({
            type: 'error',
            message,
            persistent: true, // Errors should be persistent by default
            ...options
        });
    }
    
    warning(message, options = {}) {
        return this.show({
            type: 'warning',
            message,
            ...options
        });
    }
    
    info(message, options = {}) {
        return this.show({
            type: 'info',
            message,
            ...options
        });
    }
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .animate-slide-in-right {
        animation: slideInRight 0.3s ease-out forwards;
    }
    
    .animate-slide-out-right {
        animation: slideOutRight 0.3s ease-in forwards;
    }
`;
document.head.appendChild(style);

// Initialize notification manager
window.notificationManager = new NotificationManager();

export default window.notificationManager;