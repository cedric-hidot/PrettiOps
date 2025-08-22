import { Controller } from "@hotwired/stimulus"

/**
 * Dashboard Controller
 * 
 * Manages the dashboard interface including snippet filtering, sorting,
 * search functionality, and statistics updates.
 * 
 * Targets:
 * - snippetGrid: Container for snippet cards
 * - languageFilter: Language filter dropdown
 * - statusFilter: Status filter dropdown
 * - sortFilter: Sort options dropdown
 * - searchInput: Search input field
 * - emptyState: Empty state container
 * - noResultsState: No results state container
 * - loadingState: Loading state container
 * - totalSnippets: Total snippets counter
 * - totalViews: Total views counter
 * - emailSends: Email sends counter
 * - activeLinks: Active links counter
 * 
 * Values:
 * - userId: Current user ID
 * - refreshInterval: Auto-refresh interval for stats
 */
export default class extends Controller {
    static targets = [
        "snippetGrid", "languageFilter", "statusFilter", "sortFilter", "searchInput",
        "emptyState", "noResultsState", "loadingState",
        "totalSnippets", "totalViews", "emailSends", "activeLinks"
    ]
    
    static values = {
        userId: String,
        refreshInterval: Number
    }

    connect() {
        console.log("Dashboard controller connected")
        this.setupFilters()
        this.setupSearch()
        this.setupAutoRefresh()
        this.loadInitialData()
        
        // Store reference globally
        window.dashboardController = this
    }

