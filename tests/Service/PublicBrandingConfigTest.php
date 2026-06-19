<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PublicBrandingConfig;
use PHPUnit\Framework\TestCase;

final class PublicBrandingConfigTest extends TestCase
{
    public function testNormalizesPartialSocialConfigWithoutLosingDefaults(): void
    {
        $config = (new PublicBrandingConfig())->normalize([
            'app_name' => 'Directorio Demo',
            'social_share' => ['facebook_title' => 'Facebook especial'],
        ]);

        self::assertSame('Directorio Demo', $config['app_name']);
        self::assertSame('Facebook especial', $config['social_share']['facebook_title']);
        self::assertSame('summary_large_image', $config['social_share']['twitter_card_type']);
        self::assertArrayHasKey('threads_image', $config['social_share']);
    }

    public function testResolvesPlatformFallbacks(): void
    {
        $previews = (new PublicBrandingConfig())->resolvedPreviews([
            'default_meta_title' => 'Título base',
            'default_meta_description' => 'Descripción base',
            'social_share' => [
                'default_og_title' => 'Título OG',
                'facebook_title' => 'Título Facebook',
            ],
        ]);

        self::assertSame('Título OG', $previews['open_graph']['title']);
        self::assertSame('Título Facebook', $previews['facebook']['title']);
        self::assertSame('Título OG', $previews['x_twitter']['title']);
        self::assertSame('Título base', $previews['google']['title']);
    }
}
