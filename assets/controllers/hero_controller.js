/**
 * Hero Controller - Tiptap-inspired
 * 
 * Handles animated gradient backgrounds, text effects, and scroll-based animations
 * for hero sections inspired by Tiptap.dev design patterns
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'gradientContainer',
    'gradientPanel', 
    'heroText',
    'animatedElement',
    'button',
    'labelTag'
  ]
  
  static values = {
    animationType: { type: String, default: 'gradient-shift' },
    scrollOffset: { type: Number, default: 100 },
    enableParallax: { type: Boolean, default: true },
    gradientSpeed: { type: Number, default: 6 }
  }

  connect() {
    this.setupGradientAnimation()
    this.setupScrollAnimations()
    this.setupTextAnimations()
    this.setupButtonAnimations()
    console.log('Hero controller connected with Tiptap-inspired animations')
  }

  disconnect() {
    this.cleanup()
  }

  setupGradientAnimation() {
    if (this.hasGradientContainerTarget) {
      // Create animated gradient background
      this.gradientContainerTarget.style.background = `
        linear-gradient(-45deg, #6f00ff, #8a2be2, #4169e1, #1e90ff),
        radial-gradient(circle at 25% 25%, rgba(111, 0, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(65, 105, 225, 0.1) 0%, transparent 50%)
      `
      this.gradientContainerTarget.style.backgroundSize = '400% 400%'
      this.gradientContainerTarget.style.animation = `gradient-shift ${this.gradientSpeedValue}s ease-in-out infinite`
    }
  }

  setupScrollAnimations() {
    if (!this.enableParallaxValue) return

    this.scrollHandler = this.handleScroll.bind(this)
    window.addEventListener('scroll', this.scrollHandler, { passive: true })
    
    // Initial setup for scroll-based animations
    this.handleScroll()
  }

  setupTextAnimations() {
    // Animate hero text elements with staggered delays
    this.heroTextTargets.forEach((element, index) => {
      element.style.opacity = '0'
      element.style.transform = 'translateY(30px)'
      
      setTimeout(() => {
        element.style.transition = 'all 0.8s cubic-bezier(0.22, 1, 0.36, 1)'
        element.style.opacity = '1'
        element.style.transform = 'translateY(0)'
      }, 200 + (index * 150))
    })

    // Setup typing animation for special text elements
    this.setupTypingAnimation()
  }

  setupButtonAnimations() {
    this.buttonTargets.forEach(button => {
      // Enhanced hover animations
      button.addEventListener('mouseenter', this.buttonHoverIn.bind(this))
      button.addEventListener('mouseleave', this.buttonHoverOut.bind(this))
      
      // Click animation
      button.addEventListener('click', this.buttonClick.bind(this))
      
      // Initial setup
      button.style.transition = 'all 0.3s cubic-bezier(0.22, 1, 0.36, 1)'
    })
  }

  setupTypingAnimation() {
    // Find elements with data-animate="typing"
    const typingElements = this.element.querySelectorAll('[data-animate="typing"]')
    
    typingElements.forEach((element, index) => {
      const text = element.textContent
      element.textContent = ''
      element.style.opacity = '1'
      
      // Add typing cursor
      const cursor = document.createElement('span')
      cursor.textContent = '|'
      cursor.style.animation = 'blink 1s infinite'
      cursor.className = 'typing-cursor'
      
      setTimeout(() => {
        this.typeText(element, text, 50).then(() => {
          // Remove cursor after typing
          setTimeout(() => {
            if (cursor.parentNode) cursor.remove()
          }, 1000)
        })
      }, 500 + (index * 200))
      
      element.appendChild(cursor)
    })
  }

  async typeText(element, text, speed = 50) {
    return new Promise((resolve) => {
      let i = 0
      const timer = setInterval(() => {
        if (i < text.length) {
          // Insert character before cursor
          const cursor = element.querySelector('.typing-cursor')
          const textNode = document.createTextNode(text.charAt(i))
          if (cursor) {
            element.insertBefore(textNode, cursor)
          } else {
            element.appendChild(textNode)
          }
          i++
        } else {
          clearInterval(timer)
          resolve()
        }
      }, speed)
    })
  }

  handleScroll() {
    const scrollY = window.scrollY
    const windowHeight = window.innerHeight
    
    // Parallax effect for gradient background
    if (this.hasGradientContainerTarget) {
      const parallaxSpeed = 0.5
      this.gradientContainerTarget.style.transform = `translateY(${scrollY * parallaxSpeed}px)`
    }

    // Fade out hero content on scroll
    this.heroTextTargets.forEach(element => {
      const rect = element.getBoundingClientRect()
      const opacity = Math.max(0, Math.min(1, rect.top / windowHeight))
      element.style.opacity = opacity
    })

    // Animate label tags on scroll
    this.labelTagTargets.forEach((tag, index) => {
      const rect = tag.getBoundingClientRect()
      const isVisible = rect.top < windowHeight && rect.bottom > 0
      
      if (isVisible) {
        const delay = index * 100
        setTimeout(() => {
          tag.style.transform = 'translateY(0) scale(1)'
          tag.style.opacity = '1'
        }, delay)
      }
    })
  }

  buttonHoverIn(event) {
    const button = event.currentTarget
    button.style.transform = 'translateY(-3px) scale(1.02)'
    
    // Enhanced glow effect
    if (button.classList.contains('btn-primary')) {
      button.style.boxShadow = '0 15px 40px rgba(111, 0, 255, 0.4)'
    }
    
    // Arrow animation
    const arrow = button.querySelector('.btn-primary-arrow')
    if (arrow) {
      arrow.style.transform = 'translateX(4px)'
    }
  }

  buttonHoverOut(event) {
    const button = event.currentTarget
    button.style.transform = 'translateY(0) scale(1)'
    
    if (button.classList.contains('btn-primary')) {
      button.style.boxShadow = '0 8px 25px rgba(111, 0, 255, 0.3)'
    }
    
    const arrow = button.querySelector('.btn-primary-arrow')
    if (arrow) {
      arrow.style.transform = 'translateX(0)'
    }
  }

  buttonClick(event) {
    const button = event.currentTarget
    
    // Create ripple effect
    const ripple = document.createElement('span')
    const rect = button.getBoundingClientRect()
    const size = Math.max(rect.width, rect.height)
    const x = event.clientX - rect.left - size / 2
    const y = event.clientY - rect.top - size / 2
    
    ripple.style.cssText = `
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.6);
      width: ${size}px;
      height: ${size}px;
      left: ${x}px;
      top: ${y}px;
      animation: ripple 0.6s linear;
      pointer-events: none;
    `
    
    button.style.position = 'relative'
    button.appendChild(ripple)
    
    setTimeout(() => ripple.remove(), 600)
  }

  // Animation methods for external triggering
  playEntranceAnimation() {
    this.animatedElementTargets.forEach((element, index) => {
      element.style.opacity = '0'
      element.style.transform = 'translateY(50px)'
      
      setTimeout(() => {
        element.style.transition = 'all 0.8s cubic-bezier(0.22, 1, 0.36, 1)'
        element.style.opacity = '1'
        element.style.transform = 'translateY(0)'
      }, index * 100)
    })
  }

  triggerGradientAnimation() {
    if (this.hasGradientPanelTarget) {
      this.gradientPanelTarget.style.animation = 'none'
      this.gradientPanelTarget.offsetHeight // Trigger reflow
      this.gradientPanelTarget.style.animation = `gradient-shift ${this.gradientSpeedValue}s ease-in-out infinite`
    }
  }

  cleanup() {
    if (this.scrollHandler) {
      window.removeEventListener('scroll', this.scrollHandler)
    }
    
    // Clean up event listeners
    this.buttonTargets.forEach(button => {
      button.removeEventListener('mouseenter', this.buttonHoverIn.bind(this))
      button.removeEventListener('mouseleave', this.buttonHoverOut.bind(this))
      button.removeEventListener('click', this.buttonClick.bind(this))
    })
  }
}

// Add CSS animations via JavaScript if not in CSS
const style = document.createElement('style')
style.textContent = `
  @keyframes gradient-shift {
    0%, 100% {
      background-position: 0% 50%;
    }
    50% {
      background-position: 100% 50%;
    }
  }
  
  @keyframes ripple {
    to {
      transform: scale(2);
      opacity: 0;
    }
  }
  
  @keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
  }
  
  .typing-cursor {
    color: #6f00ff;
    font-weight: normal;
  }
`
document.head.appendChild(style)