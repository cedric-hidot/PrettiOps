const { test, expect } = require('@playwright/test');

test.describe('Authentication Flow', () => {
  test.beforeEach(async ({ page }) => {
    // Reset database state if needed
    // This would typically involve API calls to reset test data
  });

  test('User Registration Flow', async ({ page }) => {
    await page.goto('/register');
    
    // Verify registration page loads
    await expect(page).toHaveTitle(/Register.*PrettiOps/);
    await expect(page.locator('h1')).toContainText('Create Account');

    // Fill registration form
    await page.fill('#email', 'test@example.com');
    await page.fill('#username', 'testuser123');
    await page.fill('#firstName', 'John');
    await page.fill('#lastName', 'Doe');
    await page.fill('#password', 'SecurePass123!');
    await page.fill('#passwordConfirm', 'SecurePass123!');
    
    // Accept terms and privacy policy
    await page.check('#terms');
    await page.check('#privacy');

    // Submit form
    await page.click('button[type="submit"]');

    // Should redirect to email verification or dashboard
    await expect(page).toHaveURL(/\/verify-email|\/dashboard/);
    
    // Check for success message
    await expect(page.locator('.alert-success, .notification-success')).toBeVisible();
    await expect(page.locator('.alert-success, .notification-success')).toContainText('Account created successfully');
  });

  test('User Registration with Invalid Data', async ({ page }) => {
    await page.goto('/register');

    // Try to submit with invalid email
    await page.fill('#email', 'invalid-email');
    await page.fill('#password', '123'); // Too weak
    await page.click('button[type="submit"]');

    // Should show validation errors
    await expect(page.locator('.error, .invalid-feedback')).toBeVisible();
    await expect(page.locator('.error, .invalid-feedback')).toContainText(/valid email|password.*strong/i);
  });

  test('User Login Flow', async ({ page }) => {
    // First register a user (or use existing test user)
    await page.goto('/login');
    
    await expect(page).toHaveTitle(/Login.*PrettiOps/);
    await expect(page.locator('h1')).toContainText('Sign In');

    // Fill login form
    await page.fill('#email', 'test@example.com');
    await page.fill('#password', 'SecurePass123!');
    
    // Submit login
    await page.click('button[type="submit"]');

    // Should redirect to dashboard
    await expect(page).toHaveURL('/dashboard');
    
    // Verify user is logged in
    await expect(page.locator('[data-testid="user-menu"]')).toBeVisible();
    await expect(page.locator('[data-testid="user-name"]')).toContainText('John Doe');
  });

  test('Login with Invalid Credentials', async ({ page }) => {
    await page.goto('/login');

    await page.fill('#email', 'nonexistent@example.com');
    await page.fill('#password', 'wrongpassword');
    await page.click('button[type="submit"]');

    // Should show error message
    await expect(page.locator('.alert-error, .notification-error')).toBeVisible();
    await expect(page.locator('.alert-error, .notification-error')).toContainText(/invalid credentials|login failed/i);
    
    // Should stay on login page
    await expect(page).toHaveURL('/login');
  });

  test('Login Rate Limiting', async ({ page }) => {
    await page.goto('/login');

    // Attempt multiple failed logins
    for (let i = 0; i < 6; i++) {
      await page.fill('#email', 'test@example.com');
      await page.fill('#password', 'wrongpassword');
      await page.click('button[type="submit"]');
      await page.waitForTimeout(500);
    }

    // Should show rate limit message
    await expect(page.locator('.alert-warning, .notification-warning')).toBeVisible();
    await expect(page.locator('.alert-warning, .notification-warning')).toContainText(/too many attempts|rate limit/i);
    
    // Login button should be disabled
    await expect(page.locator('button[type="submit"]')).toBeDisabled();
  });

  test('Logout Flow', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('#email', 'test@example.com');
    await page.fill('#password', 'SecurePass123!');
    await page.click('button[type="submit"]');
    
    await expect(page).toHaveURL('/dashboard');

    // Click logout
    await page.click('[data-testid="user-menu"]');
    await page.click('[data-testid="logout-button"]');

    // Should redirect to home/login page
    await expect(page).toHaveURL(/\/|\/login/);
    
    // User menu should not be visible
    await expect(page.locator('[data-testid="user-menu"]')).not.toBeVisible();
  });

  test('OAuth2 Google Login', async ({ page }) => {
    await page.goto('/login');

    // Click Google login button
    await page.click('[data-testid="google-login"]');

    // Should redirect to Google OAuth2 (in test environment, might be mocked)
    await expect(page).toHaveURL(/accounts\.google\.com|mock-oauth/);
    
    // In a real test, you'd complete the OAuth flow
    // For now, we'll just verify the redirect happens
  });

  test('Password Reset Flow', async ({ page }) => {
    await page.goto('/login');
    
    // Click forgot password link
    await page.click('a[href="/forgot-password"]');
    
    await expect(page).toHaveURL('/forgot-password');
    await expect(page.locator('h1')).toContainText('Reset Password');

    // Enter email
    await page.fill('#email', 'test@example.com');
    await page.click('button[type="submit"]');

    // Should show success message
    await expect(page.locator('.alert-success, .notification-success')).toBeVisible();
    await expect(page.locator('.alert-success, .notification-success')).toContainText(/reset link sent|check your email/i);
  });

  test('Email Verification', async ({ page }) => {
    // This would typically involve clicking a link from an email
    // For testing, we might have a test route or mock the verification
    
    await page.goto('/verify-email?token=test-verification-token');
    
    // Should show verification success
    await expect(page.locator('.alert-success, .notification-success')).toBeVisible();
    await expect(page.locator('.alert-success, .notification-success')).toContainText(/email verified|verification successful/i);
    
    // Should redirect to dashboard
    await expect(page).toHaveURL('/dashboard');
  });

  test('Two-Factor Authentication Setup', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('#email', 'test@example.com');
    await page.fill('#password', 'SecurePass123!');
    await page.click('button[type="submit"]');
    
    // Go to security settings
    await page.goto('/settings/security');
    
    // Enable 2FA
    await page.click('[data-testid="enable-2fa"]');
    
    // Should show QR code and backup codes
    await expect(page.locator('[data-testid="qr-code"]')).toBeVisible();
    await expect(page.locator('[data-testid="backup-codes"]')).toBeVisible();
    
    // Enter verification code (in real test, you'd generate this)
    await page.fill('#verification-code', '123456');
    await page.click('[data-testid="verify-2fa"]');
    
    // Should show 2FA enabled message
    await expect(page.locator('.alert-success')).toContainText('Two-factor authentication enabled');
  });

  test('Session Timeout', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('#email', 'test@example.com');
    await page.fill('#password', 'SecurePass123!');
    await page.click('button[type="submit"]');
    
    // Simulate session expiration (this would be configured in test environment)
    await page.evaluate(() => {
      // Simulate expired session by clearing auth tokens
      localStorage.clear();
      sessionStorage.clear();
    });
    
    // Try to access protected page
    await page.goto('/dashboard');
    
    // Should redirect to login
    await expect(page).toHaveURL('/login');
    await expect(page.locator('.alert-warning')).toContainText(/session expired|please login/i);
  });

  test('Account Lockout After Failed Attempts', async ({ page }) => {
    await page.goto('/login');

    // Attempt multiple failed logins beyond rate limit
    for (let i = 0; i < 10; i++) {
      await page.fill('#email', 'test@example.com');
      await page.fill('#password', 'wrongpassword');
      await page.click('button[type="submit"]');
      await page.waitForTimeout(1000);
    }

    // Account should be locked
    await expect(page.locator('.alert-error')).toContainText(/account locked|temporarily suspended/i);
    
    // Even correct password should fail
    await page.fill('#email', 'test@example.com');
    await page.fill('#password', 'SecurePass123!');
    await page.click('button[type="submit"]');
    
    await expect(page.locator('.alert-error')).toContainText(/account locked/i);
  });
});