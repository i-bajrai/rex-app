<?php

declare(strict_types=1);

use Domain\Contact\DataTransferObjects\ContactData;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;

test('toModelAttributes returns only contact-table columns, not phones or emails', function (): void {
    $dto = new ContactData(
        name: 'Jane Doe',
        phones: [new PhoneNumber('+61412345678')],
        emails: [new EmailAddress('jane@example.com')],
    );

    expect($dto->toModelAttributes())->toBe(['name' => 'Jane Doe']);
});

test('accepts a typed array of PhoneNumber and EmailAddress value objects', function (): void {
    $phones = [new PhoneNumber('+61412345678'), new PhoneNumber('+64211234567')];
    $emails = [new EmailAddress('jane@example.com')];

    $dto = new ContactData(name: 'Jane Doe', phones: $phones, emails: $emails);

    expect($dto->phones)->toBe($phones)
        ->and($dto->emails)->toBe($emails)
        ->and($dto->name)->toBe('Jane Doe');
});

test('rejects raw string phones at the boundary', function (): void {
    expect(fn (): ContactData => new ContactData(
        name: 'Jane',
        phones: ['+61412345678'],
        emails: [],
    ))->toThrow(TypeError::class);
});

test('rejects raw string emails at the boundary', function (): void {
    expect(fn (): ContactData => new ContactData(
        name: 'Jane',
        phones: [],
        emails: ['jane@example.com'],
    ))->toThrow(TypeError::class);
});

test('allows empty phone and email arrays at construction', function (): void {
    $dto = new ContactData(name: 'Solo', phones: [], emails: []);

    expect($dto->phones)->toBe([])
        ->and($dto->emails)->toBe([])
        ->and($dto->toModelAttributes())->toBe(['name' => 'Solo']);
});
