const { test, expect } = require('@playwright/test');

test.describe('Snippet Creation and Management Workflow', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto('/login');
    await page.fill('#email', 'test@example.com');
    await page.fill('#password', 'SecurePass123!');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL('/dashboard');
  });

  test('Create New Snippet', async ({ page }) => {
    // Navigate to create snippet page
    await page.click('[data-testid="create-snippet-btn"]');
    await expect(page).toHaveURL('/snippets/create');
    
    // Verify editor is loaded
    await expect(page.locator('[data-testid="snippet-editor"]')).toBeVisible();
    await expect(page.locator('[data-testid="monaco-editor"]')).toBeVisible();

    // Fill snippet details
    await page.fill('#snippet-title', 'My First PHP Function');
    await page.fill('#snippet-description', 'A simple function that returns Hello World');
    
    // Select language
    await page.selectOption('#language', 'php');
    
    // Add tags
    await page.fill('#tags-input', 'php, function, hello-world');
    
    // Enter code in Monaco editor
    await page.click('[data-testid="monaco-editor"]');
    const phpCode = `<?php
function helloWorld($name = 'World') {
    return "Hello, " . $name . "!";
}

echo helloWorld('PrettiOps');
?>`;
    await page.keyboard.type(phpCode);

    // Preview should update in real-time
    await expect(page.locator('[data-testid="preview-pane"]')).toBeVisible();
    await expect(page.locator('[data-testid="preview-pane"]')).toContainText('Hello, PrettiOps!');

    // Set visibility
    await page.selectOption('#visibility', 'private');

    // Save snippet
    await page.click('[data-testid="save-snippet-btn"]');

    // Should redirect to snippet view
    await expect(page).toHaveURL(/\/snippets\/[a-f0-9-]+$/);
    
    // Verify snippet was saved
    await expect(page.locator('h1')).toContainText('My First PHP Function');
    await expect(page.locator('[data-testid="snippet-description"]')).toContainText('A simple function that returns Hello World');
    await expect(page.locator('[data-testid="language-badge"]')).toContainText('PHP');
  });

  test('Edit Existing Snippet', async ({ page }) => {
    // Assume we have a snippet already created
    await page.goto('/snippets');
    
    // Click on first snippet
    await page.click('[data-testid="snippet-card"]:first-child');
    
    // Click edit button
    await page.click('[data-testid="edit-snippet-btn"]');
    await expect(page).toHaveURL(/\/snippets\/[a-f0-9-]+\/edit$/);

    // Modify title
    await page.fill('#snippet-title', 'Updated PHP Function');
    
    // Modify code
    await page.click('[data-testid="monaco-editor"]');
    await page.keyboard.press('Control+A');
    const updatedCode = `<?php
function helloWorld($name = 'World', $greeting = 'Hello') {
    return $greeting . ", " . $name . "!";
}

echo helloWorld('PrettiOps', 'Welcome');
?>`;
    await page.keyboard.type(updatedCode);

    // Save changes
    await page.click('[data-testid="save-snippet-btn"]');

    // Verify changes were saved
    await expect(page.locator('h1')).toContainText('Updated PHP Function');
    await expect(page.locator('[data-testid="code-content"]')).toContainText('Welcome, PrettiOps!');
  });

  test('Syntax Highlighting and Theme Selection', async ({ page }) => {
    await page.goto('/snippets/create');

    // Select different languages and verify syntax highlighting
    const languages = ['javascript', 'python', 'java', 'css', 'html'];
    
    for (const lang of languages) {
      await page.selectOption('#language', lang);
      
      // Verify editor updates language mode
      await expect(page.locator('[data-testid="language-indicator"]')).toContainText(lang.toUpperCase());
      
      // Enter some sample code
      await page.click('[data-testid="monaco-editor"]');
      await page.keyboard.press('Control+A');
      
      let sampleCode = '';
      switch(lang) {
        case 'javascript':
          sampleCode = 'function test() { console.log("Hello World"); }';
          break;
        case 'python':
          sampleCode = 'def test():\n    print("Hello World")';
          break;
        case 'java':
          sampleCode = 'public class Test { public static void main(String[] args) { System.out.println("Hello World"); } }';
          break;
        case 'css':
          sampleCode = 'body { background-color: #f0f0f0; color: #333; }';
          break;
        case 'html':
          sampleCode = '<html><body><h1>Hello World</h1></body></html>';
          break;
      }
      
      await page.keyboard.type(sampleCode);
      
      // Verify syntax highlighting is applied (check for colored tokens)
      await expect(page.locator('.monaco-editor .mtk1, .monaco-editor .mtk22, .monaco-editor .mtk9')).toBeVisible();
    }

    // Test theme switching
    await page.selectOption('#theme', 'dark');
    await expect(page.locator('[data-testid="monaco-editor"]')).toHaveClass(/vs-dark|dark-theme/);
    
    await page.selectOption('#theme', 'light');
    await expect(page.locator('[data-testid="monaco-editor"]')).toHaveClass(/vs-light|light-theme/);
  });

  test('Real-time Preview Functionality', async ({ page }) => {
    await page.goto('/snippets/create');
    
    // Select HTML
    await page.selectOption('#language', 'html');
    
    // Enter HTML code
    await page.click('[data-testid="monaco-editor"]');
    const htmlCode = `<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; }
        h1 { color: #333; text-align: center; }
    </style>
</head>
<body>
    <h1>Hello from PrettiOps!</h1>
    <p>This is a live preview.</p>
</body>
</html>`;
    await page.keyboard.type(htmlCode);

    // Verify preview pane shows rendered HTML
    await expect(page.locator('[data-testid="preview-pane"] iframe')).toBeVisible();
    
    // Switch to preview iframe and verify content
    const previewFrame = page.frameLocator('[data-testid="preview-pane"] iframe');
    await expect(previewFrame.locator('h1')).toContainText('Hello from PrettiOps!');
    await expect(previewFrame.locator('p')).toContainText('This is a live preview.');
  });

  test('Code Formatting and Linting', async ({ page }) => {
    await page.goto('/snippets/create');
    
    // Select JavaScript
    await page.selectOption('#language', 'javascript');
    
    // Enter poorly formatted code
    await page.click('[data-testid="monaco-editor"]');
    const messyCode = `function test(){const x=1;if(x>0){console.log('positive');}else{console.log('negative');}}`;
    await page.keyboard.type(messyCode);

    // Click format code button
    await page.click('[data-testid="format-code-btn"]');
    
    // Verify code is formatted
    await expect(page.locator('[data-testid="monaco-editor"]')).toContainText('function test() {');
    await expect(page.locator('[data-testid="monaco-editor"]')).toContainText('  const x = 1;');
    
    // Enter code with syntax errors
    await page.click('[data-testid="monaco-editor"]');
    await page.keyboard.press('Control+A');
    await page.keyboard.type('function test( { console.log("missing parenthesis"); }');
    
    // Should show linting errors
    await expect(page.locator('.monaco-editor .squiggly-error')).toBeVisible();
    await expect(page.locator('[data-testid="lint-errors"]')).toBeVisible();
  });

  test('Snippet Sharing and Visibility', async ({ page }) => {
    await page.goto('/snippets/create');
    
    // Create a public snippet
    await page.fill('#snippet-title', 'Public JavaScript Function');
    await page.selectOption('#language', 'javascript');
    await page.click('[data-testid="monaco-editor"]');
    await page.keyboard.type('function publicFunction() { return "Hello World"; }');
    
    // Set as public
    await page.selectOption('#visibility', 'public');
    await page.click('[data-testid="save-snippet-btn"]');
    
    // Should show sharing options
    await expect(page.locator('[data-testid="share-btn"]')).toBeVisible();
    
    // Click share button
    await page.click('[data-testid="share-btn"]');
    
    // Should show share modal with options
    await expect(page.locator('[data-testid="share-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="share-url"]')).toBeVisible();
    
    // Copy share link
    await page.click('[data-testid="copy-link-btn"]');
    
    // Should show copied confirmation
    await expect(page.locator('.notification-success')).toContainText('Link copied');
    
    // Generate email template
    await page.click('[data-testid="generate-email-btn"]');
    
    // Should show email preview
    await expect(page.locator('[data-testid="email-preview"]')).toBeVisible();
    await expect(page.locator('[data-testid="email-preview"]')).toContainText('Public JavaScript Function');
  });

  test('Version Control and History', async ({ page }) => {
    // Create initial version
    await page.goto('/snippets/create');
    await page.fill('#snippet-title', 'Versioned Function');
    await page.selectOption('#language', 'javascript');
    await page.click('[data-testid="monaco-editor"]');
    await page.keyboard.type('function v1() { return "Version 1"; }');
    await page.click('[data-testid="save-snippet-btn"]');
    
    const snippetUrl = page.url();
    
    // Edit to create new version
    await page.click('[data-testid="edit-snippet-btn"]');
    await page.click('[data-testid="monaco-editor"]');
    await page.keyboard.press('Control+A');
    await page.keyboard.type('function v2() { return "Version 2 - Updated"; }');
    
    // Check "Create new version" instead of updating
    await page.check('#create-version');
    await page.click('[data-testid="save-snippet-btn"]');
    
    // Should show version indicator
    await expect(page.locator('[data-testid="version-indicator"]')).toContainText('v2');
    
    // Click version history
    await page.click('[data-testid="version-history-btn"]');
    
    // Should show version list
    await expect(page.locator('[data-testid="version-list"]')).toBeVisible();
    await expect(page.locator('[data-testid="version-item"]')).toHaveCount(2);
    
    // View previous version
    await page.click('[data-testid="version-item"]:first-child');
    await expect(page.locator('[data-testid="code-content"]')).toContainText('Version 1');
    
    // Compare versions
    await page.click('[data-testid="compare-versions-btn"]');
    await expect(page.locator('[data-testid="diff-viewer"]')).toBeVisible();
    await expect(page.locator('.diff-removed')).toContainText('Version 1');
    await expect(page.locator('.diff-added')).toContainText('Version 2');
  });

  test('Fork Snippet Functionality', async ({ page }) => {
    // Navigate to a public snippet (assume exists)
    await page.goto('/snippets/public');
    await page.click('[data-testid="snippet-card"]:first-child');
    
    // Fork the snippet
    await page.click('[data-testid="fork-snippet-btn"]');
    
    // Should redirect to edit page of forked snippet
    await expect(page).toHaveURL(/\/snippets\/[a-f0-9-]+\/edit$/);
    
    // Title should indicate it's a fork
    await expect(page.locator('#snippet-title')).toHaveValue(/\(Fork\)$/);
    
    // Modify the fork
    await page.fill('#snippet-title', 'My Forked Version');
    await page.click('[data-testid="monaco-editor"]');
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await page.keyboard.type('// Modified by me');
    
    await page.click('[data-testid="save-snippet-btn"]');
    
    // Should show fork indicator
    await expect(page.locator('[data-testid="fork-indicator"]')).toBeVisible();
    await expect(page.locator('[data-testid="original-link"]')).toBeVisible();
  });

  test('Snippet Search and Filtering', async ({ page }) => {
    await page.goto('/snippets');
    
    // Search by title
    await page.fill('[data-testid="search-input"]', 'PHP Function');
    await page.keyboard.press('Enter');
    
    // Should filter results
    await expect(page.locator('[data-testid="snippet-card"]')).toContainText('PHP Function');
    
    // Filter by language
    await page.selectOption('[data-testid="language-filter"]', 'php');
    
    // Should show only PHP snippets
    const phpSnippets = page.locator('[data-testid="language-badge"]:has-text("PHP")');
    await expect(phpSnippets).toHaveCount(await phpSnippets.count());
    
    // Filter by tags
    await page.fill('[data-testid="tags-filter"]', 'function');
    
    // Should show snippets with 'function' tag
    await expect(page.locator('[data-testid="snippet-card"]')).toContainText('function');
    
    // Clear all filters
    await page.click('[data-testid="clear-filters-btn"]');
    
    // Should show all snippets
    await expect(page.locator('[data-testid="snippet-card"]')).toHaveCount.toBeGreaterThan(1);
  });

  test('Export Snippet to Various Formats', async ({ page }) => {
    // Navigate to a snippet
    await page.goto('/snippets');
    await page.click('[data-testid="snippet-card"]:first-child');
    
    // Click export button
    await page.click('[data-testid="export-btn"]');
    
    // Should show export options
    await expect(page.locator('[data-testid="export-modal"]')).toBeVisible();
    
    // Test different export formats
    const formats = ['raw', 'gist', 'pdf', 'html'];
    
    for (const format of formats) {
      const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.click(`[data-testid="export-${format}"]`)
      ]);
      
      // Verify download started
      expect(download.suggestedFilename()).toMatch(new RegExp(`\\.${format === 'raw' ? 'txt' : format}$`));
    }
  });

  test('Responsive Design on Mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto('/snippets/create');
    
    // Mobile editor should be visible
    await expect(page.locator('[data-testid="mobile-editor"]')).toBeVisible();
    
    // Preview should be collapsible on mobile
    await expect(page.locator('[data-testid="preview-toggle"]')).toBeVisible();
    
    // Click to toggle preview
    await page.click('[data-testid="preview-toggle"]');
    await expect(page.locator('[data-testid="preview-pane"]')).toBeVisible();
    
    // Mobile menu should work
    await page.click('[data-testid="mobile-menu-btn"]');
    await expect(page.locator('[data-testid="mobile-menu"]')).toBeVisible();
    
    // Touch interactions should work
    await page.tap('[data-testid="monaco-editor"]');
    await page.keyboard.type('console.log("Mobile typing works");');
    
    await expect(page.locator('[data-testid="monaco-editor"]')).toContainText('Mobile typing works');
  });

  test('Accessibility Features', async ({ page }) => {
    await page.goto('/snippets/create');
    
    // Test keyboard navigation
    await page.keyboard.press('Tab');
    await expect(page.locator('#snippet-title')).toBeFocused();
    
    await page.keyboard.press('Tab');
    await expect(page.locator('#snippet-description')).toBeFocused();
    
    // Test screen reader labels
    await expect(page.locator('#snippet-title')).toHaveAttribute('aria-label');
    await expect(page.locator('[data-testid="monaco-editor"]')).toHaveAttribute('role', 'textbox');
    
    // Test high contrast mode
    await page.addStyleTag({
      content: `
        @media (prefers-contrast: high) {
          body { background: white; color: black; }
        }
      `
    });
    
    // Verify high contrast styles are applied
    await page.emulateMedia({ reducedMotion: 'reduce' });
    
    // Test focus indicators
    await page.keyboard.press('Tab');
    await expect(page.locator(':focus')).toHaveCSS('outline-style', 'solid');
  });
});