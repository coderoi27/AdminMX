<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Admin\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:admin:create-user',
    description: 'Creates a bootstrap admin user.',
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('full-name', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('role-key', InputArgument::OPTIONAL, '', 'operator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = mb_strtolower((string) $input->getArgument('email'));
        $fullName = (string) $input->getArgument('full-name');
        $password = (string) $input->getArgument('password');
        $roleKey = (string) $input->getArgument('role-key');

        $existing = $this->entityManager
            ->getRepository(AdminUser::class)
            ->findOneBy(['email' => $email]);

        if ($existing !== null) {
            $io->error(sprintf('Admin user "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $user = (new AdminUser())
            ->setEmail($email)
            ->setFullName($fullName)
            ->setRoleKey($roleKey)
            ->setStatus('active');

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Admin user "%s" created.', $email));

        return Command::SUCCESS;
    }
}
