<?php

declare(strict_types=1);

namespace App\Service\Core;

final class InvitationTokenFactory
{
    public function createPlainToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
