<?php

declare(strict_types=1);

namespace App\Service;

final class PublicBrandingConfig
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'contract_version' => '2026-06-19',
            'app_name' => 'Mi Monchis',
            'logo_horizontal_url' => '/images/branding/logo-simple-horizontal.png',
            'logo_square_url' => '/images/branding/logo-simple-square.png',
            'favicon_url' => '/favicon.ico',
            'theme_color' => '#ff7a00',
            'default_meta_title' => 'Mi Monchis MX',
            'default_meta_description' => 'Explora locales cerca de ti con Mi Monchis MX.',
            'asset_manifest' => [],
            'social_share' => [
                'default_og_title' => '',
                'default_og_description' => '',
                'default_og_image' => '',
                'twitter_card_type' => 'summary_large_image',
                'twitter_title' => '',
                'twitter_description' => '',
                'twitter_image' => '',
                'facebook_title' => '',
                'facebook_description' => '',
                'facebook_image' => '',
                'threads_title' => '',
                'threads_description' => '',
                'threads_image' => '',
                'google_title' => '',
                'google_description' => '',
                'google_image' => '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function normalize(array $config): array
    {
        $normalized = array_replace_recursive($this->defaults(), $config);
        $normalized['contract_version'] = '2026-06-19';

        return $normalized;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, array<string, string>>
     */
    public function resolvedPreviews(array $config): array
    {
        $config = $this->normalize($config);
        $social = $config['social_share'];
        $baseTitle = $this->firstString($config['default_meta_title'], $config['app_name']);
        $baseDescription = $this->firstString($config['default_meta_description']);
        $baseImage = $this->firstString($config['logo_horizontal_url'], $config['logo_square_url']);
        $ogTitle = $this->firstString($social['default_og_title'], $baseTitle);
        $ogDescription = $this->firstString($social['default_og_description'], $baseDescription);
        $ogImage = $this->firstString($social['default_og_image'], $baseImage);

        return [
            'base' => [
                'title' => $baseTitle,
                'description' => $baseDescription,
                'image' => $baseImage,
            ],
            'open_graph' => [
                'title' => $ogTitle,
                'description' => $ogDescription,
                'image' => $ogImage,
            ],
            'facebook' => [
                'title' => $this->firstString($social['facebook_title'], $ogTitle),
                'description' => $this->firstString($social['facebook_description'], $ogDescription),
                'image' => $this->firstString($social['facebook_image'], $ogImage),
            ],
            'x_twitter' => [
                'title' => $this->firstString($social['twitter_title'], $ogTitle),
                'description' => $this->firstString($social['twitter_description'], $ogDescription),
                'image' => $this->firstString($social['twitter_image'], $ogImage),
                'card_type' => $this->firstString($social['twitter_card_type'], 'summary_large_image'),
            ],
            'threads' => [
                'title' => $this->firstString($social['threads_title'], $ogTitle),
                'description' => $this->firstString($social['threads_description'], $ogDescription),
                'image' => $this->firstString($social['threads_image'], $ogImage),
            ],
            'google' => [
                'title' => $this->firstString($social['google_title'], $baseTitle),
                'description' => $this->firstString($social['google_description'], $baseDescription),
                'image' => $this->firstString($social['google_image'], $ogImage),
            ],
        ];
    }

    private function firstString(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
