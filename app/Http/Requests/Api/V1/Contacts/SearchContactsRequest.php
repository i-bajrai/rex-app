<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contacts;

use Domain\Contact\DataTransferObjects\ContactSearchCriteria;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

final class SearchContactsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
        ];
    }

    public function toCriteria(): ContactSearchCriteria
    {
        $phoneValue = $this->input('phone');

        return new ContactSearchCriteria(
            name: $this->stringOrNull('name'),
            phone: is_string($phoneValue) && $phoneValue !== '' ? new PhoneNumber($phoneValue) : null,
            email: $this->stringOrNull('email'),
        );
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