    disconnect() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer)
        }
        if (this.searchTimer) {
            clearTimeout(this.searchTimer)
        }
        window.dashboardController = null
    }

    /**
     * Load initial dashboard data
     */
    async loadInitialData() {
        try {
            await Promise.all([
                this.refreshStats(),
                this.loadSnippets()
            ])
        } catch (error) {
            console.error("Failed to load initial dashboard data:", error)
            this.showError("Failed to load dashboard data")
        }
    }

    /**
     * Setup filter functionality
     */
    setupFilters() {
        this.currentFilters = {
            language: '',
            status: '',
            search: '',
            sort: 'recent'
        }
    }

    /**
     * Setup search functionality
     */
    setupSearch() {
        this.searchDelay = 300 // milliseconds
    }

    /**
     * Setup auto-refresh for statistics
     */
    setupAutoRefresh() {
        if (this.refreshIntervalValue > 0) {
            this.refreshTimer = setInterval(() => {
                this.refreshStats()
            }, this.refreshIntervalValue)
        }
    }

    /**
     * Handle search input
     */
    filterSnippets(event) {
        const searchTerm = event ? event.target.value : this.searchInputTarget.value
        
        // Clear previous search timer
        if (this.searchTimer) {
            clearTimeout(this.searchTimer)
        }
        
        // Debounce search
        this.searchTimer = setTimeout(() => {
            this.currentFilters.search = searchTerm.toLowerCase()
            this.applyFilters()
        }, this.searchDelay)
    }

    /**
     * Handle language filter change
     */
    filterByLanguage(event) {
        this.currentFilters.language = event.target.value
        this.applyFilters()
        this.trackFilter('language', event.target.value)
    }

    /**
     * Handle status filter change
     */
    filterByStatus(event) {
        this.currentFilters.status = event.target.value
        this.applyFilters()
        this.trackFilter('status', event.target.value)
    }

    /**
     * Handle sort change
     */
    sortSnippets(event) {
        this.currentFilters.sort = event.target.value
        this.applySort()
        this.trackSort(event.target.value)
    }

    /**
     * Apply all active filters
     */
    applyFilters() {
        const snippetCards = this.getAllSnippetCards()
        let visibleCards = []

        snippetCards.forEach(card => {
            const isVisible = this.cardMatchesFilters(card)
            
            if (isVisible) {
                visibleCards.push(card)
                card.style.display = ''
            } else {
                card.style.display = 'none'
            }
        })

        // Apply current sort to visible cards
        this.sortCards(visibleCards)

        // Show appropriate state
        this.updateDisplayState(visibleCards.length)
    }

    /**
     * Check if card matches current filters
     */
    cardMatchesFilters(card) {
        const { language, status, search } = this.currentFilters

        // Language filter
        if (language && card.dataset.language !== language) {
            return false
        }

        // Status filter
        if (status && card.dataset.status !== status) {
            return false
        }

        // Search filter
        if (search) {
            const title = card.querySelector('.snippet-title')?.textContent.toLowerCase() || ''
            const description = card.querySelector('.snippet-description')?.textContent.toLowerCase() || ''
            const tags = card.querySelector('.snippet-tags')?.textContent.toLowerCase() || ''
            
            const searchableText = `${title} ${description} ${tags}`
            
            if (!searchableText.includes(search)) {
                return false
            }
        }

        return true
    }

    /**
     * Apply sorting to cards
     */
    applySort() {
        const visibleCards = this.getVisibleSnippetCards()
        this.sortCards(visibleCards)
    }

    /**
     * Sort array of cards
     */
    sortCards(cards) {
        const { sort } = this.currentFilters

        cards.sort((a, b) => {
            switch (sort) {
                case 'name':
                    const titleA = a.querySelector('.snippet-title')?.textContent || ''
                    const titleB = b.querySelector('.snippet-title')?.textContent || ''
                    return titleA.localeCompare(titleB)

                case 'popular':
                    const viewsA = this.getCardViews(a)
                    const viewsB = this.getCardViews(b)
                    return viewsB - viewsA

                case 'views':
                    const viewCountA = this.getCardViews(a)
                    const viewCountB = this.getCardViews(b)
                    return viewCountB - viewCountA

                case 'recent':
                default:
                    const dateA = this.getCardDate(a)
                    const dateB = this.getCardDate(b)
                    return dateB - dateA
            }
        })

        // Reorder cards in DOM
        cards.forEach(card => {
            this.snippetGridTarget.appendChild(card)
        })
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        // Reset filter controls
        if (this.hasLanguageFilterTarget) {
            this.languageFilterTarget.value = ''
        }
        if (this.hasStatusFilterTarget) {
            this.statusFilterTarget.value = ''
        }
        if (this.hasSearchInputTarget) {
            this.searchInputTarget.value = ''
        }
        if (this.hasSortFilterTarget) {
            this.sortFilterTarget.value = 'recent'
        }

        // Reset filters object
        this.currentFilters = {
            language: '',
            status: '',
            search: '',
            sort: 'recent'
        }

        // Apply changes
        this.applyFilters()
        
        this.trackAction('clear_filters')
    }

    /**
     * Load snippets from server
     */
    async loadSnippets() {
        try {
            this.showLoadingState()

            const response = await fetch('/api/dashboard/snippets', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const data = await response.json()
                this.renderSnippets(data.snippets)
            } else {
                throw new Error("Failed to load snippets")
            }

        } catch (error) {
            console.error("Error loading snippets:", error)
            this.showError("Failed to load snippets")
        } finally {
            this.hideLoadingState()
        }
    }

    /**
     * Refresh dashboard statistics
     */
    async refreshStats() {
        try {
            const response = await fetch('/api/dashboard/stats', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const stats = await response.json()
                this.updateStats(stats)
            } else {
                console.warn("Failed to refresh stats")
            }

        } catch (error) {
            console.error("Error refreshing stats:", error)
        }
    }

    /**
     * Update statistics display
     */
    updateStats(stats) {
        if (this.hasTotalSnippetsTarget) {
            this.animateCounter(this.totalSnippetsTarget, stats.totalSnippets)
        }
        if (this.hasTotalViewsTarget) {
            this.animateCounter(this.totalViewsTarget, stats.totalViews)
        }
        if (this.hasEmailSendsTarget) {
            this.animateCounter(this.emailSendsTarget, stats.emailSends)
        }
        if (this.hasActiveLinksTarget) {
            this.animateCounter(this.activeLinksTarget, stats.activeLinks)
        }
    }

    /**
     * Animate counter updates
     */
    animateCounter(element, newValue) {
        const currentValue = parseInt(element.textContent) || 0
        
        if (currentValue === newValue) return

        const duration = 1000 // 1 second
        const steps = 20
        const stepValue = (newValue - currentValue) / steps
        const stepDuration = duration / steps

        let step = 0
        const timer = setInterval(() => {
            step++
            const value = Math.round(currentValue + (stepValue * step))
            element.textContent = value

            if (step >= steps) {
                clearInterval(timer)
                element.textContent = newValue
            }
        }, stepDuration)
    }

    /**
     * Render snippets list
     */
    renderSnippets(snippets) {
        if (snippets.length === 0) {
            this.showEmptyState()
            return
        }

        // Implementation would depend on whether we're using Turbo frames
        // or rendering client-side. For now, assume snippets are already
        // rendered server-side and we're just managing their display.
        
        this.applyFilters()
    }

    /**
     * Update display state based on results
     */
    updateDisplayState(visibleCount) {
        const hasSnippets = this.getAllSnippetCards().length > 0

        if (!hasSnippets) {
            this.showEmptyState()
        } else if (visibleCount === 0) {
            this.showNoResultsState()
        } else {
            this.showSnippetGrid()
        }
    }

    /**
     * Show empty state
     */
    showEmptyState() {
        this.hideAllStates()
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.remove('hidden')
        }
    }

    /**
     * Show no results state
     */
    showNoResultsState() {
        this.hideAllStates()
        if (this.hasNoResultsStateTarget) {
            this.noResultsStateTarget.classList.remove('hidden')
        }
    }

    /**
     * Show snippet grid
     */
    showSnippetGrid() {
        this.hideAllStates()
        if (this.hasSnippetGridTarget) {
            this.snippetGridTarget.classList.remove('hidden')
        }
    }

    /**
     * Show loading state
     */
    showLoadingState() {
        this.hideAllStates()
        if (this.hasLoadingStateTarget) {
            this.loadingStateTarget.classList.remove('hidden')
        }
    }

    /**
     * Hide loading state
     */
    hideLoadingState() {
        if (this.hasLoadingStateTarget) {
            this.loadingStateTarget.classList.add('hidden')
        }
    }

    /**
     * Hide all display states
     */
    hideAllStates() {
        const states = [
            'emptyStateTarget', 'noResultsStateTarget', 
            'loadingStateTarget', 'snippetGridTarget'
        ]

        states.forEach(state => {
            if (this[`has${state.charAt(0).toUpperCase() + state.slice(1)}`] && 
                this[state]) {
                this[state].classList.add('hidden')
            }
        })
    }

    /**
     * Get all snippet cards
     */
    getAllSnippetCards() {
        return Array.from(this.snippetGridTarget.querySelectorAll('.snippet-card'))
    }

    /**
     * Get visible snippet cards
     */
    getVisibleSnippetCards() {
        return this.getAllSnippetCards().filter(card => 
            card.style.display !== 'none'
        )
    }

    /**
     * Get card view count
     */
    getCardViews(card) {
        const viewsElement = card.querySelector('[title="Views"]')
        if (viewsElement) {
            const text = viewsElement.textContent.replace(/\D/g, '')
            return parseInt(text) || 0
        }
        return 0
    }

    /**
     * Get card date for sorting
     */
    getCardDate(card) {
        const dateElement = card.querySelector('time[datetime]')
        if (dateElement) {
            return new Date(dateElement.getAttribute('datetime'))
        }
        return new Date(0) // Default to epoch
    }

    /**
     * Handle snippet deletion
     */
    async deleteSnippet(snippetId, cardElement) {
        try {
            const response = await fetch(`/api/snippets/${snippetId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                // Animate removal
                cardElement.style.transition = 'all 0.3s ease-out'
                cardElement.style.transform = 'translateX(-100%)'
                cardElement.style.opacity = '0'

                setTimeout(() => {
                    cardElement.remove()
                    this.applyFilters() // Update display state
                    this.refreshStats()
                }, 300)

                this.showSuccess("Snippet deleted successfully")
            } else {
                throw new Error("Failed to delete snippet")
            }

        } catch (error) {
            console.error("Error deleting snippet:", error)
            this.showError("Failed to delete snippet")
        }
    }

    /**
     * Export dashboard data
     */
    async exportData() {
        try {
            const response = await fetch('/api/dashboard/export', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const blob = await response.blob()
                const url = URL.createObjectURL(blob)
                
                const link = document.createElement('a')
                link.href = url
                link.download = `dashboard-export-${new Date().toISOString().split('T')[0]}.csv`
                document.body.appendChild(link)
                link.click()
                document.body.removeChild(link)
                
                URL.revokeObjectURL(url)
                
                this.showSuccess("Dashboard data exported successfully")
            } else {
                throw new Error("Failed to export data")
            }

        } catch (error) {
            console.error("Error exporting data:", error)
            this.showError("Failed to export dashboard data")
        }
    }

    /**
     * Track user interactions for analytics
     */
    trackFilter(type, value) {
        if (window.analytics) {
            window.analytics.track('dashboard_filter', {
                filter_type: type,
                filter_value: value,
                timestamp: new Date().toISOString()
            })
        }
    }

    trackSort(value) {
        if (window.analytics) {
            window.analytics.track('dashboard_sort', {
                sort_value: value,
                timestamp: new Date().toISOString()
            })
        }
    }

    trackAction(action) {
        if (window.analytics) {
            window.analytics.track('dashboard_action', {
                action: action,
                timestamp: new Date().toISOString()
            })
        }
    }

    /**
     * Utility functions
     */
    showSuccess(message) {
        if (window.notificationManager) {
            window.notificationManager.success(message)
        }
    }

    showError(message) {
        if (window.notificationManager) {
            window.notificationManager.error(message)
        } else {
            console.error(message)
        }
    }
}