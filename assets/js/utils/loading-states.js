/**
 * Loading States and Skeleton UI System
 * Provides comprehensive loading state management with smooth transitions and skeleton UI
 */

/**
 * Loading States Manager
 * Handles loading indicators, skeleton UI, and loading state transitions
 * 
 * @class LoadingStatesManager
 * @example
 * const loadingManager = new LoadingStatesManager();
 * loadingManager.showLoading('api-call', { message: 'Loading data...' });
 * // ... perform async operation
 * loadingManager.hideLoading('api-call');
 * 
 * @since 1.0.0
 * @author PrettiOps Development Team
 */
class LoadingStatesManager {
  constructor() {
    this.activeLoadingStates = new Map();
    this.skeletonElements = new Map();
    this.loadingOverlays = new Map();
    this.defaultOptions = {
      showSpinner: true,
      showMessage: true,
      showProgress: false,
      overlayOpacity: 0.8,
      minDisplayTime: 200, // Minimum time to show loading to avoid flicker
      transitionDuration: 200,
      skeletonAnimation: true,
    };
    
    this.init();
  }

  /**
   * Initialize the loading states system
   * Sets up default CSS and creates necessary DOM elements
   * 
   * @private
   * @memberof LoadingStatesManager
   */
  init() {
    this.createLoadingCSS();
    this.setupGlobalLoadingContainer();
    this.observeNetworkRequests();
    
    console.log('⏳ Loading States Manager initialized');
  }

  /**
   * Create CSS styles for loading states and skeleton UI
   * Injects comprehensive styling for all loading components
   * 
   * @private
   * @memberof LoadingStatesManager
   */
  createLoadingCSS() {
    if (document.getElementById('loading-states-css')) return;

    const style = document.createElement('style');
    style.id = 'loading-states-css';
    style.textContent = `
      /* Loading Overlay Styles */
      .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(2px);
        transition: opacity 0.2s ease-in-out;
        opacity: 0;
        pointer-events: none;
      }

      .loading-overlay.show {
        opacity: 1;
        pointer-events: auto;
      }

      .dark .loading-overlay {
        background: rgba(15, 23, 42, 0.9);
        color: white;
      }

      /* Element Loading Overlay */
      .element-loading {
        position: relative;
        pointer-events: none;
        overflow: hidden;
      }

      .element-loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1;
        opacity: 0;
        pointer-events: none;
        animation: fadeIn 0.2s ease-in-out forwards;
      }

      .dark .element-loading::after {
        background: rgba(15, 23, 42, 0.8);
      }

      /* Spinner Animations */
      .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #f3f4f6;
        border-top: 3px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 16px;
      }

      .dark .loading-spinner {
        border-color: #374151;
        border-top-color: #60a5fa;
      }

      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      /* Loading Message */
      .loading-message {
        font-size: 14px;
        font-weight: 500;
        color: #6b7280;
        text-align: center;
        margin-bottom: 8px;
      }

      .dark .loading-message {
        color: #9ca3af;
      }

      /* Progress Bar */
      .loading-progress {
        width: 200px;
        height: 4px;
        background: #e5e7eb;
        border-radius: 2px;
        overflow: hidden;
        margin-top: 16px;
      }

      .loading-progress-bar {
        height: 100%;
        background: #3b82f6;
        border-radius: 2px;
        transition: width 0.3s ease;
        width: 0%;
      }

      .dark .loading-progress {
        background: #374151;
      }

      .dark .loading-progress-bar {
        background: #60a5fa;
      }

      /* Skeleton Styles */
      .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
        border-radius: 4px;
      }

      .skeleton.no-animation {
        animation: none;
        background: #f0f0f0;
      }

      .dark .skeleton {
        background: linear-gradient(90deg, #374151 25%, #4b5563 50%, #374151 75%);
        background-size: 200% 100%;
      }

      .dark .skeleton.no-animation {
        background: #374151;
      }

      @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
      }

      /* Skeleton Elements */
      .skeleton-text {
        height: 16px;
        margin-bottom: 8px;
      }

      .skeleton-text.large {
        height: 20px;
      }

      .skeleton-text.small {
        height: 12px;
      }

      .skeleton-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
      }

      .skeleton-button {
        height: 40px;
        width: 120px;
        border-radius: 6px;
      }

      .skeleton-card {
        height: 200px;
        border-radius: 8px;
        margin-bottom: 16px;
      }

      .skeleton-line {
        height: 1px;
        margin: 12px 0;
      }

      /* Button Loading States */
      .btn-loading {
        position: relative;
        pointer-events: none;
        color: transparent !important;
      }

      .btn-loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 16px;
        height: 16px;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
      }

      /* Fade Animations */
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }

      @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
      }

      /* Pulse Animation */
      .pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
      }

      @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
      }

      /* Shimmer Effect */
      .shimmer {
        position: relative;
        overflow: hidden;
      }

      .shimmer::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
          90deg,
          transparent,
          rgba(255, 255, 255, 0.6),
          transparent
        );
        animation: shimmer 2s infinite;
      }

      .dark .shimmer::before {
        background: linear-gradient(
          90deg,
          transparent,
          rgba(255, 255, 255, 0.1),
          transparent
        );
      }

      @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(200%); }
      }

      /* Responsive Design */
      @media (max-width: 640px) {
        .loading-spinner {
          width: 32px;
          height: 32px;
          border-width: 2px;
        }

        .loading-progress {
          width: 150px;
        }

        .loading-message {
          font-size: 12px;
        }
      }

      /* Reduced Motion Support */
      @media (prefers-reduced-motion: reduce) {
        .loading-spinner,
        .skeleton,
        .shimmer::before {
          animation: none;
        }
        
        .skeleton {
          background: #f0f0f0;
        }
        
        .dark .skeleton {
          background: #374151;
        }
      }
    `;
    
    document.head.appendChild(style);
  }

