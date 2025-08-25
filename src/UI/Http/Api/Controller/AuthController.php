<?php

namespace App\UI\Http\Api\Controller;

use App\User\Domain\Entity\User;
use App\User\Infrastructure\Persistence\Doctrine\UserRepository;
use App\Security\JwtTokenService;
use App\User\Infrastructure\Security\SecurityService;
use App\User\Infrastructure\Security\RgpdComplianceService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API Authentication Controller
 * Handles login, registration, OAuth2, password reset, and email verification
 */
#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenService $jwtTokenService,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly SecurityService $securityService,
        private readonly RgpdComplianceService $rgpdService,
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * User registration endpoint
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['email', 'password'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return $this->json(['error' => "Field '$field' is required"], Response::HTTP_BAD_REQUEST);
                }
            }

            // Sanitize input
            $email = $this->securityService->sanitizeInput($data['email'], ['strict' => true]);
            $password = $data['password'];
            $firstName = $data['first_name'] ?? null;
            $lastName = $data['last_name'] ?? null;
            $username = $data['username'] ?? null;

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Invalid email format'], Response::HTTP_BAD_REQUEST);
            }

            // Check if user already exists
            $existingUser = $this->userRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                return $this->json(['error' => 'Email already registered'], Response::HTTP_CONFLICT);
            }

            // Validate password strength
            $passwordValidation = $this->securityService->validatePasswordStrength($password);
            if (!$passwordValidation['valid']) {
                return $this->json([
                    'error' => 'Password does not meet security requirements',
                    'details' => $passwordValidation['errors']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create new user
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setUsername($username);
            
            // Hash password
            $hashedPassword = $this->securityService->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Record GDPR consent if provided
            if ($data['gdpr_consent'] ?? false) {
                $this->rgpdService->recordConsent($user, 'gdpr', true);
            }

            if ($data['marketing_consent'] ?? false) {
                $this->rgpdService->recordConsent($user, 'marketing', true);
            }

            // Validate user entity
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Validation failed', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // Save user
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Send email verification
            $this->sendEmailVerification($user);

            $this->logger->info('User registered successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp()
            ]);

            // Generate JWT token
            $token = $this->jwtTokenService->generateToken($user);

            return $this->json([
                'message' => 'Registration successful',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'first_name' => $user->getFirstName(),
                    'last_name' => $user->getLastName(),
                    'username' => $user->getUsernameField(),
                    'subscription_plan' => $user->getSubscriptionPlan(),
                    'email_verified' => false
                ],
                'token' => $token
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('Registration failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['error' => 'Registration failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * User login endpoint (handled by Lexik JWT but we provide custom response)
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // This endpoint is primarily handled by Lexik JWT Authentication
        // We provide it here for documentation and custom logic if needed
        
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || empty($data['email']) || empty($data['password'])) {
                return $this->json(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
            }

            $email = $this->securityService->sanitizeInput($data['email']);
            $password = $data['password'];

            $user = $this->userRepository->findOneBy(['email' => $email]);
            
            if (!$user) {
                throw new BadCredentialsException('Invalid credentials');
            }

            // Check if user is locked out
            if ($this->securityService->isUserLockedOut($user)) {
                return $this->json(['error' => 'Account temporarily locked due to failed login attempts'], Response::HTTP_LOCKED);
            }

            // Verify password
            if (!$this->securityService->verifyPassword($user, $password)) {
                $this->securityService->handleFailedLogin($user, $request);
                $this->entityManager->flush();
                throw new BadCredentialsException('Invalid credentials');
            }

            // Handle successful login
            $this->securityService->handleSuccessfulLogin($user, $request);
            $this->entityManager->flush();

            // Generate tokens
            $accessToken = $this->jwtTokenService->generateToken($user);
            $refreshToken = $this->jwtTokenService->generateRefreshToken($user);

            return $this->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'first_name' => $user->getFirstName(),
                    'last_name' => $user->getLastName(),
                    'username' => $user->getUsernameField(),
                    'subscription_plan' => $user->getSubscriptionPlan(),
                    'email_verified' => $user->getEmailVerifiedAt() !== null
                ],
                'token' => $accessToken,
                'refresh_token' => $refreshToken
            ]);

        } catch (BadCredentialsException) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            $this->logger->error('Login failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['error' => 'Login failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Refresh JWT token
     */
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $refreshToken = $data['refresh_token'] ?? null;

            if (!$refreshToken) {
                return $this->json(['error' => 'Refresh token required'], Response::HTTP_BAD_REQUEST);
            }

            // Validate refresh token (in a real implementation, you'd check against stored refresh tokens)
            // For now, we'll just generate a new token for the current user
            
            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->json(['error' => 'Invalid user'], Response::HTTP_UNAUTHORIZED);
            }

            $newAccessToken = $this->jwtTokenService->generateToken($user);
            $newRefreshToken = $this->jwtTokenService->generateRefreshToken($user);

            return $this->json([
                'token' => $newAccessToken,
                'refresh_token' => $newRefreshToken
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Token refresh failed', [
                'error' => $e->getMessage()
            ]);

            return $this->json(['error' => 'Token refresh failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * User logout endpoint
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        try {
            // Extract token from Authorization header
            $authHeader = $request->headers->get('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
                $this->jwtTokenService->revokeToken($token);
            }

            $user = $this->getUser();
            if ($user instanceof User) {
                $this->logger->info('User logged out', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $request->getClientIp()
                ]);
            }

            return $this->json(['message' => 'Logout successful']);

        } catch (\Exception $e) {
            $this->logger->error('Logout failed', [
                'error' => $e->getMessage()
            ]);

            return $this->json(['error' => 'Logout failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get current user profile
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'username' => $user->getUsernameField(),
                'avatar_url' => $user->getAvatarUrl(),
                'timezone' => $user->getTimezone(),
                'locale' => $user->getLocale(),
                'subscription_plan' => $user->getSubscriptionPlan(),
                'monthly_snippet_limit' => $user->getMonthlySnippetLimit(),
                'monthly_snippets_used' => $user->getMonthlySnippetsUsed(),
                'email_verified' => $user->getEmailVerifiedAt() !== null,
                'two_factor_enabled' => $user->isTwoFactorEnabled(),
                'created_at' => $user->getCreatedAt()->format('c'),
                'updated_at' => $user->getUpdatedAt()->format('c')
            ]
        ]);
    }

    /**
     * Forgot password endpoint
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Valid email address required'], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);
            
            // Always return success to prevent email enumeration
            if ($user) {
                $resetToken = $this->jwtTokenService->generatePasswordResetToken($user);
                $this->sendPasswordResetEmail($user, $resetToken);

                $this->logger->info('Password reset requested', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $request->getClientIp()
                ]);
            }

            return $this->json(['message' => 'If an account with that email exists, a password reset link has been sent']);

        } catch (\Exception $e) {
            $this->logger->error('Password reset request failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['error' => 'Password reset request failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reset password endpoint
     */
    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $token = $data['token'] ?? null;
            $newPassword = $data['password'] ?? null;

            if (!$token || !$newPassword) {
                return $this->json(['error' => 'Token and new password are required'], Response::HTTP_BAD_REQUEST);
            }

            // Validate reset token
            $tokenValidation = $this->jwtTokenService->validatePasswordResetToken($token);
            if (!$tokenValidation['valid']) {
                return $this->json(['error' => $tokenValidation['error']], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->userRepository->find($tokenValidation['user_id']);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], Response::HTTP_BAD_REQUEST);
            }

            // Validate new password strength
            $passwordValidation = $this->securityService->validatePasswordStrength($newPassword);
            if (!$passwordValidation['valid']) {
                return $this->json([
                    'error' => 'Password does not meet security requirements',
                    'details' => $passwordValidation['errors']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update password
            $hashedPassword = $this->securityService->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            // Reset failed login attempts
            $user->setFailedLoginAttempts(0);
            $user->setLockedUntil(null);
            
            $this->entityManager->flush();

            $this->logger->info('Password reset completed', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['message' => 'Password reset successful']);

        } catch (\Exception $e) {
            $this->logger->error('Password reset failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['error' => 'Password reset failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Email verification endpoint
     */
    #[Route('/verify-email', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $token = $request->query->get('token');

            if (!$token) {
                return $this->json(['error' => 'Verification token required'], Response::HTTP_BAD_REQUEST);
            }

            // Validate verification token
            $tokenValidation = $this->jwtTokenService->validateEmailVerificationToken($token);
            if (!$tokenValidation['valid']) {
                return $this->json(['error' => $tokenValidation['error']], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->userRepository->find($tokenValidation['user_id']);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], Response::HTTP_BAD_REQUEST);
            }

            if ($user->getEmailVerifiedAt()) {
                return $this->json(['message' => 'Email already verified']);
            }

            $user->setEmailVerifiedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Email verified', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['message' => 'Email verified successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Email verification failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['error' => 'Email verification failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Resend email verification
     */
    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(#[CurrentUser] User $user): JsonResponse
    {
        try {
            if ($user->getEmailVerifiedAt()) {
                return $this->json(['error' => 'Email already verified'], Response::HTTP_BAD_REQUEST);
            }

            $this->sendEmailVerification($user);

            return $this->json(['message' => 'Verification email sent']);

        } catch (\Exception $e) {
            $this->logger->error('Resend verification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to resend verification email'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Send email verification
     */
    private function sendEmailVerification(User $user): void
    {
        $verificationToken = $this->jwtTokenService->generateEmailVerificationToken($user);
        $verificationUrl = $this->generateUrl('api_auth_verify_email', ['token' => $verificationToken], true);

        $email = (new Email())
            ->from('noreply@prettiops.com')
            ->to($user->getEmail())
            ->subject('PrettiOps - Verify your email address')
            ->html($this->getEmailVerificationTemplate($user, $verificationUrl));

        $this->mailer->send($email);

        $this->logger->info('Email verification sent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        $resetUrl = $_ENV['FRONTEND_URL'] . '/reset-password?token=' . urlencode($resetToken);

        $email = (new Email())
            ->from('noreply@prettiops.com')
            ->to($user->getEmail())
            ->subject('PrettiOps - Password Reset')
            ->html($this->getPasswordResetTemplate($user, $resetUrl));

        $this->mailer->send($email);
    }

    /**
     * Get email verification template
     */
    private function getEmailVerificationTemplate(User $user, string $verificationUrl): string
    {
        $name = $user->getFirstName() ?? $user->getEmail();
        
        return sprintf('
            <h2>Welcome to PrettiOps!</h2>
            <p>Hello %s,</p>
            <p>Thank you for registering with PrettiOps. Please click the link below to verify your email address:</p>
            <p><a href="%s" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Verify Email Address</a></p>
            <p>If you didn\'t create an account, you can safely ignore this email.</p>
            <p>Best regards,<br>The PrettiOps Team</p>
        ', $name, $verificationUrl);
    }

    /**
     * Get password reset template
     */
    private function getPasswordResetTemplate(User $user, string $resetUrl): string
    {
        $name = $user->getFirstName() ?? $user->getEmail();
        
        return sprintf('
            <h2>Password Reset Request</h2>
            <p>Hello %s,</p>
            <p>We received a request to reset your password. Click the link below to create a new password:</p>
            <p><a href="%s" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn\'t request a password reset, you can safely ignore this email.</p>
            <p>Best regards,<br>The PrettiOps Team</p>
        ', $name, $resetUrl);
    }
}