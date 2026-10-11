<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Süper admin kullanıcısı oluşturur',
)]
class CreateAdminCommand extends Command
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
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Kullanıcı adı')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-posta')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Parola (en az 12 karakter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getOption('username') ?? $io->ask('Kullanıcı adı');
        $email = $input->getOption('email') ?? $io->ask('E-posta');
        $password = $input->getOption('password') ?? $io->askHidden('Parola (en az 12 karakter)');

        if (!is_string($username) || trim($username) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Geçerli bir kullanıcı adı ve e-posta gerekli.');

            return Command::FAILURE;
        }
        if (!is_string($password) || mb_strlen($password) < 12) {
            $io->error('Parola en az 12 karakter olmalı.');

            return Command::FAILURE;
        }

        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
            $io->warning('Bu e-posta ile kullanıcı zaten mevcut: '.$email);

            return Command::SUCCESS;
        }

        $user = new User();
        $user->setUsername(trim($username));
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
        $user->setIsActive(true);
        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Admin oluşturuldu: '.$username.' / '.$email);

        return Command::SUCCESS;
    }
}
