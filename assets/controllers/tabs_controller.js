/**
 * Tabs Controller - Tiptap-inspired
 * 
 * Enhanced tab system with smooth transitions, glassmorphism effects,
 * and keyboard navigation inspired by Tiptap.dev
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'tabMenu',
    'tabButton',
    'tabContent',
    'tabPane',
    'indicator'
  ]
  
  static values = {
    activeClass: { type: String, default: 'w--current' },
    transitionDuration: { type: Number, default: 300 },
    defaultTab: { type: Number, default: 0 },
    autoHeight: { type: Boolean, default: true },
    enableKeyboard: { type: Boolean, default: true }
  }
  
  static classes = ['active']

  connect() {
    this.currentTab = this.defaultTabValue
    this.setupTabs()
    this.setupKeyboardNavigation()
    this.setupIndicator()
    this.showInitialTab()
    console.log('Enhanced tabs controller connected with Tiptap-inspired animations')
  }

  disconnect() {
    this.cleanup()
  }

  setupTabs() {
    this.tabButtonTargets.forEach((button, index) => {
      button.addEventListener('click', this.tabClick.bind(this))
      button.setAttribute('role', 'tab')
      button.setAttribute('aria-selected', index === this.currentTab ? 'true' : 'false')
      button.setAttribute('tabindex', index === this.currentTab ? '0' : '-1')
      button.dataset.tabIndex = index
      
      // Enhanced styling
      button.style.transition = `all ${this.transitionDurationValue}ms cubic-bezier(0.22, 1, 0.36, 1)`
    })

    // Setup tab content
    this.tabPaneTargets.forEach((pane, index) => {
      pane.setAttribute('role', 'tabpanel')
      pane.setAttribute('aria-hidden', index === this.currentTab ? 'false' : 'true')
      pane.style.transition = `all ${this.transitionDurationValue}ms cubic-bezier(0.22, 1, 0.36, 1)`
      
      if (index !== this.currentTab) {
        pane.style.opacity = '0'
        pane.style.display = 'none'
        pane.style.transform = 'translateY(10px)'
      }
    })

    // Setup tab menu
    if (this.hasTabMenuTarget) {
      this.tabMenuTarget.setAttribute('role', 'tablist')
      this.tabMenuTarget.style.position = 'relative'
    }
  }

  setupIndicator() {
    if (!this.hasIndicatorTarget) {
      // Create indicator if it doesn't exist
      const indicator = document.createElement('div')
      indicator.className = 'tab-indicator'
      indicator.style.cssText = `
        position: absolute;
        bottom: 0;
        height: 2px;
        background: linear-gradient(135deg, #6f00ff, #8a2be2);
        transition: all ${this.transitionDurationValue}ms cubic-bezier(0.22, 1, 0.36, 1);
        border-radius: 1px;
        z-index: 1;
      `
      this.tabMenuTarget.appendChild(indicator)
      this.indicatorTarget = indicator
    }

    this.updateIndicator()
  }

  setupKeyboardNavigation() {
    if (!this.enableKeyboardValue) return

    this.keydownHandler = this.handleKeydown.bind(this)
    this.element.addEventListener('keydown', this.keydownHandler)
  }

  handleKeydown(event) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return

    event.preventDefault()
    
    const currentIndex = this.currentTab
    let newIndex = currentIndex

    switch (event.key) {
      case 'ArrowLeft':
        newIndex = currentIndex > 0 ? currentIndex - 1 : this.tabButtonTargets.length - 1
        break
      case 'ArrowRight':
        newIndex = currentIndex < this.tabButtonTargets.length - 1 ? currentIndex + 1 : 0
        break
      case 'Home':
        newIndex = 0
        break
      case 'End':
        newIndex = this.tabButtonTargets.length - 1
        break
    }

    this.showTab(newIndex)
    this.tabButtonTargets[newIndex].focus()
  }

  tabClick(event) {
    event.preventDefault()
    const button = event.currentTarget
    const tabIndex = parseInt(button.dataset.tabIndex)
    
    // Add click animation
    this.animateButtonClick(button)
    
    this.showTab(tabIndex)
  }

  animateButtonClick(button) {
    button.style.transform = 'scale(0.95)'
    setTimeout(() => {
      button.style.transform = 'scale(1)'
    }, 150)
  }

  async showTab(index) {
    if (index === this.currentTab || index < 0 || index >= this.tabButtonTargets.length) {
      return
    }

    const previousTab = this.currentTab
    this.currentTab = index

    // Update buttons
    this.updateButtons(previousTab, index)
    
    // Update content with animation
    await this.updateContent(previousTab, index)
    
    // Update indicator
    this.updateIndicator()

    // Dispatch custom event
    this.dispatch('tabChanged', {
      detail: {
        previousTab,
        currentTab: index,
        tabButton: this.tabButtonTargets[index],
        tabPane: this.tabPaneTargets[index]
      }
    })
  }

  updateButtons(previousIndex, currentIndex) {
    // Update previous button
    if (this.tabButtonTargets[previousIndex]) {
      const prevButton = this.tabButtonTargets[previousIndex]
      prevButton.classList.remove(this.activeClassValue)
      prevButton.setAttribute('aria-selected', 'false')
      prevButton.setAttribute('tabindex', '-1')
      
      // Visual feedback
      prevButton.style.background = 'transparent'
      prevButton.style.color = ''
    }

    // Update current button
    const currentButton = this.tabButtonTargets[currentIndex]
    currentButton.classList.add(this.activeClassValue)
    currentButton.setAttribute('aria-selected', 'true')
    currentButton.setAttribute('tabindex', '0')
    
    // Enhanced visual feedback
    currentButton.style.background = 'rgba(111, 0, 255, 0.05)'
    currentButton.style.color = '#6f00ff'
  }

  async updateContent(previousIndex, currentIndex) {
    const previousPane = this.tabPaneTargets[previousIndex]
    const currentPane = this.tabPaneTargets[currentIndex]

    // Fade out previous pane
    if (previousPane) {
      previousPane.style.opacity = '0'
      previousPane.style.transform = 'translateY(-10px)'
      
      await new Promise(resolve => {
        setTimeout(() => {
          previousPane.style.display = 'none'
          previousPane.setAttribute('aria-hidden', 'true')
          resolve()
        }, this.transitionDurationValue / 2)
      })
    }

    // Fade in current pane
    if (currentPane) {
      currentPane.style.display = 'block'
      currentPane.style.opacity = '0'
      currentPane.style.transform = 'translateY(10px)'
      currentPane.setAttribute('aria-hidden', 'false')
      
      // Force reflow
      currentPane.offsetHeight
      
      // Animate in
      setTimeout(() => {
        currentPane.style.opacity = '1'
        currentPane.style.transform = 'translateY(0)'
      }, 50)
    }

    // Auto-height adjustment
    if (this.autoHeightValue && this.hasTabContentTarget) {
      this.adjustContentHeight(currentPane)
    }
  }

  updateIndicator() {
    if (!this.hasIndicatorTarget) return

    const currentButton = this.tabButtonTargets[this.currentTab]
    if (!currentButton) return

    const buttonRect = currentButton.getBoundingClientRect()
    const menuRect = this.tabMenuTarget.getBoundingClientRect()
    
    const width = buttonRect.width
    const left = buttonRect.left - menuRect.left

    this.indicatorTarget.style.width = `${width}px`
    this.indicatorTarget.style.transform = `translateX(${left}px)`
  }

  adjustContentHeight(currentPane) {
    if (!currentPane) return

    const height = currentPane.scrollHeight
    this.tabContentTarget.style.height = `${height}px`
    
    // Reset height after transition to allow dynamic content
    setTimeout(() => {
      this.tabContentTarget.style.height = 'auto'
    }, this.transitionDurationValue)
  }

  showInitialTab() {
    // Show the default tab without animation
    const initialButton = this.tabButtonTargets[this.currentTab]
    const initialPane = this.tabPaneTargets[this.currentTab]

    if (initialButton) {
      initialButton.classList.add(this.activeClassValue)
      initialButton.setAttribute('aria-selected', 'true')
      initialButton.setAttribute('tabindex', '0')
      initialButton.style.background = 'rgba(111, 0, 255, 0.05)'
      initialButton.style.color = '#6f00ff'
    }

    if (initialPane) {
      initialPane.style.display = 'block'
      initialPane.style.opacity = '1'
      initialPane.style.transform = 'translateY(0)'
      initialPane.setAttribute('aria-hidden', 'false')
    }
  }

  // Public methods for external control
  next() {
    const nextIndex = this.currentTab < this.tabButtonTargets.length - 1 
      ? this.currentTab + 1 
      : 0
    this.showTab(nextIndex)
  }

  previous() {
    const prevIndex = this.currentTab > 0 
      ? this.currentTab - 1 
      : this.tabButtonTargets.length - 1
    this.showTab(prevIndex)
  }

  goToTab(index) {
    this.showTab(index)
  }

  getCurrentTab() {
    return this.currentTab
  }

  // Enhanced animations
  addGlowEffect(button) {
    button.style.boxShadow = '0 0 20px rgba(111, 0, 255, 0.3)'
  }

  removeGlowEffect(button) {
    button.style.boxShadow = ''
  }

  // Window resize handler to update indicator
  handleResize() {
    this.updateIndicator()
  }

  cleanup() {
    if (this.keydownHandler) {
      this.element.removeEventListener('keydown', this.keydownHandler)
    }
    
    this.tabButtonTargets.forEach(button => {
      button.removeEventListener('click', this.tabClick.bind(this))
    })
    
    if (this.resizeHandler) {
      window.removeEventListener('resize', this.resizeHandler)
    }
  }

  // Connect resize handler
  resizeHandler = this.handleResize.bind(this)
  
  static afterLoad() {
    window.addEventListener('resize', () => {
      // Update all tab controllers on resize
      this.instances?.forEach(instance => {
        instance.handleResize()
      })
    })
  }
}