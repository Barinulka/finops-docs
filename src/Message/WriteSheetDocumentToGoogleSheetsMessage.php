<?php

namespace App\Message;

final readonly class WriteSheetDocumentToGoogleSheetsMessage
{
    public function __construct(
        public string $sheetDocumentId,
    ) {
    }
}
