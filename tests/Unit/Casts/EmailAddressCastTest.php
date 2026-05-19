<?php

declare(strict_types=1);

use App\Casts\EmailAddressCast;
use App\Models\ContactEmail;
use Domain\Contact\Exceptions\InvalidEmailAddressException;
use Domain\Contact\ValueObjects\EmailAddress;

test('hydrates a raw string column into an EmailAddress value object', function (): void {
    $cast = new EmailAddressCast;

    $value = $cast->get(new ContactEmail, 'address', 'jane@example.com', []);

    expect($value)->toBeInstanceOf(EmailAddress::class)
        ->and((string) $value)->toBe('jane@example.com');
});

test('serialises an EmailAddress value object back into the column string', function (): void {
    $cast = new EmailAddressCast;
    $email = new EmailAddress('Jane@Example.com');

    $stored = $cast->set(new ContactEmail, 'address', $email, []);

    expect($stored)->toBe('jane@example.com');
});

test('serialises a raw string into the normalised lowercase column string', function (): void {
    $cast = new EmailAddressCast;

    $stored = $cast->set(new ContactEmail, 'address', 'Jane@Example.com', []);

    expect($stored)->toBe('jane@example.com');
});

test('rejects an invalid raw string when hydrating from the column', function (): void {
    $cast = new EmailAddressCast;

    try {
        $cast->get(new ContactEmail, 'address', 'not-an-email', []);
        $this->fail('expected InvalidEmailAddressException');
    } catch (InvalidEmailAddressException $invalidEmailAddressException) {
        expect($invalidEmailAddressException->errorCode)->toBe('invalid');
    }
});

test('rejects an invalid raw string when serialising for storage', function (): void {
    $cast = new EmailAddressCast;

    try {
        $cast->set(new ContactEmail, 'address', 'not-an-email', []);
        $this->fail('expected InvalidEmailAddressException');
    } catch (InvalidEmailAddressException $invalidEmailAddressException) {
        expect($invalidEmailAddressException->errorCode)->toBe('invalid');
    }
});

test('rejects non-string hydration values', function (): void {
    $cast = new EmailAddressCast;

    try {
        $cast->get(new ContactEmail, 'address', 12345, []);
        $this->fail('expected InvalidEmailAddressException');
    } catch (InvalidEmailAddressException $invalidEmailAddressException) {
        expect($invalidEmailAddressException->errorCode)->toBe('invalid');
    }
});

test('rejects non-string serialisation values', function (): void {
    $cast = new EmailAddressCast;

    try {
        $cast->set(new ContactEmail, 'address', 12345, []);
        $this->fail('expected InvalidEmailAddressException');
    } catch (InvalidEmailAddressException $invalidEmailAddressException) {
        expect($invalidEmailAddressException->errorCode)->toBe('invalid');
    }
});
