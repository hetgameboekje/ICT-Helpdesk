<?php

namespace App\Core\Exceptions;

/** Server-side validatiefout uit de Service-laag — de API-laag zet dit om naar een 422-response. */
class ValidationException extends \RuntimeException
{
    /** @param array<string,array<int,string>> $errors veld => lijst foutmeldingen */
    public function __construct(private readonly array $errors, string $message = 'Validatie mislukt.')
    {
        parent::__construct($message);
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
