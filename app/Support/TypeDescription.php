<?php

declare(strict_types=1);

namespace App\Support;

final class TypeDescription
{
    public static function of(mixed $value): string
    {
        return is_object($value) ? $value::class : gettype($value);
    }
}
