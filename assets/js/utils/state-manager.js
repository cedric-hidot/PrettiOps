/**
 * State Management System for PrettiOps
 * Provides reactive state management with persistence and synchronization
 */

/**
 * State Manager Class
 * Manages application state with reactive updates and persistence
 * 
 * @class StateManager
 * @example
 * const state = new StateManager({
 *   user: { name: '', email: '' },
 *   theme: 'light'
 * });
 * 
 * state.subscribe('user', (user) => console.log('User updated:', user));
 * state.set('user.name', 'John Doe');
 * 
 * @since 1.0.0
 * @author PrettiOps Development Team
 */
class StateManager {
  constructor(initialState = {}) {
    this.state = this.deepClone(initialState);
    this.subscribers = new Map();
    this.middleware = [];
    this.history = [];
    this.maxHistorySize = 50;
    this.persistKey = 'prettiops-state';
    this.syncChannel = null;
    
    this.setupBroadcastChannel();
    this.loadPersistedState();
    this.init();
  }

  /**
   * Initialize the state manager
   * Sets up automatic persistence and cleanup
   * 
   * @private
   * @memberof StateManager
   */
  init() {
    // Auto-persist state changes
    this.subscribe('*', () => {
      this.persistState();
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
      this.persistState();
    });

    // Listen for storage changes (multi-tab sync)
    window.addEventListener('storage', (event) => {
      if (event.key === this.persistKey) {
        this.handleExternalStateChange(event.newValue);
      }
    });

    console.log('🔄 State Manager initialized');
  }

  /**
   * Setup BroadcastChannel for cross-tab synchronization
   * 
   * @private
   * @memberof StateManager
   */
  setupBroadcastChannel() {
    if ('BroadcastChannel' in window) {
      this.syncChannel = new BroadcastChannel('prettiops-state-sync');
      
      this.syncChannel.addEventListener('message', (event) => {
        this.handleCrossTabSync(event.data);
      });
    }
  }

  /**
   * Get state value by path
   * Supports dot notation for nested objects
   * 
   * @param {string} path - State path (e.g., 'user.profile.name')
   * @returns {any} State value
   * @memberof StateManager
   * 
   * @example
   * const userName = state.get('user.name');
   * const fullState = state.get(); // Returns entire state
   */
  get(path = null) {
    if (!path) {
      return this.deepClone(this.state);
    }

    return this.getByPath(this.state, path);
  }

  /**
   * Set state value by path
   * Supports dot notation and triggers subscribers
   * 
   * @param {string} path - State path
   * @param {any} value - New value
   * @param {Object} [options={}] - Set options
   * @param {boolean} [options.silent=false] - Skip notifications
   * @param {boolean} [options.merge=false] - Merge objects instead of replacing
   * @returns {boolean} Success status
   * @memberof StateManager
   * 
   * @example
   * state.set('user.name', 'John Doe');
   * state.set('user', { name: 'John', email: 'john@example.com' });
   * state.set('theme', 'dark', { silent: true });
   */
  set(path, value, options = {}) {
    const config = {
      silent: false,
      merge: false,
      source: 'local',
      ...options,
    };

    try {
      const oldState = this.deepClone(this.state);
      const oldValue = this.getByPath(this.state, path);

      // Apply middleware before setting
      const processedValue = this.applyMiddleware('beforeSet', {
        path,
        value,
        oldValue,
        oldState: this.state,
      });

      if (processedValue !== undefined) {
        value = processedValue;
      }

      // Set the new value
      if (config.merge && typeof value === 'object' && value !== null) {
        const currentValue = this.getByPath(this.state, path);
        if (typeof currentValue === 'object' && currentValue !== null) {
          value = { ...currentValue, ...value };
        }
      }

      this.setByPath(this.state, path, value);

      // Add to history
      this.addToHistory({
        type: 'set',
        path,
        value,
        oldValue,
        timestamp: Date.now(),
      });

      // Notify subscribers
      if (!config.silent) {
        this.notifySubscribers(path, value, oldValue);
      }

      // Apply middleware after setting
      this.applyMiddleware('afterSet', {
        path,
        value,
        oldValue,
        newState: this.state,
      });

      // Sync across tabs if change is from local source
      if (config.source === 'local' && this.syncChannel) {
        this.syncChannel.postMessage({
          type: 'state-change',
          path,
          value,
          timestamp: Date.now(),
        });
      }

      return true;

    } catch (error) {
      console.error('Failed to set state:', error);
      
      if (window.errorBoundary) {
        window.errorBoundary.captureError(error, {
          context: { path, value, options },
        });
      }

      return false;
    }
  }

