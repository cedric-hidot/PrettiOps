<?php

namespace App\User\Infrastructure\Security;

use App\User\Domain\Entity\User;
use App\User\Infrastructure\Persistence\Doctrine\UserRepository;
use App\Snippet\Infrastructure\Persistence\Doctrine\SnippetRepository;
use App\Attachment\Infrastructure\Persistence\Doctrine\AttachmentRepository;
use App\Sharing\Infrastructure\Persistence\Doctrine\ShareRepository;
use App\Attachment\Infrastructure\Storage\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * RGPD/GDPR Compliance service
 * Handles data privacy, consent management, and right to be forgotten
 */
class RgpdComplianceService
{
    private const DATA_RETENTION_PERIOD = 'P3Y'; // 3 years
    private const CONSENT_VALID_PERIOD = 'P2Y'; // 2 years

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly SnippetRepository $snippetRepository,
        private readonly AttachmentRepository $attachmentRepository,
        private readonly ShareRepository $shareRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Record user consent for data processing
     */
    public function recordConsent(User $user, string $consentType, bool $granted = true): void
    {
        switch ($consentType) {
            case 'gdpr':
                $user->setGdprConsentAt($granted ? new \DateTimeImmutable() : null);
                break;
            case 'marketing':
                $user->setMarketingConsent($granted);
                break;
        }

        $this->entityManager->flush();

        $this->logger->info('User consent recorded', [
            'user_id' => $user->getId(),
            'consent_type' => $consentType,
            'granted' => $granted,
            'timestamp' => new \DateTimeImmutable()
        ]);
    }

    /**
     * Check if user consent is still valid
     */
    public function isConsentValid(User $user, string $consentType): bool
    {
        switch ($consentType) {
            case 'gdpr':
                $consentDate = $user->getGdprConsentAt();
                break;
            case 'marketing':
                return $user->isMarketingConsentGiven();
            default:
                return false;
        }

        if (!$consentDate) {
            return false;
        }

        $expiryDate = $consentDate->add(new \DateInterval(self::CONSENT_VALID_PERIOD));
        return $expiryDate > new \DateTimeImmutable();
    }

