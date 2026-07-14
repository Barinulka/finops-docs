<?php

namespace App\Command;

use App\Service\GoogleSheets\GoogleSheetsClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:google-sheets:test-append',
    description: 'Дописывает тестовую строку в Google Sheets.',
)]
final class TestGoogleSheetsAppendCommand extends Command
{
    public function __construct(
        private readonly GoogleSheetsClient $googleSheetsClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /*
         * Это техническая тестовая строка.
         * Она нужна только для проверки доступа к Google Sheets.
         */
        $row = [
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'test',
            'CRM Telegram bot',
            'Google Sheets append works',
        ];

        try {
            $response = $this->googleSheetsClient->appendRow($row);
        } catch (\Throwable $exception) {
            /*
             * Здесь важно показать реальный текст ошибки:
             * Google API обычно хорошо объясняет проблему:
             * 403 - нет доступа к таблице,
             * 404 - неверный spreadsheet id или лист,
             * invalid_grant - проблема с JSON-ключом.
             */
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Тестовая строка добавлена в Google Sheets.');

        if ($output->isVerbose()) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return Command::SUCCESS;
    }
}
