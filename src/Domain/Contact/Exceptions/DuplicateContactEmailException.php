<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class DuplicateContactEmailException extends DomainException
{
    public readonly string $errorCode;

    public function __construct(public readonly string $value)
    {
        $this->errorCode = 'contact.email.duplicate';

        parent::__construct(sprintf('Email address [%s] is already attached to another contact.', $value));
    }
}
