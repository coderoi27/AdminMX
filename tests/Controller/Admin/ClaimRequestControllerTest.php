<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Domain\Claim\ClaimNotificationSenderInterface;
use App\Entity\Admin\AdminUser;
use App\Entity\Core\EventLog;
use App\Entity\Core\LocationClaimEvidence;
use App\Entity\Core\LocationClaimRequest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClaimRequestControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $claimId;
    private int $evidenceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadataFactory = $entityManager->getMetadataFactory();
        $metadata = [
            $metadataFactory->getMetadataFor(AdminUser::class),
            $metadataFactory->getMetadataFor(LocationClaimRequest::class),
            $metadataFactory->getMetadataFor(LocationClaimEvidence::class),
            $metadataFactory->getMetadataFor(EventLog::class),
        ];
        (new SchemaTool($entityManager))->createSchema($metadata);

        $admin = (new AdminUser())
            ->setEmail('claims-admin@example.test')
            ->setFullName('Claims Admin')
            ->setPasswordHash('not-used-in-functional-test')
            ->setRoleKey('operator');
        $claim = (new LocationClaimRequest())
            ->setClaimUuid('01978c8f-f3d7-72e0-b46d-0800200c9a66')
            ->setSourceType('google_places')
            ->setExternalSourceKey('ChIJ_A6_TEST')
            ->setLocationName('Local A6')
            ->setShortAddress('Dirección de prueba')
            ->setClaimantName('Persona reclamante')
            ->setEmail('claimant@example.test')
            ->setStatus(LocationClaimRequest::STATUS_SUBMITTED);
        $evidence = (new LocationClaimEvidence())
            ->setClaim($claim)
            ->setEvidenceType('ownership_document')
            ->setStorageProvider('cloudflare_r2')
            ->setBucketName('private-a6-bucket')
            ->setObjectKey('claims/private/object-key.pdf')
            ->setOriginalFilename('evidencia.pdf')
            ->setMimeType('application/pdf')
            ->setSizeBytes(15)
            ->setChecksumSha256('1234567890abcdef1234567890abcdef')
            ->setStatus(LocationClaimEvidence::STATUS_UPLOADED)
            ->setUploadedAt(new \DateTimeImmutable());

        $entityManager->persist($admin);
        $entityManager->persist($claim);
        $entityManager->persist($evidence);
        $entityManager->flush();

        $this->claimId = (int) $claim->getId();
        $this->evidenceId = (int) $evidence->getId();
        $this->client->loginUser($admin);
    }

    public function testListAndDetailDoNotExposePrivateStorageIdentifiers(): void
    {
        $this->client->request('GET', '/claims?status=submitted&source=google_places');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Local A6');

        $this->client->request('GET', sprintf('/claims/%d', $this->claimId));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'evidencia.pdf');
        self::assertStringNotContainsString('private-a6-bucket', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('claims/private/object-key.pdf', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('ETag 1234567890ab', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('1234567890abcdef1234567890abcdef', (string) $this->client->getResponse()->getContent());
    }

    public function testDetailShowsPrivateEvidenceSignalsForHumanReview(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $evidence = $entityManager->find(LocationClaimEvidence::class, $this->evidenceId);
        $evidence?->setMetadataJson([
            'capture_mode' => 'live_capture',
            'has_audio' => true,
            'challenge_text' => 'Hola Mi Monchis. Mi código es 4827.',
            'capture_started_at' => '2026-07-07T12:00:00+00:00',
            'geolocation_status' => 'granted',
            'accuracy_meters' => 30,
            'distance_meters' => 42,
            'location_signal' => 'near',
            'expected_latitude' => 20.0,
            'expected_longitude' => -87.0,
            'captured_latitude' => 20.0001,
            'captured_longitude' => -87.0001,
        ]);
        $entityManager->flush();

        $this->client->request('GET', sprintf('/claims/%d', $this->claimId));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Señales de evidencia');
        self::assertSelectorTextContains('body', 'Estas señales apoyan la revisión humana y no constituyen una validación automática.');
        self::assertSelectorTextContains('body', 'live_capture');
        self::assertSelectorTextContains('body', 'near');
        self::assertSelectorTextContains('body', '42 m');

        $entityManager->clear();
        self::assertSame(LocationClaimRequest::STATUS_SUBMITTED, $entityManager->find(LocationClaimRequest::class, $this->claimId)?->getStatus());
    }

    public function testNotesAndUnderReviewTransitionArePersistedAndAudited(): void
    {
        $crawler = $this->client->request('GET', sprintf('/claims/%d', $this->claimId));
        $notesToken = $crawler->filter(sprintf('form[action="/claims/%d/notes"] input[name="_token"]', $this->claimId))->attr('value');

        $this->client->request('POST', sprintf('/claims/%d/notes', $this->claimId), [
            '_token' => $notesToken,
            'review_notes' => 'Nota interna A6',
            'check_contact_verified' => '1',
        ]);
        self::assertResponseRedirects(sprintf('/claims/%d', $this->claimId));

        $crawler = $this->client->request('GET', sprintf('/claims/%d', $this->claimId));
        $statusToken = $crawler->filter(sprintf('form[action="/claims/%d/status"] input[name="_token"]', $this->claimId))->attr('value');
        $this->client->request('POST', sprintf('/claims/%d/status', $this->claimId), [
            '_token' => $statusToken,
            'status' => LocationClaimRequest::STATUS_UNDER_REVIEW,
            'review_notes' => 'Nota interna A6',
            'check_contact_verified' => '1',
        ]);
        self::assertResponseRedirects(sprintf('/claims/%d', $this->claimId));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $claim = $entityManager->find(LocationClaimRequest::class, $this->claimId);
        self::assertSame(LocationClaimRequest::STATUS_UNDER_REVIEW, $claim?->getStatus());
        self::assertSame('Nota interna A6', $claim?->getReviewNotes());
        self::assertSame(2, $entityManager->getRepository(EventLog::class)->count([
            'entityType' => 'location_claim_request',
            'entityId' => $this->claimId,
            'sourceApp' => 'admin',
        ]));
    }

    public function testPendingEvidenceChecklistCopyAndNotesDoNotChangeStatusOrSendEmail(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $claim = $entityManager->find(LocationClaimRequest::class, $this->claimId);
        self::assertNotNull($claim);
        $status = new \ReflectionProperty(LocationClaimRequest::class, 'status');
        $status->setValue($claim, LocationClaimRequest::STATUS_PENDING_EVIDENCE);
        $entityManager->flush();

        $notificationSender = static::getContainer()->get(ClaimNotificationSenderInterface::class);
        $messagesBefore = method_exists($notificationSender, 'getSentMessages') ? count($notificationSender->getSentMessages()) : 0;

        $crawler = $this->client->request('GET', sprintf('/claims/%d', $this->claimId));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Este checklist documenta la revisión interna. Guardarlo no cambia el estado del Claim.');
        self::assertSelectorTextContains('body', 'Las transiciones estarán disponibles después de que el reclamante complete y envíe la solicitud.');
        self::assertSelectorTextContains('body', 'No hay transiciones administrativas disponibles.');

        $notesToken = $crawler->filter(sprintf('form[action="/claims/%d/notes"] input[name="_token"]', $this->claimId))->attr('value');
        $this->client->request('POST', sprintf('/claims/%d/notes', $this->claimId), [
            '_token' => $notesToken,
            'review_notes' => 'Checklist documentado sin transición',
            'check_contact_verified' => '1',
        ]);
        self::assertResponseRedirects(sprintf('/claims/%d', $this->claimId));

        $entityManager->clear();
        $claim = $entityManager->find(LocationClaimRequest::class, $this->claimId);
        self::assertSame(LocationClaimRequest::STATUS_PENDING_EVIDENCE, $claim?->getStatus());
        self::assertSame('Checklist documentado sin transición', $claim?->getReviewNotes());
        self::assertSame(1, $entityManager->getRepository(EventLog::class)->count([
            'eventName' => 'admin_claim_notes_updated',
            'entityId' => $this->claimId,
        ]));

        $messagesAfter = method_exists($notificationSender, 'getSentMessages') ? count($notificationSender->getSentMessages()) : 0;
        self::assertSame($messagesBefore, $messagesAfter);
    }

    public function testEvidenceIsProxiedAndAuditedWithoutReturningSignedUrl(): void
    {
        $storage = $this->createMock(ClaimEvidenceStorageInterface::class);
        $storage->expects(self::once())
            ->method('createReadUrl')
            ->with('claims/private/object-key.pdf', 120)
            ->willReturn('https://storage.example.test/signed-private-get');
        $httpClient = new MockHttpClient([
            new MockResponse('private-content', ['http_code' => 200]),
        ]);
        static::getContainer()->set(ClaimEvidenceStorageInterface::class, $storage);
        static::getContainer()->set(HttpClientInterface::class, $httpClient);

        $this->client->request('GET', sprintf('/claims/%d/evidence/%d', $this->claimId, $this->evidenceId));

        self::assertResponseIsSuccessful();
        self::assertSame(1, $httpClient->getRequestsCount());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertFalse($this->client->getResponse()->headers->has('Location'));
        self::assertFalse($this->client->getResponse()->getContent());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(1, $entityManager->getRepository(EventLog::class)->count([
            'eventName' => 'admin_claim_evidence_viewed',
            'entityId' => $this->claimId,
        ]));
    }

    public function testEvidenceRouteRequiresAdminAuthentication(): void
    {
        static::ensureKernelShutdown();
        $anonymousClient = static::createClient();
        $anonymousClient->request('GET', sprintf('/claims/%d/evidence/%d', $this->claimId, $this->evidenceId));

        self::assertResponseRedirects('http://localhost/login');
    }

    #[DataProvider('reviewTransitions')]
    public function testReviewTransitionsRemainAdministrativeOnly(string $targetStatus, string $reviewNotes): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $claim = $entityManager->find(LocationClaimRequest::class, $this->claimId);
        $claim?->setStatus(LocationClaimRequest::STATUS_UNDER_REVIEW);
        $entityManager->flush();

        $crawler = $this->client->request('GET', sprintf('/claims/%d', $this->claimId));
        $statusToken = $crawler->filter(sprintf('form[action="/claims/%d/status"] input[name="_token"]', $this->claimId))->attr('value');
        $this->client->request('POST', sprintf('/claims/%d/status', $this->claimId), [
            '_token' => $statusToken,
            'status' => $targetStatus,
            'review_notes' => $reviewNotes,
            'check_contact_verified' => '1',
            'check_ownership_evidence' => '1',
            'check_location_match' => '1',
        ]);

        self::assertResponseRedirects(sprintf('/claims/%d', $this->claimId));
        $entityManager->clear();
        self::assertSame($targetStatus, $entityManager->find(LocationClaimRequest::class, $this->claimId)?->getStatus());
        self::assertSame(1, $entityManager->getRepository(EventLog::class)->count([
            'eventName' => 'admin_claim_status_changed',
            'entityId' => $this->claimId,
        ]));
    }

    /** @return iterable<string, array{string, string}> */
    public static function reviewTransitions(): iterable
    {
        yield 'needs info' => [LocationClaimRequest::STATUS_NEEDS_INFO, 'Falta información verificable'];
        yield 'approved without materialization' => [LocationClaimRequest::STATUS_APPROVED, 'Validación administrativa completa'];
        yield 'rejected' => [LocationClaimRequest::STATUS_REJECTED, 'La evidencia no acredita propiedad'];
    }
}
