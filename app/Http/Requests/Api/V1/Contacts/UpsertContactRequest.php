<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contacts;

use Domain\Contact\DataTransferObjects\ContactData;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

final class UpsertContactRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phones' => ['present', 'array'],
            'phones.*' => ['string'],
            'emails' => ['present', 'array'],
            'emails.*' => ['string'],
        ];
    }

    public function toContactData(): ContactData
    {
        return new ContactData(
            name: $this->string('name')->toString(),
            phones: $this->valueObjects('phones', static fn (string $value): PhoneNumber => new PhoneNumber($value)),
            emails: $this->valueObjects('emails', static fn (string $value): EmailAddress => new EmailAddress($value)),
        );
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $factory
     * @return list<T>
     */
    private function valueObjects(string $key, callable $factory): array
    {
        /** @var array<int, mixed> $values */
        $values = (array) $this->input($key, []);

        return array_values(Collection::make($values)
            ->map(static fn (mixed $value): string => is_string($value) ? $value : '')
            ->map($factory)
            ->all());
    }
}
