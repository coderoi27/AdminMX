<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;

#[AsCommand(
    name: 'app:claims:mail-test',
    description: 'Envia un correo de prueba para verificar la configuración SMTP del flujo Claim.',
)]
#[When(env: 'dev')]
class MailTestCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(string:CLAIM_FROM_EMAIL)%')] private string $fromEmail,
        #[Autowire('%env(string:CLAIM_FROM_NAME)%')] private string $fromName
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Correo electrónico destino');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetEmail = (string) $input->getArgument('email');

        if (filter_var($targetEmail, FILTER_VALIDATE_EMAIL) === false) {
            $io->error('La dirección de correo no tiene un formato válido.');
            return Command::INVALID;
        }

        $io->info(sprintf('Intentando enviar correo de prueba a %s desde %s <%s>...', $targetEmail, $this->fromName, $this->fromEmail));

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($targetEmail)
            ->subject('Test SMTP Claim Flow')
            ->text('Si recibiste este correo, la configuración SMTP y Mailer DSN de Admin-MiMonchisMX está funcionando correctamente.')
            ->html('<p>Si recibiste este correo, la configuración SMTP y Mailer DSN de Admin-MiMonchisMX está funcionando correctamente.</p>');

        try {
            $this->mailer->send($email);
            $io->success('Correo enviado exitosamente al MTA configurado.');
            return Command::SUCCESS;
        } catch (TransportExceptionInterface $e) {
            $io->error('Fallo al enviar correo: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
