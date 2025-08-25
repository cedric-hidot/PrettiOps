<?php

namespace App\UI\Http\Web\Controller;

use App\Domain\User\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Repository\UserRepository;
use App\Security\AppCustomAuthenticator;
use App\Security\JwtTokenService;
use App\Infrastructure\Security\RgpdComplianceService;
use App\Infrastructure\Security\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Web Authentication Controller
 * Handles web-based login, registration, and password reset flows
 */
#[Route('/auth', name: 'app_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly SecurityService $securityService,
        private readonly RgpdComplianceService $rgpdService,
        private readonly JwtTokenService $jwtTokenService,
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly AppCustomAuthenticator $authenticator
    ) {
    }

    /**
     * Login page
     */
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Registration page
     */
    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        // If user is already authenticated, redirect to dashboard
        if ($this->getUser()) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Already authenticated'], Response::HTTP_BAD_REQUEST);
            }
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('GET')) {
            return $this->render('auth/register.html.twig', [
                'errors' => []
            ]);
        }

        // Handle POST request (registration)
        try {
            // Handle AJAX request differently
            if ($request->isXmlHttpRequest()) {
                return $this->handleAjaxRegistration($request);
            }

            // Handle traditional form submission
            return $this->handleFormRegistration($request);

        } catch (\Exception $e) {
            $this->logger->error('Registration failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Registration failed. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->addFlash('error', 'Registration failed. Please try again.');
            return $this->render('auth/register.html.twig', ['errors' => []]);
        }
    }

    private function handleAjaxRegistration(Request $request): Response
    {
        try {
            // Test database connectivity first
            try {
                $connection = $this->entityManager->getConnection();
                $connection->executeQuery('SELECT 1');
                error_log("Database connection successful");
                
                // Check if users table exists
                $schemaManager = $connection->createSchemaManager();
                $tables = $schemaManager->listTableNames();
                if (!in_array('users', $tables)) {
                    error_log("ERROR: users table does not exist in database");
                    return $this->json([
                        'error' => 'Database not properly initialized. Users table missing.',
                        'details' => 'Please run: php bin/console doctrine:migrations:migrate'
                    ], Response::HTTP_SERVICE_UNAVAILABLE);
                }
                error_log("Users table exists in database");
            } catch (\Exception $e) {
                error_log("Database connection failed: " . $e->getMessage());
                return $this->json([
                    'error' => 'Database connection failed',
                    'details' => $e->getMessage()
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
            
            // Get form data from request
            $data = [
                'first_name' => trim($request->request->get('first_name', '')),
                'last_name' => trim($request->request->get('last_name', '')),
                'email' => trim($request->request->get('email', '')),
                'password' => $request->request->get('password', ''),
                'password_confirm' => $request->request->get('password_confirm', ''),
                'terms_accepted' => $request->request->getBoolean('terms_accepted'),
            ];

            // Validate required fields
            $errors = [];
            if (empty($data['first_name'])) {
                $errors[] = 'First name is required';
            }
            if (empty($data['last_name'])) {
                $errors[] = 'Last name is required';
            }
            if (empty($data['email'])) {
                $errors[] = 'Email address is required';
            }
            if (empty($data['password'])) {
                $errors[] = 'Password is required';
            }
            if (!$data['terms_accepted']) {
                $errors[] = 'You must accept the Terms of Service and Privacy Policy';
            }

            // Validate email format
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }

            // Validate password confirmation
            if ($data['password'] !== $data['password_confirm']) {
                $errors[] = 'Passwords do not match';
            }

            // Basic password validation
            if (!empty($data['password']) && strlen($data['password']) < 8) {
                $errors[] = 'Password must be at least 8 characters long';
            }

            if (!empty($errors)) {
                return $this->json(['error' => implode('. ', $errors)], Response::HTTP_BAD_REQUEST);
            }

            // Check if user already exists
            $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUser) {
                return $this->json(['error' => 'An account with this email address already exists'], Response::HTTP_CONFLICT);
            }

            // Create new user with all required fields
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['first_name']);
            $user->setLastName($data['last_name']);
            
            // Hash password using Symfony's password hasher
            $hashedPassword = $this->securityService->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
            
            // Set all required defaults from User entity
            $user->setTimezone('UTC');
            $user->setLocale('en');
            $user->setStatus(User::STATUS_ACTIVE);
            $user->setSubscriptionPlan(User::PLAN_FREEMIUM);
            $user->setMonthlySnippetLimit(10);
            $user->setMonthlySnippetsUsed(0);
            $user->setMonthlyUsageResetAt(new \DateTimeImmutable());
            
            // Ensure boolean values are properly set (PostgreSQL can be strict about boolean types)
            $user->setTwoFactorEnabled(false);
            $user->setFailedLoginAttempts(0);
            $user->setMarketingConsent($data['marketing_consent'] ?? false);
            
            // Validate the user entity before persisting
            $violations = $this->validator->validate($user);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                return $this->json(['error' => 'Validation failed: ' . implode(', ', $errors)], Response::HTTP_BAD_REQUEST);
            }

            // Save user using direct SQL approach to bypass any ORM transaction issues
            try {
                $connection = $this->entityManager->getConnection();
                $now = new \DateTimeImmutable();
                
                $sql = "INSERT INTO users (
                    id, email, first_name, last_name, password_hash,
                    timezone, locale, status, subscription_plan,
                    monthly_snippet_limit, monthly_snippets_used, monthly_usage_reset_at,
                    two_factor_enabled, failed_login_attempts, marketing_consent,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $result = $connection->executeStatement($sql, [
                    $user->getId()->toString(),
                    $user->getEmail(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getPassword(),
                    $user->getTimezone(),
                    $user->getLocale(),
                    $user->getStatus(),
                    $user->getSubscriptionPlan(),
                    $user->getMonthlySnippetLimit(),
                    $user->getMonthlySnippetsUsed(),
                    $user->getMonthlyUsageResetAt()->format('Y-m-d H:i:s'),
                    $user->isTwoFactorEnabled() ? 1 : 0,
                    $user->getFailedLoginAttempts(),
                    $user->isMarketingConsentGiven() ? 1 : 0,
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s')
                ]);
                
                if ($result === 0) {
                    return $this->json([
                        'error' => 'Failed to create user account'
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                
                // Get the saved user using ORM for authentication
                $savedUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
                if (!$savedUser) {
                    return $this->json([
                        'error' => 'User registration succeeded but login failed'
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                
                $user = $savedUser;
                
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Registration failed: ' . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Auto-login the user after successful registration
            try {
                $this->userAuthenticator->authenticateUser(
                    $user,
                    $this->authenticator,
                    $request
                );
                
                return $this->json([
                    'success' => true,
                    'message' => 'Welcome to PrettiOps! Your account has been created and you are now logged in.',
                    'redirect' => $this->generateUrl('app_dashboard')
                ]);
                
            } catch (\Exception $e) {
                // If authentication fails, still return success since user was created
                return $this->json([
                    'success' => true,
                    'message' => 'Account created successfully! Please sign in to continue.',
                    'redirect' => $this->generateUrl('app_login')
                ]);
            }

        } catch (\Doctrine\DBAL\Exception $e) {
            // Database connection or schema issue
            return $this->json([
                'error' => 'Registration is temporarily unavailable. Database not ready yet.',
                'details' => 'Please run: php bin/console doctrine:migrations:migrate'
            ], Response::HTTP_SERVICE_UNAVAILABLE);
            
        } catch (\Exception $e) {
            // Other errors
            error_log("Registration error: " . $e->getMessage());
            return $this->json([
                'error' => 'Registration failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleFormRegistration(Request $request): Response
    {
        // Traditional form handling (existing logic simplified)
        $this->addFlash('error', 'Traditional form submission not implemented yet. Please use the AJAX form.');
        return $this->render('auth/register.html.twig', ['errors' => []]);
    }

    /**
     * Forgot password page
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                // Validate CSRF token
                $token = $request->request->get('_token');
                if (!$this->isCsrfTokenValid('forgot_password', $token)) {
                    throw new \InvalidArgumentException('Invalid CSRF token');
                }

                $email = trim($request->request->get('email', ''));
                
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addFlash('error', 'Please enter a valid email address');
                } else {
                    $user = $this->userRepository->findOneBy(['email' => $email]);
                    
                    // Always show success to prevent email enumeration
                    $this->addFlash('success', 'If an account with that email exists, a password reset link has been sent.');
                    
                    if ($user && $user->isActive()) {
                        $resetToken = $this->jwtTokenService->generatePasswordResetToken($user);
                        $this->sendPasswordResetEmail($user, $resetToken);

                        $this->logger->info('Password reset requested via web', [
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail(),
                            'ip' => $request->getClientIp()
                        ]);
                    }

                    return $this->redirectToRoute('app_login');
                }
            } catch (\Exception $e) {
                $this->logger->error('Forgot password failed', [
                    'error' => $e->getMessage(),
                    'ip' => $request->getClientIp()
                ]);
                $this->addFlash('error', 'An error occurred. Please try again.');
            }
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    /**
     * Reset password page
     */
    #[Route('/reset-password', name: 'reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request): Response
    {
        $token = $request->query->get('token') ?? $request->request->get('token');
        
        if (!$token) {
            $this->addFlash('error', 'Invalid or missing reset token');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Validate reset token
        $tokenValidation = $this->jwtTokenService->validatePasswordResetToken($token);
        if (!$tokenValidation['valid']) {
            $this->addFlash('error', $tokenValidation['error']);
            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $this->userRepository->find($tokenValidation['user_id']);
        if (!$user) {
            $this->addFlash('error', 'Invalid reset token');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            try {
                // Validate CSRF token
                $csrfToken = $request->request->get('_token');
                if (!$this->isCsrfTokenValid('reset_password', $csrfToken)) {
                    throw new \InvalidArgumentException('Invalid CSRF token');
                }

                $password = $request->request->get('password', '');
                $passwordConfirm = $request->request->get('password_confirm', '');

                if ($password !== $passwordConfirm) {
                    $this->addFlash('error', 'Passwords do not match');
                } else {
                    // Validate new password strength
                    $passwordValidation = $this->securityService->validatePasswordStrength($password);
                    if (!$passwordValidation['valid']) {
                        foreach ($passwordValidation['errors'] as $error) {
                            $this->addFlash('error', $error);
                        }
                    } else {
                        // Update password
                        $hashedPassword = $this->securityService->hashPassword($user, $password);
                        $user->setPassword($hashedPassword);
                        
                        // Reset failed login attempts
                        $user->setFailedLoginAttempts(0);
                        $user->setLockedUntil(null);
                        
                        $this->entityManager->flush();

                        $this->logger->info('Password reset completed via web', [
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail(),
                            'ip' => $request->getClientIp()
                        ]);

                        $this->addFlash('success', 'Password reset successful! You can now log in with your new password.');
                        return $this->redirectToRoute('app_login');
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Password reset failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $user?->getId(),
                    'ip' => $request->getClientIp()
                ]);
                $this->addFlash('error', 'Password reset failed. Please try again.');
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token
        ]);
    }

    /**
     * Logout route (handled by security system)
     */
    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Email verification
     */
    #[Route('/verify-email', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token');

        if (!$token) {
            $this->addFlash('error', 'Invalid verification link');
            return $this->redirectToRoute('app_login');
        }

        try {
            // Validate verification token
            $tokenValidation = $this->jwtTokenService->validateEmailVerificationToken($token);
            if (!$tokenValidation['valid']) {
                $this->addFlash('error', $tokenValidation['error']);
                return $this->redirectToRoute('app_login');
            }

            $user = $this->userRepository->find($tokenValidation['user_id']);
            if (!$user) {
                $this->addFlash('error', 'Invalid verification link');
                return $this->redirectToRoute('app_login');
            }

            if ($user->getEmailVerifiedAt()) {
                $this->addFlash('info', 'Your email is already verified');
            } else {
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->logger->info('Email verified via web', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $request->getClientIp()
                ]);

                $this->addFlash('success', 'Email verified successfully! You can now access all features.');
            }

            return $this->redirectToRoute('app_login');

        } catch (\Exception $e) {
            $this->logger->error('Email verification failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            $this->addFlash('error', 'Email verification failed');
            return $this->redirectToRoute('app_login');
        }
    }

    /**
     * Send email verification
     */
    private function sendEmailVerification(User $user): void
    {
        try {
            $verificationToken = $this->jwtTokenService->generateEmailVerificationToken($user);
            $verificationUrl = $this->generateUrl('app_verify_email', ['token' => $verificationToken], true);

            $email = (new Email())
                ->from('noreply@prettiops.com')
                ->to($user->getEmail())
                ->subject('PrettiOps - Verify your email address')
                ->html($this->getEmailVerificationTemplate($user, $verificationUrl));

            $this->mailer->send($email);

            $this->logger->info('Email verification sent via web registration', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send verification email', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);
        }
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        try {
            $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], true);

            $email = (new Email())
                ->from('noreply@prettiops.com')
                ->to($user->getEmail())
                ->subject('PrettiOps - Password Reset')
                ->html($this->getPasswordResetTemplate($user, $resetUrl));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);
        }
    }

    /**
     * Get email verification template
     */
    private function getEmailVerificationTemplate(User $user, string $verificationUrl): string
    {
        $name = $user->getFirstName() ?? $user->getEmail();
        
        return sprintf('
            <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
                <h2 style="color: #333;">Welcome to PrettiOps!</h2>
                <p>Hello %s,</p>
                <p>Thank you for registering with PrettiOps. Please click the button below to verify your email address:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%s" style="background: #8B5CF6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Verify Email Address</a>
                </div>
                <p>If you didn\'t create an account, you can safely ignore this email.</p>
                <p>Best regards,<br>The PrettiOps Team</p>
            </div>
        ', $name, $verificationUrl);
    }

    /**
     * Get password reset template
     */
    private function getPasswordResetTemplate(User $user, string $resetUrl): string
    {
        $name = $user->getFirstName() ?? $user->getEmail();
        
        return sprintf('
            <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
                <h2 style="color: #333;">Password Reset Request</h2>
                <p>Hello %s,</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%s" style="background: #8B5CF6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Reset Password</a>
                </div>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn\'t request a password reset, you can safely ignore this email.</p>
                <p>Best regards,<br>The PrettiOps Team</p>
            </div>
        ', $name, $resetUrl);
    }
}