<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Custom authenticator for web-based authentication
 * Handles login forms, OAuth redirects, and security features
 */
class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserRepository $userRepository,
        private readonly SecurityService $securityService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');
        $password = $request->getPayload()->getString('password');

        if (empty($email) || empty($password)) {
            throw new CustomUserMessageAuthenticationException('Email and password are required.');
        }

        // Sanitize email input
        $email = $this->securityService->sanitizeInput($email, ['strict' => true]);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new CustomUserMessageAuthenticationException('Invalid email format.');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        // Check for user existence and account status
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        // Check if account is active
        if (!$user->isActive()) {
            $message = match($user->getStatus()) {
                User::STATUS_SUSPENDED => 'Your account has been suspended. Please contact support.',
                User::STATUS_DELETED => 'This account no longer exists.',
                default => 'Your account is not active. Please contact support.'
            };
            throw new CustomUserMessageAuthenticationException($message);
        }

        // Check for account lockout due to failed attempts
        if ($this->securityService->isUserLockedOut($user)) {
            $lockedUntil = $user->getLockedUntil();
            $message = $lockedUntil 
                ? sprintf('Account temporarily locked until %s due to failed login attempts.', $lockedUntil->format('Y-m-d H:i:s'))
                : 'Account temporarily locked due to failed login attempts.';
            throw new CustomUserMessageAuthenticationException($message);
        }

        $passport = new Passport(
            new UserBadge($email, function ($userIdentifier) {
                return $this->userRepository->findOneBy(['email' => $userIdentifier]);
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_token')),
            ]
        );

        // Add remember me badge if checkbox was checked
        if ($request->getPayload()->getBoolean('_remember_me')) {
            $passport->addBadge(new RememberMeBadge());
        }

        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        
        if ($user instanceof User) {
            // Handle successful login in security service
            $this->securityService->handleSuccessfulLogin($user, $request);
            $this->entityManager->flush();

            $this->logger->info('Web login successful', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            // Add flash message
            $request->getSession()->getFlashBag()->add('success', 'Welcome back, ' . ($user->getFirstName() ?: $user->getEmail()) . '!');
        }

        // Redirect to the page they were trying to access, or dashboard
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $email = $request->getPayload()->getString('email');
        
        if ($email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            
            if ($user) {
                // Handle failed login attempt
                $this->securityService->handleFailedLogin($user, $request);
                $this->entityManager->flush();

                $this->logger->warning('Web login failed', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $request->getClientIp(),
                    'error' => $exception->getMessage()
                ]);
            }
        }

        // Add flash error message
        $request->getSession()->getFlashBag()->add('error', $exception->getMessageKey());

        return parent::onAuthenticationFailure($request, $exception);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public function supports(Request $request): bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }
}