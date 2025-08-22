/**
 * Comprehensive Accessibility Management System
 * Provides utilities for WCAG 2.1 AA compliance and screen reader support
 */

/**
 * Accessibility Manager Class
 */
class AccessibilityManager {
  constructor() {
    this.announcements = new Set();
    this.focusTraps = new Map();
    this.preferences = this.loadUserPreferences();
    this.init();
  }

  /**
   * Initialize accessibility features
   */
  init() {
    this.setupKeyboardNavigation();
    this.setupFocusManagement();
    this.setupReducedMotion();
    this.setupHighContrast();
    this.setupScreenReaderOptimizations();
    this.setupSkipLinks();
    
    // Listen for preference changes
    this.watchUserPreferences();
    
    console.log('🎯 Accessibility Manager initialized');
  }

  /**
   * Enhanced screen reader announcements
   */
  announce(message, priority = 'polite', options = {}) {
    const config = {
      delay: 100,
      deduplicate: true,
      persist: false,
      id: null,
      ...options,
    };

    // Prevent duplicate announcements
    if (config.deduplicate && this.announcements.has(message)) {
      return;
    }

    const announcer = document.createElement('div');
    const id = config.id || `announcement-${Date.now()}`;
    
    announcer.id = id;
    announcer.setAttribute('aria-live', priority);
    announcer.setAttribute('aria-atomic', 'true');
    announcer.className = 'sr-only';
    announcer.style.cssText = `
      position: absolute;
      left: -10000px;
      width: 1px;
      height: 1px;
      overflow: hidden;
    `;

    // Delay announcement to ensure screen reader picks it up
    setTimeout(() => {
      announcer.textContent = message;
      document.body.appendChild(announcer);
      
      this.announcements.add(message);
      
      // Clean up announcement
      if (!config.persist) {
        setTimeout(() => {
          if (document.body.contains(announcer)) {
            document.body.removeChild(announcer);
          }
          this.announcements.delete(message);
        }, 2000);
      }
    }, config.delay);

    return id;
  }

  /**
   * Enhanced focus management and trapping
   */
  trapFocus(container, options = {}) {
    const config = {
      initialFocus: null,
      returnFocus: true,
      allowTabToAll: false,
      skipLinks: true,
      ...options,
    };

    const focusableSelector = config.allowTabToAll 
      ? '*[tabindex]:not([tabindex="-1"]), a[href], button, textarea, input, select, [contenteditable="true"]'
      : `
        a[href]:not([tabindex="-1"]),
        button:not([disabled]):not([tabindex="-1"]),
        textarea:not([disabled]):not([tabindex="-1"]),
        input:not([disabled]):not([type="hidden"]):not([tabindex="-1"]),
        select:not([disabled]):not([tabindex="-1"]),
        [tabindex]:not([tabindex="-1"]),
        [contenteditable="true"]:not([tabindex="-1"])
      `.replace(/\s+/g, ' ').trim();

    const focusableElements = Array.from(container.querySelectorAll(focusableSelector))
      .filter(el => this.isVisible(el) && !el.hasAttribute('inert'));

    if (focusableElements.length === 0) {
      console.warn('No focusable elements found in focus trap container');
      return () => {}; // Return empty cleanup function
    }

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    const previousActiveElement = document.activeElement;

    // Set initial focus
    const initialTarget = config.initialFocus 
      ? container.querySelector(config.initialFocus) || firstElement
      : firstElement;
    
    setTimeout(() => initialTarget.focus(), 0);

    const handleKeyDown = (event) => {
      if (event.key !== 'Tab') return;

      const currentIndex = focusableElements.indexOf(event.target);
      
      if (event.shiftKey) {
        // Shift + Tab
        if (event.target === firstElement || currentIndex === -1) {
          event.preventDefault();
          lastElement.focus();
        }
      } else {
        // Tab
        if (event.target === lastElement || currentIndex === -1) {
          event.preventDefault();
          firstElement.focus();
        }
      }
    };

    container.addEventListener('keydown', handleKeyDown);

    // Store trap for potential updates
    const trapId = `trap-${Date.now()}`;
    this.focusTraps.set(trapId, {
      container,
      handleKeyDown,
      previousActiveElement,
      returnFocus: config.returnFocus,
    });

    // Return cleanup function
    return () => {
      container.removeEventListener('keydown', handleKeyDown);
      
      if (config.returnFocus && previousActiveElement && 
          typeof previousActiveElement.focus === 'function') {
        previousActiveElement.focus();
      }
      
      this.focusTraps.delete(trapId);
    };
  }

