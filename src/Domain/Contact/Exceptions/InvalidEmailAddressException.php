<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class InvalidEmailAddressException extends DomainException
{
    private function __construct(
        public readonly string $errorCode,
        public readonly string $value,
        string $message = '',
    ) {
        parent::__construct($message === '' ? sprintf('Email address failed validation: %s.', $errorCode) : $message);
    }

    public static function invalid(string $value): self
    {
        return new self(
            errorCode: 'invalid',
            value: $value,
            message: sprintf('Email address [%s] is not RFC-valid.', $value),
        );
    }

    public static function tooLong(string $value): self
    {
        return new self(
            errorCode: 'too_long',
            value: $value,
            message: 'Email address exceeds the 254-character RFC limit.',
        );
    }
}
