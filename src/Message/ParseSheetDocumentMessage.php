<?php

namespace App\Message;

final readonly class ParseSheetDocumentMessage
{
    public function __construct(
        public string $sheetDocumentId,
    ) {
    }
}
