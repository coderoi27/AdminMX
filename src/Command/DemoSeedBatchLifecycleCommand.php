<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\DemoSeedBatch;
use App\Service\Admin\DemoSeedBatchLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo-batches:lifecycle',
    description: 'Expires due demo batches and can logically purge a specific demo seed batch.',
)]
final class DemoSeedBatchLifecycleCommand extends Command
{
    public function __construct(
        private readonly DemoSeedBatchLifecycleService $lifecycleService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'Action to run: expire-due or purge.', 'expire-due')
            ->addOption('batch-id', null, InputOption::VALUE_REQUIRED, 'Required for purge. Demo seed batch id to purge.')
            ->addOption('now', null, InputOption::VALUE_REQUIRED, 'Reference datetime for expiration, any DateTimeImmutable-compatible value.', 'now');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = (string) $input->getArgument('action');

        try {
            $now = new \DateTimeImmutable((string) $input->getOption('now'));
        } catch (\Throwable $exception) {
            $io->error(sprintf('Invalid --now value: %s', $exception->getMessage()));

            return Command::INVALID;
        }

        if ($action === 'expire-due') {
            $stats = $this->lifecycleService->expireDueBatches($now);
            $io->success(sprintf(
                'Expired due demo batches. Batches: %d. Items: %d. Locations hidden: %d.',
                $stats['batches'],
                $stats['items'],
                $stats['locations']
            ));

            return Command::SUCCESS;
        }

        if ($action === 'purge') {
            $batchId = (int) ($input->getOption('batch-id') ?? 0);
            if ($batchId <= 0) {
                $io->error('Use --batch-id=<id> when action is purge.');

                return Command::INVALID;
            }

            $batch = $this->entityManager->getRepository(DemoSeedBatch::class)->find($batchId);
            if (!$batch instanceof DemoSeedBatch) {
                $io->error(sprintf('Demo seed batch #%d was not found.', $batchId));

                return Command::FAILURE;
            }

            $stats = $this->lifecycleService->purgeBatch($batch, $now);
            $io->success(sprintf(
                'Demo batch #%d logically purged. Batches: %d. Items: %d. Locations archived: %d.',
                $batchId,
                $stats['batches'],
                $stats['items'],
                $stats['locations']
            ));

            return Command::SUCCESS;
        }

        $io->error(sprintf('Unsupported action "%s". Use expire-due or purge.', $action));

        return Command::INVALID;
    }
}
