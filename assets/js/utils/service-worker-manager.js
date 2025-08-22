/**
 * Service Worker Manager
 * Handles service worker registration, updates, and communication
 */

/**
 * Service Worker Manager Class
 * Manages service worker lifecycle and provides offline capabilities
 * 
 * @class ServiceWorkerManager
 * @example
 * const swManager = new ServiceWorkerManager();
 * swManager.register('/sw.js');
 * 
 * @since 1.0.0
 * @author PrettiOps Development Team
 */
class ServiceWorkerManager {
  constructor() {
    this.registration = null;
    this.isOnline = navigator.onLine;
    this.updateAvailable = false;
    this.offlineQueue = [];
    
    this.init();
  }

  /**
   * Initialize service worker manager
   * Sets up network monitoring and event listeners
   * 
   * @private
   * @memberof ServiceWorkerManager
   */
  init() {
    this.setupNetworkMonitoring();
    this.setupVisibilityChangeListener();
    
    if ('serviceWorker' in navigator) {
      console.log('📡 Service Worker Manager initialized');
    } else {
      console.warn('Service Worker not supported in this browser');
    }
  }

  /**
   * Register service worker
   * Registers the service worker and handles updates
   * 
   * @param {string} [swUrl='/sw.js'] - Service worker script URL
   * @param {Object} [options={}] - Registration options
   * @param {boolean} [options.updateOnReload=true] - Update SW on page reload
   * @param {boolean} [options.notifyOnUpdate=true] - Show update notification
   * @returns {Promise<ServiceWorkerRegistration|null>} Registration object
   * @memberof ServiceWorkerManager
   * 
   * @example
   * // Basic registration
   * swManager.register();
   * 
   * @example
   * // Custom configuration
   * swManager.register('/custom-sw.js', {
   *   updateOnReload: false,
   *   notifyOnUpdate: false
   * });
   */
  async register(swUrl = '/build/service-worker.js', options = {}) {
    if (!('serviceWorker' in navigator)) {
      console.warn('Service Worker not supported');
      return null;
    }

    const config = {
      updateOnReload: true,
      notifyOnUpdate: true,
      ...options,
    };

    try {
      this.registration = await navigator.serviceWorker.register(swUrl, {
        scope: '/',
      });

      console.log('Service Worker registered successfully:', this.registration.scope);

      // Handle different registration states
      if (this.registration.installing) {
        console.log('Service Worker installing...');
        this.trackInstallProgress(this.registration.installing);
      } else if (this.registration.waiting) {
        console.log('Service Worker waiting to activate');
        if (config.notifyOnUpdate) {
          this.showUpdateNotification();
        }
      } else if (this.registration.active) {
        console.log('Service Worker active');
      }

      // Listen for updates
      this.registration.addEventListener('updatefound', () => {
        console.log('Service Worker update found');
        this.handleServiceWorkerUpdate(config);
      });

      // Handle controller change
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        console.log('Service Worker controller changed');
        if (config.updateOnReload) {
          window.location.reload();
        }
      });

      // Setup message listener
      navigator.serviceWorker.addEventListener('message', this.handleMessage.bind(this));

      // Dispatch custom event
      document.dispatchEvent(new CustomEvent('sw:registered', {
        detail: { registration: this.registration },
      }));

