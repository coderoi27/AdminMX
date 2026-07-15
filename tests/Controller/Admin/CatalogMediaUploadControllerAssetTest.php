<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use PHPUnit\Framework\TestCase;

final class CatalogMediaUploadControllerAssetTest extends TestCase
{
    public function testStimulusUploadControllerUsesDirectPutWithoutCredentialsOrAuthorization(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 3).'/assets/controllers/catalog_media_upload_controller.js');
        self::assertIsString($controller);

        self::assertStringContainsString("xhr.open('PUT', item.uploadUrl)", $controller);
        self::assertStringContainsString('xhr.withCredentials = false', $controller);
        self::assertStringContainsString("xhr.getResponseHeader('ETag')", $controller);
        self::assertStringContainsString('R2 no expuso ETag. Revisa CORS del bucket.', $controller);
        self::assertStringContainsString('retryFailed', $controller);
        self::assertStringContainsString('multiple', file_get_contents(dirname(__DIR__, 3).'/templates/admin/catalog_media/index.html.twig'));
        self::assertStringNotContainsString('Authorization', $controller);
        self::assertStringNotContainsString('Bearer', $controller);
        self::assertStringNotContainsString('localStorage', $controller);
        self::assertStringNotContainsString('sessionStorage', $controller);
        self::assertStringNotContainsString('indexedDB', $controller);
    }
}