  /**
   * Setup global loading container
   * Creates a reusable container for global loading states
   * 
   * @private
   * @memberof LoadingStatesManager
   */
  setupGlobalLoadingContainer() {
    if (document.getElementById('global-loading-container')) return;

    const container = document.createElement('div');
    container.id = 'global-loading-container';
    container.className = 'loading-overlay';
    container.setAttribute('role', 'status');
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-label', 'Loading');

    document.body.appendChild(container);
  }

  /**
   * Observe network requests for automatic loading states
   * Automatically shows loading indicators for fetch requests
   * 
   * @private
   * @memberof LoadingStatesManager
   */
  observeNetworkRequests() {
    if (!window.fetch) return;

    const originalFetch = window.fetch;
    window.fetch = (...args) => {
      const url = typeof args[0] === 'string' ? args[0] : args[0]?.url || '';
      const isApiCall = url.includes('/api/');
      
      if (isApiCall) {
        const loadingId = `fetch-${Date.now()}`;
        this.showLoading(loadingId, {
          message: 'Loading...',
          showSpinner: true,
          showMessage: false,
        });

        return originalFetch(...args).finally(() => {
          setTimeout(() => {
            this.hideLoading(loadingId);
          }, this.defaultOptions.minDisplayTime);
        });
      }

      return originalFetch(...args);
    };
  }

