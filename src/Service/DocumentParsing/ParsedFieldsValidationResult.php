<?php

namespace App\Service\DocumentParsing;

final readonly class ParsedFieldsValidationResult
{
    /**
     * @param list<string> $errors
     * @param list<array<string, mixed>> $details
     */
    public function __construct(
        public array $errors,
        public array $details,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
