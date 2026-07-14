<?php

namespace App\Message;

final readonly class ParseDocumentMessage
{
    public function __construct(
        public string $documentId,
        public string $actorId,
    ) {
    }
}