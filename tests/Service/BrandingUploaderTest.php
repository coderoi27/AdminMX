<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BrandingUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BrandingUploaderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/mimonchis-branding-'.bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $directory = $this->projectDir.'/public/uploads/branding';
        if (is_dir($directory)) {
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
            @rmdir(dirname($directory));
            @rmdir(dirname(dirname($directory)));
        }
        @rmdir($this->projectDir);
    }

    public function testStoresSafeSvgAndReturnsManifest(): void
    {
        $source = $this->projectDir.'/safe.svg';
        file_put_contents($source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M0 0h20v20H0z"/></svg>');

        $asset = (new BrandingUploader($this->projectDir))->upload(
            new UploadedFile($source, 'Mi Logo.svg', 'image/svg+xml', null, true),
            'logo_square',
        );

        self::assertSame('logo_square', $asset['slot']);
        self::assertSame('image/svg+xml', $asset['mime_type']);
        self::assertStringStartsWith('/uploads/branding/logo_square-', $asset['url']);
        self::assertFileExists($this->projectDir.'/public'.$asset['url']);
        self::assertSame(64, strlen($asset['sha256']));
    }

    public function testRejectsSvgWithActiveContent(): void
    {
        $source = $this->projectDir.'/unsafe.svg';
        file_put_contents($source, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('active or external content');

        (new BrandingUploader($this->projectDir))->upload(
            new UploadedFile($source, 'unsafe.svg', 'image/svg+xml', null, true),
            'logo_square',
        );
    }

    public function testDeletesOnlyManagedPublicUrls(): void
    {
        $source = $this->projectDir.'/safe.svg';
        file_put_contents($source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"/>');
        $uploader = new BrandingUploader($this->projectDir);
        $asset = $uploader->upload(new UploadedFile($source, 'safe.svg', 'image/svg+xml', null, true), 'favicon');
        $storedPath = $this->projectDir.'/public'.$asset['url'];

        $uploader->deleteByPublicUrl('/images/branding/default.svg');
        self::assertFileExists($storedPath);

        $uploader->deleteByPublicUrl($asset['url']);
        self::assertFileDoesNotExist($storedPath);
    }
}
