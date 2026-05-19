<?php

declare(strict_types=1);

namespace Domain\Contact\Exceptions;

use DomainException;

final class InvalidPhoneNumberException extends DomainException
{
    /** @var list<string> */
    public const array SUPPORTED_REGIONS = ['AU', 'NZ'];

    /**
     * @param  list<string>  $supportedRegions
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly string $value,
        public readonly array $supportedRegions = self::SUPPORTED_REGIONS,
        string $message = '',
    ) {
        parent::__construct($message === '' ? "Phone number [{$value}] failed validation: {$errorCode}." : $message);
    }

    public static function notE164(string $value): self
    {
        return new self(
            errorCode: 'not_e164',
            value: $value,
            message: "Phone number [{$value}] is not in E.164 format.",
        );
    }

    public static function unsupportedRegion(string $value): self
    {
        return new self(
            errorCode: 'unsupported_region',
            value: $value,
            message: "Phone number [{$value}] is not from a supported region.",
        );
    }

    public static function malformed(string $value): self
    {
        return new self(
            errorCode: 'malformed',
            value: $value,
            message: "Phone number [{$value}] contains non-digit characters.",
        );
    }
}