      return this.registration;

    } catch (error) {
      console.error('Service Worker registration failed:', error);
      
      // Dispatch error event
      document.dispatchEvent(new CustomEvent('sw:error', {
        detail: { error, type: 'registration' },
      }));

      return null;
    }
  }

  /**
   * Handle service worker updates
   * Manages the update process and user notifications
   * 
   * @private
   * @param {Object} config - Configuration options
   * @memberof ServiceWorkerManager
   */
  handleServiceWorkerUpdate(config) {
    const installingWorker = this.registration.installing;
    
    if (!installingWorker) return;

    this.trackInstallProgress(installingWorker);

    installingWorker.addEventListener('statechange', () => {
      if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
        console.log('New Service Worker installed, waiting to activate');
        
        this.updateAvailable = true;
        
        if (config.notifyOnUpdate) {
          this.showUpdateNotification();
        }

        // Dispatch update event
        document.dispatchEvent(new CustomEvent('sw:updateavailable', {
          detail: { registration: this.registration },
        }));
      }
    });
  }

  /**
   * Track service worker install progress
   * Provides feedback during service worker installation
   * 
   * @private
   * @param {ServiceWorker} worker - Installing service worker
   * @memberof ServiceWorkerManager
   */
  trackInstallProgress(worker) {
    // Show loading state
    if (window.loadingStatesManager) {
      window.loadingStatesManager.showLoading('sw-install', {
        message: 'Installing updates...',
        showProgress: false,
      });
    }

    worker.addEventListener('statechange', () => {
      if (worker.state === 'installed') {
        if (window.loadingStatesManager) {
          window.loadingStatesManager.hideLoading('sw-install');
        }
      }
    });
  }

  /**
   * Show update notification to user
   * Displays a notification when app update is available
   * 
   * @private
   * @memberof ServiceWorkerManager
   */
  showUpdateNotification() {
    if (window.notificationManager) {
      window.notificationManager.show({
        type: 'info',
        title: 'Update Available',
        message: 'A new version of PrettiOps is available.',
        persistent: true,
        actions: [{
          label: 'Update Now',
          handler: () => this.applyUpdate(),
          primary: true,
        }, {
          label: 'Later',
          handler: () => {},
        }],
      });
    }
  }

  /**
   * Apply service worker update
   * Activates the waiting service worker
   * 
   * @returns {Promise} Promise that resolves when update is applied
   * @memberof ServiceWorkerManager
   */
  async applyUpdate() {
    if (!this.registration || !this.registration.waiting) {
      console.warn('No service worker update available');
      return;
    }

    try {
      // Show loading state
      if (window.loadingStatesManager) {
        window.loadingStatesManager.showLoading('sw-update', {
          message: 'Applying update...',
        });
      }

      // Tell the waiting service worker to skip waiting
      this.registration.waiting.postMessage({ type: 'SKIP_WAITING' });
      
      // Wait for controller change
      await new Promise((resolve) => {
        navigator.serviceWorker.addEventListener('controllerchange', resolve, { once: true });
      });

      console.log('Service Worker update applied');
      
      // Dispatch update event
      document.dispatchEvent(new CustomEvent('sw:updated', {
        detail: { registration: this.registration },
      }));

      if (window.loadingStatesManager) {
        window.loadingStatesManager.hideLoading('sw-update');
      }

      // Reload the page to use the new service worker
      window.location.reload();

    } catch (error) {
      console.error('Failed to apply service worker update:', error);
      
      if (window.loadingStatesManager) {
        window.loadingStatesManager.hideLoading('sw-update');
      }

      if (window.notificationManager) {
        window.notificationManager.error('Failed to apply update. Please refresh the page.');
      }
    }
  }

  /**
   * Setup network monitoring
   * Monitors online/offline state and queues offline actions
   * 
   * @private
   * @memberof ServiceWorkerManager
   */
  setupNetworkMonitoring() {
    window.addEventListener('online', () => {
      console.log('📶 Back online');
      this.isOnline = true;
      
      // Process offline queue
      this.processOfflineQueue();
      
      // Notify user
      if (window.notificationManager) {
        window.notificationManager.success('Connection restored');
      }

      // Dispatch event
      document.dispatchEvent(new CustomEvent('sw:online'));
    });

    window.addEventListener('offline', () => {
      console.log('📵 Gone offline');
      this.isOnline = false;
      
      // Notify user
      if (window.notificationManager) {
        window.notificationManager.warning('Working offline', {
          persistent: true,
        });
      }

      // Dispatch event
      document.dispatchEvent(new CustomEvent('sw:offline'));
    });
  }

  /**
   * Setup visibility change listener
   * Handles app visibility changes for background sync
   * 
   * @private
   * @memberof ServiceWorkerManager
   */
  setupVisibilityChangeListener() {
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && this.registration) {
        // Check for updates when app becomes visible
        this.checkForUpdates();
        
        // Sync offline actions
        this.syncOfflineActions();
      }
    });
  }

  /**
   * Check for service worker updates
   * Manually checks for service worker updates
   * 
   * @returns {Promise} Promise that resolves when check is complete
   * @memberof ServiceWorkerManager
   */
  async checkForUpdates() {
    if (!this.registration) return;

    try {
      await this.registration.update();
      console.log('Service Worker update check completed');
    } catch (error) {
      console.error('Service Worker update check failed:', error);
    }
  }

  /**
   * Send message to service worker
   * Communicates with the active service worker
   * 
   * @param {Object} message - Message to send
   * @returns {Promise} Promise that resolves with response
   * @memberof ServiceWorkerManager
   * 
   * @example
   * swManager.sendMessage({ type: 'CACHE_SNIPPET', snippet: snippetData });
   */
  async sendMessage(message) {
    if (!navigator.serviceWorker.controller) {
      console.warn('No active service worker to send message to');
      return;
    }

    return new Promise((resolve, reject) => {
      const messageChannel = new MessageChannel();
      
      messageChannel.port1.onmessage = (event) => {
        if (event.data.error) {
          reject(new Error(event.data.error));
        } else {
          resolve(event.data);
        }
      };

      navigator.serviceWorker.controller.postMessage(message, [messageChannel.port2]);
    });
  }

  /**
   * Handle messages from service worker
   * Processes messages received from the service worker
   * 
   * @private
   * @param {MessageEvent} event - Message event
   * @memberof ServiceWorkerManager
   */
  handleMessage(event) {
    const { data } = event;
    
    console.log('Message from Service Worker:', data);

    switch (data.type) {
      case 'CACHE_UPDATED':
        this.handleCacheUpdate(data);
        break;
        
      case 'BACKGROUND_SYNC_SUCCESS':
        this.handleBackgroundSyncSuccess(data);
        break;
        
      case 'BACKGROUND_SYNC_FAILED':
        this.handleBackgroundSyncFailed(data);
        break;
        
      default:
        console.log('Unknown message from Service Worker:', data);
    }
  }

  /**
   * Handle cache update notification
   * 
   * @private
   * @param {Object} data - Cache update data
   * @memberof ServiceWorkerManager
   */
  handleCacheUpdate(data) {
    document.dispatchEvent(new CustomEvent('sw:cache-updated', {
      detail: data,
    }));
  }

  /**
   * Handle successful background sync
   * 
   * @private
   * @param {Object} data - Sync success data
   * @memberof ServiceWorkerManager
   */
  handleBackgroundSyncSuccess(data) {
    if (window.notificationManager) {
      window.notificationManager.success(`${data.operation} completed`);
    }

    document.dispatchEvent(new CustomEvent('sw:sync-success', {
      detail: data,
    }));
  }

  /**
   * Handle failed background sync
   * 
   * @private
   * @param {Object} data - Sync failure data
   * @memberof ServiceWorkerManager
   */
  handleBackgroundSyncFailed(data) {
    if (window.notificationManager) {
      window.notificationManager.error(`${data.operation} failed`);
    }

    document.dispatchEvent(new CustomEvent('sw:sync-failed', {
      detail: data,
    }));
  }

  /**
   * Queue action for offline processing
   * Adds actions to queue when offline
   * 
   * @param {Object} action - Action to queue
   * @param {string} action.type - Action type
   * @param {Object} action.data - Action data
   * @memberof ServiceWorkerManager
   * 
   * @example
   * swManager.queueOfflineAction({
   *   type: 'SAVE_SNIPPET',
   *   data: { id: '123', content: '...' }
   * });
   */
  queueOfflineAction(action) {
    this.offlineQueue.push({
      ...action,
      timestamp: Date.now(),
      id: `offline-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
    });

    console.log('Action queued for offline processing:', action.type);

    // Store in localStorage for persistence
    try {
      localStorage.setItem('sw-offline-queue', JSON.stringify(this.offlineQueue));
    } catch (error) {
      console.error('Failed to persist offline queue:', error);
    }
  }

  /**
   * Process queued offline actions
   * Processes actions when back online
   * 
   * @private
   * @memberof ServiceWorkerManager
   */
  async processOfflineQueue() {
    if (this.offlineQueue.length === 0) return;

    console.log(`Processing ${this.offlineQueue.length} offline actions`);

    const queue = [...this.offlineQueue];
    this.offlineQueue = [];

    for (const action of queue) {
      try {
        await this.processOfflineAction(action);
        console.log('Processed offline action:', action.type);
      } catch (error) {
        console.error('Failed to process offline action:', error);
        // Re-queue failed actions
        this.offlineQueue.push(action);
      }
    }

    // Update localStorage
    try {
      localStorage.setItem('sw-offline-queue', JSON.stringify(this.offlineQueue));
    } catch (error) {
      console.error('Failed to update offline queue:', error);
    }
  }

  /**
   * Process individual offline action
   * 
   * @private
   * @param {Object} action - Action to process
   * @memberof ServiceWorkerManager
   */
  async processOfflineAction(action) {
    switch (action.type) {
      case 'SAVE_SNIPPET':
        await this.saveSnippet(action.data);
        break;
        
      case 'UPDATE_SNIPPET':
        await this.updateSnippet(action.data);
        break;
        
      case 'DELETE_SNIPPET':
        await this.deleteSnippet(action.data);
        break;
        
      default:
        console.warn('Unknown offline action type:', action.type);
    }
  }

  /**
   * Sync offline actions with service worker
   * Triggers background sync for queued actions
   * 
   * @private
   * @memberof ServiceWorkerManager
   */
  async syncOfflineActions() {
    if (!this.registration || this.offlineQueue.length === 0) return;

    try {
      // Trigger background sync
      await this.registration.sync.register('offline-actions');
      console.log('Background sync registered for offline actions');
    } catch (error) {
      console.error('Background sync registration failed:', error);
      // Fallback to immediate processing
      this.processOfflineQueue();
    }
  }

  /**
   * Cache snippet for offline access
   * Tells service worker to cache a specific snippet
   * 
   * @param {Object} snippet - Snippet data to cache
   * @memberof ServiceWorkerManager
   */
  async cacheSnippet(snippet) {
    if (!navigator.serviceWorker.controller) return;

    try {
      await this.sendMessage({
        type: 'CACHE_SNIPPET',
        snippet,
      });

      console.log('Snippet cached for offline access:', snippet.id);
    } catch (error) {
      console.error('Failed to cache snippet:', error);
    }
  }

  /**
   * Clear all caches
   * Clears all service worker caches
   * 
   * @memberof ServiceWorkerManager
   */
  async clearCaches() {
    if (!navigator.serviceWorker.controller) return;

    try {
      await this.sendMessage({ type: 'CLEAR_CACHE' });
      
      if (window.notificationManager) {
        window.notificationManager.success('Cache cleared successfully');
      }

      console.log('All caches cleared');
    } catch (error) {
      console.error('Failed to clear caches:', error);
      
      if (window.notificationManager) {
        window.notificationManager.error('Failed to clear cache');
      }
    }
  }

  /**
   * Get cache size information
   * Returns information about cache usage
   * 
   * @returns {Promise<Object>} Cache size information
   * @memberof ServiceWorkerManager
   */
  async getCacheInfo() {
    if ('storage' in navigator && 'estimate' in navigator.storage) {
      try {
        const estimate = await navigator.storage.estimate();
        return {
          used: estimate.usage,
          quota: estimate.quota,
          usedMB: Math.round(estimate.usage / 1024 / 1024 * 100) / 100,
          quotaMB: Math.round(estimate.quota / 1024 / 1024 * 100) / 100,
          percentUsed: Math.round(estimate.usage / estimate.quota * 100),
        };
      } catch (error) {
        console.error('Failed to get storage estimate:', error);
      }
    }

    return null;
  }

  /**
   * Unregister service worker
   * Completely removes the service worker
   * 
   * @returns {Promise<boolean>} Success status
   * @memberof ServiceWorkerManager
   */
  async unregister() {
    if (!this.registration) return false;

    try {
      const result = await this.registration.unregister();
      console.log('Service Worker unregistered:', result);
      return result;
    } catch (error) {
      console.error('Failed to unregister Service Worker:', error);
      return false;
    }
  }

  /**
   * Check if app is running in offline mode
   * 
   * @returns {boolean} True if offline
   * @memberof ServiceWorkerManager
   */
  isOffline() {
    return !this.isOnline;
  }

  /**
   * API method stubs for offline actions
   * These would typically make actual API calls
   */

  async saveSnippet(data) {
    const response = await fetch('/api/snippets', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    return response.json();
  }

  async updateSnippet(data) {
    const response = await fetch(`/api/snippets/${data.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    return response.json();
  }

  async deleteSnippet(data) {
    const response = await fetch(`/api/snippets/${data.id}`, {
      method: 'DELETE',
    });
    return response.json();
  }
}

// Create global service worker manager instance
window.serviceWorkerManager = new ServiceWorkerManager();

// Auto-register service worker if available
if ('serviceWorker' in navigator) {
  // Register after page load to avoid impacting initial page performance
  window.addEventListener('load', () => {
    window.serviceWorkerManager.register();
  });
}

// Export for ES6 modules
export default window.serviceWorkerManager;