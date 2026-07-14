<?php

namespace App\Command;

use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use App\Service\Document\DocumentParserClient;
use App\Service\Document\DocumentParsingResultApplier;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:document:parse-via-api',
    description: 'Отправляет документ в Python parser API и применяет результат.',
)]
final class ParseDocumentViaApiCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly UserRepository $userRepository,
        private readonly DocumentParserClient $parserClient,
        private readonly DocumentParsingResultApplier $parsingResultApplier,
        #[Autowire(service: 'documents.storage')]
        private readonly FilesystemOperator $documentsStorage,
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

        $storagePath = $document->getStoragePath();

        if ($storagePath === null || $storagePath === '') {
            $io->error('У документа не заполнен путь к файлу.');

            return Command::FAILURE;
        }

        try {
            $pdfContent = $this->documentsStorage->read($storagePath);
            $result = $this->parserClient->parsePdf(
                $pdfContent,
                $document->getOriginalFilename() ?? 'document.pdf',
            );
            $operation = $this->parsingResultApplier->apply($document, $result, $actor);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Документ распарсен через API. ID операции: %s',
            (string) $operation->getId(),
        ));

        return Command::SUCCESS;
    }
}