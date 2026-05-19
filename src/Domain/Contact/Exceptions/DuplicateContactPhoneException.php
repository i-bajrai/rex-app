<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class DuplicateContactPhoneException extends DomainException
{
    public readonly string $errorCode;

    public function __construct(public readonly string $value)
    {
        $this->errorCode = 'contact.phone.duplicate';

        parent::__construct(sprintf('Phone number [%s] is already attached to another contact.', $value));
    }
}
