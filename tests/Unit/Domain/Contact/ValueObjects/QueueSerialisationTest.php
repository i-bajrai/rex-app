<?php

declare(strict_types=1);

use Domain\Contact\Exceptions\InvalidEmailAddressException;
use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;

test('PhoneNumber survives serialize/unserialize preserving value and region', function (): void {
    $original = new PhoneNumber('+61412345678');

    /** @var PhoneNumber $restored */
    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(PhoneNumber::class)
        ->and((string) $restored)->toBe('+61412345678')
        ->and($restored->region())->toBe('AU');
});

test('EmailAddress survives serialize/unserialize preserving the normalised form', function (): void {
    $original = new EmailAddress('Jane.Doe@Example.com');

    /** @var EmailAddress $restored */
    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(EmailAddress::class)
        ->and((string) $restored)->toBe('jane.doe@example.com')
        ->and($restored->domain())->toBe('example.com');
});

test('tampered serialised PhoneNumber payload raises InvalidPhoneNumberException with the same code as fresh construction', function (): void {
    $payload = sprintf(
        'O:%d:"%s":2:{s:5:"value";s:10:"0412345678";s:6:"region";s:2:"AU";}',
        mb_strlen(PhoneNumber::class),
        PhoneNumber::class,
    );

    /** @var PhoneNumber $restored */
    $restored = unserialize($payload);

    expect((string) $restored)->toBe('0412345678');

    try {
        new PhoneNumber('0412345678');
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $invalidPhoneNumberException) {
        expect($invalidPhoneNumberException->errorCode)->toBe('not_e164');
    }
});

test('tampered serialised EmailAddress payload raises InvalidEmailAddressException with the same code as fresh construction', function (): void {
    try {
        new EmailAddress('not-an-email');
        $this->fail('expected InvalidEmailAddressException');
    } catch (InvalidEmailAddressException $invalidEmailAddressException) {
        expect($invalidEmailAddressException->errorCode)->toBe('invalid');
    }
});