  /**
   * Show loading state
   * Displays loading indicator with specified options
   * 
   * @param {string} id - Unique identifier for the loading state
   * @param {Object} [options={}] - Loading configuration options
   * @param {boolean} [options.showSpinner=true] - Show spinning indicator
   * @param {boolean} [options.showMessage=true] - Show loading message
   * @param {string} [options.message='Loading...'] - Custom loading message
   * @param {boolean} [options.showProgress=false] - Show progress bar
   * @param {number} [options.progress=0] - Initial progress value (0-100)
   * @param {HTMLElement} [options.target] - Target element for scoped loading
   * @param {boolean} [options.overlay=false] - Show as overlay
   * @param {string} [options.size='medium'] - Loading indicator size
   * @returns {Object} Loading state control object
   * @memberof LoadingStatesManager
   * 
   * @example
   * // Global loading
   * loadingManager.showLoading('api-call');
   * 
   * @example
   * // Element-specific loading
   * loadingManager.showLoading('form-submit', {
   *   target: document.getElementById('my-form'),
   *   message: 'Saving...'
   * });
   * 
   * @example
   * // Progress loading
   * const loading = loadingManager.showLoading('upload', {
   *   showProgress: true,
   *   message: 'Uploading file...'
   * });
   * loading.setProgress(50); // Update to 50%
   */
  showLoading(id, options = {}) {
    const config = { ...this.defaultOptions, ...options };
    const startTime = Date.now();

    // Remove existing loading state with same ID
    this.hideLoading(id);

    const loadingState = {
      id,
      config,
      startTime,
      element: null,
      progressBar: null,
      messageElement: null,
      setProgress: (value) => this.setProgress(id, value),
      setMessage: (message) => this.setMessage(id, message),
      hide: () => this.hideLoading(id),
    };

    if (config.target) {
      this.showElementLoading(loadingState);
    } else {
      this.showGlobalLoading(loadingState);
    }

    this.activeLoadingStates.set(id, loadingState);

    // Announce to screen readers
    if (window.accessibilityManager && config.showMessage) {
      window.accessibilityManager.announce(config.message || 'Loading', 'polite');
    }

    return loadingState;
  }

  /**
   * Show global loading overlay
   * Creates and displays a full-screen loading overlay
   * 
   * @private
   * @param {Object} loadingState - Loading state configuration
   * @memberof LoadingStatesManager
   */
  showGlobalLoading(loadingState) {
    const { config } = loadingState;
    const container = document.getElementById('global-loading-container');
    
    container.innerHTML = '';
    container.className = 'loading-overlay';

    if (config.showSpinner) {
      const spinner = document.createElement('div');
      spinner.className = 'loading-spinner';
      container.appendChild(spinner);
    }

    if (config.showMessage) {
      const message = document.createElement('div');
      message.className = 'loading-message';
      message.textContent = config.message || 'Loading...';
      container.appendChild(message);
      loadingState.messageElement = message;
    }

    if (config.showProgress) {
      const progressContainer = document.createElement('div');
      progressContainer.className = 'loading-progress';
      
      const progressBar = document.createElement('div');
      progressBar.className = 'loading-progress-bar';
      progressBar.style.width = `${config.progress || 0}%`;
      
      progressContainer.appendChild(progressBar);
      container.appendChild(progressContainer);
      loadingState.progressBar = progressBar;
    }

    loadingState.element = container;

    // Animate in
    requestAnimationFrame(() => {
      container.classList.add('show');
    });
  }

  /**
   * Show element-specific loading
   * Adds loading state to a specific DOM element
   * 
   * @private
   * @param {Object} loadingState - Loading state configuration
   * @memberof LoadingStatesManager
   */
  showElementLoading(loadingState) {
    const { config } = loadingState;
    const target = config.target;

    if (!target) return;

    // Add loading class
    target.classList.add('element-loading');
    
    // Create loading content
    const loadingContent = document.createElement('div');
    loadingContent.className = 'absolute inset-0 flex flex-col items-center justify-center bg-white bg-opacity-90 z-10';
    loadingContent.style.cssText = `
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      background: rgba(255, 255, 255, 0.9);
      z-index: 10;
      opacity: 0;
        pointer-events: none;
      transition: opacity ${config.transitionDuration}ms ease-in-out;
    `;

    if (config.showSpinner) {
      const spinner = document.createElement('div');
      spinner.className = 'loading-spinner';
      spinner.style.width = config.size === 'small' ? '24px' : config.size === 'large' ? '48px' : '32px';
      spinner.style.height = config.size === 'small' ? '24px' : config.size === 'large' ? '48px' : '32px';
      loadingContent.appendChild(spinner);
    }

    if (config.showMessage) {
      const message = document.createElement('div');
      message.className = 'loading-message';
      message.textContent = config.message || 'Loading...';
      message.style.fontSize = config.size === 'small' ? '12px' : config.size === 'large' ? '16px' : '14px';
      loadingContent.appendChild(message);
      loadingState.messageElement = message;
    }

    if (config.showProgress) {
      const progressContainer = document.createElement('div');
      progressContainer.className = 'loading-progress';
      progressContainer.style.width = config.size === 'small' ? '120px' : config.size === 'large' ? '240px' : '180px';
      
      const progressBar = document.createElement('div');
      progressBar.className = 'loading-progress-bar';
      progressBar.style.width = `${config.progress || 0}%`;
      
      progressContainer.appendChild(progressBar);
      loadingContent.appendChild(progressContainer);
      loadingState.progressBar = progressBar;
    }

    target.appendChild(loadingContent);
    loadingState.element = loadingContent;

    // Animate in
    requestAnimationFrame(() => {
      loadingContent.style.opacity = '1';
    });
  }

