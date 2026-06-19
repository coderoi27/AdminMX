<?php

declare(strict_types=1);

namespace App\Domain\Claim;

use App\Entity\Core\LocationClaimOtp;
use App\Entity\Core\LocationClaimRequest;

final class ClaimOtpService
{
    /**
     * @return array{code: string, entity: LocationClaimOtp}
     */
    public function generateOtp(LocationClaimRequest $claim, string $purpose, int $expiresInSeconds = 600, int $maxAttempts = 3): array
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
        if ($otp->getConsumedAt() !== null) {
            return false;
        }

        $now = new \DateTimeImmutable();

        $reflection = new \ReflectionClass($otp);
        $lastAttemptAtProp = $reflection->getProperty('lastAttemptAt');
        $lastAttemptAtProp->setValue($otp, $now);
        
        $attemptCountProp = $reflection->getProperty('attemptCount');
        $currentAttempts = $attemptCountProp->getValue($otp);
        $attemptCountProp->setValue($otp, $currentAttempts + 1);

        if ($now > $otp->getExpiresAt()) {
            return false;
        }

        if ($currentAttempts >= $otp->getMaxAttempts()) {
            return false;
        }

        if (!password_verify($code, $otp->getCodeHash())) {
            return false;
        }

        $consumedAtProp = $reflection->getProperty('consumedAt');
        $consumedAtProp->setValue($otp, $now);

        return true;
    }
}
