<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BrandingUploader
{
    private const PUBLIC_PREFIX = '/uploads/branding/';

    /** @var array<string, true> */
    private const ALLOWED_SLOTS = [
        'logo_horizontal' => true,
        'logo_square' => true,
        'favicon' => true,
        'default_og_image' => true,
        'twitter_image' => true,
        'facebook_image' => true,
        'threads_image' => true,
        'google_image' => true,
    ];

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    private readonly string $targetDirectory;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $this->targetDirectory = $projectDir.'/public/uploads/branding';
    }

    /**
     * @return array{
     *     slot: string,
     *     url: string,
     *     storage: string,
     *     original_name: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     sha256: string,
     *     uploaded_at: string
     * }
     */
    public function upload(UploadedFile $file, string $slot): array
    {
        if (!isset(self::ALLOWED_SLOTS[$slot])) {
            throw new \InvalidArgumentException(sprintf('Unsupported branding asset slot "%s".', $slot));
        }

        if (!$file->isValid()) {
            throw new \RuntimeException(sprintf('The upload for branding slot "%s" is not valid.', $slot));
        }

        $mimeType = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;
        if ($extension === null) {
            throw new \RuntimeException(sprintf('Unsupported branding MIME type "%s".', $mimeType));
        }

        if ($mimeType === 'image/svg+xml') {
            $this->assertSafeSvg($file);
        }

        $this->ensureTargetDirectory();

        $fileName = sprintf('%s-%s-%s.%s', $slot, date('YmdHis'), bin2hex(random_bytes(8)), $extension);

        try {
            $storedFile = $file->move($this->targetDirectory, $fileName);
            @chmod($storedFile->getPathname(), 0644);
        } catch (FileException $exception) {
            throw new \RuntimeException('Unable to store the branding asset.', previous: $exception);
        }

        return [
            'slot' => $slot,
            'url' => self::PUBLIC_PREFIX.$fileName,
            'storage' => 'admin_public_uploads',
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mimeType,
            'size_bytes' => (int) $storedFile->getSize(),
            'sha256' => hash_file('sha256', $storedFile->getPathname()),
            'uploaded_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    public function deleteByPublicUrl(?string $publicUrl): void
    {
        if ($publicUrl === null || !str_starts_with($publicUrl, self::PUBLIC_PREFIX)) {
            return;
        }

        $fileName = basename($publicUrl);
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return;
        }

        $path = $this->targetDirectory.'/'.$fileName;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    private function ensureTargetDirectory(): void
    {
        if (is_dir($this->targetDirectory)) {
            return;
        }

        if (!mkdir($this->targetDirectory, 0755, true) && !is_dir($this->targetDirectory)) {
            throw new \RuntimeException('Unable to create the branding asset directory.');
        }
    }

    private function assertSafeSvg(UploadedFile $file): void
    {
        $contents = file_get_contents($file->getPathname());
        if ($contents === false || trim($contents) === '') {
            throw new \RuntimeException('The SVG branding asset is empty or unreadable.');
        }

        $unsafePatterns = [
            '/<!DOCTYPE/i',
            '/<!ENTITY/i',
            '/<script\b/i',
            '/<foreignObject\b/i',
            '/\son[a-z]+\s*=/i',
            '/javascript\s*:/i',
            '/(?:href|src)\s*=\s*["\']\s*(?:https?:|\/\/|data:)/i',
            '/url\s*\(\s*["\']?\s*(?:https?:|\/\/|data:)/i',
        ];

        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                throw new \RuntimeException('The SVG contains active or external content and cannot be stored.');
            }
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $document->documentElement?->localName !== 'svg') {
            throw new \RuntimeException('The uploaded SVG is not a valid SVG document.');
        }
    }
}
