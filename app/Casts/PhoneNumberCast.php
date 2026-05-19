<?php

declare(strict_types=1);

namespace App\Casts;

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
        return new PhoneNumber((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $phone = $value instanceof PhoneNumber ? $value : new PhoneNumber((string) $value);

        return (string) $phone;
    }
}
