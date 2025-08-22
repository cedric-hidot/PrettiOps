/**
 * Marquee Logos Controller - Based on Tiptap's System
 * 
 * Handles infinite scrolling logo marquee with pause on hover
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["row", "content"]
    static values = { 
        speed: { type: Number, default: 30 },
        pauseOnHover: { type: Boolean, default: true }
    }

    connect() {
        this.setupMarquees()
        this.cloneContent()
        this.bindEvents()
    }

    setupMarquees() {
        this.contentTargets.forEach((content, index) => {
            const row = content.closest('[data-marquee-logos-target="row"]')
            const isReverse = content.getAttribute('data-direction') === 'reverse'
            
            // Set animation duration based on speed
            const duration = `${this.speedValue}s`
            content.style.animationDuration = duration
            
            // Set animation direction
            if (isReverse) {
                content.style.animationName = 'marquee-reverse'
            } else {
                content.style.animationName = 'marquee'
            }
        })
    }

    cloneContent() {
        // Clone content for seamless loop
        this.contentTargets.forEach(content => {
            const clone = content.cloneNode(true)
            clone.setAttribute('aria-hidden', 'true') // Hide from screen readers
            content.parentNode.appendChild(clone)
        })
    }

    bindEvents() {
        if (this.pauseOnHoverValue) {
            this.rowTargets.forEach(row => {
                row.addEventListener('mouseenter', this.pauseAnimation.bind(this))
                row.addEventListener('mouseleave', this.resumeAnimation.bind(this))
            })
        }

        // Handle intersection observer for performance
        this.setupIntersectionObserver()
    }

    pauseAnimation(event) {
        const row = event.currentTarget
        const contents = row.querySelectorAll('[data-marquee-logos-target="content"]')
        contents.forEach(content => {
            content.style.animationPlayState = 'paused'
        })
    }

    resumeAnimation(event) {
        const row = event.currentTarget
        const contents = row.querySelectorAll('[data-marquee-logos-target="content"]')
        contents.forEach(content => {
            content.style.animationPlayState = 'running'
        })
    }

    setupIntersectionObserver() {
        // Pause animations when not visible for performance
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const contents = entry.target.querySelectorAll('[data-marquee-logos-target="content"]')
                
                if (entry.isIntersecting) {
                    contents.forEach(content => {
                        content.style.animationPlayState = 'running'
                    })
                } else {
                    contents.forEach(content => {
                        content.style.animationPlayState = 'paused'
                    })
                }
            })
        }, {
            threshold: 0.1,
            rootMargin: '50px'
        })

        this.rowTargets.forEach(row => {
            observer.observe(row)
        })
        
        this.observer = observer
    }

    // Public methods for external control
    pause() {
        this.contentTargets.forEach(content => {
            content.style.animationPlayState = 'paused'
        })
    }

    resume() {
        this.contentTargets.forEach(content => {
            content.style.animationPlayState = 'running'
        })
    }

    setSpeed(speed) {
        this.speedValue = speed
        this.setupMarquees()
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect()
        }
    }
}