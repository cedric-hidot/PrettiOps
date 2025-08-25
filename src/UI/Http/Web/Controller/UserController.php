<?php

namespace App\UI\Http\Web\Controller;

use App\User\Domain\Entity\User;
use App\User\Infrastructure\Persistence\Doctrine\UserRepository;
use App\User\Infrastructure\Security\SecurityService;
use App\Attachment\Infrastructure\Storage\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Web User Controller
 * Handles user profile, settings updates via web forms
 */
#[Route('/user', name: 'app_user_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly SecurityService $securityService,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Update profile information
     */
    #[Route('/profile/update', name: 'profile_update', methods: ['POST'])]
    public function updateProfile(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('update_profile', $token)) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'Invalid CSRF token');
                return $this->redirectToRoute('app_settings');
            }

            $data = [
                'first_name' => trim($request->request->get('first_name', '')),
                'last_name' => trim($request->request->get('last_name', '')),
                'username' => trim($request->request->get('username', '')),
                'bio' => trim($request->request->get('bio', '')),
                'location' => trim($request->request->get('location', '')),
                'website' => trim($request->request->get('website', '')),
            ];

            $errors = [];

            // Validate required fields
            if (empty($data['first_name'])) {
                $errors[] = 'First name is required';
            }
            if (empty($data['last_name'])) {
                $errors[] = 'Last name is required';
            }

            // Validate username if provided
            if (!empty($data['username'])) {
                if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $data['username'])) {
                    $errors[] = 'Username must be 3-50 characters and contain only letters, numbers, underscores, and hyphens';
                }
                
                // Check if username is taken by another user
                $existingUser = $this->userRepository->findOneBy(['username' => $data['username']]);
                if ($existingUser && $existingUser !== $user) {
                    $errors[] = 'Username is already taken';
                }
            }

            // Validate website URL if provided
            if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Website must be a valid URL';
            }

            if (empty($errors)) {
                // Update user data
                $user->setFirstName($this->securityService->sanitizeInput($data['first_name']));
                $user->setLastName($this->securityService->sanitizeInput($data['last_name']));
                
                if (!empty($data['username'])) {
                    $user->setUsername($this->securityService->sanitizeInput($data['username']));
                }

                // Note: Bio, Location, and Website would need to be added to User entity
                // For now, we'll skip these fields as they don't exist in the current entity

                // Validate entity
                $entityErrors = $this->validator->validate($user);
                if (count($entityErrors) > 0) {
                    foreach ($entityErrors as $error) {
                        $errors[] = $error->getMessage();
                    }
                } else {
                    $this->entityManager->flush();

                    $this->logger->info('Profile updated', [
                        'user_id' => $user->getId(),
                        'ip' => $request->getClientIp()
                    ]);

                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'message' => 'Profile updated successfully',
                            'user' => [
                                'first_name' => $user->getFirstName(),
                                'last_name' => $user->getLastName(),
                                'username' => $user->getUsernameField(),
                                'full_name' => $user->getFullName()
                            ]
                        ]);
                    }

                    $this->addFlash('success', 'Profile updated successfully');
                    return $this->redirectToRoute('app_settings');
                }
            }

            // Handle errors
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => implode(', ', $errors)], Response::HTTP_BAD_REQUEST);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

        } catch (\Exception $e) {
            $this->logger->error('Profile update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'ip' => $request->getClientIp()
            ]);

            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Profile update failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->addFlash('error', 'Profile update failed');
        }

        return $this->redirectToRoute('app_settings');
    }

    /**
     * Update account settings
     */
    #[Route('/account/update', name: 'account_update', methods: ['POST'])]
    public function updateAccount(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('update_account', $token)) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'Invalid CSRF token');
                return $this->redirectToRoute('app_settings');
            }

            $data = [
                'email' => trim($request->request->get('email', '')),
                'timezone' => trim($request->request->get('timezone', 'UTC')),
                'locale' => trim($request->request->get('locale', 'en')),
            ];

            $errors = [];

            // Validate email
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email address is required';
            } else {
                // Check if email is taken by another user
                $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
                if ($existingUser && $existingUser !== $user) {
                    $errors[] = 'Email address is already taken';
                }
            }

            // Validate timezone
            $validTimezones = \DateTimeZone::listIdentifiers();
            if (!in_array($data['timezone'], $validTimezones, true)) {
                $errors[] = 'Invalid timezone';
            }

            // Validate locale
            $validLocales = ['en', 'fr', 'es', 'de', 'it', 'pt', 'ja', 'zh'];
            if (!in_array($data['locale'], $validLocales, true)) {
                $errors[] = 'Invalid locale';
            }

            if (empty($errors)) {
                $emailChanged = $user->getEmail() !== $data['email'];
                
                // Update user data
                $user->setEmail($this->securityService->sanitizeInput($data['email'], ['strict' => true]));
                $user->setTimezone($data['timezone']);
                $user->setLocale($data['locale']);

                // If email changed, unverify it
                if ($emailChanged) {
                    $user->setEmailVerifiedAt(null);
                }

                // Validate entity
                $entityErrors = $this->validator->validate($user);
                if (count($entityErrors) > 0) {
                    foreach ($entityErrors as $error) {
                        $errors[] = $error->getMessage();
                    }
                } else {
                    $this->entityManager->flush();

                    $this->logger->info('Account settings updated', [
                        'user_id' => $user->getId(),
                        'email_changed' => $emailChanged,
                        'ip' => $request->getClientIp()
                    ]);

                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'message' => 'Account settings updated successfully',
                            'email_changed' => $emailChanged
                        ]);
                    }

                    $message = 'Account settings updated successfully';
                    if ($emailChanged) {
                        $message .= '. Please verify your new email address.';
                    }
                    
                    $this->addFlash('success', $message);
                    return $this->redirectToRoute('app_settings');
                }
            }

            // Handle errors
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => implode(', ', $errors)], Response::HTTP_BAD_REQUEST);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

        } catch (\Exception $e) {
            $this->logger->error('Account update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'ip' => $request->getClientIp()
            ]);

            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Account update failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->addFlash('error', 'Account update failed');
        }

        return $this->redirectToRoute('app_settings');
    }

    /**
     * Update password
     */
    #[Route('/password/update', name: 'password_update', methods: ['POST'])]
    public function updatePassword(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('update_password', $token)) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'Invalid CSRF token');
                return $this->redirectToRoute('app_settings');
            }

            $data = [
                'current_password' => $request->request->get('current_password', ''),
                'new_password' => $request->request->get('new_password', ''),
                'new_password_confirm' => $request->request->get('new_password_confirm', ''),
            ];

            $errors = [];

            // Validate current password
            if (empty($data['current_password'])) {
                $errors[] = 'Current password is required';
            } elseif (!$this->securityService->verifyPassword($user, $data['current_password'])) {
                $errors[] = 'Current password is incorrect';
            }

            // Validate new password
            if (empty($data['new_password'])) {
                $errors[] = 'New password is required';
            } elseif ($data['new_password'] !== $data['new_password_confirm']) {
                $errors[] = 'New passwords do not match';
            } else {
                // Validate password strength
                $passwordValidation = $this->securityService->validatePasswordStrength($data['new_password']);
                if (!$passwordValidation['valid']) {
                    $errors = array_merge($errors, $passwordValidation['errors']);
                }
            }

            if (empty($errors)) {
                // Update password
                $hashedPassword = $this->securityService->hashPassword($user, $data['new_password']);
                $user->setPassword($hashedPassword);
                
                // Reset failed login attempts
                $user->setFailedLoginAttempts(0);
                $user->setLockedUntil(null);
                
                $this->entityManager->flush();

                $this->logger->info('Password updated via settings', [
                    'user_id' => $user->getId(),
                    'ip' => $request->getClientIp()
                ]);

                if ($request->isXmlHttpRequest()) {
                    return $this->json(['message' => 'Password updated successfully']);
                }

                $this->addFlash('success', 'Password updated successfully');
                return $this->redirectToRoute('app_settings');
            }

            // Handle errors
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => implode(', ', $errors)], Response::HTTP_BAD_REQUEST);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

        } catch (\Exception $e) {
            $this->logger->error('Password update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'ip' => $request->getClientIp()
            ]);

            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Password update failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->addFlash('error', 'Password update failed');
        }

        return $this->redirectToRoute('app_settings');
    }

    /**
     * Upload avatar
     */
    #[Route('/avatar/upload', name: 'avatar_upload', methods: ['POST'])]
    public function uploadAvatar(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('upload_avatar', $token)) {
                return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
            }

            /** @var UploadedFile $file */
            $file = $request->files->get('avatar');
            
            if (!$file) {
                return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
            }

            // Validate file type and size
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedTypes, true)) {
                return $this->json(['error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'], Response::HTTP_BAD_REQUEST);
            }

            if ($file->getSize() > 2 * 1024 * 1024) { // 2MB
                return $this->json(['error' => 'File too large. Maximum size is 2MB.'], Response::HTTP_BAD_REQUEST);
            }

            // Upload file
            $uploadResult = $this->fileUploadService->upload($file, 'avatars', $user->getId()->toString());
            
            if (!$uploadResult['success']) {
                return $this->json(['error' => $uploadResult['error']], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Update user avatar URL
            $user->setAvatarUrl($uploadResult['url']);
            $this->entityManager->flush();

            $this->logger->info('Avatar uploaded', [
                'user_id' => $user->getId(),
                'file_path' => $uploadResult['path'],
                'ip' => $request->getClientIp()
            ]);

            return $this->json([
                'message' => 'Avatar uploaded successfully',
                'avatar_url' => $uploadResult['url']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Avatar upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'ip' => $request->getClientIp()
            ]);

            return $this->json(['error' => 'Avatar upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete account (soft delete)
     */
    #[Route('/account/delete', name: 'account_delete', methods: ['POST'])]
    public function deleteAccount(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('delete_account', $token)) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'Invalid CSRF token');
                return $this->redirectToRoute('app_settings');
            }

            // Verify password for account deletion
            $password = $request->request->get('password', '');
            if (empty($password) || !$this->securityService->verifyPassword($user, $password)) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['error' => 'Password verification failed'], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'Password verification failed');
                return $this->redirectToRoute('app_settings');
            }

            // Soft delete the account
            $user->setStatus(User::STATUS_DELETED);
            $user->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Account deleted', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp()
            ]);

            // Logout and redirect
            $this->get('security.token_storage')->setToken(null);
            $request->getSession()->invalidate();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['message' => 'Account deleted successfully']);
            }

            $this->addFlash('info', 'Your account has been deleted. Sorry to see you go!');
            return $this->redirectToRoute('app_home');

        } catch (\Exception $e) {
            $this->logger->error('Account deletion failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'ip' => $request->getClientIp()
            ]);

            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Account deletion failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->addFlash('error', 'Account deletion failed');
            return $this->redirectToRoute('app_settings');
        }
    }
}