/**
 * Feature Card Controller - Tiptap-inspired
 * 
 * Handles interactive feature cards with hover animations, glassmorphism effects,
 * and scroll-based reveal animations inspired by Tiptap.dev
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = [
    'card',
    'icon',
    'content',
    'badge',
    'number'
  ]
  
  static values = {
    hoverScale: { type: Number, default: 1.05 },
    hoverDelay: { type: Number, default: 150 },
    revealOnScroll: { type: Boolean, default: true },
    animationDelay: { type: Number, default: 100 }
  }

  connect() {
    this.setupCards()
    this.setupScrollReveal()
    console.log('Feature card controller connected with Tiptap-inspired interactions')
  }

  disconnect() {
    this.cleanup()
  }

  setupCards() {
    this.cardTargets.forEach((card, index) => {
      // Setup hover animations
      card.addEventListener('mouseenter', this.cardHoverIn.bind(this))
      card.addEventListener('mouseleave', this.cardHoverOut.bind(this))
      
      // Setup focus animations for accessibility
      card.addEventListener('focusin', this.cardFocusIn.bind(this))
      card.addEventListener('focusout', this.cardFocusOut.bind(this))
      
      // Initial setup
      card.style.transition = 'all 0.3s cubic-bezier(0.22, 1, 0.36, 1)'
      
      // Add tabindex for keyboard navigation if not already present
      if (!card.hasAttribute('tabindex')) {
        card.setAttribute('tabindex', '0')
      }
      
      // Setup initial state for scroll reveal
      if (this.revealOnScrollValue) {
        this.setupInitialState(card, index)
      }
    })
  }

  setupInitialState(card, index) {
    // Hide cards initially for scroll reveal animation
    card.style.opacity = '0'
    card.style.transform = 'translateY(30px)'
    card.dataset.revealed = 'false'
    card.dataset.index = index
  }

  setupScrollReveal() {
    if (!this.revealOnScrollValue) return

    this.intersectionObserver = new IntersectionObserver(
      this.handleIntersection.bind(this),
      {
        threshold: 0.1,
        rootMargin: '50px 0px'
      }
    )

    this.cardTargets.forEach(card => {
      this.intersectionObserver.observe(card)
    })
  }

  handleIntersection(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting && entry.target.dataset.revealed === 'false') {
        this.revealCard(entry.target)
      }
    })
  }

  revealCard(card) {
    const index = parseInt(card.dataset.index) || 0
    const delay = index * this.animationDelayValue

    setTimeout(() => {
      card.style.transition = 'all 0.8s cubic-bezier(0.22, 1, 0.36, 1)'
      card.style.opacity = '1'
      card.style.transform = 'translateY(0)'
      card.dataset.revealed = 'true'
      
      // Animate child elements
      this.animateCardElements(card)
    }, delay)
  }

  animateCardElements(card) {
    // Animate icon
    const icon = card.querySelector('[data-feature-card-target="icon"]')
    if (icon) {
      setTimeout(() => {
        icon.style.transform = 'scale(1.1)'
        setTimeout(() => {
          icon.style.transition = 'transform 0.3s ease'
          icon.style.transform = 'scale(1)'
        }, 200)
      }, 300)
    }

    // Animate number badge
    const number = card.querySelector('[data-feature-card-target="number"]')
    if (number) {
      setTimeout(() => {
        number.style.animation = 'bounce 0.6s ease'
      }, 400)
    }

    // Animate badges
    const badges = card.querySelectorAll('[data-feature-card-target="badge"]')
    badges.forEach((badge, index) => {
      setTimeout(() => {
        badge.style.opacity = '1'
        badge.style.transform = 'translateX(0)'
      }, 500 + (index * 100))
    })
  }

  cardHoverIn(event) {
    const card = event.currentTarget
    
    // Main card animation
    card.style.transform = `translateY(-8px) scale(${this.hoverScaleValue})`
    card.style.boxShadow = '0 20px 60px rgba(111, 0, 255, 0.15)'
    
    // Update border color
    card.style.borderColor = 'rgba(111, 0, 255, 0.3)'
    
    // Animate icon
    const icon = card.querySelector('[data-feature-card-target="icon"]')
    if (icon) {
      icon.style.transform = 'scale(1.1) rotate(5deg)'
    }

    // Enhance glassmorphism effect
    this.enhanceGlassmorphism(card)
    
    // Animate content
    const content = card.querySelector('[data-feature-card-target="content"]')
    if (content) {
      content.style.transform = 'translateY(-2px)'
    }

    // Create subtle glow effect
    this.createGlowEffect(card)
  }

  cardHoverOut(event) {
    const card = event.currentTarget
    
    // Reset main card
    card.style.transform = 'translateY(0) scale(1)'
    card.style.boxShadow = '0 8px 32px rgba(0, 0, 0, 0.1)'
    card.style.borderColor = 'rgba(111, 0, 255, 0.1)'
    
    // Reset icon
    const icon = card.querySelector('[data-feature-card-target="icon"]')
    if (icon) {
      icon.style.transform = 'scale(1) rotate(0deg)'
    }

    // Reset glassmorphism
    this.resetGlassmorphism(card)
    
    // Reset content
    const content = card.querySelector('[data-feature-card-target="content"]')
    if (content) {
      content.style.transform = 'translateY(0)'
    }

    // Remove glow effect
    this.removeGlowEffect(card)
  }

  cardFocusIn(event) {
    // Similar to hover but more accessible
    this.cardHoverIn(event)
    event.currentTarget.style.outline = '2px solid #6f00ff'
    event.currentTarget.style.outlineOffset = '2px'
  }

  cardFocusOut(event) {
    this.cardHoverOut(event)
    event.currentTarget.style.outline = 'none'
  }

  enhanceGlassmorphism(card) {
    // Increase backdrop blur and adjust opacity
    card.style.backdropFilter = 'blur(20px)'
    card.style.background = 'rgba(255, 255, 255, 0.95)'
  }

  resetGlassmorphism(card) {
    card.style.backdropFilter = 'blur(10px)'
    card.style.background = 'rgba(255, 255, 255, 0.9)'
  }

  createGlowEffect(card) {
    // Create a subtle glow using pseudo-element or additional styling
    card.style.position = 'relative'
    
    if (!card.querySelector('.glow-effect')) {
      const glow = document.createElement('div')
      glow.className = 'glow-effect'
      glow.style.cssText = `
        position: absolute;
        inset: -2px;
        background: linear-gradient(135deg, #6f00ff, #8a2be2, #4169e1);
        border-radius: 1.5rem;
        z-index: -1;
        opacity: 0.1;
        transition: opacity 0.3s ease;
        pointer-events: none;
      `
      card.appendChild(glow)
    }
    
    const glow = card.querySelector('.glow-effect')
    if (glow) {
      glow.style.opacity = '0.2'
    }
  }

  removeGlowEffect(card) {
    const glow = card.querySelector('.glow-effect')
    if (glow) {
      glow.style.opacity = '0'
    }
  }

  // Public methods for external control
  revealAllCards() {
    this.cardTargets.forEach((card, index) => {
      setTimeout(() => {
        this.revealCard(card)
      }, index * this.animationDelayValue)
    })
  }

  hideAllCards() {
    this.cardTargets.forEach(card => {
      card.style.opacity = '0'
      card.style.transform = 'translateY(30px)'
      card.dataset.revealed = 'false'
    })
  }

  animateNumber(numberElement, targetNumber, duration = 1000) {
    const startNumber = 0
    const startTime = performance.now()
    
    const animate = (currentTime) => {
      const elapsed = currentTime - startTime
      const progress = Math.min(elapsed / duration, 1)
      
      // Easing function
      const easeOut = 1 - Math.pow(1 - progress, 3)
      const currentNumber = Math.floor(startNumber + (targetNumber - startNumber) * easeOut)
      
      numberElement.textContent = currentNumber.toString().padStart(2, '0')
      
      if (progress < 1) {
        requestAnimationFrame(animate)
      }
    }
    
    requestAnimationFrame(animate)
  }

  // Card click handler for interactive features
  cardClick(event) {
    const card = event.currentTarget
    
    // Create ripple effect
    this.createRipple(event, card)
    
    // Dispatch custom event for other controllers
    this.dispatch('cardClicked', {
      detail: {
        card: card,
        index: card.dataset.index
      }
    })
  }

  createRipple(event, card) {
    const ripple = document.createElement('span')
    const rect = card.getBoundingClientRect()
    const size = Math.max(rect.width, rect.height)
    const x = event.clientX - rect.left - size / 2
    const y = event.clientY - rect.top - size / 2
    
    ripple.style.cssText = `
      position: absolute;
      border-radius: 50%;
      background: rgba(111, 0, 255, 0.3);
      width: ${size}px;
      height: ${size}px;
      left: ${x}px;
      top: ${y}px;
      animation: ripple 0.6s ease-out;
      pointer-events: none;
      z-index: 1;
    `
    
    card.style.position = 'relative'
    card.appendChild(ripple)
    
    setTimeout(() => ripple.remove(), 600)
  }

  cleanup() {
    if (this.intersectionObserver) {
      this.intersectionObserver.disconnect()
    }
    
    this.cardTargets.forEach(card => {
      card.removeEventListener('mouseenter', this.cardHoverIn.bind(this))
      card.removeEventListener('mouseleave', this.cardHoverOut.bind(this))
      card.removeEventListener('focusin', this.cardFocusIn.bind(this))
      card.removeEventListener('focusout', this.cardFocusOut.bind(this))
    })
  }
}

// Add necessary CSS animations
const style = document.createElement('style')
style.textContent = `
  @keyframes bounce {
    0%, 20%, 53%, 80%, 100% {
      transform: translate3d(0,0,0);
    }
    40%, 43% {
      transform: translate3d(0,-30px,0);
    }
    70% {
      transform: translate3d(0,-15px,0);
    }
    90% {
      transform: translate3d(0,-4px,0);
    }
  }
  
  @keyframes ripple {
    to {
      transform: scale(2);
      opacity: 0;
    }
  }
`
if (!document.querySelector('#feature-card-styles')) {
  style.id = 'feature-card-styles'
  document.head.appendChild(style)
}