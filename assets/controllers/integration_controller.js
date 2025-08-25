import { Controller } from "@hotwired/stimulus"

/**
 * Integration Controller
 * 
 * Handles integrations with external services including GitHub, Jira, and IDE exports.
 * Provides seamless workflow integration for developers to export snippets to
 * their preferred development environments and project management tools.
 */
export default class extends Controller {
    static targets = [
        "githubConnect", "githubRepos", "githubExport",
        "jiraConnect", "jiraProjects", "jiraIssues", "jiraExport",
        "ideExport", "ideSelector", "exportFormat",
        "integrationsList", "connectionStatus"
    ]
    
    static values = {
        snippetId: String,
        githubConnected: Boolean,
        jiraConnected: Boolean,
        availableIntegrations: Array,
        githubToken: String,
        jiraToken: String
    }

    static classes = [
        "connecting", "connected", "exporting", "error", "success"
    ]

    connect() {
        console.log("Integration controller connected")
        this.loadIntegrationStatus()
        this.setupEventListeners()
        
        // Global reference
        window.integrationController = this
    }

    disconnect() {
        window.integrationController = null
    }

    /**
     * Load integration status and connected services
     */
    async loadIntegrationStatus() {
        try {
            const response = await fetch('/api/integrations/status', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const status = await response.json()
                this.updateConnectionStatus(status)
                
                if (status.github?.connected) {
                    this.githubConnectedValue = true
                    await this.loadGitHubRepos()
                }
                
                if (status.jira?.connected) {
                    this.jiraConnectedValue = true
                    await this.loadJiraProjects()
                }
            }

        } catch (error) {
            console.error("Failed to load integration status:", error)
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for export events from other controllers
        document.addEventListener('snippet:export-requested', (e) => {
            this.handleExportRequest(e.detail)
        })
    }

    /**
     * GitHub Integration
     */
    async connectGitHub(event) {
        if (event) event.preventDefault()
        
        try {
            this.setConnectingState('github', true)
            
            // Initiate OAuth flow
            const response = await fetch('/api/integrations/github/authorize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const data = await response.json()
                
                // Redirect to GitHub OAuth
                window.location.href = data.authUrl
            } else {
                throw new Error('Failed to initiate GitHub connection')
            }

        } catch (error) {
            console.error("GitHub connection failed:", error)
            this.showError("Failed to connect to GitHub")
        } finally {
            this.setConnectingState('github', false)
        }
    }

    async disconnectGitHub(event) {
        if (event) event.preventDefault()
        
        if (!confirm('Disconnect GitHub integration?')) return

        try {
            const response = await fetch('/api/integrations/github/disconnect', {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                this.githubConnectedValue = false
                this.updateGitHubUI(false)
                this.showSuccess("GitHub disconnected successfully")
            } else {
                throw new Error('Failed to disconnect GitHub')
            }

        } catch (error) {
            console.error("GitHub disconnection failed:", error)
            this.showError("Failed to disconnect GitHub")
        }
    }

    async loadGitHubRepos() {
        if (!this.hasGithubReposTarget) return

        try {
            const response = await fetch('/api/integrations/github/repos', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const repos = await response.json()
                this.renderGitHubRepos(repos)
            }

        } catch (error) {
            console.error("Failed to load GitHub repos:", error)
        }
    }

    async exportToGitHub(repoId, branch = 'main', path = '') {
        try {
            this.setExportingState('github', true)
            
            const snippetData = this.getSnippetData()
            
            const response = await fetch('/api/integrations/github/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    snippetId: this.snippetIdValue,
                    repoId: repoId,
                    branch: branch,
                    path: path,
                    filename: this.generateFilename(snippetData),
                    content: snippetData.content,
                    commitMessage: `Add snippet: ${snippetData.title}`
                })
            })

            if (response.ok) {
                const result = await response.json()
                this.showSuccess(`Successfully exported to GitHub: ${result.htmlUrl}`)
                
                // Track export
                this.trackExport('github', repoId)
                
                return result
            } else {
                const error = await response.json()
                throw new Error(error.message || 'Export failed')
            }

        } catch (error) {
            console.error("GitHub export failed:", error)
            this.showError(error.message || "Failed to export to GitHub")
        } finally {
            this.setExportingState('github', false)
        }
    }

    /**
     * Jira Integration
     */
    async connectJira(event) {
        if (event) event.preventDefault()
        
        try {
            this.setConnectingState('jira', true)
            
            // Show Jira connection modal
            const modal = this.createJiraConnectionModal()
            document.body.appendChild(modal)

        } catch (error) {
            console.error("Jira connection failed:", error)
            this.showError("Failed to connect to Jira")
        } finally {
            this.setConnectingState('jira', false)
        }
    }

    async submitJiraConnection(serverUrl, email, apiToken) {
        try {
            const response = await fetch('/api/integrations/jira/connect', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    serverUrl,
                    email,
                    apiToken
                })
            })

            if (response.ok) {
                const result = await response.json()
                this.jiraConnectedValue = true
                this.updateJiraUI(true)
                await this.loadJiraProjects()
                this.showSuccess("Jira connected successfully")
                
                return result
            } else {
                const error = await response.json()
                throw new Error(error.message || 'Connection failed')
            }

        } catch (error) {
            console.error("Jira connection failed:", error)
            throw error
        }
    }

    async disconnectJira(event) {
        if (event) event.preventDefault()
        
        if (!confirm('Disconnect Jira integration?')) return

        try {
            const response = await fetch('/api/integrations/jira/disconnect', {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                this.jiraConnectedValue = false
                this.updateJiraUI(false)
                this.showSuccess("Jira disconnected successfully")
            } else {
                throw new Error('Failed to disconnect Jira')
            }

        } catch (error) {
            console.error("Jira disconnection failed:", error)
            this.showError("Failed to disconnect Jira")
        }
    }

    async loadJiraProjects() {
        if (!this.hasJiraProjectsTarget) return

        try {
            const response = await fetch('/api/integrations/jira/projects', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const projects = await response.json()
                this.renderJiraProjects(projects)
            }

        } catch (error) {
            console.error("Failed to load Jira projects:", error)
        }
    }

    async loadJiraIssues(projectKey) {
        if (!this.hasJiraIssuesTarget) return

        try {
            const response = await fetch(`/api/integrations/jira/projects/${projectKey}/issues`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const issues = await response.json()
                this.renderJiraIssues(issues)
            }

        } catch (error) {
            console.error("Failed to load Jira issues:", error)
        }
    }

    async exportToJira(issueKey, attachmentType = 'comment') {
        try {
            this.setExportingState('jira', true)
            
            const snippetData = this.getSnippetData()
            
            const response = await fetch('/api/integrations/jira/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    snippetId: this.snippetIdValue,
                    issueKey: issueKey,
                    attachmentType: attachmentType,
                    content: this.formatJiraContent(snippetData)
                })
            })

            if (response.ok) {
                const result = await response.json()
                this.showSuccess(`Successfully exported to Jira issue: ${issueKey}`)
                
                this.trackExport('jira', issueKey)
                
                return result
            } else {
                const error = await response.json()
                throw new Error(error.message || 'Export failed')
            }

        } catch (error) {
            console.error("Jira export failed:", error)
            this.showError(error.message || "Failed to export to Jira")
        } finally {
            this.setExportingState('jira', false)
        }
    }

    /**
     * IDE Export Integration
     */
    async exportToIDE(ide, format = 'file') {
        try {
            this.setExportingState('ide', true)
            
            const snippetData = this.getSnippetData()
            const exportData = this.formatIDEExport(snippetData, ide, format)
            
            switch (ide) {
                case 'vscode':
                    await this.exportToVSCode(exportData)
                    break
                case 'webstorm':
                    await this.exportToWebStorm(exportData)
                    break
                case 'sublime':
                    await this.exportToSublime(exportData)
                    break
                case 'atom':
                    await this.exportToAtom(exportData)
                    break
                default:
                    await this.exportAsFile(exportData)
            }
            
            this.showSuccess(`Snippet exported for ${ide}`)
            this.trackExport('ide', ide)

        } catch (error) {
            console.error("IDE export failed:", error)
            this.showError(error.message || "Failed to export to IDE")
        } finally {
            this.setExportingState('ide', false)
        }
    }

    async exportToVSCode(exportData) {
        // Generate VS Code snippet format
        const vsCodeSnippet = {
            [exportData.title]: {
                prefix: exportData.prefix || exportData.title.toLowerCase().replace(/\s+/g, '-'),
                body: exportData.content.split('\n'),
                description: exportData.description || ''
            }
        }
        
        const blob = new Blob([JSON.stringify(vsCodeSnippet, null, 2)], { type: 'application/json' })
        this.downloadFile(blob, `${exportData.title}.code-snippets`)
    }

    async exportToWebStorm(exportData) {
        // Generate WebStorm live template format
        const webstormTemplate = `
<template name="${exportData.title}" value="${this.escapeXml(exportData.content)}" description="${exportData.description || ''}" toReformat="true" toShortenFQNames="true">
  <context>
    <option name="OTHER" value="true" />
  </context>
</template>
        `.trim()
        
        const blob = new Blob([webstormTemplate], { type: 'application/xml' })
        this.downloadFile(blob, `${exportData.title}.xml`)
    }

    async exportToSublime(exportData) {
        // Generate Sublime Text snippet format
        const sublimeSnippet = `
<snippet>
    <content><![CDATA[${exportData.content}]]></content>
    <tabTrigger>${exportData.prefix || exportData.title.toLowerCase().replace(/\s+/g, '-')}</tabTrigger>
    <description>${exportData.description || ''}</description>
</snippet>
        `.trim()
        
        const blob = new Blob([sublimeSnippet], { type: 'application/xml' })
        this.downloadFile(blob, `${exportData.title}.sublime-snippet`)
    }

    async exportAsFile(exportData) {
        const extension = this.getFileExtension(exportData.language)
        const blob = new Blob([exportData.content], { type: 'text/plain' })
        this.downloadFile(blob, `${exportData.title}${extension}`)
    }

    /**
     * Rendering functions
     */
    renderGitHubRepos(repos) {
        if (!this.hasGithubReposTarget) return

        this.githubReposTarget.innerHTML = `
            <div class="repo-selector">
                <label for="github-repo-select" class="form-label">Select Repository</label>
                <select id="github-repo-select" class="form-select">
                    <option value="">Choose a repository...</option>
                    ${repos.map(repo => 
                        `<option value="${repo.id}" data-full-name="${repo.full_name}">${repo.full_name}</option>`
                    ).join('')}
                </select>
            </div>
            <div class="export-options">
                <div class="form-group">
                    <label for="github-branch" class="form-label">Branch</label>
                    <input type="text" id="github-branch" class="form-input" value="main" placeholder="main">
                </div>
                <div class="form-group">
                    <label for="github-path" class="form-label">Path (optional)</label>
                    <input type="text" id="github-path" class="form-input" placeholder="snippets/">
                </div>
                <button class="btn btn-primary" data-action="click->integration#handleGitHubExport">
                    Export to GitHub
                </button>
            </div>
        `
    }

    renderJiraProjects(projects) {
        if (!this.hasJiraProjectsTarget) return

        this.jiraProjectsTarget.innerHTML = `
            <div class="project-selector">
                <label for="jira-project-select" class="form-label">Select Project</label>
                <select id="jira-project-select" class="form-select" data-action="change->integration#handleJiraProjectChange">
                    <option value="">Choose a project...</option>
                    ${projects.map(project => 
                        `<option value="${project.key}" data-id="${project.id}">${project.name} (${project.key})</option>`
                    ).join('')}
                </select>
            </div>
        `
    }

    renderJiraIssues(issues) {
        if (!this.hasJiraIssuesTarget) return

        this.jiraIssuesTarget.innerHTML = `
            <div class="issue-selector">
                <label for="jira-issue-select" class="form-label">Select Issue</label>
                <select id="jira-issue-select" class="form-select">
                    <option value="">Choose an issue...</option>
                    ${issues.map(issue => 
                        `<option value="${issue.key}">${issue.key}: ${issue.summary}</option>`
                    ).join('')}
                </select>
            </div>
            <div class="export-options">
                <div class="form-group">
                    <label class="form-label">Attachment Type</label>
                    <div class="form-radio-group">
                        <label class="form-radio">
                            <input type="radio" name="jira-attachment-type" value="comment" checked>
                            Comment
                        </label>
                        <label class="form-radio">
                            <input type="radio" name="jira-attachment-type" value="attachment">
                            File Attachment
                        </label>
                    </div>
                </div>
                <button class="btn btn-primary" data-action="click->integration#handleJiraExport">
                    Export to Jira
                </button>
            </div>
        `
    }

    /**
     * Event handlers
     */
    handleGitHubExport(event) {
        event.preventDefault()
        
        const repoSelect = document.getElementById('github-repo-select')
        const branchInput = document.getElementById('github-branch')
        const pathInput = document.getElementById('github-path')
        
        if (!repoSelect.value) {
            this.showError("Please select a repository")
            return
        }
        
        this.exportToGitHub(
            repoSelect.value,
            branchInput.value || 'main',
            pathInput.value || ''
        )
    }

    handleJiraProjectChange(event) {
        const projectKey = event.target.value
        if (projectKey) {
            this.loadJiraIssues(projectKey)
        }
    }

    handleJiraExport(event) {
        event.preventDefault()
        
        const issueSelect = document.getElementById('jira-issue-select')
        const attachmentType = document.querySelector('input[name="jira-attachment-type"]:checked')
        
        if (!issueSelect.value) {
            this.showError("Please select an issue")
            return
        }
        
        this.exportToJira(
            issueSelect.value,
            attachmentType ? attachmentType.value : 'comment'
        )
    }

    handleExportRequest(detail) {
        const { type, target, options } = detail
        
        switch (type) {
            case 'github':
                this.exportToGitHub(target, options?.branch, options?.path)
                break
            case 'jira':
                this.exportToJira(target, options?.attachmentType)
                break
            case 'ide':
                this.exportToIDE(target, options?.format)
                break
        }
    }

    /**
     * Helper functions
     */
    getSnippetData() {
        return {
            title: window.snippetController?.titleTarget?.value || 'Untitled Snippet',
            description: window.snippetController?.descriptionTarget?.value || '',
            content: window.monacoController?.getContent() || '',
            language: window.monacoController?.languageValue || 'javascript',
            tags: window.snippetController?.tagsTarget?.value?.split(',').map(t => t.trim()) || []
        }
    }

    generateFilename(snippetData) {
        const title = snippetData.title.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-')
        const extension = this.getFileExtension(snippetData.language)
        return `${title}${extension}`
    }

    getFileExtension(language) {
        const extensions = {
            javascript: '.js',
            typescript: '.ts',
            python: '.py',
            java: '.java',
            csharp: '.cs',
            cpp: '.cpp',
            c: '.c',
            php: '.php',
            ruby: '.rb',
            go: '.go',
            rust: '.rs',
            swift: '.swift',
            kotlin: '.kt',
            scala: '.scala',
            html: '.html',
            css: '.css',
            scss: '.scss',
            json: '.json',
            xml: '.xml',
            yaml: '.yml',
            markdown: '.md',
            sql: '.sql',
            bash: '.sh',
            powershell: '.ps1'
        }
        
        return extensions[language] || '.txt'
    }

    formatIDEExport(snippetData, ide, format) {
        return {
            title: snippetData.title,
            description: snippetData.description,
            content: snippetData.content,
            language: snippetData.language,
            prefix: snippetData.title.toLowerCase().replace(/\s+/g, '-'),
            tags: snippetData.tags
        }
    }

    formatJiraContent(snippetData) {
        return `
h3. ${snippetData.title}

${snippetData.description ? `${snippetData.description}\n` : ''}

{code:${snippetData.language}}
${snippetData.content}
{code}

_Exported from PrettiOps_
        `.trim()
    }

    createJiraConnectionModal() {
        const modal = document.createElement('div')
        modal.className = 'modal-overlay'
        modal.innerHTML = `
            <div class="modal modal-md" role="dialog">
                <div class="modal-header">
                    <h3 class="modal-title">Connect to Jira</h3>
                    <button class="modal-close" data-action="click->integration#closeJiraModal">×</button>
                </div>
                <div class="modal-body">
                    <form class="jira-connection-form" data-action="submit->integration#submitJiraConnectionForm">
                        <div class="form-group">
                            <label for="jira-server-url" class="form-label">Server URL</label>
                            <input type="url" id="jira-server-url" class="form-input" placeholder="https://yourcompany.atlassian.net" required>
                            <div class="form-help">Your Jira server URL (Cloud or Server)</div>
                        </div>
                        <div class="form-group">
                            <label for="jira-email" class="form-label">Email</label>
                            <input type="email" id="jira-email" class="form-input" placeholder="your-email@company.com" required>
                        </div>
                        <div class="form-group">
                            <label for="jira-api-token" class="form-label">API Token</label>
                            <input type="password" id="jira-api-token" class="form-input" placeholder="API Token" required>
                            <div class="form-help">
                                <a href="https://id.atlassian.com/manage-profile/security/api-tokens" target="_blank">Create an API token</a>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-action="click->integration#closeJiraModal">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Connect
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `
        
        return modal
    }

    async submitJiraConnectionForm(event) {
        event.preventDefault()
        
        const formData = new FormData(event.target)
        const serverUrl = document.getElementById('jira-server-url').value
        const email = document.getElementById('jira-email').value
        const apiToken = document.getElementById('jira-api-token').value
        
        try {
            await this.submitJiraConnection(serverUrl, email, apiToken)
            this.closeJiraModal()
        } catch (error) {
            this.showError(error.message)
        }
    }

    closeJiraModal() {
        const modal = document.querySelector('.modal-overlay')
        if (modal) {
            modal.remove()
        }
    }

    downloadFile(blob, filename) {
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = filename
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
    }

    escapeXml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
    }

    /**
     * UI state management
     */
    updateConnectionStatus(status) {
        if (this.hasConnectionStatusTarget) {
            this.connectionStatusTarget.innerHTML = this.renderConnectionStatus(status)
        }
    }

    renderConnectionStatus(status) {
        return `
            <div class="connection-status">
                <div class="connection-item ${status.github?.connected ? 'connected' : 'disconnected'}">
                    <span class="connection-icon">📂</span>
                    <span class="connection-name">GitHub</span>
                    <span class="connection-badge">${status.github?.connected ? 'Connected' : 'Disconnected'}</span>
                </div>
                <div class="connection-item ${status.jira?.connected ? 'connected' : 'disconnected'}">
                    <span class="connection-icon">📋</span>
                    <span class="connection-name">Jira</span>
                    <span class="connection-badge">${status.jira?.connected ? 'Connected' : 'Disconnected'}</span>
                </div>
            </div>
        `
    }

    updateGitHubUI(connected) {
        this.element.classList.toggle('github-connected', connected)
        
        if (this.hasGithubConnectTarget) {
            this.githubConnectTarget.textContent = connected ? 'Disconnect GitHub' : 'Connect GitHub'
        }
    }

    updateJiraUI(connected) {
        this.element.classList.toggle('jira-connected', connected)
        
        if (this.hasJiraConnectTarget) {
            this.jiraConnectTarget.textContent = connected ? 'Disconnect Jira' : 'Connect Jira'
        }
    }

    setConnectingState(service, connecting) {
        this.element.classList.toggle(`${service}-connecting`, connecting)
    }

    setExportingState(service, exporting) {
        this.element.classList.toggle(`${service}-exporting`, exporting)
    }

    /**
     * Tracking functions
     */
    trackExport(service, target) {
        if (window.analytics) {
            window.analytics.track('snippet_exported', {
                service: service,
                target: target,
                snippet_id: this.snippetIdValue,
                timestamp: new Date().toISOString()
            })
        }
    }

    /**
     * Notification helpers
     */
    showSuccess(message) {
        if (window.notificationManager) {
            window.notificationManager.success(message)
        }
    }

    showError(message) {
        if (window.notificationManager) {
            window.notificationManager.error(message)
        }
    }
}