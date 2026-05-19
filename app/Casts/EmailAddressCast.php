<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\TypeDescription;
use Domain\Contact\Exceptions\InvalidEmailAddressException;
use Domain\Contact\ValueObjects\EmailAddress;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<EmailAddress, EmailAddress|string>
 */
final class EmailAddressCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): EmailAddress
    {
        if (! is_string($value)) {
            throw InvalidEmailAddressException::invalid(TypeDescription::of($value));
        }

        return new EmailAddress($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof EmailAddress) {
            return (string) $value;
        }

        if (! is_string($value)) {
            throw InvalidEmailAddressException::invalid(TypeDescription::of($value));
        }

        return (string) new EmailAddress($value);
    }
}
