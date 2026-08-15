<?php

namespace App\Service\SheetDocument;

use App\Entity\SheetDocument;
use App\Enum\SheetDocumentStatus;
use App\Service\Document\DocumentParserClient;
use App\Service\DocumentParsing\ParsedFieldsBusinessValidator;

final readonly class SheetDocumentParser
{
    public function __construct(
        private DocumentParserClient $documentParserClient,
        private ParsedFieldsBusinessValidator $businessValidator,
    ) {
    }

    /**
     * Вызывает Python parser API и перекладывает результат в SheetDocument.
     *
     * Здесь нет чтения файла из хранилища и нет flush().
     * Сервис отвечает только за бизнес-действие: "PDF content -> parsed fields".
     */
    public function parse(SheetDocument $sheetDocument, string $pdfContent): void
    {
        $filename = $sheetDocument->getOriginalFilename() ?: 'sheet-document.pdf';

        $result = $this->documentParserClient->parsePdf($pdfContent, $filename);

        $fields = $result['fields'] ?? [];

        if (is_array($fields)) {
            $fields['executionBusinessDays'] = $this->resolveExecutionBusinessDays($fields);
        }

        $warnings = $result['warnings'] ?? [];
        $rawText = $result['rawText'] ?? null;
        $confidence = $result['confidence'] ?? null;

        $sheetDocument->setParserVersion(is_string($result['parserVersion'] ?? null) ? $result['parserVersion'] : null);
        $sheetDocument->setParsedFields(is_array($fields) ? $fields : []);
        $sheetDocument->setParserWarnings(is_array($warnings) ? $warnings : []);
        $sheetDocument->setRawText(is_string($rawText) ? $rawText : null);
        $sheetDocument->setParserConfidence(is_numeric($confidence) ? (float) $confidence : null);
        $sheetDocument->setParsedAt(new \DateTimeImmutable());
        $sheetDocument->setErrorMessage(null);
        $sheetDocument->setFailedAt(null);

        $validationResult = $this->businessValidator->validateWithDetails($sheetDocument->getParsedFields());

        $sheetDocument->setValidationErrors($validationResult->errors);
        $sheetDocument->setValidationDetails($validationResult->details);

        $validationErrors = $validationResult->errors;

        if (
            $sheetDocument->getParserConfidence() === null
            || $sheetDocument->getParserConfidence() < 0.8
            || $sheetDocument->getParserWarnings() !== []
        ) {
            $sheetDocument->setStatus(SheetDocumentStatus::NeedsReview);

            return;
        }

        if ($validationErrors !== []) {
            $sheetDocument->setStatus(SheetDocumentStatus::ValidationFailed);

            return;
        }

        $sheetDocument->setStatus(SheetDocumentStatus::Parsed);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolveExecutionBusinessDays(array $fields): ?int
    {
        $businessDays = $this->extractBusinessDays($fields['executionTermRaw'] ?? null);

        if ($businessDays !== null) {
            return $businessDays;
        }

        return $this->countBusinessDaysBetween(
            $fields['requestDate'] ?? null,
            $fields['executionDueDate'] ?? null,
        );
    }

    private function extractBusinessDays(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = (string) $value;

        if (preg_match('/(\d+)\s*\([^)]*\)\s*(?:рабочего|рабочих|working|business)\s+(?:дня|дней|день|days?|day)/iu', $text, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/(\d+)\s*(?:рабочего|рабочих|раб\.?|working|business)\s+(?:дня|дней|день|days?|day)/iu', $text, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/(\d+)\s+(?:дня|дней|день|days?|day)/iu', $text, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function countBusinessDaysBetween(mixed $startDate, mixed $endDate): ?int
    {
        $start = $this->dateFromValue($startDate);
        $end = $this->dateFromValue($endDate);

        if ($start === null || $end === null || $end < $start) {
            return null;
        }

        $days = 0;
        $current = $start->modify('+1 day');

        while ($current <= $end) {
            if ((int) $current->format('N') <= 5) {
                ++$days;
            }

            $current = $current->modify('+1 day');
        }

        return $days;
    }

    private function dateFromValue(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text);

            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }
}
