<?php

namespace App\Command;

use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use App\Service\Document\DocumentParsingResultApplier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:document:apply-parser-result',
    description: 'Применяет JSON-результат парсера к загруженному документу.',
)]
final class ApplyDocumentParserResultCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly UserRepository $userRepository,
        private readonly DocumentParsingResultApplier $parsingResultApplier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('documentId', InputArgument::REQUIRED, 'ULID документа.')
            ->addArgument('actorEmail', InputArgument::REQUIRED, 'Email пользователя для аудита и автора операции.')
            ->addArgument('jsonPath', InputArgument::REQUIRED, 'Путь к JSON-файлу с результатом парсера.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $documentId = (string) $input->getArgument('documentId');
        $actorEmail = (string) $input->getArgument('actorEmail');
        $jsonPath = (string) $input->getArgument('jsonPath');

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

        if (!is_file($jsonPath) || !is_readable($jsonPath)) {
            $io->error(sprintf('JSON-файл "%s" не существует или недоступен для чтения.', $jsonPath));

            return Command::FAILURE;
        }

        $contents = file_get_contents($jsonPath);

        if ($contents === false) {
            $io->error(sprintf('Не удалось прочитать JSON-файл "%s".', $jsonPath));

            return Command::FAILURE;
        }

        try {
            $result = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $io->error(sprintf('Некорректный JSON: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        if (!is_array($result) || array_is_list($result)) {
            $io->error('JSON результата парсера должен быть объектом.');

            return Command::FAILURE;
        }

        try {
            $operation = $this->parsingResultApplier->apply($document, $result, $actor);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Результат парсера применен. ID операции: %s',
            (string) $operation->getId(),
        ));

        return Command::SUCCESS;
    }
}
