<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Actions\GetContact;
use Domain\Contact\Exceptions\ContactNotFoundException;

test('returns the contact with eager-loaded phones and emails', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('jane@example.com')
        ->create();

    $result = resolve(GetContact::class)->execute($contact->id);

    expect($result->id)->toBe($contact->id)
        ->and($result->relationLoaded('phones'))->toBeTrue()
        ->and($result->relationLoaded('emails'))->toBeTrue()
        ->and($result->phones)->toHaveCount(1)
        ->and($result->emails)->toHaveCount(1);
});

test('throws ContactNotFoundException when the contact id is missing', function (): void {
    try {
        resolve(GetContact::class)->execute(987_654);
        $this->fail('expected ContactNotFoundException');
    } catch (ContactNotFoundException $contactNotFoundException) {
        expect($contactNotFoundException->errorCode)->toBe('contact.not_found')
            ->and($contactNotFoundException->id)->toBe(987_654);
    }
});
