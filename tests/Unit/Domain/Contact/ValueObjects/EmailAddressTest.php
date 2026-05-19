<?php

declare(strict_types=1);

use Domain\Contact\Exceptions\InvalidEmailAddressException;
use Domain\Contact\ValueObjects\EmailAddress;

test('lowercases the address on construction', function (): void {
    $email = new EmailAddress('Jane.Doe@Example.COM');

    expect((string) $email)->toBe('jane.doe@example.com');
});

test('rejects an RFC invalid address', function (): void {
    try {
        new EmailAddress('not-an-email');
        $this->fail('expected InvalidEmailAddressException');
    } catch (InvalidEmailAddressException $invalidEmailAddressException) {
        expect($invalidEmailAddressException->errorCode)->toBe('invalid');
    }
});

test('rejects an address longer than 254 characters', function (): void {
    $local = str_repeat('a', 250);
    $address = $local.'@example.com';

    try {
        new EmailAddress($address);
        $this->fail('expected InvalidEmailAddressException');
    } catch (InvalidEmailAddressException $invalidEmailAddressException) {
        expect($invalidEmailAddressException->errorCode)->toBe('too_long');
    }
});

test('exposes the domain part of the address', function (): void {
    $email = new EmailAddress('Jane.Doe@Example.COM');

    expect($email->domain())->toBe('example.com');
});

test('equality is case insensitive', function (): void {
    $upper = new EmailAddress('Jane@Example.com');
    $lower = new EmailAddress('jane@example.com');

    expect($upper->equals($lower))->toBeTrue()
        ->and((string) $upper)->toBe((string) $lower);
});
