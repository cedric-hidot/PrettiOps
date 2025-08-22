<?php

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\JwtTokenService;
use App\Service\RgpdComplianceService;
use App\Service\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
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
        private readonly LoggerInterface $logger
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
            return $this->redirectToRoute('app_dashboard');
        }

        $form = ['first_name' => '', 'last_name' => '', 'email' => ''];
        $errors = [];

        if ($request->isMethod('POST')) {
            try {
                // Validate CSRF token
                $token = $request->request->get('_token');
                if (!$this->isCsrfTokenValid('register', $token)) {
                    throw new \InvalidArgumentException('Invalid CSRF token');
                }

                // Get form data
                $data = [
                    'first_name' => trim($request->request->get('first_name', '')),
                    'last_name' => trim($request->request->get('last_name', '')),
                    'email' => trim($request->request->get('email', '')),
                    'password' => $request->request->get('password', ''),
                    'password_confirm' => $request->request->get('password_confirm', ''),
                    'terms_accepted' => $request->request->getBoolean('terms_accepted'),
                    'marketing_emails' => $request->request->getBoolean('marketing_emails'),
                ];

                $form = $data; // Keep form data for repopulation

                // Validate required fields
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

                if (empty($errors)) {
                    // Sanitize input
                    $email = $this->securityService->sanitizeInput($data['email'], ['strict' => true]);
                    $firstName = $this->securityService->sanitizeInput($data['first_name']);
                    $lastName = $this->securityService->sanitizeInput($data['last_name']);

                    // Check if user already exists
                    $existingUser = $this->userRepository->findOneBy(['email' => $email]);
                    if ($existingUser) {
                        $errors[] = 'An account with this email address already exists';
                    } else {
                        // Validate password strength
                        $passwordValidation = $this->securityService->validatePasswordStrength($data['password']);
                        if (!$passwordValidation['valid']) {
                            $errors = array_merge($errors, $passwordValidation['errors']);
                        } else {
                            // Create new user
                            $user = new User();
                            $user->setEmail($email);
                            $user->setFirstName($firstName);
                            $user->setLastName($lastName);
                            
                            // Hash password
                            $hashedPassword = $this->securityService->hashPassword($user, $data['password']);
                            $user->setPassword($hashedPassword);

                            // Record GDPR consent
                            if ($data['terms_accepted']) {
                                $this->rgpdService->recordConsent($user, 'gdpr', true);
                            }

                            if ($data['marketing_emails']) {
                                $this->rgpdService->recordConsent($user, 'marketing', true);
                                $user->setMarketingConsent(true);
                            }

                            // Validate user entity
                            $entityErrors = $this->validator->validate($user);
                            if (count($entityErrors) > 0) {
                                foreach ($entityErrors as $error) {
                                    $errors[] = $error->getMessage();
                                }
                            } else {
                                // Save user
                                $this->entityManager->persist($user);
                                $this->entityManager->flush();

                                // Send email verification
                                $this->sendEmailVerification($user);

                                $this->logger->info('Web registration successful', [
                                    'user_id' => $user->getId(),
                                    'email' => $user->getEmail(),
                                    'ip' => $request->getClientIp()
                                ]);

                                // Add success message and redirect to login
                                $this->addFlash('success', 'Account created successfully! Please check your email to verify your account.');
                                return $this->redirectToRoute('app_login');
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Web registration failed', [
                    'error' => $e->getMessage(),
                    'ip' => $request->getClientIp()
                ]);
                $errors[] = 'Registration failed. Please try again.';
            }

            // If we have errors, add them as flash messages
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('auth/register.html.twig', [
            'form' => (object)['first_name' => (object)['vars' => ['value' => $form['first_name']]], 
                              'last_name' => (object)['vars' => ['value' => $form['last_name']]], 
                              'email' => (object)['vars' => ['value' => $form['email']]]],
            'errors' => $errors
        ]);
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