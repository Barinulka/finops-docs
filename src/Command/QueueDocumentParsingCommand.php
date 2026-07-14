<?php

namespace App\Command;

use App\Enum\DocumentStatus;
use App\Message\ParseDocumentMessage;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:document:queue-parsing',
    description: 'Ставит документ в очередь на парсинг.',
)]
final class QueueDocumentParsingCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('documentId', InputArgument::REQUIRED, 'ULID документа.')
            ->addArgument('actorEmail', InputArgument::REQUIRED, 'Email пользователя для аудита и автора операции.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $documentId = (string) $input->getArgument('documentId');
        $actorEmail = (string) $input->getArgument('actorEmail');

        $document = $this->documentRepository->find($documentId);

        if ($document === null) {
            $io->error(sprintf('Документ "%s" не найден.', $documentId));

            return Command::FAILURE;
        }

        $actor = $this->userRepository->findOneBy([
            'email' => $actorEmail,
        ]);

        if ($actor === null) {
            $io->error(sprintf('Пользователь "%s" не найден.', $actorEmail));

            return Command::FAILURE;
        }

        $document
            ->setStatus(DocumentStatus::Queued)
            ->setQueuedAt(new \DateTimeImmutable())
            ->setParseError(null);

        $this->entityManager->flush();

        $this->messageBus->dispatch(new ParseDocumentMessage(
            (string) $document->getId(),
            (string) $actor->getId(),
        ));

        $io->success(sprintf(
            'Документ "%s" поставлен в очередь на парсинг.',
            (string) $document->getId(),
        ));

        return Command::SUCCESS;
    }
}