    /**
     * Get all user data for export (GDPR Article 20)
     */
    public function exportUserData(User $user): array
    {
        $this->logger->info('User data export requested', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        // Personal information
        $personalData = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsernameField(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'timezone' => $user->getTimezone(),
            'locale' => $user->getLocale(),
            'subscription_plan' => $user->getSubscriptionPlan(),
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'gdpr_consent_at' => $user->getGdprConsentAt()?->format('Y-m-d H:i:s'),
            'marketing_consent' => $user->isMarketingConsentGiven()
        ];

        // Snippets
        $snippets = $this->snippetRepository->findBy(['user' => $user]);
        $snippetData = [];
        foreach ($snippets as $snippet) {
            $snippetData[] = [
                'id' => $snippet->getId(),
                'title' => $snippet->getTitle(),
                'description' => $snippet->getDescription(),
                'language' => $snippet->getLanguage(),
                'content' => $snippet->getContent(),
                'visibility' => $snippet->getVisibility(),
                'created_at' => $snippet->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $snippet->getUpdatedAt()->format('Y-m-d H:i:s')
            ];
        }

        // Attachments
        $attachments = $this->attachmentRepository->findBy(['user' => $user]);
        $attachmentData = [];
        foreach ($attachments as $attachment) {
            $attachmentData[] = [
                'id' => $attachment->getId(),
                'original_filename' => $attachment->getOriginalFilename(),
                'mime_type' => $attachment->getMimeType(),
                'file_size' => $attachment->getFileSize(),
                'uploaded_at' => $attachment->getUploadedAt()->format('Y-m-d H:i:s')
            ];
        }

        // Shares created by user
        $shares = $this->shareRepository->findBy(['createdByUser' => $user]);
        $shareData = [];
        foreach ($shares as $share) {
            $shareData[] = [
                'id' => $share->getId(),
                'recipient_email' => $share->getRecipientEmail(),
                'access_type' => $share->getAccessType(),
                'created_at' => $share->getCreatedAt()->format('Y-m-d H:i:s'),
                'expires_at' => $share->getExpiresAt()?->format('Y-m-d H:i:s')
            ];
        }

        return [
            'export_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'personal_data' => $personalData,
            'snippets' => $snippetData,
            'attachments' => $attachmentData,
            'shares' => $shareData
        ];
    }

    /**
     * Anonymize user data (GDPR Article 17 - Right to be forgotten)
     */
    public function anonymizeUser(User $user, string $reason = 'User request'): bool
    {
        try {
            $this->logger->info('User anonymization started', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'reason' => $reason
            ]);

            // Delete or anonymize personal data
            $user->setEmail('anonymized_' . bin2hex(random_bytes(8)) . '@deleted.local');
            $user->setUsername(null);
            $user->setFirstName(null);
            $user->setLastName(null);
            $user->setAvatarUrl(null);
            $user->setPassword(null);
            $user->setOauthProvider(null);
            $user->setOauthId(null);
            $user->setOauthData(null);
            $user->setTwoFactorSecret(null);
            $user->setBackupCodes(null);
            $user->setLastLoginIp(null);
            $user->setGdprConsentAt(null);
            $user->setMarketingConsent(false);
            $user->setDeletedAt(new \DateTimeImmutable());
            $user->setStatus(User::STATUS_DELETED);

            // Anonymize or delete snippets
            $snippets = $this->snippetRepository->findBy(['user' => $user]);
            foreach ($snippets as $snippet) {
                if ($snippet->getVisibility() === 'public') {
                    // Keep public snippets but anonymize ownership
                    $snippet->setTitle('[Anonymized] ' . $snippet->getTitle());
                    $snippet->setDescription('Content from anonymized user');
                } else {
                    // Delete private snippets
                    $this->entityManager->remove($snippet);
                }
            }

            // Delete attachments and files
            $attachments = $this->attachmentRepository->findBy(['user' => $user]);
            foreach ($attachments as $attachment) {
                $this->fileUploadService->deleteFile($attachment);
                $this->entityManager->remove($attachment);
            }

            // Delete shares
            $shares = $this->shareRepository->findBy(['createdByUser' => $user]);
            foreach ($shares as $share) {
                $this->entityManager->remove($share);
            }

            $this->entityManager->flush();

            $this->logger->info('User anonymization completed', [
                'user_id' => $user->getId(),
                'reason' => $reason
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('User anonymization failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Process data retention (delete old data)
     */
    public function processDataRetention(): int
    {
        $cutoffDate = new \DateTimeImmutable('-' . self::DATA_RETENTION_PERIOD);
        $deletedCount = 0;

        // Find users marked for deletion
        $usersToDelete = $this->userRepository->createQueryBuilder('u')
            ->where('u.deletedAt IS NOT NULL')
            ->andWhere('u.deletedAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        foreach ($usersToDelete as $user) {
            if ($this->permanentlyDeleteUser($user)) {
                $deletedCount++;
            }
        }

        // Delete expired snippets
        $expiredSnippets = $this->snippetRepository->createQueryBuilder('s')
            ->where('s.autoExpireAt IS NOT NULL')
            ->andWhere('s.autoExpireAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        foreach ($expiredSnippets as $snippet) {
            $this->entityManager->remove($snippet);
            $deletedCount++;
        }

        $this->entityManager->flush();

        $this->logger->info('Data retention processing completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        return $deletedCount;
    }

    /**
     * Permanently delete user and all associated data
     */
    private function permanentlyDeleteUser(User $user): bool
    {
        try {
            // Delete all attachments and files
            $attachments = $this->attachmentRepository->findBy(['user' => $user]);
            foreach ($attachments as $attachment) {
                $this->fileUploadService->deleteFile($attachment);
                $this->entityManager->remove($attachment);
            }

            // Delete all snippets
            $snippets = $this->snippetRepository->findBy(['user' => $user]);
            foreach ($snippets as $snippet) {
                $this->entityManager->remove($snippet);
            }

            // Delete all shares
            $shares = $this->shareRepository->findBy(['createdByUser' => $user]);
            foreach ($shares as $share) {
                $this->entityManager->remove($share);
            }

            // Delete user
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->logger->info('User permanently deleted', [
                'user_id' => $user->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Permanent user deletion failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send consent renewal reminder
     */
    public function sendConsentRenewalReminder(User $user): void
    {
        $email = (new Email())
            ->from('noreply@prettiops.com')
            ->to($user->getEmail())
            ->subject('PrettiOps - Consent Renewal Required')
            ->html($this->getConsentRenewalEmailTemplate($user));

        $this->mailer->send($email);

        $this->logger->info('Consent renewal reminder sent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);
    }

    /**
     * Get users with expiring consent
     */
    public function getUsersWithExpiringConsent(): array
    {
        $warningDate = new \DateTimeImmutable('+30 days'); // 30 days before expiry
        $consentExpiryDate = (new \DateTimeImmutable())
            ->sub(new \DateInterval(self::CONSENT_VALID_PERIOD))
            ->add(new \DateInterval('P30D'));

        return $this->userRepository->createQueryBuilder('u')
            ->where('u.gdprConsentAt IS NOT NULL')
            ->andWhere('u.gdprConsentAt < :expiry')
            ->andWhere('u.status = :status')
            ->setParameter('expiry', $consentExpiryDate)
            ->setParameter('status', User::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();
    }

    /**
     * Generate data processing report
     */
    public function generateDataProcessingReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        // Count new users
        $newUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        // Count anonymized users
        $anonymizedUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        // Count data exports
        // This would require tracking export requests in a separate table

        return [
            'period' => [
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s')
            ],
            'new_users' => $newUsers,
            'anonymized_users' => $anonymizedUsers,
            'total_active_users' => $this->userRepository->count(['status' => User::STATUS_ACTIVE]),
            'consent_compliance' => $this->getConsentComplianceStats()
        ];
    }

    /**
     * Get consent compliance statistics
     */
    private function getConsentComplianceStats(): array
    {
        $activeUsers = $this->userRepository->findBy(['status' => User::STATUS_ACTIVE]);
        $totalUsers = count($activeUsers);
        $gdprConsent = 0;
        $marketingConsent = 0;
        $validConsent = 0;

        foreach ($activeUsers as $user) {
            if ($user->getGdprConsentAt()) {
                $gdprConsent++;
                if ($this->isConsentValid($user, 'gdpr')) {
                    $validConsent++;
                }
            }
            if ($user->isMarketingConsentGiven()) {
                $marketingConsent++;
            }
        }

        return [
            'total_users' => $totalUsers,
            'gdpr_consent' => $gdprConsent,
            'marketing_consent' => $marketingConsent,
            'valid_consent' => $validConsent,
            'consent_compliance_rate' => $totalUsers > 0 ? round(($validConsent / $totalUsers) * 100, 2) : 0
        ];
    }

    /**
     * Get consent renewal email template
     */
    private function getConsentRenewalEmailTemplate(User $user): string
    {
        return sprintf('
            <h2>Consent Renewal Required</h2>
            <p>Hello %s,</p>
            <p>Your data processing consent for PrettiOps will expire soon. To continue using our services, please renew your consent.</p>
            <p><a href="%s/consent/renew?token=%s">Renew Consent</a></p>
            <p>If you do not renew your consent, your account will be anonymized according to GDPR regulations.</p>
            <p>Best regards,<br>The PrettiOps Team</p>
        ', 
            $user->getFirstName() ?? $user->getEmail(),
            $_ENV['APP_URL'] ?? 'https://prettiops.com',
            base64_encode($user->getId())
        );
    }

    /**
     * Validate data processing lawful basis
     */
    public function validateLawfulBasis(User $user, string $purpose): bool
    {
        switch ($purpose) {
            case 'service_provision':
                // Legitimate interest for providing the service
                return $user->isActive();
                
            case 'marketing':
                // Requires explicit consent
                return $user->isMarketingConsentGiven();
                
            case 'analytics':
                // Requires GDPR consent
                return $this->isConsentValid($user, 'gdpr');
                
            case 'security':
                // Legitimate interest for security
                return true;
                
            default:
                return false;
        }
    }

    /**
     * Log data access for audit trail
     */
    public function logDataAccess(User $user, string $dataType, string $purpose): void
    {
        $this->logger->info('User data accessed', [
            'user_id' => $user->getId(),
            'data_type' => $dataType,
            'purpose' => $purpose,
            'timestamp' => new \DateTimeImmutable(),
            'lawful_basis_valid' => $this->validateLawfulBasis($user, $purpose)
        ]);
    }
}