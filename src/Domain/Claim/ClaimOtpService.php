<?php

declare(strict_types=1);

namespace App\Domain\Claim;

use App\Entity\Core\LocationClaimOtp;
use App\Entity\Core\LocationClaimRequest;

final class ClaimOtpService
{
    public const DEFAULT_EXPIRES_IN_SECONDS = 600;
    public const DEFAULT_MAX_ATTEMPTS = 3;
    public const VERIFY_VALID = 'valid';
    public const VERIFY_INVALID = 'invalid';
    public const VERIFY_EXPIRED = 'expired';
    public const VERIFY_ATTEMPTS_EXHAUSTED = 'attempts_exhausted';

    /**
     * @return array{code: string, entity: LocationClaimOtp}
     */
    public function generateOtp(LocationClaimRequest $claim, string $purpose, int $expiresInSeconds = self::DEFAULT_EXPIRES_IN_SECONDS, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($code, PASSWORD_DEFAULT);

        $now = new \DateTimeImmutable();
        
        $otp = (new LocationClaimOtp());
        
        $reflection = new \ReflectionClass($otp);
        $claimProp = $reflection->getProperty('claim');
        $claimProp->setValue($otp, $claim);
        
        $purposeProp = $reflection->getProperty('purpose');
        $purposeProp->setValue($otp, $purpose);
        
        $codeHashProp = $reflection->getProperty('codeHash');
        $codeHashProp->setValue($otp, $hash);
        
        $expiresAtProp = $reflection->getProperty('expiresAt');
        $expiresAtProp->setValue($otp, $now->modify(sprintf('+%d seconds', $expiresInSeconds)));
        
        $requestedAtProp = $reflection->getProperty('requestedAt');
        $requestedAtProp->setValue($otp, $now);
        
        $maxAttemptsProp = $reflection->getProperty('maxAttempts');
        $maxAttemptsProp->setValue($otp, $maxAttempts);

        return [
            'code' => $code,
            'entity' => $otp,
        ];
    }

    public function verifyOtp(LocationClaimOtp $otp, string $code): bool
    {
        return $this->verifyOtpResult($otp, $code) === self::VERIFY_VALID;
    }

    public function verifyOtpResult(LocationClaimOtp $otp, string $code): string
    {
        if ($otp->getConsumedAt() !== null) {
            return self::VERIFY_INVALID;
        }

        $now = new \DateTimeImmutable();

        $reflection = new \ReflectionClass($otp);
        $lastAttemptAtProp = $reflection->getProperty('lastAttemptAt');
        $lastAttemptAtProp->setValue($otp, $now);
        
        $attemptCountProp = $reflection->getProperty('attemptCount');
        $currentAttempts = $attemptCountProp->getValue($otp);
        $attemptCountProp->setValue($otp, $currentAttempts + 1);

        if ($now > $otp->getExpiresAt()) {
            return self::VERIFY_EXPIRED;
        }

        if ($currentAttempts >= $otp->getMaxAttempts()) {
            return self::VERIFY_ATTEMPTS_EXHAUSTED;
        }

        if (!password_verify($code, $otp->getCodeHash())) {
            return self::VERIFY_INVALID;
        }

        $consumedAtProp = $reflection->getProperty('consumedAt');
        $consumedAtProp->setValue($otp, $now);

        return self::VERIFY_VALID;
    }
}
