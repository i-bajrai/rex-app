<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class ContactHasNoPhoneException extends DomainException
{
    public readonly string $errorCode;

    public function __construct(public readonly int $id)
    {
        $this->errorCode = 'contact.call.no_phone';

        parent::__construct(sprintf('Contact [%d] has no phone number to call.', $id));
    }
}
