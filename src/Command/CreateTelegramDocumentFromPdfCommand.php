<?php

namespace App\Command;

use App\Entity\TelegramDocument;
use App\Enum\Telegram\TelegramDocumentSource;
use App\Enum\Telegram\TelegramDocumentStatus;
use App\Repository\TelegramUserRepository;
use App\Service\Telegram\TelegramDocumentParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:telegram-document:create-from-pdf',
    description: 'Создает тестовый Telegram документ из локального PDF.',
)]
final class CreateTelegramDocumentFromPdfCommand extends Command
{
    public function __construct(
        private readonly TelegramUserRepository $telegramUserRepository,
        private readonly TelegramDocumentParser $telegramDocumentParser,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('pdfPath', InputArgument::REQUIRED, 'Путь к PDF внутри контейнера app.')
            ->addArgument('telegramId', InputArgument::REQUIRED, 'Telegram ID существующего Telegram пользователя.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pdfPath = (string) $input->getArgument('pdfPath');
        $telegramId = (string) $input->getArgument('telegramId');

        $telegramUser = $this->telegramUserRepository->findOneByTelegramId($telegramId);

        if ($telegramUser === null) {
            $io->error(sprintf('Telegram пользователь "%s" не найден.', $telegramId));

            return Command::FAILURE;
        }

        if (!is_file($pdfPath)) {
            $io->error(sprintf('Файл "%s" не найден.', $pdfPath));

            return Command::FAILURE;
        }

        $pdfContent = file_get_contents($pdfPath);

        if ($pdfContent === false || $pdfContent === '') {
            $io->error(sprintf('Не удалось прочитать файл "%s".', $pdfPath));

            return Command::FAILURE;
        }

        $telegramDocument = new TelegramDocument();
        $telegramDocument->setTelegramUser($telegramUser);
        $telegramDocument->setChatId($telegramUser->getTelegramId() ?? '0');
        $telegramDocument->setTelegramFileId('dev-file-id-' . bin2hex(random_bytes(6)));
        $telegramDocument->setTelegramFileUniqueId('dev-file-unique-id-' . hash('sha256', $pdfContent));
        $telegramDocument->setOriginalFilename(basename($pdfPath));
        $telegramDocument->setMimeType('application/pdf');
        $telegramDocument->setSizeBytes(strlen($pdfContent));
        $telegramDocument->setChecksumSha256(hash('sha256', $pdfContent));
        $telegramDocument->setStatus(TelegramDocumentStatus::Received);
        $telegramDocument->setSource(TelegramDocumentSource::AdminUpload);

        /*
         * Парсер сам выставит статус:
         * Parsed, NeedsReview или Failed.
         */
        $this->telegramDocumentParser->parse($telegramDocument, $pdfContent);

        $this->entityManager->persist($telegramDocument);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Telegram документ создан. ID: %s, статус: %s',
            (string) $telegramDocument->getId(),
            $telegramDocument->getStatus()->label(),
        ));

        return Command::SUCCESS;
    }
}
