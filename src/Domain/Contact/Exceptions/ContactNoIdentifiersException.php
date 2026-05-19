<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class ContactNoIdentifiersException extends DomainException
{
    public readonly string $errorCode;

    public function __construct()
    {
        $this->errorCode = 'contact.no_identifiers';

        parent::__construct('A contact must have at least one phone number or one email address.');
    }
}
