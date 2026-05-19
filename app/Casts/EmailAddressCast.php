<?php

declare(strict_types=1);

namespace App\Casts;

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
        return new EmailAddress((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $email = $value instanceof EmailAddress ? $value : new EmailAddress((string) $value);

        return (string) $email;
    }
}
