/**
 * Performance Monitoring and Optimization System
 * Provides comprehensive performance tracking, bundle analysis, and optimization suggestions
 */

/**
 * Performance Monitor Class
 * Tracks various performance metrics and provides optimization insights
 * 
 * @class PerformanceMonitor
 * @example
 * const monitor = new PerformanceMonitor();
 * monitor.startTimer('api-call');
 * // ... perform operation
 * monitor.endTimer('api-call');
 * 
 * @since 1.0.0
 * @author PrettiOps Development Team
 */
class PerformanceMonitor {
  constructor() {
    this.timers = new Map();
    this.metrics = new Map();
    this.observers = new Map();
    this.isEnabled = process.env.NODE_ENV !== 'production';
    this.reportingEndpoint = '/api/performance';
    
    this.init();
  }

  /**
   * Initialize performance monitoring
   * Sets up observers and begins collecting baseline metrics
   * 
   * @memberof PerformanceMonitor
   * @since 1.0.0
   */
  init() {
    this.setupPerformanceObservers();
    this.trackInitialMetrics();
    this.setupIntersectionObserver();
    this.setupResourceObserver();
    this.trackCoreWebVitals();
    
    console.log('📊 Performance Monitor initialized');
  }

  /**
   * Setup Performance Observer for various entry types
   * Monitors paint, navigation, resource, and longtask entries
   * 
   * @private
   * @memberof PerformanceMonitor
   */
  setupPerformanceObservers() {
    if (!('PerformanceObserver' in window)) {
      console.warn('PerformanceObserver not supported');
      return;
    }

    // Monitor paint metrics
    try {
      const paintObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          this.recordMetric({
            name: entry.name,
            value: entry.startTime,
            unit: 'ms',
            type: 'paint',
            timestamp: Date.now(),
          });
        }
      });
      paintObserver.observe({ entryTypes: ['paint'] });
      this.observers.set('paint', paintObserver);
    } catch (e) {
      console.warn('Paint observer setup failed:', e.message);
    }

    // Monitor navigation metrics
    try {
      const navigationObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          this.processNavigationMetrics(entry);
        }
      });
      navigationObserver.observe({ entryTypes: ['navigation'] });
      this.observers.set('navigation', navigationObserver);
    } catch (e) {
      console.warn('Navigation observer setup failed:', e.message);
    }

    // Monitor long tasks
    try {
      const longtaskObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          this.recordMetric({
            name: 'long-task',
            value: entry.duration,
            unit: 'ms',
            type: 'performance',
            timestamp: Date.now(),
            context: {
              startTime: entry.startTime,
              attribution: entry.attribution?.map(attr => ({
                name: attr.name,
                entryType: attr.entryType,
              })),
            },
          });

          // Warn about long tasks in development
          if (this.isEnabled && entry.duration > 50) {
            console.warn(`Long task detected: ${entry.duration}ms`, entry);
          }
        }
      });
      longtaskObserver.observe({ entryTypes: ['longtask'] });
      this.observers.set('longtask', longtaskObserver);
    } catch (e) {
      console.warn('Long task observer setup failed:', e.message);
    }

    // Monitor resource loading
    try {
      const resourceObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          this.processResourceMetrics(entry);
        }
      });
      resourceObserver.observe({ entryTypes: ['resource'] });
      this.observers.set('resource', resourceObserver);
    } catch (e) {
      console.warn('Resource observer setup failed:', e.message);
    }
  }

  /**
   * Process navigation timing metrics
   * Extracts key timing information from navigation entries
   * 
   * @private
   * @param {PerformanceNavigationTiming} entry - Navigation timing entry
   * @memberof PerformanceMonitor
   */
  processNavigationMetrics(entry) {
    const metrics = {
      'dns-lookup': entry.domainLookupEnd - entry.domainLookupStart,
      'tcp-connect': entry.connectEnd - entry.connectStart,
      'tls-handshake': entry.secureConnectionStart > 0 ? entry.connectEnd - entry.secureConnectionStart : 0,
      'request': entry.responseStart - entry.requestStart,
      'response': entry.responseEnd - entry.responseStart,
      'dom-processing': entry.domComplete - entry.domLoading,
      'dom-content-loaded': entry.domContentLoadedEventEnd - entry.domContentLoadedEventStart,
      'load-event': entry.loadEventEnd - entry.loadEventStart,
      'total-time': entry.loadEventEnd - entry.fetchStart,
    };

    for (const [name, value] of Object.entries(metrics)) {
      if (value > 0) {
        this.recordMetric({
          name,
          value,
          unit: 'ms',
          type: 'navigation',
          timestamp: Date.now(),
        });
      }
    }
  }

  /**
   * Process resource loading metrics
   * Analyzes resource loading performance and identifies bottlenecks
   * 
   * @private
   * @param {PerformanceResourceTiming} entry - Resource timing entry
   * @memberof PerformanceMonitor
   */
  processResourceMetrics(entry) {
    const resourceType = this.getResourceType(entry.name);
    const size = entry.transferSize || entry.encodedBodySize || 0;
    const duration = entry.responseEnd - entry.startTime;

    this.recordMetric({
      name: `resource-${resourceType}`,
      value: duration,
      unit: 'ms',
      type: 'resource',
      timestamp: Date.now(),
      context: {
        url: entry.name,
        size,
        cached: entry.transferSize === 0 && entry.encodedBodySize > 0,
        initiatorType: entry.initiatorType,
      },
    });

    // Flag slow resources
    if (duration > 1000) {
      console.warn(`Slow resource loading detected: ${entry.name} (${duration}ms)`);
    }

    // Flag large resources
    if (size > 500000) { // 500KB
      console.warn(`Large resource detected: ${entry.name} (${(size / 1024).toFixed(1)}KB)`);
    }
  }

  /**
   * Get resource type from URL
   * Categorizes resources based on file extension and content type
   * 
   * @private
   * @param {string} url - Resource URL
   * @returns {string} Resource type category
   * @memberof PerformanceMonitor
   */
  getResourceType(url) {
    const extension = url.split('.').pop()?.toLowerCase();
    const typeMap = {
      js: 'script',
      css: 'stylesheet',
      png: 'image',
      jpg: 'image',
      jpeg: 'image',
      gif: 'image',
      svg: 'image',
      webp: 'image',
      woff: 'font',
      woff2: 'font',
      ttf: 'font',
      json: 'xhr',
    };
    
    return typeMap[extension] || 'other';
  }

  /**
   * Track Core Web Vitals metrics
   * Monitors LCP, FID, and CLS for performance optimization
   * 
   * @memberof PerformanceMonitor
   */
  trackCoreWebVitals() {
    // Largest Contentful Paint (LCP)
    try {
      const lcpObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        const lastEntry = entries[entries.length - 1];
        
        this.recordMetric({
          name: 'largest-contentful-paint',
          value: lastEntry.startTime,
          unit: 'ms',
          type: 'core-web-vital',
          timestamp: Date.now(),
          context: {
            element: lastEntry.element?.tagName,
            url: lastEntry.url,
            size: lastEntry.size,
          },
        });
      });
      lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
      this.observers.set('lcp', lcpObserver);
    } catch (e) {
      console.warn('LCP observer setup failed:', e.message);
    }

    // First Input Delay (FID)
    try {
      const fidObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          this.recordMetric({
            name: 'first-input-delay',
            value: entry.processingStart - entry.startTime,
            unit: 'ms',
            type: 'core-web-vital',
            timestamp: Date.now(),
            context: {
              eventType: entry.name,
            },
          });
        }
      });
      fidObserver.observe({ entryTypes: ['first-input'] });
      this.observers.set('fid', fidObserver);
    } catch (e) {
      console.warn('FID observer setup failed:', e.message);
    }

    // Cumulative Layout Shift (CLS)
    try {
      let clsValue = 0;
      const clsObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!entry.hadRecentInput) {
            clsValue += entry.value;
            
            this.recordMetric({
              name: 'cumulative-layout-shift',
              value: clsValue,
              unit: 'score',
              type: 'core-web-vital',
              timestamp: Date.now(),
              context: {
                sources: entry.sources?.map(source => ({
                  node: source.node?.tagName,
                  previousRect: source.previousRect,
                  currentRect: source.currentRect,
                })),
              },
            });
          }
        }
      });
      clsObserver.observe({ entryTypes: ['layout-shift'] });
      this.observers.set('cls', clsObserver);
    } catch (e) {
      console.warn('CLS observer setup failed:', e.message);
    }
  }

  /**
   * Track initial performance metrics
   * Captures baseline performance data on initialization
   * 
   * @private
   * @memberof PerformanceMonitor
   */
  trackInitialMetrics() {
    if (!('performance' in window)) return;

    // Memory usage
    if (performance.memory) {
      this.recordMetric({
        name: 'memory-used',
        value: performance.memory.usedJSHeapSize,
        unit: 'bytes',
        type: 'memory',
        timestamp: Date.now(),
      });

      this.recordMetric({
        name: 'memory-total',
        value: performance.memory.totalJSHeapSize,
        unit: 'bytes',
        type: 'memory',
        timestamp: Date.now(),
      });
    }

    // Connection information
    if ('connection' in navigator) {
      const connection = navigator.connection;
      this.recordMetric({
        name: 'connection-type',
        value: connection.effectiveType,
        unit: 'string',
        type: 'network',
        timestamp: Date.now(),
        context: {
          downlink: connection.downlink,
          rtt: connection.rtt,
          saveData: connection.saveData,
        },
      });
    }
  }

  /**
   * Setup Intersection Observer for viewport monitoring
   * Tracks element visibility for lazy loading optimization
   * 
   * @private
   * @memberof PerformanceMonitor
   */
  setupIntersectionObserver() {
    if (!('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          this.recordMetric({
            name: 'element-visible',
            value: Date.now(),
            unit: 'timestamp',
            type: 'viewport',
            context: {
              element: entry.target.tagName,
              id: entry.target.id,
              class: entry.target.className,
              intersectionRatio: entry.intersectionRatio,
            },
            timestamp: Date.now(),
          });
        }
      });
    }, {
      threshold: [0.1, 0.5, 1.0],
    });

    // Observe key elements
    document.querySelectorAll('[data-lazy], img, iframe').forEach(el => {
      observer.observe(el);
    });

    this.intersectionObserver = observer;
  }

  /**
   * Setup resource observer for runtime resource monitoring
   * Monitors resources loaded after initial page load
   * 
   * @private
   * @memberof PerformanceMonitor
   */
  setupResourceObserver() {
    if (!('PerformanceObserver' in window)) return;

    // Monitor runtime resource loading
    const runtimeResourceObserver = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        // Only track resources loaded after page initialization
        if (entry.startTime > 5000) { // 5 seconds after page start
          this.processResourceMetrics(entry);
        }
      }
    });

    try {
      runtimeResourceObserver.observe({ entryTypes: ['resource'] });
      this.observers.set('runtime-resource', runtimeResourceObserver);
    } catch (e) {
      console.warn('Runtime resource observer setup failed:', e.message);
    }
  }

  /**
   * Start a performance timer
   * Begins timing a specific operation or code block
   * 
   * @param {string} name - Timer identifier
   * @returns {Function} Function to end the timer
   * @memberof PerformanceMonitor
   * 
   * @example
   * const endTimer = monitor.startTimer('api-call');
   * // ... perform operation
   * endTimer();
   * 
   * @example
   * // Or use the returned function later
   * monitor.startTimer('database-query');
   * // ... 
   * monitor.endTimer('database-query');
   */
  startTimer(name) {
    const startTime = performance.now();
    this.timers.set(name, startTime);

    // Return a function to end this specific timer
    return () => this.endTimer(name);
  }

  /**
   * End a performance timer
   * Stops timing and records the duration metric
   * 
   * @param {string} name - Timer identifier
   * @returns {number|null} Duration in milliseconds or null if timer not found
   * @memberof PerformanceMonitor
   */
  endTimer(name) {
    const startTime = this.timers.get(name);
    if (!startTime) {
      console.warn(`Timer '${name}' not found`);
      return null;
    }

    const duration = performance.now() - startTime;
    this.timers.delete(name);

    this.recordMetric({
      name,
      value: duration,
      unit: 'ms',
      type: 'timer',
      timestamp: Date.now(),
    });

    return duration;
  }

  /**
   * Record a performance metric
   * Stores metric data and triggers reporting if necessary
   * 
   * @param {Object} metric - Metric data object
   * @param {string} metric.name - Metric name
   * @param {number|string} metric.value - Metric value
   * @param {string} metric.unit - Unit of measurement
   * @param {string} metric.type - Metric category
   * @param {number} metric.timestamp - Timestamp when metric was recorded
   * @param {Object} [metric.context] - Additional context data
   * @memberof PerformanceMonitor
   */
  recordMetric(metric) {
    const fullMetric = {
      ...metric,
      id: `${metric.name}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      url: window.location.href,
      userAgent: navigator.userAgent,
    };

    // Store in memory (with size limit)
    if (!this.metrics.has(metric.type)) {
      this.metrics.set(metric.type, []);
    }

    const typeMetrics = this.metrics.get(metric.type);
    typeMetrics.push(fullMetric);

    // Keep only last 100 metrics per type to prevent memory issues
    if (typeMetrics.length > 100) {
      typeMetrics.shift();
    }

    // Log in development
    if (this.isEnabled) {
      console.log(`📊 Performance: ${metric.name} = ${metric.value}${metric.unit}`, metric.context);
    }

    // Dispatch custom event for other parts of the app
    document.dispatchEvent(new CustomEvent('performance:metric', {
      detail: fullMetric,
    }));

    // Report critical metrics immediately
    if (this.isCriticalMetric(metric)) {
      this.reportMetric(fullMetric);
    }
  }

  /**
   * Check if metric is critical and should be reported immediately
   * Identifies performance issues that need immediate attention
   * 
   * @private
   * @param {Object} metric - Metric to evaluate
   * @returns {boolean} True if metric is critical
   * @memberof PerformanceMonitor
   */
  isCriticalMetric(metric) {
    const criticalThresholds = {
      'largest-contentful-paint': 4000, // 4 seconds
      'first-input-delay': 300, // 300ms
      'cumulative-layout-shift': 0.25, // CLS score
      'long-task': 100, // 100ms
    };

    return criticalThresholds[metric.name] && metric.value > criticalThresholds[metric.name];
  }

  /**
   * Get all recorded metrics
   * Returns comprehensive performance data for analysis
   * 
   * @param {string} [type] - Filter by metric type
   * @returns {Object} Metrics data organized by type
   * @memberof PerformanceMonitor
   */
  getMetrics(type = null) {
    if (type) {
      return this.metrics.get(type) || [];
    }

    const allMetrics = {};
    for (const [metricType, metrics] of this.metrics) {
      allMetrics[metricType] = metrics;
    }
    return allMetrics;
  }

  /**
   * Get performance summary
   * Provides aggregated performance insights and recommendations
   * 
   * @returns {Object} Performance summary with key metrics and recommendations
   * @memberof PerformanceMonitor
   */
  getSummary() {
    const summary = {
      coreWebVitals: {},
      loadingPerformance: {},
      resourceAnalysis: {},
      recommendations: [],
      timestamp: Date.now(),
    };

    // Core Web Vitals
    const cwvMetrics = this.metrics.get('core-web-vital') || [];
    cwvMetrics.forEach(metric => {
      summary.coreWebVitals[metric.name] = {
        value: metric.value,
        unit: metric.unit,
        grade: this.getPerformanceGrade(metric.name, metric.value),
      };
    });

    // Loading Performance
    const navigationMetrics = this.metrics.get('navigation') || [];
    const latestNavigation = navigationMetrics[navigationMetrics.length - 1];
    if (latestNavigation) {
      summary.loadingPerformance = {
        totalTime: latestNavigation.value,
        grade: this.getPerformanceGrade('total-time', latestNavigation.value),
      };
    }

    // Resource Analysis
    const resourceMetrics = this.metrics.get('resource') || [];
    const resourceSummary = this.analyzeResources(resourceMetrics);
    summary.resourceAnalysis = resourceSummary;

    // Generate recommendations
    summary.recommendations = this.generateRecommendations(summary);

    return summary;
  }

  /**
   * Get performance grade for a metric
   * Assigns letter grades based on performance thresholds
   * 
   * @private
   * @param {string} metricName - Name of the metric
   * @param {number} value - Metric value
   * @returns {string} Performance grade (A-F)
   * @memberof PerformanceMonitor
   */
  getPerformanceGrade(metricName, value) {
    const gradeThresholds = {
      'largest-contentful-paint': { A: 2500, B: 4000, C: 6000, D: 8000 },
      'first-input-delay': { A: 100, B: 300, C: 500, D: 1000 },
      'cumulative-layout-shift': { A: 0.1, B: 0.25, C: 0.5, D: 1.0 },
      'total-time': { A: 2000, B: 4000, C: 6000, D: 10000 },
    };

    const thresholds = gradeThresholds[metricName];
    if (!thresholds) return 'N/A';

    if (value <= thresholds.A) return 'A';
    if (value <= thresholds.B) return 'B';
    if (value <= thresholds.C) return 'C';
    if (value <= thresholds.D) return 'D';
    return 'F';
  }

  /**
   * Analyze resource loading patterns
   * Provides insights into resource loading performance
   * 
   * @private
   * @param {Array} resourceMetrics - Resource timing data
   * @returns {Object} Resource analysis summary
   * @memberof PerformanceMonitor
   */
  analyzeResources(resourceMetrics) {
    const analysis = {
      totalResources: resourceMetrics.length,
      slowResources: 0,
      largeResources: 0,
      cachedResources: 0,
      byType: {},
    };

    resourceMetrics.forEach(metric => {
      const context = metric.context || {};
      
      // Count slow resources
      if (metric.value > 1000) {
        analysis.slowResources++;
      }

      // Count large resources
      if (context.size > 500000) {
        analysis.largeResources++;
      }

      // Count cached resources
      if (context.cached) {
        analysis.cachedResources++;
      }

      // Group by type
      const resourceType = metric.name.replace('resource-', '');
      if (!analysis.byType[resourceType]) {
        analysis.byType[resourceType] = {
          count: 0,
          totalTime: 0,
          totalSize: 0,
        };
      }

      analysis.byType[resourceType].count++;
      analysis.byType[resourceType].totalTime += metric.value;
      analysis.byType[resourceType].totalSize += (context.size || 0);
    });

    return analysis;
  }

  /**
   * Generate performance recommendations
   * Provides actionable suggestions based on collected metrics
   * 
   * @private
   * @param {Object} summary - Performance summary data
   * @returns {Array} Array of recommendation objects
   * @memberof PerformanceMonitor
   */
  generateRecommendations(summary) {
    const recommendations = [];

    // Core Web Vitals recommendations
    const cwv = summary.coreWebVitals;
    
    if (cwv['largest-contentful-paint'] && cwv['largest-contentful-paint'].grade > 'B') {
      recommendations.push({
        type: 'critical',
        title: 'Improve Largest Contentful Paint',
        description: 'LCP is slower than recommended. Consider optimizing critical resource loading.',
        suggestions: [
          'Optimize and compress large images',
          'Implement preloading for critical resources',
          'Consider using a CDN',
          'Remove unused CSS and JavaScript',
        ],
      });
    }

    if (cwv['cumulative-layout-shift'] && cwv['cumulative-layout-shift'].grade > 'B') {
      recommendations.push({
        type: 'important',
        title: 'Reduce Layout Shifts',
        description: 'Page layout is shifting during load, affecting user experience.',
        suggestions: [
          'Set explicit dimensions for images and embeds',
          'Reserve space for dynamic content',
          'Avoid inserting content above existing content',
          'Use CSS transforms instead of changing layout properties',
        ],
      });
    }

    // Resource recommendations
    const resources = summary.resourceAnalysis;
    
    if (resources.slowResources > 3) {
      recommendations.push({
        type: 'important',
        title: 'Optimize Slow Resources',
        description: `${resources.slowResources} resources are loading slowly.`,
        suggestions: [
          'Enable compression (gzip/brotli)',
          'Optimize image formats and sizes',
          'Consider using resource hints (preload, prefetch)',
          'Implement lazy loading for non-critical resources',
        ],
      });
    }

    if (resources.largeResources > 2) {
      recommendations.push({
        type: 'moderate',
        title: 'Reduce Resource Sizes',
        description: `${resources.largeResources} large resources detected.`,
        suggestions: [
          'Compress and optimize images',
          'Minify CSS and JavaScript',
          'Remove unused code',
          'Consider code splitting',
        ],
      });
    }

    // Caching recommendations
    const cacheRatio = resources.totalResources > 0 ? 
      resources.cachedResources / resources.totalResources : 0;
    
    if (cacheRatio < 0.3) {
      recommendations.push({
        type: 'moderate',
        title: 'Improve Caching Strategy',
        description: 'Low cache hit ratio detected. Many resources are not being cached.',
        suggestions: [
          'Set appropriate cache headers',
          'Implement service worker caching',
          'Use versioning for static assets',
          'Consider using a CDN with edge caching',
        ],
      });
    }

    return recommendations;
  }

  /**
   * Report metrics to server
   * Sends performance data to backend for analysis and monitoring
   * 
   * @param {Object} metric - Metric to report
   * @returns {Promise} Promise resolving when report is sent
   * @memberof PerformanceMonitor
   */
  async reportMetric(metric) {
    if (!this.reportingEndpoint) return;

    try {
      await fetch(this.reportingEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          metrics: [metric],
          timestamp: Date.now(),
          sessionId: this.getSessionId(),
        }),
      });
    } catch (error) {
      if (this.isEnabled) {
        console.warn('Failed to report performance metric:', error);
      }
    }
  }

  /**
   * Get or create session ID for metric correlation
   * Creates a unique session identifier for tracking user sessions
   * 
   * @private
   * @returns {string} Session ID
   * @memberof PerformanceMonitor
   */
  getSessionId() {
    let sessionId = sessionStorage.getItem('performance-session-id');
    if (!sessionId) {
      sessionId = `session-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
      sessionStorage.setItem('performance-session-id', sessionId);
    }
    return sessionId;
  }

  /**
   * Get bundle size information
   * Analyzes loaded JavaScript and CSS bundle sizes
   * 
   * @returns {Promise<Object>} Bundle size information
   * @memberof PerformanceMonitor
   */
  async getBundleSize() {
    const resourceMetrics = this.metrics.get('resource') || [];
    const bundles = {
      javascript: { size: 0, count: 0 },
      stylesheet: { size: 0, count: 0 },
      total: { size: 0, count: 0 },
    };

    resourceMetrics.forEach(metric => {
      const context = metric.context || {};
      const size = context.size || 0;
      
      if (metric.name === 'resource-script') {
        bundles.javascript.size += size;
        bundles.javascript.count++;
      } else if (metric.name === 'resource-stylesheet') {
        bundles.stylesheet.size += size;
        bundles.stylesheet.count++;
      }
      
      bundles.total.size += size;
      bundles.total.count++;
    });

    return bundles;
  }

  /**
   * Monitor specific element performance
   * Tracks performance metrics for a specific DOM element
   * 
   * @param {HTMLElement} element - Element to monitor
   * @param {string} name - Metric name identifier
   * @memberof PerformanceMonitor
   */
  monitorElement(element, name) {
    if (!element) return;

    // Monitor when element becomes visible
    if (this.intersectionObserver) {
      this.intersectionObserver.observe(element);
    }

    // Monitor element interactions
    const interactionHandler = (event) => {
      this.recordMetric({
        name: `element-interaction-${name}`,
        value: Date.now(),
        unit: 'timestamp',
        type: 'interaction',
        context: {
          eventType: event.type,
          element: element.tagName,
          id: element.id,
          class: element.className,
        },
        timestamp: Date.now(),
      });
    };

    ['click', 'focus', 'hover'].forEach(eventType => {
      element.addEventListener(eventType, interactionHandler, { passive: true });
    });
  }

  /**
   * Cleanup observers and timers
   * Properly dispose of performance monitoring resources
   * 
   * @memberof PerformanceMonitor
   */
  dispose() {
    // Disconnect all observers
    for (const [name, observer] of this.observers) {
      try {
        observer.disconnect();
      } catch (e) {
        console.warn(`Failed to disconnect ${name} observer:`, e);
      }
    }
    this.observers.clear();

    // Disconnect intersection observer
    if (this.intersectionObserver) {
      this.intersectionObserver.disconnect();
    }

    // Clear timers
    this.timers.clear();
    this.metrics.clear();

    console.log('📊 Performance Monitor disposed');
  }
}

// Create global performance monitor instance
window.performanceMonitor = new PerformanceMonitor();

// Export for ES6 modules
export default window.performanceMonitor;

// Utility function to wrap functions with performance monitoring
export const withPerformanceMonitoring = (fn, name) => {
  return async (...args) => {
    const endTimer = window.performanceMonitor.startTimer(name);
    try {
      const result = await fn(...args);
      endTimer();
      return result;
    } catch (error) {
      endTimer();
      throw error;
    }
  };
};