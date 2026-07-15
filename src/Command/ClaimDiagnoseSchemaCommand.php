<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Doctrine\ClaimSchemaInspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:claim:diagnose-schema', description: 'Diagnostica de forma read-only el schema operativo de Claim.')]
final class ClaimDiagnoseSchemaCommand extends Command
{
    public function __construct(private readonly ClaimSchemaInspector $inspector)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emite un diagnóstico estructurado sin secretos.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $diagnosis = $this->inspector->inspect();
        } catch (\Throwable $exception) {
            $requestId = bin2hex(random_bytes(12));
            $payload = [
                'ready' => false,
                'error_code' => 'claim_schema_connection_failed',
                'request_id' => $requestId,
                'exception_class' => $exception::class,
            ];
            if ($input->getOption('json')) {
                $output->writeln((string) json_encode($payload, JSON_THROW_ON_ERROR));
            } else {
                (new SymfonyStyle($input, $output))->error(sprintf('No fue posible conectar para diagnosticar el schema Claim. request_id=%s', $requestId));
            }

            return Command::FAILURE + 1;
        }

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode($diagnosis, JSON_THROW_ON_ERROR));
        } else {
            $io = new SymfonyStyle($input, $output);
            $io->definitionList(
                ['Database' => $diagnosis['database']],
                ['Server version' => $diagnosis['server_version']],
                ['Platform' => $diagnosis['platform']],
                ['Metadata table' => sprintf('%s (%s)', $diagnosis['metadata_table']['name'], $diagnosis['metadata_table']['exists'] ? 'present' : 'missing')],
            );
            $io->section('Claim tables');
            foreach ($diagnosis['tables'] as $name => $table) {
                $io->writeln(sprintf('%s: %s', $name, $table['exists'] ? 'present' : 'missing'));
            }
            if ($diagnosis['differences'] !== []) {
                $io->warning($diagnosis['differences']);
            } else {
                $io->success('Claim schema is ready.');
            }
        }

        return $diagnosis['ready'] ? Command::SUCCESS : Command::FAILURE;
    }
}
