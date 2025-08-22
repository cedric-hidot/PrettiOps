/**
 * Code Showcase Controller - Interactive Demo System
 * 
 * Handles tabbed code examples with smooth transitions and animations
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["tabContainer", "tabButton", "tabPane"]
    static values = { 
        transitionDuration: { type: Number, default: 300 }
    }

    connect() {
        this.setupInitialState()
        this.bindKeyboardEvents()
    }

    setupInitialState() {
        // Set first tab as active if none is marked as active
        const activeTab = this.tabButtonTargets.find(tab => tab.classList.contains('active'))
        if (!activeTab && this.tabButtonTargets.length > 0) {
            this.activateTab(this.tabButtonTargets[0])
        } else if (activeTab) {
            this.activateTab(activeTab)
        }
    }

    switchTab(event) {
        event.preventDefault()
        const clickedTab = event.currentTarget
        this.activateTab(clickedTab)
    }

    activateTab(targetTab) {
        const tabId = targetTab.getAttribute('data-tab')
        const targetPane = this.tabPaneTargets.find(pane => 
            pane.getAttribute('data-tab') === tabId
        )

        if (!targetPane) return

        // Update tab button states
        this.tabButtonTargets.forEach(tab => {
            if (tab === targetTab) {
                tab.classList.add('active', 'text-purple-400', 'border-purple-500')
                tab.classList.remove('text-gray-300')
                tab.setAttribute('aria-selected', 'true')
            } else {
                tab.classList.remove('active', 'text-purple-400', 'border-purple-500')
                tab.classList.add('text-gray-300')
                tab.setAttribute('aria-selected', 'false')
            }
        })

        // Smooth transition for tab panes
        this.transitionToPane(targetPane)

        // Dispatch custom event
        this.dispatch('tabChanged', { 
            detail: { 
                tabId: tabId, 
                tab: targetTab, 
                pane: targetPane 
            } 
        })
    }

    transitionToPane(targetPane) {
        // Fade out all panes
        this.tabPaneTargets.forEach(pane => {
            if (pane !== targetPane) {
                pane.style.opacity = '0'
                pane.style.transform = 'translateY(10px)'
                pane.classList.add('hidden')
                pane.setAttribute('aria-hidden', 'true')
            }
        })

        // Fade in target pane
        setTimeout(() => {
            targetPane.classList.remove('hidden')
            targetPane.style.opacity = '0'
            targetPane.style.transform = 'translateY(10px)'
            targetPane.setAttribute('aria-hidden', 'false')

            requestAnimationFrame(() => {
                targetPane.style.transition = `all ${this.transitionDurationValue}ms cubic-bezier(0.4, 0, 0.2, 1)`
                targetPane.style.opacity = '1'
                targetPane.style.transform = 'translateY(0)'
            })
        }, 50)
    }

    bindKeyboardEvents() {
        if (this.hasTabContainerTarget) {
            this.tabContainerTarget.addEventListener('keydown', this.handleKeydown.bind(this))
        }
    }

    handleKeydown(event) {
        const activeTabIndex = this.tabButtonTargets.findIndex(tab => 
            tab.classList.contains('active')
        )

        switch (event.key) {
            case 'ArrowLeft':
                event.preventDefault()
                const prevIndex = activeTabIndex > 0 ? activeTabIndex - 1 : this.tabButtonTargets.length - 1
                this.activateTab(this.tabButtonTargets[prevIndex])
                this.tabButtonTargets[prevIndex].focus()
                break
            
            case 'ArrowRight':
                event.preventDefault()
                const nextIndex = activeTabIndex < this.tabButtonTargets.length - 1 ? activeTabIndex + 1 : 0
                this.activateTab(this.tabButtonTargets[nextIndex])
                this.tabButtonTargets[nextIndex].focus()
                break
            
            case 'Home':
                event.preventDefault()
                this.activateTab(this.tabButtonTargets[0])
                this.tabButtonTargets[0].focus()
                break
            
            case 'End':
                event.preventDefault()
                const lastTab = this.tabButtonTargets[this.tabButtonTargets.length - 1]
                this.activateTab(lastTab)
                lastTab.focus()
                break
        }
    }

    // Public methods for external control
    activateTabById(tabId) {
        const targetTab = this.tabButtonTargets.find(tab => 
            tab.getAttribute('data-tab') === tabId
        )
        if (targetTab) {
            this.activateTab(targetTab)
        }
    }

    getActiveTab() {
        return this.tabButtonTargets.find(tab => tab.classList.contains('active'))
    }

    getActiveTabId() {
        const activeTab = this.getActiveTab()
        return activeTab ? activeTab.getAttribute('data-tab') : null
    }

    // Animation utilities
    animateCodeBlock(codeElement) {
        if (!codeElement) return

        const lines = codeElement.querySelectorAll('code > span, pre > span')
        lines.forEach((line, index) => {
            line.style.opacity = '0'
            line.style.transform = 'translateX(-20px)'
            
            setTimeout(() => {
                line.style.transition = 'all 0.3s ease'
                line.style.opacity = '1'
                line.style.transform = 'translateX(0)'
            }, index * 100)
        })
    }

    // Auto-play functionality
    startAutoPlay(interval = 5000) {
        if (this.autoPlayTimer) {
            clearInterval(this.autoPlayTimer)
        }

        this.autoPlayTimer = setInterval(() => {
            const activeIndex = this.tabButtonTargets.findIndex(tab => 
                tab.classList.contains('active')
            )
            const nextIndex = (activeIndex + 1) % this.tabButtonTargets.length
            this.activateTab(this.tabButtonTargets[nextIndex])
        }, interval)
    }

    stopAutoPlay() {
        if (this.autoPlayTimer) {
            clearInterval(this.autoPlayTimer)
            this.autoPlayTimer = null
        }
    }

    disconnect() {
        this.stopAutoPlay()
    }
}