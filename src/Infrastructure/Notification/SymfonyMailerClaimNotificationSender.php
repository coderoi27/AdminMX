<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Claim\ClaimNotificationSenderInterface;
use App\Entity\Core\LocationClaimRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SymfonyMailerClaimNotificationSender implements ClaimNotificationSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(string:CLAIM_FROM_EMAIL)%')] private string $fromEmail,
        #[Autowire('%env(string:CLAIM_FROM_NAME)%')] private string $fromName,
        #[Autowire('%env(string:PUBLIC_BASE_URL)%')] private string $publicBaseUrl
    ) {
    }

    public function sendEmailOtp(LocationClaimRequest $claim, string $otpCode): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($claim->getEmail())
            ->subject('Tu código para continuar con tu reclamación')
            ->htmlTemplate('emails/claim/otp.html.twig')
            ->textTemplate('emails/claim/otp.txt.twig')
            ->context([
                'otp_code' => $otpCode,
                'location_name' => $claim->getLocationName(),
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('OTP email dispatched successfully.', [
                'claim_uuid' => $claim->getClaimUuid()
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send OTP email via SMTP.', [
                'claim_uuid' => $claim->getClaimUuid(),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('claim_notification_unavailable', 503, $e);
        }
    }

    public function sendResumeLink(LocationClaimRequest $claim, string $resumeToken): void
    {
        $resumeUrl = sprintf('%s/claim/resume/%s', rtrim($this->publicBaseUrl, '/'), rawurlencode($resumeToken));

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($claim->getEmail())
            ->subject('Continuar con tu reclamación de local')
            ->htmlTemplate('emails/claim/resume.html.twig')
            ->textTemplate('emails/claim/resume.txt.twig')
            ->context([
                'resume_url' => $resumeUrl,
                'location_name' => $claim->getLocationName(),
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Resume link email dispatched successfully.', [
                'claim_uuid' => $claim->getClaimUuid()
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send resume link email via SMTP.', [
                'claim_uuid' => $claim->getClaimUuid(),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('claim_notification_unavailable', 503, $e);
        }
    }

    public function sendSubmissionConfirmation(LocationClaimRequest $claim): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($claim->getEmail())
            ->subject('Hemos recibido tu solicitud de reclamación')
            ->htmlTemplate('emails/claim/submitted.html.twig')
            ->textTemplate('emails/claim/submitted.txt.twig')
            ->context([
                'location_name' => $claim->getLocationName(),
                'claim_uuid' => $claim->getClaimUuid(),
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Submission confirmation email dispatched successfully.', [
                'claim_uuid' => $claim->getClaimUuid()
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send submission confirmation email via SMTP.', [
                'claim_uuid' => $claim->getClaimUuid(),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('claim_notification_unavailable', 503, $e);
        }
    }
}
