<?php

namespace App\Command;

use App\Repository\TelegramDocumentRepository;
use App\Service\Telegram\TelegramDocumentGoogleSheetWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:telegram-document:write-to-google-sheets',
    description: 'Записывает Telegram документ в Google Sheets.',
)]
final class WriteTelegramDocumentToGoogleSheetsCommand extends Command
{
    public function __construct(
        private readonly TelegramDocumentRepository $telegramDocumentRepository,
        private readonly TelegramDocumentGoogleSheetWriter $googleSheetWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'telegramDocumentId',
            InputArgument::REQUIRED,
            'ULID Telegram документа.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $telegramDocumentId = (string) $input->getArgument('telegramDocumentId');

        $telegramDocument = $this->telegramDocumentRepository->find($telegramDocumentId);

        if ($telegramDocument === null) {
            $io->error(sprintf('Telegram документ "%s" не найден.', $telegramDocumentId));

            return Command::FAILURE;
        }

        try {
            $log = $this->googleSheetWriter->write($telegramDocument);
        } catch (\Throwable $exception) {
            /*
             * Сюда попадут ошибки бизнес-валидации, например:
             * документ еще не распарсен и его нельзя писать в таблицу.
             */
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($log->getStatus()->value === 'failed') {
            $io->error($log->getErrorMessage() ?? 'Не удалось записать строку в Google Sheets.');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Telegram документ "%s" записан в Google Sheets. Диапазон: %s',
            $telegramDocumentId,
            $log->getAppendedRange() ?? 'неизвестно',
        ));

        return Command::SUCCESS;
    }
}
