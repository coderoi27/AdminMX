<?php

declare(strict_types=1);

namespace App\Domain\Claim\Exception;

final class ClaimOtpException extends \DomainException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $safeMessage,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($safeMessage, $httpStatus);
    }

    public static function invalid(): self
    {
        return new self('claim_otp_invalid', 'El código es incorrecto o expiró.');
    }

    public static function expired(): self
    {
        return new self('claim_otp_expired', 'El código expiró. Solicita uno nuevo.');
    }

    public static function attemptsExhausted(): self
    {
        return new self('claim_otp_attempts_exhausted', 'Se agotaron los intentos. Solicita un código nuevo.', 429);
    }
}