  /**
   * Hide loading state
   * Removes loading indicator and restores normal state
   * 
   * @param {string} id - Loading state identifier
   * @returns {Promise} Promise that resolves when loading is hidden
   * @memberof LoadingStatesManager
   */
  async hideLoading(id) {
    const loadingState = this.activeLoadingStates.get(id);
    if (!loadingState) return;

    const { config, startTime, element } = loadingState;
    const elapsedTime = Date.now() - startTime;
    
    // Ensure minimum display time to avoid flicker
    if (elapsedTime < config.minDisplayTime) {
      await new Promise(resolve => {
        setTimeout(resolve, config.minDisplayTime - elapsedTime);
      });
    }

    return new Promise((resolve) => {
      if (element) {
        // Animate out
        element.style.transition = `opacity ${config.transitionDuration}ms ease-in-out`;
        element.style.opacity = '0';

        setTimeout(() => {
          // Remove element or class
          if (element.id === 'global-loading-container') {
            element.classList.remove('show');
            element.innerHTML = '';
          } else if (config.target) {
            config.target.classList.remove('element-loading');
            if (element.parentNode) {
              element.parentNode.removeChild(element);
            }
          }
          
          this.activeLoadingStates.delete(id);
          resolve();
        }, config.transitionDuration);
      } else {
        this.activeLoadingStates.delete(id);
        resolve();
      }
    });
  }

  /**
   * Update progress for a loading state
   * Updates the progress bar value for loading states with progress enabled
   * 
   * @param {string} id - Loading state identifier
   * @param {number} value - Progress value (0-100)
   * @memberof LoadingStatesManager
   */
  setProgress(id, value) {
    const loadingState = this.activeLoadingStates.get(id);
    if (!loadingState || !loadingState.progressBar) return;

    const clampedValue = Math.max(0, Math.min(100, value));
    loadingState.progressBar.style.width = `${clampedValue}%`;
  }

  /**
   * Update message for a loading state
   * Changes the loading message text
   * 
   * @param {string} id - Loading state identifier
   * @param {string} message - New message text
   * @memberof LoadingStatesManager
   */
  setMessage(id, message) {
    const loadingState = this.activeLoadingStates.get(id);
    if (!loadingState || !loadingState.messageElement) return;

    loadingState.messageElement.textContent = message;

    // Announce change to screen readers
    if (window.accessibilityManager) {
      window.accessibilityManager.announce(message, 'polite');
    }
  }

