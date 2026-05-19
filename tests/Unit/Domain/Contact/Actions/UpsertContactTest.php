<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Actions\UpsertContact;
use Domain\Contact\DataTransferObjects\ContactData;
use Domain\Contact\Exceptions\ContactNoIdentifiersException;
use Domain\Contact\Exceptions\DuplicateContactEmailException;
use Domain\Contact\Exceptions\DuplicateContactPhoneException;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Pest\Expectation;

test('creates a new contact with phones and emails when no id is supplied', function (): void {
    $data = new ContactData(
        name: 'Jane Doe',
        phones: [new PhoneNumber('+61412345678')],
        emails: [new EmailAddress('jane@example.com')],
    );

    $contact = resolve(UpsertContact::class)->execute($data);

    expect($contact->name)->toBe('Jane Doe')
        ->and($contact->phones()->count())->toBe(1)
        ->and($contact->emails()->count())->toBe(1)
        ->and((string) $contact->phones()->first()?->e164)->toBe('+61412345678')
        ->and((string) $contact->emails()->first()?->address)->toBe('jane@example.com');
});

test('updates an existing contact and replaces the child sets atomically', function (): void {
    $existing = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('old@example.com')
        ->create(['name' => 'Old Name']);

    $data = new ContactData(
        name: 'New Name',
        phones: [new PhoneNumber('+64211234567')],
        emails: [new EmailAddress('new@example.com')],
    );

    $contact = resolve(UpsertContact::class)->execute($data, $existing->id);

    expect($contact->id)->toBe($existing->id)
        ->and($contact->name)->toBe('New Name')
        ->and($contact->phones()->pluck('e164')->map(fn ($p): string => (string) $p)->all())->toBe(['+64211234567'])
        ->and($contact->emails()->pluck('address')->map(fn ($e): string => (string) $e)->all())->toBe(['new@example.com']);
});

test('rejects an upsert with no phones and no emails', function (): void {
    $data = new ContactData(name: 'Identifier-less', phones: [], emails: []);

    expect(fn () => resolve(UpsertContact::class)->execute($data))
        ->toThrow(ContactNoIdentifiersException::class);
});

test('rejects an upsert whose phone is already attached to another contact', function (): void {
    Contact::factory()->withPhone('+61412345678')->create();

    $data = new ContactData(
        name: 'Conflict',
        phones: [new PhoneNumber('+61412345678')],
        emails: [],
    );

    expect(fn () => resolve(UpsertContact::class)->execute($data))
        ->toThrow(fn (DuplicateContactPhoneException $e): Expectation => expect($e->errorCode)->toBe('contact.phone.duplicate')
            ->and($e->value)->toBe('+61412345678'));
});

test('rejects an upsert whose email is already attached to another contact', function (): void {
    Contact::factory()->withEmail('shared@example.com')->create();

    $data = new ContactData(
        name: 'Conflict',
        phones: [],
        emails: [new EmailAddress('Shared@Example.com')],
    );

    expect(fn () => resolve(UpsertContact::class)->execute($data))
        ->toThrow(fn (DuplicateContactEmailException $e): Expectation => expect($e->errorCode)->toBe('contact.email.duplicate')
            ->and($e->value)->toBe('shared@example.com'));
});

test('allows keeping the contact own phones and emails on update', function (): void {
    $existing = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('jane@example.com')
        ->create();

    $data = new ContactData(
        name: 'Jane',
        phones: [new PhoneNumber('+61412345678')],
        emails: [new EmailAddress('jane@example.com')],
    );

    $contact = resolve(UpsertContact::class)->execute($data, $existing->id);

    expect($contact->phones()->pluck('e164')->map(fn ($p): string => (string) $p)->all())->toBe(['+61412345678'])
        ->and($contact->emails()->pluck('address')->map(fn ($e): string => (string) $e)->all())->toBe(['jane@example.com']);
});

test('rolls back the entire transaction when a child write fails mid-flight', function (): void {
    $existing = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('old@example.com')
        ->create(['name' => 'Original']);

    DB::statement('DROP TABLE contact_emails');

    $data = new ContactData(
        name: 'Updated',
        phones: [new PhoneNumber('+64211234567')],
        emails: [new EmailAddress('new@example.com')],
    );

    expect(fn () => resolve(UpsertContact::class)->execute($data, $existing->id))
        ->toThrow(QueryException::class);

    $reloaded = Contact::query()->find($existing->id);

    expect($reloaded?->name)->toBe('Original')
        ->and($reloaded?->phones()->pluck('e164')->map(fn ($p): string => (string) $p)->all())->toBe(['+61412345678']);
});