  /**
   * Focus element with enhanced options
   */
  focusElement(target, options = {}) {
    const config = {
      preventScroll: false,
      announce: false,
      restoreOnBlur: false,
      ...options,
    };

    let element;
    if (typeof target === 'string') {
      element = document.querySelector(target);
    } else if (target instanceof Element) {
      element = target;
    } else {
      console.warn('Invalid focus target:', target);
      return false;
    }

    if (!element || !this.isVisible(element)) {
      console.warn('Element not found or not visible for focusing:', target);
      return false;
    }

    // Make element focusable if needed
    if (!element.hasAttribute('tabindex') && !this.isNativelyFocusable(element)) {
      element.setAttribute('tabindex', '-1');
    }

    // Focus with options
    if (typeof element.focus === 'function') {
      element.focus({ preventScroll: config.preventScroll });
      
      if (config.announce) {
        const label = this.getAccessibleLabel(element);
        if (label) {
          this.announce(`Focused on ${label}`);
        }
      }

      if (config.restoreOnBlur) {
        const previousElement = document.activeElement;
        element.addEventListener('blur', () => {
          if (previousElement && typeof previousElement.focus === 'function') {
            previousElement.focus();
          }
        }, { once: true });
      }

      return true;
    }

    return false;
  }

  /**
   * Enhanced keyboard navigation
   */
  setupKeyboardNavigation() {
    document.addEventListener('keydown', (event) => {
      // Skip link activation
      if (event.key === 'Enter' && event.target.classList.contains('skip-link')) {
        event.preventDefault();
        const target = document.querySelector(event.target.getAttribute('href'));
        if (target) {
          this.focusElement(target, { announce: true });
        }
      }

      // Escape key handling for modals and overlays
      if (event.key === 'Escape') {
        this.handleEscapeKey(event);
      }

      // Arrow key navigation for custom widgets
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
        this.handleArrowNavigation(event);
      }
    });
  }

  /**
   * Handle Escape key for dismissible elements
   */
  handleEscapeKey(event) {
    // Find the closest dismissible element
    const dismissible = event.target.closest('[data-dismissible], .modal, .dropdown, .popover');
    if (dismissible) {
      const closeButton = dismissible.querySelector('[data-action*="close"], .close, .dismiss');
      if (closeButton) {
        closeButton.click();
      } else {
        // Dispatch custom close event
        dismissible.dispatchEvent(new CustomEvent('dismiss', { bubbles: true }));
      }
    }
  }

  /**
   * Handle arrow key navigation for custom widgets
   */
  handleArrowNavigation(event) {
    const widget = event.target.closest('[role="menu"], [role="listbox"], [role="tablist"], [role="radiogroup"]');
    if (!widget) return;

    const items = Array.from(widget.querySelectorAll('[role="menuitem"], [role="option"], [role="tab"], [role="radio"]'))
      .filter(item => this.isVisible(item) && !item.hasAttribute('disabled'));

    if (items.length === 0) return;

    const currentIndex = items.indexOf(event.target);
    if (currentIndex === -1) return;

    let nextIndex;
    const isVertical = widget.getAttribute('aria-orientation') !== 'horizontal';

    switch (event.key) {
      case 'ArrowUp':
        if (isVertical) {
          event.preventDefault();
          nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        }
        break;
      case 'ArrowDown':
        if (isVertical) {
          event.preventDefault();
          nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        }
        break;
      case 'ArrowLeft':
        if (!isVertical) {
          event.preventDefault();
          nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        }
        break;
      case 'ArrowRight':
        if (!isVertical) {
          event.preventDefault();
          nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        }
        break;
      case 'Home':
        event.preventDefault();
        nextIndex = 0;
        break;
      case 'End':
        event.preventDefault();
        nextIndex = items.length - 1;
        break;
    }

    if (nextIndex !== undefined) {
      items[nextIndex].focus();
      
      // Update aria-selected for appropriate widgets
      if (widget.getAttribute('role') === 'tablist') {
        items.forEach((item, index) => {
          item.setAttribute('aria-selected', index === nextIndex);
        });
      }
    }
  }

  /**
   * Setup focus management
   */
  setupFocusManagement() {
    // Add focus indicators
    document.addEventListener('focusin', (event) => {
      event.target.setAttribute('data-focused', 'true');
    });

    document.addEventListener('focusout', (event) => {
      event.target.removeAttribute('data-focused');
    });

    // Manage focus for dynamic content
    this.observeFocusableChanges();
  }

  /**
   * Observe changes to focusable elements
   */
  observeFocusableChanges() {
    if (!('MutationObserver' in window)) return;

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            this.setupNewFocusableElement(node);
          }
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  /**
   * Setup newly added focusable elements
   */
  setupNewFocusableElement(element) {
    // Add proper ARIA attributes to new interactive elements
    const interactiveElements = element.querySelectorAll('button, a, input, select, textarea, [tabindex]');
    
    interactiveElements.forEach((el) => {
      // Ensure proper labeling
      if (!this.getAccessibleLabel(el)) {
        console.warn('Interactive element without accessible label:', el);
      }

      // Add role if missing for custom elements
      if (!el.hasAttribute('role') && el.tagName.includes('-')) {
        this.inferRole(el);
      }
    });
  }

  /**
   * Infer appropriate ARIA role for custom elements
   */
  inferRole(element) {
    const tagName = element.tagName.toLowerCase();
    
    if (tagName.includes('button') || element.hasAttribute('onclick')) {
      element.setAttribute('role', 'button');
    } else if (tagName.includes('link')) {
      element.setAttribute('role', 'link');
    } else if (tagName.includes('input') || tagName.includes('field')) {
      element.setAttribute('role', 'textbox');
    }
  }

  /**
   * Setup reduced motion preferences
   */
  setupReducedMotion() {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    
    const updateMotionPreference = (query) => {
      document.documentElement.classList.toggle('reduce-motion', query.matches);
      
      if (query.matches) {
        // Disable auto-playing content
        document.querySelectorAll('video[autoplay], audio[autoplay]').forEach(media => {
          media.pause();
        });
        
        // Reduce animation duration
        document.documentElement.style.setProperty('--animation-duration', '0.01s');
      } else {
        document.documentElement.style.removeProperty('--animation-duration');
      }
    };

    updateMotionPreference(mediaQuery);
    mediaQuery.addEventListener('change', updateMotionPreference);
  }

  /**
   * Setup high contrast preferences
   */
  setupHighContrast() {
    const mediaQuery = window.matchMedia('(prefers-contrast: high)');
    
    const updateContrastPreference = (query) => {
      document.documentElement.classList.toggle('high-contrast', query.matches);
    };

    updateContrastPreference(mediaQuery);
    mediaQuery.addEventListener('change', updateContrastPreference);
  }

  /**
   * Setup screen reader optimizations
   */
  setupScreenReaderOptimizations() {
    // Add screen reader only text for icon-only buttons
    document.querySelectorAll('button:empty, a:empty').forEach(element => {
      if (!element.getAttribute('aria-label') && !element.getAttribute('title')) {
        console.warn('Empty interactive element without accessible label:', element);
      }
    });

    // Improve form field associations
    document.querySelectorAll('input, select, textarea').forEach(field => {
      if (!field.getAttribute('aria-label') && !field.getAttribute('aria-labelledby')) {
        const label = document.querySelector(`label[for="${field.id}"]`);
        if (!label && field.id) {
          console.warn('Form field without associated label:', field);
        }
      }
    });
  }

  /**
   * Setup skip links
   */
  setupSkipLinks() {
    // Create skip navigation if not present
    if (!document.querySelector('.skip-links')) {
      this.createSkipNavigation();
    }

    // Handle skip link focus
    document.querySelectorAll('.skip-link').forEach(link => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
          this.focusElement(target, { announce: true });
        }
      });
    });
  }

  /**
   * Create skip navigation links
   */
  createSkipNavigation() {
    const skipNav = document.createElement('nav');
    skipNav.className = 'skip-links';
    skipNav.setAttribute('aria-label', 'Skip navigation');
    
    const mainContent = document.querySelector('main, #main, .main-content');
    const navigation = document.querySelector('nav, .navigation, .nav');
    
    let skipLinksHtml = '';
    
    if (mainContent) {
      if (!mainContent.id) mainContent.id = 'main-content';
      skipLinksHtml += `<a href="#${mainContent.id}" class="skip-link">Skip to main content</a>`;
    }
    
    if (navigation) {
      if (!navigation.id) navigation.id = 'navigation';
      skipLinksHtml += `<a href="#${navigation.id}" class="skip-link">Skip to navigation</a>`;
    }
    
    if (skipLinksHtml) {
      skipNav.innerHTML = skipLinksHtml;
      document.body.insertBefore(skipNav, document.body.firstChild);
    }
  }

  /**
   * Load user accessibility preferences
   */
  loadUserPreferences() {
    try {
      const stored = localStorage.getItem('accessibility-preferences');
      return stored ? JSON.parse(stored) : {
        fontSize: 'medium',
        highContrast: false,
        reducedMotion: false,
        screenReader: false,
      };
    } catch (e) {
      return {
        fontSize: 'medium',
        highContrast: false,
        reducedMotion: false,
        screenReader: false,
      };
    }
  }

  /**
   * Save user accessibility preferences
   */
  saveUserPreferences(preferences) {
    try {
      this.preferences = { ...this.preferences, ...preferences };
      localStorage.setItem('accessibility-preferences', JSON.stringify(this.preferences));
      this.applyUserPreferences();
    } catch (e) {
      console.warn('Failed to save accessibility preferences:', e);
    }
  }

  /**
   * Apply user accessibility preferences
   */
  applyUserPreferences() {
    const html = document.documentElement;
    
    // Font size
    html.setAttribute('data-font-size', this.preferences.fontSize);
    
    // High contrast
    html.classList.toggle('user-high-contrast', this.preferences.highContrast);
    
    // Reduced motion
    html.classList.toggle('user-reduced-motion', this.preferences.reducedMotion);
    
    // Screen reader mode
    html.classList.toggle('screen-reader-mode', this.preferences.screenReader);
  }

  /**
   * Watch for user preference changes
   */
  watchUserPreferences() {
    // Watch for system preference changes and update accordingly
    const queries = [
      { query: '(prefers-reduced-motion: reduce)', preference: 'reducedMotion' },
      { query: '(prefers-contrast: high)', preference: 'highContrast' },
    ];

    queries.forEach(({ query, preference }) => {
      const mediaQuery = window.matchMedia(query);
      mediaQuery.addEventListener('change', (e) => {
        this.saveUserPreferences({ [preference]: e.matches });
      });
    });
  }

  /**
   * Utility: Check if element is visible
   */
  isVisible(element) {
    return element.offsetParent !== null && 
           getComputedStyle(element).visibility !== 'hidden' &&
           getComputedStyle(element).display !== 'none';
  }

  /**
   * Utility: Check if element is natively focusable
   */
  isNativelyFocusable(element) {
    const focusableTags = ['a', 'button', 'input', 'select', 'textarea'];
    return focusableTags.includes(element.tagName.toLowerCase()) ||
           element.hasAttribute('contenteditable');
  }

  /**
   * Utility: Get accessible label for element
   */
  getAccessibleLabel(element) {
    // Check aria-label
    const ariaLabel = element.getAttribute('aria-label');
    if (ariaLabel) return ariaLabel;

    // Check aria-labelledby
    const labelledBy = element.getAttribute('aria-labelledby');
    if (labelledBy) {
      const labelElement = document.getElementById(labelledBy);
      if (labelElement) return labelElement.textContent.trim();
    }

    // Check associated label
    if (element.id) {
      const label = document.querySelector(`label[for="${element.id}"]`);
      if (label) return label.textContent.trim();
    }

    // Check title attribute
    const title = element.getAttribute('title');
    if (title) return title;

    // Check text content for buttons and links
    if (['button', 'a'].includes(element.tagName.toLowerCase())) {
      const text = element.textContent.trim();
      if (text) return text;
    }

    // Check alt text for images
    if (element.tagName.toLowerCase() === 'img') {
      return element.getAttribute('alt') || '';
    }

    return null;
  }

  /**
   * Update ARIA states for dynamic content
   */
  setAriaExpanded(element, expanded) {
    element.setAttribute('aria-expanded', expanded.toString());
    
    // Update associated content visibility
    const controls = element.getAttribute('aria-controls');
    if (controls) {
      const controlled = document.getElementById(controls);
      if (controlled) {
        controlled.setAttribute('aria-hidden', (!expanded).toString());
        if (expanded) {
          controlled.removeAttribute('hidden');
        } else {
          controlled.setAttribute('hidden', '');
        }
      }
    }
  }

  /**
   * Set ARIA selected state
   */
  setAriaSelected(element, selected) {
    element.setAttribute('aria-selected', selected.toString());
    
    // Update visual state
    element.classList.toggle('selected', selected);
    
    // Update tabindex for keyboard navigation
    element.setAttribute('tabindex', selected ? '0' : '-1');
  }

  /**
   * Validate accessibility of element
   */
  validateAccessibility(element) {
    const issues = [];

    // Check for missing alt text on images
    if (element.tagName === 'IMG' && !element.hasAttribute('alt')) {
      issues.push('Image missing alt attribute');
    }

    // Check for missing labels on form controls
    const formControls = ['INPUT', 'SELECT', 'TEXTAREA'];
    if (formControls.includes(element.tagName) && !this.getAccessibleLabel(element)) {
      issues.push('Form control missing accessible label');
    }

    // Check for low contrast (simplified check)
    if (element.tagName === 'BUTTON' || element.tagName === 'A') {
      const styles = getComputedStyle(element);
      const bgColor = styles.backgroundColor;
      const textColor = styles.color;
      
      // This is a simplified contrast check - in production you'd use a proper contrast ratio calculation
      if (bgColor === textColor) {
        issues.push('Potential contrast issue detected');
      }
    }

    return issues;
  }
}

// Create global accessibility manager instance
window.accessibilityManager = new AccessibilityManager();

// Export for ES6 modules and backward compatibility
export default window.accessibilityManager;

// Legacy exports for backward compatibility
export const trapFocus = (element, options) => window.accessibilityManager.trapFocus(element, options);
export const announce = (message, priority) => window.accessibilityManager.announce(message, priority);