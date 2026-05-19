<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;

test('phones relationship returns the contact phones', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->withPhone('+61412345679')
        ->create();

    $phones = $contact->phones()->get();

    expect($phones)->toHaveCount(2)
        ->and($phones->each(fn (ContactPhone $phone): PhoneNumber => $phone->e164))
        ->each->toBeInstanceOf(ContactPhone::class);
});

test('emails relationship returns the contact emails', function (): void {
    $contact = Contact::factory()
        ->withEmail('first@example.com')
        ->withEmail('second@example.com')
        ->create();

    $emails = $contact->emails()->get();

    expect($emails)->toHaveCount(2)
        ->and($emails->each(fn (ContactEmail $email): EmailAddress => $email->address))
        ->each->toBeInstanceOf(ContactEmail::class);
});

test('primaryPhone returns the oldest phone by created_at', function (): void {
    $contact = Contact::factory()->create();

    $older = ContactPhone::factory()->recycle($contact)->create([
        'e164' => '+61412345678',
        'created_at' => now()->subMinutes(5),
    ]);

    ContactPhone::factory()->recycle($contact)->create([
        'e164' => '+61412345679',
        'created_at' => now(),
    ]);

    $primary = $contact->primaryPhone()->first();

    expect($primary)->not->toBeNull()
        ->and($primary->id)->toBe($older->id);
});

test('casts surface id and timestamps as the declared types', function (): void {
    $contact = Contact::factory()->create()->refresh();

    expect($contact->id)->toBeInt()
        ->and($contact->name)->toBeString()
        ->and($contact->created_at)->not->toBeNull()
        ->and($contact->updated_at)->not->toBeNull();
});