  /**
   * Update state using a function
   * Provides the current value and expects a new value
   * 
   * @param {string} path - State path
   * @param {Function} updater - Update function (currentValue) => newValue
   * @param {Object} [options={}] - Update options
   * @memberof StateManager
   * 
   * @example
   * state.update('counter', (count) => count + 1);
   * state.update('user', (user) => ({ ...user, lastLogin: new Date() }));
   */
  update(path, updater, options = {}) {
    const currentValue = this.get(path);
    const newValue = updater(currentValue);
    return this.set(path, newValue, options);
  }

  /**
   * Delete a state property
   * 
   * @param {string} path - State path to delete
   * @param {Object} [options={}] - Delete options
   * @returns {boolean} Success status
   * @memberof StateManager
   */
  delete(path, options = {}) {
    try {
      const oldValue = this.getByPath(this.state, path);
      
      if (oldValue === undefined) {
        return true; // Already doesn't exist
      }

      this.deleteByPath(this.state, path);

      // Add to history
      this.addToHistory({
        type: 'delete',
        path,
        oldValue,
        timestamp: Date.now(),
      });

      // Notify subscribers
      if (!options.silent) {
        this.notifySubscribers(path, undefined, oldValue);
      }

      return true;

    } catch (error) {
      console.error('Failed to delete state:', error);
      return false;
    }
  }

