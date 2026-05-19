<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class ContactNotFoundException extends DomainException
{
    public readonly string $errorCode;

    public function __construct(public readonly int $id)
    {
        $this->errorCode = 'contact.not_found';

        parent::__construct(sprintf('Contact [%d] was not found.', $id));
    }
}