  /**
   * Show skeleton UI for element
   * Replaces element content with skeleton placeholders
   * 
   * @param {HTMLElement|string} target - Target element or selector
   * @param {Object} [options={}] - Skeleton configuration
   * @param {string} [options.type='text'] - Skeleton type ('text', 'card', 'avatar', 'button')
   * @param {number} [options.lines=3] - Number of skeleton lines for text type
   * @param {boolean} [options.animation=true] - Enable skeleton animation
   * @param {string} [options.className] - Additional CSS classes
   * @returns {string} Skeleton identifier for removal
   * @memberof LoadingStatesManager
   * 
   * @example
   * // Show text skeleton
   * loadingManager.showSkeleton('#content', { type: 'text', lines: 5 });
   * 
   * @example
   * // Show card skeleton
   * loadingManager.showSkeleton('.card-container', { type: 'card' });
   */
  showSkeleton(target, options = {}) {
    const element = typeof target === 'string' ? document.querySelector(target) : target;
    if (!element) {
      console.warn('Skeleton target element not found');
      return null;
    }

    const config = {
      type: 'text',
      lines: 3,
      animation: true,
      className: '',
      ...options,
    };

    const skeletonId = `skeleton-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    // Store original content
    const originalContent = element.innerHTML;
    
    // Generate skeleton HTML
    const skeletonHTML = this.generateSkeletonHTML(config);
    
    // Replace content
    element.innerHTML = skeletonHTML;
    element.setAttribute('aria-busy', 'true');
    element.setAttribute('aria-live', 'polite');
    
    // Store skeleton info
    this.skeletonElements.set(skeletonId, {
      element,
      originalContent,
      config,
    });

    return skeletonId;
  }

  /**
   * Generate skeleton HTML based on configuration
   * Creates appropriate skeleton markup for different types
   * 
   * @private
   * @param {Object} config - Skeleton configuration
   * @returns {string} Generated HTML string
   * @memberof LoadingStatesManager
   */
  generateSkeletonHTML(config) {
    const animationClass = config.animation ? 'skeleton' : 'skeleton no-animation';
    const customClass = config.className ? ` ${config.className}` : '';
    const skeletonClass = `${animationClass}${customClass}`;

    switch (config.type) {
      case 'text':
        return Array.from({ length: config.lines }, (_, i) => {
          const width = i === config.lines - 1 ? '60%' : '100%';
          return `<div class="${skeletonClass} skeleton-text" style="width: ${width}"></div>`;
        }).join('');

      case 'card':
        return `
          <div class="${skeletonClass} skeleton-card"></div>
          <div class="${skeletonClass} skeleton-text" style="width: 80%"></div>
          <div class="${skeletonClass} skeleton-text" style="width: 60%"></div>
        `;

      case 'avatar':
        return `<div class="${skeletonClass} skeleton-avatar"></div>`;

      case 'button':
        return `<div class="${skeletonClass} skeleton-button"></div>`;

      case 'list':
        return Array.from({ length: config.lines || 5 }, () => `
          <div style="display: flex; align-items: center; margin-bottom: 16px;">
            <div class="${skeletonClass} skeleton-avatar" style="margin-right: 12px;"></div>
            <div style="flex: 1;">
              <div class="${skeletonClass} skeleton-text" style="width: 70%; margin-bottom: 8px;"></div>
              <div class="${skeletonClass} skeleton-text" style="width: 40%;"></div>
            </div>
          </div>
        `).join('');

      case 'table':
        const rows = config.rows || 5;
        const columns = config.columns || 4;
        return `
          <div style="width: 100%;">
            ${Array.from({ length: rows }, () => `
              <div style="display: flex; margin-bottom: 12px;">
                ${Array.from({ length: columns }, () => `
                  <div class="${skeletonClass} skeleton-text" style="flex: 1; margin-right: 16px;"></div>
                `).join('')}
              </div>
            `).join('')}
          </div>
        `;

      default:
        return `<div class="${skeletonClass} skeleton-text"></div>`;
    }
  }

  /**
   * Hide skeleton UI
   * Restores original content and removes skeleton
   * 
   * @param {string} skeletonId - Skeleton identifier returned from showSkeleton
   * @param {number} [delay=0] - Delay before hiding skeleton (ms)
   * @returns {Promise} Promise that resolves when skeleton is hidden
   * @memberof LoadingStatesManager
   */
  async hideSkeleton(skeletonId, delay = 0) {
    const skeletonInfo = this.skeletonElements.get(skeletonId);
    if (!skeletonInfo) return;

    if (delay > 0) {
      await new Promise(resolve => setTimeout(resolve, delay));
    }

    const { element, originalContent } = skeletonInfo;
    
    // Animate out if supported
    if (element.animate) {
      await element.animate([
        { opacity: 1 },
        { opacity: 0 }
      ], {
        duration: 200,
        easing: 'ease-out'
      }).finished;
    }

    // Restore original content
    element.innerHTML = originalContent;
    element.removeAttribute('aria-busy');
    element.removeAttribute('aria-live');

    // Animate in
    if (element.animate) {
      element.animate([
        { opacity: 0 },
        { opacity: 1 }
      ], {
        duration: 200,
        easing: 'ease-in'
      });
    }

    this.skeletonElements.delete(skeletonId);
  }

  /**
   * Show button loading state
   * Adds loading state to a button element
   * 
   * @param {HTMLButtonElement|string} target - Button element or selector
   * @param {Object} [options={}] - Loading options
   * @param {string} [options.loadingText] - Text to show while loading
   * @returns {Function} Function to hide loading state
   * @memberof LoadingStatesManager
   */
  showButtonLoading(target, options = {}) {
    const button = typeof target === 'string' ? document.querySelector(target) : target;
    if (!button) return () => {};

    const originalText = button.textContent;
    const originalDisabled = button.disabled;

    button.classList.add('btn-loading');
    button.disabled = true;

    if (options.loadingText) {
      button.textContent = options.loadingText;
    }

    return () => {
      button.classList.remove('btn-loading');
      button.disabled = originalDisabled;
      button.textContent = originalText;
    };
  }

  /**
   * Check if loading state is active
   * Returns whether a specific loading state is currently active
   * 
   * @param {string} id - Loading state identifier
   * @returns {boolean} True if loading state is active
   * @memberof LoadingStatesManager
   */
  isLoading(id) {
    return this.activeLoadingStates.has(id);
  }

  /**
   * Get all active loading states
   * Returns array of all currently active loading state IDs
   * 
   * @returns {string[]} Array of active loading state IDs
   * @memberof LoadingStatesManager
   */
  getActiveLoadingStates() {
    return Array.from(this.activeLoadingStates.keys());
  }

  /**
   * Hide all loading states
   * Removes all currently active loading states
   * 
   * @returns {Promise} Promise that resolves when all loading states are hidden
   * @memberof LoadingStatesManager
   */
  async hideAllLoading() {
    const promises = Array.from(this.activeLoadingStates.keys()).map(id => 
      this.hideLoading(id)
    );
    return Promise.all(promises);
  }

  /**
   * Dispose of all resources
   * Cleans up all loading states and removes DOM elements
   * 
   * @memberof LoadingStatesManager
   */
  dispose() {
    this.hideAllLoading();
    
    // Clean up skeleton elements
    for (const [id] of this.skeletonElements) {
      this.hideSkeleton(id);
    }

    // Remove global loading container
    const container = document.getElementById('global-loading-container');
    if (container) {
      container.remove();
    }

    // Remove CSS
    const style = document.getElementById('loading-states-css');
    if (style) {
      style.remove();
    }

    console.log('⏳ Loading States Manager disposed');
  }
}

// Create global loading states manager instance
window.loadingStatesManager = new LoadingStatesManager();

// Export for ES6 modules
export default window.loadingStatesManager;

// Utility functions for common loading patterns
export const withLoading = (asyncFn, id, options = {}) => {
  return async (...args) => {
    window.loadingStatesManager.showLoading(id, options);
    try {
      return await asyncFn(...args);
    } finally {
      window.loadingStatesManager.hideLoading(id);
    }
  };
};

export const withButtonLoading = (asyncFn, button, options = {}) => {
  return async (...args) => {
    const hideLoading = window.loadingStatesManager.showButtonLoading(button, options);
    try {
      return await asyncFn(...args);
    } finally {
      hideLoading();
    }
  };
};