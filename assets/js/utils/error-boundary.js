/**
 * Error Boundary and Error Management System
 * Provides comprehensive error handling, logging, and user feedback
 */

import notificationManager from './notifications.js';

/**
 * Custom Error Classes
 */

export class AppError extends Error {
  constructor(message, options = {}) {
    super(message);
    this.name = 'AppError';
    this.code = options.code || 'GENERIC_ERROR';
    this.context = options.context || {};
    this.userMessage = options.userMessage || message;
    this.recoverable = options.recoverable !== false;
    this.reportable = options.reportable !== false;
    this.timestamp = new Date();
    this.stack = this.stack || (new Error()).stack;
  }
}

export class NetworkError extends AppError {
  constructor(message, status = 0, options = {}) {
    super(message, {
      code: 'NETWORK_ERROR',
      userMessage: 'Network connection problem. Please check your connection and try again.',
      ...options,
    });
    this.name = 'NetworkError';
    this.status = status;
  }
}

export class ValidationError extends AppError {
  constructor(message, fields = {}, options = {}) {
    super(message, {
      code: 'VALIDATION_ERROR',
      userMessage: 'Please check your input and try again.',
      recoverable: true,
      reportable: false,
      ...options,
    });
    this.name = 'ValidationError';
    this.fields = fields;
  }
}

export class AuthenticationError extends AppError {
  constructor(message = 'Authentication required', options = {}) {
    super(message, {
      code: 'AUTH_ERROR',
      userMessage: 'Please log in to continue.',
      recoverable: true,
      reportable: false,
      ...options,
    });
    this.name = 'AuthenticationError';
  }
}

export class PermissionError extends AppError {
  constructor(message = 'Access denied', options = {}) {
    super(message, {
      code: 'PERMISSION_ERROR',
      userMessage: "You don't have permission to perform this action.",
      recoverable: false,
      reportable: false,
      ...options,
    });
    this.name = 'PermissionError';
  }
}

export class MonacoError extends AppError {
  constructor(message, options = {}) {
    super(message, {
      code: 'MONACO_ERROR',
      userMessage: 'Code editor encountered an issue. Falling back to basic editor.',
      recoverable: true,
      reportable: true,
      ...options,
    });
    this.name = 'MonacoError';
  }
}

/**
 * Error Boundary Class
 */
class ErrorBoundary {
  constructor() {
    this.errorQueue = new Map();
    this.reportingEndpoint = '/api/errors';
    this.maxRetries = 3;
    this.retryDelay = 1000;
    this.rateLimitWindow = 60000; // 1 minute
    this.maxErrorsPerWindow = 10;
    this.errorCounts = new Map();
    this.setupGlobalHandlers();
    this.setupPerformanceMonitoring();
  }

