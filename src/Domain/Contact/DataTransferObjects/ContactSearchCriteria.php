<?php

declare(strict_types=1);

namespace Domain\Contact\DataTransferObjects;

use Domain\Contact\ValueObjects\PhoneNumber;

final readonly class ContactSearchCriteria
{
    public function __construct(
        public ?string $name = null,
        public ?PhoneNumber $phone = null,
        public ?string $email = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->name === null && ! $this->phone instanceof PhoneNumber && $this->email === null;
    }
}
