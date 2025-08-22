/**
 * Counter Controller - Animated Number Counter
 * 
 * Animates numbers from 0 to target value with smooth easing
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = { 
        target: Number,
        duration: { type: Number, default: 2000 },
        startDelay: { type: Number, default: 0 },
        decimals: { type: Number, default: 0 },
        separator: { type: String, default: ',' }
    }

    connect() {
        this.hasStarted = false
        this.setupIntersectionObserver()
    }

    setupIntersectionObserver() {
        // Start animation when element comes into view
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.hasStarted) {
                    this.hasStarted = true
                    setTimeout(() => {
                        this.startAnimation()
                    }, this.startDelayValue)
                }
            })
        }, {
            threshold: 0.5
        })

        observer.observe(this.element)
        this.observer = observer
    }

    startAnimation() {
        const startValue = 0
        const endValue = this.targetValue
        const duration = this.durationValue
        const startTime = performance.now()

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime
            const progress = Math.min(elapsed / duration, 1)
            
            // Easing function - ease out cubic
            const easeProgress = 1 - Math.pow(1 - progress, 3)
            
            const currentValue = startValue + (endValue - startValue) * easeProgress
            
            this.updateDisplay(currentValue)
            
            if (progress < 1) {
                requestAnimationFrame(animate)
            } else {
                // Ensure final value is exactly the target
                this.updateDisplay(endValue)
                this.dispatch('completed', { detail: { finalValue: endValue } })
            }
        }

        requestAnimationFrame(animate)
    }

    updateDisplay(value) {
        let displayValue = value
        
        if (this.decimalsValue > 0) {
            displayValue = value.toFixed(this.decimalsValue)
        } else {
            displayValue = Math.round(value)
        }
        
        // Add separator for thousands
        if (this.separatorValue && displayValue >= 1000) {
            displayValue = displayValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, this.separatorValue)
        }
        
        // Handle special formatting for percentages
        if (this.element.textContent.includes('%') || this.targetValue <= 100) {
            this.element.textContent = displayValue + (this.targetValue <= 100 && this.targetValue > 50 ? '%' : '')
        } else {
            this.element.textContent = displayValue
        }
    }

    // Public methods
    reset() {
        this.hasStarted = false
        this.element.textContent = '0'
    }

    restart() {
        this.reset()
        setTimeout(() => {
            this.startAnimation()
        }, 100)
    }

    setTarget(newTarget) {
        this.targetValue = newTarget
        this.restart()
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect()
        }
    }
}