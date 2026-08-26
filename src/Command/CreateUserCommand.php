<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a login account (idempotent — safe to re-run for an existing email)',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN (can manage other Users)')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Account email (prompted interactively if omitted)')
            ->addOption('full-name', null, InputOption::VALUE_REQUIRED, 'Full name (prompted interactively if omitted)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Plaintext password (prompted interactively if omitted — dev/local use only, visible in shell history)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create a Volunteer Manager account');

        $helper = $this->getHelper('question');

        $email = $input->getOption('email') ?? $helper->ask($input, $output, new Question('Email: '));
        if (null !== $existing = $this->users->findOneByEmail($email)) {
            $io->warning(sprintf('A user with email "%s" already exists — updating instead of creating a duplicate.', $email));
            $user = $existing;
        } else {
            $user = new User();
            $user->setEmail($email);
        }

        $fullName = $input->getOption('full-name') ?? $helper->ask($input, $output, new Question('Full name: '));
        $user->setFullName($fullName);

        $passwordQuestion = new Question('Password: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $plainPassword = $input->getOption('password') ?? $helper->ask($input, $output, $passwordQuestion);

        $user->setRoles($input->getOption('admin') ? ['ROLE_ADMIN'] : ['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getMessage());
            }

            return Command::FAILURE;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'User "%s" (%s) saved with roles: %s',
            $user->getFullName(),
            $user->getEmail(),
            implode(', ', $user->getRoles()),
        ));

        return Command::SUCCESS;
    }
}
