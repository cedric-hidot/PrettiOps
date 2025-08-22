/**
 * Advanced Hero Controller - Parallax and Animation Effects
 * 
 * Handles hero section animations, parallax effects, and floating elements
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["floatingElements"]
    static values = { 
        parallax: { type: Boolean, default: true },
        animationSpeed: { type: Number, default: 0.5 }
    }

    connect() {
        this.setupParallaxEffect()
        this.animateFloatingElements()
        this.bindScrollEvents()
        this.setupIntersectionObserver()
    }

    setupParallaxEffect() {
        if (!this.parallaxValue) return

        this.parallaxHandler = this.handleParallax.bind(this)
        window.addEventListener('scroll', this.parallaxHandler, { passive: true })
    }

    handleParallax() {
        const scrolled = window.pageYOffset
        const parallaxElements = this.element.querySelectorAll('.po-gradient-animation')
        
        parallaxElements.forEach(element => {
            const speed = this.animationSpeedValue
            element.style.transform = `translateY(${scrolled * speed}px)`
        })

        // Parallax for floating code elements
        if (this.hasFloatingElementsTarget) {
            const codeBlocks = this.floatingElementsTarget.querySelectorAll('.code-block')
            codeBlocks.forEach((block, index) => {
                const speed = 0.2 + (index * 0.1)
                block.style.transform = `translateY(${scrolled * speed}px)`
            })
        }
    }

    animateFloatingElements() {
        if (!this.hasFloatingElementsTarget) return

        const codeBlocks = this.floatingElementsTarget.querySelectorAll('.code-block')
        
        codeBlocks.forEach((block, index) => {
            // Initial state
            block.style.opacity = '0'
            block.style.transform = 'translateY(30px) scale(0.8)'
            
            // Animate in with delay
            setTimeout(() => {
                block.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)'
                block.style.opacity = '0.2'
                block.style.transform = 'translateY(0) scale(1)'
            }, index * 200)
        })
    }

    bindScrollEvents() {
        this.scrollHandler = this.handleScroll.bind(this)
        window.addEventListener('scroll', this.scrollHandler, { passive: true })
    }

    handleScroll() {
        const scrolled = window.pageYOffset
        const heroHeight = this.element.offsetHeight
        const scrollProgress = Math.min(scrolled / heroHeight, 1)

        // Fade hero content on scroll
        const heroContent = this.element.querySelector('.po-hero-intro')
        if (heroContent) {
            const opacity = 1 - (scrollProgress * 0.7)
            heroContent.style.opacity = Math.max(opacity, 0.3)
        }

        // Scale floating elements based on scroll
        if (this.hasFloatingElementsTarget) {
            const scale = 1 - (scrollProgress * 0.2)
            this.floatingElementsTarget.style.transform = `scale(${Math.max(scale, 0.8)})`
        }
    }

    setupIntersectionObserver() {
        // Animate elements when they come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '50px'
        }

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateElement(entry.target)
                }
            })
        }, observerOptions)

        // Observe animated elements
        const animatedElements = this.element.querySelectorAll('.meta-feature-item, .po-meta-infos')
        animatedElements.forEach(el => this.observer.observe(el))
    }

    animateElement(element) {
        element.style.opacity = '0'
        element.style.transform = 'translateY(20px)'
        element.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)'
        
        requestAnimationFrame(() => {
            element.style.opacity = '1'
            element.style.transform = 'translateY(0)'
        })
        
        // Unobserve after animation
        this.observer.unobserve(element)
    }

    // Button interaction enhancements
    enhanceButtons() {
        const buttons = this.element.querySelectorAll('.po-button')
        
        buttons.forEach(button => {
            button.addEventListener('mouseenter', this.buttonHoverIn.bind(this))
            button.addEventListener('mouseleave', this.buttonHoverOut.bind(this))
            button.addEventListener('click', this.buttonClick.bind(this))
        })
    }

    buttonHoverIn(event) {
        const button = event.currentTarget
        button.style.transform = 'scale(1.05) translateY(-2px)'
        
        // Add glow effect
        if (button.classList.contains('btn-primary')) {
            button.style.boxShadow = '0 10px 25px rgba(111, 0, 255, 0.3)'
        }
    }

    buttonHoverOut(event) {
        const button = event.currentTarget
        button.style.transform = 'scale(1) translateY(0)'
        button.style.boxShadow = ''
    }

    buttonClick(event) {
        const button = event.currentTarget
        
        // Ripple effect
        const ripple = document.createElement('div')
        ripple.classList.add('ripple')
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            pointer-events: none;
            width: 0;
            height: 0;
            animation: rippleAnimation 0.6s ease-out;
        `
        
        const rect = button.getBoundingClientRect()
        const size = Math.max(rect.width, rect.height)
        const x = event.clientX - rect.left - size / 2
        const y = event.clientY - rect.top - size / 2
        
        ripple.style.left = x + 'px'
        ripple.style.top = y + 'px'
        ripple.style.width = size + 'px'
        ripple.style.height = size + 'px'
        
        button.style.position = 'relative'
        button.appendChild(ripple)
        
        setTimeout(() => ripple.remove(), 600)
    }

    // Performance monitoring
    measurePerformance() {
        if (!window.performance) return

        const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart
        console.log(`Hero loaded in ${loadTime}ms`)
        
        // Track animation performance
        let frameCount = 0
        const startTime = performance.now()
        
        const measureFPS = () => {
            frameCount++
            if (frameCount % 60 === 0) {
                const currentTime = performance.now()
                const fps = 60000 / (currentTime - startTime)
                
                if (fps < 30) {
                    console.warn('Low FPS detected in hero animations:', fps)
                    this.optimizePerformance()
                }
            }
            requestAnimationFrame(measureFPS)
        }
        
        requestAnimationFrame(measureFPS)
    }

    optimizePerformance() {
        // Reduce animation complexity for low-performance devices
        const floatingElements = this.element.querySelectorAll('.animate-float-1, .animate-float-2, .animate-float-3')
        floatingElements.forEach(el => {
            el.style.animation = 'none'
            el.style.opacity = '0.1'
        })
        
        // Disable parallax on low-performance devices
        if (this.parallaxValue) {
            window.removeEventListener('scroll', this.parallaxHandler)
            this.parallaxValue = false
        }
    }

    // Public methods for external control
    pauseAnimations() {
        const animatedElements = this.element.querySelectorAll('.animate-float-1, .animate-float-2, .animate-float-3')
        animatedElements.forEach(el => {
            el.style.animationPlayState = 'paused'
        })
    }

    resumeAnimations() {
        const animatedElements = this.element.querySelectorAll('.animate-float-1, .animate-float-2, .animate-float-3')
        animatedElements.forEach(el => {
            el.style.animationPlayState = 'running'
        })
    }

    scrollToContent() {
        const contentSection = document.querySelector('.code-showcase-section')
        if (contentSection) {
            contentSection.scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            })
        }
    }

    disconnect() {
        // Clean up event listeners
        if (this.parallaxHandler) {
            window.removeEventListener('scroll', this.parallaxHandler)
        }
        if (this.scrollHandler) {
            window.removeEventListener('scroll', this.scrollHandler)
        }
        if (this.observer) {
            this.observer.disconnect()
        }
    }
}

// Add ripple animation CSS
const style = document.createElement('style')
style.textContent = `
@keyframes rippleAnimation {
    from {
        width: 0;
        height: 0;
        opacity: 1;
    }
    to {
        width: 100px;
        height: 100px;
        opacity: 0;
    }
}
`
document.head.appendChild(style)