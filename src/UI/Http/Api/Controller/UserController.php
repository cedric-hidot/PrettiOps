<?php

namespace App\UI\Http\Api\Controller;

use App\Domain\User\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Repository\UserRepository;
use App\Infrastructure\Security\SecurityService;
use App\Infrastructure\Security\RgpdComplianceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API User Controller
 * Handles user profile management, preferences, and account settings
 */
#[Route('/api/user', name: 'api_user_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly SecurityService $securityService,
        private readonly RgpdComplianceService $rgpdService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get user profile
     */
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'user' => $this->serializeUser($user, true)
        ]);
    }

    /**
     * Update user profile
     */
    #[Route('/profile', name: 'update_profile', methods: ['PUT', 'PATCH'])]
    public function updateProfile(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $updated = false;

            // Update basic profile fields
            if (isset($data['first_name'])) {
                $firstName = $this->securityService->sanitizeInput($data['first_name']);
                if ($firstName !== $user->getFirstName()) {
                    $user->setFirstName($firstName);
                    $updated = true;
                }
            }

            if (isset($data['last_name'])) {
                $lastName = $this->securityService->sanitizeInput($data['last_name']);
                if ($lastName !== $user->getLastName()) {
                    $user->setLastName($lastName);
                    $updated = true;
                }
            }

            if (isset($data['username'])) {
                $username = $this->securityService->sanitizeInput($data['username']);
                
                // Validate username format
                if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
                    return $this->json(['error' => 'Username must be 3-50 characters and contain only letters, numbers, underscores, and hyphens'], Response::HTTP_BAD_REQUEST);
                }

                // Check if username is available
                if ($username !== $user->getUsernameField()) {
                    $existingUser = $this->userRepository->findOneBy(['username' => $username]);
                    if ($existingUser) {
                        return $this->json(['error' => 'Username is already taken'], Response::HTTP_CONFLICT);
                    }
                    
                    $user->setUsername($username);
                    $updated = true;
                }
            }

            if (isset($data['avatar_url'])) {
                $avatarUrl = $this->securityService->sanitizeInput($data['avatar_url']);
                if (filter_var($avatarUrl, FILTER_VALIDATE_URL) || empty($avatarUrl)) {
                    $user->setAvatarUrl($avatarUrl ?: null);
                    $updated = true;
                } else {
                    return $this->json(['error' => 'Invalid avatar URL'], Response::HTTP_BAD_REQUEST);
                }
            }

            if (isset($data['timezone'])) {
                $timezone = $data['timezone'];
                try {
                    new \DateTimeZone($timezone);
                    $user->setTimezone($timezone);
                    $updated = true;
                } catch (\Exception) {
                    return $this->json(['error' => 'Invalid timezone'], Response::HTTP_BAD_REQUEST);
                }
            }

            if (isset($data['locale'])) {
                $locale = $data['locale'];
                $supportedLocales = ['en', 'fr', 'es', 'de', 'it', 'pt', 'ja', 'zh'];
                if (in_array($locale, $supportedLocales, true)) {
                    $user->setLocale($locale);
                    $updated = true;
                } else {
                    return $this->json(['error' => 'Unsupported locale'], Response::HTTP_BAD_REQUEST);
                }
            }

            // Validate updated user
            if ($updated) {
                $errors = $this->validator->validate($user);
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getMessage();
                    }
                    return $this->json(['error' => 'Validation failed', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
                }

                $this->entityManager->flush();

                $this->logger->info('User profile updated', [
                    'user_id' => $user->getId(),
                    'updated_fields' => array_keys($data)
                ]);
            }

            return $this->json([
                'message' => $updated ? 'Profile updated successfully' : 'No changes detected',
                'user' => $this->serializeUser($user, true)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update profile', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to update profile'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Change user password
     */
    #[Route('/password', name: 'change_password', methods: ['PUT'])]
    public function changePassword(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || empty($data['current_password']) || empty($data['new_password'])) {
                return $this->json(['error' => 'Current password and new password are required'], Response::HTTP_BAD_REQUEST);
            }

            // Verify current password
            if (!$this->securityService->verifyPassword($user, $data['current_password'])) {
                return $this->json(['error' => 'Current password is incorrect'], Response::HTTP_BAD_REQUEST);
            }

            // Validate new password strength
            $passwordValidation = $this->securityService->validatePasswordStrength($data['new_password']);
            if (!$passwordValidation['valid']) {
                return $this->json([
                    'error' => 'New password does not meet security requirements',
                    'details' => $passwordValidation['errors']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if new password is different from current
            if ($this->securityService->verifyPassword($user, $data['new_password'])) {
                return $this->json(['error' => 'New password must be different from current password'], Response::HTTP_BAD_REQUEST);
            }

            // Update password
            $hashedPassword = $this->securityService->hashPassword($user, $data['new_password']);
            $user->setPassword($hashedPassword);
            $this->entityManager->flush();

            $this->logger->info('User password changed', [
                'user_id' => $user->getId(),
                'password_strength' => $passwordValidation['strength']
            ]);

            return $this->json(['message' => 'Password changed successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to change password', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to change password'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user preferences
     */
    #[Route('/preferences', name: 'preferences', methods: ['GET'])]
    public function preferences(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'preferences' => [
                'timezone' => $user->getTimezone(),
                'locale' => $user->getLocale(),
                'marketing_consent' => $user->isMarketingConsentGiven(),
                'two_factor_enabled' => $user->isTwoFactorEnabled()
            ]
        ]);
    }

    /**
     * Update user preferences
     */
    #[Route('/preferences', name: 'update_preferences', methods: ['PUT', 'PATCH'])]
    public function updatePreferences(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $updated = false;

            if (isset($data['marketing_consent'])) {
                $marketingConsent = (bool) $data['marketing_consent'];
                if ($marketingConsent !== $user->isMarketingConsentGiven()) {
                    $this->rgpdService->recordConsent($user, 'marketing', $marketingConsent);
                    $updated = true;
                }
            }

            if (isset($data['timezone'])) {
                try {
                    new \DateTimeZone($data['timezone']);
                    $user->setTimezone($data['timezone']);
                    $updated = true;
                } catch (\Exception) {
                    return $this->json(['error' => 'Invalid timezone'], Response::HTTP_BAD_REQUEST);
                }
            }

            if (isset($data['locale'])) {
                $supportedLocales = ['en', 'fr', 'es', 'de', 'it', 'pt', 'ja', 'zh'];
                if (in_array($data['locale'], $supportedLocales, true)) {
                    $user->setLocale($data['locale']);
                    $updated = true;
                } else {
                    return $this->json(['error' => 'Unsupported locale'], Response::HTTP_BAD_REQUEST);
                }
            }

            if ($updated) {
                $this->entityManager->flush();

                $this->logger->info('User preferences updated', [
                    'user_id' => $user->getId(),
                    'updated_fields' => array_keys($data)
                ]);
            }

            return $this->json([
                'message' => $updated ? 'Preferences updated successfully' : 'No changes detected',
                'preferences' => [
                    'timezone' => $user->getTimezone(),
                    'locale' => $user->getLocale(),
                    'marketing_consent' => $user->isMarketingConsentGiven(),
                    'two_factor_enabled' => $user->isTwoFactorEnabled()
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update preferences', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to update preferences'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user subscription info
     */
    #[Route('/subscription', name: 'subscription', methods: ['GET'])]
    public function subscription(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'subscription' => [
                'plan' => $user->getSubscriptionPlan(),
                'expires_at' => $user->getSubscriptionExpiresAt()?->format('c'),
                'monthly_snippet_limit' => $user->getMonthlySnippetLimit(),
                'monthly_snippets_used' => $user->getMonthlySnippetsUsed(),
                'monthly_usage_reset_at' => $user->getMonthlyUsageResetAt()->format('c'),
                'can_create_snippet' => $user->canCreateSnippet(),
                'features' => $this->getSubscriptionFeatures($user->getSubscriptionPlan())
            ]
        ]);
    }

    /**
     * Export user data (GDPR Article 20)
     */
    #[Route('/export', name: 'export', methods: ['POST'])]
    public function exportData(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $exportData = $this->rgpdService->exportUserData($user);

            $this->logger->info('User data export generated', [
                'user_id' => $user->getId(),
                'export_date' => $exportData['export_date']
            ]);

            return $this->json([
                'message' => 'Data export generated successfully',
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to export user data', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to export data'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete user account (GDPR Article 17 - Right to be forgotten)
     */
    #[Route('/delete-account', name: 'delete_account', methods: ['DELETE'])]
    public function deleteAccount(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $password = $data['password'] ?? null;
            $reason = $data['reason'] ?? 'User request';

            if (!$password) {
                return $this->json(['error' => 'Password confirmation required'], Response::HTTP_BAD_REQUEST);
            }

            // Verify password
            if (!$this->securityService->verifyPassword($user, $password)) {
                return $this->json(['error' => 'Password is incorrect'], Response::HTTP_BAD_REQUEST);
            }

            // Anonymize user data
            $success = $this->rgpdService->anonymizeUser($user, $reason);

            if (!$success) {
                return $this->json(['error' => 'Failed to delete account'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->logger->info('User account deleted', [
                'user_id' => $user->getId(),
                'reason' => $reason
            ]);

            return $this->json(['message' => 'Account deleted successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete account', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to delete account'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get account activity log
     */
    #[Route('/activity', name: 'activity', methods: ['GET'])]
    public function activity(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

            // This would typically fetch from an audit log table
            // For now, return basic activity info
            $activity = [
                [
                    'type' => 'login',
                    'timestamp' => $user->getLastLoginAt()?->format('c'),
                    'ip_address' => $user->getLastLoginIp(),
                    'description' => 'User logged in'
                ],
                [
                    'type' => 'profile_update',
                    'timestamp' => $user->getUpdatedAt()->format('c'),
                    'description' => 'Profile information updated'
                ]
            ];

            return $this->json([
                'activity' => array_filter($activity, fn($item) => $item['timestamp'] !== null),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($activity),
                    'pages' => 1
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve activity', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve activity'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get account security settings
     */
    #[Route('/security', name: 'security', methods: ['GET'])]
    public function security(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'security' => [
                'two_factor_enabled' => $user->isTwoFactorEnabled(),
                'last_login_at' => $user->getLastLoginAt()?->format('c'),
                'last_login_ip' => $user->getLastLoginIp(),
                'failed_login_attempts' => $user->getFailedLoginAttempts(),
                'account_locked' => $this->securityService->isUserLockedOut($user),
                'oauth_provider' => $user->getOauthProvider(),
                'has_password' => $user->getPassword() !== null
            ]
        ]);
    }

    /**
     * Update security settings
     */
    #[Route('/security', name: 'update_security', methods: ['PUT', 'PATCH'])]
    public function updateSecurity(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $updated = false;

            if (isset($data['two_factor_enabled'])) {
                $twoFactorEnabled = (bool) $data['two_factor_enabled'];
                
                if ($twoFactorEnabled && !$user->isTwoFactorEnabled()) {
                    // Enable 2FA (would require additional setup in real implementation)
                    $user->setTwoFactorEnabled(true);
                    // Generate backup codes
                    $backupCodes = [];
                    for ($i = 0; $i < 10; $i++) {
                        $backupCodes[] = $this->securityService->generateSecureToken(8);
                    }
                    $user->setBackupCodes($backupCodes);
                    $updated = true;
                } elseif (!$twoFactorEnabled && $user->isTwoFactorEnabled()) {
                    // Disable 2FA (would require password confirmation in real implementation)
                    $user->setTwoFactorEnabled(false);
                    $user->setTwoFactorSecret(null);
                    $user->setBackupCodes(null);
                    $updated = true;
                }
            }

            if ($updated) {
                $this->entityManager->flush();

                $this->logger->info('User security settings updated', [
                    'user_id' => $user->getId(),
                    'two_factor_enabled' => $user->isTwoFactorEnabled()
                ]);
            }

            return $this->json([
                'message' => $updated ? 'Security settings updated successfully' : 'No changes detected',
                'security' => [
                    'two_factor_enabled' => $user->isTwoFactorEnabled(),
                    'backup_codes' => $updated && $user->isTwoFactorEnabled() ? $user->getBackupCodes() : null
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update security settings', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to update security settings'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Serialize user data for API response
     */
    private function serializeUser(User $user, bool $includePrivateInfo = false): array
    {
        $data = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsernameField(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'full_name' => $user->getFullName(),
            'avatar_url' => $user->getAvatarUrl(),
            'created_at' => $user->getCreatedAt()->format('c'),
            'updated_at' => $user->getUpdatedAt()->format('c')
        ];

        if ($includePrivateInfo) {
            $data = array_merge($data, [
                'email_verified' => $user->getEmailVerifiedAt() !== null,
                'email_verified_at' => $user->getEmailVerifiedAt()?->format('c'),
                'timezone' => $user->getTimezone(),
                'locale' => $user->getLocale(),
                'subscription_plan' => $user->getSubscriptionPlan(),
                'subscription_expires_at' => $user->getSubscriptionExpiresAt()?->format('c'),
                'monthly_snippet_limit' => $user->getMonthlySnippetLimit(),
                'monthly_snippets_used' => $user->getMonthlySnippetsUsed(),
                'monthly_usage_reset_at' => $user->getMonthlyUsageResetAt()->format('c'),
                'can_create_snippet' => $user->canCreateSnippet(),
                'two_factor_enabled' => $user->isTwoFactorEnabled(),
                'marketing_consent' => $user->isMarketingConsentGiven(),
                'last_login_at' => $user->getLastLoginAt()?->format('c'),
                'oauth_provider' => $user->getOauthProvider()
            ]);
        }

        return $data;
    }

    /**
     * Get subscription features for a plan
     */
    private function getSubscriptionFeatures(string $plan): array
    {
        return match ($plan) {
            User::PLAN_FREEMIUM => [
                'snippet_limit' => 10,
                'syntax_highlighting' => 'basic',
                'link_expiration' => '7 days',
                'themes' => ['default', 'dark', 'light'],
                'data_masking' => false,
                'integrations' => [],
                'collaboration' => false,
                'priority_support' => false
            ],
            User::PLAN_PRO => [
                'snippet_limit' => -1, // unlimited
                'syntax_highlighting' => 'advanced',
                'link_expiration' => 'custom',
                'themes' => 'all',
                'data_masking' => true,
                'integrations' => ['basic'],
                'collaboration' => false,
                'priority_support' => false
            ],
            User::PLAN_TEAM => [
                'snippet_limit' => -1, // unlimited
                'syntax_highlighting' => 'advanced',
                'link_expiration' => 'custom',
                'themes' => 'all',
                'data_masking' => true,
                'integrations' => ['basic', 'advanced'],
                'collaboration' => true,
                'priority_support' => true
            ],
            User::PLAN_ENTERPRISE => [
                'snippet_limit' => -1, // unlimited
                'syntax_highlighting' => 'advanced',
                'link_expiration' => 'custom',
                'themes' => 'all',
                'data_masking' => true,
                'integrations' => ['basic', 'advanced', 'enterprise'],
                'collaboration' => true,
                'priority_support' => true,
                'sso' => true,
                'custom_branding' => true,
                'on_premise' => true
            ],
            default => []
        };
    }
}