<?php

declare(strict_types=1);

use App\Casts\PhoneNumberCast;
use App\Models\ContactPhone;
use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Domain\Contact\ValueObjects\PhoneNumber;

test('hydrates a raw string column into a PhoneNumber value object', function (): void {
    $cast = new PhoneNumberCast;

    $value = $cast->get(new ContactPhone, 'e164', '+61412345678', []);

    expect($value)->toBeInstanceOf(PhoneNumber::class)
        ->and((string) $value)->toBe('+61412345678');
});

test('serialises a PhoneNumber value object back into the column string', function (): void {
    $cast = new PhoneNumberCast;
    $phone = new PhoneNumber('+61412345678');

    $stored = $cast->set(new ContactPhone, 'e164', $phone, []);

    expect($stored)->toBe('+61412345678');
});

test('serialises a raw string into the column string after re-validating it', function (): void {
    $cast = new PhoneNumberCast;

    $stored = $cast->set(new ContactPhone, 'e164', '+64211234567', []);

    expect($stored)->toBe('+64211234567');
});

test('rejects an invalid raw string when hydrating from the column', function (): void {
    $cast = new PhoneNumberCast;

    try {
        $cast->get(new ContactPhone, 'e164', '0412345678', []);
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $invalidPhoneNumberException) {
        expect($invalidPhoneNumberException->errorCode)->toBe('not_e164');
    }
});

test('rejects an invalid raw string when serialising for storage', function (): void {
    $cast = new PhoneNumberCast;

    try {
        $cast->set(new ContactPhone, 'e164', '+14155551234', []);
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $invalidPhoneNumberException) {
        expect($invalidPhoneNumberException->errorCode)->toBe('unsupported_region');
    }
});

test('rejects non-string hydration values', function (): void {
    $cast = new PhoneNumberCast;

    try {
        $cast->get(new ContactPhone, 'e164', 12345, []);
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $invalidPhoneNumberException) {
        expect($invalidPhoneNumberException->errorCode)->toBe('malformed');
    }
});

test('rejects non-string serialisation values', function (): void {
    $cast = new PhoneNumberCast;

    try {
        $cast->set(new ContactPhone, 'e164', 12345, []);
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $invalidPhoneNumberException) {
        expect($invalidPhoneNumberException->errorCode)->toBe('malformed');
    }
});
