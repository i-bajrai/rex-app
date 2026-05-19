<?php

declare(strict_types=1);

use App\Support\TypeDescription;
use Domain\Contact\ValueObjects\PhoneNumber;

test('returns the fully-qualified class name for objects', function (): void {
    expect(TypeDescription::of(new PhoneNumber('+61412345678')))->toBe(PhoneNumber::class);
});

test('returns the PHP type name for non-objects', function (): void {
    expect(TypeDescription::of('a string'))->toBe('string')
        ->and(TypeDescription::of(123))->toBe('integer')
        ->and(TypeDescription::of(null))->toBe('NULL')
        ->and(TypeDescription::of([1, 2]))->toBe('array');
});