  /**
   * Setup global error handlers
   */
  setupGlobalHandlers() {
    // Handle unhandled JavaScript errors
    window.addEventListener('error', (event) => {
      const error = new AppError(event.message, {
        code: 'UNHANDLED_ERROR',
        context: {
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno,
          stack: event.error?.stack,
        },
        userMessage: 'An unexpected error occurred. We\'ve been notified and are working on a fix.',
        recoverable: true,
        reportable: true,
      });
      this.captureError(error);
    });

    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      let error;
      if (event.reason instanceof Error) {
        error = new AppError(event.reason.message, {
          code: 'UNHANDLED_PROMISE_REJECTION',
          context: { stack: event.reason.stack },
          userMessage: 'An unexpected error occurred. Please try again.',
          recoverable: true,
          reportable: true,
        });
      } else {
        error = new AppError(String(event.reason), {
          code: 'UNHANDLED_PROMISE_REJECTION',
          userMessage: 'An unexpected error occurred. Please try again.',
          recoverable: true,
          reportable: true,
        });
      }
      this.captureError(error);
      event.preventDefault();
    });

    // Handle network errors
    window.addEventListener('offline', () => {
      this.handleNetworkStatusChange(false);
    });

    window.addEventListener('online', () => {
      this.handleNetworkStatusChange(true);
    });
  }

  /**
   * Setup performance monitoring
   */
  setupPerformanceMonitoring() {
    // Monitor long tasks
    if ('PerformanceObserver' in window) {
      try {
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (entry.duration > 100) { // Report tasks longer than 100ms
              this.reportPerformanceIssue('long-task', {
                duration: entry.duration,
                startTime: entry.startTime,
                name: entry.name,
              });
            }
          }
        });
        observer.observe({ entryTypes: ['longtask'] });
      } catch (e) {
        // PerformanceObserver not supported or failed to initialize
        console.warn('Performance monitoring not available:', e.message);
      }
    }
  }

  /**
   * Main error capture method
   */
  captureError(error, context = {}) {
    try {
      // Ensure error is an AppError instance
      const appError = this.normalizeError(error);
      appError.context = { ...appError.context, ...context };

      // Check rate limiting
      if (this.isRateLimited(appError)) {
        return;
      }

      // Log to console in development
      if (process.env.NODE_ENV === 'development') {
        console.group(`🚨 ${appError.name}: ${appError.message}`);
        console.error('Error:', appError);
        console.error('Context:', appError.context);
        console.error('Stack:', appError.stack);
        console.groupEnd();
      }

      // Show user feedback
      this.showUserFeedback(appError);

      // Report error if reportable
      if (appError.reportable) {
        this.reportError(appError);
      }

      // Dispatch custom event for other parts of the app
      document.dispatchEvent(new CustomEvent('app:error', {
        detail: { error: appError, context },
      }));

    } catch (handlingError) {
      // Error in error handling - fallback to basic logging
      console.error('Error in error handling:', handlingError);
      console.error('Original error:', error);
    }
  }

  /**
   * Normalize different error types to AppError
   */
  normalizeError(error) {
    if (error instanceof AppError) {
      return error;
    }

    if (error instanceof TypeError && error.message.includes('fetch')) {
      return new NetworkError(error.message, 0, {
        context: { originalError: error.message, stack: error.stack },
      });
    }

    if (error instanceof Error) {
      return new AppError(error.message, {
        context: { 
          originalName: error.name,
          stack: error.stack,
        },
      });
    }

    if (typeof error === 'string') {
      return new AppError(error);
    }

    return new AppError('Unknown error occurred', {
      context: { originalError: error },
    });
  }

  /**
   * Check if error reporting should be rate limited
   */
  isRateLimited(error) {
    const now = Date.now();
    const windowStart = now - this.rateLimitWindow;
    const errorKey = `${error.name}:${error.code}`;

    // Clean old entries
    for (const [key, timestamps] of this.errorCounts.entries()) {
      this.errorCounts.set(key, timestamps.filter(t => t > windowStart));
      if (this.errorCounts.get(key).length === 0) {
        this.errorCounts.delete(key);
      }
    }

    // Check current error count
    const currentCount = this.errorCounts.get(errorKey) || [];
    if (currentCount.length >= this.maxErrorsPerWindow) {
      return true;
    }

    // Add current error
    currentCount.push(now);
    this.errorCounts.set(errorKey, currentCount);
    return false;
  }

  /**
   * Show user-friendly feedback
   */
  showUserFeedback(error) {
    const notificationType = this.getNotificationType(error);
    const actions = this.getErrorActions(error);

    notificationManager.show({
      type: notificationType,
      title: this.getErrorTitle(error),
      message: error.userMessage,
      persistent: !error.recoverable,
      actions: actions,
    });
  }

  /**
   * Get notification type based on error
   */
  getNotificationType(error) {
    if (error instanceof ValidationError) return 'warning';
    if (error instanceof NetworkError) return 'warning';
    if (error instanceof AuthenticationError) return 'info';
    if (error instanceof PermissionError) return 'warning';
    return 'error';
  }

  /**
   * Get error title for notification
   */
  getErrorTitle(error) {
    const titles = {
      NetworkError: 'Connection Problem',
      ValidationError: 'Input Error',
      AuthenticationError: 'Authentication Required',
      PermissionError: 'Access Denied',
      MonacoError: 'Editor Issue',
    };
    return titles[error.name] || 'Error';
  }

  /**
   * Get contextual actions for error recovery
   */
  getErrorActions(error) {
    const actions = [];

    if (error instanceof NetworkError) {
      actions.push({
        label: 'Retry',
        handler: () => window.location.reload(),
        primary: true,
      });
    }

    if (error instanceof AuthenticationError) {
      actions.push({
        label: 'Log In',
        handler: () => window.location.href = '/login',
        primary: true,
      });
    }

    if (error.recoverable && error.context?.retryHandler) {
      actions.push({
        label: 'Try Again',
        handler: error.context.retryHandler,
        primary: true,
      });
    }

    // Always provide feedback option for reportable errors
    if (error.reportable) {
      actions.push({
        label: 'Report Issue',
        handler: () => this.showFeedbackForm(error),
      });
    }

    return actions;
  }

  /**
   * Report error to server
   */
  async reportError(error) {
    try {
      const errorReport = {
        name: error.name,
        message: error.message,
        code: error.code,
        stack: error.stack,
        context: this.sanitizeContext(error.context),
        timestamp: error.timestamp,
        url: window.location.href,
        userAgent: navigator.userAgent,
        viewport: {
          width: window.innerWidth,
          height: window.innerHeight,
        },
        performance: this.getPerformanceData(),
      };

      const response = await fetch(this.reportingEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(errorReport),
      });

      if (!response.ok) {
        throw new Error(`Error reporting failed: ${response.status}`);
      }

    } catch (reportingError) {
      // Store in local queue for later retry
      this.queueErrorReport(error);
      console.warn('Failed to report error:', reportingError);
    }
  }

  /**
   * Sanitize context to remove sensitive data
   */
  sanitizeContext(context) {
    const sanitized = { ...context };
    const sensitiveKeys = ['password', 'token', 'key', 'secret', 'auth'];
    
    for (const [key, value] of Object.entries(sanitized)) {
      if (sensitiveKeys.some(sensitive => key.toLowerCase().includes(sensitive))) {
        sanitized[key] = '[REDACTED]';
      }
      if (typeof value === 'string' && value.length > 1000) {
        sanitized[key] = value.substring(0, 1000) + '...[TRUNCATED]';
      }
    }
    
    return sanitized;
  }

  /**
   * Get performance data for error context
   */
  getPerformanceData() {
    if (!('performance' in window)) return null;

    try {
      const perfData = {
        memory: performance.memory ? {
          usedJSHeapSize: performance.memory.usedJSHeapSize,
          totalJSHeapSize: performance.memory.totalJSHeapSize,
          jsHeapSizeLimit: performance.memory.jsHeapSizeLimit,
        } : null,
        navigation: performance.getEntriesByType('navigation')[0],
        timing: {
          loadComplete: Date.now() - performance.timing.navigationStart,
        },
      };

      return perfData;
    } catch (e) {
      return null;
    }
  }

  /**
   * Queue error report for later retry
   */
  queueErrorReport(error) {
    const errorId = `${error.name}_${Date.now()}`;
    this.errorQueue.set(errorId, {
      error,
      attempts: 0,
      nextRetry: Date.now() + this.retryDelay,
    });
    
    // Start retry process if not already running
    this.processErrorQueue();
  }

  /**
   * Process queued error reports
   */
  async processErrorQueue() {
    if (this.isProcessingQueue) return;
    this.isProcessingQueue = true;

    for (const [errorId, errorData] of this.errorQueue.entries()) {
      if (Date.now() >= errorData.nextRetry && errorData.attempts < this.maxRetries) {
        try {
          await this.reportError(errorData.error);
          this.errorQueue.delete(errorId);
        } catch (e) {
          errorData.attempts++;
          errorData.nextRetry = Date.now() + (this.retryDelay * errorData.attempts);
          
          if (errorData.attempts >= this.maxRetries) {
            this.errorQueue.delete(errorId);
            console.warn('Max retry attempts reached for error:', errorId);
          }
        }
      }
    }

    this.isProcessingQueue = false;
  }

  /**
   * Handle network status changes
   */
  handleNetworkStatusChange(isOnline) {
    if (isOnline) {
      notificationManager.success('Connection restored');
      this.processErrorQueue(); // Retry queued reports
    } else {
      notificationManager.warning('Connection lost. Working offline.', {
        persistent: true,
      });
    }
  }

  /**
   * Report performance issues
   */
  reportPerformanceIssue(type, data) {
    const perfError = new AppError(`Performance issue: ${type}`, {
      code: 'PERFORMANCE_ISSUE',
      context: { type, ...data },
      userMessage: 'The application may be running slowly.',
      recoverable: true,
      reportable: true,
    });

    this.captureError(perfError);
  }

  /**
   * Show feedback form for error reporting
   */
  showFeedbackForm(error) {
    // This would open a modal with a feedback form
    // For now, we'll just show a notification with instructions
    notificationManager.info(
      'To report this issue, please contact support with error code: ' + error.code,
      {
        persistent: true,
        actions: [{
          label: 'Copy Error Details',
          handler: () => this.copyErrorDetails(error),
          primary: true,
        }],
      }
    );
  }

  /**
   * Copy error details to clipboard
   */
  async copyErrorDetails(error) {
    const details = {
      code: error.code,
      message: error.message,
      timestamp: error.timestamp,
      url: window.location.href,
    };

    try {
      await navigator.clipboard.writeText(JSON.stringify(details, null, 2));
      notificationManager.success('Error details copied to clipboard');
    } catch (e) {
      console.error('Failed to copy error details:', e);
    }
  }

  /**
   * Create error wrapper for async functions
   */
  wrapAsync(fn, context = {}) {
    return async (...args) => {
      try {
        return await fn(...args);
      } catch (error) {
        this.captureError(error, { ...context, args });
        throw error;
      }
    };
  }

  /**
   * Create error wrapper for event handlers
   */
  wrapEventHandler(fn, context = {}) {
    return (event) => {
      try {
        return fn(event);
      } catch (error) {
        this.captureError(error, { 
          ...context, 
          eventType: event.type,
          target: event.target?.tagName,
        });
        // Don't re-throw for event handlers to prevent page errors
      }
    };
  }
}

// Create global error boundary instance
window.errorBoundary = new ErrorBoundary();

// Export for ES6 modules
export default window.errorBoundary;

// Utility function to create error-wrapped async functions
export const withErrorBoundary = (fn, context = {}) => {
  return window.errorBoundary.wrapAsync(fn, context);
};

// Utility function to create error-wrapped event handlers
export const withErrorHandler = (fn, context = {}) => {
  return window.errorBoundary.wrapEventHandler(fn, context);
};