<?php

declare(strict_types=1);

namespace Domain\Contact\DataTransferObjects;

use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Support\Collection;
use TypeError;

final readonly class ContactData
{
    /**
     * @param  list<PhoneNumber>  $phones
     * @param  list<EmailAddress>  $emails
     */
    public function __construct(
        public string $name,
        public array $phones,
        public array $emails,
    ) {
        $this->guardElementType($phones, PhoneNumber::class, 'phones');
        $this->guardElementType($emails, EmailAddress::class, 'emails');
    }

    /**
     * @return array{name: string}
     */
    public function toModelAttributes(): array
    {
        return ['name' => $this->name];
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  class-string  $expected
     */
    private function guardElementType(array $values, string $expected, string $field): void
    {
        Collection::make($values)->each(function (mixed $value) use ($expected, $field): void {
            if (! $value instanceof $expected) {
                $given = get_debug_type($value);
                throw new TypeError(
                    sprintf('ContactData::$%s must be a list of %s, got %s.', $field, $expected, $given)
                );
            }
        });
    }
}
