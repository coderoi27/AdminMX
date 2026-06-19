<?php

declare(strict_types=1);

namespace App\Command\Claim;

use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Domain\Claim\Exception\ClaimObjectNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Aws\S3\S3ClientInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:claims:r2-smoke-test',
    description: 'Runs a full lifecycle test against the R2 storage adapter without touching the database.'
)]
final class R2SmokeTestCommand extends Command
{
    private ClaimEvidenceStorageInterface $storage;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $environment;

    public function __construct(
        ClaimEvidenceStorageInterface $storage,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $environment
    ) {
        parent::__construct();
        $this->storage = $storage;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->environment = $environment;
    }

    protected function configure(): void
    {
        $this
            ->addOption('content-type', null, InputOption::VALUE_REQUIRED, 'MIME type to test', 'text/plain')
            ->addOption('keep-object', null, InputOption::VALUE_NONE, 'Do not delete the object at the end')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->environment === 'prod') {
            $output->writeln('<error>This command cannot be run in the production environment.</error>');
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $isJson = $input->getOption('json');
        $contentType = $input->getOption('content-type');
        $keepObject = $input->getOption('keep-object');

        if (!$isJson) {
            $io->title('Cloudflare R2 Storage Smoke Test');
        }

        $uuid = Uuid::v4()->toRfc4122();
        
        // We will fake the content type map for the test if it's text/plain
        // Wait, prepareUpload validates the MIME type against the map.
        // We should use an allowed MIME type or add text/plain to the map in the adapter.
        // For the smoke test, we'll just use 'application/pdf' or 'image/jpeg' to pass the internal validation.
        $allowedMimeType = 'application/pdf';
        if ($contentType === 'text/plain') {
            if (!$isJson) $io->note('Using application/pdf to pass MIME validation instead of text/plain.');
            $contentType = $allowedMimeType;
        }
        
        $testContent = 'SMOKE TEST CONTENT ' . $uuid;
        $sizeBytes = strlen($testContent);

        try {
            // 1 & 2. Prepare upload
            if (!$isJson) $io->section('1. Preparing Upload');
            $uploadData = $this->storage->prepareUpload($uuid, 'smoke_test', $contentType, $sizeBytes);
            $objectKey = $uploadData['object_key'];
            $uploadUrl = $uploadData['upload_url'];

            if (!$isJson) {
                $io->success("Prepared upload for key: {$objectKey}");
            }

            // 3. Upload content
            if (!$isJson) $io->section('2. Uploading Content via HTTP PUT');
            $response = $this->httpClient->request('PUT', $uploadUrl, [
                'headers' => [
                    'Content-Type' => $contentType,
                ],
                'body' => $testContent,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Upload failed: HTTP ' . $response->getStatusCode() . ' ' . $response->getContent(false));
            }

            if (!$isJson) {
                $io->success("Content uploaded successfully.");
            }

            // 4. HeadObject (inspectObject)
            if (!$isJson) $io->section('3. Inspecting Object');
            $metadata = $this->storage->inspectObject($objectKey);

            if ($metadata['size_bytes'] !== $sizeBytes) {
                throw new \RuntimeException('Size mismatch. Expected: ' . $sizeBytes . ', Got: ' . $metadata['size_bytes']);
            }

            if (!$isJson) {
                $io->success("Object inspected successfully. Size: {$metadata['size_bytes']} bytes.");
            }

            // 5 & 6. GetObject
            if (!$isJson) $io->section('4. Reading Content via HTTP GET');
            $readUrl = $this->storage->createReadUrl($objectKey, 60);
            $readResponse = $this->httpClient->request('GET', $readUrl);

            if ($readResponse->getStatusCode() !== 200) {
                throw new \RuntimeException('Read failed: HTTP ' . $readResponse->getStatusCode());
            }

            $downloadedContent = $readResponse->getContent();
            if ($downloadedContent !== $testContent) {
                throw new \RuntimeException('Content mismatch on read.');
            }

            if (!$isJson) {
                $io->success("Content read successfully and matches.");
            }

            // 7. Delete Object
            if (!$keepObject) {
                if (!$isJson) $io->section('5. Deleting Object');
                $this->storage->deleteObject($objectKey);

                // 8. Confirm deletion
                try {
                    $this->storage->inspectObject($objectKey);
                    throw new \RuntimeException('Object still exists after deletion!');
                } catch (ClaimObjectNotFoundException $e) {
                    if (!$isJson) {
                        $io->success("Object successfully deleted (404 confirmed).");
                    }
                }
            } else {
                if (!$isJson) {
                    $io->note("Skipping deletion as --keep-object was passed.");
                }
            }

            if ($isJson) {
                $output->writeln(json_encode([
                    'status' => 'success',
                    'object_key' => $objectKey,
                    'provider' => get_class($this->storage)
                ]));
            } else {
                $io->success('Smoke test completed successfully!');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Smoke test failed', ['exception' => $e->getMessage()]);
            
            if ($isJson) {
                $output->writeln(json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]));
            } else {
                $io->error('Smoke test failed: ' . $e->getMessage());
            }

            return Command::FAILURE;
        }
    }
}
