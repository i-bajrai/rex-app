<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\TypeDescription;
use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<PhoneNumber, PhoneNumber|string>
 */
final class PhoneNumberCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): PhoneNumber
    {
        if (! is_string($value)) {
            throw InvalidPhoneNumberException::malformed(TypeDescription::of($value));
        }

        return new PhoneNumber($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof PhoneNumber) {
            return (string) $value;
        }

        if (! is_string($value)) {
            throw InvalidPhoneNumberException::malformed(TypeDescription::of($value));
        }

        return (string) new PhoneNumber($value);
    }
}
