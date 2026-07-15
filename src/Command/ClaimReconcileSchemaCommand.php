<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Doctrine\ClaimSchemaReconciler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:claim:reconcile-schema', description: 'Reconciles partial Claim schema installations before Doctrine historical migrations run.')]
final class ClaimReconcileSchemaCommand extends Command
{
    public function __construct(private readonly ClaimSchemaReconciler $reconciler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only prints the reconciliation plan.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Outputs a safe JSON payload.')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Required to mutate the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $json = (bool) $input->getOption('json');
        $confirm = (bool) $input->getOption('confirm');

        if (!$dryRun && !$confirm) {
            $payload = [
                'ok' => false,
                'error_code' => 'claim_reconciliation_confirmation_required',
                'message' => 'Run with --dry-run first, then use --confirm to apply the reconciliation.',
            ];
            $this->writePayload($payload, $json, $input, $output, true);

            return Command::FAILURE;
        }

        $result = $this->reconciler->reconcile($dryRun);
        $this->writePayload($result, $json, $input, $output, ($result['ok'] ?? false) !== true);

        if (($result['error_code'] ?? null) === 'claim_schema_reconciliation_failed') {
            return Command::FAILURE + 1;
        }

        return ($result['ok'] ?? false) === true ? Command::SUCCESS : Command::FAILURE;
    }

    /** @param array<string, mixed> $payload */
    private function writePayload(array $payload, bool $json, InputInterface $input, OutputInterface $output, bool $isError): void
    {
        if ($json) {
            $output->writeln((string) json_encode($payload, JSON_THROW_ON_ERROR));

            return;
        }

        $io = new SymfonyStyle($input, $output);
        if ($isError) {
            $io->error((string) ($payload['message'] ?? 'Claim schema reconciliation failed.'));
        } else {
            $io->success((bool) ($payload['dry_run'] ?? false) ? 'Claim reconciliation plan is ready.' : 'Claim schema reconciliation completed.');
        }

        if (($payload['plan'] ?? []) !== []) {
            $io->section('Plan');
            foreach ($payload['plan'] as $statement) {
                $io->writeln((string) $statement);
            }
        }
    }
}
