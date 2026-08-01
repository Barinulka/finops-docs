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

        $validationErrors = $this->businessValidator->validate($sheetDocument->getParsedFields());
        $sheetDocument->setValidationErrors($validationErrors);

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
}
