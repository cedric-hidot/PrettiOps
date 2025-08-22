/**
 * Billing Tabs Controller - Based on Tiptap's System
 * 
 * Handles billing period switching with custom event dispatch
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["tab"]
    static values = { 
        initialPeriod: { type: String, default: "monthly" }
    }

    connect() {
        this.setInitialTab()
        this.triggerBillingPeriodChanged(this.initialPeriodValue)
    }

    setInitialTab() {
        this.tabTargets.forEach(tab => {
            const period = tab.getAttribute('data-billing-period')
            if (period === this.initialPeriodValue) {
                this.setActiveTab(tab)
            } else {
                this.setInactiveTab(tab)
            }
        })
    }

    switchPeriod(event) {
        const clickedTab = event.currentTarget
        const newPeriod = clickedTab.getAttribute('data-billing-period')
        
        // Update tab states
        this.tabTargets.forEach(tab => {
            if (tab === clickedTab) {
                this.setActiveTab(tab)
            } else {
                this.setInactiveTab(tab)
            }
        })
        
        // Trigger custom event for pricing cards
        this.triggerBillingPeriodChanged(newPeriod)
    }

    setActiveTab(tab) {
        tab.classList.remove('text-gray-600', 'hover:text-purple-600')
        tab.classList.add('bg-white', 'shadow-md', 'text-purple-700', 'active')
    }

    setInactiveTab(tab) {
        tab.classList.remove('bg-white', 'shadow-md', 'text-purple-700', 'active')
        tab.classList.add('text-gray-600', 'hover:text-purple-600')
    }

    triggerBillingPeriodChanged(activePeriod) {
        const event = new CustomEvent('billingPeriodChanged', {
            detail: { 
                period: activePeriod,
                isYearly: activePeriod === 'yearly'
            }
        })
        
        document.dispatchEvent(event)
        
        // Also dispatch from this element for local listeners
        this.dispatch('periodChanged', { 
            detail: { 
                period: activePeriod,
                isYearly: activePeriod === 'yearly'
            } 
        })
    }

    // Public method to get current period
    getCurrentPeriod() {
        const activeTab = this.tabTargets.find(tab => tab.classList.contains('active'))
        return activeTab ? activeTab.getAttribute('data-billing-period') : this.initialPeriodValue
    }

    // Public method to switch period programmatically
    setPeriod(period) {
        const targetTab = this.tabTargets.find(tab => 
            tab.getAttribute('data-billing-period') === period
        )
        
        if (targetTab) {
            this.switchPeriod({ currentTarget: targetTab })
        }
    }
}

// Add CSS animations
const style = document.createElement('style')
style.textContent = `
.billing-tab {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.billing-tab:hover:not(.active) {
    background: rgba(111, 0, 255, 0.05);
}

.billing-toggle {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
}
`
document.head.appendChild(style)