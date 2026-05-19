<?php

declare(strict_types=1);

use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Domain\Contact\ValueObjects\PhoneNumber;

test('accepts a valid Australian mobile number', function (): void {
    $phone = new PhoneNumber('+61412345678');

    expect((string) $phone)->toBe('+61412345678')
        ->and($phone->region())->toBe('AU');
});

test('accepts a valid Australian landline number', function (): void {
    $phone = new PhoneNumber('+61298765432');

    expect((string) $phone)->toBe('+61298765432')
        ->and($phone->region())->toBe('AU');
});

test('accepts a valid New Zealand mobile number', function (): void {
    $phone = new PhoneNumber('+64211234567');

    expect((string) $phone)->toBe('+64211234567')
        ->and($phone->region())->toBe('NZ');
});

test('accepts a valid New Zealand landline number', function (): void {
    $phone = new PhoneNumber('+6492345678');

    expect((string) $phone)->toBe('+6492345678')
        ->and($phone->region())->toBe('NZ');
});

test('rejects a number without the leading plus', function (): void {
    try {
        new PhoneNumber('0412345678');
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $exception) {
        expect($exception->errorCode)->toBe('not_e164');
    }
});

test('rejects a US country code', function (): void {
    try {
        new PhoneNumber('+14155551234');
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $exception) {
        expect($exception->errorCode)->toBe('unsupported_region')
            ->and($exception->supportedRegions)->toBe(['AU', 'NZ']);
    }
});

test('rejects a number that is too short for AU/NZ', function (): void {
    try {
        new PhoneNumber('+6112345');
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $exception) {
        expect($exception->errorCode)->toBe('not_e164');
    }
});

test('rejects a number that is too long for AU/NZ', function (): void {
    try {
        new PhoneNumber('+61412345678901234');
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $exception) {
        expect($exception->errorCode)->toBe('not_e164');
    }
});

test('rejects a number containing non-digit characters', function (): void {
    try {
        new PhoneNumber('+61 412-345-678');
        $this->fail('expected InvalidPhoneNumberException');
    } catch (InvalidPhoneNumberException $exception) {
        expect($exception->errorCode)->toBe('malformed');
    }
});

test('exposes the region for AU and NZ values', function (): void {
    expect((new PhoneNumber('+61412345678'))->region())->toBe('AU')
        ->and((new PhoneNumber('+64211234567'))->region())->toBe('NZ');
});
