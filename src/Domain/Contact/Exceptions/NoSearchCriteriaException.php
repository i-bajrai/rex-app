<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class NoSearchCriteriaException extends DomainException
{
    public readonly string $errorCode;

    public function __construct()
    {
        $this->errorCode = 'contact.search.no_criteria';

        parent::__construct('At least one search criterion must be supplied.');
    }
}
