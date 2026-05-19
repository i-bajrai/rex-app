<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Domain\Contact\Actions\DeleteContact;
use Domain\Contact\Exceptions\ContactNotFoundException;

test('deletes the contact and cascades to phones and emails', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('jane@example.com')
        ->create();

    resolve(DeleteContact::class)->execute($contact->id);

    expect(Contact::query()->find($contact->id))->toBeNull()
        ->and(ContactPhone::query()->where('contact_id', $contact->id)->count())->toBe(0)
        ->and(ContactEmail::query()->where('contact_id', $contact->id)->count())->toBe(0);
});

test('throws ContactNotFoundException when the contact does not exist', function (): void {
    expect(fn () => resolve(DeleteContact::class)->execute(999_999))
        ->toThrow(ContactNotFoundException::class);
});