  /**
   * Subscribe to state changes
   * 
   * @param {string} path - State path to watch ('*' for all changes)
   * @param {Function} callback - Callback function
   * @param {Object} [options={}] - Subscription options
   * @param {boolean} [options.immediate=false] - Call immediately with current value
   * @returns {Function} Unsubscribe function
   * @memberof StateManager
   * 
   * @example
   * const unsubscribe = state.subscribe('user.name', (name, oldName) => {
   *   console.log(`Name changed from ${oldName} to ${name}`);
   * });
   * 
   * // Later...
   * unsubscribe();
   */
  subscribe(path, callback, options = {}) {
    const config = {
      immediate: false,
      once: false,
      ...options,
    };

    const subscriptionId = `sub-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    if (!this.subscribers.has(path)) {
      this.subscribers.set(path, new Map());
    }

    const pathSubscribers = this.subscribers.get(path);
    pathSubscribers.set(subscriptionId, {
      callback,
      options: config,
    });

    // Call immediately if requested
    if (config.immediate) {
      const currentValue = path === '*' ? this.state : this.get(path);
      callback(currentValue, undefined);
    }

    // Return unsubscribe function
    return () => {
      const pathSubs = this.subscribers.get(path);
      if (pathSubs) {
        pathSubs.delete(subscriptionId);
        if (pathSubs.size === 0) {
          this.subscribers.delete(path);
        }
      }
    };
  }

  /**
   * Subscribe to state changes once
   * Automatically unsubscribes after first notification
   * 
   * @param {string} path - State path to watch
   * @param {Function} callback - Callback function
   * @returns {Function} Unsubscribe function
   * @memberof StateManager
   */
  once(path, callback) {
    return this.subscribe(path, callback, { once: true });
  }

  /**
   * Add middleware for state operations
   * 
   * @param {string} hook - Hook name ('beforeSet', 'afterSet')
   * @param {Function} middleware - Middleware function
   * @returns {Function} Remove middleware function
   * @memberof StateManager
   * 
   * @example
   * state.addMiddleware('beforeSet', ({ path, value }) => {
   *   if (path === 'user.email' && !value.includes('@')) {
   *     throw new Error('Invalid email');
   *   }
   *   return value;
   * });
   */
  addMiddleware(hook, middleware) {
    if (!this.middleware.find(m => m.hook === hook)) {
      this.middleware.push({ hook, middleware });
    }

    // Return removal function
    return () => {
      this.middleware = this.middleware.filter(m => 
        !(m.hook === hook && m.middleware === middleware)
      );
    };
  }

  /**
   * Reset state to initial values
   * 
   * @param {Object} [newInitialState] - New initial state
   * @memberof StateManager
   */
  reset(newInitialState = null) {
    const oldState = this.deepClone(this.state);
    
    if (newInitialState) {
      this.state = this.deepClone(newInitialState);
    } else {
      // Reset to empty object or restore from initial state
      this.state = {};
    }

    // Clear history
    this.history = [];

    // Notify all subscribers
    this.notifySubscribers('*', this.state, oldState);

    console.log('State reset');
  }

  /**
   * Get state change history
   * 
   * @param {number} [limit] - Number of entries to return
   * @returns {Array} History entries
   * @memberof StateManager
   */
  getHistory(limit = null) {
    if (limit) {
      return this.history.slice(-limit);
    }
    return [...this.history];
  }

  /**
   * Undo last state change
   * 
   * @returns {boolean} Success status
   * @memberof StateManager
   */
  undo() {
    if (this.history.length === 0) return false;

    const lastChange = this.history.pop();
    
    try {
      if (lastChange.type === 'set') {
        this.set(lastChange.path, lastChange.oldValue, { silent: true });
      } else if (lastChange.type === 'delete') {
        this.set(lastChange.path, lastChange.oldValue, { silent: true });
      }

      // Notify subscribers about the undo
      this.notifySubscribers('*', this.state);
      
      return true;

    } catch (error) {
      console.error('Failed to undo state change:', error);
      return false;
    }
  }

  /**
   * Persist state to localStorage
   * 
   * @private
   * @memberof StateManager
   */
  persistState() {
    try {
      const serializedState = JSON.stringify({
        state: this.state,
        timestamp: Date.now(),
        version: '1.0.0',
      });
      
      localStorage.setItem(this.persistKey, serializedState);
    } catch (error) {
      console.warn('Failed to persist state:', error);
    }
  }

  /**
   * Load persisted state from localStorage
   * 
   * @private
   * @memberof StateManager
   */
  loadPersistedState() {
    try {
      const serialized = localStorage.getItem(this.persistKey);
      
      if (serialized) {
        const persisted = JSON.parse(serialized);
        
        // Validate version compatibility
        if (persisted.version === '1.0.0' && persisted.state) {
          this.state = { ...this.state, ...persisted.state };
          console.log('Persisted state loaded');
        }
      }
    } catch (error) {
      console.warn('Failed to load persisted state:', error);
    }
  }

  /**
   * Handle external state changes (from other tabs)
   * 
   * @private
   * @param {string} newValue - New state value
   * @memberof StateManager
   */
  handleExternalStateChange(newValue) {
    if (!newValue) return;

    try {
      const parsed = JSON.parse(newValue);
      if (parsed.state) {
        const oldState = this.deepClone(this.state);
        this.state = parsed.state;
        
        // Notify subscribers
        this.notifySubscribers('*', this.state, oldState);
        
        console.log('External state change applied');
      }
    } catch (error) {
      console.warn('Failed to handle external state change:', error);
    }
  }

  /**
   * Handle cross-tab synchronization
   * 
   * @private
   * @param {Object} data - Sync data
   * @memberof StateManager
   */
  handleCrossTabSync(data) {
    if (data.type === 'state-change') {
      this.set(data.path, data.value, { 
        silent: false,
        source: 'external',
      });
    }
  }

  /**
   * Notify subscribers of state changes
   * 
   * @private
   * @param {string} changedPath - Path that changed
   * @param {any} newValue - New value
   * @param {any} [oldValue] - Old value
   * @memberof StateManager
   */
  notifySubscribers(changedPath, newValue, oldValue) {
    // Notify exact path subscribers
    this.notifyPathSubscribers(changedPath, newValue, oldValue);
    
    // Notify wildcard subscribers
    this.notifyPathSubscribers('*', this.state, undefined);
    
    // Notify parent path subscribers
    this.notifyParentSubscribers(changedPath, newValue, oldValue);
  }

  /**
   * Notify subscribers of a specific path
   * 
   * @private
   * @param {string} path - Subscription path
   * @param {any} newValue - New value
   * @param {any} oldValue - Old value
   * @memberof StateManager
   */
  notifyPathSubscribers(path, newValue, oldValue) {
    const pathSubscribers = this.subscribers.get(path);
    if (!pathSubscribers) return;

    const subscribersToRemove = [];

    for (const [id, subscription] of pathSubscribers) {
      try {
        subscription.callback(newValue, oldValue);
        
        // Remove one-time subscribers
        if (subscription.options.once) {
          subscribersToRemove.push(id);
        }
      } catch (error) {
        console.error('Subscriber callback error:', error);
        subscribersToRemove.push(id); // Remove failing subscribers
      }
    }

    // Clean up subscribers
    subscribersToRemove.forEach(id => pathSubscribers.delete(id));
  }

  /**
   * Notify parent path subscribers
   * 
   * @private
   * @param {string} changedPath - Path that changed
   * @param {any} newValue - New value
   * @param {any} oldValue - Old value
   * @memberof StateManager
   */
  notifyParentSubscribers(changedPath, newValue, oldValue) {
    const pathParts = changedPath.split('.');
    
    // Notify parent paths (e.g., if 'user.name' changes, notify 'user' subscribers)
    for (let i = pathParts.length - 1; i > 0; i--) {
      const parentPath = pathParts.slice(0, i).join('.');
      const parentValue = this.get(parentPath);
      this.notifyPathSubscribers(parentPath, parentValue, undefined);
    }
  }

  /**
   * Apply middleware to state operations
   * 
   * @private
   * @param {string} hook - Hook name
   * @param {Object} context - Operation context
   * @returns {any} Processed value
   * @memberof StateManager
   */
  applyMiddleware(hook, context) {
    let result = context.value;

    for (const middleware of this.middleware) {
      if (middleware.hook === hook) {
        try {
          const processed = middleware.middleware(context);
          if (processed !== undefined) {
            result = processed;
            context.value = processed; // Update context for next middleware
          }
        } catch (error) {
          console.error(`Middleware error in ${hook}:`, error);
          throw error; // Re-throw to prevent state change
        }
      }
    }

    return result;
  }

  /**
   * Add entry to history
   * 
   * @private
   * @param {Object} entry - History entry
   * @memberof StateManager
   */
  addToHistory(entry) {
    this.history.push(entry);
    
    // Limit history size
    if (this.history.length > this.maxHistorySize) {
      this.history.shift();
    }
  }

  /**
   * Utility functions
   */

  getByPath(obj, path) {
    return path.split('.').reduce((current, key) => 
      current && current[key] !== undefined ? current[key] : undefined, obj
    );
  }

  setByPath(obj, path, value) {
    const keys = path.split('.');
    const lastKey = keys.pop();
    
    const target = keys.reduce((current, key) => {
      if (!current[key] || typeof current[key] !== 'object') {
        current[key] = {};
      }
      return current[key];
    }, obj);
    
    target[lastKey] = value;
  }

  deleteByPath(obj, path) {
    const keys = path.split('.');
    const lastKey = keys.pop();
    
    const target = keys.reduce((current, key) => 
      current && current[key] ? current[key] : {}, obj
    );
    
    delete target[lastKey];
  }

  deepClone(obj) {
    if (obj === null || typeof obj !== 'object') return obj;
    if (obj instanceof Date) return new Date(obj.getTime());
    if (obj instanceof Array) return obj.map(item => this.deepClone(item));
    if (obj instanceof Object) {
      const cloned = {};
      for (const key in obj) {
        if (obj.hasOwnProperty(key)) {
          cloned[key] = this.deepClone(obj[key]);
        }
      }
      return cloned;
    }
  }

  /**
   * Dispose of the state manager
   * Cleans up all subscriptions and listeners
   * 
   * @memberof StateManager
   */
  dispose() {
    this.subscribers.clear();
    this.middleware = [];
    this.history = [];
    
    if (this.syncChannel) {
      this.syncChannel.close();
    }

    console.log('🔄 State Manager disposed');
  }
}

// Global state instance for the application
const globalState = new StateManager({
  // Application state structure
  app: {
    version: '1.0.0',
    theme: 'system',
    loading: false,
    online: navigator.onLine,
  },
  
  user: {
    id: null,
    name: '',
    email: '',
    preferences: {
      theme: 'system',
      fontSize: 'medium',
      autoSave: true,
    },
  },
  
  editor: {
    content: '',
    language: 'javascript',
    theme: 'prettiops-dark',
    fontSize: 14,
    wordWrap: true,
    minimap: true,
    autoSave: true,
    unsavedChanges: false,
  },
  
  snippets: {
    list: [],
    current: null,
    filters: {
      language: '',
      status: '',
      search: '',
      sort: 'recent',
    },
    loading: false,
  },
  
  notifications: {
    list: [],
    unreadCount: 0,
  },
});

// Set up global state reactivity
globalState.addMiddleware('beforeSet', ({ path, value }) => {
  // Auto-update app online status
  if (path === 'app.online') {
    document.dispatchEvent(new CustomEvent('app:online-status', {
      detail: { online: value },
    }));
  }
  
  // Validate user email
  if (path === 'user.email' && value && !value.includes('@')) {
    throw new Error('Invalid email address');
  }
  
  return value;
});

// Global window reference
window.state = globalState;

// Export for ES6 modules
export default globalState;

// Utility functions for common state operations
export const useLocalState = (initialState = {}) => {
  return new StateManager(initialState);
};

export const createComputed = (statePath, computeFn, dependencies = []) => {
  const unsubscribers = [];
  
  const updateComputed = () => {
    const currentValue = globalState.get(statePath);
    const newValue = computeFn(currentValue, globalState.get());
    globalState.set(statePath, newValue, { silent: true });
  };
  
  // Subscribe to dependencies
  dependencies.forEach(dep => {
    const unsubscribe = globalState.subscribe(dep, updateComputed);
    unsubscribers.push(unsubscribe);
  });
  
  // Initial computation
  updateComputed();
  
  // Return cleanup function
  return () => {
    unsubscribers.forEach(fn => fn());
  };
};

export const bindToElement = (element, statePath, property = 'textContent') => {
  return globalState.subscribe(statePath, (value) => {
    if (element && element[property] !== undefined) {
      element[property] = value;
    }
  }, { immediate: true });
};