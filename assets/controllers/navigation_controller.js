/**
 * Navigation Controller - Tiptap-inspired
 * 
 * Enhanced navigation with glassmorphism, mega menu, and smooth animations
 * inspired by Tiptap.dev design patterns
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'navbar',
    'menu',
    'menuButton',
    'dropdown',
    'dropdownContent',
    'link',
    'logo',
    'mobileMenu'
  ]
  
  static values = {
    scrollThreshold: { type: Number, default: 100 },
    megaMenuDelay: { type: Number, default: 200 },
    enableGlassmorphism: { type: Boolean, default: true },
    mobileBreakpoint: { type: Number, default: 768 }
  }
  
  static classes = [
    'scrolled',
    'mobileOpen',
    'dropdownOpen'
  ]

  connect() {
    this.setupScrollEffects()
    this.setupMegaMenu()
    this.setupMobileMenu()
    this.setupGlassmorphism()
    this.currentScroll = 0
    this.ticking = false
    console.log('Enhanced navigation controller connected with Tiptap-inspired effects')
  }

  disconnect() {
    this.cleanup()
  }

  setupScrollEffects() {
    this.scrollHandler = this.handleScroll.bind(this)
    window.addEventListener('scroll', this.scrollHandler, { passive: true })
    
    // Initial call to set correct state
    this.handleScroll()
  }

  setupMegaMenu() {
    this.dropdownTargets.forEach((dropdown, index) => {
      const trigger = dropdown.querySelector('[data-navigation-target="menuButton"]')
      const content = dropdown.querySelector('[data-navigation-target="dropdownContent"]')
      
      if (trigger && content) {
        // Mouse events
        trigger.addEventListener('mouseenter', () => this.showMegaMenu(dropdown, content))
        dropdown.addEventListener('mouseleave', () => this.hideMegaMenu(dropdown, content))
        
        // Focus events for accessibility
        trigger.addEventListener('focus', () => this.showMegaMenu(dropdown, content))
        trigger.addEventListener('blur', (e) => {
          // Only hide if focus is moving outside the dropdown
          setTimeout(() => {
            if (!dropdown.contains(document.activeElement)) {
              this.hideMegaMenu(dropdown, content)
            }
          }, 10)
        })
        
        // Keyboard navigation
        trigger.addEventListener('keydown', (e) => this.handleMenuKeydown(e, dropdown, content))
        
        // Setup ARIA attributes
        trigger.setAttribute('aria-haspopup', 'true')
        trigger.setAttribute('aria-expanded', 'false')
        content.setAttribute('role', 'menu')
      }
    })
  }

  setupMobileMenu() {
    // Mobile menu toggle
    const mobileMenuButton = this.element.querySelector('[data-action="navigation#toggleMobileMenu"]')
    if (mobileMenuButton && this.hasMobileMenuTarget) {
      mobileMenuButton.addEventListener('click', this.toggleMobileMenu.bind(this))
    }
    
    // Close mobile menu on resize
    this.resizeHandler = () => {
      if (window.innerWidth > this.mobileBreakpointValue && this.isMobileMenuOpen()) {
        this.closeMobileMenu()
      }
    }
    window.addEventListener('resize', this.resizeHandler)
  }

  setupGlassmorphism() {
    if (!this.enableGlassmorphismValue || !this.hasNavbarTarget) return
    
    // Apply initial glassmorphism styles
    this.navbarTarget.style.backdropFilter = 'blur(20px)'
    this.navbarTarget.style.background = 'rgba(255, 255, 255, 0.95)'
    this.navbarTarget.style.borderBottom = '1px solid rgba(111, 0, 255, 0.1)'
    this.navbarTarget.style.transition = 'all 0.3s cubic-bezier(0.22, 1, 0.36, 1)'
  }

  handleScroll() {
    this.currentScroll = window.pageYOffset
    
    if (!this.ticking) {
      requestAnimationFrame(() => this.updateNavbar())
      this.ticking = true
    }
  }

  updateNavbar() {
    const isScrolled = this.currentScroll > this.scrollThresholdValue
    
    if (this.hasNavbarTarget) {
      if (isScrolled) {
        this.navbarTarget.classList.add(this.scrolledClass || 'scrolled')
        
        // Enhanced glassmorphism when scrolled
        if (this.enableGlassmorphismValue) {
          this.navbarTarget.style.background = 'rgba(255, 255, 255, 0.98)'
          this.navbarTarget.style.backdropFilter = 'blur(30px)'
          this.navbarTarget.style.boxShadow = '0 8px 32px rgba(0, 0, 0, 0.1)'
        }
      } else {
        this.navbarTarget.classList.remove(this.scrolledClass || 'scrolled')
        
        if (this.enableGlassmorphismValue) {
          this.navbarTarget.style.background = 'rgba(255, 255, 255, 0.95)'
          this.navbarTarget.style.backdropFilter = 'blur(20px)'
          this.navbarTarget.style.boxShadow = 'none'
        }
      }
    }
    
    // Logo animation
    if (this.hasLogoTarget) {
      const scale = isScrolled ? 0.9 : 1
      this.logoTarget.style.transform = `scale(${scale})`
    }
    
    this.ticking = false
  }

  showMegaMenu(dropdown, content) {
    // Clear any existing timeout
    if (this.hideTimeout) {
      clearTimeout(this.hideTimeout)
      this.hideTimeout = null
    }
    
    this.showTimeout = setTimeout(() => {
      dropdown.classList.add(this.dropdownOpenClass || 'dropdown-open')
      content.style.opacity = '0'
      content.style.visibility = 'visible'
      content.style.transform = 'translateY(-10px)'
      
      // Animate in
      requestAnimationFrame(() => {
        content.style.transition = 'all 0.3s cubic-bezier(0.22, 1, 0.36, 1)'
        content.style.opacity = '1'
        content.style.transform = 'translateY(0)'
      })
      
      // Update ARIA
      const trigger = dropdown.querySelector('[data-navigation-target="menuButton"]')
      if (trigger) {
        trigger.setAttribute('aria-expanded', 'true')
      }
      
      // Add backdrop for mobile
      this.createBackdrop(dropdown)
      
      // Animate menu items
      this.animateMenuItems(content)
      
    }, this.megaMenuDelayValue)
  }

  hideMegaMenu(dropdown, content) {
    // Clear show timeout if it exists
    if (this.showTimeout) {
      clearTimeout(this.showTimeout)
      this.showTimeout = null
    }
    
    this.hideTimeout = setTimeout(() => {
      dropdown.classList.remove(this.dropdownOpenClass || 'dropdown-open')
      
      content.style.opacity = '0'
      content.style.transform = 'translateY(-10px)'
      
      setTimeout(() => {
        content.style.visibility = 'hidden'
      }, 300)
      
      // Update ARIA
      const trigger = dropdown.querySelector('[data-navigation-target="menuButton"]')
      if (trigger) {
        trigger.setAttribute('aria-expanded', 'false')
      }
      
      // Remove backdrop
      this.removeBackdrop()
      
    }, 100)
  }

  animateMenuItems(content) {
    const menuItems = content.querySelectorAll('a, button')
    menuItems.forEach((item, index) => {
      item.style.opacity = '0'
      item.style.transform = 'translateY(10px)'
      
      setTimeout(() => {
        item.style.transition = 'all 0.3s cubic-bezier(0.22, 1, 0.36, 1)'
        item.style.opacity = '1'
        item.style.transform = 'translateY(0)'
      }, index * 50)
    })
  }

  createBackdrop(dropdown) {
    if (window.innerWidth <= this.mobileBreakpointValue) {
      const backdrop = document.createElement('div')
      backdrop.className = 'menu-backdrop'
      backdrop.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
      `
      document.body.appendChild(backdrop)
      
      backdrop.addEventListener('click', () => {
        const content = dropdown.querySelector('[data-navigation-target="dropdownContent"]')
        this.hideMegaMenu(dropdown, content)
      })
      
      requestAnimationFrame(() => {
        backdrop.style.opacity = '1'
      })
    }
  }

  removeBackdrop() {
    const backdrop = document.querySelector('.menu-backdrop')
    if (backdrop) {
      backdrop.style.opacity = '0'
      setTimeout(() => backdrop.remove(), 300)
    }
  }

  handleMenuKeydown(event, dropdown, content) {
    switch (event.key) {
      case 'Enter':
      case ' ':
        event.preventDefault()
        if (dropdown.classList.contains(this.dropdownOpenClass || 'dropdown-open')) {
          this.hideMegaMenu(dropdown, content)
        } else {
          this.showMegaMenu(dropdown, content)
        }
        break
      case 'Escape':
        this.hideMegaMenu(dropdown, content)
        break
      case 'ArrowDown':
        event.preventDefault()
        const firstMenuItem = content.querySelector('a, button')
        if (firstMenuItem) firstMenuItem.focus()
        break
    }
  }

  toggleMobileMenu() {
    if (this.isMobileMenuOpen()) {
      this.closeMobileMenu()
    } else {
      this.openMobileMenu()
    }
  }

  openMobileMenu() {
    if (!this.hasMobileMenuTarget) return
    
    this.element.classList.add(this.mobileOpenClass || 'mobile-open')
    this.mobileMenuTarget.style.display = 'block'
    this.mobileMenuTarget.style.opacity = '0'
    this.mobileMenuTarget.style.transform = 'translateY(-20px)'
    
    requestAnimationFrame(() => {
      this.mobileMenuTarget.style.transition = 'all 0.3s cubic-bezier(0.22, 1, 0.36, 1)'
      this.mobileMenuTarget.style.opacity = '1'
      this.mobileMenuTarget.style.transform = 'translateY(0)'
    })
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden'
    
    // Add backdrop
    this.createMobileBackdrop()
  }

  closeMobileMenu() {
    if (!this.hasMobileMenuTarget) return
    
    this.element.classList.remove(this.mobileOpenClass || 'mobile-open')
    this.mobileMenuTarget.style.opacity = '0'
    this.mobileMenuTarget.style.transform = 'translateY(-20px)'
    
    setTimeout(() => {
      this.mobileMenuTarget.style.display = 'none'
    }, 300)
    
    // Restore body scroll
    document.body.style.overflow = ''
    
    // Remove backdrop
    this.removeMobileBackdrop()
  }

  isMobileMenuOpen() {
    return this.element.classList.contains(this.mobileOpenClass || 'mobile-open')
  }

  createMobileBackdrop() {
    const backdrop = document.createElement('div')
    backdrop.className = 'mobile-menu-backdrop'
    backdrop.style.cssText = `
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: 998;
      opacity: 0;
      transition: opacity 0.3s ease;
    `
    document.body.appendChild(backdrop)
    
    backdrop.addEventListener('click', this.closeMobileMenu.bind(this))
    
    requestAnimationFrame(() => {
      backdrop.style.opacity = '1'
    })
  }

  removeMobileBackdrop() {
    const backdrop = document.querySelector('.mobile-menu-backdrop')
    if (backdrop) {
      backdrop.style.opacity = '0'
      setTimeout(() => backdrop.remove(), 300)
    }
  }

  // Enhanced link hover effects
  linkHover(event) {
    const link = event.currentTarget
    link.style.transform = 'translateY(-1px)'
    link.style.textShadow = '0 2px 8px rgba(111, 0, 255, 0.2)'
  }

  linkLeave(event) {
    const link = event.currentTarget
    link.style.transform = 'translateY(0)'
    link.style.textShadow = 'none'
  }

  // Public methods
  scrollToTop() {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    })
  }

  hideAllDropdowns() {
    this.dropdownTargets.forEach(dropdown => {
      const content = dropdown.querySelector('[data-navigation-target="dropdownContent"]')
      if (content) {
        this.hideMegaMenu(dropdown, content)
      }
    })
  }

  cleanup() {
    if (this.scrollHandler) {
      window.removeEventListener('scroll', this.scrollHandler)
    }
    
    if (this.resizeHandler) {
      window.removeEventListener('resize', this.resizeHandler)
    }
    
    if (this.showTimeout) {
      clearTimeout(this.showTimeout)
    }
    
    if (this.hideTimeout) {
      clearTimeout(this.hideTimeout)
    }
    
    // Remove any backdrops
    this.removeBackdrop()
    this.removeMobileBackdrop()
    
    // Restore body scroll
    document.body.style.overflow = ''
  }
}