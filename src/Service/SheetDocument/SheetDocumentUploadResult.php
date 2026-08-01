<?php

namespace App\Service\SheetDocument;

use App\Entity\SheetDocument;

final class SheetDocumentUploadResult
{
    /**
     * @var list<SheetDocument>
     */
    private array $uploadedDocuments = [];

    /**
     * @var list<array{filename: string, existingDocument: SheetDocument}>
     */
    private array $skippedDuplicates = [];

    public function addUploadedDocument(SheetDocument $sheetDocument): void
    {
        $this->uploadedDocuments[] = $sheetDocument;
    }

    public function addSkippedDuplicate(string $filename, SheetDocument $existingDocument): void
    {
        $this->skippedDuplicates[] = [
            'filename' => $filename,
            'existingDocument' => $existingDocument,
        ];
    }

    /**
     * @return list<SheetDocument>
     */
    public function getUploadedDocuments(): array
    {
        return $this->uploadedDocuments;
    }

    /**
     * @return list<array{filename: string, existingDocument: SheetDocument}>
     */
    public function getSkippedDuplicates(): array
    {
        return $this->skippedDuplicates;
    }

    public function getUploadedCount(): int
    {
        return count($this->uploadedDocuments);
    }

    public function getSkippedDuplicateCount(): int
    {
        return count($this->skippedDuplicates);
    }
}
