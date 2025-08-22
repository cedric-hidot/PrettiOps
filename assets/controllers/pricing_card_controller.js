/**
 * Pricing Card Controller - Based on Tiptap's Interactive System
 * 
 * Handles dynamic pricing updates and cross-card synchronization
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["price", "period", "savings"]
    static values = { 
        plan: String,
        animationDuration: { type: Number, default: 300 }
    }

    connect() {
        this.isYearly = false
        this.setupEventListeners()
        this.updatePricing('monthly')
    }

    setupEventListeners() {
        // Listen for billing period changes
        document.addEventListener('billingPeriodChanged', this.handleBillingPeriodChange.bind(this))
        
        // Listen for plan-specific events
        this.element.addEventListener('planActivated', this.handlePlanActivation.bind(this))
        this.element.addEventListener('planDeactivated', this.handlePlanDeactivation.bind(this))
    }

    handleBillingPeriodChange(event) {
        const { period, isYearly } = event.detail
        this.isYearly = isYearly
        this.updatePricing(period)
    }

    updatePricing(period) {
        const monthlyPrice = this.element.getAttribute('data-price-monthly')
        const yearlyPrice = this.element.getAttribute('data-price-yearly')
        
        let displayPrice, displayPeriod, savings = null
        
        if (period === 'yearly' && yearlyPrice !== 'custom') {
            displayPrice = yearlyPrice === '0' ? '€0' : `€${yearlyPrice}`
            displayPeriod = 'per month, billed yearly'
            
            // Calculate savings
            if (monthlyPrice !== '0' && yearlyPrice !== '0' && monthlyPrice !== 'custom') {
                const monthlyCost = parseFloat(monthlyPrice) * 12
                const yearlyCost = parseFloat(yearlyPrice) * 12
                const savedAmount = monthlyCost - yearlyCost
                
                if (savedAmount > 0) {
                    savings = `Save €${savedAmount.toFixed(2)}/year`
                }
            }
        } else {
            if (monthlyPrice === 'custom') {
                displayPrice = 'Custom'
                displayPeriod = 'pricing'
            } else {
                displayPrice = monthlyPrice === '0' ? '€0' : `€${monthlyPrice}`
                displayPeriod = this.planValue === 'team' ? 'per user/month' : 'per month'
            }
        }
        
        // Animate price change
        this.animatePriceUpdate(displayPrice, displayPeriod, savings)
    }

    animatePriceUpdate(newPrice, newPeriod, savings) {
        if (!this.hasPriceTarget) return
        
        // Fade out current price
        this.priceTarget.style.opacity = '0'
        this.priceTarget.style.transform = 'translateY(-10px)'
        
        if (this.hasPeriodTarget) {
            this.periodTarget.style.opacity = '0'
        }
        
        setTimeout(() => {
            // Update content
            this.priceTarget.textContent = newPrice
            if (this.hasPeriodTarget) {
                this.periodTarget.textContent = newPeriod
            }
            
            // Handle savings display
            if (this.hasSavingsTarget) {
                if (savings) {
                    this.savingsTarget.textContent = savings
                    this.savingsTarget.classList.remove('opacity-0')
                    this.savingsTarget.classList.add('opacity-100')
                } else {
                    this.savingsTarget.classList.remove('opacity-100')
                    this.savingsTarget.classList.add('opacity-0')
                }
            }
            
            // Fade in new price
            this.priceTarget.style.opacity = '1'
            this.priceTarget.style.transform = 'translateY(0)'
            
            if (this.hasPeriodTarget) {
                this.periodTarget.style.opacity = '1'
            }
        }, this.animationDurationValue / 2)
    }

    handlePlanActivation(event) {
        this.element.classList.add('plan-activated')
        this.element.classList.remove('plan-inactive')
        
        // Add glow effect for activated plan
        this.element.style.boxShadow = '0 0 30px rgba(111, 0, 255, 0.3)'
        this.element.style.borderColor = '#6f00ff'
    }

    handlePlanDeactivation(event) {
        this.element.classList.add('plan-inactive')
        this.element.classList.remove('plan-activated')
        
        // Remove glow effect
        this.element.style.boxShadow = ''
        this.element.style.borderColor = ''
    }

    // Public method to get current pricing info
    getCurrentPricing() {
        const monthlyPrice = this.element.getAttribute('data-price-monthly')
        const yearlyPrice = this.element.getAttribute('data-price-yearly')
        
        return {
            plan: this.planValue,
            monthly: monthlyPrice,
            yearly: yearlyPrice,
            current: this.isYearly ? yearlyPrice : monthlyPrice,
            period: this.isYearly ? 'yearly' : 'monthly'
        }
    }

    // Public method to activate this plan
    activate() {
        this.dispatch('activated', { detail: { plan: this.planValue } })
        this.handlePlanActivation()
    }

    // Public method to deactivate this plan
    deactivate() {
        this.dispatch('deactivated', { detail: { plan: this.planValue } })
        this.handlePlanDeactivation()
    }

    disconnect() {
        document.removeEventListener('billingPeriodChanged', this.handleBillingPeriodChange.bind(this))
    }
}

// Add CSS for smooth transitions
const style = document.createElement('style')
style.textContent = `
.pricing-card .price-amount,
.pricing-card .price-period {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.pricing-card.plan-inactive {
    opacity: 0.7;
    transform: scale(0.98);
}

.pricing-card.plan-activated {
    transform: scale(1.02);
    z-index: 10;
}

.pricing-card .savings-badge {
    transition: opacity 0.3s ease;
}

/* Hover effects for non-activated cards */
.pricing-card:hover:not(.plan-activated) {
    transform: translateY(-2px) scale(1.01);
}

/* Popular badge pulse animation */
.popular-badge {
    animation: pulse-glow 2s ease-in-out infinite alternate;
}

@keyframes pulse-glow {
    0% {
        box-shadow: 0 4px 12px rgba(111, 0, 255, 0.3);
    }
    100% {
        box-shadow: 0 4px 20px rgba(111, 0, 255, 0.5);
    }
}
`
document.head.appendChild(style)