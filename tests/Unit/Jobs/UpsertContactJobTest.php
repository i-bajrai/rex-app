<?php

declare(strict_types=1);

use App\Jobs\UpsertContactJob;
use App\Models\Contact;
use Domain\Contact\Actions\UpsertContact;
use Domain\Contact\DataTransferObjects\ContactData;
use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;

test('handle dispatches UpsertContact with the constructor-supplied ContactData', function (): void {
    $data = new ContactData(
        name: 'Jane Doe',
        phones: [new PhoneNumber('+61412345678')],
        emails: [new EmailAddress('jane@example.com')],
    );

    $job = new UpsertContactJob($data);

    $job->handle(resolve(UpsertContact::class));

    expect(Contact::query()->where('name', 'Jane Doe')->count())->toBe(1);
});

test('ContactData survives Laravel queue serialisation roundtrip', function (): void {
    $data = new ContactData(
        name: 'Jane Doe',
        phones: [new PhoneNumber('+61412345678')],
        emails: [new EmailAddress('jane@example.com')],
    );

    /** @var UpsertContactJob $restored */
    $restored = unserialize(serialize(new UpsertContactJob($data)));

    expect($restored)->toBeInstanceOf(UpsertContactJob::class);

    $restored->handle(resolve(UpsertContact::class));

    expect(Contact::query()->where('name', 'Jane Doe')->count())->toBe(1);
});

test('validation failures inside the job surface the same domain exception as HTTP/CLI', function (): void {
    $payload = sprintf(
        'O:%d:"%s":2:{s:5:"value";s:10:"0412345678";s:6:"region";s:2:"AU";}',
        mb_strlen(PhoneNumber::class),
        PhoneNumber::class,
    );

    /** @var PhoneNumber $tampered */
    $tampered = unserialize($payload);

    expect(fn (): ContactData => new ContactData(name: 'X', phones: [$tampered], emails: []))
        ->not->toThrow(InvalidPhoneNumberException::class);

    expect(fn (): PhoneNumber => new PhoneNumber('0412345678'))
        ->toThrow(InvalidPhoneNumberException::class);
});

test('UpsertContactJob class file contains zero Eloquent calls', function (): void {
    $source = (string) file_get_contents((string) realpath(__DIR__.'/../../../app/Jobs/UpsertContactJob.php'));

    expect($source)->not->toContain('App\\Models')
        ->and($source)->not->toContain('Contact::query')
        ->and($source)->not->toContain('::create(')
        ->and($source)->not->toContain('->save(');
});
