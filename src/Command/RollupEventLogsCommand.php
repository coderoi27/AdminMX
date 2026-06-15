<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Admin\EventRollupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:analytics:rollup-daily', description: 'Builds daily metric rollups from raw event logs.')]
final class RollupEventLogsCommand extends Command
{
    public function __construct(private readonly EventRollupService $eventRollupService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date to roll up in Y-m-d format.', 'yesterday');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dateOption = (string) $input->getOption('date');

        try {
            $date = $dateOption === 'yesterday'
                ? new \DateTimeImmutable('yesterday')
                : new \DateTimeImmutable($dateOption);
        } catch (\Exception $exception) {
            $io->error(sprintf('Invalid --date value "%s". Use Y-m-d, today or yesterday.', $dateOption));

            return Command::INVALID;
        }

        $written = $this->eventRollupService->rollupDay($date);
        $io->success(sprintf('Rollup %s computed with %d metric rows.', $date->format('Y-m-d'), $written));

        return Command::SUCCESS;
    }
}
