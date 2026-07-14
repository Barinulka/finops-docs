<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Команда добавления пользователя',
)]
class CreateUserCommand extends Command
{
    private const ALLOWED_ROLES = [
        'ROLE_ADMIN',
        'ROLE_MANAGER',
        'ROLE_OPERATOR',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email Пользователя')
            ->addArgument('fullName', InputArgument::REQUIRED, 'ФИО')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Роль пользователя', 'ROLE_ADMIN')
        ;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $fullName = $input->getArgument('fullName');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error("Не передан или введен невалидный email: {$email}");
            return Command::FAILURE;
        }

        if (empty($fullName)) {
            $io->error('Не передан ФИО');
            return Command::FAILURE;
        }

        $role = $input->getOption('role');
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $io->error("Указанная роль: {$role} недоступна");
            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user) {
            $io->error("Пользователь с таким email: {$email} уже существует");
            return Command::FAILURE;
        }

        $password = $io->askHidden('Пароль');
        $repeatPassword = $io->askHidden('Повторите пароль');

        if (empty($password)) {
            $io->error("Пароль не может быть пустым");
            return Command::FAILURE;
        }

        if ($password !== $repeatPassword) {
            $io->error('Пароли не совпадают');
            return Command::FAILURE;
        }

        if (strlen($password) < 8) {
            $io->error("Длина пароля должна быть не менее 8 символов");
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles([$role]);
        $user->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success("Пользователь успешно добавлен: {$user->getEmail()}");

        return Command::SUCCESS;
    }
}
